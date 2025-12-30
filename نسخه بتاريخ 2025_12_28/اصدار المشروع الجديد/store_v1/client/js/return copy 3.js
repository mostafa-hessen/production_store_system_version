// ReturnManager.js
import AppData from "./app_data.js";
import InvoiceManager from "./invoices.js";
import PrintManager from "./print.js";
import WalletManager from "./transaction.js";
import apiService from "./constant/api_service.js";
import apis from "./constant/api_links.js";

    const ReturnManager = {
        async init() {
            this.setupReturnStyles();
            await this.loadReturnsData();
            this.setupTableEventListeners();

        },


        // في دالة loadReturnsData
        async loadReturnsData() {
            try {
                // تحديث: جلب بيانات المرتجعات من السيرفر
                const response = await apiService.getReturns(AppData.currentCustomer.id);

                if (response.success && response.data) {
                    AppData.returns = response.data.map(returnItem => {
                        return {
                            ...returnItem,
                            return_date_formatted: returnItem.return_date ?
                                new Date(returnItem.return_date).toLocaleDateString('ar-EG') : '',
                            created_at_formatted: returnItem.created_at ?
                                new Date(returnItem.created_at).toLocaleDateString('ar-EG') : ''
                        };
                    });

                    this.updateReturnsTable();
                } else {
                    AppData.returns = [];
                    this.updateReturnsTable();
                }
            } catch (error) {
                console.error('Error loading returns:', error);
                AppData.returns = [];
                this.updateReturnsTable();
            }
        }
        ,
        // إضافة دالة جديدة لعرض تفاصيل المرتجع
        async showReturnDetails(returnId) {
            console.log(returnId);

            try {
                const response = await apiService.getReturnDetails(returnId);
                if (response.success && response.data) {
                    this.populateReturnModal(response.data);
                    const modal = new bootstrap.Modal(document.getElementById('returnDetailsModal'));
                    modal.show();

                    AppData.currentReturn = response.data;



                }
            } catch (error) {
                console.error('Error loading return details:', error);
                Swal.fire('خطأ', data.message, 'error');

            } finally {
                this.hideModalLoading()
            }
        }
        ,
        hideModalLoading() {
            const loadingDiv = document.querySelector(".modal-loading");
            if (loadingDiv) loadingDiv.remove();
        },
        // دالة لملء مودال تفاصيل المرتجع
        populateReturnModal(returnData) {
            const modalContent = document.getElementById('returnDetailsContent');

            if (!modalContent) {
                console.error('Modal content element not found');
                return;
            }

            const ret = returnData.return || {}; // المعلومات الرئيسية للمرتجع
            const items = returnData.items || []; // بنود المرتجع

            // بناء HTML البنود
            let itemsHtml = '';
            if (items.length > 0) {
                items.forEach(item => {
                    itemsHtml += `
                    <tr>
                        <td>${item.product_name || `المنتج ${item.product_id}`}</td>
                        <td>${parseFloat(item.quantity).toFixed(2)}</td>
                        <td>${parseFloat(item.return_price).toFixed(2)} ج.م</td>
                        <td>${parseFloat(item.total_amount).toFixed(2)} ج.م</td>
                        <td>
                            <span class="badge ${item.status === 'restocked' ? 'bg-success' :
                            item.status === 'discarded' ? 'bg-danger' :
                                'bg-warning'}">
                                ${item.status === 'restocked' ? 'مخزن' :
                            item.status === 'discarded' ? 'مهمل' :
                                'معلق'}
                            </span>
                        </td>
                    </tr>
                `;
                });
            }

            modalContent.innerHTML = `
            <div class="container-fluid">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">معلومات المرتجع</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-6">
                                        <small class="text-muted">رقم المرتجع</small>
                                        <div class="fw-bold note-text">#RET-${ret.return_id || 'N/A'}</div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">تاريخ المرتجع</small>
                                        <div class="fw-bold note-text">
                                            ${ret.return_date ? new Date(ret.return_date).toLocaleDateString('ar-EG') : 'N/A'}
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-6">
                                        <small class="text-muted">نوع المرتجع</small>
                                        <div>
                                            ${ret.return_type === 'full' ?
                    '<span class="badge badge-return-full">كامل</span>' :
                    ret.return_type === 'partial' ?
                        '<span class="badge badge-return-partial">جزئي</span>' :
                        '<span class="badge badge-return-partial">تبادل</span>'}
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">الحالة</small>
                                        <div>
                                            ${ret.status === 'completed' ?
                    '<span class="badge badge-paid">مكتمل</span>' :
                    ret.status === 'approved' ?
                        '<span class="badge bg-info">معتمد</span>' :
                        ret.status === 'pending' ?
                            '<span class="badge badge-pending">معلق</span>' :
                            '<span class="badge bg-danger">مرفوض</span>'}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">المعلومات المالية</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-6">
                                        <small class="text-muted">المبلغ الإجمالي</small>
                                        <div class="fw-bold text-success fs-5">
                                            ${parseFloat(ret.total_amount || 0).toFixed(2)} ج.م
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">السبب</small>
                                        <div class="text-muted">${ret.reason || 'لا يوجد'}</div>
                                    </div>
                                </div>
                                ${ret.reason ? `
                                <div class="row mt-2">
                                    <div class="col-12">
                                        <small class="text-muted">ملاحظات</small>
                                        <div class="alert  mt-1" style="background-color: var(--surface-2); border: 1px solid var(--border); color: var(--text);">
                                            ${ret.reason}
                                        </div>
                                    </div>
                                </div>` : ''}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">بنود المرتجع</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive custom-table-wrapper">
                                    <table class="custom-table">
                                        <thead class="center">
                                            <tr>
                                                <th>المنتج</th>
                                                <th>الكمية</th>
                                                <th>سعر المرتجع</th>
                                                <th>الإجمالي</th>
                                                <th>حالة الصنف</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${itemsHtml || `
                                            <tr>
                                                <td colspan="5" class="text-center text-muted">
                                                    لا توجد بنود
                                                </td>
                                            </tr>`}
                                        </tbody>
                                        <tfoot>
                                            <tr class="table-active">
                                                <td colspan="3" class="text-end fw-bold">المجموع:</td>
                                                <td class="fw-bold text-success">
                                                    ${parseFloat(ret.total_amount || 0).toFixed(2)} ج.م
                                                </td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        }
        ,

        setupReturnStyles() {
            const style = document.createElement('style');
            style.textContent = `
                .return-row {
                    transition: all var(--fast);
                    border-left: 3px solid transparent;
                }
                .return-row:hover {
                    background: var(--surface-2);
                    border-left-color: var(--primary);
                    transform: translateX(2px);
                }
                
                .badge-return {
                    padding: 4px 10px;
                    border-radius: 20px;
                    font-size: 0.85rem;
                    font-weight: 500;
                }
                
                .badge-return-full {
                    background: var(--grad-3);
                    color: white;
                }
                
                .badge-return-partial {
                    background: var(--grad-2);
                    color: white;
                }
                
                .badge-method-wallet {
                    background: var(--grad-1);
                    color: white;
                }
                
                .badge-method-cash {
                    background: var(--grad-4);
                    color: white;
                }
                
                .badge-method-credit {
                    background: linear-gradient(135deg, var(--amber), var(--rose));
                    color: white;
                }
                
                .status-badge {
                    padding: 5px 12px;
                    border-radius: var(--radius-sm);
                    font-size: 0.85rem;
                    font-weight: 500;
                    display: inline-block;
                }
                
                .badge-paid {
                    background: linear-gradient(135deg, #10b981, #0ea5e9);
                    color: white;
                }
                
                .badge-pending {
                    background: linear-gradient(135deg, var(--amber), #f97316);
                    color: white;
                }
                
                .items-preview {
                    max-height: 100px;
                    overflow-y: auto;
                    padding-right: 8px;
                }
                
                .items-preview::-webkit-scrollbar {
                    width: 4px;
                }
                
                .items-preview::-webkit-scrollbar-thumb {
                    background: var(--border);
                    border-radius: 2px;
                }
                
                .amount-display {
                    position: relative;
                    padding: 8px 12px;
                    background: var(--surface-2);
                    border-radius: var(--radius-sm);
                    border: 1px solid var(--border);
                }
                
                .action-buttons {
                    display: flex;
                    gap: 6px;
                    flex-wrap: wrap;
                }
                
                .btn-sm-icon {
                    width: 32px;
                    height: 32px;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    border-radius: var(--radius-sm);
                    transition: all var(--fast);
                }
                
                .btn-sm-icon:hover {
                    transform: translateY(-1px);
                    box-shadow: var(--shadow-1);
                }
                
                [data-theme="dark"] .return-row:hover {
                    background: var(--surface);
                    border-left-color: var(--primary);
                }
            `;
            document.head.appendChild(style);
        },

        /**
     * استخراج طريقة الاسترداد من بنود المرتجع (نسخة مبسطة)
     */
        getRefundMethodFromItems(items) {
            if (!items || !Array.isArray(items) || items.length === 0) {
                return "credit_adjustment";
            }

            // أخذ طريقة الاسترداد من أول بند
            const firstItem = items[0];
            if (!firstItem.refund_preference) {
                return "credit_adjustment";
            }

            const method = firstItem.refund_preference.toLowerCase();

            if (method.includes('wallet') || method.includes('محفظة')) {
                return "wallet";
            } else if (method.includes('cash') || method.includes('نقدي')) {
                return "cash";
            } else if (method.includes('credit') || method.includes('خصم') || method.includes('آجل')) {
                return "credit_adjustment";
            }

            return "credit_adjustment";
        },
        updateReturnsTable(data = null) {
            const tbody = document.getElementById("returnsTableBody");
            if (!tbody) {
                console.warn('Element #returnsTableBody not found');
                return;
            }

            // استخدام البيانات المقدمة أو البيانات من AppData
            const returnsData = data || AppData.returns || [];

            tbody.innerHTML = "";

            if (!returnsData || returnsData.length === 0) {
                tbody.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center py-5">
                        <div class="text-muted">
                            <i class="fas fa-undo fa-2x mb-3"></i>
                            <p>لا توجد مرتجعات</p>
                        </div>
                    </td>
                </tr>
            `;
                return;
            }


            returnsData.forEach((returnItem) => {
                const row = document.createElement("tr");
                row.className = "return-row";

                // تحديد نوع المرتجع
                let typeBadge = "";
                if (returnItem.return_type === "full") {
                    typeBadge = '<span class="badge-return badge-return-full">كامل</span>';
                } else if (returnItem.return_type === "partial") {
                    typeBadge = '<span class="badge-return badge-return-partial">جزئي</span>';
                } else if (returnItem.return_type === "exchange") {
                    typeBadge = '<span class="badge-return badge-return-partial">تبادل</span>';
                } else {
                    typeBadge = '<span class="badge-return badge-return-partial">غير محدد</span>';
                }

                // تحديد طريقة الاسترداد (من البنود)
                let refundMethod = this.getRefundMethodFromItems(returnItem.items);
                let methodBadge = "";
                if (refundMethod === "wallet") {
                    methodBadge = '<span class="badge-return badge-method-wallet">محفظة</span>';
                } else if (refundMethod === "cash") {
                    methodBadge = '<span class="badge-return badge-method-cash">نقدي</span>';
                } else if (refundMethod === "credit_adjustment" || refundMethod === "خصم من المتبقي") {
                    methodBadge = '<span class="badge-return badge-method-credit">تعديل آجل</span>';
                } else {
                    methodBadge = '<span class="badge-return badge-method-credit">غير محدد</span>';
                }

                // تحديد حالة المرتجع
                let statusBadge = "";
                if (returnItem.status === "completed") {
                    statusBadge = '<span class="status-badge badge-paid">مكتمل</span>';
                } else if (returnItem.status === "approved") {
                    statusBadge = '<span class="status-badge badge-approved">معتمد</span>';
                } else if (returnItem.status === "pending") {
                    statusBadge = '<span class="status-badge badge-pending">معلق</span>';
                } else if (returnItem.status === "rejected") {
                    statusBadge = '<span class="status-badge badge-rejected">مرفوض</span>';
                } else {
                    statusBadge = `<span class="status-badge badge-pending">${returnItem.status || 'معلق'}</span>`;
                }

                let totalReturnedItems = 0;
                // عرض بنود المرتجع
                let itemsList = "";
                if (returnItem.items && returnItem.items.length > 0) {
                    returnItem.items.forEach((item) => {
                        totalReturnedItems += item.returned_quantity || 0;
                        itemsList += `<div class="d-flex justify-content-between small border-bottom pb-1 mb-1">
                                    <span>${item.product_name || `المنتج ${item.product_id}`}</span>
                                    <span>${item.returned_quantity} </span>
                                </div>`;
                    });
                }

                // تحضير التاريخ للعرض
                const dateToDisplay = returnItem.return_date_formatted ||
                    returnItem.created_at_formatted ||
                    new Date(returnItem.return_date || returnItem.created_at).toLocaleDateString('ar-EG');

                // تحضير المبلغ الإجمالي
                const totalAmount = parseFloat(returnItem.total_amount) || 0;

                row.innerHTML = `
                <td>
                    <div class="d-flex flex-column">
                        <strong class="text-primary">#RET-${returnItem.id}</strong>
                        <button class="btn btn-sm btn-link p-0 mt-1 view-original-invoice" 
                                data-invoice-id="${returnItem.invoice_info?.id || returnItem.invoice_id}">
                            <i class="fas fa-external-link-alt me-1"></i> عرض الفاتورة
                        </button>
                    </div>
                </td>
                <td>
                    <div class="d-flex flex-column">
                        <a href="#" class="text-decoration-none view-invoice-from-return" 
                        data-invoice-id="${returnItem.invoice_info?.id || returnItem.invoice_id}">
                            <span class="fw-bold">#${returnItem.invoice_info?.id || returnItem.invoice_id}</span>
                        </a>
                        <small class="text-muted mt-1">${returnItem.reason || ''}</small>
                    </div>
                </td>
                <td>
                    <div class="items-preview">
                        ${itemsList}
                    </div>
                </td>
                <td>
                    <span class="badge bg-light text-dark">
                        ${totalReturnedItems ?? 0}
                    </span>
                </td>
                <td>
                    <div class="amount-display">
                        <div class="fw-bold text-success">${totalAmount.toFixed(2)} ج.م</div>
                        <small class="text-muted">${typeBadge}</small>
                    </div>
                </td>
                <td>${methodBadge}</td>
                <td>${statusBadge}</td>
                <td>
                    <div class="small text-muted">${dateToDisplay}</div>
                    <div class="small text-muted">
                        ${returnItem.created_by_name ? `بواسطة: ${returnItem.created_by_name}` : ''}
                    </div>
                </td>
            

                        <td>
            <button class="bt btn-sm btn-outline-info btn-sm-icon view-return-details" 
                    data-return-id="${returnItem.id}"
                    title="عرض بنود المرتجع">
                <i class="fas fa-eye"></i>
            </button>
            <button class="bt btn-sm btn-outline-primary btn-sm-icon view-original-invoice" 
                    data-invoice-id="${returnItem.invoice_info?.id || returnItem.invoice_id}"
                    title="عرض الفاتورة">
                <i class="fas fa-file-invoice"></i>
            </button>
            ${returnItem.status === 'pending' ? `
                <button class="btn btn-sm btn-outline-success btn-sm-icon approve-return" 
                        data-return-id="${returnItem.id}"
                        title="اعتماد المرتجع">
                    <i class="fas fa-check"></i>
                </button>
            ` : ''}
        </div>
    </td>
            

            `;

                tbody.appendChild(row);
            });

            // إضافة مستمعي الأحداث للأزرار الجديدة
            this.setupTableEventListeners();
        },
        // إضافة دالة جديدة في ReturnManager
        setupTableEventListeners() {
            const tbody = document.getElementById("returnsTableBody");
            if (!tbody) return;

            // مستمع لزر عرض تفاصيل المرتجع
            tbody.addEventListener('click', async (e) => {
                const viewReturnBtn = e.target.closest('.view-return-details');
                const viewInvoiceBtn = e.target.closest('.view-original-invoice');

                if (viewReturnBtn) {
                    const returnId = viewReturnBtn.getAttribute('data-return-id');
                    await this.showReturnDetails(returnId);
                }

                if (viewInvoiceBtn) {
                    const invoiceId = viewInvoiceBtn.getAttribute('data-invoice-id');
                    // استدعاء دالة عرض الفاتورة من InvoiceManager
                    if (typeof InvoiceManager !== 'undefined' && InvoiceManager.showInvoiceDetails) {
                        InvoiceManager.showInvoiceDetails(invoiceId);
                    }
                }
            });
        }

        ,

        async addReturn(returnData) {


            try {
                // إرسال بيانات الإرجاع إلى الباك إند
                const response = await apiService.createReturn(returnData);





                if (response.success) {

                    // إضافة المرتجع إلى البيانات المحلية
                    // const newReturn = {
                    //     id: response.return_id,
                    //     invoice_id: returnData.invoice_id,
                    //     customer_id: returnData.customer_id,
                    //     return_type: returnData.return_type,
                    //     total_amount: returnData.total_amount || response.total_amount,
                    //     status: response.status || 'approved',
                    //     reason: returnData.reason,
                    //     items: returnData.items,
                    //     return_date: new Date().toISOString(),
                    //     created_at: new Date().toISOString()
                    // };

                    // AppData.returns.unshift(newReturn);
                    // this.updateReturnsTable();

                    return {
                        success: true,
                        return_id: response.return_id,
                        message: response.message || 'تم إنشاء الإرجاع بنجاح'
                    };
                } else {
                    throw new Error(response.message || 'فشل في إنشاء الإرجاع');
                }
            } catch (error) {
                console.error('Error adding return:', error);
                return {
                    success: false,
                    message: error.message || 'حدث خطأ أثناء إنشاء الإرجاع'
                };
            }
        }
    };

const CustomReturnManager = {
    currentInvoiceId: null,
    returnItems: [],
    currentInvoiceData: null,
    customerData: null,

    async openReturnModal(invoiceId) {
        this.currentInvoiceId = invoiceId;
        this.returnItems = [];

        try {
            // جلب بيانات الفاتورة من الباك إند
            const response = await apiService.getInvoiceForReturn(invoiceId);
            if (response) {
                this.currentInvoiceData = response;
                // this.customerData = response.customer;

                // this.setupModalStyles();
                this.populateModalData();

                // فتح المودال
                const modal = new bootstrap.Modal(document.getElementById("customReturnModal"));
                modal.show();
            } else {
                Swal.fire({
                    title: "خطأ",
                    text: response.message || "الفاتورة غير موجودة",
                    icon: "error",
                    confirmButtonColor: "var(--primary)",
                    background: "var(--surface)",
                    color: "var(--text)"
                });
            }
        } catch (error) {
            Swal.fire({
                title: "خطأ",
                text: "حدث خطأ في جلب بيانات الفاتورة",
                icon: "error",
                confirmButtonColor: "var(--primary)",
                background: "var(--surface)",
                color: "var(--text)"
            });
        }
    },

    populateModalData() {
        const invoice = this.currentInvoiceData;
        // const customer = this.customerData;


        // تعبئة معلومات الفاتورة
        document.getElementById("returnInvoiceNumber").textContent = `#${invoice.id}`;
        document.getElementById("returnInvoiceDate").textContent = new Date(invoice.date).toLocaleDateString('ar-EG');
        document.getElementById("returnInvoiceTotal").textContent = invoice.total?.toFixed(2) + " ج.م";

        // تعبئة معلومات الدفع
        // document.getElementById("originalPaymentMethod").innerHTML = `
        //     <span class="badge ${this.getPaymentMethodBadge(invoice.payment_method)}">
        //         ${this.getPaymentMethodText(invoice.payment_method)}
        //     </span>
        // `;

        document.getElementById("paymentStatus").innerHTML = `
            <span class="badge ${this.getPaymentStatusBadge(invoice)}">
                ${this.getPaymentStatusText(invoice)}
            </span>
        `;

        document.getElementById("invoicePaidAmount").textContent = invoice.paid?.toFixed(2) + " ج.م";
        document.getElementById("invoiceRemainingAmount").textContent = invoice.remaining?.toFixed(2) + " ج.م";

        // تعبئة بنود الفاتورة
        this.populateReturnItems(invoice.items || []);

        // إضافة مستمعي الأحداث للأزرار
        document.getElementById("returnAllBtn").onclick = () => this.returnAllItems();
        document.getElementById("returnPartialBtn").onclick = () => this.returnPartialItems();
        document.getElementById("processCustomReturnBtn").onclick = () => this.processReturn();
    },

    getPaymentMethodText(method) {
        const methods = {
            "credit": "آجل",
            "wallet": "محفظة",
            "cash": "نقدي"
        };
        return methods[method] || method;
    },

    getPaymentMethodBadge(method) {
        const badges = {
            "credit": "badge-method-credit",
            "wallet": "badge-method-wallet",
            "cash": "badge-method-cash"
        };
        return badges[method] || "badge-method-cash";
    },

    getPaymentStatusText(invoice) {
        if (invoice.paid_amount === 0 || invoice.status === 'pending') {
            return "لم يدفع";
        } else if (invoice.paid_amount >= invoice.total_after_discount || invoice.status === 'paid') {
            return "مدفوع بالكامل";
        } else {
            return "مدفوع جزئياً";
        }
    },

    getPaymentStatusBadge(invoice) {
        if (invoice.paid_amount === 0 || invoice.status === 'pending') {

            return "bg-gradient-3";
        } else if (invoice.paid_amount >= invoice.total_after_discount || invoice.status === 'paid') {
            return "bg-gradient-2";
        } else {
            return "bg-gradient-1";
        }
    },

    populateReturnItems(items) {
        const container = document.getElementById("customReturnItemsContainer");
        container.innerHTML = "";

        items.forEach((item, index) => {
            const availableQuantity = item.quantity - (item.returned_quantity || 0);

            if (availableQuantity > 0) {
                const itemElement = document.createElement("div");
                itemElement.className = "return-modal-card";
                itemElement.setAttribute("data-item-index", index);

                const unitPriceAfterDiscount = item.total_after_discount / item.quantity;




                itemElement.innerHTML = `
                    <div class="return-item-header">
                        <div>
                            <h6 class="mb-1">${item.product_name || `المنتج ${item.product_id}`}</h6>
                            <div class="small text-muted">
                                <span class="me-3">السعر: ${unitPriceAfterDiscount?.toFixed(2)} ج.م</span>
                                <span>متاح للإرجاع: <strong>${availableQuantity}</strong></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-3">
                            <div class="quantity-input-group">
                                <label class="form-label small text-muted">الكمية الأصلية</label>
                                <input type="number" class="form-control bg-light" value="${item.quantity}" readonly>
                                <span class="input-label">وحدة</span>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="quantity-input-group">
                                <label class="form-label small text-muted">مرتجع سابق</label>
                                <input type="number" class="form-control" 
                                       value="${item.returned_quantity || 0}" readonly
                                       style="background: linear-gradient(135deg, var(--amber), #f97316); color: white;">
                                <span class="input-label">وحدة</span>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="quantity-input-group">
                                <label class="form-label small text-muted">الكمية الحالية</label>
                                <input type="number" class="form-control" 
                                       value="${availableQuantity}" readonly
                                       style="background: linear-gradient(135deg, #10b981, #0ea5e9); color: white;">
                                <span class="input-label">وحدة</span>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="quantity-input-group">
                                <label class="form-label small text-muted text-primary">كمية الإرجاع</label>
                                <input type="number" class="form-control custom-return-quantity border-primary" 
                                       data-item-index="${index}" min="0" max="${availableQuantity}" 
                                       value="0" data-max="${availableQuantity}" 
                                       data-unit-price="${unitPriceAfterDiscount}"
                                       data-invoice-item-id="${item.id}"
                                       data-product-id="${item.product_id}"
                                       placeholder="0">
                                <span class="input-label">وحدة</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-8">
                            <div class="validation-message" id="validation-${index}"></div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-end">
                                <label class="form-label small text-muted">الإجمالي</label>
                                <div class="fw-bold text-primary fs-5">
                                    <span class="custom-return-total" data-item-index="${index}">0.00</span> ج.م
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                container.appendChild(itemElement);

                // إضافة مستمعي الأحداث
                const quantityInput = itemElement.querySelector(".custom-return-quantity");
                quantityInput.addEventListener("input", (e) => {
                    this.validateReturnItem(index, e.target);
                    this.updateReturnItem(index);
                });
            }
        });

        this.updateReturnTotal();
    },

    validateReturnItem(itemIndex, inputElement) {
        const value = parseFloat(inputElement.value) || 0;
        const max = parseFloat(inputElement.getAttribute("data-max"));
        const validationMessage = document.getElementById(`validation-${itemIndex}`);

        if (value > max) {
            validationMessage.innerHTML = `<div class="alert alert-danger alert-sm mb-0">
                <i class="fas fa-exclamation-circle me-1"></i>
                خطأ: لا يمكن إرجاع أكثر من ${max}
            </div>`;
            inputElement.classList.add("is-invalid");
            inputElement.value = max;
            this.updateReturnItem(itemIndex);
            return false;
        } else if (value < 0) {
            validationMessage.innerHTML = `<div class="alert alert-danger alert-sm mb-0">
                <i class="fas fa-exclamation-circle me-1"></i>
                خطأ: القيمة يجب أن تكون موجبة
            </div>`;
            inputElement.classList.add("is-invalid");
            inputElement.value = 0;
            this.updateReturnItem(itemIndex);
            return false;
        } else {
            validationMessage.innerHTML = "";
            inputElement.classList.remove("is-invalid");
            return true;
        }
    },

    updateReturnItem(itemIndex) {
        const quantityInput = document.querySelector(`.custom-return-quantity[data-item-index="${itemIndex}"]`);
        const totalInput = document.querySelector(`.custom-return-total[data-item-index="${itemIndex}"]`);

        const quantity = parseFloat(quantityInput.value) || 0;
        const unitPrice = parseFloat(quantityInput.getAttribute("data-unit-price"));

        const total = quantity * +unitPrice;
        totalInput.textContent = total?.toFixed(2);

        this.updateReturnTotal();
    },

    updateReturnTotal() {
        let totalAmount = 0;
        let hasErrors = false;
        const returnItemsData = [];

        // جمع المبلغ الإجمالي والإرجاع
        document.querySelectorAll(".custom-return-quantity").forEach((input) => {
            const value = parseFloat(input.value) || 0;
            const max = parseFloat(input.getAttribute("data-max"));

            if (value > max) {
                hasErrors = true;
            }

            const itemIndex = parseInt(input.getAttribute("data-item-index"));
            const unitPrice = parseFloat(input.getAttribute("data-unit-price"));
            const invoiceItemId = input.getAttribute("data-invoice-item-id");
            const productId = input.getAttribute("data-product-id");

            totalAmount += value * unitPrice;

            if (value > 0) {
                returnItemsData.push({
                    invoice_item_id: invoiceItemId,
                    product_id: productId,
                    quantity: value,
                    unit_price: unitPrice,
                    total: value * unitPrice
                });
            }
        });

        // حفظ بيانات الإرجاع للاستخدام لاحقاً
        this.returnItems = returnItemsData;

        const totalElement = document.getElementById("customReturnTotalAmount");
        totalElement.textContent = totalAmount?.toFixed(2) + " ج.م";

        if (totalAmount > 0 && !hasErrors) {
            totalElement.className = "fw-bold text-success fs-4";

            // حساب التأثير المالي
            const impact = this.calculateReturnImpact(totalAmount);
            this.displayImpactDetails(impact);

            // تفعيل زر المعالجة
            document.getElementById("processCustomReturnBtn").disabled = false;
        } else {
            document.getElementById("impactDetails").style.display = "none";
            document.getElementById("refundMethodSection").style.display = "none";
            document.getElementById("processCustomReturnBtn").disabled = true;
        }
    },

    calculateReturnImpact(totalReturnAmount) {
        const invoice = this.currentInvoiceData;
        let amountFromRemaining = 0;
        let amountFromPaid = 0;
        let refundMethod = "credit_adjustment";

        if (invoice.paid_amount === 0) {
            // فاتورة مؤجلة
            amountFromRemaining = Math.min(totalReturnAmount, invoice.remaining_amount);
            refundMethod = "خصم من المتبقي";
        } else if (invoice.paid_amount >= invoice.total_after_discount) {
            // فاتورة مدفوعة كلياً
            amountFromPaid = Math.min(totalReturnAmount, invoice.paid_amount);
            refundMethod = "pending_choice";
        } else {
            // فاتورة مدفوعة جزئياً
            amountFromRemaining = Math.min(totalReturnAmount, invoice.remaining_amount);
            const remainingAfterDeduction = totalReturnAmount - amountFromRemaining;
            if (remainingAfterDeduction > 0) {
                amountFromPaid = Math.min(remainingAfterDeduction, invoice.paid_amount);
                refundMethod = "pending_choice";
            }
        }

        return {
            amountFromRemaining,
            amountFromPaid,
            refundMethod,
            totalReturnAmount
        };
    },

    displayImpactDetails(impact) {
        const detailsContainer = document.getElementById("impactDetails");
        detailsContainer.style.display = "block";

        let detailsHTML = `
        <div class="impact-card">
            <div class="impact-header">
                <i class="fas fa-calculator"></i>
                <strong class="text_muted">تفاصيل التأثير المالي</strong>
            </div>
        `;

        if (impact.amountFromRemaining > 0) {
            detailsHTML += `
            <div class="alert alert-warning mb-2">
                <div class="d-flex align-items-center">
                    <i class="fas fa-minus-circle text-amber me-2 fs-5"></i>
                    <div class="flex-grow-1">
                        <div class="fw-bold">يخصم من المتبقي</div>
                        <div class="text-amber fw-bold fs-5">${impact.amountFromRemaining?.toFixed(2)} ج.م</div>
                    </div>
                </div>
            </div>
            `;
        }

        if (impact.amountFromPaid > 0) {
            detailsHTML += `
            <div class="alert alert-success mb-2">
                <div class="d-flex align-items-center">
                    <i class="fas fa-undo text-teal me-2 fs-5"></i>
                    <div class="flex-grow-1">
                        <div class="fw-bold">يُرد للعميل</div>
                        <div class="text-teal fw-bold fs-5">${impact.amountFromPaid?.toFixed(2)} ج.م</div>
                    </div>
                </div>
            </div>
            `;
        }

        detailsContainer.innerHTML = detailsHTML;

        // عرض قسم اختيار طريقة الرد إذا كان هناك مبلغ يرد
        const refundMethodSection = document.getElementById("refundMethodSection");
        if (impact.amountFromPaid > 0) {
            refundMethodSection.style.display = "block";
            this.setupRefundOptions(impact);
        } else {
            refundMethodSection.style.display = "none";
        }
    },

    setupRefundOptions(impact) {
        const refundOptions = document.getElementById("refundOptions");
        refundOptions.innerHTML = `
            <div class="form-group">
                <label class="form-label fw-bold mb-3">
                    <i class="fas fa-money-bill-wave me-2"></i>
                    اختر طريقة رد المبلغ (${impact.amountFromPaid?.toFixed(2)} ج.م)
                </label>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="refund-option-card selected">
                            <input class="form-check-input" type="radio" name="refundMethodChoice" 
                                   id="cashChoice" value="cash" checked>
                            <label class="form-check-label w-100" for="cashChoice">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <i class="fas fa-money-bill-wave fa-2x text-success"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-1">استرجاع نقدي</h6>
                                        <p class="small text-muted mb-0">سيتم رد المبلغ نقداً للعميل</p>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="refund-option-card">
                            <input class="form-check-input" type="radio" name="refundMethodChoice" 
                                   id="walletChoice" value="wallet">
                            <label class="form-check-label w-100" for="walletChoice">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <i class="fas fa-wallet fa-2x text-primary"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-1">إضافة للمحفظة</h6>
                                        <p class="small text-muted mb-0">سيتم إضافة المبلغ لمحفظة العميل</p>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        `;
    },

    returnAllItems() {
        document.querySelectorAll(".custom-return-quantity").forEach((input) => {
            const max = parseFloat(input.getAttribute("data-max"));
            input.value = max;
            const itemIndex = input.getAttribute("data-item-index");
            this.validateReturnItem(itemIndex, input);
            this.updateReturnItem(itemIndex);
        });

        Swal.fire({
            title: "تم تحديد الكل",
            text: "تم تحديد جميع الكميات المتاحة للإرجاع",
            icon: "success",
            timer: 1500,
            showConfirmButton: false,
            background: "var(--surface)",
            color: "var(--text)"
        });
    },

    returnPartialItems() {
        document.querySelectorAll(".custom-return-quantity").forEach((input) => {
            input.disabled = false;
            input.focus();
        });
    },

    async processReturn() {
        // التحقق من صحة البيانات المدخلة
        let hasErrors = false;
        const errorMessages = [];

        document.querySelectorAll(".custom-return-quantity").forEach((input) => {
            const itemIndex = input.getAttribute("data-item-index");
            if (!this.validateReturnItem(itemIndex, input)) {
                hasErrors = true;
            }
        });

        if (hasErrors) {
            Swal.fire({
                title: "تحذير",
                text: "يوجد أخطاء في الكميات المدخلة، يرجى تصحيحها أولاً",
                icon: "warning",
                confirmButtonColor: "var(--amber)",
                background: "var(--surface)",
                color: "var(--text)"
            });
            return;
        }

        const returnReason = document.getElementById("customReturnReason").value.trim();
        if (!returnReason) {
            Swal.fire({
                title: "تحذير",
                text: "يرجى إدخال سبب الإرجاع",
                icon: "warning",
                confirmButtonColor: "var(--amber)",
                background: "var(--surface)",
                color: "var(--text)"
            });
            return;
        }

        // تحديد طريقة الاسترداد
        let refundPreference = "خصم من المتبقي";
        const refundMethodInput = document.querySelector('input[name="refundMethodChoice"]:checked');
        if (refundMethodInput) {
            refundPreference = refundMethodInput.value;
        }

        // حساب إجمالي المبلغ
        const totalReturnAmount = this.returnItems.reduce((sum, item) => sum + item.total, 0);

        // التحقق إذا كان الإرجاع كاملاً
        const allItemsReturned = this.returnItems.every(item => {
            const input = document.querySelector(`.custom-return-quantity[data-invoice-item-id="${item.invoice_item_id}"]`);
            const max = parseFloat(input?.getAttribute("data-max") || 0);
            return item.quantity === max;
        });

        const returnType = allItemsReturned ? "full" : "partial";

        // إعداد بيانات الإرجاع
        const returnData = {
            invoice_id: this.currentInvoiceId,
            customer_id: AppData.currentCustomer?.id,
            return_type: returnType,
            reason: document.getElementById("customReturnReason").value,
            items: this.returnItems.map(item => ({
                invoice_item_id: item.invoice_item_id,
                product_id: item.product_id,
                quantity: item.quantity,
                reason: document.getElementById("customReturnReason").value,
                refund_preference: refundPreference
            }))
        };


        // عرض تأكيد
        const confirmResult = await Swal.fire({
            title: "تأكيد عملية الإرجاع",
            html: `
                <div class="text-start">
                    <p>هل أنت متأكد من تنفيذ عملية الإرجاع؟</p>
                    <div class="alert alert-info">
                        <strong>تفاصيل الإرجاع:</strong>
                        <div class="mt-2">
                            <div>نوع الإرجاع: <strong>${returnType === 'full' ? 'كامل' : 'جزئي'}</strong></div>
                            <div>المبلغ الإجمالي: <strong class="text-success">${totalReturnAmount?.toFixed(2)} ج.م</strong></div>
                            <div>عدد المنتجات: <strong>${this.returnItems.length}</strong></div>
                            <div>طريقة الاسترداد: <strong>${refundPreference}</strong></div>
                        </div>
                    </div>
                </div>
            `,
            icon: "question",
            showCancelButton: true,
            confirmButtonText: "نعم، تأكيد",
            cancelButtonText: "إلغاء",
            confirmButtonColor: "var(--primary)",
            cancelButtonColor: "var(--rose)",
            background: "var(--surface)",
            color: "var(--text)"
        });

        if (confirmResult.isConfirmed) {
            // إرسال البيانات إلى الباك إند
            const loadingSwal = Swal.fire({
                title: "جاري المعالجة...",
                text: "يرجى الانتظار قليلاً",
                allowOutsideClick: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });

            try {

                const response = await ReturnManager.addReturn(returnData);

                await loadingSwal.close();

                console.log(response);

                console.log(response.ok);
                console.log(response.success);

                if (response.success) {

                    Swal.fire({
                        title: "تم بنجاح",
                        text: `تم إنشاء الإرجاع برقم #RET-${response.return_id}`,
                        icon: "success",
                        confirmButtonColor: "var(--primary)",
                        background: "var(--surface)",
                        color: "var(--text)"
                    });

                    // إغلاق المودال
                    const modal = bootstrap.Modal.getInstance(document.getElementById("customReturnModal"));
                    if (modal) {
                        modal.hide();
                    }

                    // تحديث صفحة الفاتورة إذا كانت مفتوحة
                    // if (typeof InvoiceManager !== 'undefined') {
                    //     InvoiceManager?.refreshCurrentInvoice();
                    // }
                } else {
                    Swal.fire({
                        title: "خطأ",
                        text: response.message,
                        icon: "error",
                        confirmButtonColor: "var(--primary)",
                        background: "var(--surface)",
                        color: "var(--text)"
                    });
                }
            } catch (error) {
                await loadingSwal.close();
                Swal.fire({
                    title: "خطأ",
                    text: "حدث خطأ أثناء معالجة الإرجاع",
                    icon: "error",
                    confirmButtonColor: "var(--primary)",
                    background: "var(--surface)",
                    color: "var(--text)"
                });
            }
        }
    }
};

export { ReturnManager, CustomReturnManager };