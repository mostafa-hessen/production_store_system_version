-- Turn off FK checks to allow circular/any-order creation
SET FOREIGN_KEY_CHECKS = 0;

-- 1) settings (لا تعتمد على شيء)
CREATE TABLE `settings` (
  `setting_name` varchar(100) NOT NULL COMMENT 'اسم الإعداد',
  `setting_value` text DEFAULT NULL COMMENT 'قيمة الإعداد',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'آخر تحديث',
  PRIMARY KEY (`setting_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول إعدادات النظام العامة';

-- 2) users (مطلوب كمرجع لكثير من الجداول)
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `created_at`) VALUES
(5, 'admin', 'admin@gmail.com', '$2y$10$9DkMvN8bVe3xV3Mf5qd91O/0YyliyVLUVWVcy8NQkjmNo4.hDuvIq', 'admin', '2025-06-04 13:52:41');

-- 3) suppliers (يعتمد فقط على users كمفتاح اختياري)
CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'المعرف التلقائي للمورد',
  `name` varchar(200) NOT NULL COMMENT 'اسم المورد',
  `mobile` varchar(11) NOT NULL COMMENT 'رقم موبايل المورد (11 رقم)',
  `city` varchar(100) NOT NULL COMMENT 'مدينة المورد',
  `address` text DEFAULT NULL COMMENT 'عنوان المورد التفصيلي (اختياري)',
  `commercial_register` varchar(100) DEFAULT NULL COMMENT 'رقم السجل التجاري (اختياري ولكنه فريد إذا أدخل)',
  `created_by` int(11) DEFAULT NULL COMMENT 'معرف المستخدم الذي أضاف المورد (FK to users.id)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'تاريخ إضافة المورد',
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'تاريخ آخر تعديل لبيانات المورد',
  PRIMARY KEY (`id`),
  UNIQUE KEY `mobile` (`mobile`),
  UNIQUE KEY `commercial_register` (`commercial_register`),
  KEY `fk_supplier_user_creator` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول بيانات الموردين';

-- 4) expense_categories (لا تعتمد)
CREATE TABLE `expense_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL COMMENT 'اسم فئة المصروف (مثال: نقل، كهرباء، إيجار)',
  `description` text DEFAULT NULL COMMENT 'وصف إضافي للفئة (اختياري)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='فئات المصاريف المختلفة';

-- 5) products (مطلوب قبل batches و purchase items)
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'المعرف التلقائي للمنتج',
  `product_code` varchar(50) NOT NULL COMMENT 'كود المنتج الفريد',
  `name` varchar(255) NOT NULL COMMENT 'اسم المنتج',
  `description` text DEFAULT NULL COMMENT 'وصف المنتج (اختياري)',
  `unit_of_measure` varchar(50) NOT NULL COMMENT 'وحدة القياس (مثال: قطعة، كجم، لتر)',
  `current_stock` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'الرصيد الحالي في المخزن',
  `reorder_level` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'حد إعادة الطلب (التنبيه عند وصول الرصيد إليه أو أقل)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'تاريخ الإنشاء',
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'تاريخ آخر تعديل',
  `cost_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `selling_price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_code` (`product_code`)
) ENGINE=InnoDB AUTO_INCREMENT=813 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول المنتجات المخزنة';

-- 6) customers (يعتمد على users)
CREATE TABLE `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL COMMENT 'اسم العميل',
  `mobile` varchar(11) NOT NULL COMMENT 'رقم الموبايل (11 رقم)',
  `city` varchar(100) NOT NULL COMMENT 'المدينة',
  `address` varchar(255) DEFAULT NULL COMMENT 'العنوان التفصيلي',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL COMMENT 'معرف المستخدم الذي أضاف العميل',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'تاريخ الإضافة',
  PRIMARY KEY (`id`),
  UNIQUE KEY `mobile` (`mobile`),
  KEY `fk_customer_user` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=88 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `customers` (`id`, `name`, `mobile`, `city`, `address`, `notes`, `created_by`, `created_at`) VALUES
(8, 'عميل نقدي', '12345678901', 'Fayoum', 'Fayoum, Egypt', '', 5, '2025-09-01 13:38:46');

-- 7) invoices_out (يعتمد على customers)
CREATE TABLE `invoices_out` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'المعرف التلقائي للفاتورة',
  `customer_id` int(11) NOT NULL COMMENT 'معرف العميل المرتبط بالفاتورة',
  `delivered` enum('yes','no') NOT NULL DEFAULT 'no' COMMENT 'هل تم التسليم؟ (نعم/لا)',
  `invoice_group` enum('group1','group2','group3','group4','group5','group6','group7','group8','group9','group10','group11') NOT NULL COMMENT 'مجموعة الفاتورة (من 1 إلى 11)',
  `created_by` int(11) DEFAULT NULL COMMENT 'معرف المستخدم الذي أنشأ الفاتورة',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'تاريخ ووقت الإنشاء',
  `updated_by` int(11) DEFAULT NULL COMMENT 'معرف المستخدم الذي آخر من عدل الفاتورة',
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'تاريخ ووقت آخر تعديل',
  `notes` text DEFAULT NULL,
  `cancel_reason` varchar(255) DEFAULT NULL,
  `revert_reason` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_invoice_customer` (`customer_id`),
  KEY `fk_invoice_creator` (`created_by`),
  KEY `fk_invoice_updater` (`updated_by`)
) ENGINE=InnoDB AUTO_INCREMENT=259 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول فواتير العملاء الصادرة';

-- 8) invoice_out_items (يعتمد على invoices_out و products)
CREATE TABLE `invoice_out_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'المعرف التلقائي لبند الفاتورة',
  `invoice_out_id` int(11) NOT NULL COMMENT 'معرف الفاتورة الصادرة (مفتاح أجنبي لجدول invoices_out)',
  `product_id` int(11) NOT NULL COMMENT 'معرف المنتج (مفتاح أجنبي لجدول products)',
  `quantity` decimal(10,2) NOT NULL COMMENT 'الكمية المباعة من المنتج',
  `total_price` decimal(10,2) NOT NULL COMMENT 'السعر الإجمالي للبند (الكمية * سعر الوحدة)',
  `cost_price_per_unit` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'تاريخ إضافة البند',
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'تاريخ آخر تعديل للبند',
  `selling_price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_invoice_item_to_invoice` (`invoice_out_id`),
  KEY `fk_invoice_item_to_product` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=293 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9) purchase_invoices (يعتمد على suppliers و users)
