<?php
// reports/profit_report_invoices_summary.responsive.php
$page_title = "تقرير الربح - ملخص الفواتير (محدث)";
require_once dirname(__DIR__) . '/config.php';
require_once BASE_DIR . 'partials/session_admin.php';

if (!isset($conn) || !$conn) { echo "DB connection error"; exit; }
function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// === AJAX endpoint: جلب بنود فاتورة معينة ===
if (isset($_GET['action']) && $_GET['action'] === 'get_invoice_items' && isset($_GET['id'])) {
    header('Content-Type: application/json; charset=utf-8');
    $inv_id = intval($_GET['id']);
    if ($inv_id <= 0) { echo json_encode(['ok'=>false,'msg'=>'معرف فاتورة غير صالح']); exit; }

    $sql_items = "
        SELECT 
            ioi.id,
            ioi.product_id,
            COALESCE(p.name,'') AS product_name,
            ioi.quantity,
            ioi.returned_quantity,
            ioi.available_for_return,
            ioi.return_flag,
            ioi.selling_price,
            ioi.total_before_discount,
            ioi.cost_price_per_unit,
            ioi.discount_type,
            ioi.discount_value,
            ioi.discount_amount,
            ioi.total_after_discount,
            CASE 
                WHEN ioi.discount_type = 'percent' 
                THEN CONCAT(ioi.discount_value, '%')
                WHEN ioi.discount_type = 'amount' 
                THEN CONCAT(ioi.discount_value, ' ج.م')
                ELSE 'لا يوجد'
            END AS discount_display,
            ROUND(ioi.selling_price * (1 - COALESCE(ioi.discount_value,0)/100), 2) AS price_after_item_discount
        FROM invoice_out_items ioi
        LEFT JOIN products p ON p.id = ioi.product_id
        WHERE ioi.invoice_out_id = ? 
        AND ioi.return_flag = 0
        ORDER BY ioi.id ASC
    ";
    
    if ($stmt = $conn->prepare($sql_items)) {
        $stmt->bind_param("i", $inv_id);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            $items = [];
            while ($r = $res->fetch_assoc()) {
                // تحويل القيم العددية
                $r['quantity'] = floatval($r['quantity'] ?? 0);
                $r['returned_quantity'] = floatval($r['returned_quantity'] ?? 0);
                $r['available_for_return'] = floatval($r['available_for_return'] ?? 0);
                $r['selling_price'] = floatval($r['selling_price'] ?? 0);
                $r['total_before_discount'] = floatval($r['total_before_discount'] ?? 0);
                $r['cost_price_per_unit'] = floatval($r['cost_price_per_unit'] ?? 0);
                $r['discount_value'] = floatval($r['discount_value'] ?? 0);
                $r['discount_amount'] = floatval($r['discount_amount'] ?? 0);
                $r['total_after_discount'] = floatval($r['total_after_discount'] ?? 0);
                $r['price_after_item_discount'] = floatval($r['price_after_item_discount'] ?? 0);
                
                // حساب الأرباح
                $r['line_cogs'] = $r['available_for_return'] * $r['cost_price_per_unit'];
                $r['line_profit'] = ($r['total_after_discount'] > 0 ? $r['total_after_discount'] : $r['total_before_discount']) - $r['line_cogs'];
                
                $items[] = $r;
            }
            echo json_encode(['ok'=>true,'items'=>$items], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['ok'=>false,'msg'=>'فشل تنفيذ الاستعلام: '.$stmt->error], JSON_UNESCAPED_UNICODE);
        }
        $stmt->close();
    } else {
        echo json_encode(['ok'=>false,'msg'=>'فشل تحضير الاستعلام: '.$conn->error], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// === Main report ===
$message = '';
$summaries = [];
$invoice_details = [];

// فلترة حسب حالة الفاتورة
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$advanced_filter = isset($_GET['advanced']) ? $_GET['advanced'] : '';
$customer_filter = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;

// الافتراضي: اليوم
$start_date_filter = isset($_GET['start_date']) && trim($_GET['start_date']) !== '' ? trim($_GET['start_date']) : date('Y-m-d');
$end_date_filter   = isset($_GET['end_date']) && trim($_GET['end_date']) !== '' ? trim($_GET['end_date']) : date('Y-m-d');

// زر من أول المدة
if (isset($_GET['from_beginning']) && $_GET['from_beginning'] == '1') {
    $start_date_filter = '2022-01-01';
    $end_date_filter = date('Y-m-d');
}

$report_generated = false;

if (!empty($start_date_filter) && !empty($end_date_filter)) {
    if (DateTime::createFromFormat('Y-m-d', $start_date_filter) === false || DateTime::createFromFormat('Y-m-d', $end_date_filter) === false) {
        $message = "<div class='alert alert-danger'>صيغة التاريخ غير صحيحة. استخدم YYYY-MM-DD.</div>";
    } elseif ($start_date_filter > $end_date_filter) {
        $message = "<div class='alert alert-danger'>تاريخ البدء لا يمكن أن يكون بعد تاريخ الانتهاء.</div>";
    } else {
        $report_generated = true;
        $start_sql = $start_date_filter . " 00:00:00";
        $end_sql   = $end_date_filter . " 23:59:59";

        // ====== بناء شروط WHERE ======
        $where_conditions = ["io.created_at BETWEEN ? AND ?"];
        
        // استبعاد الفواتير الملغاة والمرتجعة
        $where_conditions[] = "io.delivered NOT IN ('canceled', 'reverted')";
        
        // فلتر حسب حالة الدفع
        if ($status_filter) {
            switch($status_filter) {
                case 'paid':
                    $where_conditions[] = "io.remaining_amount = 0";
                    break;
                case 'partial':
                    $where_conditions[] = "io.paid_amount > 0 AND io.remaining_amount > 0";
                    break;
                case 'pending':
                    $where_conditions[] = "io.paid_amount = 0 AND io.remaining_amount > 0";
                    break;
                case 'returned':
                    $where_conditions[] = "io.delivered = 'reverted'";
                    break;
                case 'delivered':
                    $where_conditions[] = "io.delivered IN ('yes', 'partial')";
                    break;
            }
        }
        
        // فلتر حسب العميل
        if ($customer_filter > 0) {
            $where_conditions[] = "io.customer_id = " . $customer_filter;
        }
        
        $where_clause = implode(' AND ', $where_conditions);

        // ====== حساب إجماليات البطاقات ======
        $totals_sql = "
            SELECT
                COUNT(DISTINCT io.id) AS invoice_count,
                COALESCE(SUM(io.total_before_discount),0) AS total_revenue_before_discount,
                COALESCE(SUM(io.total_after_discount),0) AS total_revenue_after_discount,
                COALESCE(SUM(io.discount_amount),0) AS total_discount,
                COALESCE(SUM(io.total_cost),0) AS total_cost,
                COALESCE(SUM(io.profit_amount),0) AS total_profit,
                COALESCE(SUM(io.paid_amount),0) AS total_paid,
                COALESCE(SUM(io.remaining_amount),0) AS total_remaining
            FROM invoices_out io
            WHERE {$where_clause}
        ";
        
        if ($stt = $conn->prepare($totals_sql)) {
            $stt->bind_param("ss", $start_sql, $end_sql);
            if ($stt->execute()) {
                $r = $stt->get_result()->fetch_assoc();
                $invoice_count = intval($r['invoice_count'] ?? 0);
                $total_revenue_before_discount = floatval($r['total_revenue_before_discount'] ?? 0);
                $total_revenue_after_discount = floatval($r['total_revenue_after_discount'] ?? 0);
                $total_discount = floatval($r['total_discount'] ?? 0);
                $total_cost = floatval($r['total_cost'] ?? 0);
                $total_profit = floatval($r['total_profit'] ?? 0);
                $total_paid = floatval($r['total_paid'] ?? 0);
                $total_remaining = floatval($r['total_remaining'] ?? 0);
                
                // حساب النسب
                $profit_margin = ($total_revenue_after_discount > 0) ? ($total_profit / $total_revenue_after_discount) * 100 : 0;
                $discount_percent = ($total_revenue_before_discount > 0) ? ($total_discount / $total_revenue_before_discount) * 100 : 0;
                
            } else {
                $message = "<div class='alert alert-danger'>فشل حساب الإجماليات: " . e($stt->error) . "</div>";
            }
            $stt->close();
        } else {
            $message = "<div class='alert alert-danger'>فشل تحضير استعلام الإجماليات: " . e($conn->error) . "</div>";
        }

        // ====== جلب قائمة الفواتير مع التفاصيل ======
        $sql = "
            SELECT
                io.id AS invoice_id,
                io.created_at AS invoice_created_at,
                COALESCE(c.name, '') AS customer_name,
                c.id AS customer_id,
                io.delivered,
                io.total_before_discount,
                io.discount_type,
                io.discount_value,
                io.discount_amount,
                io.total_after_discount,
                io.total_cost,
                io.profit_amount,
                io.paid_amount,
                io.remaining_amount,
                io.invoice_group,
                io.discount_scope,
                CASE 
                    WHEN io.delivered = 'reverted' THEN 'returned'
                    WHEN io.remaining_amount = 0 THEN 'paid'
                    WHEN io.paid_amount > 0 AND io.remaining_amount > 0 THEN 'partial'
                    ELSE 'pending'
                END AS payment_status,
                CASE 
                    WHEN io.discount_scope = 'invoice' AND io.discount_amount > 0 THEN 'نعم'
                    WHEN io.discount_scope = 'items' THEN 'على البنود'
                    WHEN io.discount_scope = 'mixed' THEN 'مختلط'
                    ELSE 'لا'
                END AS has_discount,
                (SELECT COUNT(*) FROM invoice_out_items ioi WHERE ioi.invoice_out_id = io.id AND ioi.return_flag = 0) AS active_items_count,
                (SELECT COUNT(*) FROM invoice_out_items ioi WHERE ioi.invoice_out_id = io.id AND ioi.return_flag = 1) AS returned_items_count
            FROM invoices_out io
            LEFT JOIN customers c ON c.id = io.customer_id
            WHERE {$where_clause}
            ORDER BY io.created_at DESC, io.id DESC
        ";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ss", $start_sql, $end_sql);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    // تحويل القيم العددية
                    $row['invoice_id'] = intval($row['invoice_id']);
                    $row['customer_id'] = intval($row['customer_id'] ?? 0);
                    $row['total_before_discount'] = floatval($row['total_before_discount'] ?? 0);
                    $row['discount_value'] = floatval($row['discount_value'] ?? 0);
                    $row['discount_amount'] = floatval($row['discount_amount'] ?? 0);
                    $row['total_after_discount'] = floatval($row['total_after_discount'] ?? 0);
                    $row['total_cost'] = floatval($row['total_cost'] ?? 0);
                    $row['profit_amount'] = floatval($row['profit_amount'] ?? 0);
                    $row['paid_amount'] = floatval($row['paid_amount'] ?? 0);
                    $row['remaining_amount'] = floatval($row['remaining_amount'] ?? 0);
                    $row['active_items_count'] = intval($row['active_items_count'] ?? 0);
                    $row['returned_items_count'] = intval($row['returned_items_count'] ?? 0);
                    
                    // حساب هامش الربح
                    $row['profit_margin'] = ($row['total_after_discount'] > 0) 
                        ? round(($row['profit_amount'] / $row['total_after_discount']) * 100, 2)
                        : 0;
                    
                    $summaries[] = $row;
                }
            } else {
                $message = "<div class='alert alert-danger'>خطأ في تنفيذ الاستعلام: " . e($stmt->error) . "</div>";
            }
            $stmt->close();
        } else {
            $message = "<div class='alert alert-danger'>خطأ في تحضير الاستعلام: " . e($conn->error) . "</div>";
        }

        // ====== جلب قائمة العملاء للفلتر ======
        $customers = [];
        $cust_sql = "
            SELECT DISTINCT c.id, c.name 
            FROM invoices_out io
            JOIN customers c ON c.id = io.customer_id
            WHERE io.created_at BETWEEN ? AND ?
            ORDER BY c.name
        ";
        if ($cust_stmt = $conn->prepare($cust_sql)) {
            $cust_stmt->bind_param("ss", $start_sql, $end_sql);
            if ($cust_stmt->execute()) {
                $cust_res = $cust_stmt->get_result();
                while ($cust = $cust_res->fetch_assoc()) {
                    $customers[] = $cust;
                }
            }
            $cust_stmt->close();
        }
    }
}

