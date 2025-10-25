-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 02, 2025 at 08:12 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";




--
-- Database: `store_v1_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `batches`
--

CREATE TABLE `batches` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` int(11) NOT NULL,
  `qty` decimal(13,4) NOT NULL DEFAULT 0.0000,
  `remaining` decimal(13,4) NOT NULL DEFAULT 0.0000,
  `original_qty` decimal(13,4) NOT NULL DEFAULT 0.0000,
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
  `status` enum('active','consumed','cancelled','reverted') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `batches`
--
INSERT INTO `batches` (`id`, `product_id`, `qty`, `remaining`, `original_qty`, `unit_cost`, `sale_price`, `received_at`, `expiry`, `notes`, `source_invoice_id`, `source_item_id`, `created_by`, `adjusted_by`, `adjusted_at`, `created_at`, `updated_at`, `revert_reason`, `cancel_reason`, `status`) VALUES
(1, 34, '2.0000', '1.2500', '2.0000', '80.0000', '100.0000', '2025-10-01', NULL, NULL, 1, 1, 5, 5, '2025-10-01 16:47:14', '2025-10-01 16:14:31', '2025-10-01 16:47:14', NULL, NULL, 'active'),
(2, 35, '1.0000', '0.8000', '1.0000', '75.0000', '100.0000', '2025-10-01', NULL, NULL, 2, 2, 5, 5, '2025-10-01 16:48:03', '2025-10-01 16:19:24', '2025-10-01 16:48:03', NULL, NULL, 'active');


-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL COMMENT 'اسم العميل',
  `mobile` varchar(11) NOT NULL COMMENT 'رقم الموبايل (11 رقم)',
  `city` varchar(100) NOT NULL COMMENT 'المدينة',
  `address` varchar(255) DEFAULT NULL COMMENT 'العنوان التفصيلي',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL COMMENT 'معرف المستخدم الذي أضاف العميل',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'تاريخ الإضافة'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--
INSERT INTO `customers` (`id`, `name`, `mobile`, `city`, `address`, `notes`, `created_by`, `created_at`) VALUES
(8, 'عميل نقدي', '01096590798', 'Fayoum', 'Fayoum, Egypt', '', 5, '2025-09-01 11:38:46'),
(9, 'اسلام حمدي', '01002821969', 'الفيوم', '', '', 5, '2025-10-01 14:01:17'),
(10, 'بولا البلد', '01005557898', 'الفيوم', '', '', 5, '2025-10-01 14:02:04');

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `expense_date` date NOT NULL COMMENT 'تاريخ حدوث المصروف',
  `description` varchar(255) NOT NULL COMMENT 'وصف أو بيان المصروف',
  `amount` decimal(10,2) NOT NULL COMMENT 'قيمة المصروف',
  `category_id` int(11) DEFAULT NULL COMMENT 'معرف فئة المصروف (FK to expense_categories.id)',
  `notes` text DEFAULT NULL COMMENT 'ملاحظات إضافية على المصروف (اختياري)',
  `created_by` int(11) DEFAULT NULL COMMENT 'معرف المستخدم الذي سجل المصروف (FK to users.id)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='سجل المصاريف التشغيلية';

-- --------------------------------------------------------

--
-- Table structure for table `expense_categories`
--

