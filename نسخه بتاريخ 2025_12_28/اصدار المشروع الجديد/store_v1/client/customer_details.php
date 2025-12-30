  <?php
  // admin/manage_customers.php
  $page_title = "إدارة العملاء";
  $class_dashboard = "active";
  require_once dirname(__DIR__) . '/config.php';
  require_once BASE_DIR . 'partials/session_admin.php';
  require_once BASE_DIR . 'partials/header.php';
  require_once BASE_DIR . 'partials/sidebar.php';
  // جلب معرف العميل من الرابط
  $customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;

  // إذا لم يكن هناك معرف عميل، ربما نريد عرض رسالة خطأ أو توجيه المستخدم.
  if (!$customer_id) {
    // ربما نعيد توجيه المستخدم إلى صفحة إدارة العملاء
    header("Location: manage_customers.php");
    exit;
  }

  // التحقق إذا كان العميل رقم 8
  $is_customer_8 = ($customer_id == 8);

  ?>
  
 
 <head>
    <link rel="stylesheet" href="assets/index.css" />
  </head> 

    <div class="container-fluid py-4">
      <!-- رأس العميل -->
      <div class="customer-header">
        <div class="row align-items-center">
          <div class="col-md-8">
            <div class="d-flex align-items-center">
              <div class="customer-avatar me-4" id="customerAvatar">م</div>
              <div>
                <h1 class="h2 mb-2" id="customerName">no name</h1>
                <div class="d-flex flex-wrap gap-4 fs-5">
                  <span><i class="fas fa-phone me-2"></i>
                    <span id="customerPhone">--</span></span>
                  <span><i class="fas fa-city me-2"></i>
                    <span id="customerAddress">--</span></span>
                  <span><i class="fas fa-calendar me-2"></i> عضو منذ
                    <span id="customerJoinDate">--</span></span>
                </div>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="row g-3">
              <div class="col-6">
                <div class="stat-card negative">
                  <div class="stat-value" id="currentBalance">0</div>
                  <div class="stat-label">الرصيد الحالي</div>
                  <small class="text-danger">مدين</small>
                </div>
              </div>
              <div class="col-6">
                <div class="stat-card positive">
                  <div class="stat-value" id="walletBalance">0.00</div>
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
          <div class="stat-value" id="totalInvoicesCount">0</div>
          <div class="stat-label">جميع الفواتير</div>
          <div class="stat-amount text-primary">0</div>
        </div>
        <div class="invoice-stat-card pending" data-filter="pending">
          <div class="stat-value" id="pendingInvoicesCount">0</div>
          <div class="stat-label">مؤجل</div>
          <div class="stat-amount text-warning">0.00ج.م</div>
        </div>
        <div class="invoice-stat-card partial" data-filter="partial">
          <div class="stat-value" id="partialInvoicesCount">0</div>
          <div class="stat-label">جزئي</div>
          <div class="stat-amount text-info"> ج.م0.00</div>
        </div>
        <div class="invoice-stat-card paid" data-filter="paid">
          <div class="stat-value" id="paidInvoicesCount">0</div>
          <div class="stat-label">مسلم</div>
          <div class="stat-amount text-success">0.00ج.م</div>
        </div>
        <!-- <div class="invoice-stat-card returned" data-filter="returned">
          <div class="stat-value" id="returnedInvoicesCount">0</div>
          <div class="stat-label">مرتجع</div>
          <div class="stat-amount text-danger">0.00ج.م</div>
        </div> -->
      </div>




      <!-- أزرار الإجراءات السريعة -->
      <div class="quick-actions mb-4">
        <div class="d-flex gap-3 flex-wrap">
          <?php if (!$is_customer_8): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newWorkOrderModal">
              <i class="fas fa-tools me-2"></i> شغلانة جديدة
            </button>
          <?php endif; ?>

          <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#paymentModal">
            <i class="fas fa-money-bill-wave me-2"></i> سداد
          </button>

          <?php if (!$is_customer_8): ?>
            <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#walletDepositModal">
              <i class="fas fa-wallet me-2"></i> إيداع محفظة
            </button>
            <button class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#walletWithdrawModal">
              <i class="fas fa-wallet me-2"></i> سحب محفظة
            </button>
          <?php endif; ?>

          <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#statementReportModal">
            <i class="fas fa-file-invoice me-2"></i> كشف حساب
          </button>
          <button class="btn btn-outline-secondary" id="printMultipleBtn">
            <i class="fas fa-print me-2"></i> طباعة متعددة
          </button>
        </div>
      </div>
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
              <div class="col-md-6">
                <label for="productSearch" class="form-label">بحث بالصنف</label>
                <input
                  type="text"
                  class="form-control"
                  id="productSearch"
                  placeholder="اكتب اسم الصنف..." />
              </div>
              <!-- أضف هذا داخل div.row.g-3 في Filters -->


              <div class="col-12 mb-3">
                <label for="advancedProductSearch" class="form-label">بحث متقدم عن صنف</label>
                <input
                  type="text"
                  class="form-control"
                  id="advancedProductSearch"
                  placeholder="اكتب اسم الصنف للبحث في جميع الفواتير..." />
                <small class="text-muted">سيتم تمييز النص المطابق باللون الأصفر وعرض الفاتورة في الجانب
                  الأيمن</small>
              </div>

              <div
                id="advancedSearchResults"
                class="product-search-results"
                style="display: none"></div>
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
              <button
                class="nav-link active"
                id="invoices-tab"
                data-bs-toggle="tab"
                data-bs-target="#invoices"
                type="button"
                role="tab">
                <i class="fas fa-receipt me-2"></i> الفواتير
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button
                class="nav-link"
                id="work-orders-tab"
                data-bs-toggle="tab"
                data-bs-target="#work-orders"
                type="button"
                role="tab">
                <i class="fas fa-tools me-2"></i> الشغلانات
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button
                class="nav-link"
                id="transaction-tab"
                data-bs-toggle="tab"
                data-bs-target="#transaction"
                type="button"
                role="tab">
                <i class="fas fa-wallet me-2"></i> حركات العميل
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button
                class="nav-link"
                id="returns-tab"
                data-bs-toggle="tab"
                data-bs-target="#returns"
                type="button"
                role="tab">
                <i class="fas fa-undo me-2"></i> المرتجعات
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button
                class="nav-link"
                id="wallet-tab"
                data-bs-toggle="tab"
                data-bs-target="#walletTransaction"
                type="button"
                role="tab">
                <i class="fas fa-wallet me-2"></i> حركات المحفظة
              </button>
            </li>
          </ul>

          <div class="tab-content p-4" id="customerTabsContent">
            <!-- تبويب الفواتير -->
            <div
              class="tab-pane fade show active"
              id="invoices"
              role="tabpanel">
              <div class="table-responsive-fixed custom-table-wrapper">
                <div
                  class="mb-3 d-flex justify-content-between align-items-center">
                  <div>
                    <input
                      type="checkbox"
                      class="form-check-input"
                      id="selectAllInvoices" />
                    <label class="form-check-label ms-2" for="selectAllInvoices">تحديد الكل</label>
                  </div>
                  <button
                    class="btn btn-primary btn-sm"
                    id="printSelectedInvoices"
                    disabled>
                    <i class="fas fa-print me-2"></i>طباعة المحدد
                  </button>
                </div>
                <table class="custom-table table-hover">
                  <thead class="center">
                    <tr>
                      <th style="width: 40px">
                        <input
                          type="checkbox"
                          class="form-check-input"
                          id="selectAllInvoicesHeader" />
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
                  <tbody id="invoicesTableBody"></tbody>
                </table>
              </div>
            </div>

            <!-- تبويب الشغلانات -->
            <div class="tab-pane fade" id="work-orders" role="tabpanel">
              <div class="row" id="workOrdersContainer"></div>
            </div>

            <!-- تبويب حركات المحفظة -->
            <div class="tab-pane fade" id="transaction" role="tabpanel">
              <div class="table-responsive-fixed custom-table-wrapper">
                <table class="custom-table">
                  <thead>
                    <tr>
                      <th>تاريخ

                        انشاء
                        المعامله</th>
                      <th>تاريخ
                        تسجيل
                        المعامله</th>
                      <th>نوع الحركة</th>
                      <th>الوصف</th>
                      <th>المبلغ</th>
                      <th>المحفظة قبل</th>
                      <th>المحفظة بعد</th>
                      <th>الديون قبل</th>
                      <th>الديون بعد</th>
                      <th>المستخدم</th>
                    </tr>
                  </thead>
                  <tbody id="transactionTableBody"></tbody>
                </table>
              </div>
            </div>


            <div class="tab-pane fade" id="walletTransaction" role="tabpanel">
              <div class="table-responsive-fixed custom-table-wrapper">
                <table class="custom-table">
                  <thead>
                    <tr>
                      <th>تاريخ

                        انشاء
                        المعامله</th>
                      <th>تاريخ
                        تسجيل
                        المعامله</th>
                      <th>نوع الحركة</th>
                      <th>الوصف</th>
                      <th>المبلغ</th>
                      <th>المحفظة قبل</th>
                      <th>المحفظة بعد</th>

                      <th>المستخدم</th>
                    </tr>
                  </thead>
                  <tbody id="walletTransactionTableBody"></tbody>
                </table>
              </div>
            </div>
            <!-- تبويب المرتجعات -->
            <div class="tab-pane fade" id="returns" role="tabpanel">
              <div class="table-responsive-fixed custom-table-wrapper">
                <table class=" custom-table">
                  <thead>
                    <tr>
                      <th>رقم المرتجع</th>
                      <th>الفاتورة الأصلية</th>
                      <th>المنتج</th>
                      <th>الكمية</th>
                      <th>المبلغ</th>
                      <!-- <th>طريقة الاسترجاع</th> -->
                      <th>الحالة</th>
                      <th>التاريخ</th>
                      <th>المستخدم</th>
                    </tr>
                  </thead>
                  <tbody id="returnsTableBody"></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- مودال عرض فواتير الشغلانة -->
    <div
      class="modal fade"
      id="workOrderInvoicesModal"
      tabindex="-1"
      aria-labelledby="workOrderInvoicesModalLabel"
      aria-hidden="true">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="workOrderInvoicesModalLabel">
              فواتير الشغلانة: <span id="workOrderInvoicesName"></span>
            </h5>
            <button
              type="button"
              class="btn-close"
              data-bs-dismiss="modal"
              aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="row mb-4">
              <div class="col-md-4">
                <div class="card card-work-order">
                  <div class="card-body text-center">
                    <h6>إجمالي الفواتير</h6>
                    <h4 id="workOrderTotalInvoices">0.00 ج.م</h4>
                  </div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="card card-work-order">
                  <div class="card-body text-center">
                    <h6>المدفوع</h6>
                    <h4 id="workOrderTotalPaid">0.00 ج.م</h4>
                  </div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="card card-work-order">
                  <div class="card-body text-center">
                    <h6>المتبقي</h6>
                    <h4 id="workOrderTotalRemaining">0.00 ج.م</h4>
                  </div>
                </div>
              </div>
            </div>

            <div class="table-responsive-fixed custom-table-wrapper">
              <table class=" table-hover custom-table">
                <thead class="center">
                  <tr>
                    <th>#</th>
                    <th>التاريخ</th>
                    <th>الإجمالي</th>
                    <th>المدفوع</th>
                    <th>المتبقي</th>
                    <th>الحالة</th>
                    <th>الإجراءات</th>
                  </tr>
                </thead>
                <tbody id="workOrderInvoicesList"></tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-secondary"
              data-bs-dismiss="modal">
              إغلاق
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- مودال الشغلانة الجديدة -->
    <div
      class="modal fade"
      id="newWorkOrderModal"
      tabindex="-1"
      aria-labelledby="newWorkOrderModalLabel"
      aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="newWorkOrderModalLabel">
              شغلانة جديدة
            </h5>
            <button
              type="button"
              class="btn-close btn-close-white"
              data-bs-dismiss="modal"
              aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form id="newWorkOrderForm">
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label for="workOrderName" class="form-label">اسم الشغلانة</label>
                  <input
                    type="text"
                    class="form-control"
                    id="workOrderName"
                    required />
                </div>
                <div class="col-md-6 mb-3">
                  <label for="workOrderStartDate" class="form-label">تاريخ البدء</label>
                  <input
                    type="date"
                    class="form-control"
                    id="workOrderStartDate"
                    required />
                </div>
              </div>
              <div class="row">
                <div class="col-12 mb-3">
                  <label for="workOrderDescription" class="form-label">وصف الشغلانة</label>
                  <textarea
                    class="form-control"
                    id="workOrderDescription"
                    rows="3"
                    required></textarea>
                </div>
              </div>
              <div class="row">
                <div class="col-12 mb-3">
                  <label for="workOrderNotes" class="form-label">ملاحظات إضافية</label>
                  <textarea
                    class="form-control"
                    id="workOrderNotes"
                    rows="2"></textarea>
                </div>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-secondary"
              data-bs-dismiss="modal">
              إلغاء
            </button>
            <button type="button" class="btn btn-primary" id="saveWorkOrderBtn">
              حفظ الشغلانة
            </button>
          </div>
        </div>
      </div>
    </div>

    <div
      class="modal fade"
      id="paymentModal"
      tabindex="-1"
      aria-labelledby="paymentModalLabel"
      aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="paymentModalLabel">سداد مديونية</h5>
            <button
              type="button"
              class="btn-close btn-close-white"
              data-bs-dismiss="modal"
              aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <!-- اختيار نوع السداد -->
            <div class="mb-3">
              <div class="form-check form-check-inline">
                <input
                  class="form-check-input"
                  type="radio"
                  name="paymentType"
                  id="payInvoicesRadio"
                  value="invoices"
                  checked />
                <label class="form-check-label" for="payInvoicesRadio">
                  سداد من فواتير
                </label>
              </div>
              <div class="form-check form-check-inline">
                <input
                  class="form-check-input"
                  type="radio"
                  name="paymentType"
                  id="payWorkOrderRadio"
                  value="workOrder" />
                <label class="form-check-label" for="payWorkOrderRadio">
                  سداد لشغلانة محددة
                </label>
              </div>
            </div>

            <!-- قسم سداد الفواتير -->
            <div id="invoicesPaymentSection">
              <div class="mb-3">
                <label for="invoiceSearch" class="form-label">بحث في الفواتير (اختياري)</label>
                <input
                  type="text"
                  class="form-control"
                  id="invoiceSearch"
                  placeholder="ابحث برقم الفاتورة..." />
              </div>
              <div class="d-flex justify-content-end mb-2">
                <button
                  type="button"
                  class="btn btn-sm btn-outline-secondary toggleInvoicesSectionBtn"
                  data-section="invoice-payment">
                  <i class="fas fa-chevron-up me-1"></i>
                  إخفاء الفواتير
                </button>
              </div>

              <div class="table-responsive-fixed custom-table-wrapper" id="invoice-payment">
                <!-- في تبويب الفواتير، قبل الجدول مباشرة -->
                <div
                  class="mb-3 d-flex justify-content-between align-items-center">
                  <div class="d-flex gap-2">
                    <button
                      class="btn btn-sm btn-outline-primary"
                      id="selectAllInvoicesBtn">
                      <i class="fas fa-check-double me-1"></i> تحديد الكل
                    </button>
                    <button
                      class="btn btn-sm btn-outline-secondary"
                      id="selectNonWorkOrderBtn">
                      <i class="fas fa-file-invoice me-1"></i> تحديد غير
                      المرتبطة
                    </button>
                  </div>
                </div>
                <table class=" custom-table">
                  <thead class="center">
                    <tr>
                      <th width="40">
                        <input
                          type="checkbox"
                          class="form-check-input"
                          id="selectAllInvoicesForPayment" />
                      </th>
                      <th>#</th>
                      <th>التاريخ</th>
                      <th>الإجمالي</th>
                      <th>المتبقي</th>
                      <th>المبلغ المسدد</th>
                    </tr>
                  </thead>
                  <tbody id="invoicesPaymentTableBody"></tbody>
                </table>
              </div>

              <div class="row mt-3">
                <div class="col-md-6">
                  <label for="invoicesTotalAmount" class="form-label">المبلغ الكلي المراد سداده</label>
                  <div class="input-group">
                    <input
                      readonly
                      disabled
                      type="number"
                      class="form-control"
                      id="invoicesTotalAmount"
                      min="0"
                      placeholder="المبلغ الكلي" />
                    <span class="input-group-text">ج.م</span>
                  </div>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                  <button
                    type="button"
                    class="btn btn-outline-secondary w-100"
                    id="fillInvoicesRemainingBtn">
                    <i class="fas fa-fill me-1"></i> سدّد المتبقي بالكامل
                  </button>
                </div>
              </div>
            </div>

            <!-- قسم سداد الشغلانة -->
            <div id="workOrderPaymentSection" style="display: none">
              <div class="mb-3">
                <label for="workOrderSearch" class="form-label">بحث في الشغلانات</label>
                <input
                  type="text"
                  class="form-control"
                  id="workOrderSearch"
                  placeholder="اكتب اسم الشغلانة للبحث..." />
                <div
                  id="workOrderSearchResults"
                  class="product-search-results"
                  style="display: none"></div>
              </div>

              <div id="selectedWorkOrderDetails" style="display: none">
                <h6>تفاصيل الشغلانة المحددة</h6>
                <div class="card mb-3">
                  <div class="card-body">

                    <!-- عنوان الشغلانة -->
                    <h5 id="selectedWorkOrderName" class="mb-2 note-text"></h5>

                    <!-- وصف الشغلانة -->
                    <p id="selectedWorkOrderDescription" class="text-muted mb-3"></p>

                    <!-- معلومات أساسية -->
                    <div class="row text-center">
                      <div class="col-4 mb-2">
                        <small class="text-muted d-block">تاريخ البدء</small>
                        <div id="selectedWorkOrderStartDate" class="fw-bold note-text"></div>
                      </div>
                      <div class="col-4 mb-2">
                        <small class="text-muted d-block">حالة الشغلانة</small>
                        <div id="selectedWorkOrderStatus" class="fw-bold  note-text "></div>
                      </div>
                      <div class="col-4 mb-2">
                        <small class="text-muted d-block">عدد الفواتير الغير مسدده</small>
                        <div id="selectedWorkOrderInvoicesCount" class="fw-bold note-text"></div>
                      </div>
                    </div>

                  </div>
                </div>

                <div class="row  justify-content-between align-items-center">

                  <div class="col-6 mb-2 ">
                    <h6>فواتير الشغلانة</h6>
                  </div>
                  <div class="col-6 mb-2  ">

                    <button
                      type="button"
                      class="btn btn-sm btn-outline-secondary toggleInvoicesSectionBtn"
                      data-section="work-order-payment">
                      <i class="fas fa-chevron-up me-1"></i>
                      إخفاء الفواتير الشغلانه
                    </button>
                  </div>
                </div>
                <div class="custom-table-wrapper" id="work-order-payment">
                  <table class="custom-table table-sm">
                    <thead class="center">
                      <tr>
                        <th>#</th>
                        <th>التاريخ</th>
                        <th>الإجمالي</th>
                        <th>المدفوع</th>
                        <th>المتبقي</th>
                        <th>المبلغ المسدد</th>
                      </tr>
                    </thead>
                    <tbody id="workOrderInvoicesTableBody"></tbody>
                  </table>
                </div>
              </div>
              <div class="row mt-3">
                <div class="col-md-6">
                  <label for="invoicesTotalAmountWorkOrder" class="form-label">المبلغ الكلي المراد سداده</label>
                  <div class="input-group">
                    <input
                      disapbled
                      readonly
                      type="number"
                      class="form-control"
                      id="invoicesTotalAmountWorkOrder"
                      min="0"
                      placeholder="المبلغ الكلي" />
                    <span class="input-group-text">ج.م</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- طرق الدفع المشتركة -->
            <div class="mt-4">
              <h6>طرق الدفع</h6>
              <div id="paymentMethodsContainer"></div>
              <!-- بعد paymentMethodsContainer -->
              <div class="mt-4 border p-3 rounded " id="payment-validation-section"">
                  <div
                    class=" d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">تحقق المدفوعات</h6>
                <button
                  type="button"
                  class="btn btn-success btn-sm"
                  id="autoDistributeBtn">
                  <i class="fas fa-calculator me-1"></i> توزيع تلقائي
                </button>
              </div>

              <div class="row mb-2">
                <div class="col-md-6">
                  <div class="input-group input-group-sm">
                    <span class="input-group-text">مجموع طرق الدفع</span>
                    <input
                      type="text"
                      class="form-control text-center fw-bold"
                      id="totalPaymentMethodsAmount"
                      readonly />
                    <span class="input-group-text">ج.م</span>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="input-group input-group-sm">
                    <span class="input-group-text">المبلغ المطلوب</span>
                    <input
                      type="text"
                      class="form-control text-center fw-bold"
                      id="paymentRequiredAmount"
                      readonly />
                    <span class="input-group-text">ج.م</span>
                  </div>
                </div>
              </div>

              <div id="paymentValidation" class="text-center mt-2">
                <div
                  id="paymentValid"
                  class="alert alert-success py-2 mb-0"
                  style="display: none">
                  <i class="fas fa-check-circle me-1"></i> المبلغ المدخل يساوي
                  المطلوب
                </div>
                <div
                  id="paymentInvalid"
                  class="alert alert-danger py-2 mb-0"
                  style="display: none">
                  <i class="fas fa-times-circle me-1"></i> المبلغ المدخل لا
                  يساوي المطلوب
                </div>
                <div
                  id="paymentExceeds"
                  class="alert alert-warning py-2 mb-0"
                  style="display: none">
                  <i class="fas fa-exclamation-triangle me-1"></i> المبلغ
                  المدخل يتجاوز المطلوب
                </div>
              </div>
            </div>
            <button
              type="button"
              class="btn btn-outline-primary btn-sm mt-2"
              id="addPaymentMethodBtn">
              <i class="fas fa-plus me-1"></i> إضافة طريقة دفع
            </button>

            <!-- ملخص الدفع -->
            <div class="payment-summary mt-3 p-3 border rounded">
              <div class="d-flex justify-content-between">
                <span>المبلغ الإجمالي المطلوب:</span>
                <span id="totalRequiredAmount">0.00 ج.م</span>
              </div>
              <div class="d-flex justify-content-between">
                <span>المبلغ المدخل:</span>
                <span id="totalEnteredAmount">0.00 ج.م</span>
              </div>
              <div id="walletPaymentDetails" style="display: none">
                <div class="d-flex justify-content-between">
                  <span>الرصيد المتاح في المحفظة:</span>
                  <span id="availableWalletBalance">0.00 ج.م</span>
                </div>
                <div class="d-flex justify-content-between">
                  <span>المبلغ المطلوب من المحفظة:</span>
                  <span id="walletPaymentAmount">0.00 ج.م</span>
                </div>
                <div class="d-flex justify-content-between fw-bold">
                  <span>المتبقي في المحفظة بعد السداد:</span>
                  <span id="remainingWalletBalance">0.00 ج.م</span>
                </div>
              </div>
              <div
                class="d-flex justify-content-between fw-bold mt-2 pt-2 border-top">
                <span>المتبقي بعد السداد:</span>
                <span id="totalRemainingAfterPayment">0.00 ج.م</span>
              </div>
              <div
                id="paymentError"
                class="text-danger mt-2"
                style="display: none"></div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button
            type="button"
            class="btn btn-secondary"
            data-bs-dismiss="modal">
            إلغاء
          </button>
          <button
            type="button"
            class="btn btn-primary"
            id="processPaymentBtn"
            disabled>
            معالجة السداد
          </button>
        </div>
      </div>
    </div>
    </div>

    <!-- مودال إيداع المحفظة -->

    <!-- مودال إيداع المحفظة -->
    <div class="modal fade" id="walletDepositModal" tabindex="-1" aria-labelledby="walletDepositModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">

          <div class="modal-header">
            <h5 class="modal-title" id="walletDepositModalLabel">
              إيداع في المحفظة
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>

          <div class="modal-body">
            <form id="walletDepositForm">

              <!-- مبلغ الإيداع -->
              <div class="mb-3">
                <label for="depositAmount" class="form-label">المبلغ</label>
                <input type="number" class="form-control" id="depositAmount" required min="0" step="0.01" />
              </div>

              <!-- التاريخ -->
              <div class="mb-3">
                <label for="depositDate" class="form-label">تاريخ الإيداع</label>
                <input type="date" class="form-control" id="depositDate" required />
              </div>

              <!-- الوقت -->
              <div class="mb-3">
                <label for="depositTime" class="form-label">الوقت</label>
                <input type="time" class="form-control" id="depositTime" required />
              </div>

              <!-- الوصف -->
              <div class="mb-3">
                <label for="depositDescription" class="form-label">الوصف</label>
                <textarea class="form-control" id="depositDescription" rows="2" placeholder="سبب الإيداع..."></textarea>
              </div>

            </form>
          </div>

          <div class="modal-footer">
            <button class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
            <button class="btn btn-success" id="processDepositBtn">تأكيد الإيداع</button>
          </div>

        </div>
      </div>
    </div>
    <!-- مودال سحب المحفظة -->
    <div class="modal fade" id="walletWithdrawModal" tabindex="-1" aria-labelledby="walletWithdrawModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">

          <div class="modal-header">
            <h5 class="modal-title" id="walletWithdrawModalLabel">
              سحب من المحفظة
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>

          <div class="modal-body">
            <form id="walletWithdrawForm">

              <!-- الرصيد المتاح -->
              <div class="mb-3">
                <label class="form-label">الرصيد المتاح للسحب</label>
                <div class="p-3 bg-light border rounded">
                  <strong id="walletAvailableAmount">0.00 ج.م</strong>
                </div>
              </div>

              <!-- مبلغ السحب -->
              <div class="mb-3">
                <label for="withdrawAmount" class="form-label">مبلغ السحب</label>
                <input type="number" class="form-control" id="withdrawAmount" required min="0" step="0.01" />
                <small id="withdrawWarning" class="text-danger" style="display:none;">
                  ⚠ المبلغ أكبر من الرصيد المتاح!
                </small>
              </div>

              <!-- التاريخ -->
              <div class="mb-3">
                <label for="withdrawDate" class="form-label">تاريخ السحب</label>
                <input type="date" class="form-control" id="withdrawDate" required />
              </div>

              <!-- الوقت -->
              <div class="mb-3">
                <label for="withdrawTime" class="form-label">الوقت</label>
                <input type="time" class="form-control" id="withdrawTime" required />
              </div>

              <!-- الوصف -->
              <div class="mb-3">
                <label for="withdrawDescription" class="form-label">وصف العملية (اختياري)</label>
                <textarea class="form-control" id="withdrawDescription" rows="2"></textarea>
              </div>

            </form>
          </div>

          <div class="modal-footer">
            <button class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
            <button class="btn btn-danger" id="confirmWithdrawBtn">تأكيد السحب</button>
          </div>

        </div>
      </div>
    </div>