CREATE TABLE `purchase_invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'المعرف التلقائي لفاتورة الشراء',
  `supplier_id` int(11) NOT NULL COMMENT 'معرف المورد (FK to suppliers.id)',
  `supplier_invoice_number` varchar(100) DEFAULT NULL COMMENT 'رقم فاتورة المورد (قد يكون فريداً لكل مورد)',
  `purchase_date` date NOT NULL COMMENT 'تاريخ الشراء/الفاتورة',
  `notes` text DEFAULT NULL COMMENT 'ملاحظات إضافية على الفاتورة',
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'الإجمالي الكلي للفاتورة (يُحسب من البنود)',
  `status` enum('pending','partial_received','fully_received','cancelled') NOT NULL DEFAULT 'pending' COMMENT 'حالة فاتورة الشراء',
  `created_by` int(11) DEFAULT NULL COMMENT 'معرف المستخدم الذي أنشأ فاتورة الشراء (FK to users.id)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'تاريخ ووقت الإنشاء',
  `updated_by` int(11) DEFAULT NULL COMMENT 'معرف المستخدم الذي آخر من عدل بيانات رأس الفاتورة',
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'تاريخ آخر تعديل',
  `cancel_reason` varchar(255) DEFAULT NULL,
  `revert_reason` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_purchase_invoice_supplier` (`supplier_id`),
  KEY `fk_purchase_invoice_creator` (`created_by`),
  KEY `fk_purchase_invoice_updater` (`updated_by`)
) ENGINE=InnoDB AUTO_INCREMENT=349 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول فواتير المشتريات (الوارد)';