CREATE TABLE `expense_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL COMMENT 'اسم فئة المصروف (مثال: نقل، كهرباء، إيجار)',
  `description` text DEFAULT NULL COMMENT 'وصف إضافي للفئة (اختياري)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='فئات المصاريف المختلفة';

-- --------------------------------------------------------

--
-- Table structure for table `invoices_out`
--

CREATE TABLE `invoices_out` (
  `id` int(11) NOT NULL COMMENT 'المعرف التلقائي للفاتورة',
  `customer_id` int(11) NOT NULL COMMENT 'معرف العميل المرتبط بالفاتورة',
  `delivered` enum('yes','no','canceled','reverted') NOT NULL DEFAULT 'no' COMMENT 'هل تم التسليم؟ (نعم/لا)',
  `invoice_group` enum('group1','group2','group3','group4','group5','group6','group7','group8','group9','group10','group11') NOT NULL COMMENT 'مجموعة الفاتورة (من 1 إلى 11)',
  `created_by` int(11) DEFAULT NULL COMMENT 'معرف المستخدم الذي أنشأ الفاتورة',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'تاريخ ووقت الإنشاء',
  `updated_by` int(11) DEFAULT NULL COMMENT 'معرف المستخدم الذي آخر من عدل الفاتورة',
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'تاريخ ووقت آخر تعديل',
  `notes` text DEFAULT NULL,
  `cancel_reason` varchar(255) DEFAULT NULL,
  `revert_reason` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول فواتير العملاء الصادرة';

--
-- Dumping data for table `invoices_out`
--

INSERT INTO `invoices_out` (`id`, `customer_id`, `delivered`, `invoice_group`, `created_by`, `created_at`, `updated_by`, `updated_at`, `notes`, `cancel_reason`, `revert_reason`) VALUES
(1, 9, 'no', 'group1', 5, '2025-10-01 14:16:06', 5, '2025-10-01 14:17:32', '', NULL, NULL),
(2, 9, 'no', 'group1', 5, '2025-10-01 14:20:00', NULL, NULL, '', NULL, NULL),
(3, 9, 'no', 'group1', 5, '2025-10-01 14:47:14', NULL, NULL, '', NULL, NULL),
(4, 9, 'no', 'group1', 5, '2025-10-01 14:48:03', NULL, NULL, '', NULL, NULL);


-- --------------------------------------------------------

--
-- Table structure for table `invoice_cancellations`
--

CREATE TABLE `invoice_cancellations` (
  `id` int(11) NOT NULL,
  `invoice_out_id` int(11) NOT NULL,
  `cancelled_by` int(11) NOT NULL,
  `cancelled_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reason` text DEFAULT NULL,
  `total_restored_qty` decimal(12,3) DEFAULT 0.000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoice_cancellations`
--


-- --------------------------------------------------------

--
-- Table structure for table `invoice_cancellation_allocations`
--