<div class="modal fade" id="customReturnModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-undo me-2"></i>إنشاء مرتجع مخصص
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- معلومات الفاتورة -->
                <div class="card mb-3">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-file-invoice me-2"></i>معلومات الفاتورة</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="mb-2">
                                    <small class="text-muted">رقم الفاتورة</small>
                                    <div class="fw-bold note-text" id="returnInvoiceNumber">#123</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-2">
                                    <small class="text-muted">تاريخ الفاتورة</small>
                                    <div id="returnInvoiceDate" class="fw-bold note-text">01/01/2024</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-2">
                                    <small class="text-muted">إجمالي الفاتورة</small>
                                    <div class="fw-bold note-text" id="returnInvoiceTotal">0.00 ج.م</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-2">
                                    <small class="text-muted">حالة الدفع</small>
                                    <div id="paymentStatus">
                                        <span class="badge bg-secondary">غير محدد</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-2">
                            <div class="col-md-3">
                                <div class="mb-2">
                                    <small class="text-muted">المدفوع</small>
                                    <div class="text-success fw-bold" id="invoicePaidAmount">0.00 ج.م</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-2">
                                    <small class="text-muted">المتبقي</small>
                                    <div class="text-amber fw-bold note-text" id="invoiceRemainingAmount">0.00 ج.م</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- سبب الإرجاع -->
                <div class="card mb-3">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-comment me-2"></i>سبب الإرجاع</h6>
                    </div>
                    <div class="card-body">
                        <textarea class="form-control" id="customReturnReason" rows="2" 
                                  placeholder="أدخل سبب الإرجاع (اختياري)..."></textarea>
                    </div>
                </div>

                <!-- بنود المرتجع -->
                <div class="card mb-3">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fas fa-list-ul me-2"></i>بنود المرتجع</h6>
                        <div>
                            <button class="btn btn-sm btn-outline-primary me-2" id="returnAllBtn">
                                <i class="fas fa-check-double me-1"></i>إرجاع الكل
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" id="returnPartialBtn">
                                <i class="fas fa-edit me-1"></i>إرجاع جزئي
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="customReturnItemsContainer">
                            <!-- سيتم تعبئته ديناميكياً -->
                        </div>
                    </div>
                </div>

                <!-- ملخص الإرجاع -->
                <div class="card mb-3">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-calculator me-2"></i>ملخص الإرجاع</h6>
                    </div>
                    <div class="card-body">
                        <div id="impactDetails" style="display: none;">
                            <!-- سيتم تعبئته ديناميكياً -->
                        </div>
                        
                        <div id="refundMethodSection" style="display: none;" class="mt-3">
                            <div id="refundOptions">
                                <!-- سيتم تعبئته ديناميكياً -->
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-8">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>مجموع المبلغ المرتجع:</strong>
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <div class="fw-bold text-muted">المبلغ الإجمالي:</div>
                                <div class="fw-bold text-success fs-3" id="customReturnTotalAmount">0.00 ج.م</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-primary" id="processCustomReturnBtn" disabled>
                    <i class="fas fa-check me-2"></i>تأكيد عملية الإرجاع
                </button>
            </div>
        </div>
    </div>
