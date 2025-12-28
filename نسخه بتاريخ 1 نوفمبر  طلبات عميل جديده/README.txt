طلبات العميل التي تمت 
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