-- 10) purchase_invoice_items (يعتمد على purchase_invoices و products و batch_id ممكن يشير لبatches => circular)
CREATE TABLE `purchase_invoice_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'المعرف التلقائي لبند فاتورة الشراء',
  `batch_id` bigint(20) unsigned DEFAULT NULL,
  `purchase_invoice_id` int(11) NOT NULL COMMENT 'معرف فاتورة الشراء (FK to purchase_invoices.id)',
  `product_id` int(11) NOT NULL COMMENT 'معرف المنتج (FK to products.id)',
  `quantity` decimal(13,4) NOT NULL DEFAULT 0.0000,
  `cost_price_per_unit` decimal(10,2) NOT NULL COMMENT 'سعر التكلفة للوحدة من المورد',
  `total_cost` decimal(12,2) NOT NULL COMMENT 'التكلفة الإجمالية للبند (الكمية المطلوبة * سعر التكلفة)',
  `sale_price` decimal(13,4) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `qty_received` decimal(13,4) NOT NULL DEFAULT 0.0000,
  `qty_adjusted` decimal(13,4) DEFAULT NULL,
  `adjustment_reason` varchar(255) DEFAULT NULL,
  `adjusted_by` int(11) DEFAULT NULL,
  `adjusted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_purchase_item_to_purchase_invoice` (`purchase_invoice_id`),
  KEY `fk_purchase_item_to_product` (`product_id`),
  KEY `fk_pitem_batch` (`batch_id`)
) ENGINE=InnoDB AUTO_INCREMENT=404 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11) batches (يعتمد على products, purchase_invoices, purchase_invoice_items)
CREATE TABLE `batches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `qty` decimal(13,4) NOT NULL DEFAULT 0.0000,
  `remaining` decimal(13,4) NOT NULL DEFAULT 0.0000,
  `original_qty` decimal(13,4) NOT NULL,
  `unit_cost` decimal(13,4) NOT NULL DEFAULT 0.0000,
  `sale_price` decimal(13,4) DEFAULT NULL,
  `received_at` date DEFAULT NULL,
  `expiry` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `source_invoice_id` int(11) DEFAULT NULL,
  `source_item_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `adjusted_by` int(11) DEFAULT NULL,
  `adjusted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `revert_reason` varchar(255) DEFAULT NULL,
  `cancel_reason` varchar(255) DEFAULT NULL,
  `status` enum('active','consumed','cancelled','reverted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `fk_batches_invoice` (`source_invoice_id`),
  KEY `fk_batches_invoice_item` (`source_item_id`),
  KEY `idx_product_date` (`product_id`,`received_at`,`status`),
  KEY `idx_remaining` (`product_id`,`remaining`)
) ENGINE=InnoDB AUTO_INCREMENT=132 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12) sale_item_allocations (يعتمد على invoice_out_items و batches)
CREATE TABLE `sale_item_allocations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sale_item_id` int(11) NOT NULL,
  `batch_id` bigint(20) unsigned NOT NULL,
  `qty` decimal(13,4) NOT NULL DEFAULT 0.0000,
  `unit_cost` decimal(13,4) NOT NULL DEFAULT 0.0000,
  `line_cost` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sale_item` (`sale_item_id`),
  KEY `idx_batch` (`batch_id`),
  KEY `idx_saleitem_batch` (`sale_item_id`,`batch_id`)
) ENGINE=InnoDB AUTO_INCREMENT=96 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13) expenses (يعتمد على expense_categories و users)
CREATE TABLE `expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `expense_date` date NOT NULL COMMENT 'تاريخ حدوث المصروف',
  `description` varchar(255) NOT NULL COMMENT 'وصف أو بيان المصروف',
  `amount` decimal(10,2) NOT NULL COMMENT 'قيمة المصروف',
  `category_id` int(11) DEFAULT NULL COMMENT 'معرف فئة المصروف (FK to expense_categories.id)',
  `notes` text DEFAULT NULL COMMENT 'ملاحظات إضافية على المصروف (اختياري)',
  `created_by` int(11) DEFAULT NULL COMMENT 'معرف المستخدم الذي سجل المصروف (FK to users.id)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_expense_category` (`category_id`),
  KEY `fk_expense_user_creator` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='سجل المصاريف التشغيلية';

-- 14) الآن: أضف القيود الأجنبية (ALTER TABLE) بعد أن تكون كل الجداول موجودة
-- (بهذه الطريقة نتفادى أخطاء تشكيل القيود عند الإنشاء)

-- customers.created_by -> users.id
ALTER TABLE `customers`
  ADD CONSTRAINT `fk_customer_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- suppliers.created_by -> users.id
ALTER TABLE `suppliers`
  ADD CONSTRAINT `fk_supplier_user_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- invoices_out.customer_id -> customers.id
ALTER TABLE `invoices_out`
  ADD CONSTRAINT `fk_invoice_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`);

-- invoices_out.created_by, updated_by -> users.id
ALTER TABLE `invoices_out`
  ADD CONSTRAINT `fk_invoice_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_invoice_updater` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- invoice_out_items.invoice_out_id -> invoices_out.id
ALTER TABLE `invoice_out_items`
  ADD CONSTRAINT `fk_invoice_item_to_invoice` FOREIGN KEY (`invoice_out_id`) REFERENCES `invoices_out`(`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_invoice_item_to_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`);

-- purchase_invoices.supplier_id, created_by, updated_by
ALTER TABLE `purchase_invoices`
  ADD CONSTRAINT `fk_purchase_invoice_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_purchase_invoice_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_purchase_invoice_updater` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- purchase_invoice_items -> purchase_invoices, products, batch (batch may be null)
ALTER TABLE `purchase_invoice_items`
  ADD CONSTRAINT `fk_purchase_item_to_purchase_invoice` FOREIGN KEY (`purchase_invoice_id`) REFERENCES `purchase_invoices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_purchase_item_to_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pitem_batch` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE SET NULL;

-- batches -> purchase_invoices, purchase_invoice_items, products
ALTER TABLE `batches`
  ADD CONSTRAINT `fk_batches_invoice` FOREIGN KEY (`source_invoice_id`) REFERENCES `purchase_invoices` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_batches_invoice_item` FOREIGN KEY (`source_item_id`) REFERENCES `purchase_invoice_items` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_batches_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

-- sale_item_allocations -> batches, invoice_out_items
ALTER TABLE `sale_item_allocations`
  ADD CONSTRAINT `fk_alloc_batch` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`),
  ADD CONSTRAINT `fk_alloc_sale_item` FOREIGN KEY (`sale_item_id`) REFERENCES `invoice_out_items` (`id`) ON DELETE CASCADE;

-- expenses foreign keys
ALTER TABLE `expenses`
  ADD CONSTRAINT `fk_expense_category` FOREIGN KEY (`category_id`) REFERENCES `expense_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_expense_user_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- Re-enable FK checks
SET FOREIGN_KEY_CHECKS = 1;
