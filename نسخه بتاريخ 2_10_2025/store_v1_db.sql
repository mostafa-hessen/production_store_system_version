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


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

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
(1, 1, 5.0000, 4.0000, 5.0000, 150.0000, 200.0000, '2025-10-01', NULL, NULL, 2, 2, 5, 5, '2025-10-02 08:33:33', '2025-10-01 23:22:15', '2025-10-02 08:46:22', NULL, NULL, 'active'),
(2, 1, 5.0000, 5.0000, 5.0000, 250.0000, 350.0000, '2025-10-01', NULL, NULL, 3, 3, 5, 5, '2025-10-02 01:04:59', '2025-10-01 23:22:20', '2025-10-02 01:05:15', NULL, NULL, 'active'),
(3, 2, 5.0000, 5.0000, 5.0000, 70.0000, 120.0000, '2025-10-02', NULL, NULL, 5, 6, 5, 5, '2025-10-02 08:33:33', '2025-10-02 08:31:23', '2025-10-02 08:46:22', NULL, NULL, 'active'),
(4, 3, 1.0000, 1.0000, 1.0000, 120.0000, 130.0000, '2025-10-02', NULL, NULL, 5, 7, 5, 5, '2025-10-02 08:33:33', '2025-10-02 08:31:23', '2025-10-02 08:46:22', NULL, NULL, 'active'),
(5, 2, 5.0000, 5.0000, 5.0000, 100.0000, 150.0000, '2025-10-02', NULL, NULL, 4, 4, 5, 5, '2025-10-02 08:33:33', '2025-10-02 08:31:29', '2025-10-02 08:46:22', NULL, NULL, 'active'),
(6, 3, 5.0000, 5.0000, 5.0000, 50.0000, 100.0000, '2025-10-02', NULL, NULL, 4, 5, 5, 5, '2025-10-02 08:33:33', '2025-10-02 08:31:29', '2025-10-02 08:46:22', NULL, NULL, 'active');

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
(8, 'عميل نقدي', '12345678901', 'Fayoum', 'Fayoum, Egypt', '', 5, '2025-09-01 10:38:46'),
(9, 'said hamdy abdelrazek', '01099337896', 'Faiyum', 'abo shnak', '', 5, '2025-10-01 18:19:17');

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
(1, 8, 'canceled', 'group1', 5, '2025-10-01 20:25:21', 5, '2025-10-01 21:05:58', '', NULL, NULL),
(2, 8, 'canceled', 'group1', 5, '2025-10-01 21:14:06', 5, '2025-10-01 21:14:45', '', NULL, NULL),
(3, 8, 'canceled', 'group1', 5, '2025-10-01 21:18:20', 5, '2025-10-01 21:30:52', '', NULL, NULL),
(4, 8, 'canceled', 'group1', 5, '2025-10-01 21:45:09', 5, '2025-10-01 21:58:14', '', NULL, NULL),
(5, 8, 'canceled', 'group1', 5, '2025-10-01 22:02:49', 5, '2025-10-01 22:03:10', '', NULL, NULL),
(6, 8, 'canceled', 'group1', 5, '2025-10-01 22:04:59', 5, '2025-10-01 22:05:15', '', NULL, NULL),
(7, 8, 'no', 'group1', 5, '2025-10-02 03:44:29', NULL, NULL, '', NULL, NULL),
(8, 8, 'canceled', 'group1', 5, '2025-10-02 05:33:33', 5, '2025-10-02 05:46:22', '', NULL, NULL);

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

INSERT INTO `invoice_cancellations` (`id`, `invoice_out_id`, `cancelled_by`, `cancelled_at`, `reason`, `total_restored_qty`) VALUES
(1, 1, 5, '2025-10-02 00:05:58', 'ksd', 6.000),
(2, 2, 5, '2025-10-02 00:14:45', '', 6.000),
(3, 3, 5, '2025-10-02 00:30:52', 'كلاكيت اخر مره', 6.000),
(4, 4, 5, '2025-10-02 00:58:14', ';lkmn', 6.000),
(5, 5, 5, '2025-10-02 01:03:10', 'اخر مره الغي يا حبيبي \r\nبسم الله', 10.000),
(6, 6, 5, '2025-10-02 01:05:15', 'الحمدلله', 7.000),
(7, 8, 5, '2025-10-02 08:46:22', 'الحمدلله الغاء بعد عناء', 13.000);

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

