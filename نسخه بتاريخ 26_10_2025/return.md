جميل — نناقشها خطوة خطوة ونختار الحل العملي المناسب قبل التطبيق. سأشرح الخيارات، أين نضع زرّ الإلغاء (Cancel)، وكيف نطبّق **مرتجع** (Return) ومتى نستخدم كلّ واحد، ثم أعرض تصميم قاعدة البيانات والـ API والـ UI وPseudo-code للتنفيذ داخل معاملة (transaction). في النهاية أقدّم توصية واضحة عن أي جزء نطبّقه أولًا.

# 1) الفكرة العامة — فصل وظيفتين مختلفتين

أقترح فصل **وظيفتين** مختلفتين لأن كل واحدة لها متطلبات واعية مختلفة:

* **إلغاء الفاتورة (Cancel invoice)**

  * مخصّص للحالات **قبل** تسليم الفاتورة أو قبل إغلاقها/تحصيلها — بمعنى الفاتورة لازالت في الـ *pending* ولم تُسجّل كـ delivered/paid نهائيًا.
  * باختصار: "تلغي" الفاتورة وتعيد كميات البنود إلى الدفعات (batches) عن طريق إعادة `remaining`، وتُسجّل حدث الـ cancel في السجل.
  * يتم عبر زرّ **في صفحة pending_invoices** (وفي modal تفاصيل الفاتورة أيضاً).

* **مرتجع / إرجاع بعد التسليم (Return / Credit note / Refund)**

  * مخصّص للحالات **بعد** أن تمّ تسليم الفاتورة (delivered) أو بعد أن دفع الزبون.
  * هو عملية تجارية: قد تتضمّن استرداد نقدي أو استبدال أو إعادة للبضاعة للمخزون. يجب تسجيلها كسجل مرتجع مستقل (return record) مع تخصيصات، وعدم حذف الفاتورة الأصلية.
  * يتم عبر زرّ **في صفحة delivered_invoices** ضمن تفاصيل الفاتورة: "Process return" أو "Create credit/return".

# 2) أين نضع الأزرار في الواجهة (UX)

* **pending_invoices list / modal**

  * زرّ صغير أحمر: "إلغاء الفاتورة" لكل صف (أو داخل شاشة تفاصيل الفاتورة).
  * عند الضغط: modal تأكيد + حقل نصي لسبب الإلغاء + زر تأكيد.
  * المسموح: فقط للمستخدمين بصلاحية (role = admin أو permission = cancel_invoice).

* **delivered_invoices list / modal**

  * زرّ: "عمل مرتجع" (Create Return) + زر "عرض المرتجعات" إن وُجدت.
  * داخل modal: اختيار البنود والكميات المراد إرجاعها (partial return) وحقل سبب، وطريقة الاسترداد (نقدي/رصيد/بديل).
  * النتيجة: إنشاء سجل في جدول `returns` و`return_items` و`return_allocations`، وتحديث `batches` أو إضافة دفعات جديدة حسب طريقة الإرجاع.

# 3) قواعد العمل (Business rules) المقترحة

* **Cancel**: مسموح فقط قبل `delivered = 'yes'` (أو قبل `status = paid`). إلغاء كامل الفاتورة (لا جزئي). يعيد الكميات المخصّصة للـ batches ويضع `invoices_out.status = 'cancelled'` و`cancelled_by`, `cancelled_at`, `cancel_reason`. الاحتفاظ بالـ invoice record أفضل من الحذف لإبقاء السجل المحاسبي.
* **Return**: يسمح جزئياً أو كليًا بعد التسليم. لا نحذف الفاتورة الأصلية؛ نُنشئ سجل مرتجع مرتبطًا بالفاتورة (credit note). نحدِّد ما إذا كنا:

  * نعيد الكميات إلى نفس الدفعات (best for preserving cost history) أو
  * نُضيف دفعات استرجاع جديدة (restock batch) مع تكلفة الوحدة بنفس تكلفة البيع/التخصيص الأصلي أو تكلفة محددة من قبل المدير.
  * نحدّد طريقة التعامل مع الـ COGS: تعديل تقرير الـ COGS عند المرتجعات أو تركه كما هو مع سجل المرتجع منفصل.

# 4) تصميم قاعدة البيانات المقترح للمرتجعات والإلغاءات

(أقترح إضافة الجداول التالية للحفاظ على تاريخ التخصيص وعدم فقدان المعلومات)

