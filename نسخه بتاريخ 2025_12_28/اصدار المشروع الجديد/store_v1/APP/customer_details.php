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

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>نظام إدارة العملاء والمخزون المتطور</title>
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
      rel="stylesheet"
    />
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"
    />
    <link
      href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="assets/index.css" />
  </head>
  <body >
    <div class="container-fluid py-4">
      <!-- رأس العميل -->
      <div class="customer-header">
        <div class="row align-items-center">
          <div class="col-md-8">
            <div class="d-flex align-items-center">
              <div class="customer-avatar me-4" id="customerAvatar">م</div>
              <div>
                <h1 class="h2 mb-2" id="customerName">محمد أحمد</h1>
                <div class="d-flex flex-wrap gap-4 fs-5">
                  <span
                    ><i class="fas fa-phone me-2"></i>
                    <span id="customerPhone">01234567890</span></span
                  >
                  <span
                    ><i class="fas fa-city me-2"></i>
                    <span id="customerAddress">القاهرة - المعادي</span></span
                  >
                  <span
                    ><i class="fas fa-calendar me-2"></i> عضو منذ
                    <span id="customerJoinDate">2024-01-20</span></span
                  >
                </div>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="row g-3">
              <div class="col-6">
                <div class="stat-card negative">
                  <div class="stat-value" id="currentBalance">1,200.00</div>
                  <div class="stat-label">الرصيد الحالي</div>
                  <small class="text-danger">مدين</small>
                </div>
              </div>
              <div class="col-6">
                <div class="stat-card positive">
                  <div class="stat-value" id="walletBalance">500.00</div>
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
          <div class="stat-value" id="totalInvoicesCount">16</div>
          <div class="stat-label">جميع الفواتير</div>
          <div class="stat-amount text-primary">15,000.00 ج.م</div>
        </div>
        <div class="invoice-stat-card pending" data-filter="pending">
          <div class="stat-value" id="pendingInvoicesCount">3</div>
          <div class="stat-label">مؤجل</div>
          <div class="stat-amount text-warning">2,500.00 ج.م</div>
        </div>
        <div class="invoice-stat-card partial" data-filter="partial">
          <div class="stat-value" id="partialInvoicesCount">2</div>
          <div class="stat-label">جزئي</div>
          <div class="stat-amount text-info">1,200.00 ج.م</div>
        </div>
        <div class="invoice-stat-card paid" data-filter="paid">
          <div class="stat-value" id="paidInvoicesCount">10</div>
          <div class="stat-label">مسلم</div>
          <div class="stat-amount text-success">11,300.00 ج.م</div>
        </div>
        <div class="invoice-stat-card returned" data-filter="returned">
          <div class="stat-value" id="returnedInvoicesCount">1</div>
          <div class="stat-label">مرتجع</div>
          <div class="stat-amount text-danger">800.00 ج.م</div>
        </div>
      </div>

      <!-- أزرار الإجراءات السريعة -->
      <div class="quick-actions mb-4">
        <div class="d-flex gap-3 flex-wrap">
          <button
            class="btn btn-primary"
            data-bs-toggle="modal"
            data-bs-target="#newWorkOrderModal"
          >
            <i class="fas fa-tools me-2"></i> شغلانة جديدة
          </button>
          <button
            class="btn btn-success"
            data-bs-toggle="modal"
            data-bs-target="#paymentModal"
          >
            <i class="fas fa-money-bill-wave me-2"></i> سداد
          </button>
          <button
            class="btn btn-warning"
            data-bs-toggle="modal"
            data-bs-target="#walletDepositModal"
          >
            <i class="fas fa-wallet me-2"></i> إيداع محفظة
          </button>
          <button
            class="btn btn-outline-primary"
            data-bs-toggle="modal"
            data-bs-target="#statementReportModal"
          >
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
              <div class="col-md-3">
                <label for="productSearch" class="form-label">بحث بالصنف</label>
                <input
                  type="text"
                  class="form-control"
                  id="productSearch"
                  placeholder="اكتب اسم الصنف..."
                />
              </div>

              <div class="col-12 mb-3">
                <label for="advancedProductSearch" class="form-label"
                  >بحث متقدم عن صنف</label
                >
                <input
                  type="text"
                  class="form-control"
                  id="advancedProductSearch"
                  placeholder="اكتب اسم الصنف للبحث في جميع الفواتير..."
                />
                <small class="text-muted"
                  >سيتم تمييز النص المطابق باللون الأصفر وعرض الفاتورة في الجانب
                  الأيمن</small
                >
              </div>

              <div
                id="advancedSearchResults"
                class="product-search-results"
                style="display: none"
              ></div>
              <div class="col-md-3">
                <label for="invoiceTypeFilter" class="form-label"
                  >نوع الفاتورة</label
                >
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
                role="tab"
              >
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
                role="tab"
              >
                <i class="fas fa-tools me-2"></i> الشغلانات
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button
                class="nav-link"
                id="wallet-tab"
                data-bs-toggle="tab"
                data-bs-target="#wallet"
                type="button"
                role="tab"
              >
                <i class="fas fa-wallet me-2"></i> حركات المحفظة
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button
                class="nav-link"
                id="returns-tab"
                data-bs-toggle="tab"
                data-bs-target="#returns"
                type="button"
                role="tab"
              >
                <i class="fas fa-undo me-2"></i> المرتجعات
              </button>
            </li>
          </ul>

          <div class="tab-content p-4" id="customerTabsContent">
            <!-- تبويب الفواتير -->
            <div
              class="tab-pane fade show active"
              id="invoices"
              role="tabpanel"
            >
              <div class="table-responsive-fixed">
                <div
                  class="mb-3 d-flex justify-content-between align-items-center"
                >
                  <div>
                    <input
                      type="checkbox"
                      class="form-check-input"
                      id="selectAllInvoices"
                    />
                    <label class="form-check-label ms-2" for="selectAllInvoices"
                      >تحديد الكل</label
                    >
                  </div>
                  <button
                    class="btn btn-primary btn-sm"
                    id="printSelectedInvoices"
                    disabled
                  >
                    <i class="fas fa-print me-2"></i>طباعة المحدد
                  </button>
                </div>
                <table class="table table-hover">
                  <thead>
                    <tr>
                      <th style="width: 40px">
                        <input
                          type="checkbox"
                          class="form-check-input"
                          id="selectAllInvoicesHeader"
                        />
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
                  <tbody id="walletTableBody"></tbody>
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
                  <tbody id="returnsTableBody"></tbody>
                </table>
              </div>
            </div>
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
      aria-hidden="true"
    >
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
              aria-label="Close"
            ></button>
          </div>
          <div class="modal-body">
            <form id="newWorkOrderForm">
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label for="workOrderName" class="form-label"
                    >اسم الشغلانة</label
                  >
                  <input
                    type="text"
                    class="form-control"
                    id="workOrderName"
                    required
                  />
                </div>
                <div class="col-md-6 mb-3">
                  <label for="workOrderStartDate" class="form-label"
                    >تاريخ البدء</label
                  >
                  <input
                    type="date"
                    class="form-control"
                    id="workOrderStartDate"
                    required
                  />
                </div>
              </div>
              <div class="row">
                <div class="col-12 mb-3">
                  <label for="workOrderDescription" class="form-label"
                    >وصف الشغلانة</label
                  >
                  <textarea
                    class="form-control"
                    id="workOrderDescription"
                    rows="3"
                    required
                  ></textarea>
                </div>
              </div>
              <div class="row">
                <div class="col-12 mb-3">
                  <label for="workOrderNotes" class="form-label"
                    >ملاحظات إضافية</label
                  >
                  <textarea
                    class="form-control"
                    id="workOrderNotes"
                    rows="2"
                  ></textarea>
                </div>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-secondary"
              data-bs-dismiss="modal"
            >
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
      aria-hidden="true"
    >
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="paymentModalLabel">سداد مديونية</h5>
            <button
              type="button"
              class="btn-close btn-close-white"
              data-bs-dismiss="modal"
              aria-label="Close"
            ></button>
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
                  checked
                />
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
                  value="workOrder"
                />
                <label class="form-check-label" for="payWorkOrderRadio">
                  سداد لشغلانة محددة
                </label>
              </div>
            </div>

            <!-- قسم سداد الفواتير -->
            <div id="invoicesPaymentSection">
              <div class="mb-3">
                <label for="invoiceSearch" class="form-label"
                  >بحث في الفواتير (اختياري)</label
                >
                <input
                  type="text"
                  class="form-control"
                  id="invoiceSearch"
                  placeholder="ابحث برقم الفاتورة..."
                />
              </div>
              <div class="table-responsive-fixed">
                <!-- في تبويب الفواتير، قبل الجدول مباشرة -->
                <div
                  class="mb-3 d-flex justify-content-between align-items-center"
                >
                  <div class="d-flex gap-2">
                    <button
                      class="btn btn-sm btn-outline-primary"
                      id="selectAllInvoicesBtn"
                    >
                      <i class="fas fa-check-double me-1"></i> تحديد الكل
                    </button>
                    <button
                      class="btn btn-sm btn-outline-secondary"
                      id="selectNonWorkOrderBtn"
                    >
                      <i class="fas fa-file-invoice me-1"></i> تحديد غير
                      المرتبطة
                    </button>
                  </div>
                </div>
                <table class="table table-sm">
                  <thead>
                    <tr>
                      <th width="40">
                        <input
                          type="checkbox"
                          class="form-check-input"
                          id="selectAllInvoicesForPayment"
                        />
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
                  <label for="invoicesTotalAmount" class="form-label"
                    >المبلغ الكلي المراد سداده</label
                  >
                  <div class="input-group">
                    <input
                      readonly
                      disabled
                      type="number"
                      class="form-control"
                      id="invoicesTotalAmount"
                      min="0"
                      placeholder="المبلغ الكلي"
                    />
                    <span class="input-group-text">ج.م</span>
                  </div>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                  <button
                    type="button"
                    class="btn btn-outline-secondary w-100"
                    id="fillInvoicesRemainingBtn"
                  >
                    <i class="fas fa-fill me-1"></i> سدّد المتبقي بالكامل
                  </button>
                </div>
              </div>
            </div>

            <!-- قسم سداد الشغلانة -->
            <div id="workOrderPaymentSection" style="display: none">
              <div class="mb-3">
                <label for="workOrderSearch" class="form-label"
                  >بحث في الشغلانات</label
                >
                <input
                  type="text"
                  class="form-control"
                  id="workOrderSearch"
                  placeholder="اكتب اسم الشغلانة للبحث..."
                />
                <div
                  id="workOrderSearchResults"
                  class="product-search-results"
                  style="display: none"
                ></div>
              </div>

              <div id="selectedWorkOrderDetails" style="display: none">
                <h6>تفاصيل الشغلانة المحددة</h6>
                <div class="card mb-3">
                  <div class="card-body">
                    <h5 id="selectedWorkOrderName"></h5>
                    <p id="selectedWorkOrderDescription" class="text-muted"></p>
                    <div class="row">
                      <div class="col-4">
                        <small class="text-muted">تاريخ البدء</small>
                        <div id="selectedWorkOrderStartDate"></div>
                      </div>
                      <div class="col-4">
                        <small class="text-muted">حالة الشغلانة</small>
                        <div id="selectedWorkOrderStatus"></div>
                      </div>
                      <div class="col-4">
                        <small class="text-muted">عدد الفواتير</small>
                        <div id="selectedWorkOrderInvoicesCount"></div>
                      </div>
                    </div>
                  </div>
                </div>

                <h6>فواتير الشغلانة</h6>
                <div class="table-responsive-fixed">
                  <table class="table table-sm">
                    <thead>
                      <tr>
                        <th>#</th>
                        <th>التاريخ</th>
                        <th>الإجمالي</th>
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
                  <label for="invoicesTotalAmountWorkOrder" class="form-label"
                    >المبلغ الكلي المراد سداده</label
                  >
                  <div class="input-group">
                    <input
                      disapbled
                      readonly
                      type="number"
                      class="form-control"
                      id="invoicesTotalAmountWorkOrder"
                      min="0"
                      placeholder="المبلغ الكلي"
                    />
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
              <div class="mt-4 border p-3 rounded bg-light">
                <div
                  class="d-flex justify-content-between align-items-center mb-2"
                >
                  <h6 class="mb-0">تحقق المدفوعات</h6>
                  <button
                    type="button"
                    class="btn btn-success btn-sm"
                    id="autoDistributeBtn"
                  >
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
                        readonly
                        style="background-color: #e9ecef"
                      />
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
                        readonly
                        style="background-color: #e9ecef"
                      />
                      <span class="input-group-text">ج.م</span>
                    </div>
                  </div>
                </div>

                <div id="paymentValidation" class="text-center mt-2">
                  <div
                    id="paymentValid"
                    class="alert alert-success py-2 mb-0"
                    style="display: none"
                  >
                    <i class="fas fa-check-circle me-1"></i> المبلغ المدخل يساوي
                    المطلوب
                  </div>
                  <div
                    id="paymentInvalid"
                    class="alert alert-danger py-2 mb-0"
                    style="display: none"
                  >
                    <i class="fas fa-times-circle me-1"></i> المبلغ المدخل لا
                    يساوي المطلوب
                  </div>
                  <div
                    id="paymentExceeds"
                    class="alert alert-warning py-2 mb-0"
                    style="display: none"
                  >
                    <i class="fas fa-exclamation-triangle me-1"></i> المبلغ
                    المدخل يتجاوز المطلوب
                  </div>
                </div>
              </div>
              <button
                type="button"
                class="btn btn-outline-primary btn-sm mt-2"
                id="addPaymentMethodBtn"
              >
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
                  class="d-flex justify-content-between fw-bold mt-2 pt-2 border-top"
                >
                  <span>المتبقي بعد السداد:</span>
                  <span id="totalRemainingAfterPayment">0.00 ج.م</span>
                </div>
                <div
                  id="paymentError"
                  class="text-danger mt-2"
                  style="display: none"
                ></div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-secondary"
              data-bs-dismiss="modal"
            >
              إلغاء
            </button>
            <button
              type="button"
              class="btn btn-primary"
              id="processPaymentBtn"
              disabled
            >
              معالجة السداد
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- مودال إيداع المحفظة -->
    <div
      class="modal fade"
      id="walletDepositModal"
      tabindex="-1"
      aria-labelledby="walletDepositModalLabel"
      aria-hidden="true"
    >
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="walletDepositModalLabel">
              إيداع مبلغ في المحفظة
            </h5>
            <button
              type="button"
              class="btn-close btn-close-white"
              data-bs-dismiss="modal"
              aria-label="Close"
            ></button>
          </div>
          <div class="modal-body">
            <form id="walletDepositForm">
              <div class="mb-3">
                <label for="depositAmount" class="form-label"
                  >المبلغ المودع</label
                >
                <input
                  type="number"
                  class="form-control"
                  id="depositAmount"
                  required
                />
              </div>
              <div class="mb-3">
                <label for="depositDescription" class="form-label"
                  >وصف الإيداع</label
                >
                <textarea
                  class="form-control"
                  id="depositDescription"
                  rows="3"
                ></textarea>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-secondary"
              data-bs-dismiss="modal"
            >
              إلغاء
            </button>
            <button
              type="button"
              class="btn btn-primary"
              id="processDepositBtn"
            >
              معالجة الإيداع
            </button>
          </div>
        </div>
      </div>
    </div>

