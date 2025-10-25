جميل — هنمشي خطوة بخطوة من الألف للياء عشان تنفّذ **فكرة المرتجعات** في مشروعك (بما يتوافق مع الهيكل اللي عندك: `invoices_out`, `invoice_out_items`, `batches`, `sale_item_allocations`، إلخ). هديك خطة عملية + شفرات جاهزة (SQL / PHP / HTML / JS) + اختبارات ونصائح دمج. اتبع كل خطوة بالترتيب وطبّقها على بيئة التطوير أولًا قبل الإنتاج.

# ملخص سريع للخطوات

1. نسخة احتياطية وبيئة تطوير
2. تصميم قواعد البيانات (migrations)
3. واجهة المستخدم (زرود + modal)
4. جافاسكربت: فتح المودال، ملء الكميات، حساب الإجمالي، الإرسال
5. باك-إند: `returns_action.php` (PDO + transaction + FOR UPDATE)
6. استراتيجية التخصيص / إعادة إلى الدفعات (خيارات)
7. أمان وصلاحيات + التحقق من الجهة الخادمية
8. تحديث الواجهة فوراً (DOM) أو إعادة التحميل
9. اختبارات يدوية ووحدات للتأكد من الاتساق
10. تقارير وقيود محاسبية (اختياري)
11. نشر / نسخ احتياطية / خطوات أخيرة
12. تحسينات مستقبلية

---

# 0) prerequisites (قبل البدء)

* اصنع نسخة احتياطية من قاعدة البيانات. لا تعمل migrations على الإنتاج قبل تجربة staging.
* تأكد أن مشروعك يستخدم PDO مع `ERRMODE_EXCEPTION`.
* لديك جلسة مستخدم و`$_SESSION['csrf_token']` و دوال `current_user_id()` و `current_user_has_perm()` أو ما يماثلها.
* اعمل branch جديد في git (مثلاً `feature/returns`).

---

# 1) миграشن قاعدة البيانات (SQL)

نفّذ هذه الاستعلامات في migration (بعد أخذ backup):

```sql
-- جدول رأس المرتجعات
CREATE TABLE IF NOT EXISTS `returns` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_out_id` INT NOT NULL,
  `created_by` INT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `total_refund` DECIMAL(12,2) DEFAULT 0,
  `refund_method` ENUM('cash','credit','exchange') DEFAULT 'cash',
  `status` ENUM('processed','pending','rejected') DEFAULT 'processed',
  `note` TEXT,
  FOREIGN KEY (`invoice_out_id`) REFERENCES `invoices_out`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `return_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `return_id` INT NOT NULL,
  `invoice_out_item_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `qty` DECIMAL(12,4) NOT NULL,
  `unit_price` DECIMAL(12,4) NOT NULL,
  `total_amount` DECIMAL(12,2) NOT NULL,
  FOREIGN KEY (`return_id`) REFERENCES `returns`(`id`),
  FOREIGN KEY (`invoice_out_item_id`) REFERENCES `invoice_out_items`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `return_allocations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `return_item_id` INT NOT NULL,
  `batch_id` INT NULL,
  `qty` DECIMAL(12,4) NOT NULL,
  `unit_cost` DECIMAL(12,4) NULL,
  FOREIGN KEY (`return_item_id`) REFERENCES `return_items`(`id`),
  FOREIGN KEY (`batch_id`) REFERENCES `batches`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- اضافة حقل returned_qty اذا غير موجود
ALTER TABLE invoice_out_items 
  ADD COLUMN IF NOT EXISTS returned_qty DECIMAL(12,4) NOT NULL DEFAULT 0;

-- اضافة حقل type وتاريخ في batches (ان لم تكن موجودة)
ALTER TABLE batches 
  ADD COLUMN IF NOT EXISTS `type` VARCHAR(50) DEFAULT 'purchase',
  ADD COLUMN IF NOT EXISTS `created_at` DATETIME NULL;
```

> ملاحظة: لو MySQL/MariaDB قديم ولا يدعم `IF NOT EXISTS` في `ALTER`, نفّذ فحص وجود العمود قبل ALTER أو استخدم migration tool.

---

# 2) تصميم الواجهة — أين تضع الأزرار والمودال

أضف:

* زر **مرتجع كامل** في صف الفاتورة داخل قائمة الفواتير (action button). عند الضغط يستدعي `confirmFullReturn(invId)`.
* زر **إضافة مرتجع** داخل صفحة عرض الفاتورة (invoice view).
* Modal لإنشاء المرتجع يحتوي على صفوف البنود مع inputs: `return_qty`, `unit_price`، و hidden `invoice_id`, `csrf_token`.
  مثال مبسّط للمودال:

```html
<button id="btnCreateReturn" class="btn btn-warning">إنشاء مرتجع</button>

<div id="returnModal" class="modal" style="display:none;">
  <div class="modal-dialog">
    <div class="modal-content p-3">
      <div class="modal-header">
        <h5>إنشاء مرتجع للفاتورة #<span id="returnInvoiceNumber"></span></h5>
        <button id="closeReturnModal" class="close">&times;</button>
      </div>
      <form id="returnForm">
        <input type="hidden" name="invoice_id" value="">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
        <table id="returnItemsTable" class="table">
          <thead>...</thead>
          <tbody><!-- صفوف البنود (server-side أو JS populated) --></tbody>
        </table>
        <select name="refund_method">...</select>
        <textarea name="note"></textarea>
        <div id="returnTotal">إجمالي المرتجع: 0</div>
        <button id="cancelReturn" type="button">إلغاء</button>
        <button type="submit">تأكيد</button>
      </form>
    </div>
  </div>
</div>
```

نقطة مهمة: إذا صفحة `invoice_view.php` بالفعل تحتوي على قائمة البنود، استخدم نفس البيانات لملء modal بدلاً من إعادة استعلام قاعدة بيانات.

---

# 3) JavaScript — وظائف أساسية (مفتاح التنفيذ)

الوظائف المطلوبة:

* `confirmFullReturn(invId)` — يفتح المودال، يملأ حقول `return_qty = sold - returned` لكل بند، يضع refund_method افتراضي، ويترك المستخدم يراجع ثم يضغط تأكيد. (أو مع خيار auto-submit كما طبقت سابقًا).
* `fillFullQuantitiesForInvoice(invId)` — يملي الحقول بالقيم القصوى.
* `recalcTotal()` — يحسب الإجمالي ويعرضه.
* `submitReturnForm()` — يجمع `FormData` ويرسل `fetch('returns_action.php', {method:'POST', credentials:'same-origin', body: fd})`, يتعامل مع JSON response.
* التعامل مع الأخطاء: تظهر رسائل واضحة للمستخدم.

مثال مختصر للجافاسكربت (أعدت استخدام الشفرة التي أرسلتها لك سابقًا — استبدل المسارات والتسمية بحسب مشروعك):

```js
// key functions: open modal, fill, recalc, submit
async function submitReturnForm() {
  const form = document.getElementById('returnForm');
  const fd = new FormData(form);
  const res = await fetch('returns_action.php', { method:'POST', credentials:'same-origin', body: fd });
  const json = await res.json();
  if (json.success) {
    alert('تم إنشاء المرتجع #' + json.return_id);
    location.reload();
  } else {
    alert('خطأ: ' + json.error);
  }
}
```

> تأكد أن كل حقل `return_qty` و `unit_price` يحمل اسم مثل `qty[<invoice_item_id>]` و `unit_price[<invoice_item_id>]` لكي يسهُل على الباك-إند قراءتها كمصفوفات.

---

# 4) الباك-إند — `returns_action.php` (مهم جداً)

المتطلّبات: PDO، session، CSRF، صلاحيات، transaction، `FOR UPDATE`، rollback عند الخطأ.

السلوك الأساسي:

1. استلم POST: `invoice_id`, `csrf_token`, `refund_method`, `note`, `qty[ioi_id]`, `unit_price[ioi_id]`.
2. تحقق CSRF و صلاحيات المستخدم (`create_return`).
3. اجمع العناصر ذات qty>0.
4. ابدأ transaction، اقفل الفاتورة وصفوف البنود:

   ```sql
   SELECT * FROM invoices_out WHERE id = ? FOR UPDATE;
   SELECT * FROM invoice_out_items WHERE id = ? FOR UPDATE;
   ```
5. لكل بند: تحقق `return_qty <= sold_qty - returned_qty`.
6. أدخل `returns` (header) ثم `return_items`.
7. طريقة تخصيص الإرجاع: إما إنشاء `batches` جديدة من نوع `restock_return`، أو إعادة ربط إلى الدفعات الأصلية (انظر فقرة 6).
8. حدّث `invoice_out_items.returned_qty += return_qty`.
9. احسب `total_refund` وقم بتحديثه في جدول `returns`.
10. commit ثم أعد JSON success.

لقد أرسلت لك سابقًا ملف PHP كامل؛ استخدمه كقاعدة — تأكد من إضافة سجلات في جدول المدفوعات إذا استدعاه `refund_method`.

---

# 5) استراتيجية التخصيص (هام)

عند إرجاع كمية، لديك خياران رئيسيان:

A) **إنشاء دفعات restock جديدة** (مبسط وآمن)

* كل مرتجع يولّد دفعة جديدة في `batches` مع `type = 'restock_return'`, `remaining = returned_qty`, `unit_cost` إما من `sale_item_allocations.unit_cost` أو NULL.
* ثم أدخل سجل في `return_allocations` يشير إلى هذه الدفعة.
* ميزة: لا تغيّر تاريخ دفعات الشراء الأصلية، وتحافظ على تتبع واضح للكمية المعادة.

B) **إرجاع الكميات إلى نفس الدفعات الأصلية** (مركّب ومناسب للحسـاب الدقيق للتكلفة)

* تحتاج لتتبع كيف تم تخصيص المبيعات إلى دفعات (جدول `sale_item_allocations`).
* عند إرجاع، يجب أن تعكس بطريقة عكسية التخصيص (تقليل `sale_item_allocations.qty_allocated` أو إنشاء تخصيص إلغاء).
* أصعب لأن تكلفة الوحدة قد تختلف عبر دفعات.

