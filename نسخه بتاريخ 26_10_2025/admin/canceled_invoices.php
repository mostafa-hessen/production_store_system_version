<?php
// canceled_invoices.php
$page_title = "الفواتير الملغاة";

require_once dirname(__DIR__) . '/config.php';
require_once BASE_DIR . 'partials/session_admin.php';

// CSRF token
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];

/*
 * Handler: fetch_canceled_list
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'fetch_canceled_list') {
    header('Content-Type: application/json; charset=utf-8');
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'csrf']);
        exit;
    }
    $cid = trim($_POST['customer_id'] ?? '');
    $iid = trim($_POST['invoice_id'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
    $date_to   = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
    // إجمالي الفواتير غير المستلمة (بدون تطبيق البحث) لتلخيص 

    $sql = "SELECT io.id AS invoice_id, COALESCE(c.name,'عميل نقدي') AS customer_name, COALESCE(c.mobile,'') AS mobile,
                   io.created_by, u.username AS created_by_name,
                   ic.cancelled_at, ic.id AS cancellation_id,
                   (SELECT COALESCE(SUM(total_price),0) FROM invoice_out_items ioi WHERE ioi.invoice_out_id = io.id) AS total
            FROM invoices_out io
            LEFT JOIN invoice_cancellations ic ON ic.invoice_out_id = io.id
            LEFT JOIN customers c ON io.customer_id = c.id
            LEFT JOIN users u ON io.created_by = u.id
            WHERE io.delivered = 'canceled'";

    $params = [];
    if ($cid !== '') {
        $sql .= " AND io.customer_id = ?";
        $params[] = $cid;
    }
    if ($iid !== '') {
        $sql .= " AND io.id = ?";
        $params[] = $iid;
    }
    if ($mobile !== '') {
        $sql .= " AND c.mobile LIKE ?";
        $params[] = "%$mobile%";
    }
    if ($date_from !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $date_from);
        if ($d !== false) {
            $start = $d->format('Y-m-d') . ' 00:00:00';
            $sql .= " AND i.created_at >= ? ";
            $params[] = $start;
            $types .= 's';
        }
    }
    if ($date_to !== '') {
        $d2 = DateTime::createFromFormat('Y-m-d', $date_to);
        if ($d2 !== false) {
            // inclusive to date -> use next day as exclusive upper bound
            $d2->modify('+1 day');
            $end = $d2->format('Y-m-d') . ' 00:00:00';
            $sql .= " AND i.created_at < ? ";
            $params[] = $end;
            $types .= 's';
        }
    }
    $sql .= " ORDER BY ic.cancelled_at DESC, io.id DESC LIMIT 1000";

    $stmt = $conn->prepare($sql);
    if ($params) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($row = $res->fetch_assoc()) $out[] = $row;
    echo json_encode(['success' => true, 'data' => $out]);
    exit;
}

/*
 * Handler: fetch_canceled_details
 * Returns: invoice, items, allocations (flat), allocations_by_item (map), cancellation, customer
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'fetch_canceled_details') {
    header('Content-Type: application/json; charset=utf-8');
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'csrf']);
        exit;
    }
    $invoiceId = (int)($_POST['invoice_id'] ?? 0);
    $cancellationId = isset($_POST['cancellation_id']) ? (int)$_POST['cancellation_id'] : null;
    if ($invoiceId <= 0) {
        echo json_encode(['success' => false, 'error' => 'invalid invoice id']);
        exit;
    }

    try {
        // invoice + customer + creator
        $st = $conn->prepare("SELECT io.*, COALESCE(c.name,'') AS customer_name, COALESCE(c.mobile,'') AS customer_mobile, c.city AS customer_city,
                                     u.username AS creator_name
                              FROM invoices_out io
                              LEFT JOIN customers c ON io.customer_id = c.id
                              LEFT JOIN users u ON io.created_by = u.id
                              WHERE io.id = ? LIMIT 1");
        $st->bind_param("i", $invoiceId);
        $st->execute();
        $inv = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$inv) {
            echo json_encode(['success' => false, 'error' => 'invoice not found']);
            exit;
        }

        // items
        $st2 = $conn->prepare("SELECT id, product_id, quantity, selling_price, total_price,
                                      (SELECT product_code FROM products p WHERE p.id = invoice_out_items.product_id LIMIT 1) AS product_code,
                                      (SELECT name FROM products p WHERE p.id = invoice_out_items.product_id LIMIT 1) AS product_name
                               FROM invoice_out_items WHERE invoice_out_id = ?");
        $st2->bind_param("i", $invoiceId);
        $st2->execute();
        $items = $st2->get_result()->fetch_all(MYSQLI_ASSOC);
        $st2->close();

        // cancellation
        if ($cancellationId) {
            $st3 = $conn->prepare("SELECT * FROM invoice_cancellations WHERE id = ? LIMIT 1");
            $st3->bind_param("i", $cancellationId);
        } else {
            $st3 = $conn->prepare("SELECT * FROM invoice_cancellations WHERE invoice_out_id = ? ORDER BY cancelled_at DESC LIMIT 1");
            $st3->bind_param("i", $invoiceId);
        }
        $st3->execute();
        $cancellation = $st3->get_result()->fetch_assoc();
        $st3->close();


        // === allocations flat + group by invoice item (مُحسّن) ===
        $allocs = [];
        $allocs_by_item = [];

        if ($cancellation && isset($cancellation['id'])) {
            $sql = "
        SELECT 
            ica.id AS ica_id,
            ica.cancellation_id,
            ica.sale_item_allocation_id,
            ica.batch_id,
            ica.qty_restored,
            ica.unit_cost,
            sa.id AS sa_id,
            sa.sale_item_id AS sa_sale_item_id,
            sa.batch_id AS sa_batch_id,
            sa.qty AS sa_qty,
            sa.unit_cost AS sa_unit_cost,
            ioi.id AS ioi_id,
            ioi.product_id AS ioi_product_id,
            ioi.quantity AS invoice_qty,
            ioi.selling_price,
            -- optional: product details if useful
            (SELECT p.product_code FROM products p WHERE p.id = ioi.product_id LIMIT 1) AS product_code,
            (SELECT p.name FROM products p WHERE p.id = ioi.product_id LIMIT 1) AS product_name
        FROM invoice_cancellation_allocations ica
        LEFT JOIN sale_item_allocations sa ON sa.id = ica.sale_item_allocation_id
        LEFT JOIN invoice_out_items ioi ON ioi.id = sa.sale_item_id
        WHERE ica.cancellation_id = ?
    ";

            $st4 = $conn->prepare($sql);
            if (!$st4) {
                // debug helper
                error_log("prepare failed (allocs): " . $conn->error);
                // return a safe error to client
                echo json_encode(['success' => false, 'error' => 'db prepare failed']);
                exit;
            }
            $st4->bind_param("i", $cancellation['id']);
            $st4->execute();
            $rawAlloc = $st4->get_result()->fetch_all(MYSQLI_ASSOC);
            $st4->close();

            foreach ($rawAlloc as $r) {
                // Prefer the invoice item id from the joined invoice_out_items (ioi_id).
                // Fallback to sa.sale_item_id if for some reason ioi_id is null.
                $itemId = null;
                if (!empty($r['ioi_id'])) {
                    $itemId = (int)$r['ioi_id'];
                } elseif (!empty($r['sa_sale_item_id'])) {
                    $itemId = (int)$r['sa_sale_item_id'];
                } else {
                    $itemId = null;
                }

                $alloc = [
                    'id' => (int)$r['ica_id'],
                    'sale_item_allocation_id' => isset($r['sale_item_allocation_id']) ? (int)$r['sale_item_allocation_id'] : null,
                    'batch_id' => isset($r['batch_id']) ? (int)$r['batch_id'] : null,
                    'qty_restored' => isset($r['qty_restored']) ? (float)$r['qty_restored'] : 0,
                    'unit_cost' => $r['unit_cost'] !== null ? (float)$r['unit_cost'] : null,
                    'linked_invoice_item_id' => $itemId,   // هذا المفتاح سيُستخدم في allocations_by_item
                    'product_id' => isset($r['ioi_product_id']) ? (int)$r['ioi_product_id'] : null,
                    'product_code' => $r['product_code'] ?? null,
                    'product_name' => $r['product_name'] ?? null,
                    'invoice_qty' => isset($r['invoice_qty']) ? (float)$r['invoice_qty'] : null,
                    'selling_price' => isset($r['selling_price']) ? (float)$r['selling_price'] : null,
                    // optionally include sale_item_alloc info
                    'allocation_qty' => isset($r['sa_qty']) ? (float)$r['sa_qty'] : null,
                    'allocation_unit_cost' => isset($r['sa_unit_cost']) ? (float)$r['sa_unit_cost'] : null,
                    // if you have a batches table and want human-readable "from" info, join it above and map here:
                    // 'batch_source' => $r['batch_source'] ?? null,
                ];

                $allocs[] = $alloc;

                // group by invoice item id if available, otherwise group under '__unmapped'
                $key = $itemId !== null ? (string)$itemId : '__unmapped';
                if (!isset($allocs_by_item[$key])) $allocs_by_item[$key] = [];
                $allocs_by_item[$key][] = $alloc;
            }
        }

        $customer = [
            'name' => $inv['customer_name'] ?? '',
            'mobile' => $inv['customer_mobile'] ?? '',
            'city' => $inv['customer_city'] ?? ''
        ];

        echo json_encode([
            'success' => true,
            'invoice' => $inv,
            'items' => $items,
            'allocations' => $allocs,
            'allocations_by_item' => $allocs_by_item,
            'cancellation' => $cancellation,
            'customer' => $customer
        ]);
        exit;
    } catch (Exception $e) {
        error_log("fetch_canceled_details error: " . $e->getMessage() . " in " . __FILE__);
        echo json_encode(['success' => false, 'error' => 'server error']);
        exit;
    }
}

require_once BASE_DIR . 'partials/header.php';
require_once BASE_DIR . 'partials/sidebar.php';
?>

<style>
    /* بسيط ومرن — عدّل على ذوقك */
    .card {
        background: var(--bg);
        padding: 14px;
        border-radius: 10px;
        box-shadow: 0 6px 18px rgba(15, 23, 42, 0.04);
        margin-bottom: 16px;
        color: var(--text);
    }

    .filters {
        display: flex;
        gap: 8px;
        justify-content: space-around;
        align-items: center;
    }

    .filters input,
    .filters select {
        padding: 8px 10px;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        min-width: 180px;
    }

    .filters button {
        padding: 8px 12px;
        border-radius: 6px;
        border: 0;
        background: #0d6efd;
        color: #fff;
        cursor: pointer;
    }

    .actions {
        display: flex;
        gap: 8px;
        justify-content: center;
    }

    .btn-icon {
        transition: opacity .15s ease-in-out;
        border: 0;
        background: var(--bg);
        color: var(--text);
        cursor: pointer;
        padding: 10px;
        border-radius: 6px;
    }

    .btn-icon:hover {
        opacity: 0.85;
        transform: scale(1.05);
    }

    .badge {
        display: inline-block;
        padding: 6px 10px;
        border-radius: 20px;
        color: #fff;
        font-weight: 700;
    }

    .badge-canceled {
        background: #ef4444;
    }

    .small-muted {
        color: #6b7280;
        font-size: 0.85rem;
    }



    .modal-card {
        background: var(--bg);
        border-radius: 10px;
        width: 100%;
        max-width: 920px;
        padding: 18px;
        box-shadow: 0 8px 30px rgba(2, 6, 23, .15);
        max-height: 90vh;
        overflow: auto;
    }

    .table-small td,
    .table-small th {
        padding: 8px;
        font-size: 0.92rem;
    }
