import AppData from "./app_data.js";
import CustomerManager from "./customer.js";
import PrintManager from "./print.js";
import PaymentManager from "./payment.js";
import apis from "./constant/api_links.js";
import InvoiceManager from "./invoices.js";
import { CustomReturnManager } from "./return.js";


// work-order-manager.js
const WorkOrderManager = {
    currentCustomerId: null,
    async init() {
        let customerId = this.getCustomerIdFromURL();

        ;
        if (!customerId) {
            console.error('Customer ID is required');
            return;
        }

        this.currentCustomerId = customerId;
        await this.fetchWorkOrders();
        await this.eventy();
        //    this.attachInvoiceEventListeners();
    },

    // جلب الشغلانات من الـ API وتخزينها في AppData
    async fetchWorkOrders() {
        try {
            // عرض حالة التحميل
            this.showLoading();

            const response = await fetch(
                `${apis.getCustomerWorkOrders}${encodeURIComponent(this.currentCustomerId)}`,
                {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    cache: 'no-cache'
                }
            );

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (data.success) {
                // تخزين البيانات في AppData
                AppData.workOrders = data.work_orders.map(wo => ({
                    id: wo.id,
                    name: wo.title,
                    title: wo.title,
                    description: wo.description || '',
                    status: wo.status,
                    startDate: wo.start_date,
                    // نستخدم البيانات المالية من الـ API
                    total_invoice_amount: parseFloat(wo.total_invoice_amount) || 0,
                    total_paid: parseFloat(wo.total_paid) || 0,
                    total_remaining: parseFloat(wo.total_remaining) || 0,
                    progress_percent: wo.progress_percent || 0,
                    invoices_count: wo.invoices_count || 0,
                    customer_id: wo.customer_id,
                    customer_name: wo.customer_name,
                    created_at: wo.created_at,
                    invoices: wo.invoices || []
                }));

                // تحديث الجدول
                this.updateWorkOrdersTable();

            } else {
                throw new Error(data.message || 'فشل في تحميل البيانات');
            }
        } catch (error) {
            console.error('❌ خطأ في جلب الشغلانات:', error);
            this.showError('خطأ', 'فشل في تحميل الشغلانات');
        } finally {
            this.hideLoading();
        }
    },

     createTooltipContainer(invoice) {
        
    return `        <div class="invoice-items-tooltip "style=" 
        overflow: hidden;
        height: 0;
        transition: all 1s ease-in-out;
        opacity: 0;

    position: sticky;
    top: 0;
    z-index: 99999;
    
    " id="tooltip-${invoice.id}" >
            <div class="tooltip-content" id="tooltip-content-${invoice.id}">
              
            </div>
        </div>
    `;
},

setupTooltipStyles() {
    if (document.querySelector('#work-order-tooltip-styles')) return;
    
    const style = document.createElement('style');
    style.id = 'work-order-tooltip-styles';
    style.textContent = `
        /* ===== Tooltip Positioning FIX ===== */
        /* إصلاح كامل لموقع وتكديس الـ tooltip */
        
        /* العنصر الذي يحوي الـ tooltip */
        .work-order-item-hover {
            position: relative !important;
            display: inline-block !important;
            cursor: pointer !important;
        }
        
        /* الـ tooltip نفسه - الأهم */
        .invoice-items-tooltip {
            position: fixed !important; /* تغيير من absolute إلى fixed */
            width: 350px !important;
            min-height: 180px !important;
            max-height: 500px !important;
            background: white !important;
            border: 1px solid #dee2e6 !important;
            border-radius: 8px !important;
            box-shadow: 
                0 6px 20px rgba(0,0,0,0.15),
                0 12px 40px rgba(0,0,0,0.2) !important;
            z-index: 999999 !important; /* أعلى قيمة ممكنة */
            padding: 15px !important;
            display: none !important;
            font-size: 13px !important;
            pointer-events: auto !important;
            overflow: hidden !important;
            animation: tooltipFadeIn 0.15s ease-out !important;
            backdrop-filter: blur(2px) !important;
        }
        
        @keyframes tooltipFadeIn {
            from {
                opacity: 0;
                transform: translateY(-5px) scale(0.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        /* إصلاح للجداول داخل المودال */
        .modal .table,
        .modal thead,
        .modal th,
        .modal tr {
            position: static !important;
            z-index: auto !important;
        }
        
        /* منع أي عنصر من التغطية على الـ tooltip */
        .modal-backdrop,
        .modal-content,
        .modal-header,
        .modal-body,
        .table thead,
        .table th {
            z-index: auto !important;
            position: relative !important;
        }
        
        /* إصلاح خاص للـ thead */
        .table thead {
            position: sticky !important;
            top: 0 !important;
            z-index: 1 !important;
            background: white !important;
        }
        
        .table thead th {
            position: sticky !important;
            top: 0 !important;
            z-index: 2 !important;
            background: white !important;
            border-bottom: 2px solid #dee2e6 !important;
        }
        
        /* ضمان ظهور الـ tooltip فوق الـ thead */
        .invoice-items-tooltip {
            z-index: 999999 !important;
        }
        
        /* محتوى الـ tooltip */
        .tooltip-content {
            max-height: 400px !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
        }
        
        /* تصميم محتوى الـ tooltip */
        .tooltip-header {
            font-weight: bold !important;
            border-bottom: 2px solid #0d6efd !important;
            padding-bottom: 8px !important;
            margin-bottom: 12px !important;
            color: #212529 !important;
            font-size: 14px !important;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef) !important;
            padding: 8px 12px !important;
            border-radius: 6px 6px 0 0 !important;
            margin: -15px -15px 12px -15px !important;
        }
        
        .tooltip-item {
            display: flex !important;
            justify-content: space-between !important;
            padding: 8px 0 !important;
            border-bottom: 1px solid #f1f3f5 !important;
            align-items: flex-start !important;
        }
        
        .tooltip-item:last-child {
            border-bottom: none !important;
        }
        
        .tooltip-item-name {
            font-weight: 600 !important;
            color: #212529 !important;
            margin-bottom: 4px !important;
            font-size: 13px !important;
        }
        
        .tooltip-item-details {
            font-size: 12px !important;
            color: #6c757d !important;
            line-height: 1.4 !important;
        }
        
        .tooltip-total {
            display: flex !important;
            justify-content: space-between !important;
            font-weight: bold !important;
            padding: 12px !important;
            margin-top: 12px !important;
            border-top: 2px solid #dee2e6 !important;
            color: #198754 !important;
            background: #f8f9fa !important;
            border-radius: 6px !important;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .invoice-items-tooltip {
                width: 300px !important;
                max-width: 90vw !important;
            }
        }
    `;
    document.head.appendChild(style);
},


// تحديث دالة buildItemsTooltip لإظهار الخصم
  buildItemsTooltip(invoice) {
    const items = invoice.items || [];

    const discountAmount = parseFloat(invoice.discount_amount || 0);
    const discountValue = parseFloat(invoice.discount_value || 0);
    const discountType = invoice.discount_type || "percent";
    const discountScope = invoice.discount_scope || "invoice";
    const beforeDiscount = parseFloat(
      invoice.total_before_discount || invoice.total || 0
    );
    const afterDiscount = parseFloat(
      invoice.total_after_discount || invoice.total || 0
    );

    if (items.length === 0) {
      return `
                <div class="tooltip-header">
                    بنود الفاتورة ${invoice.invoice_number || invoice.id}
                </div>
                <div class="text-center py-3 text-muted">
                    لا توجد بنود
                </div>
            `;
    }

    let totalReturnedAmount = 0; // جديد: لحساب إجمالي المرتجعات

    const itemsList = items
      .map((item) => {
        const returnedQuantity = item.returned_quantity || 0;
        const currentQuantity = item.quantity - returnedQuantity;
        const originalTotal =
          item.total_before_discount ||
          item.quantity * (item.selling_price || item.price || 0);
        const discountedUnitPrice =
          item.unit_price_after_discount ||
          item.selling_price ||
          item.price ||
          0;
        const currentTotal = currentQuantity * discountedUnitPrice; // جديد: الإجمالي بعد الخصم والمرتجع

        // حساب إجمالي المرتجع
        if (returnedQuantity > 0) {
          totalReturnedAmount += returnedQuantity * discountedUnitPrice;
        }

        const itemDiscount =
          discountScope === "items" ? parseFloat(item.discount_amount || 0) : 0;
        const hasDiscount = itemDiscount > 0;

        let discountHTML = "";
        if (hasDiscount) {
          const itemDiscountPercent = (
            (itemDiscount / originalTotal) *
            100
          ).toFixed(1);
          discountHTML = `
                        <div class="tooltip-item-discount">
                            <small class="text-danger">
                                <i class="fas fa-tag me-1"></i>
                                خصم: ${itemDiscount.toFixed(
                                  2
                                )} ج.م (${itemDiscountPercent}%)
                            </small>
                        </div>
                    `;
        }

        const returnedText =
          returnedQuantity > 0
            ? `<br><small class="text-warning">(مرتجع: ${returnedQuantity})</small>`
            : "";

        return `
                    <div class="tooltip-item">
                        <div>
                            <div class="tooltip-item-name">${
                              item.product_name || "منتج"
                            }</div>
                            <div class="tooltip-item-details">
                                الكمية: ${currentQuantity} من ${
          item.quantity
        }${returnedText}
                                <br>
                                السعر: <span style="${
                                  hasDiscount
                                    ? "text-decoration: line-through;"
                                    : ""
                                }">${(
          item.selling_price ||
          item.price ||
          0
        ).toFixed(2)}</span>
                                ${
                                  hasDiscount
                                    ? ` → ${discountedUnitPrice.toFixed(2)} ج.م`
                                    : ""
                                }
                                ${discountHTML}
                            </div>
                        </div>
                        <div class="fw-bold">
                            ${currentTotal.toFixed(2)} ج.م
                        </div>
                    </div>
                `;
      })
      .join("");

    // بناء قسم الخصم + المرتجعات
    let discountSection = "";
    if (discountAmount > 0 || totalReturnedAmount > 0) {
      const discountPercent =
        discountType === "percent"
          ? discountValue
          : (discountAmount / beforeDiscount) * 100;

      discountSection = `
                <div class="tooltip-discount-section">
                    <div class="tooltip-discount-row">
                        <span>الإجمالي قبل الخصم:</span>
                        <span>${beforeDiscount.toFixed(2)} ج.م</span>
                    </div>
                    ${
                      discountAmount > 0
                        ? `
                    <div class="tooltip-discount-row text-danger">
                        <span>قيمة الخصم:</span>
                        <span>-${discountAmount.toFixed(2)} ج.م</span>
                    </div>`
                        : ""
                    }
                    ${
                      totalReturnedAmount > 0
                        ? `
                    <div class="tooltip-discount-row text-warning">
                        <span>إجمالي المرتجع:</span>
                        <span>- ${totalReturnedAmount.toFixed(2)} ج.م</span>
                    </div>`
                        : ""
                    }
                    ${
                      discountAmount > 0
                        ? `
                    <div class="tooltip-discount-row">
                        <small class="text-muted">
                            نوع الخصم: ${
                              discountScope === "items"
                                ? "على البنود"
                                : "على الفاتورة"
                            } (${discountPercent.toFixed(1)}%)
                        </small>
                    </div>`
                        : ""
                    }
                </div>
            `;
    }

    return `
            <div class="tooltip-header">
                بنود الفاتورة ${invoice.invoice_number || invoice.id}
            </div>
            ${itemsList}
            ${discountSection}
            <div class="tooltip-total">
                <span>الإجمالي النهائي:</span>
                <span class="fw-bold">${afterDiscount.toFixed(2)} ج.م</span>
            </div>
        `;
  },

// تحديث CSS للـ Tooltip
setupTooltipStyles() {
    const style = document.createElement('style');
    style.textContent = `
        .tooltip-discount-badge {
            background: linear-gradient(135deg, #6c757d, #495057);
            color: white;
            padding: 4px 8px;
            border-radius: 4px 4px 0 0;
            font-size: 12px;
            text-align: center;
        }
        
        .tooltip-discount-section {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed #dee2e6;
        }
        
        .tooltip-discount-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
            font-size: 12px;
        }
        
        .tooltip-item-discount {
            margin-top: 2px;
            padding: 2px 5px;
            background: #fff3cd;
            border-radius: 3px;
            border: 1px solid #ffeaa7;
        }
    `;
    document.head.appendChild(style);
},

setupTooltipHover(row, invoiceId) {
    const itemsCell = row.querySelector('.work-order-item-hover');
    const tooltip = row.querySelector(`#tooltip-${invoiceId}`);
    const tooltipContent = tooltip.querySelector(`#tooltip-content-${invoiceId}`);
    
    let timeoutId;
    
    itemsCell.addEventListener('mouseenter', async () => {
        // إلغاء أي timeout سابق
        clearTimeout(timeoutId);
        
        // إظهار الـ tooltip فوراً
        tooltip.style.height ='fit-content';
        tooltip.style.opacity ='1';
        
        // البحث عن الفاتورة في البيانات المحلية
        const invoice = AppData.invoices?.find(inv => inv.id == invoiceId);
        
        if (invoice?.items) {
            
            // إذا كانت البيانات موجودة محلياً
            const tooltipHTML = this.buildItemsTooltip(invoice);
            (tooltipHTML);
            
            tooltipContent.innerHTML = tooltipHTML;
        } else {
            try {
                // إذا لم توجد محلياً، تحميل من API
                const invoiceDetails = await this.loadInvoiceDetails(invoiceId);
                
                if (invoiceDetails?.items) {
                    // حفظ في البيانات المحلية لاستخدامها لاحقاً
                    if (!invoice.items) {
                        invoice.items = invoiceDetails.items;
                    }
                    
                    const tooltipHTML = this.buildItemsTooltip(invoiceDetails);
                    tooltipContent.innerHTML = tooltipHTML;
                }
            } catch (error) {
                tooltipContent.innerHTML = `
                    <div class="tooltip-error text-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        فشل في تحميل البنود
                    </div>
                `;
            }
        }
    });
    
    itemsCell.addEventListener('mouseleave', () => {
        // تأخير إخفاء الـ tooltip لمدة 300ms لتجنب الاختفاء السريع
        timeoutId = setTimeout(() => {
            // tooltip.style.display = 'none';
               tooltip.style.height ='0';
        tooltip.style.opacity ='0';
            // إعادة تعيين الـ loading للمرة القادمة
            tooltipContent.innerHTML = `
                <div class="tooltip-loading">
                    <i class="fas fa-spinner fa-spin me-2"></i> جاري تحميل البنود...
                </div>
            `;
        }, 300);
    });
    
    tooltip.addEventListener('mouseenter', () => {
        clearTimeout(timeoutId);
        // tooltip.style.display = 'block';
           tooltip.style.height ='fit-content';
        tooltip.style.opacity ='1';

        ('Tooltip mouseenter - remain visible');
    });
    
    tooltip.addEventListener('mouseleave', () => {
        timeoutId = setTimeout(() => {
            // tooltip.style.display = 'none';
               tooltip.style.height ='0';
        tooltip.style.opacity ='0';
            tooltipContent.innerHTML = `
                <div class="tooltip-loading">
                    <i class="fas fa-spinner fa-spin me-2"></i> جاري تحميل البنود...
                </div>
            `;
        }, 300);
    });
},

    getCustomerIdFromURL() {
        // طريقة 1: من query string
        const urlParams = new URLSearchParams(window.location.search);
        const id = urlParams.get('customer_id') || urlParams.get('id');

        // طريقة 2: من data attribute
        if (!id) {
            const dataId = document.body.getAttribute('data-customer-id');
            if (dataId) return dataId;
        }

        // طريقة 3: من متغير global
        if (!id && window.customerId) {
            return window.customerId;
        }

        return id;
    },
    // إنشاء شغلانة جديدة
    async createWorkOrder(workOrderData) {
        try {
            const response = await fetch(apis.createWorkOrder, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(workOrderData)
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (data.success) {
                // إضافة الشغلانة الجديدة إلى AppData
                const newWorkOrder = {
                    id: data.work_order.id,
                    name: data.work_order.title,
                    title: data.work_order.title,
                    description: data.work_order.description || '',
                    status: data.work_order.status,
                    startDate: data.work_order.start_date,
                    total_invoice_amount: parseFloat(data.work_order.total_invoice_amount) || 0,
                    total_paid: parseFloat(data.work_order.total_paid) || 0,
                    total_remaining: parseFloat(data.work_order.total_remaining) || 0,
                    progress_percent: data.work_order.total_invoice_amount > 0 ?
                        Math.round((data.work_order.total_paid / data.work_order.total_invoice_amount) * 100, 2) : 0,
                    invoices_count: 0,
                    customer_id: data.work_order.customer_id,
                    customer_name: data.work_order.customer_name,
                    created_at: data.work_order.created_at
                };

                AppData.workOrders.unshift(newWorkOrder);

                // تحديث الجدول
                this.updateWorkOrdersTable();

                return {
                    success: true,
                    message: data.message,
                    workOrder: newWorkOrder
                };
            } else {
                throw new Error(data.message || 'فشل في إنشاء الشغلانة');
            }
        } catch (error) {
            console.error('❌ خطأ في إنشاء الشغلانة:', error);
            return {
                success: false,
                message: error.message
            };
        }
    },

    // جلب تفاصيل شغلانة محددة
    async fetchWorkOrderDetails(workOrderId) {
        try {
            // البحث في البيانات المحلية
            const workOrder = AppData.workOrders.find(
                wo => Number(wo.id) === Number(workOrderId)
            );

            if (!workOrder) {
                throw new Error('الشغلانة غير موجودة في البيانات المحلية');
            }

            return {
                success: true,
                workOrder: workOrder,
                invoices: workOrder.invoices || []
            };

        } catch (error) {
            console.error('❌ خطأ في جلب تفاصيل الشغلانة (Local):', error);

            return {
                success: false,
                message: error.message
            };
        }
    },


    // تحديث جدول الشغلانات
    updateWorkOrdersTable() {
        const container = document.getElementById("workOrdersContainer");
        if (!container) {
            console.error('❌ عنصر workOrdersContainer غير موجود');
            return;
        }

        container.innerHTML = "";

        if (AppData.workOrders.length === 0) {
            container.innerHTML = `
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        لا توجد شغلانات لعرضها
                    </div>
                </div>
            `;
            return;
        }

        AppData.workOrders.forEach((workOrder) => {
            const workOrderCard = document.createElement("div");
            workOrderCard.className = "col-md-6 mb-3";

            // حساب القيم من البيانات المخزنة
            const totalInvoices = workOrder.total_invoice_amount || 0;
            const totalPaid = workOrder.total_paid || 0;
            const totalRemaining = workOrder.total_remaining || 0;
            const progressPercent = workOrder.progress_percent || 0;

            // تحديد حالة الشغلانة
            let statusBadge = "";
            let statusText = "";

            if (workOrder.status === "pending") {
                statusBadge = "badge-pending";
                statusText = "قيد التنفيذ";
            } else if (workOrder.status === "in_progress") {
                statusBadge = "badge-partial";
                statusText = "جاري العمل";
            } else if (workOrder.status === "completed") {
                statusBadge = "badge-paid";
                statusText = "مكتمل";
            } else if (workOrder.status === "cancelled") {
                statusBadge = "badge-danger";
                statusText = "ملغي";
            }

            workOrderCard.innerHTML = `
<div class="work-order-card card h-100">
    <div class="card-body">

        <!-- عنوان الحالة -->
        <div class="d-flex justify-content-between align-items-start mb-2">
            <h5 class="card-title mb-0">${workOrder.title}</h5>
            <span class="status-badge ${statusBadge}">${statusText}</span>
        </div>

        <!-- الوصف -->
        <p class="card-text text-muted mb-3">${workOrder.description || 'لا يوجد وصف'}</p>

        <!-- معلومات أساسية -->
        <div class="row mb-3">
            <div class="col-6">
                <small>تاريخ البدء:</small>
                <div class="text-muted">${workOrder.startDate}</div>
            </div>
            <div class="col-6">
                <small>الفواتير:</small>
                <div class="text-muted">${workOrder.invoices_count || 0} فاتورة</div>
            </div>
        </div>

        <!-- شريط التقدم -->
        <div class="work-order-progress bg-light mb-3 rounded" style="height: 10px;">
            <div class="progress-bar bg-success rounded" style="width: ${progressPercent}%"></div>
        </div>

        <!-- المبالغ -->
        <div class="row text-center mb-3">
            <div class="col-4">
                <small>المطلوب</small>
                <div class="fw-bold">${totalInvoices?.toFixed(2)} ج.م</div>
            </div>
            <div class="col-4">
                <small>المدفوع</small>
                <div class="fw-bold text-success">${totalPaid?.toFixed(2)} ج.م</div>
            </div>
            <div class="col-4">
                <small>المتبقي</small>
                <div class="fw-bold text-danger">${totalRemaining?.toFixed(2)} ج.م</div>
            </div>
        </div>

        <!-- أزرار الإجراء -->
        <div class="action-buttons d-flex gap-2 mt-3">
            <button class="btn btn-sm btn-outline-info view-work-order" data-work-order-id="${workOrder.id}">
                <i class="fas fa-eye"></i> عرض
            </button>
            ${totalRemaining > 0 ? `
            <button class="btn btn-sm btn-outline-success pay-work-order" data-work-order-id="${workOrder.id}">
                <i class="fas fa-money-bill-wave"></i> سداد
            </button>
            ` : ''}
            <button class="btn btn-sm btn-outline-primary print-work-order" data-work-order-id="${workOrder.id}">
                <i class="fas fa-print"></i> طباعة
            </button>
        </div>

    </div>
</div>
`;


            container.appendChild(workOrderCard);
        });

        // إضافة مستمعي الأحداث
        this.attachWorkOrderEventListeners();
    },

    // إضافة مستمعي الأحداث (نفس الكود مع تعديلات طفيفة)
    attachWorkOrderEventListeners() {
        // زر عرض الشغلانة
        document.querySelectorAll(".view-work-order").forEach((btn) => {
            btn.addEventListener("click", async function () {
                const workOrderId = parseInt(this.getAttribute("data-work-order-id"));
                await WorkOrderManager.showWorkOrderDetails(workOrderId);
            });
        });

        // زر سداد الشغلانة
        document.querySelectorAll(".pay-work-order").forEach((btn) => {
            btn.addEventListener("click", function () {
                const workOrderId = parseInt(this.getAttribute("data-work-order-id"));

                // تعيين نوع السداد إلى شغلانة
                document.getElementById("payWorkOrderRadio").checked = true;
                document.getElementById("invoicesPaymentSection").style.display = "none";
                document.getElementById("workOrderPaymentSection").style.display = "block";

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
        document.querySelectorAll(".print-work-order").forEach((btn) => {
            btn.addEventListener("click", async function (e) {
                e.preventDefault();
                e.stopPropagation();
                const workOrderId = parseInt(this.getAttribute("data-work-order-id"));
                // نستخدم الـ API للحصول على البيانات قبل الطباعة
                const result = await WorkOrderManager.fetchWorkOrderDetails(workOrderId);
                if (result.success) {
                    PrintManager.printWorkOrderInvoices(workOrderId, result.invoices);
                }
            });
        });
    },

    // عرض تفاصيل الشغلانة (محدث لاستخدام الـ API)
    async showWorkOrderDetails(workOrderId) {
        try {


            const result = await this.fetchWorkOrderDetails(workOrderId);

            if (result.success) {
                const workOrder = result.workOrder;
                const invoices = result?.invoices;
                if (!workOrder) {
                    throw new Error('الشغلانة غير موجودة');
                }

              
 
    
    // 3. إنشاء خلية الإجمالي مع عرض الخصم - دي اللي هتتعدل
    




                // تحديث البيانات في المودال
                document.getElementById("workOrderInvoicesName").textContent = workOrder.title;
                document.getElementById("workOrderTotalInvoices").textContent =
                    AppData.formatCurrency(workOrder.total_invoice_amount);
                document.getElementById("workOrderTotalPaid").textContent =
                    AppData.formatCurrency(workOrder.total_paid);
                document.getElementById("workOrderTotalRemaining").textContent =
                    AppData.formatCurrency(workOrder.total_remaining);

                // ملء جدول الفواتير
                const tbody = document.getElementById("workOrderInvoicesList");
                tbody.innerHTML = "";

                if (invoices.length === 0) {
                    tbody.innerHTML = `
        <tr>
            <td colspan="7" class="text-center text-muted">
                لا توجد فواتير لهذه الشغلانة
            </td>
        </tr>
    `;}
                 

          invoices.length > 0 && invoices.forEach((invoice) => {
    // حساب بيانات الخصم لكل فاتورة
    const discountAmount = parseFloat(invoice.discount_amount || 0);
    const discountValue = parseFloat(invoice.discount_value || 0);
    const discountType = invoice.discount_type || 'percent';
    const beforeDiscount = parseFloat(invoice.total_before_discount || invoice.total || 0);
    const afterDiscount = parseFloat(invoice.total_after_discount || invoice.total || 0);
    
    
    let totalCellHTML = '';
    

            
    if (discountAmount > 0) {
        // حساب نسبة الخصم
        let discountPercentage;
        if (discountType === 'percent') {
            discountPercentage = discountValue;
        } else {
            discountPercentage = beforeDiscount > 0 ? 
                ((discountAmount / beforeDiscount) * 100) : 0;
        }
        
        totalCellHTML = `
            <div class="d-flex flex-column align-items-start">
                <!-- السعر الأصلي (عليه خط) -->
                <span class="text-muted text-decoration-line-through" style="font-size: 11px;">
                    ${beforeDiscount.toFixed(2)}
                </span>
                <!-- السعر النهائي -->
                <span class="fw-bold text-success" style="font-size: 13px;">
                    ${afterDiscount.toFixed(2)}
                </span>
                <!-- بادج الخصم -->
                <span class="badge bg-danger mt-1" style="font-size: 9px; padding: 2px 6px;">
                    خصم ${discountPercentage.toFixed(1)}%
                </span>
            </div>
        `;
    } else {
        totalCellHTML = `
            <span class="fw-bold">${afterDiscount.toFixed(2)}</span>
        `;
    }
    
    // استخدم totalCellHTML هنا حسب احتياجك
  
                    const row = document.createElement("tr");
                    row.style.transition = "all 1s ease-in-out";
                    const statusInfo = AppData.getInvoiceStatusText(invoice.status);

                    // إنشاء tooltip للبنود
                    // let itemsTooltip = "";
    const tooltipContainer = this.createTooltipContainer(invoice);
    
    
    console.log(invoice);
    
    

                 

                    // تحديد لون المبلغ المتبقي
                    let remainingColor = "text-danger";
                    if (invoice.remaining === 0) {
                        remainingColor = "text-success";
                    } else if (invoice.status === "partial") {
                        remainingColor = "text-warning";
                    }

                    row.innerHTML = `
                        <td class="position-relative" style="position: relative;">
                            <div class="invoice-item-hover work-order-item-hover" style="position: relative; display: inline-block; cursor: pointer;">
                                ${invoice?.id}
                                <br><small class="text-muted">(مرر للعرض)</small>
                                ${tooltipContainer}
                           
                            </div>
                        </td>
                        <td>${invoice.created_at}</td>
                   
                    <td>  ${totalCellHTML||0} </td>
                        <td>${invoice.paid?.toFixed(2)} ج.م</td>
                        <td><span class="${remainingColor} fw-bold">${invoice.remaining?.toFixed(2)} ج.م</span></td>
                        <td><span class="status-badge ${statusInfo.class}">${statusInfo.text}</span></td>
                        <td>
                <div class="action-buttons">
                    <button class="btn btn-sm btn-outline-info view-invoice-work-order" 
                            data-invoice-id="${invoice.id}">
                        <i class="fas fa-eye"></i>
                    </button>
                    ${invoice.status !== "paid" && invoice.status !== "returned" ? `
                    <button class="btn btn-sm btn-outline-success pay-invoice-work-order" 
                            data-invoice-id="${invoice.id}">
                        <i class="fas fa-money-bill-wave"></i>
                    </button>
                    ` : ""}
                    ${invoice.status !== "returned" ? `
                    <button class="btn btn-sm btn-outline-warning custom-return-invoice-work-order" 
                            data-invoice-id="${invoice.id}">
                        <i class="fas fa-undo"></i>
                    </button>
                    ` : ""}
                    <button class="btn btn-sm btn-outline-secondary print-invoice-work-order" 
                            data-invoice-id="${invoice.id}">
                        <i class="fas fa-print"></i>
                    </button>
                </div>
                        </td>
                    `;

                    tbody.appendChild(row);
                    this.setupTooltipHover(row, invoice.id);
                }
            
            
            
            );

                // إضافة مستمعي الأحداث للأزرار داخل المودال


                // فتح المودال
                const modal = new bootstrap.Modal(
                    document.getElementById("workOrderInvoicesModal")
                
                );
                modal.show();
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            this.showError(`${error.message}`, 'فشل في تحميل التفاصيل ,');
        } finally {
            this.hideLoading();
        }
    },

    eventy() {
        // ربط الأحداث لكل أزرار الفواتير في كل الصفحات
        document.addEventListener("click", async function (e) {

            // 👁 زر عرض الفاتورة
            const viewBtn = e.target.closest(".view-invoice-work-order");
            if (viewBtn) {
                const invoiceId = parseInt(viewBtn.dataset.invoiceId);
                await InvoiceManager.showInvoiceDetails(invoiceId);
                return;
            }

            // 💰 زر سداد الفاتورة
            const payBtn = e.target.closest(".pay-invoice-work-order");
            if (payBtn) {
                const invoiceId = parseInt(payBtn.dataset.invoiceId);
                PaymentManager.openSingleInvoicePayment(invoiceId);
                return;
            }

            // 🔄 زر إرجاع الفاتورة
            const returnBtn = e.target.closest(".custom-return-invoice-work-order");
            if (returnBtn) {
                const invoiceId = parseInt(returnBtn.dataset.invoiceId);
                CustomReturnManager.openReturnModal(invoiceId);
                return;
            }

            // 🖨 زر طباعة الفاتورة
            const printBtn = e.target.closest(".print-invoice-work-order");
            if (printBtn) {
                const invoiceId = parseInt(printBtn.dataset.invoiceId);
                PrintManager.printSingleInvoice(invoiceId);
                return;
            }

        });

    },
    //   attachInvoiceEventListeners() {
    //     ("ytrewa");

    //     // 1. زر عرض الفاتورة
    //     document.querySelectorAll(".view-invoice").forEach((btn) => {
    //         btn.addEventListener("click", async function () {
    //             ("uytrvfedcwsxa");

    //             const invoiceId = parseInt(this.getAttribute("data-invoice-id"));
    //             await InvoiceManager.showInvoiceDetails(invoiceId);
    //         });
    //     });

    //     // 2. زر سداد الفاتورة
    //     document.querySelectorAll(".pay-invoice").forEach((btn) => {
    //         btn.addEventListener("click", function () {
    //             const invoiceId = parseInt(this.getAttribute("data-invoice-id"));
    //             PaymentManager.openSingleInvoicePayment(invoiceId);
    //         });
    //     });

    //     // 3. زر إرجاع الفاتورة المخصص
    //     document.querySelectorAll(".custom-return-invoice").forEach((btn) => {
    //         btn.addEventListener("click", function () {
    //             const invoiceId = parseInt(this.getAttribute("data-invoice-id"));
    //             CustomReturnManager.openReturnModal(invoiceId);
    //         });
    //     });

    //     // 4. زر طباعة الفاتورة
    //     document.querySelectorAll(".print-invoice").forEach((btn) => {
    //         btn.addEventListener("click", function () {
    //             const invoiceId = parseInt(this.getAttribute("data-invoice-id"));
    //             PrintManager.printSingleInvoice(invoiceId);
    //         });
    //     });

    //     // 5. تحديد/إلغاء تحديد الفواتير
    //     document.querySelectorAll(".invoice-checkbox").forEach((checkbox) => {
    //         checkbox.addEventListener("change", () => {
    //             InvoiceManager.updateSelectedCount();
    //         });
    //     });

    //     // 6. تحديد الكل
    //     document.getElementById("selectAllInvoices")?.addEventListener("change", function() {
    //         const checkboxes = document.querySelectorAll(".invoice-checkbox");
    //         checkboxes.forEach(cb => cb.checked = this.checked);
    //         InvoiceManager.updateSelectedCount();
    //     });
    // },
    // دوال التحكم داخل مودال عرض الشغلانة
    attachWorkOrderModalEventListeners() {
        // عرض الفاتورة


    }
    ,
    // الدوال المساعدة للـ UI
    showLoading(message = 'جاري التحميل...') {
        const container = document.getElementById("workOrdersContainer");
        if (container) {
            container.innerHTML = `
                <div class="col-12 text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">${message}</p>
                </div>
            `;
        }
    },

    hideLoading() {
        const container = document.getElementById("workOrdersContainer");

        // لو الـ container موجود ومحتواه عبارة عن شاشة تحميل → امسحه

    }
    ,

    showError(title, message) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: title,
                text: message
            });
        } else {
            alert(`${title}: ${message}`);
        }
    },

    // دالة مساعدة لإعادة التهيئة بعد التحديث
    async refresh() {
        await this.fetchWorkOrders();
    },


    async handleCreateWorkOrder() {
        const name = document.getElementById("workOrderName").value.trim();
        const description = document
            .getElementById("workOrderDescription")
            .value.trim();
        const startDate = document.getElementById("workOrderStartDate").value;
        const notes = document.getElementById("workOrderNotes")?.value;

        if (!name || !description || !startDate) {
            Swal.fire("تحذير", "يرجى ملء جميع الحقول المطلوبة", "warning");
            return;
        }
        const workOrderData = {
            customer_id: this.currentCustomerId,
            title: document.getElementById('workOrderName')?.value,
            description: document.getElementById('workOrderDescription')?.value,
            start_date: document.getElementById('workOrderStartDate')?.value,
            status: 'pending',
            notes: notes || '',
        };

        const result = await WorkOrderManager.createWorkOrder(workOrderData);
        if (result.success) {

            const modalEl = document.getElementById("newWorkOrderModal");
            const modal = bootstrap.Modal.getInstance(modalEl);

            // 1️⃣ اقفل Bootstrap Modal أولًا
            if (modal) {
                modal.hide();
            }

            // 2️⃣ استنى المودال يقفل فعليًا
            modalEl.addEventListener('hidden.bs.modal', function handler() {
                modalEl.removeEventListener('hidden.bs.modal', handler);

                // 3️⃣ افتح Swal بعد قفل المودال
                Swal.fire('نجاح', result.message, 'success').then(() => {
                    // تنظيف أي تغييرات على body لو حصلت (fallback آمن)
                    try {
                        // إزالة overflow style إن وُضع
                        if (document.body.style.overflow === 'hidden') {
                            document.body.style.overflow = '';
                        }
                        // إزالة أي backdrops أو كلاسات متبقية لو لزم
                    } catch (e) {
                        console.warn('Cleanup after Swal failed', e);
                    }
                });
                // 4️⃣ reset بعد القفل
                document.getElementById("newWorkOrderForm").reset();
            });

        } else {
            Swal.fire('خطأ', result.message, 'error');
        }


    },
};


export default WorkOrderManager;