**توصية:** ابدأ بالـ A (restock batch) لأنها أبسط وتعمل لمعظم الحالات. بعد كده أضف خيارًا في الإعدادات لإرجاع الكميات "إلى نفس الدفعات" إذا احتاج المحاسب ذلك.

---

# 6) الأمان والتحقق (لا تعتمد فقط على الواجهة)

* تحقق من CSRF في الخادم.
* تحقق أن المستخدم لديه صلاحية `create_return`.
* على الخادم تأكد من `return_qty` نوعه عدد موجب و ≤ `sold - returned`.
* استخدم prepared statements للـ SQL (PDO).
* سجل العمليات المهمة (audit log): من؟ متى؟ أي فواتير؟ أي بنود؟ الكمية؟

---

# 7) التزامن (Concurrency)

* استخدم `BEGIN; SELECT ... FOR UPDATE; ... COMMIT;` على `invoices_out` و `invoice_out_items`.
* لو تعدد مستخدمين يرجعون من نفس الفاتورة في نفس الوقت، القفل يمنع الإرجاع الزائد.
* إذا أيضاً تعديل على `batches`، قد تحتاج لقفل `batches` المعنية أو التعامل مع `remaining` بطريقة آمنة.

---

# 8) تحديث الواجهة فورياً

بعد نجاح الإرجاع:

* ببساطة يمكنك `location.reload()` (أسهل).
* أو حدّث DOM: عدّل العنصر `.returned_qty` لكل بند، حدّث badge عدد المرتجعات، وربما ضع أيقونة صغيرة تبين وجود مرجع.
* احتفظ بمقاييس realtime إن أردت (websocket) لكن هذا اختياري.

---

# 9) اختبارات يدوية مقترحة

1. **مرتجع كامل** لفاتورة حديثة — يجب إنشاء دفعات restock و زيادة returned_qty.
2. **مرتجع جزئي** لبند مختلط — تحقق الحساب الصحيح.
3. **محاولة تجاوز الكمية** (return_qty > sold-returned) — يجب الرفض برسالة واضحة.
4. **خطأ نصف الطريق**: أثناء معالجة مرتجع متعدد البنود رمي استثناء بعد أول بند — يجب أن تتم rollback بالكامل.
5. **تزامن**: افتح نافذتين وحاول مرتجع متقاطع للتحقق من القفل.
6. تحقق من القيم المالية: إجمالي المرتجع يساوي مجموع (qty * unit_price).

للاختبار يمكنك استخدام PHP Unit أو اختبارات تكاملية مع بيانات اختبارية.

---

# 10) استعلامات تحقق بعد التنفيذ (SQL)

* تحقق مجموع `returned_qty` لكل بند:

```sql
SELECT id, qty AS sold, returned_qty FROM invoice_out_items WHERE invoice_out_id = 123;
```

* تحقق دفعات الـ restock أنشأت:

```sql
SELECT * FROM batches WHERE type = 'restock_return' AND created_at > '2025-01-01';
```

* تحقق جدول return_items و return_allocations المرتبطة:

```sql
SELECT ri.*, ra.batch_id FROM return_items ri
JOIN return_allocations ra ON ra.return_item_id = ri.id
WHERE ri.return_id = 456;
```

---

# 11) نشر (deployment) — checklist

1. اختبر على staging.
2. نفّذ migration بعد backup.
3. انشر الكود باختبار صغير (ex: create return على فاتورة تجريبية).
4. راقب الأخطاء (logs) و CPU/DB load (لأن القفل قد يزيد الحمل مؤقتًا).
5. اعلم الفريق المحاسبي بالتغييرات (خصوصًا إذا اخترت استراتيجيات تكلفة معينة).

---

# 12) تحسينات لاحقة (اقتراحات)

* وضع workflow موافقات: `returns.status = 'pending'` → يحتاج موافقة مدير.
* صفحة إدارة المرتجعات مع فلترة وتصدير CSV.
* ربط تلقائي لقيود محاسبية (journal entries) عند قبول المرتجع.
* إتاحة خيار “إرجاع لنفس الدفعات” بموافقة إداري.
* إشعارات للبائع/المخزن عند وصول مرتجعات.

---

## ختام — كيف أمشي معاك خطوة بخطوة الآن؟

لو تريد أمشي معاك «خطوة بخطوة» عملياً أعمل واحد من الآتي وأنا أقدملك الشفرة والـcommands مباشرة:

* ابدأ بـ **A**: أديك ملف migration جاهز + أمر SQL لتنفيذه.
* أو **B**: أديك HTML+JS جاهزين لتركيب المودال والزر في `invoice_view.php`.
* أو **C**: أقدملك `returns_action.php` كامل (معدّ لبيئتك) مع شرح أين تضعه وكيف تختبره.

قل لي أي واحدة تفضل أبدأ بها (A, B, أو C) وأنا أبعثلك الكود الدقيق جاهز للنسخ واللصق.