```sql
-- سجل إلغاءات (يمكن الاكتفاء بحقل status في invoices_out لكن مفيد لسجل مفصّل)
CREATE TABLE invoice_cancellations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_out_id INT NOT NULL,
  cancelled_by INT NOT NULL,
  cancelled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reason TEXT,
  FOREIGN KEY (invoice_out_id) REFERENCES invoices_out(id)
);

-- سجل المرتجعات (Credit/Return header)
CREATE TABLE returns (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_out_id INT NOT NULL, -- الفاتورة المرتبطة
  created_by INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  total_refund DECIMAL(12,2) DEFAULT 0,
  status ENUM('pending','processed','rejected') DEFAULT 'processed',
  note TEXT,
  FOREIGN KEY (invoice_out_id) REFERENCES invoices_out(id)
);

CREATE TABLE return_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  return_id INT NOT NULL,
  invoice_out_item_id INT NOT NULL, -- اي بند من الفاتورة الاصلية
  product_id INT NOT NULL,
  qty DECIMAL(12,2) NOT NULL,
  unit_price DECIMAL(12,4) NOT NULL, -- price refunded per unit
  total_amount DECIMAL(12,2) NOT NULL,
  FOREIGN KEY (return_id) REFERENCES returns(id),
  FOREIGN KEY (invoice_out_item_id) REFERENCES invoice_out_items(id)
);

-- ان اردنا تتبع كيف تم اعادة الكميات للدفعات (reverse allocations)
CREATE TABLE return_allocations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  return_item_id INT NOT NULL,
  batch_id INT, -- الدفعة التي استعيدت إليها
  qty DECIMAL(12,2) NOT NULL,
  unit_cost DECIMAL(12,4),
  FOREIGN KEY (return_item_id) REFERENCES return_items(id),
  FOREIGN KEY (batch_id) REFERENCES batches(id)
);
```

# 5) تنفيذ Cancel — الخطة التفصيلية (الـ safest)

**منطق العمل** (Cancel full invoice):

1. تحقق: الفاتورة موجودة، حالتُها `delivered = 'no'` (أو status != 'cancelled'), وصلاحية المستخدم.
2. ابدأ معاملة (BEGIN TRANSACTION).
3. احصل على `invoice_out_items` و`sale_item_allocations` المرتبطة بهذه الفاتورة.
4. لكل `allocation`:

   * `UPDATE batches SET remaining = remaining + allocation.qty_taken WHERE id = allocation.batch_id;`
   * إذا كانت الدفعة status = 'consumed' و resultant remaining > 0 -> ضعها active/available.
5. احذف/أو علّم `sale_item_allocations` المرتبطة بالـ invoice (أو انقلها إلى سجل archived — لكن الحذف البسيط ممكن إذا سجلنا الإلغاء في جدول `invoice_cancellations`).
6. احذف/علّم `invoice_out_items` كـ cancelled (أفضل: أضف حقل `status` بدل الحذف).
7. ضع `invoices_out.status = 'cancelled'` وأضف سجل في `invoice_cancellations`.
8. COMMIT.
9. إرجاع JSON نجاح.

> ملاحظة مهمة: لا تحذف السجلات نهائيًا — ضع status أو سجل إلغاء حتى تبقى audit trail.

## مثال pseudocode (PHP PDO)

```php
// assume $pdo is PDO, $invoice_id, $user_id, $reason present
$pdo->beginTransaction();

try {
  // 1. lock invoice row
  $stmt = $pdo->prepare("SELECT id, delivered, status FROM invoices_out WHERE id = ? FOR UPDATE");
  $stmt->execute([$invoice_id]);
  $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$invoice) throw new Exception("Invoice not found");
  if ($invoice['delivered'] === 'yes') throw new Exception("Cannot cancel delivered invoice");

  // 2. get allocations
  $allocSt = $pdo->prepare("
    SELECT sa.id as alloc_id, sa.batch_id, sa.qty_taken
    FROM sale_item_allocations sa
    JOIN invoice_out_items ioi ON ioi.id = sa.invoice_out_item_id
    WHERE ioi.invoice_out_id = ?
    FOR UPDATE
  ");
  $allocSt->execute([$invoice_id]);
  $allocs = $allocSt->fetchAll(PDO::FETCH_ASSOC);

  foreach ($allocs as $a) {
    // update batch remaining
    $upd = $pdo->prepare("UPDATE batches SET remaining = remaining + ? WHERE id = ?");
    $upd->execute([$a['qty_taken'], $a['batch_id']]);
    // optionally reactivate batch if was consumed
    $upd2 = $pdo->prepare("UPDATE batches SET status = 'active' WHERE id = ? AND remaining > 0");
    $upd2->execute([$a['batch_id']]);
  }

  // delete allocations
  $delAlloc = $pdo->prepare("
    DELETE sa FROM sale_item_allocations sa
    JOIN invoice_out_items ioi ON ioi.id = sa.invoice_out_item_id
    WHERE ioi.invoice_out_id = ?
  ");
  $delAlloc->execute([$invoice_id]);

  // mark invoice items as cancelled (or delete)
  $pdo->prepare("UPDATE invoice_out_items SET status='cancelled' WHERE invoice_out_id = ?")->execute([$invoice_id]);

  // mark invoice
  $pdo->prepare("UPDATE invoices_out SET status='cancelled', cancelled_by=?, cancelled_at=NOW() WHERE id = ?")
      ->execute([$user_id, $invoice_id]);

  // insert into invoice_cancellations
  $pdo->prepare("INSERT INTO invoice_cancellations (invoice_out_id, cancelled_by, reason) VALUES (?, ?, ?)")
      ->execute([$invoice_id, $user_id, $reason]);

  $pdo->commit();
  echo json_encode(['success'=>true,'message'=>'Invoice cancelled']);
} catch (Exception $e) {
  $pdo->rollBack();
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
```