require_once BASE_DIR . 'partials/header.php';
require_once BASE_DIR . 'partials/sidebar.php';
?>

<style>
:root {
  --primary: #0b84ff;
  --success: #10b981;
  --warning: #f59e0b;
  --danger: #ef4444;
  --info: #3b82f6;
  --surface: #ffffff;
  --surface-2: #f9fbff;
  --text: #0f172a;
  --text-soft: #334155;
  --muted: #64748b;
  --border: rgba(2,6,23,0.08);
  --radius: 14px;
  --shadow-1: 0 10px 24px rgba(15,23,42,0.06);
  --shadow-2: 0 12px 28px rgba(11,132,255,0.14);
}

.container-fluid { padding: 18px; }
.page-header { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:20px; }
.page-header h3 { margin:0; font-size:1.5rem; color:var(--text); font-weight:700; }
.page-header .subtitle { color:var(--text-soft); font-size:0.95rem; }

/* بطاقات الإحصائيات */
.stats-grid { 
    display: grid; 
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); 
    gap: 16px; 
    margin-bottom: 24px; 
}
.stat-card {
    background: var(--surface);
    border-radius: var(--radius);
    padding: 18px;
    box-shadow: var(--shadow-1);
    border-left: 4px solid var(--primary);
    transition: transform 0.2s ease;
}
.stat-card:hover { transform: translateY(-4px); }
.stat-card.revenue { border-left-color: #10b981; }
.stat-card.cost { border-left-color: #f59e0b; }
.stat-card.profit { border-left-color: #8b5cf6; }
.stat-card.discount { border-left-color: #3b82f6; }
.stat-card .stat-title { 
    color: var(--muted); 
    font-size: 0.9rem; 
    margin-bottom: 8px; 
    font-weight: 600; 
}
.stat-card .stat-value { 
    font-size: 1.8rem; 
    font-weight: 800; 
    color: var(--text); 
    margin-bottom: 4px; 
}
.stat-card .stat-sub { 
    color: var(--text-soft); 
    font-size: 0.85rem; 
}
.stat-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    margin-left: 8px;
}
.badge-positive { background: rgba(16,185,129,0.12); color: #065f46; }
.badge-negative { background: rgba(239,68,68,0.12); color: #991b1b; }
.badge-neutral { background: rgba(107,114,128,0.12); color: #374151; }

/* بادجات حالة الفاتورة */
.status-badge {
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    display: inline-block;
    min-width: 80px;
    text-align: center;
}
.status-paid { background: rgba(16,185,129,0.12); color: #065f46; border: 1px solid rgba(16,185,129,0.2); }
.status-partial { background: rgba(245,158,11,0.12); color: #92400e; border: 1px solid rgba(245,158,11,0.2); }
.status-pending { background: rgba(239,68,68,0.08); color: #991b1b; border: 1px solid rgba(239,68,68,0.15); }
.status-returned { background: rgba(107,114,128,0.12); color: #374151; border: 1px solid rgba(107,114,128,0.2); }
.status-delivered { background: rgba(59,130,246,0.12); color: #1e40af; border: 1px solid rgba(59,130,246,0.2); }

/* فلاتر */
.filter-section { 
    background: var(--surface-2); 
    border-radius: var(--radius); 
    padding: 16px; 
    margin-bottom: 20px; 
    border: 1px solid var(--border);
}
.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
    margin-bottom: 12px;
}
.filter-group { margin-bottom: 12px; }
.filter-group label { 
    display: block; 
    margin-bottom: 6px; 
    font-weight: 600; 
    color: var(--text-soft); 
    font-size: 0.9rem; 
}
.filter-select, .filter-input { 
    width: 100%; 
    padding: 8px 12px; 
    border-radius: 8px; 
    border: 1px solid var(--border); 
    background: var(--surface);
    color: var(--text);
    font-size: 0.95rem;
}
.filter-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 10px;
}
.btn { 
    padding: 8px 16px; 
    border-radius: 8px; 
    border: none; 
    font-weight: 600; 
    cursor: pointer; 
    font-size: 0.9rem; 
    transition: all 0.2s ease;
}
.btn-primary { background: var(--primary); color: white; }
.btn-secondary { background: var(--muted); color: white; }
.btn-success { background: var(--success); color: white; }
.btn-light { background: var(--surface-2); color: var(--text); border: 1px solid var(--border); }
.btn-sm { padding: 6px 12px; font-size: 0.85rem; }

/* الجدول */
.table-wrapper { 
    background: var(--surface); 
    border-radius: var(--radius); 
    overflow: hidden; 
    box-shadow: var(--shadow-1);
    margin-bottom: 24px;
}
.table-header { 
    padding: 16px; 
    border-bottom: 1px solid var(--border); 
    background: var(--surface-2);
}
.table-header h5 { margin: 0; color: var(--text); }
.custom-table { 
    width: 100%; 
    border-collapse: collapse; 
    font-size: 0.9rem;
}
.custom-table th { 
    padding: 12px 16px; 
    text-align: right; 
    background: var(--surface-2); 
    color: var(--text-soft); 
    font-weight: 600; 
    border-bottom: 2px solid var(--border);
}
.custom-table td { 
    padding: 12px 16px; 
    border-bottom: 1px solid var(--border); 
    color: var(--text);
}
.custom-table tbody tr:hover { background: var(--surface-2); }
.text-end { text-align: right; }
.text-center { text-align: center; }

/* المودال */
.modal-backdrop-lite { 
    position: fixed; 
    left: 0; top: 0; 
    right: 0; bottom: 0; 
    background: rgba(2,6,23,0.5); 
    display: none; 
    align-items: center; 
    justify-content: center; 
    z-index: 9999; 
    padding: 16px; 
}
.modal-card { 
    background: var(--surface); 
    border-radius: 12px; 
    max-width: 1000px; 
    width: 100%; 
    max-height: 85vh; 
    overflow: auto; 
    padding: 24px; 
    box-shadow: var(--shadow-2);
}
.modal-header { 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    margin-bottom: 20px; 
    padding-bottom: 16px; 
    border-bottom: 1px solid var(--border);
}
.modal-header h5 { margin: 0; color: var(--text); font-size: 1.2rem; }
.modal-body { padding: 0; }

/* خصومات */
.discount-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 6px;
    background: rgba(59,130,246,0.12);
    color: #1e40af;
    font-size: 0.8rem;
    font-weight: 600;
    margin-right: 5px;
}

/* الأدوات */
.tools-bar {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

@media (max-width: 768px) {
    .stats-grid { grid-template-columns: 1fr; }
    .filter-grid { grid-template-columns: 1fr; }
    .custom-table { font-size: 0.85rem; }
    .custom-table th, .custom-table td { padding: 8px 12px; }
}
</style>

<div class="container-fluid">
    <div class="page-header">
        <div>
            <h3><i class="fas fa-chart-line me-2"></i> تقرير الربح - ملخص الفواتير</h3>
            <div class="subtitle">عرض الفواتير المسلّمة مع استبعاد المرتجعة والملغاة | حساب الأرباح من بيانات الفاتورة المخزنة</div>
        </div>
        <div class="tools-bar">
            <a href="<?php echo htmlspecialchars(BASE_URL . 'user/welcome.php'); ?>" class="btn btn-light">
                <i class="fas fa-arrow-right me-1"></i> العودة
            </a>
            <button id="printBtn" class="btn btn-primary">
                <i class="fas fa-print me-1"></i> طباعة
            </button>
        </div>
    </div>

    <?php echo $message; ?>

    <!-- فلترة -->
    <div class="filter-section">
        <form id="filterForm" method="get" class="filter-form">
            <div class="filter-grid">
                <div class="filter-group">
                    <label><i class="fas fa-filter me-1"></i> حالة الفاتورة:</label>
                    <select name="status" class="filter-select">
                        <option value="">جميع الحالات</option>
                        <option value="delivered" <?php echo $status_filter == 'delivered' ? 'selected' : ''; ?>>مسلّم</option>
                        <option value="paid" <?php echo $status_filter == 'paid' ? 'selected' : ''; ?>>مدفوع بالكامل</option>
                        <option value="partial" <?php echo $status_filter == 'partial' ? 'selected' : ''; ?>>مدفوع جزئي</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>قيد الانتظار</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label><i class="fas fa-user me-1"></i> العميل:</label>
                    <select name="customer_id" class="filter-select">
                        <option value="0">جميع العملاء</option>
                        <?php foreach ($customers ?? [] as $cust): ?>
                        <option value="<?php echo $cust['id']; ?>" <?php echo $customer_filter == $cust['id'] ? 'selected' : ''; ?>>
                            <?php echo e($cust['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label><i class="fas fa-calendar me-1"></i> من تاريخ:</label>
                    <input type="date" name="start_date" class="filter-input" value="<?php echo e($start_date_filter); ?>" required>
                </div>
                
                <div class="filter-group">
                    <label><i class="fas fa-calendar me-1"></i> إلى تاريخ:</label>
                    <input type="date" name="end_date" class="filter-input" value="<?php echo e($end_date_filter); ?>" required>
                </div>
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search me-1"></i> عرض التقرير
                </button>
                
                <button type="submit" name="from_beginning" value="1" class="btn btn-success">
                    <i class="fas fa-history me-1"></i> من أول المدة (2022)
                </button>
                
                <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-light">
                    <i class="fas fa-redo me-1"></i> إعادة تعيين
                </a>
                
                <div style="margin-left: auto; display: flex; gap: 8px;">
                    <button type="button" id="btnToday" class="btn btn-sm btn-secondary">اليوم</button>
                    <button type="button" id="btnWeek" class="btn btn-sm btn-secondary">أسبوع</button>
                    <button type="button" id="btnMonth" class="btn btn-sm btn-secondary">شهر</button>
                </div>
            </div>
        </form>
    </div>

    <?php if ($report_generated): ?>
        <!-- إحصائيات رئيسية -->
        <div class="stats-grid">
            <div class="stat-card revenue">
                <div class="stat-title">إجمالي الإيرادات</div>
                <div class="stat-value"><?php echo number_format($total_revenue_after_discount ?? 0, 2); ?> <small>ج.م</small></div>
                <div class="stat-sub">
                    قبل الخصم: <?php echo number_format($total_revenue_before_discount ?? 0, 2); ?> ج.م
                    <?php if ($total_discount > 0): ?>
                        <span class="badge-neutral">خصم: <?php echo number_format($total_discount, 2); ?> ج.م (<?php echo round($discount_percent, 2); ?>%)</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="stat-card cost">
                <div class="stat-title">تكلفة البضاعة المباعة</div>
                <div class="stat-value"><?php echo number_format($total_cost ?? 0, 2); ?> <small>ج.م</small></div>
                <div class="stat-sub">من <?php echo $invoice_count ?? 0; ?> فاتورة</div>
            </div>
            
            <div class="stat-card profit">
                <div class="stat-title">صافي الربح</div>
                <div class="stat-value">
                    <?php echo number_format($total_profit ?? 0, 2); ?> <small>ج.م</small>
                    <span class="stat-badge <?php echo ($total_profit ?? 0) >= 0 ? 'badge-positive' : 'badge-negative'; ?>">
                        <?php echo round($profit_margin ?? 0, 2); ?>%
                    </span>
                </div>
                <div class="stat-sub">هامش الربح</div>
            </div>
            
            <div class="stat-card discount">
                <div class="stat-title">المدفوعات</div>
                <div class="stat-value"><?php echo number_format($total_paid ?? 0, 2); ?> <small>ج.م</small></div>
                <div class="stat-sub">المتبقي: <?php echo number_format($total_remaining ?? 0, 2); ?> ج.م</div>
            </div>
        </div>

        <!-- جدول الفواتير -->
        <div class="table-wrapper">
            <div class="table-header">
                <h5><i class="fas fa-file-invoice me-2"></i> قائمة الفواتير (<?php echo count($summaries); ?> فاتورة)</h5>
            </div>
            
            <?php if (empty($summaries)): ?>
                <div style="padding: 40px; text-align: center; color: var(--muted);">
                    <i class="fas fa-inbox fa-2x mb-3"></i>
                    <p>لا توجد فواتير مطابقة للفلاتر المحددة.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="custom-table" id="reportTable">
                        <thead>
                            <tr>
                                <th style="width: 80px">#</th>
                                <th style="width: 150px">التاريخ</th>
                                <th>العميل</th>
                                <th style="width: 130px" class="text-end">الإيرادات</th>
                                <th style="width: 120px" class="text-end">التكلفة</th>
                                <th style="width: 120px" class="text-end">الربح</th>
                                <th style="width: 100px" class="text-center">هامش</th>
                                <th style="width: 100px" class="text-center">الحالة</th>
                                <th style="width: 120px" class="text-end">المدفوع</th>
                                <th style="width: 100px" class="text-center">الخصم</th>
                                <th style="width: 100px" class="text-center">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($summaries as $invoice): 
                                $profit_class = ($invoice['profit_amount'] >= 0) ? 'badge-positive' : 'badge-negative';
                                $status_class = 'status-' . $invoice['payment_status'];
                                $status_text = '';
                                switch($invoice['payment_status']) {
                                    case 'paid': $status_text = 'مدفوع'; break;
                                    case 'partial': $status_text = 'جزئي'; break;
                                    case 'pending': $status_text = 'معلق'; break;
                                    case 'returned': $status_text = 'مرتجع'; break;
                                    case 'delivered': $status_text = 'مسلّم'; break;
                                    default: $status_text = $invoice['payment_status'];
                                }
                            ?>
                            <tr data-invoice-id="<?php echo $invoice['invoice_id']; ?>">
                                <td><strong>#<?php echo $invoice['invoice_id']; ?></strong></td>
                                <td><?php echo date('Y-m-d H:i', strtotime($invoice['invoice_created_at'])); ?></td>
                                <td>
                                    <?php echo e($invoice['customer_name']); ?>
                                    <?php if ($invoice['active_items_count'] > 0): ?>
                                        <small class="text-muted d-block"><?php echo $invoice['active_items_count']; ?> بند نشط</small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($invoice['discount_amount'] > 0): ?>
                                        <div class="text-decoration-line-through text-muted small">
                                            <?php echo number_format($invoice['total_before_discount'], 2); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php echo number_format($invoice['total_after_discount'], 2); ?> ج.م
                                </td>
                                <td class="text-end"><?php echo number_format($invoice['total_cost'], 2); ?> ج.م</td>
                                <td class="text-end">
                                    <span class="<?php echo $profit_class; ?> stat-badge">
                                        <?php echo number_format($invoice['profit_amount'], 2); ?> ج.م
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="<?php echo $invoice['profit_margin'] >= 0 ? 'badge-positive' : 'badge-negative'; ?> stat-badge">
                                        <?php echo $invoice['profit_margin']; ?>%
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <?php if ($invoice['paid_amount'] > 0): ?>
                                        <div><?php echo number_format($invoice['paid_amount'], 2); ?> ج.م</div>
                                        <?php if ($invoice['remaining_amount'] > 0): ?>
                                            <small class="text-muted">متبقي: <?php echo number_format($invoice['remaining_amount'], 2); ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($invoice['discount_amount'] > 0): ?>
                                        <span class="discount-badge">
                                            <?php if ($invoice['discount_type'] == 'percent'): ?>
                                                <?php echo $invoice['discount_value']; ?>%
                                            <?php else: ?>
                                                <?php echo number_format($invoice['discount_value'], 2); ?> ج.م
                                            <?php endif; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-primary details-btn" 
                                            data-invoice-id="<?php echo $invoice['invoice_id']; ?>"
                                            title="عرض التفاصيل">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <a href="<?php echo BASE_URL; ?>invoices_out/view_invoice_detaiels.php?id=<?php echo $invoice['invoice_id']; ?>" 
                                       class="btn btn-sm btn-light" target="_blank" title="فتح الفاتورة">
                                        <i class="fas fa-external-link-alt"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-end"><strong>المجموع:</strong></td>
                                <td class="text-end"><strong><?php echo number_format(array_sum(array_column($summaries, 'total_after_discount')), 2); ?> ج.م</strong></td>
                                <td class="text-end"><strong><?php echo number_format(array_sum(array_column($summaries, 'total_cost')), 2); ?> ج.م</strong></td>
                                <td class="text-end"><strong><?php echo number_format(array_sum(array_column($summaries, 'profit_amount')), 2); ?> ج.م</strong></td>
                                <td class="text-center">
                                    <?php 
                                        $total_rev = array_sum(array_column($summaries, 'total_after_discount'));
                                        $total_prof = array_sum(array_column($summaries, 'profit_amount'));
                                        $avg_margin = $total_rev > 0 ? ($total_prof / $total_rev) * 100 : 0;
                                    ?>
                                    <strong><?php echo round($avg_margin, 2); ?>%</strong>
                                </td>
                                <td colspan="4"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- ملاحظات -->
        <div class="card" style="background: var(--surface-2); border: none; padding: 16px; border-radius: var(--radius);">
            <div class="card-body">
                <h6><i class="fas fa-info-circle me-2"></i> معلومات حول التقرير:</h6>
                <ul style="margin-bottom: 0; color: var(--text-soft); font-size: 0.9rem;">
                    <li>يتم حساب الأرباح من القيم المخزنة في جدول الفواتير مباشرة (profit_amount).</li>
                    <li>تم استبعاد البنود المرتجعة بالكامل (return_flag = 1).</li>
                    <li>الإيرادات المعروضة هي بعد تطبيق جميع الخصومات.</li>
                    <li>هامش الربح = (الربح ÷ الإيرادات بعد الخصم) × 100.</li>
                    <li>الكمية الفعالة = الكمية الأصلية - الكمية المرتجعة (available_for_return).</li>
                    <li>اضغط زر <i class="fas fa-eye"></i> لعرض تفاصيل بنود الفاتورة مع الخصومات.</li>
                </ul>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal لعرض تفاصيل الفاتورة -->
<div id="modalBackdrop" class="modal-backdrop-lite" role="dialog" aria-hidden="true">
    <div class="modal-card" role="document" aria-modal="true">
        <div class="modal-header">
            <h5 id="modalTitle">تفاصيل فاتورة <span id="invoiceNumber"></span></h5>
            <div>
                <button id="closeModal" class="btn btn-light btn-sm">
                    <i class="fas fa-times"></i> إغلاق
                </button>
            </div>
        </div>
        <div class="modal-body">
            <div class="mb-3" id="modalInvoiceInfo"></div>
            <div id="modalContent">جارٍ تحميل تفاصيل الفاتورة...</div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    // عناصر DOM
    const filterForm = document.getElementById('filterForm');
    const startDate = document.querySelector('input[name="start_date"]');
    const endDate = document.querySelector('input[name="end_date"]');
    const btnToday = document.getElementById('btnToday');
    const btnWeek = document.getElementById('btnWeek');
    const btnMonth = document.getElementById('btnMonth');
    const printBtn = document.getElementById('printBtn');
    
    // المودال
    const modal = document.getElementById('modalBackdrop');
    const modalTitle = document.getElementById('modalTitle');
    const modalInvoiceInfo = document.getElementById('modalInvoiceInfo');
    const modalContent = document.getElementById('modalContent');
    const closeModal = document.getElementById('closeModal');
    const invoiceNumber = document.getElementById('invoiceNumber');
    
    // تاريخ اليوم
    function getToday() {
        const today = new Date();
        return today.toISOString().split('T')[0];
    }
    
    // تاريخ الأسبوع
    function getWeekRange() {
        const today = new Date();
        const day = today.getDay();
        const diff = today.getDate() - day + (day === 0 ? -6 : 1);
        const start = new Date(today.setDate(diff));
        const end = new Date(start);
        end.setDate(start.getDate() + 6);
        
        return {
            start: start.toISOString().split('T')[0],
            end: end.toISOString().split('T')[0]
        };
    }
    
    // تاريخ الشهر
    function getMonthRange() {
        const today = new Date();
        const start = new Date(today.getFullYear(), today.getMonth(), 1);
        const end = new Date(today.getFullYear(), today.getMonth() + 1, 0);
        
        return {
            start: start.toISOString().split('T')[0],
            end: end.toISOString().split('T')[0]
        };
    }
    
    // أحداث الأزرار
    btnToday?.addEventListener('click', function() {
        startDate.value = getToday();
        endDate.value = getToday();
        filterForm.submit();
    });
    
    btnWeek?.addEventListener('click', function() {
        const range = getWeekRange();
        startDate.value = range.start;
        endDate.value = range.end;
        filterForm.submit();
    });
    
    btnMonth?.addEventListener('click', function() {
        const range = getMonthRange();
        startDate.value = range.start;
        endDate.value = range.end;
        filterForm.submit();
    });
    
    // طباعة التقرير
    printBtn?.addEventListener('click', function() {
        const printWindow = window.open('', '_blank');
        const title = document.querySelector('.page-header h3').innerText;
        const period = `${startDate.value} إلى ${endDate.value}`;
        
        let html = `
            <!DOCTYPE html>
            <html dir="rtl" lang="ar">
            <head>
                <meta charset="UTF-8">
                <title>${title}</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; }
                    .header { text-align: center; margin-bottom: 30px; }
                    .header h1 { margin: 0; color: #333; }
                    .header .period { color: #666; margin-top: 5px; }
                    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    th, td { padding: 10px; text-align: right; border: 1px solid #ddd; }
                    th { background-color: #f5f5f5; font-weight: bold; }
                    .totals { margin-top: 30px; padding: 15px; background: #f9f9f9; border-radius: 5px; }
                    .total-row { display: flex; justify-content: space-between; margin-bottom: 10px; }
                    .positive { color: green; }
                    .negative { color: red; }
                    @media print {
                        body { padding: 0; }
                        .no-print { display: none; }
                    }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1>${title}</h1>
                    <div class="period">الفترة: ${period}</div>
                    <div class="period">تاريخ الطباعة: ${new Date().toLocaleDateString('ar-EG')}</div>
                </div>
        `;
        
        // نسخ بيانات الجدول
        const table = document.getElementById('reportTable');
        if (table) {
            html += table.outerHTML;
        }
        
        // إضافة الإجماليات
        html += `
            <div class="totals">
                <div class="total-row">
                    <span>عدد الفواتير:</span>
                    <span>${<?php echo count($summaries); ?>}</span>
                </div>
                <div class="total-row">
                    <span>إجمالي الإيرادات:</span>
                    <span>${<?php echo number_format($total_revenue_after_discount ?? 0, 2); ?>} ج.م</span>
                </div>
                <div class="total-row">
                    <span>إجمالي التكلفة:</span>
                    <span>${<?php echo number_format($total_cost ?? 0, 2); ?>} ج.م</span>
                </div>
                <div class="total-row">
                    <span>صافي الربح:</span>
                    <span class="${($total_profit ?? 0) >= 0 ? 'positive' : 'negative'}">
                        ${<?php echo number_format($total_profit ?? 0, 2); ?>} ج.م (${<?php echo round($profit_margin ?? 0, 2); ?>}%)
                    </span>
                </div>
                <div class="total-row">
                    <span>إجمالي الخصم:</span>
                    <span>${<?php echo number_format($total_discount ?? 0, 2); ?>} ج.م</span>
                </div>
            </div>
        `;
        
        html += `
            </body>
            </html>
        `;
        
        printWindow.document.write(html);
        printWindow.document.close();
        setTimeout(() => {
            printWindow.print();
        }, 500);
    });
    
    // عرض تفاصيل الفاتورة
    document.querySelectorAll('.details-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const invoiceId = this.dataset.invoiceId;
            openInvoiceModal(invoiceId);
        });
    });
    
    async function openInvoiceModal(invoiceId) {
        // عرض المودال
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
        invoiceNumber.textContent = '#' + invoiceId;
        
        // جلب بيانات الفاتورة
        try {
            modalContent.innerHTML = '<div class="text-center p-4"><i class="fas fa-spinner fa-spin"></i> جارٍ تحميل تفاصيل الفاتورة...</div>';
            
            const response = await fetch(`?action=get_invoice_items&id=${invoiceId}`);
            const data = await response.json();
            
            if (!data.ok) {
                modalContent.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> ${data.msg || 'حدث خطأ أثناء جلب البيانات'}
                    </div>
                `;
                return;
            }
            
            const items = data.items || [];
            
            if (items.length === 0) {
                modalContent.innerHTML = '<div class="alert alert-info">لا توجد بنود نشطة في هذه الفاتورة.</div>';
                return;
            }
            
            // بناء جدول البنود
            let html = `
                <div style="overflow-x: auto;">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>المنتج</th>
                                <th style="width: 100px" class="text-center">الكمية</th>
                                <th style="width: 100px" class="text-center">المُرجَع</th>
                                <th style="width: 100px" class="text-center">المتبقي</th>
                                <th style="width: 120px" class="text-end">سعر الوحدة</th>
                                <th style="width: 120px" class="text-center">خصم البند</th>
                                <th style="width: 120px" class="text-end">الإجمالي</th>
                                <th style="width: 120px" class="text-end">التكلفة</th>
                                <th style="width: 120px" class="text-end">الربح</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            let totalQuantity = 0;
            let totalReturned = 0;
            let totalAvailable = 0;
            let totalRevenue = 0;
            let totalCost = 0;
            let totalProfit = 0;
            let totalDiscount = 0;
            
            items.forEach(item => {
                const quantity = parseFloat(item.quantity) || 0;
                const returned = parseFloat(item.returned_quantity) || 0;
                const available = parseFloat(item.available_for_return) || 0;
                const sellingPrice = parseFloat(item.selling_price) || 0;
                const costPrice = parseFloat(item.cost_price_per_unit) || 0;
                const itemDiscount = parseFloat(item.discount_amount) || 0;
                const revenue = parseFloat(item.total_after_discount) || parseFloat(item.total_before_discount) || 0;
                const cost = available * costPrice;
                const profit = revenue - cost;
                
                totalQuantity += quantity;
                totalReturned += returned;
                totalAvailable += available;
                totalRevenue += revenue;
                totalCost += cost;
                totalProfit += profit;
                totalDiscount += itemDiscount;
                
                html += `
                    <tr>
                        <td>${escapeHtml(item.product_name || 'منتج #' + item.product_id)}</td>
                        <td class="text-center">${quantity.toFixed(2)}</td>
                        <td class="text-center">${returned.toFixed(2)}</td>
                        <td class="text-center"><strong>${available.toFixed(2)}</strong></td>
                        <td class="text-end">
                            ${item.discount_type ? `
                                <div class="text-decoration-line-through text-muted small">
                                    ${sellingPrice.toFixed(2)}
                                </div>
                                <div>${item.price_after_item_discount?.toFixed(2) || sellingPrice.toFixed(2)}</div>
                            ` : sellingPrice.toFixed(2)}
                        </td>
                        <td class="text-center">
                            ${item.discount_type ? `
                                <span class="discount-badge">
                                    ${item.discount_display}
                                </span>
                            ` : '<span class="text-muted">-</span>'}
                        </td>
                        <td class="text-end">${revenue.toFixed(2)}</td>
                        <td class="text-end">${cost.toFixed(2)}</td>
                        <td class="text-end">
                            <span class="${profit >= 0 ? 'badge-positive' : 'badge-negative'} stat-badge">
                                ${profit.toFixed(2)}
                            </span>
                        </td>
                    </tr>
                `;
            });
            
            // إجماليات البنود
            html += `
                        </tbody>
                        <tfoot>
                            <tr>
                                <th class="text-end">المجموع:</th>
                                <th class="text-center">${totalQuantity.toFixed(2)}</th>
                                <th class="text-center">${totalReturned.toFixed(2)}</th>
                                <th class="text-center">${totalAvailable.toFixed(2)}</th>
                                <th></th>
                                <th class="text-center">${totalDiscount.toFixed(2)} ج.م</th>
                                <th class="text-end">${totalRevenue.toFixed(2)} ج.م</th>
                                <th class="text-end">${totalCost.toFixed(2)} ج.م</th>
                                <th class="text-end">
                                    <span class="${totalProfit >= 0 ? 'badge-positive' : 'badge-negative'} stat-badge">
                                        ${totalProfit.toFixed(2)} ج.م
                                    </span>
                                </th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            `;
            
            modalContent.innerHTML = html;
            
        } catch (error) {
            console.error('Error loading invoice details:', error);
            modalContent.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> حدث خطأ في الاتصال بالخادم.
                </div>
            `;
        }
    }
    
    // إغلاق المودال
    closeModal.addEventListener('click', function() {
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
        modalContent.innerHTML = '';
    });
    
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
            modalContent.innerHTML = '';
        }
    });
    
    // دالة escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>

<?php
require_once BASE_DIR . 'partials/footer.php';
$conn->close();
?>