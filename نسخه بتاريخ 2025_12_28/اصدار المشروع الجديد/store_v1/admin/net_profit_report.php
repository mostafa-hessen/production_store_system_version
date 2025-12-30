<?php
// reports/net_profit_summary.responsive.php
$page_title = "تقرير صافي الربح — ملخص تفصيلي";
require_once dirname(__DIR__) . '/config.php';
require_once BASE_DIR . 'partials/session_admin.php';

if (!isset($conn) || !$conn) {
    echo "DB connection error";
    exit;
}
function e($s)
{
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// الافتراضي: اليوم
$start_date_filter = isset($_GET['start_date']) && trim($_GET['start_date']) !== '' ? trim($_GET['start_date']) : date('Y-m-d');
$end_date_filter   = isset($_GET['end_date']) && trim($_GET['end_date']) !== '' ? trim($_GET['end_date']) : date('Y-m-d');
$status_filter = isset($_GET['status']) && trim($_GET['status']) !== '' ? trim($_GET['status']) : 'all';
$discount_filter = isset($_GET['discount']) && trim($_GET['discount']) !== '' ? trim($_GET['discount']) : 'all';

$message = '';
$summaries = [];
$expenses_list = [];
$report_generated = false;

$grand_total_revenue_before_discount = 0.0;
$grand_total_revenue_after_discount = 0.0;
$grand_total_cost = 0.0;
$grand_total_discount = 0.0;
$total_invoice_discount = 0.0;
$total_items_discount = 0.0;
$total_expenses = 0.0;
$net_profit = 0.0;

// زر "من أول المدة" - يضبط التاريخ إلى 2022-01-01
if (isset($_GET['reset_period']) && $_GET['reset_period'] == '1') {
    $start_date_filter = '2022-01-01';
    $end_date_filter = date('Y-m-d');
}

// validate and produce report
if (!empty($start_date_filter) && !empty($end_date_filter)) {
    if (DateTime::createFromFormat('Y-m-d', $start_date_filter) === false || DateTime::createFromFormat('Y-m-d', $end_date_filter) === false) {
        $message = "<div class='alert alert-danger'>صيغة التاريخ غير صحيحة. استخدم YYYY-MM-DD.</div>";
    } elseif ($start_date_filter > $end_date_filter) {
        $message = "<div class='alert alert-danger'>تاريخ البدء لا يمكن أن يكون بعد تاريخ الانتهاء.</div>";
    } else {
        $report_generated = true;
        $start_sql = $start_date_filter . " 00:00:00";
        $end_sql   = $end_date_filter . " 23:59:59";
        $start_sql_date = $start_date_filter;
        $end_sql_date = $end_date_filter;

        // 1) إجماليات: إيرادات و تكلفة وخصومات
        $totals_sql = "
            SELECT
              COALESCE(SUM(io.total_before_discount),0) AS total_revenue_before_discount,
              COALESCE(SUM(io.total_after_discount),0) AS total_revenue_after_discount,
              COALESCE(SUM(io.discount_amount),0) AS total_invoice_discount,
              COALESCE(SUM(io.total_cost),0) AS total_cost,
              COALESCE(SUM(io.profit_amount),0) AS total_profit
            FROM invoices_out io
            WHERE io.delivered NOT IN ('cancelled')
              AND io.created_at BETWEEN ? AND ?
        ";
        
        // إضافة فلتر الحالة إلى إجماليات الربح
        if ($status_filter !== 'all') {
            $status_condition = "";
            switch ($status_filter) {
                case 'paid':
                    $status_condition = " AND io.remaining_amount = 0";
                    break;
                case 'partial':
                    $status_condition = " AND io.paid_amount > 0 AND io.remaining_amount > 0";
                    break;
                case 'pending':
                    $status_condition = " AND io.paid_amount = 0 AND io.remaining_amount > 0";
                    break;
                case 'returned':
                    $status_condition = " AND io.delivered = 'reverted'";
                    break;
                case 'delivered':
                    $status_condition = " AND io.delivered = 'yes'";
                    break;
            }
            $totals_sql = str_replace("WHERE io.delivered NOT IN ('cancelled')", 
                "WHERE io.delivered NOT IN ('cancelled')" . $status_condition, $totals_sql);
        }
        
        // إضافة فلتر الخصم
        if ($discount_filter !== 'all') {
            $discount_condition = "";
            switch ($discount_filter) {
                case 'has_discount':
                    $discount_condition = " AND (io.discount_amount > 0 OR io.discount_value > 0)";
                    break;
                case 'no_discount':
                    $discount_condition = " AND io.discount_amount = 0 AND io.discount_value = 0";
                    break;
            }
            $totals_sql .= $discount_condition;
        }
        
        if ($st = $conn->prepare($totals_sql)) {
            $st->bind_param('ss', $start_sql, $end_sql);
            if ($st->execute()) {
                $r = $st->get_result()->fetch_assoc();
                $grand_total_revenue_before_discount = floatval($r['total_revenue_before_discount'] ?? 0);
                $grand_total_revenue_after_discount = floatval($r['total_revenue_after_discount'] ?? 0);
                $grand_total_discount = floatval($r['total_invoice_discount'] ?? 0);
                $grand_total_cost = floatval($r['total_cost'] ?? 0);
                $net_profit = floatval($r['total_profit'] ?? 0);
                $total_invoice_discount = floatval($r['total_invoice_discount'] ?? 0);
            } else {
                $message .= "<div class='alert alert-danger'>فشل حساب الإجماليات: " . e($st->error) . "</div>";
            }
            $st->close();
        } else {
            $message .= "<div class='alert alert-danger'>فشل تحضير استعلام الإجماليات: " . e($conn->error) . "</div>";
        }

        // 2) جلب إجمالي خصم البنود
        $items_discount_sql = "
            SELECT COALESCE(SUM(ioi.discount_amount), 0) AS total_items_discount
            FROM invoices_out io
            JOIN invoice_out_items ioi ON ioi.invoice_out_id = io.id AND ioi.return_flag = 0
            WHERE io.delivered NOT IN ('cancelled')
              AND io.created_at BETWEEN ? AND ?
        ";
        
        if ($status_filter !== 'all') {
            $status_condition = "";
            switch ($status_filter) {
                case 'paid':
                    $status_condition = " AND io.remaining_amount = 0";
                    break;
                case 'partial':
                    $status_condition = " AND io.paid_amount > 0 AND io.remaining_amount > 0";
                    break;
                case 'pending':
                    $status_condition = " AND io.paid_amount = 0 AND io.remaining_amount > 0";
                    break;
                case 'returned':
                    $status_condition = " AND io.delivered = 'reverted'";
                    break;
                case 'delivered':
                    $status_condition = " AND io.delivered = 'yes'";
                    break;
            }
            $items_discount_sql = str_replace("WHERE io.delivered NOT IN ('cancelled')", 
                "WHERE io.delivered NOT IN ('cancelled')" . $status_condition, $items_discount_sql);
        }
        
        if ($st2 = $conn->prepare($items_discount_sql)) {
            $st2->bind_param('ss', $start_sql, $end_sql);
            if ($st2->execute()) {
                $r2 = $st2->get_result()->fetch_assoc();
                $total_items_discount = floatval($r2['total_items_discount'] ?? 0);
            }
            $st2->close();
        }

        // 3) ملخّص الفواتير التفصيلي
        $sql = "
          SELECT
            io.id AS invoice_id,
            io.created_at AS invoice_created_at,
            COALESCE(c.name, '') AS customer_name,
            io.total_before_discount AS invoice_total_before_discount,
            io.discount_type AS invoice_discount_type,
            io.discount_value AS invoice_discount_value,
            io.discount_amount AS invoice_discount_amount,
            io.total_after_discount AS invoice_total_after_discount,
            io.total_cost AS invoice_total_cost,
            io.profit_amount AS invoice_profit_amount,
            io.delivered,
            io.paid_amount,
            io.remaining_amount,
            io.discount_scope,
            -- إجمالي خصم البنود في هذه الفاتورة
            (SELECT COALESCE(SUM(ioi2.discount_amount), 0) 
             FROM invoice_out_items ioi2 
             WHERE ioi2.invoice_out_id = io.id AND ioi2.return_flag = 0) AS items_discount_amount,
            -- هل هناك خصم على الفاتورة؟
            CASE 
                WHEN io.discount_amount > 0 OR io.discount_value > 0 THEN 'yes'
                ELSE 'no'
            END AS has_discount,
            CASE 
                WHEN io.delivered = 'reverted' THEN 'returned'
                WHEN io.remaining_amount = 0 THEN 'paid'
                WHEN io.paid_amount > 0 AND io.remaining_amount > 0 THEN 'partial'
                WHEN io.delivered = 'yes' THEN 'delivered'
                ELSE 'pending'
            END AS status
          FROM invoices_out io
          LEFT JOIN customers c ON c.id = io.customer_id
          WHERE io.delivered NOT IN ('cancelled')
            AND io.created_at BETWEEN ? AND ?
        ";
        
        // إضافة فلتر الحالة
        if ($status_filter !== 'all') {
            switch ($status_filter) {
                case 'paid':
                    $sql .= " AND io.remaining_amount = 0";
                    break;
                case 'partial':
                    $sql .= " AND io.paid_amount > 0 AND io.remaining_amount > 0";
                    break;
                case 'pending':
                    $sql .= " AND io.paid_amount = 0 AND io.remaining_amount > 0";
                    break;
                case 'returned':
                    $sql .= " AND io.delivered = 'reverted'";
                    break;
                case 'delivered':
                    $sql .= " AND io.delivered = 'yes'";
                    break;
            }
        }
        
        // إضافة فلتر الخصم
        if ($discount_filter !== 'all') {
            switch ($discount_filter) {
                case 'has_discount':
                    $sql .= " AND (io.discount_amount > 0 OR io.discount_value > 0)";
                    break;
                case 'no_discount':
                    $sql .= " AND io.discount_amount = 0 AND io.discount_value = 0";
                    break;
            }
        }
        
        $sql .= "
          ORDER BY io.created_at DESC, io.id DESC
        ";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param('ss', $start_sql, $end_sql);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    // تحويل القيم العددية
                    $row['invoice_total_before_discount'] = floatval($row['invoice_total_before_discount']);
                    $row['invoice_discount_amount'] = floatval($row['invoice_discount_amount']);
                    $row['invoice_total_after_discount'] = floatval($row['invoice_total_after_discount']);
                    $row['invoice_total_cost'] = floatval($row['invoice_total_cost']);
                    $row['invoice_profit_amount'] = floatval($row['invoice_profit_amount']);
                    $row['items_discount_amount'] = floatval($row['items_discount_amount']);
                    $row['paid_amount'] = floatval($row['paid_amount']);
                    $row['remaining_amount'] = floatval($row['remaining_amount']);
                    
                    // حساب إجمالي الخصم (فاتورة + بنود)
                    $row['total_discount'] = $row['invoice_discount_amount'] + $row['items_discount_amount'];
                    
                    $summaries[] = $row;
                }
            } else {
                $message .= "<div class='alert alert-danger'>خطأ في استعلام ملخّص الفواتير: " . e($stmt->error) . "</div>";
            }
            $stmt->close();
        }

        // 4) جلب تفاصيل البنود لفواتير محددة (عند الضغط على عرض التفاصيل)
        $invoice_details = [];
        if (isset($_GET['view_details']) && is_numeric($_GET['view_details'])) {
            $invoice_id = intval($_GET['view_details']);
            $details_sql = "
                SELECT 
                    ioi.*,
                    p.name AS product_name,
                    p.barcode,
                    ioi.quantity,
                    ioi.returned_quantity,
                    ioi.available_for_return,
                    ioi.selling_price,
                    ioi.cost_price_per_unit,
                    ioi.discount_type,
                    ioi.discount_value,
                    ioi.discount_amount,
                    ioi.total_before_discount,
                    ioi.total_after_discount,
                    ioi.return_flag,
                    -- حساب الربح للبند
                    (ioi.total_after_discount - (ioi.quantity * ioi.cost_price_per_unit)) AS item_profit,
                    -- حساب سعر الوحدة قبل وبعد الخصم
                    CASE 
                        WHEN ioi.quantity > 0 THEN ioi.total_before_discount / ioi.quantity
                        ELSE 0
                    END AS unit_price_before_discount,
                    CASE 
                        WHEN ioi.quantity > 0 THEN ioi.total_after_discount / ioi.quantity
                        ELSE 0
                    END AS unit_price_after_discount
                FROM invoice_out_items ioi
                LEFT JOIN products p ON p.id = ioi.product_id
                WHERE ioi.invoice_out_id = ?
                AND ioi.return_flag = 0
                ORDER BY ioi.id ASC
            ";
            
            if ($details_stmt = $conn->prepare($details_sql)) {
                $details_stmt->bind_param('i', $invoice_id);
                if ($details_stmt->execute()) {
                    $details_res = $details_stmt->get_result();
                    while ($detail_row = $details_res->fetch_assoc()) {
                        $detail_row['quantity'] = floatval($detail_row['quantity']);
                        $detail_row['returned_quantity'] = floatval($detail_row['returned_quantity']);
                        $detail_row['available_for_return'] = floatval($detail_row['available_for_return']);
                        $detail_row['selling_price'] = floatval($detail_row['selling_price']);
                        $detail_row['cost_price_per_unit'] = floatval($detail_row['cost_price_per_unit']);
                        $detail_row['discount_amount'] = floatval($detail_row['discount_amount']);
                        $detail_row['total_before_discount'] = floatval($detail_row['total_before_discount']);
                        $detail_row['total_after_discount'] = floatval($detail_row['total_after_discount']);
                        $detail_row['item_profit'] = floatval($detail_row['item_profit']);
                        $detail_row['unit_price_before_discount'] = floatval($detail_row['unit_price_before_discount']);
                        $detail_row['unit_price_after_discount'] = floatval($detail_row['unit_price_after_discount']);
                        $invoice_details[] = $detail_row;
                    }
                }
                $details_stmt->close();
            }
        }

        // 5) جلب المصروفات خلال الفترة
        $sql_expenses = "SELECT id, expense_date, description, amount, category_id, notes FROM expenses WHERE expense_date BETWEEN ? AND ? ORDER BY expense_date ASC, id ASC";
        if ($sx = $conn->prepare($sql_expenses)) {
            $sx->bind_param('ss', $start_sql_date, $end_sql_date);
            if ($sx->execute()) {
                $rx = $sx->get_result();
                while ($ex = $rx->fetch_assoc()) {
                    $ex['amount'] = floatval($ex['amount']);
                    $expenses_list[] = $ex;
                }
            } else {
                $message .= "<div class='alert alert-warning'>خطأ في جلب المصروفات: " . e($sx->error) . "</div>";
            }
            $sx->close();
        }

        // 6) مجموع المصروفات
        $sql_exp_sum = "SELECT COALESCE(SUM(amount),0) AS total_expenses FROM expenses WHERE expense_date BETWEEN ? AND ?";
        if ($sx2 = $conn->prepare($sql_exp_sum)) {
            $sx2->bind_param('ss', $start_sql_date, $end_sql_date);
            if ($sx2->execute()) {
                $rr = $sx2->get_result()->fetch_assoc();
                $total_expenses = floatval($rr['total_expenses'] ?? 0);
            }
            $sx2->close();
        }

        // 7) إجمالي الخصومات (فاتورة + بنود)
        $grand_total_discount = $total_invoice_discount + $total_items_discount;
        
        // 8) صافي الربح النهائي (مع خصم المصروفات)
        $net_profit = $grand_total_revenue_after_discount - $grand_total_cost - $total_expenses;
    }
}