CREATE TABLE `invoice_cancellation_allocations` (
  `id` int(11) NOT NULL,
  `cancellation_id` int(11) NOT NULL,
  `sale_item_allocation_id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `qty_restored` decimal(12,3) NOT NULL,
  `unit_cost` decimal(12,4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoice_cancellation_allocations`
--



-- --------------------------------------------------------

--
-- Table structure for table `invoice_out_items`
--

CREATE TABLE `invoice_out_items` (
  `id` int(11) NOT NULL COMMENT 'المعرف التلقائي لبند الفاتورة',
  `invoice_out_id` int(11) NOT NULL COMMENT 'معرف الفاتورة الصادرة (مفتاح أجنبي لجدول invoices_out)',
  `product_id` int(11) NOT NULL COMMENT 'معرف المنتج (مفتاح أجنبي لجدول products)',
  `quantity` decimal(10,2) NOT NULL COMMENT 'الكمية المباعة من المنتج',
  `total_price` decimal(10,2) NOT NULL COMMENT 'السعر الإجمالي للبند (الكمية * سعر الوحدة)',
  `cost_price_per_unit` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'تاريخ إضافة البند',
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'تاريخ آخر تعديل للبند',
  `selling_price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `invoice_out_items`
--
INSERT INTO `invoice_out_items` (`id`, `invoice_out_id`, `product_id`, `quantity`, `total_price`, `cost_price_per_unit`, `created_at`, `updated_at`, `selling_price`) VALUES
(1, 1, 34, '0.50', '60.00', '80.00', '2025-10-01 14:16:06', NULL, '120.00'),
(2, 2, 35, '0.10', '15.00', '75.00', '2025-10-01 14:20:00', NULL, '150.00'),
(3, 3, 34, '0.25', '30.00', '80.00', '2025-10-01 14:47:14', NULL, '120.00'),
(4, 4, 35, '0.10', '15.00', '75.00', '2025-10-01 14:48:03', NULL, '150.00');


-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL COMMENT 'المعرف التلقائي للمنتج',
  `product_code` varchar(50) NOT NULL COMMENT 'كود المنتج الفريد',
  `name` varchar(255) NOT NULL COMMENT 'اسم المنتج',
  `description` text DEFAULT NULL COMMENT 'وصف المنتج (اختياري)',
  `unit_of_measure` varchar(50) NOT NULL COMMENT 'وحدة القياس (مثال: قطعة، كجم، لتر)',
  `current_stock` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'الرصيد الحالي في المخزن',
  `reorder_level` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'حد إعادة الطلب (التنبيه عند وصول الرصيد إليه أو أقل)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'تاريخ الإنشاء',
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'تاريخ آخر تعديل',
  `cost_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `selling_price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول المنتجات المخزنة';

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `product_code`, `name`, `description`, `unit_of_measure`, `current_stock`, `reorder_level`, `created_at`, `updated_at`, `cost_price`, `selling_price`) VALUES
(1, '128مللي', 'مقبض اسود كبير', '', 'قطعة', '0.00', '30.00', '2025-10-01 11:55:01', '2025-10-01 19:32:10', '9.00', '20.00'),
(2, '96مللي', 'مقبض اسود صغير', '', 'قطعة', '0.00', '30.00', '2025-10-01 12:07:31', '2025-10-01 19:32:16', '8.50', '0.00'),
(3, 'm05 فضي', 'مفصلة فراشة فضي pukka', '', 'كارت 2 قطعة', '0.00', '10.00', '2025-10-01 12:09:18', '2025-10-01 19:32:20', '0.00', '0.00'),
(4, 'm05 اسود', 'مفصلة فراشة اسود pukka', '', 'كارت 2 قطعة', '0.00', '10.00', '2025-10-01 12:10:15', '2025-10-01 19:32:25', '0.00', '0.00'),
(6, '20mm ابيض', 'مسمار 2سم ابيض', '', 'علبة', '0.00', '5.00', '2025-10-01 12:16:12', '2025-10-01 19:33:25', '0.00', '0.00'),
(7, '30mm ابيض', 'مسمار 3سم ابيض', '', 'علبة', '0.00', '5.00', '2025-10-01 12:16:51', '2025-10-01 19:33:33', '0.00', '0.00'),
(8, '40mm ابيض', 'مسمار 4سم ابيض', '', 'علبة', '0.00', '5.00', '2025-10-01 12:17:32', '2025-10-01 19:33:39', '0.00', '0.00'),
(9, '50mm ابيض', 'مسمار 5سم ابيض', '', 'علبة', '0.00', '5.00', '2025-10-01 12:17:57', '2025-10-01 19:33:43', '0.00', '0.00'),
(10, 'f15 turbo', 'دبوس 1.5سم مشط turbo', '', 'علبة', '0.00', '5.00', '2025-10-01 12:21:24', '2025-10-01 19:33:49', '0.00', '0.00'),
(11, 'f20 tiger', 'دبوس 2سم مشط tiger', '', 'علبة', '0.00', '5.00', '2025-10-01 12:22:28', '2025-10-01 19:33:53', '0.00', '0.00'),
(12, 'f30 tiger', 'دبوس 3سم مشط tiger', '', 'علبة', '0.00', '5.00', '2025-10-01 12:23:50', '2025-10-01 19:33:57', '0.00', '0.00'),
(13, 'f40 turbo', 'دبوس 4سم مشط turbo', '', 'علبة', '0.00', '5.00', '2025-10-01 12:26:23', '2025-10-01 19:34:01', '0.00', '0.00'),
(14, 'f50 turbo', 'دبوس 5سم مشط turbo', '', 'علبة', '0.00', '5.00', '2025-10-01 12:27:28', '2025-10-01 19:34:07', '0.00', '0.00'),
(15, '1013j tiger', 'دبوس مشبك 1013', '', 'علبة', '0.00', '5.00', '2025-10-01 12:28:59', '2025-10-01 19:34:11', '0.00', '0.00'),
(16, '8010 turbo', 'دبوس مشبك 8010 turbo', '', 'علبة', '0.00', '5.00', '2025-10-01 12:30:50', '2025-10-01 19:34:15', '0.00', '0.00'),
(17, 'x-seal 1991', 'علبة مادة كبيرة (امير) seal', '', 'قطعة', '0.00', '5.00', '2025-10-01 12:34:08', '2025-10-01 19:34:19', '0.00', '0.00'),
(18, '650f  falcon', 'امبوبة سيلكون عضم صغير رمادي', '', 'كارت', '0.00', '0.00', '2025-10-01 12:37:52', '2025-10-01 19:34:23', '0.00', '0.00'),
(19, 'super glue', 'امير الصاروخ صغير', '', 'قطعة', '0.00', '0.00', '2025-10-01 12:41:02', '2025-10-01 19:34:28', '0.00', '0.00'),
(20, 'GP-5000', 'امير الصاروخ لزوجة ثقيلة', '', 'امبوبة', '0.00', '5.00', '2025-10-01 12:42:30', '2025-10-01 19:34:34', '0.00', '0.00'),
(21, 'blade usa', 'مقشطة شريط', '', 'قطعة', '0.00', '5.00', '2025-10-01 12:44:34', '2025-10-01 19:34:40', '0.00', '0.00'),
(22, 'ATQ 80N', 'دراع باكم', '', 'قطعة', '0.00', '5.00', '2025-10-01 12:45:58', '2025-10-01 19:34:43', '0.00', '0.00'),
(23, '10cm-reglStenls', 'رجل ساتانلس', '', 'قطعة', '0.00', '4.00', '2025-10-01 12:47:41', '2025-10-01 19:34:47', '0.00', '0.00'),
(24, 'sopa3-Tatch', 'صباع تاتش', '', 'قطعة', '0.00', '5.00', '2025-10-01 12:48:45', '2025-10-01 19:34:51', '0.00', '0.00'),
(25, 'sadadda01', 'صدادة باب ابيض', '', 'قطعة', '0.00', '5.00', '2025-10-01 12:49:52', '2025-10-01 19:34:54', '0.00', '0.00'),
(27, 'P40 sarokh', 'فرخ صنفرة كليتشه 40', '', 'قطعة', '0.00', '5.00', '2025-10-01 12:51:27', '2025-10-01 19:37:34', '0.00', '0.00'),
(28, 'P120 sarokh', 'فرخ صنفرة كليتشه 120', '', 'قطعة', '0.00', '5.00', '2025-10-01 12:52:09', '2025-10-01 19:37:39', '0.00', '0.00'),
(29, '14000010-ALI', 'طلمبة كمبيوتر ALI', '', 'قطعة', '0.00', '5.00', '2025-10-01 12:54:07', '2025-10-01 19:37:42', '0.00', '0.00'),
(30, 'sadadda02', 'صدادة باب بني', '', 'قطعة', '0.00', '5.00', '2025-10-01 12:56:21', '2025-10-01 19:37:47', '0.00', '0.00'),
(33, '1000تكاية-بني', 'كيس تكاية كبير بني 1000', '', 'كيس', '0.00', '3.00', '2025-10-01 12:58:08', '2025-10-01 19:38:37', '0.00', '0.00'),
(34, '16mm ابيض', 'مسمار 1.5سم ابيض', '', 'علبة', '2.00', '5.00', '2025-10-01 12:15:26', '2025-10-01 14:14:31', '0.00', '0.00'),
(35, '1000تكاية-ابيض', 'كيس تكاية كبير ابيض 1000', '', 'كيس', '1.00', '3.00', '2025-10-01 12:57:16', '2025-10-01 14:19:24', '0.00', '0.00');

-- ---


-- --------------------------------------------------------

--
-- Table structure for table `purchase_invoices`
--

CREATE TABLE `purchase_invoices` (
  `id` int(11) NOT NULL COMMENT 'المعرف التلقائي لفاتورة الشراء',
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
  `revert_reason` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول فواتير المشتريات (الوارد)';

--
-- Dumping data for table `purchase_invoices`
--

INSERT INTO `purchase_invoices` (`id`, `supplier_id`, `supplier_invoice_number`, `purchase_date`, `notes`, `total_amount`, `status`, `created_by`, `created_at`, `updated_by`, `updated_at`, `cancel_reason`, `revert_reason`) VALUES
(1, 1, '', '2025-10-01', '', '160.00', 'fully_received', 5, '2025-10-01 14:14:31', NULL, '2025-10-01 14:14:31', NULL, NULL),
(2, 1, '', '2025-10-01', '', '75.00', 'fully_received', 5, '2025-10-01 14:19:15', 5, '2025-10-01 14:19:24', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `purchase_invoice_items`
--

CREATE TABLE `purchase_invoice_items` (
  `id` int(11) NOT NULL COMMENT 'المعرف التلقائي لبند فاتورة الشراء',
  `batch_id` bigint(20) UNSIGNED DEFAULT NULL,
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
  `adjusted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `purchase_invoice_items`
--

INSERT INTO `purchase_invoice_items` (`id`, `batch_id`, `purchase_invoice_id`, `product_id`, `quantity`, `cost_price_per_unit`, `total_cost`, `sale_price`, `created_at`, `updated_at`, `qty_received`, `qty_adjusted`, `adjustment_reason`, `adjusted_by`, `adjusted_at`) VALUES
(1, 1, 1, 34, '2.0000', '80.00', '160.00', '100.0000', '2025-10-01 14:14:31', '2025-10-01 14:14:31', '2.0000', NULL, NULL, NULL, NULL),
(2, 2, 2, 35, '1.0000', '75.00', '75.00', '100.0000', '2025-10-01 14:19:15', '2025-10-01 14:19:24', '1.0000', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `sale_item_allocations`
--

CREATE TABLE `sale_item_allocations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `sale_item_id` int(11) NOT NULL,
  `batch_id` bigint(20) UNSIGNED NOT NULL,
  `qty` decimal(13,4) NOT NULL DEFAULT 0.0000,
  `unit_cost` decimal(13,4) NOT NULL DEFAULT 0.0000,
  `line_cost` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sale_item_allocations`
--

INSERT INTO `sale_item_allocations` (`id`, `sale_item_id`, `batch_id`, `qty`, `unit_cost`, `line_cost`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 1, '0.5000', '80.0000', '40.0000', 5, '2025-10-01 16:16:06', '2025-10-01 16:16:06'),
(2, 2, 2, '0.1000', '75.0000', '7.5000', 5, '2025-10-01 16:20:00', '2025-10-01 16:20:00'),
(3, 3, 1, '0.2500', '80.0000', '20.0000', 5, '2025-10-01 16:47:14', '2025-10-01 16:47:14'),
(4, 4, 2, '0.1000', '75.0000', '7.5000', 5, '2025-10-01 16:48:03', '2025-10-01 16:48:03');


-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_name` varchar(100) NOT NULL COMMENT 'اسم الإعداد',
  `setting_value` text DEFAULT NULL COMMENT 'قيمة الإعداد',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'آخر تحديث'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول إعدادات النظام العامة';

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL COMMENT 'المعرف التلقائي للمورد',
  `name` varchar(200) NOT NULL COMMENT 'اسم المورد',
  `mobile` varchar(11) NOT NULL COMMENT 'رقم موبايل المورد (11 رقم)',
  `city` varchar(100) NOT NULL COMMENT 'مدينة المورد',
  `address` text DEFAULT NULL COMMENT 'عنوان المورد التفصيلي (اختياري)',
  `commercial_register` varchar(100) DEFAULT NULL COMMENT 'رقم السجل التجاري (اختياري ولكنه فريد إذا أدخل)',
  `created_by` int(11) DEFAULT NULL COMMENT 'معرف المستخدم الذي أضاف المورد (FK to users.id)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'تاريخ إضافة المورد',
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'تاريخ آخر تعديل لبيانات المورد'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول بيانات الموردين';

--
-- Dumping data for table `suppliers`

-- --------------------------------------------------------
INSERT INTO `suppliers` (`id`, `name`, `mobile`, `city`, `address`, `commercial_register`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'البضاعة الموجده حاليا', '01099337896', 'الفيوم', '', NULL, 5, '2025-10-01 11:50:40', NULL);

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `created_at`) VALUES
(5, 'admin', 'admin@gmail.com', '$2y$10$9DkMvN8bVe3xV3Mf5qd91O/0YyliyVLUVWVcy8NQkjmNo4.hDuvIq', 'admin', '2025-06-04 10:52:41');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `batches`
--
ALTER TABLE `batches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_batches_invoice` (`source_invoice_id`),
  ADD KEY `fk_batches_invoice_item` (`source_item_id`),
  ADD KEY `idx_product_date` (`product_id`,`received_at`,`status`),
  ADD KEY `idx_remaining` (`product_id`,`remaining`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `mobile` (`mobile`),
  ADD KEY `fk_customer_user` (`created_by`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_expense_category` (`category_id`),
  ADD KEY `fk_expense_user_creator` (`created_by`);

--
-- Indexes for table `expense_categories`
--
ALTER TABLE `expense_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `invoices_out`
--
ALTER TABLE `invoices_out`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_invoice_customer` (`customer_id`),
  ADD KEY `fk_invoice_creator` (`created_by`),
  ADD KEY `fk_invoice_updater` (`updated_by`);

--
-- Indexes for table `invoice_cancellations`
--
ALTER TABLE `invoice_cancellations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_out_id` (`invoice_out_id`);

--
-- Indexes for table `invoice_cancellation_allocations`
--
ALTER TABLE `invoice_cancellation_allocations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cancellation_id` (`cancellation_id`);

--
-- Indexes for table `invoice_out_items`
--
ALTER TABLE `invoice_out_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_invoice_item_to_invoice` (`invoice_out_id`),
  ADD KEY `fk_invoice_item_to_product` (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_code` (`product_code`);

--
-- Indexes for table `purchase_invoices`
--
ALTER TABLE `purchase_invoices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_purchase_invoice_supplier` (`supplier_id`),
  ADD KEY `fk_purchase_invoice_creator` (`created_by`),
  ADD KEY `fk_purchase_invoice_updater` (`updated_by`);

--
-- Indexes for table `purchase_invoice_items`
--
ALTER TABLE `purchase_invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_purchase_item_to_purchase_invoice` (`purchase_invoice_id`),
  ADD KEY `fk_purchase_item_to_product` (`product_id`),
  ADD KEY `fk_pitem_batch` (`batch_id`);

--
-- Indexes for table `sale_item_allocations`
--
ALTER TABLE `sale_item_allocations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sale_item` (`sale_item_id`),
  ADD KEY `idx_batch` (`batch_id`),
  ADD KEY `idx_saleitem_batch` (`sale_item_id`,`batch_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_name`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `mobile` (`mobile`),
  ADD UNIQUE KEY `commercial_register` (`commercial_register`),
  ADD KEY `fk_supplier_user_creator` (`created_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `batches`
--
ALTER TABLE `batches`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expense_categories`
--
ALTER TABLE `expense_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoices_out`
--
ALTER TABLE `invoices_out`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'المعرف التلقائي للفاتورة', AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `invoice_cancellations`
--
ALTER TABLE `invoice_cancellations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `invoice_cancellation_allocations`
--
ALTER TABLE `invoice_cancellation_allocations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `invoice_out_items`
--
ALTER TABLE `invoice_out_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'المعرف التلقائي لبند الفاتورة', AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'المعرف التلقائي للمنتج', AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `purchase_invoices`
--
ALTER TABLE `purchase_invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'المعرف التلقائي لفاتورة الشراء', AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `purchase_invoice_items`
--
ALTER TABLE `purchase_invoice_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'المعرف التلقائي لبند فاتورة الشراء', AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `sale_item_allocations`
--
ALTER TABLE `sale_item_allocations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'المعرف التلقائي للمورد', AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `batches`
--
ALTER TABLE `batches`
  ADD CONSTRAINT `fk_batches_invoice` FOREIGN KEY (`source_invoice_id`) REFERENCES `purchase_invoices` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_batches_invoice_item` FOREIGN KEY (`source_item_id`) REFERENCES `purchase_invoice_items` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_batches_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `fk_customer_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `fk_expense_category` FOREIGN KEY (`category_id`) REFERENCES `expense_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_expense_user_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `invoices_out`
--
ALTER TABLE `invoices_out`
  ADD CONSTRAINT `fk_invoice_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_invoice_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `fk_invoice_updater` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `invoice_cancellations`
--
ALTER TABLE `invoice_cancellations`
  ADD CONSTRAINT `fk_ic_invoice` FOREIGN KEY (`invoice_out_id`) REFERENCES `invoices_out` (`id`);

--
-- Constraints for table `invoice_cancellation_allocations`
--
ALTER TABLE `invoice_cancellation_allocations`
  ADD CONSTRAINT `fk_ica_cancel` FOREIGN KEY (`cancellation_id`) REFERENCES `invoice_cancellations` (`id`);

--
-- Constraints for table `invoice_out_items`
--
ALTER TABLE `invoice_out_items`
  ADD CONSTRAINT `fk_invoice_item_to_invoice` FOREIGN KEY (`invoice_out_id`) REFERENCES `invoices_out` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_invoice_item_to_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `purchase_invoices`
--
ALTER TABLE `purchase_invoices`
  ADD CONSTRAINT `fk_purchase_invoice_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_purchase_invoice_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_purchase_invoice_updater` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `purchase_invoice_items`
--
ALTER TABLE `purchase_invoice_items`
  ADD CONSTRAINT `fk_pitem_batch` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_purchase_item_to_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_purchase_item_to_purchase_invoice` FOREIGN KEY (`purchase_invoice_id`) REFERENCES `purchase_invoices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `sale_item_allocations`
--
ALTER TABLE `sale_item_allocations`
  ADD CONSTRAINT `fk_alloc_batch` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`),
  ADD CONSTRAINT `fk_alloc_sale_item` FOREIGN KEY (`sale_item_id`) REFERENCES `invoice_out_items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD CONSTRAINT `fk_supplier_user_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
