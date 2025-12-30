<?php
// customer_details.php
$page_title = "تفاصيل العميل";
$class_customers = "active";
require_once dirname(__DIR__) . '/config.php';
require_once BASE_DIR . 'partials/session_admin.php';
require_once BASE_DIR . 'partials/header.php';



$customer_id = intval($_GET['customer_id']);
$message = "";

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// استعلام بيانات العميل
$customer = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            id, name, mobile, city, address, notes, 
            balance, wallet, 
            DATE(created_at) as join_date,
            DATE(join_date) as customer_join_date
        FROM customers 
        WHERE id = ?
    ");
    
    // ربط المعاملات - مهم جداً للـ mysqli
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $customer = $result->fetch_assoc();
    
    if (!$customer) {
        $_SESSION['error'] = "العميل غير موجود";
        header("Location: ../admin/manage_customer.php");
        exit();
    }
    
    $stmt->close();
} catch (Exception $e) {
    $error = "خطأ في استرجاع بيانات العميل: " . $e->getMessage();
}

// استعلام إحصائيات الفواتير
$invoice_stats = [
    'all' => ['count' => 0, 'total' => 0],
    'pending' => ['count' => 0, 'total' => 0],
    'partial' => ['count' => 0, 'total' => 0],
    'paid' => ['count' => 0, 'total' => 0],
    'returned' => ['count' => 0, 'total' => 0]
];

try {
    // إحصائيات الفواتير بناءً على حالة التسليم
    $stmt = $conn->prepare("
        SELECT 
            delivered as status,
            COUNT(*) as invoice_count,
            COALESCE(SUM(remaining_amount), 0) as total_amount
        FROM invoices_out 
        WHERE customer_id = ?
        GROUP BY delivered
    ");
    
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $invoice_stats_raw = $result->fetch_all(MYSQLI_ASSOC);
    
    foreach ($invoice_stats_raw as $stat) {
        switch ($stat['status']) {
            case 'no':
                $invoice_stats['pending']['count'] = $stat['invoice_count'];
                $invoice_stats['pending']['total'] = floatval($stat['total_amount']);
                break;
            case 'partial':
                $invoice_stats['partial']['count'] = $stat['invoice_count'];
                $invoice_stats['partial']['total'] = floatval($stat['total_amount']);
                break;
            case 'yes':
                $invoice_stats['paid']['count'] = $stat['invoice_count'];
                $invoice_stats['paid']['total'] = floatval($stat['total_amount']);
                break;
            case 'reverted':
                $invoice_stats['returned']['count'] = $stat['invoice_count'];
                $invoice_stats['returned']['total'] = floatval($stat['total_amount']);
                break;
        }
        
        // إجمالي الكل
        $invoice_stats['all']['count'] += $stat['invoice_count'];
        $invoice_stats['all']['total'] += floatval($stat['total_amount']);
    }
    
    $stmt->close();
} catch (Exception $e) {
    // يمكن تسجيل الخطأ بدون إظهاره للمستخدم
    error_log("Error fetching invoice stats: " . $e->getMessage());
}

// استعلام الفواتير الأخيرة
$recent_invoices = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            id,
            DATE(created_at) as invoice_date,
            TIME(created_at) as invoice_time,
            total_before_discount as total,
            paid_amount as paid,
            remaining_amount as remaining,
            delivered as status,
            notes as description,
            invoice_group
        FROM invoices_out 
        WHERE customer_id = ?
        ORDER BY created_at DESC
        LIMIT 50
    ");
    
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $recent_invoices = $result->fetch_all(MYSQLI_ASSOC);
    
    $stmt->close();
} catch (Exception $e) {
    $error = "خطأ في استرجاع الفواتير: " . $e->getMessage();
}