<div class="modal fade" id="customReturnModal" tabindex="-1" aria-labelledby="customReturnModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="customReturnModalLabel">
                    <i class="fas fa-undo me-2"></i> إدارة مرتجع الفاتورة
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- معلومات الفاتورة -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-file-invoice me-2"></i>معلومات الفاتورة</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="mb-2">
                                    <strong>رقم الفاتورة:</strong>
                                    <div class="fw-bold text-primary" id="returnInvoiceNumber">-</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-2">
                                    <strong>التاريخ:</strong>
                                    <div id="returnInvoiceDate">-</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-2">
                                    <strong>الإجمالي:</strong>
                                    <div class="fw-bold" id="returnInvoiceTotal">-</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-2">
                                    <strong>طريقة الدفع:</strong>
                                    <div id="originalPaymentMethod">-</div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-2">
                                    <strong>حالة الدفع:</strong>
                                    <div id="paymentStatus">-</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-2">
                                    <strong>المبلغ المدفوع:</strong>
                                    <div class="text-success" id="invoicePaidAmount">-</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-2">
                                    <strong>المبلغ المتبقي:</strong>
                                    <div class="text-warning" id="invoiceRemainingAmount">-</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- بنود الفاتورة للإرجاع -->
                <div class="card mb-4">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fas fa-boxes me-2"></i>بنود الفاتورة للإرجاع</h6>
                        <div>
                            <button type="button" class="btn btn-sm btn-warning me-2" id="returnAllBtn">
                                <i class="fas fa-undo me-1"></i> إرجاع كلي
                            </button>
                            <button type="button" class="btn btn-sm btn-info" id="returnPartialBtn">
                                <i class="fas fa-undo-alt me-1"></i> إرجاع جزئي
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="customReturnItemsContainer">
                            <!-- سيتم تعبئة البنود هنا -->
                        </div>
                    </div>
                </div>

                <!-- ملخص المرتجع -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-calculator me-2"></i>ملخص المرتجع</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="alert alert-primary">
                                    <strong>المبلغ الإجمالي للإرجاع:</strong>
                                    <h4 class="mt-2" id="customReturnTotalAmount">0.00 ج.م</h4>
                                </div>
                            </div>
                            <!-- <div class="col-md-4">
                                <div class="alert alert-warning">
                                    <strong>يخصم من المتبقي:</strong>
                                    <h4 class="mt-2" id="deductFromRemaining">0.00 ج.م</h4>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="alert alert-info">
                                    <strong>يُرد للعميل:</strong>
                                    <h4 class="mt-2" id="refundToCustomer">0.00 ج.م</h4>
                                </div>
                            </div> -->
                        </div>
                        
                        <!-- حاوية تفاصيل التأثير -->
                        <div id="impactDetails" style="display: none;"></div>
                        
                        <!-- اختيار طريقة الاسترداد -->
                        <div class="row mt-3" id="refundMethodSection" style="display: none;">
                            <div class="col-md-12">
                                <div class="border p-3 rounded bg-light">
                                    <h6 class="mb-3"><i class="fas fa-money-bill-wave me-2"></i>اختيار طريقة استرداد المبلغ</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label for="refundMethod" class="form-label fw-bold">طريقة الاسترداد:</label>
                                            <select class="form-select" id="refundMethod" required>
                                                <option value="cash">استرجاع نقدي</option>
                                                <option value="wallet">إضافة للمحفظة</option>
                                            </select>
                                            <div class="form-text mt-2">
                                                <i class="fas fa-info-circle me-1"></i>
                                                اختر طريقة استرداد المبلغ للعميل في حالة وجود مبالغ مدفوعة سابقاً
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="alert alert-warning">
                                                <i class="fas fa-exclamation-triangle me-2"></i>
                                                <strong>ملاحظة:</strong>
                                                <div class="mt-1">
                                                    في حالة الفواتير الآجلة، يتم خصم من المتبقي أولاً
                                                    وإذا تجاوز المرتجع المتبقي يتم إرجاع الفرق للعميل
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- سبب الإرجاع -->
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-comment me-2"></i>سبب الإرجاع</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="customReturnReason" class="form-label fw-bold">
                                <i class="fas fa-edit me-1"></i>أدخل سبب الإرجاع
                            </label>
                            <textarea class="form-control" id="customReturnReason" rows="3" 
                                      placeholder="أدخل سبب الإرجاع هنا..." required></textarea>
                            <div class="form-text">مطلوب لتوثيق عملية الإرجاع</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- عنصر مخفي لتخزين البيانات -->
            <input type="hidden" id="impactData">
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> إلغاء
                </button>
                <button type="button" class="btn btn-primary" id="processCustomReturnBtn" disabled>
                    <i class="fas fa-check me-1"></i> معالجة الإرجاع
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
      aria-hidden="true"
    >
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="statementReportModalLabel">
              كشف حساب العميل
            </h5>
            <button
              type="button"
              class="btn-close btn-close-white"
              data-bs-dismiss="modal"
              aria-label="Close"
            ></button>
          </div>
          <div class="modal-body">
            <div class="row mb-3">
              <div class="col-md-6">
                <label for="statementDateFrom" class="form-label"
                  >من تاريخ</label
                >
                <input
                  type="date"
                  class="form-control"
                  id="statementDateFrom"
                />
              </div>
              <div class="col-md-6">
                <label for="statementDateTo" class="form-label"
                  >إلى تاريخ</label
                >
                <input type="date" class="form-control" id="statementDateTo" />
              </div>
            </div>

            <div class="table-responsive-fixed">
              <table class="table table-hover">
                <thead>
                  <tr>
                    <th>التاريخ</th>
                    <th>نوع الحركة</th>
                    <th>الوصف</th>
                    <th>المبلغ</th>
                    <th>الرصيد</th>
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
              data-bs-dismiss="modal"
            >
              إغلاق
            </button>
            <button
              type="button"
              class="btn btn-primary"
              id="printStatementBtn"
            >
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
      aria-hidden="true"
    >
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
              aria-label="Close"
            ></button>
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
                <a href="#" class="alert-link" id="viewInvoiceReturns"
                  >عرض المرتجعات</a
                >
              </div>
            </div>

            <div class="table-responsive-fixed">
              <table class="table table-bordered">
                <thead>
                  <tr>
                    <th>الصنف</th>
                    <th>الكمية</th>
                    <th>السعر</th>
                    <th>الإجمالي</th>
                    <th>مرتجع</th>
                    <th>الحالة</th>
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
              data-bs-dismiss="modal"
            >
              إغلاق
            </button>
            <button
              type="button"
              class="btn btn-primary"
              id="printInvoiceItemsBtn"
            >
              طباعة
            </button>
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
      aria-hidden="true"
    >
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
              aria-label="Close"
            ></button>
          </div>
          <div class="modal-body">
            <div class="row mb-4">
              <div class="col-md-4">
                <div class="card bg-light">
                  <div class="card-body text-center">
                    <h6>إجمالي الفواتير</h6>
                    <h4 id="workOrderTotalInvoices">0.00 ج.م</h4>
                  </div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="card bg-light">
                  <div class="card-body text-center">
                    <h6>المدفوع</h6>
                    <h4 id="workOrderTotalPaid">0.00 ج.م</h4>
                  </div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="card bg-light">
                  <div class="card-body text-center">
                    <h6>المتبقي</h6>
                    <h4 id="workOrderTotalRemaining">0.00 ج.م</h4>
                  </div>
                </div>
              </div>
            </div>

            <div class="table-responsive-fixed">
              <table class="table table-hover">
                <thead>
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
              data-bs-dismiss="modal"
            >
              إغلاق
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- مودال الطباعة المتعددة -->
    <div
      class="modal fade"
      id="printMultipleModal"
      tabindex="-1"
      aria-labelledby="printMultipleModalLabel"
      aria-hidden="true"
    >
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
              aria-label="Close"
            ></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">اختر الفواتير للطباعة:</label>
              <div class="form-check">
                <input
                  class="form-check-input"
                  type="checkbox"
                  id="selectAllInvoicesPrint"
                />
                <label class="form-check-label" for="selectAllInvoicesPrint">
                  تحديد الكل
                </label>
              </div>
              <div
                id="printInvoicesList"
                class="mt-2"
                style="max-height: 300px; overflow-y: auto"
              ></div>
            </div>
          </div>
          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-secondary"
              data-bs-dismiss="modal"
            >
              إلغاء
            </button>
            <button
              type="button"
              class="btn btn-primary"
              id="confirmPrintMultipleBtn"
            >
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
      aria-hidden="true"
    >
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
              aria-label="Close"
            ></button>
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
              data-bs-dismiss="modal"
            >
              إغلاق
            </button>
            <button
              type="button"
              class="btn btn-primary"
              id="printInvoiceReturnsBtn"
            >
              طباعة المرتجعات
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- قسم الطباعة -->
    <div id="printSection" class="print-section" style="display: none"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>
    
    <script type="module">
      import PaymentManager from "./js/payment.js";
      import AppData from "./js/app_data.js";
      import WalletManager from "./js/wallet.js";
      import CustomerManager from "./js/customer.js";
      import PrintManager from "./js/print.js";
      import { setupNumberInputPrevention,escapeHtml} from "./js/helper.js";
      import {  ReturnManager, CustomReturnManager} from "./js/return.js";
      import InvoiceManager from "./js/invoices.js";
     import { updateInvoiceStats } from "./js/helper.js";

      // ============================================
      // بيانات التطبيق
      // ============================================

      // طرق الدفع المتاحة

      // ============================================
      // إدارة التطبيق الرئيسية
      // ============================================
      document.addEventListener("DOMContentLoaded",async function () {
        setupNumberInputPrevention();
     await   initializeApp();
        setupEventListeners();
      });

    async  function initializeApp() {
        // تهيئة البيانات
        
     await   CustomerManager.init();

        
        
        // InvoiceManager.init();
        // WorkOrderManager.init();
        // WalletManager.init();
        // ReturnManager.init();
        // PaymentManager.init();
        // UIManager.init();

        // تحديث الإحصائيات
        updateInvoiceStats();
      }

      function setupEventListeners() {
        // إحصائيات الفواتير
        document.querySelectorAll(".invoice-stat-card").forEach((card) => {
          card.addEventListener("click", function () {
            // إزالة النشط من جميع الكروت
            document.querySelectorAll(".invoice-stat-card").forEach((c) => {
              c.classList.remove("active");
            });

            // إضافة النشط للكارت المختار
            this.classList.add("active");

            const filter = this.getAttribute("data-filter");
            AppData.activeFilters.invoiceType =
              filter === "all" ? null : filter;
            UIManager.applyFilters();
          });
        });

        // زر الطباعة المتعددة
        document
          .getElementById("printMultipleBtn")
          .addEventListener("click", function () {
            PrintManager.openPrintMultipleModal();
          });

        // زر تأكيد الطباعة المتعددة
        document
          .getElementById("confirmPrintMultipleBtn")
          .addEventListener("click", function () {
            PrintManager.printMultipleInvoices();
          });

        // تحديد/إلغاء تحديد جميع الفواتير للطباعة
        document
          .getElementById("selectAllInvoicesPrint")
          .addEventListener("change", function () {
            const checkboxes = document.querySelectorAll(
              ".print-invoice-checkbox"
            );
            checkboxes.forEach((checkbox) => {
              checkbox.checked = this.checked;
            });
          });
        // البحث في المرتجع المتقدم
        document
          .getElementById("advancedProductSearch")
          .addEventListener("input", (e) => {


            const searchTerm = e.target.value;
            const results = ReturnManager.searchProductsInInvoices(searchTerm);
            this.displayAdvancedSearchResults(results);
          });
      }
  
      const WorkOrderManager = {
        init() {
          // بيانات الشغلانات الابتدائية
          AppData.workOrders = [
            {
              id: 1,
              name: "تركيب شباك المعادي",
              description: "تركيب شباك ألوميتال مقاس 2×1.5 في فيلا المعادي",
              status: "pending",
              startDate: "2024-01-20",
              notes: "يجب الانتهاء قبل نهاية الشهر",
              invoices: [123, 124, 125],
              createdBy: "مدير النظام",
            },

            {
              id: 2,
              name: "تصليح باب خشب",
              description: "تصليح باب خشب وتغيير مفصلات في شقة الزمالك",
              status: "completed",
              startDate: "2024-01-15",
              notes: "تم التسليم والعميل راضي",
              invoices: [123, 124],
              createdBy: "مدير النظام",
            },
          ];

          this.updateWorkOrdersTable();
        },

        updateWorkOrdersTable() {
          const container = document.getElementById("workOrdersContainer");
          container.innerHTML = "";

          AppData.workOrders.forEach((workOrder) => {
            const workOrderCard = document.createElement("div");
            workOrderCard.className = "col-md-6 mb-3";

            // الحصول على الفواتير المرتبطة
            const relatedInvoices = AppData.invoices.filter((inv) =>
              workOrder.invoices.includes(inv.id)
            );

            const totalInvoices = relatedInvoices.reduce(
              (sum, inv) => sum + inv.total,
              0
            );
            const totalPaid = relatedInvoices.reduce(
              (sum, inv) => sum + inv.paid,
              0
            );
            const totalRemaining = totalInvoices - totalPaid;
            const progressPercent =
              totalInvoices > 0 ? (totalPaid / totalInvoices) * 100 : 0;

            // تحديد حالة الشغلانة
            let statusBadge = "";
            if (workOrder.status === "pending") {
              statusBadge =
                '<span class="status-badge badge-pending">  قيد التنفيذ</span>';
            } else if (workOrder.status === "in_progress") {
              statusBadge =
                '<span class="status-badge badge-partial">جاري العمل</span>';
            } else if (workOrder.status === "completed") {
              statusBadge =
                '<span class="status-badge badge-paid">مكتمل</span>';
            }

            // في WorkOrderManager.updateWorkOrdersTable():
            workOrderCard.innerHTML = `
    <div class="work-order-card">
        <div class="d-flex justify-content-between align-items-start mb-2">
            <h5>${workOrder.name}</h5>
            ${statusBadge}
        </div>
        <p class="text-muted">${workOrder.description}</p>
        <div class="row mb-2">
            <div class="col-6">
                <small class="text-muted">تاريخ البدء:</small>
                <div>${workOrder.startDate}</div>
            </div>
            <div class="col-6">
                <small class="text-muted">الفواتير:</small>
                <div>${relatedInvoices.length} فاتورة</div>
            </div>
        </div>
        
        <!-- شريط التقدم -->
        <div class="work-order-progress bg-light">
            <div class="progress-bar bg-success" style="width: ${progressPercent}%"></div>
        </div>
        
        <div class="row text-center">
            <div class="col-4">
                <small class="text-muted">المطلوب</small>
                <div class="fw-bold">${totalInvoices.toFixed(2)} ج.م</div>
            </div>
            <div class="col-4">
                <small class="text-muted">المدفوع</small>
                <div class="fw-bold text-success">${totalPaid.toFixed(
                  2
                )} ج.م</div>
            </div>
            <div class="col-4">
                <small class="text-muted">المتبقي</small>
                <div class="fw-bold text-danger">${totalRemaining.toFixed(
                  2
                )} ج.م</div>
            </div>
        </div>
        
        <div class="action-buttons mt-3">
            <button class="btn btn-sm btn-outline-info view-work-order" data-work-order-id="${
              workOrder.id
            }">
                <i class="fas fa-eye"></i> عرض
            </button>
            ${
              totalRemaining > 0
                ? `
            <button class="btn btn-sm btn-outline-success pay-work-order" data-work-order-id="${workOrder.id}">
                <i class="fas fa-money-bill-wave"></i> سداد
            </button>
            `
                : ""
            }
            <button class="btn btn-sm btn-outline-primary print-work-order" data-work-order-id="${
              workOrder.id
            }">
                <i class="fas fa-print"></i> طباعة
            </button>
        </div>
    </div>
`;
            container.appendChild(workOrderCard);
          });

          // إضافة مستمعي الأحداث
          this.attachWorkOrderEventListeners();
        },

        // في WorkOrderManager.attachWorkOrderEventListeners():
        attachWorkOrderEventListeners() {
          // زر عرض الشغلانة
          document.querySelectorAll(".view-work-order").forEach((btn) => {
            btn.addEventListener("click", function () {
              const workOrderId = parseInt(
                this.getAttribute("data-work-order-id")
              );
              WorkOrderManager.showWorkOrderDetails(workOrderId);
            });
          });

          // زر سداد الشغلانة
          document.querySelectorAll(".pay-work-order").forEach((btn) => {
            btn.addEventListener("click", function () {
              const workOrderId = parseInt(
                this.getAttribute("data-work-order-id")
              );

              // تعيين نوع السداد إلى شغلانة
              document.getElementById("payWorkOrderRadio").checked = true;
              document.getElementById("invoicesPaymentSection").style.display =
                "none";
              document.getElementById("workOrderPaymentSection").style.display =
                "block";

              // تحديد الشغلانة
              PaymentManager.selectWorkOrderForPayment(workOrderId);
              document.getElementById("workOrderSearch").value = "";

              // فتح المودال
              const paymentModal = new bootstrap.Modal(
                document.getElementById("paymentModal")
              );
              paymentModal.show();
            });
          });

          // زر طباعة الشغلانة
          // في WorkOrderManager.attachWorkOrderEventListeners():
          document.querySelectorAll(".print-work-order").forEach((btn) => {
            btn.addEventListener("click", function (e) {
              e.preventDefault();
              e.stopPropagation();
              const workOrderId = parseInt(
                this.getAttribute("data-work-order-id")
              );
              PrintManager.printWorkOrderInvoices(workOrderId);
            });
          });
          // زر تعديل الشغلانة
          document.querySelectorAll(".edit-work-order").forEach((btn) => {
            btn.addEventListener("click", function () {
              const workOrderId = parseInt(
                this.getAttribute("data-work-order-id")
              );
              // يمكنك إضافة وظيفة التعديل هنا
              Swal.fire("معلومة", "وظيفة التعديل قيد التطوير", "info");
            });
          });
        },

        showWorkOrderInvoices(workOrderId) {
          const workOrder = AppData.workOrders.find(
            (wo) => wo.id === workOrderId
          );
          if (!workOrder) return;

          const relatedInvoices = AppData.invoices.filter((inv) =>
            workOrder.invoices.includes(inv.id)
          );

          const totalInvoices = relatedInvoices.reduce(
            (sum, inv) => sum + inv.total,
            0
          );
          const totalPaid = relatedInvoices.reduce(
            (sum, inv) => sum + inv.paid,
            0
          );
          const totalRemaining = totalInvoices - totalPaid;

          document.getElementById("workOrderInvoicesName").textContent =
            workOrder.name;
          document.getElementById("workOrderTotalInvoices").textContent =
            totalInvoices.toFixed(2) + " ج.م";
          document.getElementById("workOrderTotalPaid").textContent =
            totalPaid.toFixed(2) + " ج.م";
          document.getElementById("workOrderTotalRemaining").textContent =
            totalRemaining.toFixed(2) + " ج.م";

          const tbody = document.getElementById("workOrderInvoicesList");
          tbody.innerHTML = "";

          relatedInvoices.forEach((invoice) => {
            const row = document.createElement("tr");
            let statusBadge = "";
            if (invoice.status === "pending") {
              statusBadge =
                '<span class="status-badge badge-pending">مؤجل</span>';
            } else if (invoice.status === "partial") {
              statusBadge =
                '<span class="status-badge badge-partial">جزئي</span>';
            } else if (invoice.status === "paid") {
              statusBadge = '<span class="status-badge badge-paid">مسلم</span>';
            }

            // إنشاء tooltip للبنود
            let itemsTooltip = "";
            if (invoice.items && invoice.items.length > 0) {
              const itemsList = invoice.items
                .map((item) => {
                  const itemTotal = (item.quantity || 0) * (item.price || 0);
                  return `
                                <div class="tooltip-item">
                                    <div>
                                        <div class="tooltip-item-name">${
                                          item.productName || "منتج"
                                        }</div>
                                        <div class="tooltip-item-details">الكمية: ${
                                          item.quantity || 0
                                        } | السعر: ${(item.price || 0).toFixed(
                    2
                  )} ج.م</div>
                                    </div>
                                    <div class="fw-bold">${itemTotal.toFixed(
                                      2
                                    )} ج.م</div>
                                </div>
                            `;
                })
                .join("");

              itemsTooltip = `
                            <div class="invoice-items-tooltip">
                                <div class="tooltip-header">بنود الفاتورة ${
                                  invoice.number
                                }</div>
                                ${itemsList}
                                <div class="tooltip-total">
                                    <span>الإجمالي:</span>
                                    <span>${invoice.total.toFixed(2)} ج.م</span>
                                </div>
                            </div>
                        `;
            }

            row.innerHTML = `
  
                        <td class="position-relative">
                            ${invoice.number}
                            ${itemsTooltip}
                            
                        </td>
                        <td>${invoice.date}</td>
                        <td>${invoice.total.toFixed(2)} ج.م</td>
                        <td>${invoice.paid.toFixed(2)} ج.م</td>
                        <td>${invoice.remaining.toFixed(2)} ج.م</td>
                        <td>${statusBadge}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-info view-work-order-invoice" data-invoice-id="${
                              invoice.id
                            }">
                                <i class="fas fa-eye"></i>
                            </button>
                        </td>
                    `;
            tbody.appendChild(row);
          });

          // إضافة مستمع للأزرار
          document
            .querySelectorAll(".view-work-order-invoice")
            .forEach((btn) => {
              btn.addEventListener("click", function () {
                const invoiceId = parseInt(
                  this.getAttribute("data-invoice-id")
                );
                InvoiceManager.showInvoiceDetails(invoiceId);
              });
            });

          const modal = new bootstrap.Modal(
            document.getElementById("workOrderInvoicesModal")
          );
          modal.show();
        },

        getWorkOrderInvoices(workOrderId) {
          const workOrder = AppData.workOrders.find(
            (wo) => wo.id === workOrderId
          );
          if (!workOrder) return [];

          return AppData.invoices.filter((invoice) =>
            workOrder.invoices.includes(invoice.id)
          );
        },

        // في WorkOrderManager، أضف هذه الدالة:
        showWorkOrderDetails(workOrderId) {
          const workOrder = AppData.workOrders.find(
            (wo) => wo.id === workOrderId
          );
          if (!workOrder) return;

          const relatedInvoices = AppData.invoices.filter((inv) =>
            workOrder.invoices.includes(inv.id)
          );

          const totalInvoices = relatedInvoices.reduce(
            (sum, inv) => sum + inv.total,
            0
          );
          const totalPaid = relatedInvoices.reduce(
            (sum, inv) => sum + inv.paid,
            0
          );
          const totalRemaining = totalInvoices - totalPaid;

          // تحديث البيانات في المودال
          document.getElementById("workOrderInvoicesName").textContent =
            workOrder.name;
          document.getElementById("workOrderTotalInvoices").textContent =
            totalInvoices.toFixed(2) + " ج.م";
          document.getElementById("workOrderTotalPaid").textContent =
            totalPaid.toFixed(2) + " ج.م";
          document.getElementById("workOrderTotalRemaining").textContent =
            totalRemaining.toFixed(2) + " ج.م";

          // ملء جدول الفواتير
          const tbody = document.getElementById("workOrderInvoicesList");
          tbody.innerHTML = "";

          relatedInvoices.forEach((invoice) => {
            const row = document.createElement("tr");

            // نسخ نفس بنية الصف من جدول الفواتير الرئيسي
            let statusBadge = "";
            if (invoice.status === "pending") {
              statusBadge =
                '<span class="status-badge badge-pending">مؤجل</span>';
            } else if (invoice.status === "partial") {
              statusBadge =
                '<span class="status-badge badge-partial">جزئي</span>';
            } else if (invoice.status === "paid") {
              statusBadge = '<span class="status-badge badge-paid">مسلم</span>';
            } else if (invoice.status === "returned") {
              statusBadge =
                '<span class="status-badge badge-returned">مرتجع</span>';
            }

            // إنشاء tooltip للبنود (مهم جداً)
            let itemsTooltip = "";
            if (invoice.items && invoice.items.length > 0) {
              const itemsList = invoice.items
                .map((item) => {
                  const returnedText =
                    item.returnedQuantity > 0
                      ? ` (مرتجع: ${item.returnedQuantity})`
                      : "";
                  const itemTotal = (item.quantity || 0) * (item.price || 0);
                  return `
                    <div class="tooltip-item">
                        <div>
                            <div class="tooltip-item-name">${
                              item.productName || "منتج"
                            }</div>
                            <div class="tooltip-item-details">الكمية: ${
                              item.quantity || 0
                            } | السعر: ${(item.price || 0).toFixed(
                    2
                  )} ج.م${returnedText}</div>
                        </div>
                        <div class="fw-bold">${itemTotal.toFixed(2)} ج.م</div>
                    </div>
                `;
                })
                .join("");

              itemsTooltip = `
                <div class="invoice-items-tooltip tooltip-item" >
                    <div class="tooltip-header" style="font-weight: bold; border-bottom: 1px solid #dee2e6; padding-bottom: 5px; margin-bottom: 10px;">بنود الفاتورة ${
                      invoice.number
                    }</div>
                    ${itemsList}
                    <div class="tooltip-total" style="display: flex; justify-content: space-between; font-weight: bold; border-top: 1px solid #dee2e6; padding-top: 10px; margin-top: 10px;">
                        <span>الإجمالي:</span>
                        <span>${invoice.total.toFixed(2)} ج.م</span>
                    </div>
                </div>
            `;
            }

            // تحديد لون المبلغ المتبقي
            let remainingColor = "text-danger";
            if (invoice.remaining === 0) {
              remainingColor = "text-success";
            } else if (invoice.status === "partial") {
              remainingColor = "text-warning";
            }

            row.innerHTML = `
            <td class="position-relative" style="position: relative;">
                <div class="invoice-item-hover" style="position: relative; display: inline-block; cursor: pointer;">
                    ${invoice.number}
                    <br><small class="text-muted">(مرر للعرض)</small>
                    ${itemsTooltip}
                </div>
            </td>
            <td>${invoice.date}</td>
            <td>${invoice.total.toFixed(2)} ج.م</td>
            <td>${invoice.paid.toFixed(2)} ج.م</td>
            <td><span class="${remainingColor} fw-bold">${invoice.remaining.toFixed(
              2
            )} ج.م</span></td>
            <td>${statusBadge}</td>
            <td>
                <div class="action-buttons">
                    <button class="btn btn-sm btn-outline-info view-work-order-invoice" data-invoice-id="${
                      invoice.id
                    }">
                        <i class="fas fa-eye"></i>
                    </button>
                    ${
                      invoice.status !== "paid" && invoice.status !== "returned"
                        ? `
                    <button class="btn btn-sm btn-outline-success pay-work-order-invoice" data-invoice-id="${invoice.id}">
                        <i class="fas fa-money-bill-wave"></i>
                    </button>
                    `
                        : ""
                    }
                    ${
                      invoice.status !== "returned"
                        ? `
                    <button class="btn btn-sm btn-outline-warning custom-return-work-order-invoice" data-invoice-id="${invoice.id}">
                        <i class="fas fa-undo"></i>
                    </button>
                    `
                        : ""
                    }
                    <button class="btn btn-sm btn-outline-secondary print-work-order-invoice" data-invoice-id="${
                      invoice.id
                    }">
                        <i class="fas fa-print"></i>
                    </button>
                </div>
            </td>
        `;

            tbody.appendChild(row);
          });

          // إضافة مستمعي الأحداث للأزرار داخل المودال
          this.attachWorkOrderModalEventListeners();

          // إضافة مستمعي الأحداث للتولتيب
          setTimeout(() => {
            this.attachTooltipEvents();
          }, 100);

          // فتح المودال
          const modal = new bootstrap.Modal(
            document.getElementById("workOrderInvoicesModal")
          );
          modal.show();
        },

        attachTooltipEvents() {
          // إضافة أحداث التولتيب للعناصر داخل المودال
          document
            .querySelectorAll("#workOrderInvoicesList .invoice-item-hover")
            .forEach((element) => {
              element.addEventListener("mouseenter", function (e) {
                const tooltip = this.querySelector(".invoice-items-tooltip");
                if (tooltip) {
                  tooltip.style.display = "block";
                  tooltip.style.zIndex = "9999";
                }
              });

              element.addEventListener("mouseleave", function (e) {
                const tooltip = this.querySelector(".invoice-items-tooltip");
                if (tooltip) {
                  tooltip.style.display = "none";
                }
              });
            });
        },
        attachWorkOrderModalEventListeners() {
          // هذه الدالة ستضيف event listeners للأزرار داخل المودال
          setTimeout(() => {
            // زر عرض الفاتورة
            document
              .querySelectorAll(
                "#workOrderInvoicesList .view-work-order-invoice"
              )
              .forEach((btn, index) => {
                btn.addEventListener("click", function () {
                  const invoiceId = parseInt(
                    this.getAttribute("data-invoice-id")
                  );
                  InvoiceManager.showInvoiceDetails(invoiceId);

                  // إغلاق مودال الشغلانة
                  const m = document.getElementById("invoiceItemsModal");
                  m.style.zIndex = 1060;

                  // إعادة تعيين z-index للمودال بعد فتح المودال الآخر
                });
              });

            // زر سداد الفاتورة
            document
              .querySelectorAll(
                "#workOrderInvoicesList .pay-work-order-invoice"
              )
              .forEach((btn) => {
                btn.addEventListener("click", function () {
                  const invoiceId = parseInt(
                    this.getAttribute("data-invoice-id")
                  );

                  // إغلاق مودال الشغلانة أولاً
                  const modal = bootstrap.Modal.getInstance(
                    document.getElementById("workOrderInvoicesModal")
                  );
                  modal.hide();

                  // فتح مودال السداد
                  PaymentManager.openSingleInvoicePayment(invoiceId);
                });
              });

            // زر إرجاع الفاتورة
            document
              .querySelectorAll(
                "#workOrderInvoicesList .custom-return-work-order-invoice"
              )
              .forEach((btn) => {
                btn.addEventListener("click", function () {
                  const invoiceId = parseInt(
                    this.getAttribute("data-invoice-id")
                  );

                  // إغلاق مودال الشغلانة أولاً
                  const modal = bootstrap.Modal.getInstance(
                    document.getElementById("workOrderInvoicesModal")
                  );
                  modal.hide();

                  // فتح مودال الإرجاع
                  CustomReturnManager.openReturnModal(invoiceId);
                });
              });

            // زر طباعة الفاتورة
            document
              .querySelectorAll(
                "#workOrderInvoicesList .print-work-order-invoice"
              )
              .forEach((btn) => {
                btn.addEventListener("click", function () {
                  const invoiceId = parseInt(
                    this.getAttribute("data-invoice-id")
                  );

                  // إغلاق مودال الشغلانة أولاً
                  const modal = bootstrap.Modal.getInstance(
                    document.getElementById("workOrderInvoicesModal")
                  );
                  modal.hide();

                  // فتح مودال الطباعة
                  PrintManager.printSingleInvoice(invoiceId);
                });
              });
          }, 100);
        },
      };
   
      // ============================================
      // إدارة الواجهة
      // ============================================
      const UIManager = {
        init() {
          this.setupEventListeners();
          this.initializeModals();
        },

        setupEventListeners() {
          // الفلاتر
          document
            .getElementById("dateFrom")
            .addEventListener("change", (e) => {
              AppData.activeFilters.dateFrom = e.target.value;
              this.applyFilters();
            });

          document.getElementById("dateTo").addEventListener("change", (e) => {
            AppData.activeFilters.dateTo = e.target.value;
            this.applyFilters();
          });

          document
            .getElementById("productSearch")
            .addEventListener("input", (e) => {
              AppData.activeFilters.productSearch = e.target.value;
              this.applyFilters();
            });

          document
            .getElementById("invoiceTypeFilter")
            .addEventListener("change", (e) => {
              AppData.activeFilters.invoiceType = e.target.value;
              this.applyFilters();
            });

          // البحث المتقدم
          document
            .getElementById("advancedProductSearch")
            .addEventListener("input", (e) => {


              const searchTerm = e.target.value;
              const results =
                ReturnManager.searchProductsInInvoices(searchTerm);
              this.displayAdvancedSearchResults(results);
              // يمكن إضافة وظيفة البحث هنا إذا لزم الأمر
            });

          // زر حفظ الشغلانة
          document
            .getElementById("saveWorkOrderBtn")
            .addEventListener("click", () => {
              this.addNewWorkOrder();
            });

          // زر إيداع المحفظة
          document
            .getElementById("processDepositBtn")
            .addEventListener("click", () => {
              this.processWalletDeposit();
            });

          // زر طباعة الكشف
          document
            .getElementById("printStatementBtn")
            .addEventListener("click", () => {
              this.printStatement();
            });

          // تحديث كشف الحساب عند تغيير التاريخ
          document
            .getElementById("statementDateFrom")
            .addEventListener("change", () => {
              this.updateStatementTable();
            });

          document
            .getElementById("statementDateTo")
            .addEventListener("change", () => {
              this.updateStatementTable();
            });

          // زر طباعة بنود الفاتورة
          // في UIManager.setupEventListeners():
          // زر طباعة بنود الفاتورة
          document
            .getElementById("printInvoiceItemsBtn")
            .addEventListener("click", function (e) {
              e.preventDefault();
              e.stopPropagation();

              const invoiceNumber =
                document.getElementById("invoiceItemsNumber").textContent;
              const invoice = AppData.invoices.find(
                (i) => i.number === invoiceNumber
              );
              if (invoice) {
                PrintManager.printSingleInvoice(invoice.id);
              }
            });
          // زر سداد فواتير الشغلانة

          // إعداد المودالات عند فتحها
          document
            .getElementById("paymentModal")
            .addEventListener("show.bs.modal", () => {
              PaymentManager.loadInvoicesForPayment();
              PaymentManager.resetPaymentForm();
              setupNumberInputPrevention();
            });

          document
            .getElementById("statementReportModal")
            .addEventListener("show.bs.modal", () => {
              this.setupStatementModal();
            });
        },

        applyFilters() {
          // تحديث الفلاتر النشطة
          this.updateFilterTags();

          // تطبيق الفلاتر على الجداول
          InvoiceManager.updateInvoicesTable();
        },

        updateFilterTags() {
          const container = document.getElementById("filterTags");
          container.innerHTML = "";

          Object.entries(AppData.activeFilters).forEach(([key, value]) => {
            if (value) {
              const tag = document.createElement("div");
              tag.className = "filter-tag";

              let label = "";
              let displayValue = value;

              switch (key) {
                case "dateFrom":
                  label = "من تاريخ";
                  break;
                case "dateTo":
                  label = "إلى تاريخ";
                  break;
                case "productSearch":
                  label = "بحث";
                  break;
                case "invoiceType":
                  label = "النوع";
                  displayValue = this.getInvoiceTypeText(value);
                  break;
              }

              tag.innerHTML = `
                        ${label}: ${displayValue}
                        <span class="close" data-filter="${key}">&times;</span>
                    `;

              container.appendChild(tag);
            }
          });

          // إضافة مستمعي الأحداث لإزالة الفلاتر
          document
            .querySelectorAll(".filter-tag .close")
            .forEach((closeBtn) => {
              closeBtn.addEventListener("click", function () {
                const filterKey = this.getAttribute("data-filter");
                AppData.activeFilters[filterKey] = null;

                // إعادة تعيين قيمة الإدخال
                if (filterKey === "dateFrom") {
                  document.getElementById("dateFrom").value = "";
                } else if (filterKey === "dateTo") {
                  document.getElementById("dateTo").value = "";
                } else if (filterKey === "productSearch") {
                  document.getElementById("productSearch").value = "";
                } else if (filterKey === "invoiceType") {
                  document.getElementById("invoiceTypeFilter").value = "";
                }

                UIManager.applyFilters();
              });
            });
        },

        getInvoiceTypeText(type) {
          const typeMap = {
            pending: "مؤجل",
            partial: "جزئي",
            paid: "مسلم",
            returned: "مرتجع",
          };
          return typeMap[type] || type;
        },

        addNewWorkOrder() {
          const name = document.getElementById("workOrderName").value.trim();
          const description = document
            .getElementById("workOrderDescription")
            .value.trim();
          const startDate = document.getElementById("workOrderStartDate").value;
          const notes = document.getElementById("workOrderNotes").value;

          if (!name || !description || !startDate) {
            Swal.fire("تحذير", "يرجى ملء جميع الحقول المطلوبة", "warning");
            return;
          }

          const newWorkOrder = {
            id: AppData.nextWorkOrderId++,
            name: name,
            description: description,
            status: "pending",
            startDate: startDate,
            notes: notes,
            invoices: [],
            createdBy: AppData.currentUser,
          };

          AppData.workOrders.unshift(newWorkOrder);

          Swal.fire(
            "نجاح",
            `تم إنشاء الشغلانة "${newWorkOrder.name}" بنجاح`,
            "success"
          );

          // إغلاق المودال وإعادة التعيين
          const modal = bootstrap.Modal.getInstance(
            document.getElementById("newWorkOrderModal")
          );
          modal.hide();

          document.getElementById("newWorkOrderForm").reset();
          WorkOrderManager.updateWorkOrdersTable();
        },

        processWalletDeposit() {
          const amount = parseFloat(
            document.getElementById("depositAmount").value
          );
          const description = document
            .getElementById("depositDescription")
            .value.trim();

          if (!amount || amount <= 0) {
            Swal.fire("تحذير", "يرجى إدخال مبلغ صحيح للإيداع", "warning");
            return;
          }

          if (!description) {
            Swal.fire("تحذير", "يرجى إدخال وصف للإيداع", "warning");
            return;
          }

          WalletManager.addTransaction({
            type: "deposit",
            amount: amount,
            description: description,
            date: new Date().toISOString().split("T")[0],
          });

          Swal.fire(
            "نجاح",
            `تم إيداع ${amount.toFixed(2)} ج.م في المحفظة بنجاح`,
            "success"
          );

          // إغلاق المودال وإعادة التعيين
          const modal = bootstrap.Modal.getInstance(
            document.getElementById("walletDepositModal")
          );
          modal.hide();

          document.getElementById("walletDepositForm").reset();
        },

        setupStatementModal() {
          const today = new Date().toISOString().split("T")[0];
          const firstDayOfMonth = today.substring(0, 8) + "01";

          document.getElementById("statementDateFrom").value = firstDayOfMonth;
          document.getElementById("statementDateTo").value = today;

          this.updateStatementTable();
        },

        updateStatementTable() {
          const dateFrom = document.getElementById("statementDateFrom").value;
          const dateTo = document.getElementById("statementDateTo").value;

          const transactions = WalletManager.getStatementTransactions(
            dateFrom,
            dateTo
          );
          const tbody = document.getElementById("statementTableBody");
          tbody.innerHTML = "";

          let currentBalance = 0;

          transactions.forEach((transaction) => {
            const row = document.createElement("tr");

            // تحديد لون المبلغ
            let amountClass =
              transaction.amount > 0 ? "text-success" : "text-danger";
            let amountSign = transaction.amount > 0 ? "+" : "";

            row.innerHTML = `
                    <td>${transaction.date}</td>
                    <td>${WalletManager.getTransactionTypeText(
                      transaction.type
                    )}</td>
                    <td>${transaction.description}</td>
                    <td class="${amountClass}">${amountSign}${transaction.amount.toFixed(
              2
            )} ج.م</td>
                    <td>${transaction.balanceAfter.toFixed(2)} ج.م</td>
                    <td>${transaction.createdBy}</td>
                `;

            tbody.appendChild(row);
            currentBalance = transaction.balanceAfter;
          });

          // إذا لم توجد حركات، إظهار رسالة
          if (transactions.length === 0) {
            const row = document.createElement("tr");
            row.innerHTML = `<td colspan="6" class="text-center text-muted">لا توجد حركات في الفترة المحددة</td>`;
            tbody.appendChild(row);
          }
        },

        printStatement() {
          const dateFrom = document.getElementById("statementDateFrom").value;
          const dateTo = document.getElementById("statementDateTo").value;

          PrintManager.printStatement(dateFrom, dateTo);
        },

        printInvoiceFromModal() {
          const invoiceNumber =
            document.getElementById("invoiceItemsNumber").textContent;
          const invoice = AppData.invoices.find(
            (i) => i.number === invoiceNumber
          );

          if (invoice) {
            PrintManager.printSingleInvoice(invoice.id);
          }
        },

        payWorkOrderInvoices() {
          const workOrderName = document.getElementById(
            "workOrderInvoicesName"
          ).textContent;
          const workOrder = AppData.workOrders.find(
            (wo) => wo.name === workOrderName
          );

          if (workOrder) {
            PaymentManager.openWorkOrderPayment(workOrder.id);
          }
        },

        displayAdvancedSearchResults(results) {
          const container = document.getElementById("advancedSearchResults");
          container.innerHTML = "";

          if (results.length === 0) {
            container.style.display = "none";
            return;
          }

          container.style.display = "block";

          // دالة لتمييز النص المطابق
          const highlightText = (text, searchTerms) => {
            let highlighted = escapeHtml(text);

            // ترتيب مصطلحات البحث من الأطول للأقصر لتجنب التداخل
            const sortedTerms = [...searchTerms].sort(
              (a, b) => b.length - a.length
            );

            sortedTerms.forEach((term) => {
              const regex = new RegExp(`(${term})`, "gi");
              highlighted = highlighted.replace(
                regex,
                '<span class="search-highlight">$1</span>'
              );
            });

            return highlighted;
          };

          results.forEach((result) => {
            const div = document.createElement("div");
            div.className = "search-result-item";
            div.style.cursor = "pointer";

            const highlightedProductName = highlightText(
              result.productName,
              result.searchTerms || []
            );

            div.innerHTML = `
            <div class="fw-bold">${highlightedProductName}</div>
            <div class="small text-muted">
                الفاتورة: <strong>${result.invoiceNumber}</strong> (${
              result.invoiceDate
            }) | 
                الكمية: ${result.soldQuantity} | 
                ${
                  result.availableQuantity > 0
                    ? `المتاح للإرجاع: ${result.availableQuantity} | `
                    : '<span class="text-danger">غير متاح للإرجاع</span> | '
                }
                السعر: ${result.price.toFixed(2)} ج.م
            </div>
        `;

            div.addEventListener("click", () => {
              // عرض الفاتورة في الجانب الأيمن
              this.showInvoiceInRightPanel(result.invoiceId);

              container.style.display = "none";
              document.getElementById("advancedProductSearch").value = "";
            });

            container.appendChild(div);
          });
        },

        showInvoiceInRightPanel(invoiceId) {
          // البحث عن الفاتورة
          const invoice = AppData.invoices.find((i) => i.id === invoiceId);
          if (!invoice) return;

          // تطبيق فلتر لعرض الفاتورة فقط
          AppData.activeFilters.invoiceId = invoiceId;
          InvoiceManager.updateInvoicesTable();

          // إزالة الفلتر بعد عرض الفاتورة
          setTimeout(() => {
            delete AppData.activeFilters.invoiceId;
          }, 100);

          // التمرير إلى جدول الفواتير
          const invoicesTab = document.getElementById("invoices-tab");
          if (invoicesTab) {
            invoicesTab.click();
            setTimeout(() => {
              const invoiceRow = document.querySelector(
                `tr[data-invoice-id="${invoiceId}"]`
              );
              if (invoiceRow) {
                invoiceRow.scrollIntoView({
                  behavior: "smooth",
                  block: "center",
                });
                invoiceRow.style.backgroundColor = "var(--primary)";
                invoiceRow.style.color = "white";
                setTimeout(() => {
                  invoiceRow.style.backgroundColor = "";
                  invoiceRow.style.color = "";
                }, 2000);
              }
            }, 300);
          }
        },
      };
//   
    </script>
  </body>
</html>
<?php
$conn->close();
require_once BASE_DIR . 'partials/footer.php';