require_once BASE_DIR . 'partials/header.php';
require_once BASE_DIR . 'partials/sidebar.php';
?>

<style>
    /* container and header */
    .page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 12px;
    }

    .page-header h3 {
        margin: 0;
        font-size: 1.25rem;
        color: var(--text);
    }

    .small-muted {
        color: var(--text-soft);
    }

    /* filters */
    .card.filter-card {
        background: var(--surface);
        border-radius: 12px;
        padding: 12px;
        margin-bottom: 16px;
        box-shadow: var(--shadow-1);
    }

    .form-row {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        align-items: flex-end;
    }

    /* quick periods buttons */
    .periods {
        display: flex;
        gap: 8px;
        align-items: center;
        margin-right: 8px;
    }

    .periods button {
        background: transparent;
        border: 1px solid var(--border);
        padding: 8px 10px;
        border-radius: 8px;
        cursor: pointer;
        color: var(--text);
    }

    .periods button.active {
        background: var(--primary);
        color: #fff;
        box-shadow: var(--shadow-2);
        transform: translateY(-2px);
    }

    /* summary cards */
    .summary-cards {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 16px;
        margin-bottom: 20px;
    }

    @media (max-width: 1200px) {
        .summary-cards {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    @media (max-width: 768px) {
        .summary-cards {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 480px) {
        .summary-cards {
            grid-template-columns: 1fr;
        }
    }

    .summary-card {
        background: var(--surface);
        border-radius: var(--radius);
        padding: 16px 18px;
        box-shadow: var(--shadow-1);
        position: relative;
        overflow: hidden;
        transition: transform .18s ease, box-shadow .18s ease;
    }

    .summary-card:hover {
        transform: translateY(-6px);
        box-shadow: var(--shadow-2);
    }

    .summary-card .title {
        color: var(--muted);
        font-size: 0.95rem;
        margin-bottom: 8px;
    }

    .summary-card .value {
        font-size: 1.75rem;
        font-weight: 800;
        color: var(--text);
    }

    .summary-card .sub {
        color: var(--text-soft);
        margin-top: 8px;
        font-size: 0.9rem;
    }

    .summary-card::before {
        content: '';
        position: absolute;
        right: -30px;
        top: -30px;
        width: 160px;
        height: 160px;
        opacity: 0.12;
        transform: rotate(20deg);
    }

    .card-revenue::before {
        background: var(--grad-1) !important;
    }

    .card-cost::before {
        background: var(--grad-3);
    }

    .card-profit::before {
        background: var(--grad-2);
    }

    .card-expenses::before {
        background: var(--grad-4);
    }

    .card-discount::before {
        background: linear-gradient(135deg, #ff6b6b, #ff8e8e) !important;
    }

    .card-discount-items::before {
        background: linear-gradient(135deg, #ff9f43, #ffbe76) !important;
    }

    .currency-badge {
        display: inline-block;
        margin-left: 8px;
        font-weight: 700;
        color: var(--muted);
        font-size: 0.85rem;
    }

    .badge-profit {
        font-weight: 700;
        padding: 6px 8px;
        border-radius: 6px;
        display: inline-block;
    }

    .badge-profit.positive {
        background: rgba(16, 185, 129, 0.08);
        color: #075928;
        border: 1px solid rgba(16, 185, 129, 0.12);
    }

    .badge-profit.negative {
        background: rgba(239, 68, 68, 0.06);
        color: #7f1d1d;
        border: 1px solid rgba(239, 68, 68, 0.12);
    }
    
    /* Status badges */
    .status-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.85rem;
        font-weight: 600;
        text-align: center;
        min-width: 70px;
    }
    
    .status-paid {
        background: rgba(16, 185, 129, 0.1);
        color: #075928;
        border: 1px solid rgba(16, 185, 129, 0.2);
    }
    
    .status-partial {
        background: rgba(245, 158, 11, 0.1);
        color: #92400e;
        border: 1px solid rgba(245, 158, 11, 0.2);
    }
    
    .status-pending {
        background: rgba(59, 130, 246, 0.1);
        color: #1e40af;
        border: 1px solid rgba(59, 130, 246, 0.2);
    }
    
    .status-returned {
        background: rgba(239, 68, 68, 0.1);
        color: #7f1d1d;
        border: 1px solid rgba(239, 68, 68, 0.2);
    }
    
    .status-delivered {
        background: rgba(139, 92, 246, 0.1);
        color: #5b21b6;
        border: 1px solid rgba(139, 92, 246, 0.2);
    }
    
    /* Discount badge */
    .discount-badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
        background: rgba(255, 107, 107, 0.1);
        color: #dc2626;
        border: 1px solid rgba(255, 107, 107, 0.2);
        margin-left: 4px;
    }

    /* responsive */
    @media (max-width:900px) {
        .table {
            min-width: 700px;
        }
    }

    @media (max-width:640px) {
        .page-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 6px;
        }

        .table {
            min-width: 600px;
        }
    }

    .layout-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 24px;
        align-items: start;
    }

    @media (max-width:900px) {
        .layout-grid {
            grid-template-columns: 1fr;
            gap: 18px;
        }
    }
    
    .filter-group {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .filter-group select {
        min-width: 120px;
    }
    
    /* Details table styles */
    .details-row {
        background: #f8f9fa !important;
    }
    
    .details-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 8px;
        background: #f8f9fa;
        border-radius: 8px;
        overflow: hidden;
    }
    
    .details-table th {
        background: #e9ecef;
        padding: 10px;
        text-align: right;
        font-size: 0.85rem;
        color: #495057;
        border-bottom: 1px solid #dee2e6;
    }
    
    .details-table td {
        padding: 8px 10px;
        border-bottom: 1px solid #dee2e6;
        font-size: 0.85rem;
    }
    
    .unit-price-comparison {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    
    .price-before {
        color: #6c757d;
        text-decoration: line-through;
        font-size: 0.8rem;
    }
    
    .price-after {
        color: #198754;
        font-weight: 600;
        font-size: 0.9rem;
    }
    
    .view-details-btn {
        background: none;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        padding: 4px 8px;
        font-size: 0.8rem;
        color: #6c757d;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .view-details-btn:hover {
        background: #f8f9fa;
        color: #495057;
    }
    
    .highlight-discount {
        background: #fff3cd !important;
    }
</style>

<div class="container-fluid">
    <div class="page-header">
        <h3><i class="fas fa-balance-scale-right"></i> تقرير صافي الربح — تفصيلي</h3>
        <div>
            <div class="small-muted">استبعاد الفواتير الملغاة والبضاعة المرتجعة بالكامل</div>
            <div style="margin-top:6px;text-align:right">
                <a href="<?php echo htmlspecialchars(BASE_URL . 'user/welcome.php'); ?>" class="btn btn-sm btn-light">← العودة</a>
                <button id="printBtn" class="btn btn-sm btn-primary">طباعة</button>
            </div>
        </div>
    </div>

    <?php echo $message; ?>

    <div class="card filter-card">
        <div class="card-body p-2">
            <form id="filterForm" method="get" class="d-flex align-items-end" style="gap:12px;flex-wrap:wrap">
                <input type="hidden" name="reset_period" id="resetPeriodInput" value="0">
                <?php if (isset($_GET['view_details'])): ?>
                    <input type="hidden" name="view_details" value="<?php echo e($_GET['view_details']); ?>">
                <?php endif; ?>
                
                <div class="periods" role="tablist" aria-label="فترات سريعة">
                    <button type="button" data-period="day" id="btnDay">يوم</button>
                    <button type="button" data-period="week" id="btnWeek">أسبوع</button>
                    <button type="button" data-period="month" id="btnMonth">شهر</button>
                    <button type="button" data-period="custom" id="btnCustom">مخصص</button>
                </div>
                <input type="hidden" name="period" id="periodInput" value="<?php echo e($_GET['period'] ?? 'day'); ?>">

                <div>
                    <label class="form-label">من تاريخ</label>
                    <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo e($start_date_filter); ?>" required>
                </div>
                <div>
                    <label class="form-label">إلى تاريخ</label>
                    <input type="date" name="end_date" id="end_date" class="form-control" value="<?php echo e($end_date_filter); ?>" required>
                </div>
                
                <div>
                    <label class="form-label">حالة الفاتورة</label>
                    <select name="status" id="status" class="form-control">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>جميع الحالات</option>
                        <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>مدفوع</option>
                        <option value="partial" <?php echo $status_filter === 'partial' ? 'selected' : ''; ?>>جزئي</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>قيد الانتظار</option>
                        <option value="returned" <?php echo $status_filter === 'returned' ? 'selected' : ''; ?>>مرتجع</option>
                        <option value="delivered" <?php echo $status_filter === 'delivered' ? 'selected' : ''; ?>>مسلمة</option>
                    </select>
                </div>
                
                <div>
                    <label class="form-label">حالة الخصم</label>
                    <select name="discount" id="discount" class="form-control">
                        <option value="all" <?php echo $discount_filter === 'all' ? 'selected' : ''; ?>>جميع الفواتير</option>
                        <option value="has_discount" <?php echo $discount_filter === 'has_discount' ? 'selected' : ''; ?>>بها خصم</option>
                        <option value="no_discount" <?php echo $discount_filter === 'no_discount' ? 'selected' : ''; ?>>بدون خصم</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <button type="submit" class="btn btn-primary">عرض</button>
                    <button type="button" id="todayQuick" class="btn btn-outline-secondary">اليوم</button>
                    <button type="button" id="resetPeriodBtn" class="btn btn-outline-warning">من أول المدة</button>
                </div>
                
                <div style="margin-left:auto;color:var(--muted);font-size:0.9rem;">
                    عرض <?php echo count($summaries); ?> فاتورة
                    <?php if (isset($_GET['view_details'])): ?>
                        | <a href="?" class="text-primary">عرض الكل</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <?php if ($report_generated): ?>
        <div class="summary-cards">
            <div class="summary-card card-revenue">
                <div class="title">إجمالي المبيعات قبل الخصم</div>
                <div class="value"><?php echo number_format($grand_total_revenue_before_discount, 2); ?> <span class="currency-badge">ج.م</span></div>
                <div class="sub"><?php echo count($summaries); ?> فاتورة</div>
            </div>
            
            <div class="summary-card card-discount">
                <div class="title">خصم الفواتير</div>
                <div class="value" style="color: #dc2626;"><?php echo number_format($total_invoice_discount, 2); ?> <span class="currency-badge">ج.م</span></div>
                <div class="sub">خصم عام على الفواتير</div>
            </div>
            
            <div class="summary-card card-discount-items">
                <div class="title">خصم البنود</div>
                <div class="value" style="color: #ea580c;"><?php echo number_format($total_items_discount, 2); ?> <span class="currency-badge">ج.م</span></div>
                <div class="sub">خصم على مستوى البضاعة</div>
            </div>
            
            <div class="summary-card card-revenue">
                <div class="title">صافي المبيعات بعد الخصم</div>
                <div class="value" style="color: #059669;"><?php echo number_format($grand_total_revenue_after_discount, 2); ?> <span class="currency-badge">ج.م</span></div>
                <div class="sub">إجمالي الخصم: <?php echo number_format($grand_total_discount, 2); ?> ج.م</div>
            </div>
            
            <div class="summary-card card-cost">
                <div class="title">تكلفة البضاعة المباعة</div>
                <div class="value"><?php echo number_format($grand_total_cost, 2); ?> <span class="currency-badge">ج.م</span></div>
                <div class="sub"><?php echo $status_filter === 'all' ? 'جميع الحالات' : 'فلتر: ' . $status_filter; ?></div>
            </div>
            
            <div class="summary-card card-expenses">
                <div class="title">المصروفات خلال الفترة</div>
                <div class="value"><?php echo number_format($total_expenses, 2); ?> <span class="currency-badge">ج.م</span></div>
                <div class="sub"><?php echo count($expenses_list); ?> مصروف</div>
            </div>
        </div>

        <div class="summary-cards" style="grid-template-columns: repeat(2, 1fr); margin-top: 0;">
            <div class="summary-card card-profit">
                <div class="title">إجمالي الربح من الفواتير</div>
                <div class="value" style="color: #059669;"><?php echo number_format($grand_total_revenue_after_discount - $grand_total_cost, 2); ?> <span class="currency-badge">ج.م</span></div>
                <div class="sub">(صافي المبيعات - التكلفة)</div>
            </div>
            
            <div class="summary-card card-profit">
                <div class="title">صافي الربح النهائي</div>
                <?php
                $final_profit = $grand_total_revenue_after_discount - $grand_total_cost - $total_expenses;
                $profit_color = $final_profit >= 0 ? '#059669' : '#dc2626';
                ?>
                <div class="value" style="color: <?php echo $profit_color; ?>;">
                    <?php echo number_format($final_profit, 2); ?> <span class="currency-badge">ج.م</span>
                </div>
                <div class="sub">(بعد خصم المصروفات)</div>
            </div>
        </div>

        <div class="layout-grid">
            <div>
                <div class="custom-table-wrapper">
                    <table class="custom-table" id="reportTable">
                        <thead>
                            <tr>
                                <th style="width:70px"># فاتورة</th>
                                <th style="width:150px">التاريخ</th>
                                <th>العميل</th>
                                <th style="width:100px">الحالة</th>
                                <th style="width:120px" class="text-end">المبلغ قبل الخصم</th>
                                <th style="width:100px" class="text-end">الخصم</th>
                                <th style="width:120px" class="text-end">المبلغ بعد الخصم</th>
                                <th style="width:100px" class="text-end">التكلفة</th>
                                <th style="width:100px" class="text-end">الربح</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $counter = 0;
                            foreach ($summaries as $s):
                                $profit = $s['invoice_total_after_discount'] - $s['invoice_total_cost'];
                                $profit_class = $profit >= 0 ? 'positive' : 'negative';
                                $status_class = 'status-' . $s['status'];
                                $has_discount = $s['invoice_discount_amount'] > 0 || $s['items_discount_amount'] > 0;
                                $counter++;
                            ?>
                                <tr <?php echo $has_discount ? 'class="highlight-discount"' : ''; ?>>
                                    <td>
                                        <strong>#<?php echo intval($s['invoice_id']); ?></strong>
                                        <?php if ($has_discount): ?>
                                            <span class="discount-badge">خصم</span>
                                        <?php endif; ?>
                                        <br>
                                        <small>
                                            <?php if (isset($_GET['view_details']) && $_GET['view_details'] == $s['invoice_id']): ?>
                                                <a href="?" class="text-danger">إخفاء التفاصيل</a>
                                            <?php else: ?>
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['view_details' => $s['invoice_id']])); ?>" class="text-primary">عرض التفاصيل</a>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($s['invoice_created_at'])); ?></td>
                                    <td><?php echo e($s['customer_name'] ?: 'عميل غير محدد'); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php 
                                            switch($s['status']) {
                                                case 'paid': echo 'مدفوع'; break;
                                                case 'partial': echo 'جزئي'; break;
                                                case 'pending': echo 'قيد الانتظار'; break;
                                                case 'returned': echo 'مرتجع'; break;
                                                case 'delivered': echo 'مسلمة'; break;
                                                default: echo e($s['status']);
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td class="text-end"><?php echo number_format($s['invoice_total_before_discount'], 2); ?> ج.م</td>
                                    <td class="text-end">
                                        <?php if ($s['total_discount'] > 0): ?>
                                            <span style="color: #dc2626;">
                                                <?php echo number_format($s['total_discount'], 2); ?> ج.م
                                            </span>
                                            <br>
                                            <small class="text-muted">
                                                فاتورة: <?php echo number_format($s['invoice_discount_amount'], 2); ?><br>
                                                بنود: <?php echo number_format($s['items_discount_amount'], 2); ?>
                                            </small>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end"><?php echo number_format($s['invoice_total_after_discount'], 2); ?> ج.م</td>
                                    <td class="text-end"><?php echo number_format($s['invoice_total_cost'], 2); ?> ج.م</td>
                                    <td class="text-end">
                                        <span class="badge-profit <?php echo $profit_class; ?>">
                                            <?php echo number_format($profit, 2); ?> ج.م
                                        </span>
                                    </td>
                                </tr>
                                
                                <?php if (isset($_GET['view_details']) && $_GET['view_details'] == $s['invoice_id'] && !empty($invoice_details)): ?>
                                    <tr class="details-row">
                                        <td colspan="9">
                                            <div style="padding: 15px; background: #f8f9fa; border-radius: 8px;">
                                                <h6 style="margin-bottom: 15px; color: #495057;">
                                                    <i class="fas fa-list"></i> تفاصيل بنود الفاتورة #<?php echo $s['invoice_id']; ?>
                                                    <small class="text-muted">(<?php echo count($invoice_details); ?> بند)</small>
                                                </h6>
                                                
                                                <table class="details-table">
                                                    <thead>
                                                        <tr>
                                                            <th>المنتج</th>
                                                            <th>الكمية</th>
                                                            <th>المتاح للإرجاع</th>
                                                            <th>سعر البيع</th>
                                                            <th>سعر التكلفة</th>
                                                            <th>الخصم</th>
                                                            <th>الإجمالي قبل الخصم</th>
                                                            <th>الإجمالي بعد الخصم</th>
                                                            <th>الربح</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php 
                                                        $items_total_before = 0;
                                                        $items_total_after = 0;
                                                        $items_total_discount = 0;
                                                        $items_total_cost = 0;
                                                        $items_total_profit = 0;
                                                        ?>
                                                        <?php foreach ($invoice_details as $item): ?>
                                                            <?php 
                                                            $items_total_before += $item['total_before_discount'];
                                                            $items_total_after += $item['total_after_discount'];
                                                            $items_total_discount += $item['discount_amount'];
                                                            $items_total_cost += ($item['quantity'] * $item['cost_price_per_unit']);
                                                            $items_total_profit += $item['item_profit'];
                                                            ?>
                                                            <tr>
                                                                <td>
                                                                    <div><?php echo e($item['product_name'] ?: 'غير محدد'); ?></div>
                                                                    <?php if ($item['barcode']): ?>
                                                                        <small class="text-muted"><?php echo e($item['barcode']); ?></small>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td class="text-center">
                                                                    <?php echo number_format($item['quantity'], 2); ?>
                                                                    <?php if ($item['returned_quantity'] > 0): ?>
                                                                        <br>
                                                                        <small class="text-danger">
                                                                            مرتجع: <?php echo number_format($item['returned_quantity'], 2); ?>
                                                                        </small>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td class="text-center">
                                                                    <?php echo number_format($item['available_for_return'], 2); ?>
                                                                </td>
                                                                <td class="text-end">
                                                                    <div class="unit-price-comparison">
                                                                        <?php if ($item['discount_amount'] > 0): ?>
                                                                            <span class="price-before">
                                                                                <?php echo number_format($item['unit_price_before_discount'], 2); ?> ج.م
                                                                            </span>
                                                                        <?php endif; ?>
                                                                        <span class="price-after">
                                                                            <?php echo number_format($item['unit_price_after_discount'], 2); ?> ج.م
                                                                        </span>
                                                                    </div>
                                                                </td>
                                                                <td class="text-end"><?php echo number_format($item['cost_price_per_unit'], 2); ?> ج.م</td>
                                                                <td class="text-end">
                                                                    <?php if ($item['discount_amount'] > 0): ?>
                                                                        <span style="color: #dc2626;">
                                                                            <?php echo number_format($item['discount_amount'], 2); ?> ج.م
                                                                        </span>
                                                                        <?php if ($item['discount_type'] && $item['discount_value'] > 0): ?>
                                                                            <br>
                                                                            <small class="text-muted">
                                                                                <?php 
                                                                                if ($item['discount_type'] == 'percent') {
                                                                                    echo number_format($item['discount_value'], 2) . '%';
                                                                                } else {
                                                                                    echo number_format($item['discount_value'], 2) . ' ج.م';
                                                                                }
                                                                                ?>
                                                                            </small>
                                                                        <?php endif; ?>
                                                                    <?php else: ?>
                                                                        -
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td class="text-end"><?php echo number_format($item['total_before_discount'], 2); ?> ج.م</td>
                                                                <td class="text-end"><?php echo number_format($item['total_after_discount'], 2); ?> ج.م</td>
                                                                <td class="text-end">
                                                                    <span class="<?php echo $item['item_profit'] >= 0 ? 'badge-profit positive' : 'badge-profit negative'; ?>" style="font-size: 0.85rem;">
                                                                        <?php echo number_format($item['item_profit'], 2); ?> ج.م
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                    <tfoot>
                                                        <tr>
                                                            <td colspan="6" class="text-end"><strong>إجمالي البنود:</strong></td>
                                                            <td class="text-end"><strong><?php echo number_format($items_total_before, 2); ?> ج.م</strong></td>
                                                            <td class="text-end"><strong><?php echo number_format($items_total_after, 2); ?> ج.م</strong></td>
                                                            <td class="text-end">
                                                                <strong>
                                                                    <span class="<?php echo $items_total_profit >= 0 ? 'badge-profit positive' : 'badge-profit negative'; ?>">
                                                                        <?php echo number_format($items_total_profit, 2); ?> ج.م
                                                                    </span>
                                                                </strong>
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td colspan="9" class="text-muted" style="font-size: 0.8rem;">
                                                                <strong>ملاحظة:</strong> البنود المرتجعة بالكامل (return_flag=1) غير معروضة.
                                                                إجمالي خصم البنود: <?php echo number_format($items_total_discount, 2); ?> ج.م
                                                            </td>
                                                        </tr>
                                                    </tfoot>
                                                </table>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <?php
                            $grand_total_before = 0;
                            $grand_total_after = 0;
                            $grand_total_discount_calc = 0;
                            $grand_total_cost_calc = 0;
                            $grand_total_profit_calc = 0;
                            
                            foreach ($summaries as $ss) {
                                $grand_total_before += $ss['invoice_total_before_discount'];
                                $grand_total_after += $ss['invoice_total_after_discount'];
                                $grand_total_discount_calc += $ss['total_discount'];
                                $grand_total_cost_calc += $ss['invoice_total_cost'];
                                $grand_total_profit_calc += ($ss['invoice_total_after_discount'] - $ss['invoice_total_cost']);
                            }
                            ?>
                            <tr>
                                <td colspan="4" class="text-end"><strong>إجمالي <?php echo count($summaries); ?> فاتورة:</strong></td>
                                <td class="text-end"><strong><?php echo number_format($grand_total_before, 2); ?> ج.م</strong></td>
                                <td class="text-end"><strong style="color: #dc2626;"><?php echo number_format($grand_total_discount_calc, 2); ?> ج.م</strong></td>
                                <td class="text-end"><strong><?php echo number_format($grand_total_after, 2); ?> ج.م</strong></td>
                                <td class="text-end"><strong><?php echo number_format($grand_total_cost_calc, 2); ?> ج.م</strong></td>
                                <td class="text-end">
                                    <strong>
                                        <span class="<?php echo $grand_total_profit_calc >= 0 ? 'badge-profit positive' : 'badge-profit negative'; ?>">
                                            <?php echo number_format($grand_total_profit_calc, 2); ?> ج.م
                                        </span>
                                    </strong>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div>
                <div class="custom-table-wrapper" style="padding:12px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                        <div><strong>المصروفات خلال الفترة</strong>
                            <div class="small-muted">من <?php echo e($start_date_filter); ?> إلى <?php echo e($end_date_filter); ?></div>
                        </div>
                        <div style="font-weight:700"><?php echo number_format($total_expenses, 2); ?> ج.م</div>
                    </div>

                    <?php if (empty($expenses_list)): ?>
                        <div class="alert alert-info">لا توجد مصروفات مسجّلة خلال الفترة.</div>
                    <?php else: ?>
                        <div style="max-height:380px;overflow:auto;">
                            <table class="custom-table" id="expensesTable">
                                <thead>
                                    <tr>
                                        <th style="width:120px">التاريخ</th>
                                        <th>الوصف</th>
                                        <th style="width:110px" class="text-end">المبلغ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($expenses_list as $ex): ?>
                                        <tr>
                                            <td><?php echo e($ex['expense_date']); ?></td>
                                            <td><?php echo e($ex['description']); ?></td>
                                            <td class="text-end"><?php echo number_format($ex['amount'], 2); ?> ج.م</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="2" style="text-align:right"><strong>الإجمالي:</strong></td>
                                        <td class="text-end"><strong><?php echo number_format($total_expenses, 2); ?> ج.م</strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php endif; ?>

                </div>

                <div class="summary-card" style="margin-top:12px;padding:12px;">
                    <div class="small-muted"><strong>ملاحظات:</strong></div>
                    <ul class="small-muted" style="margin:6px 0 0 0;padding-left:18px;">
                        <li>الفواتير الملغاة (cancelled) مستبعدة من التقرير.</li>
                        <li>البضاعة المرتجعة بالكامل (return_flag=1) مستبعدة من الحساب.</li>
                        <li>عرض الكمية المتاحة للإرجاع (available_for_return).</li>
                        <li>الخصم يشمل: خصم الفاتورة العام + خصم البنود التفصيلي.</li>
                        <li>الربح = المبلغ بعد الخصم - التكلفة.</li>
                    </ul>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        function qs(s) {
            return document.querySelector(s);
        }

        function qsa(s) {
            return Array.from(document.querySelectorAll(s));
        }

        function escapeHtml(s) {
            if (!s && s !== 0) return '';
            return String(s).replace(/[&<>"']/g, function(m) {
                return ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                })[m];
            });
        }

        const btnDay = qs('#btnDay'),
            btnWeek = qs('#btnWeek'),
            btnMonth = qs('#btnMonth'),
            btnCustom = qs('#btnCustom');
        const startIn = qs('#start_date'),
            endIn = qs('#end_date'),
            periodInput = qs('#periodInput'),
            filterForm = qs('#filterForm'),
            resetPeriodBtn = qs('#resetPeriodBtn'),
            resetPeriodInput = qs('#resetPeriodInput'),
            statusFilter = qs('#status'),
            discountFilter = qs('#discount');
        const todayQuick = qs('#todayQuick');

        function formatDate(d) {
            return d.toISOString().slice(0, 10);
        }
        const now = new Date();
        if (!startIn.value) startIn.value = formatDate(now);
        if (!endIn.value) endIn.value = formatDate(now);

        function setPeriod(p, autoSubmit = false) {
            [btnDay, btnWeek, btnMonth, btnCustom].forEach(b => b.classList.remove('active'));
            const today = new Date();
            let s = new Date(),
                e = new Date();
            if (p === 'day') {
                s = new Date(today);
                e = new Date(today);
                btnDay.classList.add('active');
            } else if (p === 'week') {
                const day = today.getDay() || 7;
                s = new Date(today);
                s.setDate(today.getDate() - (day - 1));
                e = new Date(s);
                e.setDate(s.getDate() + 6);
                btnWeek.classList.add('active');
            } else if (p === 'month') {
                s = new Date(today.getFullYear(), today.getMonth(), 1);
                e = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                btnMonth.classList.add('active');
            } else {
                btnCustom.classList.add('active');
                periodInput.value = 'custom';
                return;
            }
            startIn.value = formatDate(s);
            endIn.value = formatDate(e);
            periodInput.value = p;
            if (autoSubmit) setTimeout(() => filterForm.submit(), 120);
        }
        const initialPeriod = '<?php echo e($_GET['period'] ?? 'day'); ?>';
        setPeriod(initialPeriod || 'day', false);
        btnDay?.addEventListener('click', () => setPeriod('day', true));
        btnWeek?.addEventListener('click', () => setPeriod('week', true));
        btnMonth?.addEventListener('click', () => setPeriod('month', true));
        btnCustom?.addEventListener('click', () => setPeriod('custom', false));
        todayQuick?.addEventListener('click', () => setPeriod('day', true));
        
        // زر "من أول المدة" - يضبط التاريخ إلى 2022-01-01
        resetPeriodBtn?.addEventListener('click', function() {
            startIn.value = '2022-01-01';
            endIn.value = formatDate(new Date());
            resetPeriodInput.value = '1';
            filterForm.submit();
        });

        // Print: include both tables
        qs('#printBtn')?.addEventListener('click', function() {
            const startVal = startIn.value || '';
            const endVal = endIn.value || '';
            const statusVal = statusFilter?.value || 'all';
            const discountVal = discountFilter?.value || 'all';
            let html = '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><title>طباعة تقرير صافي الربح التفصيلي</title>';
            html += '<style>body{font-family:Arial;padding:18px;color:#111} h2{margin:0 0 8px} table{width:100%;border-collapse:collapse;margin-top:8px} th,td{border:1px solid #ddd;padding:8px;text-align:right} thead th{background:#f6f8fb} tfoot td{font-weight:700} .discount{color:#dc2626} .profit-positive{color:#075928} .profit-negative{color:#dc2626}</style>';
            html += '</head><body>';
            html += '<h2>تقرير صافي الربح التفصيلي</h2>';
            html += '<div>الفترة: <strong>' + escapeHtml(startVal) + ' → ' + escapeHtml(endVal) + '</strong></div>';
            html += '<div>حالة الفواتير: <strong>' + escapeHtml(statusVal === 'all' ? 'جميع الحالات' : statusVal) + '</strong></div>';
            html += '<div>فلتر الخصم: <strong>' + escapeHtml(discountVal === 'all' ? 'جميع الفواتير' : (discountVal === 'has_discount' ? 'بها خصم' : 'بدون خصم')) + '</strong></div>';

            // invoices
            html += '<h3 style="margin-top:12px">ملخص الفواتير (' + <?php echo count($summaries); ?> + ' فاتورة)</h3>';
            const invRows = Array.from(document.querySelectorAll('#reportTable tbody tr'));
            if (invRows.length === 0) html += '<div>لا توجد فواتير لعرضها.</div>';
            else {
                html += '<table><thead><tr><th>#</th><th>رقم الفاتورة</th><th>التاريخ</th><th>العميل</th><th>الحالة</th><th>المبلغ قبل الخصم</th><th>الخصم</th><th>المبلغ بعد الخصم</th><th>الربح</th></tr></thead><tbody>';
                invRows.forEach((tr, i) => {
                    // تخطي صف التفاصيل
                    if (tr.classList.contains('details-row')) return;
                    
                    const tds = tr.querySelectorAll('td');
                    const inv = tds[0]?.innerText.split('\n')[0] || '';
                    const dt = tds[1]?.innerText || '';
                    const cust = tds[2]?.innerText || '';
                    const status = tds[3]?.innerText || '';
                    const totalBefore = tds[4]?.innerText || '0';
                    const discount = tds[5]?.innerText || '0';
                    const totalAfter = tds[6]?.innerText || '0';
                    const profit = tds[8]?.innerText || '0';
                    html += '<tr><td>' + (i + 1) + '</td><td>' + escapeHtml(inv) + '</td><td>' + escapeHtml(dt) + '</td><td>' + escapeHtml(cust) + '</td><td>' + escapeHtml(status) + '</td><td style="text-align:right">' + escapeHtml(totalBefore) + '</td><td style="text-align:right" class="discount">' + escapeHtml(discount) + '</td><td style="text-align:right">' + escapeHtml(totalAfter) + '</td><td style="text-align:right">' + escapeHtml(profit) + '</td></tr>';
                });
                html += '</tbody></table>';
            }

            // expenses
            html += '<h3 style="margin-top:12px">المصروفات</h3>';
            const expRows = Array.from(document.querySelectorAll('#expensesTable tbody tr'));
            if (expRows.length === 0) html += '<div>لا توجد مصروفات لعرضها.</div>';
            else {
                html += '<table><thead><tr><th>التاريخ</th><th>الوصف</th><th>المبلغ</th></tr></thead><tbody>';
                expRows.forEach(tr => {
                    const tds = tr.querySelectorAll('td');
                    const dt = tds[0]?.innerText || '';
                    const desc = tds[1]?.innerText || '';
                    const amt = tds[2]?.innerText || '0';
                    html += '<tr><td>' + escapeHtml(dt) + '</td><td>' + escapeHtml(desc) + '</td><td style="text-align:right">' + escapeHtml(amt) + '</td></tr>';
                });
                html += '</tbody></table>';
            }

            html += '<div style="margin-top:18px;color:#666;font-size:13px">طُبع في: ' + new Date().toLocaleString('ar-EG') + '</div>';
            html += '</body></html>';

            const w = window.open('', '_blank');
            if (!w) {
                alert('يرجى السماح بفتح النوافذ المنبثقة للطباعة');
                return;
            }
            w.document.open();
            w.document.write(html);
            w.document.close();
            w.focus();
            setTimeout(() => w.print(), 300);
        });
    });
</script>

<?php
require_once BASE_DIR . 'partials/footer.php';
$conn->close();
?>