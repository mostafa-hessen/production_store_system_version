## 1-11-2025
لبات العميل التي تمت 
1-  صفحه الفاتوره 
** تثبيت شريط البحث
** تغيير زر نقدي ثابت
**زر البيع القطاعي 
**  اضافه الخصم 
** اجمالي الربح يظهر في شكل علي اليمين 
** تغيير شكل زر المؤجل والمدفوع 
** تعديل جزء الطباعه حتي يشمل فكره الخصم


2- صفحه المنتجات 
** اضافه منتح اضافه سعر القطاعي ك حقل
** تعديل اضافه تعديل المنتج  لااضافه حقل سعر القطاعي
** حل مشكله طول القسم الخاص بعرض المنتجات 
** اظهار سعر البيع القطاعي بدل سعر الشراء الاساسي + عمل تصميم يجذب الانتباه

3- صفحه اجمالي الارباح 
** 
حل مشكله الطباعه لتشمل طباعه التكلفه والبيع والربح


4- قاعده البيانات
** تعديل جدول المنتجات اضافه خاصيه البيع القطاعي
** تعديل بنيه invoice_out_items and invoice_out
ALTER TABLE `invoice_out`
  ADD COLUMN IF NOT EXISTS `total_before_discount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'مجموع البيع قبل أي خصم',
  ADD COLUMN IF NOT EXISTS `discount_type` ENUM('percent','amount') NOT NULL DEFAULT 'percent' COMMENT 'نوع الخصم',
  ADD COLUMN IF NOT EXISTS `discount_value` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'قيمة الخصم: إذا percent -> تخزن النسبة وإلا قيمة المبلغ',
  ADD COLUMN IF NOT EXISTS `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'مبلغ الخصم المحسوب بالعملة',
  ADD COLUMN IF NOT EXISTS `total_after_discount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'المجموع النهائي بعد الخصم',
  ADD COLUMN IF NOT EXISTS `total_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'اجمالي التكلفة (مخزن للتقارير)',
  ADD COLUMN IF NOT EXISTS `profit_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'اجمالي الربح = total_before_discount - total_cost';

ALTER TABLE `products`
  ADD COLUMN IF NOT EXISTS `retail_price` DECIMAL(10,2) DEFAULT NULL COMMENT 'سعر البيع القطاعي (للعميل الفرد)';


## 20-11-2025
-- creat_invoice --> edites
-- dataBase --> edites look at edites.text
# التعديلات الكاملة على قاعدة البيانات (بالترتيب)

## 1. إضافة الحقول الجديدة لجدول `invoices_out`

```sql
-- الخطوة 1: إضافة الحقول الجديدة
ALTER TABLE invoices_out 
ADD COLUMN paid_amount DECIMAL(12,2) DEFAULT 0.00,
ADD COLUMN remaining_amount DECIMAL(12,2) DEFAULT 0.00;
```

## 2. إنشاء جدول المدفوعات الجديد

```sql
-- الخطوة 2: إنشاء جدول المدفوعات
CREATE TABLE `invoice_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `payment_amount` decimal(12,2) NOT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `payment_method` enum('cash','bank_transfer','check','card') DEFAULT 'cash',
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_payment_invoice` (`invoice_id`),
  KEY `fk_payment_user` (`created_by`),
  CONSTRAINT `fk_payment_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices_out` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payment_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
);
```

## 3. تحديث البيانات القديمة (الفواتير المدفوعة)

```sql
-- الخطوة 3: تحديث الفواتير المدفوعة قديماً
UPDATE invoices_out 
SET paid_amount = COALESCE(total_after_discount, total_before_discount),
    remaining_amount = 0
WHERE delivered = 'yes' 
AND (paid_amount = 0 OR paid_amount IS NULL);
```

## 4. إنشاء مدفوعات افتراضية للبيانات القديمة

```sql
-- الخطوة 4: إنشاء سجلات مدفوعات للفواتير المدفوعة قديماً
INSERT INTO invoice_payments (invoice_id, payment_amount, payment_method, notes, created_by, created_at)
SELECT 
    id,
    COALESCE(total_after_discount,0),
    'cash',
    'دفعة تلقائية - ترحيل من النظام القديم',
    COALESCE(created_by),
    created_at
FROM invoices_out 
WHERE delivered = 'yes'
AND NOT EXISTS (
    SELECT 1 FROM invoice_payments WHERE invoice_id = invoices_out.id
);
```

## 5. تحديث الفواتير المؤجلة

```sql
-- الخطوة 5: تحديث الفواتير المؤجلة
UPDATE invoices_out 
SET paid_amount = 0,
    remaining_amount = COALESCE(total_after_discount, total_before_discount, 0)
WHERE delivered = 'no' 
AND (paid_amount = 0 OR paid_amount IS NULL);
```

ALTER TABLE invoice_out
MODIFY delivered ENUM('yes','no','canceled','reverted','partial')
NOT NULL DEFAULT 'no';




## 25-11-2025

-- clint --> 
-- 