INSERT INTO `invoice_cancellation_allocations` (`id`, `cancellation_id`, `sale_item_allocation_id`, `batch_id`, `qty_restored`, `unit_cost`) VALUES
(1, 1, 1, 1, 0.000, 150.0000),
(2, 1, 2, 2, 0.000, 250.0000),
(3, 2, 3, 1, 0.000, 150.0000),
(4, 2, 4, 2, 0.000, 250.0000),
(5, 3, 5, 1, 5.000, 150.0000),
(6, 3, 6, 2, 1.000, 250.0000),
(7, 4, 7, 1, 5.000, 150.0000),
(8, 4, 8, 2, 1.000, 250.0000),
(9, 5, 9, 1, 5.000, 150.0000),
(10, 5, 10, 2, 5.000, 250.0000),
(11, 6, 11, 1, 5.000, 150.0000),
(12, 6, 12, 2, 2.000, 250.0000),
(13, 7, 14, 4, 1.000, 120.0000),
(14, 7, 15, 6, 5.000, 50.0000),
(15, 7, 16, 1, 1.000, 150.0000),
(16, 7, 17, 3, 5.000, 70.0000),
(17, 7, 18, 5, 1.000, 100.0000);

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
(1, 1, 1, 6.00, 2100.00, 166.67, '2025-10-01 20:25:21', NULL, 350.00),
(2, 2, 1, 6.00, 2100.00, 166.67, '2025-10-01 21:14:06', NULL, 350.00),
(3, 3, 1, 6.00, 2100.00, 166.67, '2025-10-01 21:18:20', NULL, 350.00),
(4, 4, 1, 6.00, 2100.00, 166.67, '2025-10-01 21:45:09', NULL, 350.00),
(5, 5, 1, 10.00, 3500.00, 200.00, '2025-10-01 22:02:49', NULL, 350.00),
(6, 6, 1, 7.00, 2450.00, 178.57, '2025-10-01 22:04:59', NULL, 350.00),
(7, 7, 1, 1.00, 350.00, 150.00, '2025-10-02 03:44:29', NULL, 350.00),
(8, 8, 3, 6.00, 600.00, 61.67, '2025-10-02 05:33:33', NULL, 100.00),
(9, 8, 1, 1.00, 350.00, 150.00, '2025-10-02 05:33:33', NULL, 350.00),
(10, 8, 2, 6.00, 900.00, 75.00, '2025-10-02 05:33:33', NULL, 150.00);

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
(1, 'l5w1', 'مفصله', '', 'ب', 10.00, 0.00, '2025-10-01 18:22:32', '2025-10-01 20:22:20', 0.00, 0.00),
(2, '2315', 'مفصله فراشه', '', 'قطعه', 10.00, 0.00, '2025-10-01 19:22:49', '2025-10-02 05:31:29', 0.00, 0.00),
(3, '2105620', 'نتا', '', 'قطعه', 6.00, 0.00, '2025-10-01 19:24:17', '2025-10-02 05:31:29', 0.00, 0.00);

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
(1, 5, '', '2025-10-01', '', 0.00, 'pending', 5, '2025-10-01 18:29:47', NULL, NULL, NULL, NULL),
(2, 5, '', '2025-10-01', '', 750.00, 'fully_received', 5, '2025-10-01 20:21:32', 5, '2025-10-01 20:22:15', NULL, NULL),
(3, 5, '', '2025-10-01', '', 1250.00, 'fully_received', 5, '2025-10-01 20:22:20', NULL, '2025-10-01 20:22:20', NULL, NULL),
(4, 5, '', '2025-10-02', '', 750.00, 'fully_received', 5, '2025-10-02 05:30:34', 5, '2025-10-02 05:31:29', NULL, NULL),
(5, 5, '', '2025-10-02', '', 470.00, 'fully_received', 5, '2025-10-02 05:31:23', NULL, '2025-10-02 05:31:23', NULL, NULL);

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
(1, NULL, 1, 1, 1.0000, 0.00, 0.00, 0.0000, '2025-10-01 18:29:47', NULL, 0.0000, NULL, NULL, NULL, NULL),
(2, 1, 2, 1, 5.0000, 150.00, 750.00, 200.0000, '2025-10-01 20:21:32', '2025-10-01 20:22:15', 5.0000, NULL, NULL, NULL, NULL),
(3, 2, 3, 1, 5.0000, 250.00, 1250.00, 350.0000, '2025-10-01 20:22:20', '2025-10-01 20:22:20', 5.0000, NULL, NULL, NULL, NULL),
(4, 5, 4, 2, 5.0000, 100.00, 500.00, 150.0000, '2025-10-02 05:30:34', '2025-10-02 05:31:29', 5.0000, NULL, NULL, NULL, NULL),
(5, 6, 4, 3, 5.0000, 50.00, 250.00, 100.0000, '2025-10-02 05:30:34', '2025-10-02 05:31:29', 5.0000, NULL, NULL, NULL, NULL),
(6, 3, 5, 2, 5.0000, 70.00, 350.00, 120.0000, '2025-10-02 05:31:23', '2025-10-02 05:31:23', 5.0000, NULL, NULL, NULL, NULL),
(7, 4, 5, 3, 1.0000, 120.00, 120.00, 130.0000, '2025-10-02 05:31:23', '2025-10-02 05:31:23', 1.0000, NULL, NULL, NULL, NULL);

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
(1, 1, 1, 5.0000, 150.0000, 750.0000, 5, '2025-10-01 23:25:21', '2025-10-01 23:25:21'),
(2, 1, 2, 1.0000, 250.0000, 250.0000, 5, '2025-10-01 23:25:21', '2025-10-01 23:25:21'),
(3, 2, 1, 5.0000, 150.0000, 750.0000, 5, '2025-10-02 00:14:06', '2025-10-02 00:14:06'),
(4, 2, 2, 1.0000, 250.0000, 250.0000, 5, '2025-10-02 00:14:06', '2025-10-02 00:14:06'),
(5, 3, 1, 5.0000, 150.0000, 750.0000, 5, '2025-10-02 00:18:20', '2025-10-02 00:18:20'),
(6, 3, 2, 1.0000, 250.0000, 250.0000, 5, '2025-10-02 00:18:20', '2025-10-02 00:18:20'),
(7, 4, 1, 5.0000, 150.0000, 750.0000, 5, '2025-10-02 00:45:09', '2025-10-02 00:45:09'),
(8, 4, 2, 1.0000, 250.0000, 250.0000, 5, '2025-10-02 00:45:09', '2025-10-02 00:45:09'),
(9, 5, 1, 5.0000, 150.0000, 750.0000, 5, '2025-10-02 01:02:49', '2025-10-02 01:02:49'),
(10, 5, 2, 5.0000, 250.0000, 1250.0000, 5, '2025-10-02 01:02:49', '2025-10-02 01:02:49'),
(11, 6, 1, 5.0000, 150.0000, 750.0000, 5, '2025-10-02 01:04:59', '2025-10-02 01:04:59'),
(12, 6, 2, 2.0000, 250.0000, 500.0000, 5, '2025-10-02 01:04:59', '2025-10-02 01:04:59'),
(13, 7, 1, 1.0000, 150.0000, 150.0000, 5, '2025-10-02 06:44:29', '2025-10-02 06:44:29'),
(14, 8, 4, 1.0000, 120.0000, 120.0000, 5, '2025-10-02 08:33:33', '2025-10-02 08:33:33'),
(15, 8, 6, 5.0000, 50.0000, 250.0000, 5, '2025-10-02 08:33:33', '2025-10-02 08:33:33'),
(16, 9, 1, 1.0000, 150.0000, 150.0000, 5, '2025-10-02 08:33:33', '2025-10-02 08:33:33'),
(17, 10, 3, 5.0000, 70.0000, 350.0000, 5, '2025-10-02 08:33:33', '2025-10-02 08:33:33'),
(18, 10, 5, 1.0000, 100.0000, 100.0000, 5, '2025-10-02 08:33:33', '2025-10-02 08:33:33');

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
--

INSERT INTO `suppliers` (`id`, `name`, `mobile`, `city`, `address`, `commercial_register`, `created_by`, `created_at`, `updated_at`) VALUES
(5, 'said hamdy abdelrazek', '01099337896', 'Faiyum', 'abo shnak', NULL, 5, '2025-10-01 18:29:37', NULL);

-- --------------------------------------------------------

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
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'المعرف التلقائي للفاتورة', AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `invoice_cancellations`
--
ALTER TABLE `invoice_cancellations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `invoice_cancellation_allocations`
--
ALTER TABLE `invoice_cancellation_allocations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `invoice_out_items`
--
ALTER TABLE `invoice_out_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'المعرف التلقائي لبند الفاتورة', AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'المعرف التلقائي للمنتج', AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `purchase_invoices`
--
ALTER TABLE `purchase_invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'المعرف التلقائي لفاتورة الشراء', AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `purchase_invoice_items`
--
ALTER TABLE `purchase_invoice_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'المعرف التلقائي لبند فاتورة الشراء', AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `sale_item_allocations`
--
ALTER TABLE `sale_item_allocations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'المعرف التلقائي للمورد', AUTO_INCREMENT=8;

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
