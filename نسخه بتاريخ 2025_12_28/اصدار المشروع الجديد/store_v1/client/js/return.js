// ReturnManager.js - النسخة المحسنة
import AppData from "./app_data.js";
import InvoiceManager from "./invoices.js";
import PrintManager from "./print.js";
import WalletManager from "./wallet.js";
import apiService from "./constant/api_service.js";
import apis from "./constant/api_links.js";
import CustomerManager from "./customer.js";
import CustomerTransactionManager from "./transaction.js";
import WorkOrderManager from "./work_order.js";
import UIManager from "./ui.js";
import { updateInvoiceStats } from "./helper.js";


    const ReturnManager = {
        async init() {
            this.setupReturnStyles();
            await this.loadReturnsData();
            this.setupTableEventListeners();

        },


        async refreshDataAfterPayment(customerId) {
        try {
            // // تحديث بيانات العميل


            await CustomerManager.init();
            InvoiceManager.init();
            CustomerTransactionManager.init();
            WorkOrderManager.init();
            await WalletManager.init();
            UIManager.init();


            // تحديث الإحصائيات
            updateInvoiceStats();
        } catch (error) {
            console.error('Error refreshing data:', error);
            // يمكن إعادة تحميل الصفحة كحل بديل
            // window.location.reload();
        }
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
        getRefundMethodFromItems(returnItem) {
           

      
            if (!returnItem.refund_preference) {
                return "credit_adjustment";
            }

            const method = returnItem.refund_preference.toLowerCase();

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
                let refundMethod = this.getRefundMethodFromItems(returnItem);
                let methodBadge = "";
                if (refundMethod === "wallet") {
                    methodBadge = '<span class="badge-return badge-method-wallet">محفظة</span>';
                } else if (refundMethod === "cash") {
                    methodBadge = '<span class="badge-return badge-method-cash">نقدي</span>';
                } 
                else if (refundMethod === "credit_adjustment" || refundMethod === "خصم من المتبقي") {
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
this.refreshDataAfterPayment(AppData.currentCustomer.id);
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
            const response = await apiService.getInvoiceForReturn(invoiceId);
            if (response) {
                this.currentInvoiceData = response;
                this.populateModalData();
                
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
        
        // تعبئة معلومات الفاتورة
        document.getElementById("returnInvoiceNumber").textContent = `#${invoice.id}`;
        document.getElementById("returnInvoiceDate").textContent = invoice.date ? 
            new Date(invoice.date).toLocaleDateString('ar-EG') : '';
        document.getElementById("returnInvoiceTotal").textContent = 
            parseFloat(invoice.total || 0).toFixed(2) + " ج.م";
        
        // تعبئة حالة الدفع
        document.getElementById("paymentStatus").innerHTML = this.getPaymentStatusHtml(invoice);
        document.getElementById("invoicePaidAmount").textContent = 
            parseFloat(invoice.paid || 0).toFixed(2) + " ج.م";
        document.getElementById("invoiceRemainingAmount").textContent = 
            parseFloat(invoice.remaining || 0).toFixed(2) + " ج.م";

        // تعبئة بنود الفاتورة
        this.populateReturnItems(invoice.items || []);

        // إضافة مستمعي الأحداث
        document.getElementById("returnAllBtn").onclick = () => this.returnAllItems();
        document.getElementById("returnPartialBtn").onclick = () => this.returnPartialItems();
        document.getElementById("processCustomReturnBtn").onclick = () => this.processReturn();
    },

    getPaymentStatusHtml(invoice) {
        
        const paidAmount = parseFloat(invoice.paid) || 0;
        const totalAmount = parseFloat(invoice.total) || 0;
        const remainingAmount = parseFloat(invoice.remaining_amount) || 0;
        
        let statusText = "";
        let statusClass = "";
        let statusIcon = "";
        
        if (paidAmount === 0) {
            // فاتورة مؤجلة
            statusText = "فاتورة مؤجلة";
            statusClass = "bg-gradient-3";
            statusIcon = "fas fa-clock";
        } else if (remainingAmount === 0 && paidAmount === totalAmount) {
            // فاتورة مدفوعة كلياً
            statusText = "مدفوعة كلياً";
            statusClass = "bg-gradient-2";
            statusIcon = "fas fa-check-circle";
        } else {
            // فاتورة مدفوعة جزئياً
            statusText = "مدفوعة جزئياً";
            statusClass = "bg-gradient-1";
            statusIcon = "fas fa-percentage";
        }
        
        return `
            <span class="badge ${statusClass}">
                <i class="${statusIcon} me-1"></i>
                ${statusText}
            </span>
        `;
    },

    populateReturnItems(items) {
        const container = document.getElementById("customReturnItemsContainer");
        container.innerHTML = "";

        items.forEach((item, index) => {
            const availableQuantity = item.quantity - (item.returned_quantity || 0);
            
            if (availableQuantity > 0) {
                const unitPriceAfterDiscount = item.unit_price_after_discount;
                const itemElement = document.createElement("div");
                itemElement.className = "return-modal-card";
                itemElement.setAttribute("data-item-index", index);

                itemElement.innerHTML = `
                    <div class="return-item-header">
                        <div>
                            <h6 class="mb-1 text-primary">${item.product_name || `المنتج ${item.product_id}`}</h6>
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
                                <input type="number" class="form-control " value="${item.quantity}" readonly>
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
            validationMessage.innerHTML = `
                <div class="alert alert-danger alert-sm mb-0">
                    <i class="fas fa-exclamation-circle me-1"></i>
                    خطأ: لا يمكن إرجاع أكثر من ${max}
                </div>`;
            inputElement.classList.add("is-invalid");
            inputElement.value = max;
            this.updateReturnItem(itemIndex);
            return false;
        } else if (value < 0) {
            validationMessage.innerHTML = `
                <div class="alert alert-danger alert-sm mb-0">
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

        const total = quantity * unitPrice;
        totalInput.textContent = total.toFixed(2);

        this.updateReturnTotal();
    },

    calculateReturnImpact(totalReturnAmount) {
        const invoice = this.currentInvoiceData;
        const paidAmount = parseFloat(invoice.paid) || 0;
        const remainingAmount = parseFloat(invoice.remaining) || 0;
        const totalAfterDiscount = parseFloat(invoice.total) || 0;
        
        let amountFromRemaining = 0;
        let amountFromPaid = 0;
        let showRefundOptions = false;
        let paymentStatus = '';
        let logicDescription = '';
        
        // تحديد حالة الفاتورة بدقة
        if (paidAmount === 0) {
            // حالة 1: فاتورة مؤجلة (لم يدفع أي شيء)
            paymentStatus = 'فاتورة مؤجلة';
            amountFromRemaining = totalReturnAmount;
            showRefundOptions = false;
            
            logicDescription = `
                <div class="alert alert-warning">
                    <i class="fas fa-clock me-2"></i>
                    <strong>فاتورة مؤجلة - لم يدفع العميل</strong>
                    <br>سيتم خصم ${totalReturnAmount.toFixed(2)} ج.م من المتبقي فقط
                    <br><small>❌ لا يتم رد أي مبلغ للعميل (لا نقدي، لا محفظة)</small>
                </div>
            `;
            
        } else if (remainingAmount === 0 && paidAmount === totalAfterDiscount) {
            // حالة 2: فاتورة مدفوعة كلياً
            paymentStatus = 'فاتورة مدفوعة كلياً';
            amountFromPaid = totalReturnAmount;
            showRefundOptions = true;
            
            logicDescription = `
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>فاتورة مدفوعة كلياً</strong>
                    <br>سيتم رد ${totalReturnAmount.toFixed(2)} ج.م للعميل
                    <br><small>✅ يمكنك اختيار طريقة الرد: نقدي أو محفظة</small>
                </div>
            `;
            
        } else {
            // حالة 3: فاتورة مدفوعة جزئياً
         
            
        paymentStatus = 'فاتورة مدفوعة جزئياً';
        
        // 👇 **هنا التصحيح المهم** 👇
        // أولاً: نأخذ من المتبقي قدر المستطاع
        amountFromRemaining = Math.min(totalReturnAmount, remainingAmount);
        
        // ثانياً: الباقي يأتي من المدفوع
        const remainingFromPaid = totalReturnAmount - amountFromRemaining;
        if (remainingFromPaid > 0) {
            amountFromPaid = Math.min(remainingFromPaid, paidAmount);
            showRefundOptions = true; // ✅ إذا كان هناك جزء من المدفوع
        }
        
        // 📝 بناء وصف تفصيلي
        let descriptionParts = [];
        
        if (amountFromRemaining > 0) {
            descriptionParts.push(`يتم خصم ${amountFromRemaining.toFixed(2)} ج.م من المتبقي`);
        }
        
        if (amountFromPaid > 0) {
            descriptionParts.push(`يتم رد ${amountFromPaid.toFixed(2)} ج.م للعميل`);
        } else if (amountFromRemaining === totalReturnAmount) {
            descriptionParts.push(`❌ لا يوجد مبلغ للرد (كل المبلغ من المتبقي)`);
        }
        
        logicDescription = `
            <div class="alert alert-info">
                <i class="fas fa-calculator me-2"></i>
                <strong>فاتورة مدفوعة جزئياً</strong>
                ${descriptionParts.map(part => `<br>${part}`).join('')}
            </div>
        `;
    }
    
    return {
        amountFromRemaining,
        amountFromPaid,
        showRefundOptions,
        paymentStatus,
        logicDescription,
        totalReturnAmount
    };
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
                    invoice_item_id: parseInt(invoiceItemId),
                    product_id: parseInt(productId),
                    quantity: value,
                    unit_price_after_discount: unitPrice,
                    total: value * unitPrice
                });
            }
        });

        // حفظ بيانات الإرجاع للاستخدام لاحقاً
        this.returnItems = returnItemsData;

        const totalElement = document.getElementById("customReturnTotalAmount");
        totalElement.textContent = totalAmount.toFixed(2) + " ج.م";

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

    displayImpactDetails(impact) {
        const detailsContainer = document.getElementById("impactDetails");
        detailsContainer.style.display = "block";
        
        let detailsHTML = `
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light">
                    <i class="fas fa-calculator me-2"></i>
                    <strong>تفاصيل التأثير المالي</strong>
                    <span class="badge ${impact.paymentStatus === 'فاتورة مؤجلة' ? 'bg-gradient-3' : 
                                        impact.paymentStatus === 'فاتورة مدفوعة كلياً' ? 'bg-gradient-2' : 
                                        'bg-gradient-1'} float-end">
                        ${impact.paymentStatus}
                    </span>
                </div>
                <div class="card-body">
                    ${impact.logicDescription}
                    
                    <div class="row mt-3">
        `;
        
        // عرض المبلغ المخصوم من المتبقي
        if (impact.amountFromRemaining > 0) {
            detailsHTML += `
                <div class="col-md-6">
                    <div class="alert alert-warning mb-2">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-minus-circle text-amber me-2 fs-5"></i>
                            <div class="flex-grow-1">
                                <div class="fw-bold">يخصم من المتبقي (الدين)</div>
                                <div class="text-amber fw-bold fs-5">
                                    ${impact.amountFromRemaining.toFixed(2)} ج.م
                                </div>
                                <small class="text-muted">سيتم تخفيض دين العميل بهذا المبلغ</small>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
        
        // عرض المبلغ الذي سيتم رده
        if (impact.amountFromPaid > 0) {
            detailsHTML += `
                <div class="col-md-6">
                    <div class="alert alert-success mb-2">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-undo text-teal me-2 fs-5"></i>
                            <div class="flex-grow-1">
                                <div class="fw-bold">يُرد للعميل</div>
                                <div class="text-teal fw-bold fs-5">
                                    ${impact.amountFromPaid.toFixed(2)} ج.م
                                </div>
                                <small class="text-muted">سيتم اختيار طريقة الرد أدناه</small>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
        
        detailsHTML += `
                    </div>
                </div>
            </div>
        `;
        
        detailsContainer.innerHTML = detailsHTML;

        // التحكم في عرض قسم اختيار طريقة الرد
        const refundMethodSection = document.getElementById("refundMethodSection");
        if (impact.showRefundOptions && impact.amountFromPaid > 0) {
            refundMethodSection.style.display = "block";
            this.setupRefundOptions(impact);
        } else {
            refundMethodSection.style.display = "none";
        }
    },

    setupRefundOptions(impact) {
        const refundOptions = document.getElementById("refundOptions");
        
        // بناء وصف حسب نوع الفاتورة
        let description = '';
        if (impact.paymentStatus === 'فاتورة مدفوعة كلياً') {
            description = `
                <div class="alert alert-warning mb-3">
                    <div class="d-flex">
                        <i class="fas fa-info-circle me-3 text-info fa-lg mt-1"></i>
                        <div>
                            <strong class="text-info">فاتورة مدفوعة كلياً</strong>
                            <div class="mt-2">
                                سيتم رد المبلغ (<span class="fw-bold">${impact.amountFromPaid.toFixed(2)} ج.م</span>) للعميل
                                <br><br>
                                <strong class="text-success">✔ اختر طريقة الرد:</strong>
                                <div class="mt-2 ps-3">
                                    <div class="mb-2">
                                        <i class="fas fa-money-bill-wave text-success me-2"></i>
                                        <strong>نقدي:</strong> إعطاء العميل المبلغ نقداً
                                    </div>
                                    <div>
                                        <i class="fas fa-wallet text-primary me-2"></i>
                                        <strong>محفظة:</strong> إضافة المبلغ لمحفظة العميل
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        } else if (impact.paymentStatus === 'فاتورة مدفوعة جزئياً') {
            description = `
                <div class="alert alert-warning mb-3">
                    <div class="d-flex">
                        <i class="fas fa-info-circle me-3 text-warning fa-lg mt-1"></i>
                        <div>
                            <strong class="text-warning">فاتورة مدفوعة جزئياً</strong>
                            <div class="mt-2">
                                <div class="mb-2">
                                    <i class="fas fa-minus-circle text-amber me-2"></i>
                                    <strong>تم خصم:</strong> ${impact.amountFromRemaining.toFixed(2)} ج.م من المتبقي
                                </div>
                                <div class="mb-2">
                                    <i class="fas fa-undo text-teal me-2"></i>
                                    <strong>يتم رد:</strong> ${impact.amountFromPaid.toFixed(2)} ج.م للعميل
                                </div>
                                <br>
                                <strong class="text-success">✔ اختر طريقة رد المبلغ:</strong>
                                <div class="mt-2 ps-3">
                                    <div class="mb-2">
                                        <i class="fas fa-money-bill-wave text-success me-2"></i>
                                        <strong>نقدي:</strong> إعطاء العميل المبلغ نقداً
                                    </div>
                                    <div>
                                        <i class="fas fa-wallet text-primary me-2"></i>
                                        <strong>محفظة:</strong> إضافة المبلغ لمحفظة العميل
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
        
        refundOptions.innerHTML = description + `
            <div class="form-group">
                <label class="form-label fw-bold mb-3">
                    <i class="fas fa-money-bill-wave me-2"></i>
                    اختر طريقة رد المبلغ (${impact.amountFromPaid.toFixed(2)} ج.م)
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
                                        <h6 class="mb-1 text-muted">استرجاع نقدي</h6>
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
                                        <h6 class="mb-1 text_muted">إضافة للمحفظة</h6>
                                        <p class="small text-muted mb-0">سيتم إضافة المبلغ لمحفظة العميل</p>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // إضافة مستمعي الأحداث للخيارات
        document.querySelectorAll('.refund-option-card').forEach(card => {
            card.addEventListener('click', () => {
                document.querySelectorAll('.refund-option-card').forEach(c => {
                    c.classList.remove('selected');
                });
                card.classList.add('selected');
                const radio = card.querySelector('input[type="radio"]');
                if (radio) {
                    radio.checked = true;
                }
            });
        });
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

            this.getCurrentReturnQtyForItem = function(invoiceItemId) {
        const input = document.querySelector(`.custom-return-quantity[data-invoice-item-id="${invoiceItemId}"]`);
        return parseFloat(input?.value || 0);
    };

         const determineReturnType = () => {
        // 1. حساب الكمية الأصلية الكلية
        let avilableForReturn = 0;
        // 2. حساب الكمية المرتجعة الكلية بعد هذا الإرجاع
        let totalReturnedAfterThis = 0;
     

        // حساب لكل بند في الفاتورة
        this.currentInvoiceData.items.forEach(invoiceItem => {
       
            // الكمية الأصلية للبند
            const avilableForReturnItem = parseFloat(invoiceItem.available_for_return) || 0;
            avilableForReturn += avilableForReturnItem;

            // الكمية التي سيتم إرجاعها في هذه العملية
            const currentReturnQty = this.getCurrentReturnQtyForItem(invoiceItem.id);
            totalReturnedAfterThis += currentReturnQty;
            
            
        


        });


        console.log(totalReturnedAfterThis, avilableForReturn);
        
        
        // ✅ لو كل الكميات أصبحت مرتجعة = full
        // نستخدم tolerance صغير لتفادي مشاكل التقريب
        const tolerance = 0.01;
        const isFullyReturned = Math.abs(totalReturnedAfterThis - avilableForReturn) < tolerance;
        
        return isFullyReturned ? "full" : "partial";
    };

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

        // حساب التأثير المالي
        const totalReturnAmount = this.returnItems.reduce((sum, item) => sum + item.total, 0);
        const impact = this.calculateReturnImpact(totalReturnAmount);
        
        // تحديد طريقة الاسترداد بناءً على الحالة المالية
        let refundPreference = "credit_adjustment";
        
        if (impact.amountFromPaid > 0) {
            const refundMethodInput = document.querySelector('input[name="refundMethodChoice"]:checked');
            refundPreference = refundMethodInput ? refundMethodInput.value : "cash";
        } else if (impact.paymentStatus === 'فاتورة مؤجلة') {
            refundPreference = "credit_adjustment";
        }


const returnType = determineReturnType();



        // إعداد بيانات الإرجاع للـ API
        const returnData = {
            invoice_id: parseInt(this.currentInvoiceId),
            customer_id: AppData.currentCustomer?.id ? parseInt(AppData.currentCustomer.id) : 0,
            return_type: returnType,
            reason: returnReason,
            refund_preference: refundPreference,
            items: this.returnItems.map(item => ({
                invoice_item_id: item.invoice_item_id,
                product_id: item.product_id,
                return_qty: item.quantity,
                unit_price_after_discount: item.unit_price_after_discount,
            }))
        };

        // عرض تأكيد مع تفاصيل التأثير المالي
        const confirmResult = await Swal.fire({
            title: "تأكيد عملية الإرجاع",
            html: `
                <div class="text-start">
                    <p class="mb-3">هل أنت متأكد من تنفيذ عملية الإرجاع؟</p>
                    
                    <div class="alert ${impact.paymentStatus === 'فاتورة مؤجلة' ? 'alert-warning' : 
                                         impact.paymentStatus === 'فاتورة مدفوعة كلياً' ? 'alert-success' : 
                                         'alert-info'} mb-3">
                        <strong>${impact.paymentStatus}</strong>
                        <div class="mt-2">
                            <div><strong>المبلغ الإجمالي:</strong> ${totalReturnAmount.toFixed(2)} ج.م</div>
                            ${impact.amountFromRemaining > 0 ? 
                                `<div><strong>يخصم من المتبقي:</strong> ${impact.amountFromRemaining.toFixed(2)} ج.م</div>` : ''}
                            ${impact.amountFromPaid > 0 ? 
                                `<div><strong>يتم رد للعميل:</strong> ${impact.amountFromPaid.toFixed(2)} ج.م</div>` : ''}
                            <div><strong>طريقة الاسترداد:</strong> 
                                ${refundPreference === 'cash' ? 'نقدي' : 
                                 refundPreference === 'wallet' ? 'محفظة' : 
                                 'تعديل رصيد'}
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-secondary">
                        <strong>التفاصيل:</strong>
                        <div class="mt-1">
                            <div>نوع الإرجاع: <strong>${returnType === 'full' ? 'كامل' : 'جزئي'}</strong></div>
                            <div>عدد المنتجات: <strong>${this.returnItems.length}</strong></div>
                            <div>السبب: <strong>${returnReason}</strong></div>
                        </div>
                    </div>
                </div>
            `,
            icon: "question",
            showCancelButton: true,
            confirmButtonText: "نعم، تأكيد الإرجاع",
            cancelButtonText: "إلغاء",
            confirmButtonColor: "var(--primary)",
            cancelButtonColor: "var(--rose)",
            background: "var(--surface)",
            color: "var(--text)",
            width: "600px"
        });

        if (confirmResult.isConfirmed) {
            // إرسال البيانات إلى الباك إند
            const loadingSwal = Swal.fire({
                title: "جاري معالجة الإرجاع...",
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
                
                if (response.success) {
                    Swal.fire({
                        title: "تم بنجاح",
                        html: `
                            <div class="text-start">
                                <p>تم إنشاء الإرجاع بنجاح</p>
                                <div class="alert alert-success">
                                    <strong>رقم المرتجع:</strong> #RET-${response.return_id}<br>
                                    <strong>المبلغ:</strong> ${totalReturnAmount.toFixed(2)} ج.م<br>
                                    <strong>الحالة:</strong> ${response.status === 'pending' ? 'بانتظار الموافقة' : 'معتمد'}
                                </div>
                            </div>
                        `,
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

                    // تحديث البيانات
                    await ReturnManager.loadReturnsData();
                    
                    // تحديث صفحة الفاتورة إذا كانت مفتوحة
                    if (typeof InvoiceManager !== 'undefined' && InvoiceManager.refreshCurrentInvoice) {
                        InvoiceManager.refreshCurrentInvoice();
                    }
                } else {
                    Swal.fire({
                        title: "خطأ",
                        text: response.message || "حدث خطأ أثناء إنشاء الإرجاع",
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
                    text: "حدث خطأ أثناء معالجة الإرجاع: " + error.message,
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