</style>

<div class="canceld-invoices mt-3">
    <div class="container">
        <h2 class="my-3"><i class="fas fa-ban"></i> الفواتير الملغاة</h2>

        <div class="card">
            <div class="d-flex">
                <div class="filters my-4" style="flex-wrap: wrap; gap: 16px;">
                    <input type="text" id="filter_customer_id" placeholder="معرف العميل (Customer ID)" style="flex:1 1 180px; min-width:150px;">
                    <input type="text" id="filter_invoice_id" placeholder="رقم الفاتورة" style="flex:1 1 180px; min-width:150px;">
                    <input type="text" id="filter_mobile" placeholder="رقم الهاتف" style="flex:1 1 180px; min-width:150px;">

              
                    <button id="btn_filter" style="flex:0 0 auto;">بحث</button>
                    <button id="btn_reset" style="background:#6b7280; flex:0 0 auto;">مسح</button>
                </div>
            </div>

            <div id="tableContainer" class="custom-table-wrapper">
                <table id="canceledTable" class="custom-table">
                    <thead class="center">
                        <tr>
                            <th style="width:80px">رقم الفاتورة</th>
                            <th>اسم العميل</th>
                            <th style="width:140px">الموبايل</th>
                            <th style="width:140px">حالة الفاتورة</th>
                            <th style="width:160px">أنشئت بواسطة</th>
                            <th style="width:180px">تاريخ الإلغاء</th>
                            <th style="width:160px">إجمالي الفاتورة</th>
                            <th style="width:80px">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="canceledBody"></tbody>
                </table>
            </div>
        </div>

        <!-- Invoice modal (لا يعرض جدوال allocations الآن) -->
        <div id="invoiceModal" class="modal-backdrop" aria-hidden="true" role="dialog">
            <div class="modal-card" role="document" id="invoiceModalCard">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                    <h4 id="modalTitle">تفاصيل الفاتورة</h4>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <div id="modalTotal" class="fw-bold" style="min-width:160px;text-align:left;">الإجمالي: 0.00 ج.م</div>
                        <button id="modalPrintBtn" class="btn-icon" title="طباعة"><i class="fas fa-print"></i></button>
                        <button id="modalClose" class="btn-icon btn-primary btn">إغلاق</button>
                    </div>
                </div>

                <div id="modalContentArea">
                    <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;">
                        <div style="flex:1">
                            <div style="font-weight:700;font-size:1.05rem">فاتورة مبيعات ملغاة — <span id="modalInvoiceNo" style="color:var(--bs-primary,#0d6efd)">#0</span></div>
                            <div id="modalCancelDate" class="small-muted">تاريخ الإلغاء: -</div>
                        </div>
                        <div style="text-align:left">
                            <span id="modalStatusBadge" class="badge badge-canceled">ملغي</span>
                        </div>
                    </div>

                    <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:12px;">
                        <div style="flex:1;min-width:220px;padding:12px;border-radius:10px;background:var(--card-bg,rgba(0,0,0,0.03))">
                            <div style="font-weight:700;margin-bottom:6px">معلومات الفاتورة</div>
                            <div><strong>المجموعة:</strong> <span id="modalGroup">-</span></div>
                            <div><strong>منشأ الفاتورة:</strong> <span id="modalCreator">-</span></div>
                            <div><strong>آخر تحديث:</strong> <span id="modalUpdatedAt">-</span></div>
                            <div style="margin-top:6px"><strong>سبب الإلغاء:</strong> <span id="modalCancelReason">-</span></div>
                        </div>

                        <div style="flex:1;min-width:220px;padding:12px;border-radius:10px;background:var(--card-bg,rgba(0,0,0,0.03))">
                            <div style="font-weight:700;margin-bottom:6px">معلومات العميل</div>
                            <div><strong>الاسم:</strong> <span id="modalCustomerName">-</span></div>
                            <div><strong>الموبايل:</strong> <span id="modalCustomerMobile">-</span></div>
                            <div><strong>المدينة:</strong> <span id="modalCustomerCity">-</span></div>
                        </div>
                    </div>

                    <!-- بنود الفاتورة: أضفت عمود زر "تفاصيل" لكل بند -->
                    <div class="custom-table-wrapper">
                        <table class="custom-table">
                            <thead style="background:rgba(0,0,0,0.03);font-weight:700;">
                                <tr>
                                    <th style="padding:10px;width:40px">#</th>
                                    <th style="padding:10px;text-align:right">اسم / كود</th>
                                    <th style="padding:10px;width:100px;text-align:center">كمية</th>
                                    <th style="padding:10px;width:120px;text-align:right">سعر البيع</th>
                                    <th style="padding:10px;width:120px;text-align:right">الإجمالي</th>
                                    <th style="padding:10px;width:90px;text-align:center">تفاصيل</th>
                                </tr>
                            </thead>
                            <tbody id="modalItemsBody">
                                <!-- rows -->
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4" style="padding:12px;text-align:right;font-weight:700">الإجمالي الكلي</td>
                                    <td id="modalGrandTotal" style="padding:12px;text-align:right;font-weight:800">0.00 ج.م</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                </div>
            </div>
        </div>

        <!-- تفاصيل تخصيص محدد (Modal صغير) -->
        <div id="allocDetailModal" class="modal-backdrop" aria-hidden="true">
            <div class="modal-card" style="max-width:540px;">
                <h4>تفاصيل التخصيص</h4>
                <div id="allocDetailContent">
                    <!-- سيُملأ بواسطة JS -->
                </div>
                <div style="text-align:left;margin-top:12px;">
                    <button id="allocDetailClose" class="btn-icon">إغلاق</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const CSRF_TOKEN = <?= json_encode($csrf) ?>;
    document.addEventListener('DOMContentLoaded', () => {
        const tBody = document.getElementById('canceledBody');
        const btnFilter = document.getElementById('btn_filter');
        const btnReset = document.getElementById('btn_reset');

        async function fetchCanceled(params = {}) {
            const body = new FormData();
            body.append('action', 'fetch_canceled_list');
            body.append('csrf_token', CSRF_TOKEN);
            if (params.customer_id) body.append('customer_id', params.customer_id);
            if (params.invoice_id) body.append('invoice_id', params.invoice_id);
            if (params.mobile) body.append('mobile', params.mobile);

            const res = await fetch(window.location.href, {
                method: 'POST',
                body,
                credentials: 'same-origin'
            });
            const json = await res.json();
            if (!json.success) {
                alert(json.error || 'خطأ في جلب البيانات');
                return [];
            }
            return json.data;
        }

        async function renderTable(filters = {}) {
            tBody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:18px">جارٍ التحميل...</td></tr>';
            const rows = await fetchCanceled(filters);
            if (!rows || rows.length === 0) {
                tBody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:18px">لا توجد نتائج</td></tr>';
                return;
            }
            tBody.innerHTML = '';
            rows.forEach(r => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
          <td>#${r.invoice_id}</td>
          <td style="text-align:right">${r.customer_name || '—'}</td>
          <td style="text-align:center">${r.mobile || '—'}</td>
          <td style="text-align:center"><span class="badge badge-canceled">ملغي</span></td>
          <td style="text-align:center">${r.created_by_name || r.created_by || '-'}</td>
          <td style="text-align:center">${r.cancelled_at || '-'}</td>
          <td style="text-align:right">${Number(r.total || 0).toFixed(2)} ج.م</td>
          <td class="actions">
            <button class="btn-icon btn-view " data-invoice-id="${r.invoice_id}" data-cancel-id="${r.cancellation_id}" title="عرض"><i class="fas fa-eye"></i></button>
          </td>
        `;
                tBody.appendChild(tr);
            });

            // attach view handlers
            document.querySelectorAll('.btn-view').forEach(b => {
                b.addEventListener('click', (ev) => {
                    const invoiceId = b.dataset.invoiceId;
                    const cancelId = b.dataset.cancelId;
                    openInvoiceModal(invoiceId, cancelId);
                });
            });
        }

        btnFilter.addEventListener('click', () => {
            const c = document.getElementById('filter_customer_id').value.trim();
            const i = document.getElementById('filter_invoice_id').value.trim();
            const m = document.getElementById('filter_mobile').value.trim();
            renderTable({
                customer_id: c,
                invoice_id: i,
                mobile: m
            });
        });

        btnReset.addEventListener('click', () => {
            document.getElementById('filter_customer_id').value = '';
            document.getElementById('filter_invoice_id').value = '';
            document.getElementById('filter_mobile').value = '';
            renderTable({});
        });

        // initial render
        renderTable({});

        // ------------ Modal logic ----------------
        const invoiceModal = document.getElementById('invoiceModal');
        const modalInvoiceNo = document.getElementById('modalInvoiceNo');
        const modalCancelDate = document.getElementById('modalCancelDate');
        const modalGroup = document.getElementById('modalGroup');
        const modalCreator = document.getElementById('modalCreator');
        const modalUpdatedAt = document.getElementById('modalUpdatedAt');
        const modalCancelReason = document.getElementById('modalCancelReason');
        const modalCustomerName = document.getElementById('modalCustomerName');
        const modalCustomerMobile = document.getElementById('modalCustomerMobile');
        const modalCustomerCity = document.getElementById('modalCustomerCity');
        const modalItemsBody = document.getElementById('modalItemsBody');
        const modalGrandTotal = document.getElementById('modalGrandTotal');
        const modalTotal = document.getElementById('modalTotal');

        document.getElementById('modalClose').addEventListener('click', () => {
            invoiceModal.style.display = 'none';
        });
        document.getElementById('modalPrintBtn').addEventListener('click', () => window.print());

        const allocDetailModal = document.getElementById('allocDetailModal');
        const allocDetailContent = document.getElementById('allocDetailContent');
        document.getElementById('allocDetailClose').addEventListener('click', () => allocDetailModal.style.display = 'none');

        async function openInvoiceModal(invoiceId, cancellationId) {
            invoiceModal.style.display = 'flex';

            const fd = new FormData();
            fd.append('action', 'fetch_canceled_details');
            fd.append('csrf_token', CSRF_TOKEN);
            fd.append('invoice_id', invoiceId);
            if (cancellationId) fd.append('cancellation_id', cancellationId);

            const res = await fetch(window.location.href, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            });
            const json = await res.json();
            if (!json.success) {
                alert(json.error || 'خطأ في جلب تفاصيل الفاتورة');
                invoiceModal.style.display = 'none';
                return;
            }
            const data = json;

            // fill header
            modalInvoiceNo.textContent = '#' + (data.invoice.id || invoiceId);
            modalCancelDate.textContent = 'تاريخ الإلغاء: ' + (data.cancellation?.cancelled_at || '-');
            modalGroup.textContent = data.invoice.invoice_group || '-';
            modalCreator.textContent = data.invoice.creator_name || data.invoice.created_by || '-';
            modalUpdatedAt.textContent = data.invoice.updated_at || '-';
            modalCancelReason.textContent = data.cancellation?.reason || '-';
            modalCustomerName.textContent = data.customer.name || '-';
            modalCustomerMobile.textContent = data.customer.mobile || '-';
            modalCustomerCity.textContent = data.customer.city || '-';

            // items (اضفنا زر "تفاصيل" لكل بند)
            modalItemsBody.innerHTML = '';
            let totalSum = 0;
            (data.items || []).forEach((it, idx) => {
                const tr = document.createElement('tr');

                const invoiceItemId = it.id || null;
                const qty = parseFloat(it.quantity || it.qty || 0);
                const sp = parseFloat(it.selling_price || 0);
                const lineTotal = parseFloat(it.total_price || (qty * sp)) || 0;
                totalSum += lineTotal;

                const detailsBtnHtml = invoiceItemId ?
                    `<button class="btn-icon btn-item-allocs" data-invoice-item-id="${invoiceItemId}" title="تفاصيل التخصيص"><i class="fas fa-info-circle"></i></button>` :
                    `<span class="small-muted">-</span>`;

                tr.innerHTML = `<td style="padding:10px">${idx+1}</td>
                            <td style="padding:10px;text-align:right">${escapeHtml(it.product_name || '')} — ${escapeHtml(it.product_code || '')}</td>
                            <td style="padding:10px;text-align:center">${Number(qty).toFixed(2)}</td>
                            <td style="padding:10px;text-align:right">${Number(sp).toFixed(2)} ج.م</td>
                            <td style="padding:10px;text-align:right;font-weight:700">${Number(lineTotal).toFixed(2)} ج.م</td>
                            <td style="padding:10px;text-align:center">${detailsBtnHtml}</td>`;
                modalItemsBody.appendChild(tr);
            });
            modalGrandTotal.textContent = Number(totalSum).toFixed(2) + ' ج.م';
            modalTotal.textContent = 'الإجمالي: ' + Number(totalSum).toFixed(2) + ' ج.م';

            // attach handlers for item alloc detail buttons
            document.querySelectorAll('.btn-item-allocs').forEach(btn => {
                btn.addEventListener('click', () => {
                    const invoiceItemId = btn.dataset.invoiceItemId;
                    openAllocDetailModalForItem(invoiceItemId, data.allocations_by_item || {});
                });
            });
        }

        // show allocations for a specific invoice item id
        function openAllocDetailModalForItem(invoiceItemId, allocationsByItem) {
            const list = allocationsByItem[invoiceItemId] || [];
            let html = '';
            if (!list.length) {
                html = '<div style="padding:12px;" >لا توجد تخصيصات مسجلة لهذا البند.</div>';
            } else {
                html = `<table class="custom-table">
                <thead >
                  <tr><th style="padding:8px">#</th><th style="padding:8px;text-align:right">رقم الدفعه المسترجع اليها</th><th style="padding:8px;text-align:center">الكمية المسترجعة</th><th style="padding:8px;text-align:right">تكلفة الوحدة</th></tr>
                </thead><tbody>`;
                list.forEach((al, idx) => {
                    html += `<tr>
                    <td style="padding:8px">${idx+1}</td>
                    <td style="padding:8px;text-align:right">${al.batch_id}</td>
                    <td style="padding:8px;text-align:center">${Number(al.qty_restored||0).toFixed(3)}</td>
                    <td style="padding:8px;text-align:right">${al.unit_cost !== null ? Number(al.unit_cost).toFixed(2) + ' ج.م' : '-'}</td>
                </tr>`;
                });
                html += `</tbody></table>`;
            }
            allocDetailContent.innerHTML = html;
            allocDetailModal.style.display = 'flex';
        }

        function escapeHtml(s) {
            if (!s) return '';
            return s.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;');
        }
    });
</script>

<?php
// تحرير الموارد
$conn->close();
require_once BASE_DIR . 'partials/footer.php';
?>