// استعلام حركات المحفظة
$wallet_transactions = [];
try {
    // تحقق أولاً إذا كان الجدول موجود
    $table_check = $conn->query("SHOW TABLES LIKE 'wallet_transactions'");
    if ($table_check->num_rows > 0) {
        $stmt = $conn->prepare("
            SELECT 
                id,
                DATE(created_at) as trans_date,
                type,
                amount,
                description,
                balance_before,
                balance_after,
                created_by
            FROM wallet_transactions 
            WHERE customer_id = ?
            ORDER BY created_at DESC
            LIMIT 50
        ");
        
        if ($stmt) {
            $stmt->bind_param("i", $customer_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $wallet_transactions = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    }
    
} catch (Exception $e) {
    // إذا كان الجدول غير موجود، نمرر بدون أخطاء
    error_log("Wallet transactions table might not exist: " . $e->getMessage());
}

// استعلام المرتجعات
$returns = [];
try {
    // تحقق إذا كان جدول المرتجعات موجود
    $table_check = $conn->query("SHOW TABLES LIKE 'returns'");
    if ($table_check->num_rows > 0) {
        $stmt = $conn->prepare("
            SELECT 
                r.id,
                r.return_number,
                r.invoice_id,
                i.invoice_group as invoice_number,
                r.total_amount,
                r.return_type,
                r.status,
                DATE(r.created_at) as return_date,
                r.notes as reason,
                r.created_by
            FROM returns r
            JOIN invoices_out i ON r.invoice_id = i.id
            WHERE i.customer_id = ?
            ORDER BY r.created_at DESC
            LIMIT 50
        ");
        
        if ($stmt) {
            $stmt->bind_param("i", $customer_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $returns = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    }
    
} catch (Exception $e) {
    error_log("Returns table might not exist: " . $e->getMessage());
}

// استعلام الشغلانات (work orders)
$work_orders = [];
try {
    $table_check = $conn->query("SHOW TABLES LIKE 'work_orders'");
    if ($table_check->num_rows > 0) {
        $stmt = $conn->prepare("
            SELECT 
                wo.id,
                wo.name,
                wo.description,
                wo.status,
                DATE(wo.start_date) as start_date,
                wo.notes,
                wo.created_by,
                GROUP_CONCAT(wi.invoice_id) as invoice_ids
            FROM work_orders wo
            LEFT JOIN work_order_invoices wi ON wo.id = wi.work_order_id
            WHERE wo.customer_id = ?
            GROUP BY wo.id
            ORDER BY wo.start_date DESC
        ");
        
        if ($stmt) {
            $stmt->bind_param("i", $customer_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $work_orders = $result->fetch_all(MYSQLI_ASSOC);
            
            // تحويل invoice_ids إلى مصفوفة
            foreach ($work_orders as &$order) {
                $order['invoices'] = $order['invoice_ids'] ? explode(',', $order['invoice_ids']) : [];
                unset($order['invoice_ids']);
            }
            
            $stmt->close();
        }
    }
    
} catch (Exception $e) {
    error_log("Work orders table might not exist: " . $e->getMessage());
}

// تحويل البيانات لاستخدامها في JavaScript
$js_customer_data = json_encode([
    'id' => $customer['id'],
    'name' => htmlspecialchars($customer['name'], ENT_QUOTES, 'UTF-8'),
    'phone' => htmlspecialchars($customer['mobile'], ENT_QUOTES, 'UTF-8'),
    'address' => htmlspecialchars(($customer['city'] . ' - ' . $customer['address']), ENT_QUOTES, 'UTF-8'),
    'join_date' => $customer['customer_join_date'] ?? $customer['join_date'],
    'balance' => floatval($customer['balance']),
    'wallet' => floatval($customer['wallet'])
], JSON_UNESCAPED_UNICODE);

$js_invoice_stats = json_encode($invoice_stats, JSON_UNESCAPED_UNICODE);
$js_invoices = json_encode($recent_invoices, JSON_UNESCAPED_UNICODE);
$js_wallet_transactions = json_encode($wallet_transactions, JSON_UNESCAPED_UNICODE);
$js_returns = json_encode($returns, JSON_UNESCAPED_UNICODE);
$js_work_orders = json_encode($work_orders, JSON_UNESCAPED_UNICODE);

// إعداد الإحصائيات للعرض
$current_balance = floatval($customer['balance']);
$wallet_balance = floatval($customer['wallet']);

// تحديد إذا كان رصيد مدين أو دائن
$balance_class = ($current_balance > 0) ? 'negative' : 'positive';
$balance_label = ($current_balance > 0) ? 'مدين' : 'دائن';

require_once BASE_DIR . 'partials/sidebar.php';
?>
<!-- HTML Structure - سنستخدم نفس هيكل HTML الذي أرسلته -->


<head>
 
    <title>تفاصيل العميل - <?php echo htmlspecialchars($customer['name']); ?></title>
    
    <!-- Bootstrap CSS -->
   
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/index.css" />
    
    <style>
        /* إضافة بعض الأنماط الإضافية */
        .customer-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: bold;
        }
        
        .stat-card {
            padding: 1rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-card.negative {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            border: 1px solid #f5c6cb;
        }
        
        .stat-card.positive {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border: 1px solid #c3e6cb;
        }
        
        .stat-value {
            font-size: 1.8rem;
            font-weight: bold;
        }
        
        .invoice-stat-card {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .invoice-stat-card:hover {
            transform: translateY(-5px);
        }
        
        .invoice-stat-card.active {
            border: 2px solid #007bff;
            box-shadow: 0 5px 15px rgba(0,123,255,0.3);
        }
    </style>
</head>
<body>
<div class="container-fluid py-4">
    <!-- رأس العميل -->
    <div class="customer-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <div class="d-flex align-items-center">
                    <div class="customer-avatar me-4" id="customerAvatar">
                        <?php echo mb_substr($customer['name'], 0, 1, 'UTF-8'); ?>
                    </div>
                    <div>
                        <h1 class="h2 mb-2" id="customerName">
                            <?php echo htmlspecialchars($customer['name']); ?>
                        </h1>
                        <div class="d-flex flex-wrap gap-4 fs-5">
                            <span>
                                <i class="fas fa-phone me-2"></i>
                                <span id="customerPhone"><?php echo htmlspecialchars($customer['mobile']); ?></span>
                            </span>
                            <span>
                                <i class="fas fa-city me-2"></i>
                                <span id="customerAddress">
                                    <?php 
                                    $address = $customer['city'];
                                    if (!empty($customer['address'])) {
                                        $address .= ' - ' . $customer['address'];
                                    }
                                    echo htmlspecialchars($address);
                                    ?>
                                </span>
                            </span>
                            <span>
                                <i class="fas fa-calendar me-2"></i> عضو منذ
                                <span id="customerJoinDate"><?php echo $customer['customer_join_date'] ?? $customer['join_date']; ?></span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="stat-card <?php echo $balance_class; ?>">
                            <div class="stat-value" id="currentBalance">
                                <?php echo number_format(abs($current_balance), 2); ?>
                            </div>
                            <div class="stat-label">الرصيد الحالي</div>
                            <small class="<?php echo ($current_balance > 0) ? 'text-danger' : 'text-success'; ?>">
                                <?php echo $balance_label; ?>
                            </small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-card positive">
                            <div class="stat-value" id="walletBalance">
                                <?php echo number_format($wallet_balance, 2); ?>
                            </div>
                            <div class="stat-label">رصيد المحفظة</div>
                            <small class="text-success">دائن</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- كروت إحصائيات الفواتير -->
    <div class="invoice-stats-grid">
        <div class="invoice-stat-card active" data-filter="all">
            <div class="stat-value" id="totalInvoicesCount">
                <?php echo $invoice_stats['all']['count']; ?>
            </div>
            <div class="stat-label">جميع الفواتير</div>
            <div class="stat-amount text-primary">
                <?php echo number_format($invoice_stats['all']['total'], 2); ?> ج.م
            </div>
        </div>
        <div class="invoice-stat-card pending" data-filter="pending">
            <div class="stat-value" id="pendingInvoicesCount">
                <?php echo $invoice_stats['pending']['count']; ?>
            </div>
            <div class="stat-label">مؤجل</div>
            <div class="stat-amount text-warning">
                <?php echo number_format($invoice_stats['pending']['total'], 2); ?> ج.م
            </div>
        </div>
        <div class="invoice-stat-card partial" data-filter="partial">
            <div class="stat-value" id="partialInvoicesCount">
                <?php echo $invoice_stats['partial']['count']; ?>
            </div>
            <div class="stat-label">جزئي</div>
            <div class="stat-amount text-info">
                <?php echo number_format($invoice_stats['partial']['total'], 2); ?> ج.م
            </div>
        </div>
        <div class="invoice-stat-card paid" data-filter="paid">
            <div class="stat-value" id="paidInvoicesCount">
                <?php echo $invoice_stats['paid']['count']; ?>
            </div>
            <div class="stat-label">مسلم</div>
            <div class="stat-amount text-success">
                <?php echo number_format($invoice_stats['paid']['total'], 2); ?> ج.م
            </div>
        </div>
        <div class="invoice-stat-card returned" data-filter="returned">
            <div class="stat-value" id="returnedInvoicesCount">
                <?php echo $invoice_stats['returned']['count']; ?>
            </div>
            <div class="stat-label">مرتجع</div>
            <div class="stat-amount text-danger">
                <?php echo number_format($invoice_stats['returned']['total'], 2); ?> ج.م
            </div>
        </div>
    </div>

    <!-- رسائل النجاح أو الخطأ -->
    <?php if (!empty($message)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <!-- أزرار الإجراءات السريعة -->
    <div class="quick-actions mb-4">
        <div class="d-flex gap-3 flex-wrap">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newWorkOrderModal">
                <i class="fas fa-tools me-2"></i> شغلانة جديدة
            </button>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#paymentModal">
                <i class="fas fa-money-bill-wave me-2"></i> سداد
            </button>
            <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#walletDepositModal">
                <i class="fas fa-wallet me-2"></i> إيداع محفظة
            </button>
            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#statementReportModal">
                <i class="fas fa-file-invoice me-2"></i> كشف حساب
            </button>
            <button class="btn btn-outline-secondary" id="printMultipleBtn">
                <i class="fas fa-print me-2"></i> طباعة متعددة
            </button>
        </div>
    </div>

    <!-- باقي الهيكل HTML كما أرسلته -->
    <!-- ... باقي الكود الذي أرسلته يبقى كما هو ... -->

    <!-- نقلنا باقي الهيكل HTML كما هو -->
    <div class="row">
        <!-- قسم الفلاتر -->
        <div class="col-12 col-md-4 mb-4 mb-md-0">
            <div class="filters-section">
                <h5 class="mb-3">فلاتر البحث</h5>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="dateFrom" class="form-label">من تاريخ</label>
                        <input type="date" class="form-control" id="dateFrom" />
                    </div>
                    <div class="col-md-3">
                        <label for="dateTo" class="form-label">إلى تاريخ</label>
                        <input type="date" class="form-control" id="dateTo" />
                    </div>
                    <div class="col-md-3">
                        <label for="productSearch" class="form-label">بحث بالصنف</label>
                        <input type="text" class="form-control" id="productSearch" placeholder="اكتب اسم الصنف..." />
                    </div>
                    
                    <div class="col-12 mb-3">
                        <label for="advancedProductSearch" class="form-label">بحث متقدم عن صنف</label>
                        <input type="text" class="form-control" id="advancedProductSearch" placeholder="اكتب اسم الصنف للبحث في جميع الفواتير..." />
                        <small class="text-muted">سيتم تمييز النص المطابق باللون الأصفر وعرض الفاتورة في الجانب الأيمن</small>
                    </div>
                    
                    <div id="advancedSearchResults" class="product-search-results" style="display: none"></div>
                    
                    <div class="col-md-3">
                        <label for="invoiceTypeFilter" class="form-label">نوع الفاتورة</label>
                        <select class="form-select" id="invoiceTypeFilter">
                            <option value="">جميع الأنواع</option>
                            <option value="pending">مؤجل</option>
                            <option value="partial">جزئي</option>
                            <option value="paid">مسلم</option>
                            <option value="returned">مرتجع</option>
                        </select>
                    </div>
                </div>
                <div class="filter-tags" id="filterTags"></div>
            </div>
        </div>
        
        <div class="col-12 col-md-8">
            <!-- تبويبات المحتوى -->
            <ul class="nav nav-tabs" id="customerTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="invoices-tab" data-bs-toggle="tab" data-bs-target="#invoices" type="button" role="tab">
                        <i class="fas fa-receipt me-2"></i> الفواتير
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="work-orders-tab" data-bs-toggle="tab" data-bs-target="#work-orders" type="button" role="tab">
                        <i class="fas fa-tools me-2"></i> الشغلانات
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="wallet-tab" data-bs-toggle="tab" data-bs-target="#wallet" type="button" role="tab">
                        <i class="fas fa-wallet me-2"></i> حركات المحفظة
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="returns-tab" data-bs-toggle="tab" data-bs-target="#returns" type="button" role="tab">
                        <i class="fas fa-undo me-2"></i> المرتجعات
                    </button>
                </li>
            </ul>
            
            <div class="tab-content p-4" id="customerTabsContent">
                <!-- تبويب الفواتير -->
                <div class="tab-pane fade show active" id="invoices" role="tabpanel">
                    <div class="table-responsive-fixed">
                        <div class="mb-3 d-flex justify-content-between align-items-center">
                            <div>
                                <input type="checkbox" class="form-check-input" id="selectAllInvoices" />
                                <label class="form-check-label ms-2" for="selectAllInvoices">تحديد الكل</label>
                            </div>
                            <button class="btn btn-primary btn-sm" id="printSelectedInvoices" disabled>
                                <i class="fas fa-print me-2"></i>طباعة المحدد
                            </button>
                        </div>
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th style="width: 40px">
                                        <input type="checkbox" class="form-check-input" id="selectAllInvoicesHeader" />
                                    </th>
                                    <th>#</th>
                                    <th>التاريخ</th>
                                    <th>البنود</th>
                                    <th>الإجمالي</th>
                                    <th>المدفوع</th>
                                    <th>المتبقي</th>
                                    <th>الحالة</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody id="invoicesTableBody">
                                <!-- سيتم ملؤها بواسطة JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- تبويب الشغلانات -->
                <div class="tab-pane fade" id="work-orders" role="tabpanel">
                    <div class="row" id="workOrdersContainer">
                        <!-- سيتم ملؤها بواسطة JavaScript -->
                    </div>
                </div>
                
                <!-- تبويب حركات المحفظة -->
                <div class="tab-pane fade" id="wallet" role="tabpanel">
                    <div class="table-responsive-fixed">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>التاريخ</th>
                                    <th>نوع الحركة</th>
                                    <th>الوصف</th>
                                    <th>المبلغ</th>
                                    <th>الرصيد قبل</th>
                                    <th>الرصيد بعد</th>
                                    <th>المستخدم</th>
                                </tr>
                            </thead>
                            <tbody id="walletTableBody">
                                <!-- سيتم ملؤها بواسطة JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- تبويب المرتجعات -->
                <div class="tab-pane fade" id="returns" role="tabpanel">
                    <div class="table-responsive-fixed">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>رقم المرتجع</th>
                                    <th>الفاتورة الأصلية</th>
                                    <th>المنتج</th>
                                    <th>الكمية</th>
                                    <th>المبلغ</th>
                                    <th>طريقة الاسترجاع</th>
                                    <th>الحالة</th>
                                    <th>التاريخ</th>
                                    <th>المستخدم</th>
                                </tr>
                            </thead>
                            <tbody id="returnsTableBody">
                                <!-- سيتم ملؤها بواسطة JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- باقي المودالات كما هي من ملفك -->
<!-- ... باقي المودالات ... -->

<!-- JavaScript Libraries -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>

<script type="module">
    // إرسال بيانات PHP إلى JavaScript
    const PHP_DATA = {
        customer: <?php echo $js_customer_data; ?>,
        invoiceStats: <?php echo $js_invoice_stats; ?>,
        invoices: <?php echo $js_invoices; ?>,
        walletTransactions: <?php echo $js_wallet_transactions; ?>,
        returns: <?php echo $js_returns; ?>,
        workOrders: <?php echo $js_work_orders; ?>,
        currentUser: "<?php echo $_SESSION['user_name'] ?? 'مدير النظام'; ?>"
    };
    

    
    // استيراد الموديولات الخاصة بك
    import PaymentManager from "./js/payment.js";
    import WalletManager from "./js/wallet.js";
    import CustomerManager from "./js/customer.js";
    import PrintManager from "./js/print.js";
    import { setupNumberInputPrevention, escapeHtml } from "./js/helper.js";
    import { ReturnManager, CustomReturnManager } from "./js/return.js";
    import InvoiceManager from "./js/invoices.js";
    import { updateInvoiceStats } from "./js/helper.js";
    
    // AppData سيكون global للوصول إليه من الملفات الأخرى
    window.AppData = {
        customer: PHP_DATA.customer,
        invoices: PHP_DATA.invoices,
        walletTransactions: PHP_DATA.walletTransactions,
        returns: PHP_DATA.returns,
        workOrders: PHP_DATA.workOrders,
        currentUser: PHP_DATA.currentUser,
        activeFilters: {},
        nextWorkOrderId: PHP_DATA.workOrders.length + 1
    };
    
    // WorkOrderManager object
    const WorkOrderManager = {
        init() {
            this.updateWorkOrdersTable();
        },
        
        updateWorkOrdersTable() {
            const container = document.getElementById("workOrdersContainer");
            container.innerHTML = "";
            
            AppData.workOrders.forEach((workOrder) => {
                // ... كود عرض الشغلانات كما هو في ملفك ...
                // يمكنك نسخ الكود كما هو من ملفك الأصلي
            });
        }
    };
    
    // UIManager object
    const UIManager = {
        init() {
            this.setupEventListeners();
            this.initializeData();
        },
        
        initializeData() {
            // تعيين البيانات الأولية
            document.getElementById("currentBalance").textContent = 
                Math.abs(AppData.customer.balance).toFixed(2);
            document.getElementById("walletBalance").textContent = 
                AppData.customer.wallet.toFixed(2);
            
            // تحديث إحصائيات الفواتير من PHP
            const stats = PHP_DATA.invoiceStats;
            document.getElementById("totalInvoicesCount").textContent = stats.all.count;
            document.getElementById("pendingInvoicesCount").textContent = stats.pending.count;
            document.getElementById("partialInvoicesCount").textContent = stats.partial.count;
            document.getElementById("paidInvoicesCount").textContent = stats.paid.count;
            document.getElementById("returnedInvoicesCount").textContent = stats.returned.count;
            
            // تحديث جدول الفواتير
            InvoiceManager.updateInvoicesTable();
            
            // تحديث حركات المحفظة
            WalletManager.updateWalletTable();
            
            // تحديث المرتجعات
            ReturnManager.updateReturnsTable();
            
            // تحديث الشغلانات
            WorkOrderManager.updateWorkOrdersTable();
        },
        
        setupEventListeners() {
            // ... event listeners كما هي في ملفك ...
        }
    };
    
    // تهيئة التطبيق عند تحميل الصفحة
    document.addEventListener("DOMContentLoaded", function() {
        setupNumberInputPrevention();
        WorkOrderManager.init();
        UIManager.init();
    });
</script>
</body>
</html>
<?php
require_once BASE_DIR . 'partials/footer.php';
?>