</div>

    <div class="modal fade" id="returnDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-undo me-2"></i>تفاصيل المرتجع
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="returnDetailsContent">
                    <!-- سيتم تعبئته ديناميكياً -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                <button type="button" class="btn btn-primary print-return-btn" id="printReturnBtn">
                    <i class="fas fa-print me-2"></i>طباعة
                </button>
            </div>
        </div>
    </div>
</div>

    <!-- مودال كشف الحساب -->
    <div
      class="modal fade"
      id="statementReportModal"
      tabindex="-1"
      aria-labelledby="statementReportModalLabel"
      aria-hidden="true">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="statementReportModalLabel">
              كشف حساب العميل
            </h5>
            <button
              type="button"
              class="btn-close btn-close-white"
              data-bs-dismiss="modal"
              aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="row mb-3">
              <div class="col-md-6">
                <label for="statementDateFrom" class="form-label">من تاريخ</label>
                <input
                  type="date"
                  class="form-control"
                  id="statementDateFrom" />
              </div>
              <div class="col-md-6">
                <label for="statementDateTo" class="form-label">إلى تاريخ</label>
                <input type="date" class="form-control" id="statementDateTo" />
              </div>
            </div>

            <div class="table-responsive-fixed  custom-table-wrapper">
              <table class="custom-table">
                <thead>
                  <tr>
                  <tr>
                    <th>تاريخ

                      انشاء
                      المعامله</th>
                    <th>تاريخ
                      تسجيل
                      المعامله</th>
                    <th>نوع الحركة</th>
                    <th>الوصف</th>
                    <th>المبلغ</th>
                    <th>المحفظة قبل</th>
                    <th>المحفظة بعد</th>
                    <th>الديون قبل</th>
                    <th>الديون بعد</th>
                    <th>المستخدم</th>
                  </tr>
                </thead>
                <tbody id="statementTableBody"></tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-secondary"
              data-bs-dismiss="modal">
              إغلاق
            </button>
            <button
              type="button"
              class="btn btn-primary"
              id="printStatementBtn">
              طباعة الكشف
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- مودال عرض بنود الفاتورة -->
    <div
      class="modal fade items-preview-modal"
      id="invoiceItemsModal"
      tabindex="-1"
      aria-labelledby="invoiceItemsModalLabel"
      aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="invoiceItemsModalLabel">
              بنود الفاتورة <span id="invoiceItemsNumber"></span>
            </h5>
            <button
              type="button"
              class="btn-close"
              data-bs-dismiss="modal"
              aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="row mb-3">
              <div class="col-md-4">
                <strong>التاريخ:</strong> <span id="invoiceItemsDate"></span>
              </div>
              <div class="col-md-4">
                <strong>الحالة:</strong> <span id="invoiceItemsStatus"></span>
              </div>
              <div class="col-md-4">
                <strong>الشغلانة:</strong>
                <span id="invoiceItemsWorkOrder">-</span>
              </div>
            </div>

            <!-- رابط لعرض المرتجعات -->
            <div id="invoiceReturnsSection" style="display: none" class="mb-3">
              <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                هذه الفاتورة تحتوي على مرتجعات.
                <a href="#" class="alert-link" id="viewInvoiceReturns">عرض المرتجعات</a>
              </div>
            </div>

            <div class="table-responsive-fixed  custom-table-wrapper">
              <table class=" table-bordered custom-table">
                <thead class="center">
                  <tr>
                    <th>الصنف</th>
                    <th>الكمية</th>
                    <th>السعر</th>
                    <th>الإجمالي</th>
                    <th>مرتجع</th>
                  </tr>
                </thead>
                <tbody id="invoiceItemsDetails"></tbody>
              </table>
            </div>

            <div class="row mt-3">
              <div class="col-md-4">
                <strong>الإجمالي:</strong> <span id="invoiceItemsTotal"></span>
              </div>
              <div class="col-md-4">
                <strong>المدفوع:</strong> <span id="invoiceItemsPaid"></span>
              </div>
              <div class="col-md-4">
                <strong>المتبقي:</strong>
                <span id="invoiceItemsRemaining"></span>
              </div>
            </div>
            <div class="row mt-2">
              <div class="col-md-12">
                <strong>الملاحظات:</strong> <span id="invoiceItemsNotes"></span>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-secondary"
              data-bs-dismiss="modal">
              إغلاق
            </button>
            <button
              type="button"
              class="btn btn-primary"
              id="printInvoiceItemsBtn">
              طباعة
            </button>
          </div>
        </div>
      </div>
    </div>
    <!-- إضافة هذا المودال في الصفحة الرئيسية -->



    <!-- مودال الطباعة المتعددة -->
    <div
      class="modal fade"
      id="printMultipleModal"
      tabindex="-1"
      aria-labelledby="printMultipleModalLabel"
      aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="printMultipleModalLabel">
              طباعة متعددة
            </h5>
            <button
              type="button"
              class="btn-close"
              data-bs-dismiss="modal"
              aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">اختر الفواتير للطباعة:</label>
              <div class="form-check">
                <input
                  class="form-check-input"
                  type="checkbox"
                  id="selectAllInvoicesPrint" />
                <label class="form-check-label" for="selectAllInvoicesPrint">
                  تحديد الكل
                </label>
              </div>
              <div
                id="printInvoicesList"
                class="mt-2"
                style="max-height: 300px; overflow-y: auto"></div>
            </div>
          </div>
          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-secondary"
              data-bs-dismiss="modal">
              إلغاء
            </button>
            <button
              type="button"
              class="btn btn-primary"
              id="confirmPrintMultipleBtn">
              طباعة المحدد
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- مودال عرض المرتجعات الخاصة بفاتورة -->
    <div
      class="modal fade invoice-returns-modal"
      id="invoiceReturnsModal"
      tabindex="-1"
      aria-labelledby="invoiceReturnsModalLabel"
      aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="invoiceReturnsModalLabel">
              المرتجعات - الفاتورة <span id="returnsInvoiceNumber"></span>
            </h5>
            <button
              type="button"
              class="btn-close"
              data-bs-dismiss="modal"
              aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <strong>إجمالي المرتجعات:</strong>
              <span id="totalReturnsAmountForInvoice">0.00 ج.م</span>
            </div>

            <div id="invoiceReturnsList"></div>
          </div>
          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-secondary"
              data-bs-dismiss="modal">
              إغلاق
            </button>
            <button
              type="button"
              class="btn btn-primary"
              id="printInvoiceReturnsBtn">
              طباعة المرتجعات
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- قسم الطباعة -->
    <div id="printSection" class="print-section" style="display: none"></div>

  
  
    <script type="module" src="js/init.js"></script>
    </script>
  <?php
  $conn->close();
  require_once BASE_DIR . 'partials/footer.php';