# 6) تنفيذ Return — الخطة التفصيلية

**منطق العمل** (Partial/Full return after delivery):

1. واجهة: المستخدم يختار بنود وكميات مرتجعة، وسبب، وطريقة رد الفلوس.
2. في السيرفر: ابدأ transaction.
3. أنشئ سجل `returns` مرتبط بالـ `invoice_out_id`.
4. لكل بند مرتجع:

   * أنشئ `return_items` مع `invoice_out_item_id` وكمية المرتجع والسعر المرجعي.
   * لإعادة المخزون: إما

     * أ) أعد الكميات إلى نفس الدفعات الأصلية (insert return_allocations أو update batches.remaining += qty) — مع الاحتفاظ بتاريخ التكلفة الأصلي (unit_cost من sale_item_allocations الأصلية)
     * ب) أو أنشئ دفعة جديدة في `batches` (نوع batch = restock_from_return) مع remaining = qty وunit_cost = قيمة نريدها. هذا أسهل لتتبّع ومنع خلط بالدفعات القديمة.
5. حدّث سجلات الـ invoice_out_items لتخفيض الكمية المباعة أو تسجيل أنها مرتجع جزئي (مثلاً field returned_qty).
6. حدّث الحقول المالية: اعادة احتساب total_cogs أو فقط تسجيل الـ return كقيد محاسبي منفصل.
7. COMMIT.
8. إصدار فاتورة مرتجع/اشعار دائن إن لزم.

> أنصح الخيار (ب) — إنشاء دفعات restock جديدة — لأنه أوضح تاريخيًا ويمنع تعقيد دمج دفعات مدتها قديمة. لكن إن أردنا المحافظة على نفس تكلفة الشراء للـ COGS، نستخدم unit_cost من التخصيص الأصلي عند صنع دفعة restock.

# 7) حالات خاصة واعتبارات تقنية

* **التزامن**: استخدام `FOR UPDATE` على دفعات و/أو allocations داخل transaction لمنع فقدان/خلاف في الكميات.
* **Partial cancel**: هل نسمح بإلغاء بنود فقط من الفاتورة؟ (إذا كنّا سندعم ذلك، يصبح الأمر أقرب للـ Return حتى لو كانت الفاتورة pending).
* **تقرير COGS**: كيف نتعامل مع COGS عند المرتجعات؟ إما نعدّل تقرير الفترة أم ننشئ قيود عكسية (recommended: قيود عكسية عبر جدول returns بحيث تقارير COGS يمكن احتسابها بطرح مرتجعات).
* **التدقيق والموافقة**: قد تحتاج مرتجعات لقِيم مالية كبيرة إلى موافقة مدير قبل تنفيذ إعادة المخزون/رد المبلغ.
* **تاريخ التكلفة**: الاحتفاظ بالـ unit_cost على مستوى allocations مفيد للغاية عند إنشاء دفعات restock من المرتجعات حتى نعرف تكلفة الاسترجاع.

# 8) نقاط أمنيّة / صلاحيات

* تحقق دائمًا من صلاحية المستخدم (ROLE/PERMS) قبل تنفيذ cancel/return.
* سجل من قام بالإجراء ووقت الإجراء والسبب.
* تمنع الإلغاء الآلي بعد فترة زمنية (مثلاً 30 يوم) حسب السياسة التجارية إن لزم.

# 9) توصية عملية فوراً — ماذا نطبّق أولًا؟

أقترح التطبيق بالترتيب التالي (أقل مخاطرة → أكبر فائدة):

1. **زر Cancel** في `pending_invoices` + الكود الذي أعاد `batches.remaining` ثم يعين `invoices_out.status='cancelled'` مع سجل في `invoice_cancellations`. (هذا بسيط نسبياً ويحل مشاكل إلغاء الفواتير المعلقة).
2. **زر Create Return** في `delivered_invoices` مع نموذج لإنشاء return بسيط يضيف سجل في `returns` و`return_items` ويُنشئ دفعات restock (option B).
3. لاحقًا: تقارير تعديلية للـ COGS، واجهة لإدارة المرتجعات، وخيارات الموافقة.

# 10) هل أبدأ بالتنفيذ الآن؟

أستطيع:

* أ) أكتب لك كود PHP + SQL جاهز للتضمين في `pending_invoices.php` لتنفيذ زر الإلغاء (مع modal، AJAX endpoint, وPDO transaction).
* ب) أو أكتب تصميم الـ API وملفات SQL لإنشاء جداول `returns` و`return_items` + مثال تنفيذ لعملية مرتجع جزئي.

اختر أي خيار تريدني أبدأ به الآن — سأرسل لك الكود الكامل (front-end modal + AJAX + back-end PHP) للتنفيذ مباشرة.




لينك الشات
https://chatgpt.com/c/68da4272-b388-832e-ac0f-9acfb4db541d