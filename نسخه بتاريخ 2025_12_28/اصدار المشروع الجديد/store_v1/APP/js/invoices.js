  import AppData  from "./app_data.js";
  import { CustomReturnManager } from "./return.js";
  import { updateInvoiceStats } from "./helper.js";
  import CustomerManager from "./customer.js";
  import PrintManager from "./print.js";
  import PaymentManager from "./payment.js";

const InvoiceManager = {
    init() {
        // بيانات الفواتير الابتدائية المحدثة
        AppData.invoices = [
            {
                id: 123,
                number: "#123",
                date: "2024-01-15",
                time: "02:30 م",
                total: 800,
                paid: 500,
                remaining: 300,
                status: "pending",
                description: "فاتورة مرتبطة بشغلانة #WO-001",
                workOrderId: 1,
                paymentMethod: "credit",
                items: [
                    {
                        productId: 1,
                        productName: "شباك ألوميتال 2×1.5",
                        quantity: 1,
                        price: 800,
                        total: 800,
                        returnedQuantity: 0,
                        currentQuantity: 1,
                        currentTotal: 800,
                        fullyReturned: false,
                    },
                ],
                createdBy: "مدير النظام",
            },
            {
                id: 125,
                number: "#125",
                date: "2024-01-15",
                time: "02:30 م",
                total: 800,
                paid: 500,
                remaining: 300,
                status: "pending",
                description: "فاتورة مرتبطة بشغلانة #WO-001",
                workOrderId: 1,
                paymentMethod: "credit",
                items: [
                    {
                        productId: 1,
                        productName: "شباك ألوميتال 2×1.5",
                        quantity: 1,
                        price: 800,
                        total: 800,
                        returnedQuantity: 0,
                        currentQuantity: 1,
                        currentTotal: 800,
                        fullyReturned: false,
                    },
                ],
                createdBy: "مدير النظام",
            },
            {
                id: 121,
                number: "#121",
                date: "2024-01-05",
                time: "03:45 م",
                total: 500,
                paid: 200,
                remaining: 300,
                status: "partial",
                description: "فاتورة جزئية",
                paymentMethod: "cash",
                items: [
                    {
                        productId: 3,
                        productName: "مفصلات ستانلس",
                        quantity: 2,
                        price: 150,
                        total: 300,
                        returnedQuantity: 0,
                        currentQuantity: 2,
                        currentTotal: 300,
                        fullyReturned: false,
                    },
                    {
                        productId: 5,
                        productName: "زجاج عاكس",
                        quantity: 0.5,
                        price: 400,
                        total: 200,
                        returnedQuantity: 0,
                        currentQuantity: 0.5,
                        currentTotal: 200,
                        fullyReturned: false,
                    },
                ],
                createdBy: "مدير النظام",
            },
        ];

        this.updateInvoicesTable();
    },

updateInvoiceAfterReturn(invoiceId, returnData) {
    const invoice = this.getInvoiceById(invoiceId);
    if (!invoice) return false;

    // 1. تحديث بنود الفاتورة
    returnData.items.forEach((returnItem) => {
        const invoiceItem = invoice.items.find(
            (item) => item.productId === returnItem.productId
        );
        if (invoiceItem) {
            // تحديث الكمية المرتجعة
            invoiceItem.returnedQuantity = (invoiceItem.returnedQuantity || 0) + returnItem.quantity;
            
            // حساب الكمية الحالية
            invoiceItem.currentQuantity = invoiceItem.quantity - invoiceItem.returnedQuantity;
            
            // حساب الإجمالي الحالي
            invoiceItem.currentTotal = invoiceItem.currentQuantity * invoiceItem.price;
            
            // تحديد إذا كان مرتجع كلي
            if (invoiceItem.returnedQuantity >= invoiceItem.quantity) {
                invoiceItem.fullyReturned = true;
                invoiceItem.currentQuantity = 0;
                invoiceItem.currentTotal = 0;
            }
        }
    });

    // 2. تحديث القيم المالية باستخدام البيانات المحسوبة مسبقاً
    invoice.total = returnData.newTotal;
    invoice.paid = returnData.newPaid;
    invoice.remaining = returnData.newRemaining;

    // 3. تحديث حالة الفاتورة
    if (Math.abs(invoice.remaining) < 0.01) {
        if (Math.abs(invoice.paid - invoice.total) < 0.01) {
            invoice.status = "paid";
        } else {
            invoice.status = "pending";
        }
    } else if (invoice.paid > 0) {
        invoice.status = "partial";
    } else {
        invoice.status = "pending";
    }

    // 4. التحقق إذا تم إرجاع جميع البنود
    const allItemsFullyReturned = invoice.items.every((item) => item.fullyReturned);
    if (allItemsFullyReturned) {
        invoice.status = "returned";
        invoice.paid = 0;
        invoice.remaining = 0;
        invoice.total = 0;
    }

    // 5. تحديث الواجهة
    this.updateInvoicesTable();
    updateInvoiceStats();
    CustomerManager.updateCustomerBalance();

    return true;
},


    // باقي دوال InvoiceManager كما هي مع تعديلات طفيفة
    updateInvoicesTable() {
        const tbody = document.getElementById("invoicesTableBody");
        tbody.innerHTML = "";

        // تطبيق الفلاتر
        let filteredInvoices = this.filterInvoices(AppData.invoices);

        filteredInvoices.forEach((invoice) => {
            const row = document.createElement("tr");
            row.className = `invoice-row ${invoice.status}`;

            // تحديد حالة الفاتورة
            let statusBadge = "";
            if (invoice.status === "pending") {
                statusBadge = '<span class="status-badge badge-pending">مؤجل</span>';
            } else if (invoice.status === "partial") {
                statusBadge = '<span class="status-badge badge-partial">جزئي</span>';
            } else if (invoice.status === "paid") {
                statusBadge = '<span class="status-badge badge-paid">مسلم</span>';
            } else if (invoice.status === "returned") {
                statusBadge = '<span class="status-badge badge-returned">مرتجع</span>';
            }

            // تحديد لون المبلغ المتبقي
            let remainingColor = "text-danger";
            if (invoice.remaining === 0) {
                remainingColor = "text-success";
            } else if (invoice.status === "partial") {
                remainingColor = "text-warning";
            }

            // عرض البنود مع المرتجعات
            let itemsTooltip = "";
            if (invoice.items && invoice.items.length > 0) {
                const itemsList = invoice.items
                    .map((item) => {
                        const currentQuantity = item.currentQuantity || (item.quantity - (item.returnedQuantity || 0));
                        const currentTotal = item.currentTotal || (currentQuantity * item.price);
                        const returnedText = item.returnedQuantity > 0 ? ` (مرتجع: ${item.returnedQuantity})` : "";
                        
                        return `
                            <div class="tooltip-item">
                                <div>
                                    <div class="tooltip-item-name">${item.productName || "منتج"}</div>
                                    <div class="tooltip-item-details">
                                        الكمية: ${currentQuantity} من ${item.quantity}${returnedText}<br>
                                        السعر: ${item.price.toFixed(2)} ج.م
                                    </div>
                                </div>
                                <div class="fw-bold">${currentTotal.toFixed(2)} ج.م</div>
                            </div>
                        `;
                    })
                    .join("");

                itemsTooltip = `
                    <div class="invoice-items-tooltip">
                        <div class="tooltip-header">بنود الفاتورة ${invoice.number}</div>
                        ${itemsList}
                        <div class="tooltip-total">
                            <span>الإجمالي الحالي:</span>
                            <span>${invoice.total.toFixed(2)} ج.م</span>
                        </div>
                    </div>
                `;
            }

            // إنشاء صف الفاتورة
            row.setAttribute("data-invoice-id", invoice.id);
            row.innerHTML = `
                <td>
                    <input type="checkbox" class="form-check-input invoice-checkbox" data-invoice-id="${invoice.id}">
                </td>
                <td>
                    <strong>${invoice.number}</strong>
                    
                </td>
                <td>${invoice.date}<br><small>${invoice.time}</small></td>
                <td class="invoice-item-hover position-relative">
                    ${invoice.items.length} بند
                    ${invoice.items.some(i => i.returnedQuantity > 0) ? 
                        '<br><small class="text-warning">(يوجد مرتجعات)</small>' : 
                        '<br><small class="text-muted">(مرر للعرض)</small>'}
                    ${itemsTooltip}
                </td>
                <td>${invoice.total.toFixed(2)} ج.م</td>
                <td>${invoice.paid.toFixed(2)} ج.م</td>
                <td>
                    <span class="${remainingColor} fw-bold">${invoice.remaining.toFixed(2)} ج.م</span>
                </td>
                <td>${statusBadge}</td>
                <td>
                    <div class="action-buttons">
                        <button class="btn btn-sm btn-outline-info view-invoice" data-invoice-id="${invoice.id}">
                            <i class="fas fa-eye"></i>
                        </button>
                        ${invoice.status !== "paid" && invoice.status !== "returned" ? `
                        <button class="btn btn-sm btn-outline-success pay-invoice" data-invoice-id="${invoice.id}">
                            <i class="fas fa-money-bill-wave"></i>
                        </button>
                        ` : ""}
                        ${invoice.status !== "returned" ? `
                        <button class="btn btn-sm btn-outline-warning custom-return-invoice" data-invoice-id="${invoice.id}">
                            <i class="fas fa-undo"></i>
                        </button>
                        ` : ""}
                        <button class="btn btn-sm btn-outline-secondary print-invoice" data-invoice-id="${invoice.id}">
                            <i class="fas fa-print"></i>
                        </button>
                    </div>
                </td>
            `;

            tbody.appendChild(row);
        });

        this.attachInvoiceEventListeners();
    },

    getInvoiceById(invoiceId) {
        return AppData.invoices.find((inv) => inv.id === invoiceId);
    },

    getInvoiceStatusText(status) {
        const statusMap = {
            pending: "مؤجل",
            partial: "جزئي",
            paid: "مسلم",
            returned: "مرتجع",
        };
        return statusMap[status] || status;
    },

      filterInvoices(invoices) {
          let filtered = [...invoices];

          if (AppData.activeFilters.dateFrom) {
            filtered = filtered.filter(
              (inv) => inv.date >= AppData.activeFilters.dateFrom
            );
          }

          if (AppData.activeFilters.dateTo) {
            filtered = filtered.filter(
              (inv) => inv.date <= AppData.activeFilters.dateTo
            );
          }

          if (AppData.activeFilters.invoiceType) {
            filtered = filtered.filter(
              (inv) => inv.status === AppData.activeFilters.invoiceType
            );
          }

          // فلتر حسب رقم الفاتورة (للعرض من نتائج البحث)
          if (AppData.activeFilters.invoiceId) {
            filtered = filtered.filter(
              (inv) => inv.id === AppData.activeFilters.invoiceId
            );
          }

          if (AppData.activeFilters.productSearch) {
            const searchTerm =
              AppData.activeFilters.productSearch.toLowerCase();
            filtered = filtered.filter((invoice) =>
              invoice.items.some((item) =>
                item.productName.toLowerCase().includes(searchTerm)
              )
            );
          }

          return filtered;
        },

        attachInvoiceEventListeners() {
          // زر عرض الفاتورة
          document.querySelectorAll(".view-invoice").forEach((btn) => {
            btn.addEventListener("click", function () {
              const invoiceId = parseInt(this.getAttribute("data-invoice-id"));
              InvoiceManager.showInvoiceDetails(invoiceId);
            });
          });

          // زر سداد الفاتورة
          // زر سداد الفاتورة
          document.querySelectorAll(".pay-invoice").forEach((btn) => {
            btn.addEventListener("click", function () {
              const invoiceId = parseInt(this.getAttribute("data-invoice-id"));
              PaymentManager.openSingleInvoicePayment(invoiceId);
            });
          });

          // زر إرجاع الفاتورة المخصص
          document.querySelectorAll(".custom-return-invoice").forEach((btn) => {
            btn.addEventListener("click", function () {
              const invoiceId = parseInt(this.getAttribute("data-invoice-id"));
              CustomReturnManager.openReturnModal(invoiceId);
            });
          });

          // زر طباعة الفاتورة
          document.querySelectorAll(".print-invoice").forEach((btn) => {
            btn.addEventListener("click", function () {
              const invoiceId = parseInt(this.getAttribute("data-invoice-id"));
              PrintManager.printSingleInvoice(invoiceId);
            });
          });
        },

      
        showInvoiceDetails(invoiceId) {
    const invoice = AppData.invoices.find((i) => i.id === invoiceId);
    if (invoice) {
        document.getElementById("invoiceItemsNumber").textContent = invoice.number;
        document.getElementById("invoiceItemsDate").textContent = invoice.date + " - " + invoice.time;
        document.getElementById("invoiceItemsStatus").textContent = this.getInvoiceStatusText(invoice.status);
        document.getElementById("invoiceItemsTotal").textContent = invoice.total.toFixed(2) + " ج.م";
        document.getElementById("invoiceItemsPaid").textContent = invoice.paid.toFixed(2) + " ج.م";
        document.getElementById("invoiceItemsRemaining").textContent = invoice.remaining.toFixed(2) + " ج.م";
        document.getElementById("invoiceItemsNotes").textContent = invoice.description || "لا يوجد";

        // عرض اسم الشغلانة إذا كانت مرتبطة
        let workOrderName = "لا يوجد";
        if (invoice.workOrderId) {
            const workOrder = AppData.workOrders.find((wo) => wo.id === invoice.workOrderId);
            if (workOrder) {
                workOrderName = workOrder.name;
            }
        }
        document.getElementById("invoiceItemsWorkOrder").textContent = workOrderName;

        // التحقق من وجود مرتجعات
        const hasReturns = AppData.returns.some((r) => r.invoiceId === invoiceId);
        if (hasReturns) {
            document.getElementById("invoiceReturnsSection").style.display = "block";
            document.getElementById("viewInvoiceReturns").addEventListener("click", function (e) {
                e.preventDefault();
                CustomReturnManager.showInvoiceReturns(invoiceId);
            });
        } else {
            document.getElementById("invoiceReturnsSection").style.display = "none";
        }

        const tbody = document.getElementById("invoiceItemsDetails");
        tbody.innerHTML = "";

        invoice.items.forEach((item) => {
            const row = document.createElement("tr");
            
            // حساب الكمية الحالية والإجمالي الحالي
            const currentQuantity = item.currentQuantity || (item.quantity - (item.returnedQuantity || 0));
            const currentTotal = item.currentTotal || (currentQuantity * item.price);
            const originalTotal = item.quantity * item.price;
            
            // عرض تاريخ آخر مرتجع
            let lastReturnDate = "";
            if (item.returnedQuantity > 0) {
                const lastReturn = AppData.returns
                    .filter(r => r.invoiceId === invoiceId)
                    .map(r => r.items.find(i => i.productId === item.productId))
                    .filter(i => i)
                    .sort((a, b) => new Date(b.date) - new Date(a.date))[0];
                
                if (lastReturn) {
                    lastReturnDate = lastReturn.date || "";
                }
            }

            let itemStatus = "سليم";
            let rowClass = "";
            if (item.fullyReturned) {
                itemStatus = '<span class="badge bg-danger">مرتجع كلي</span>';
                rowClass = "fully-returned";
            } else if (item.returnedQuantity > 0) {
                itemStatus = '<span class="badge bg-warning">مرتجع جزئي</span>';
                rowClass = "partially-returned";
            }

            row.className = rowClass;
            row.innerHTML = `
                <td>
                    <strong>${item.productName}</strong>
                    ${item.returnedQuantity > 0 ? 
                        `<div class="mt-1">
                            <span class="badge bg-warning return-history-badge">
                                مرتجع: ${item.returnedQuantity}
                                ${lastReturnDate ? ` (${lastReturnDate})` : ''}
                            </span>
                        </div>` : 
                        ''}
                </td>
                <td>
                    <div class="d-flex flex-column">
                        <span class="text-muted small">أصلي: ${item.quantity}</span>
                        <span class="fw-bold mt-1">حالي: ${currentQuantity}</span>
                    </div>
                </td>
                <td>${item.price.toFixed(2)} ج.م</td>
                <td>
                    <div class="d-flex flex-column">
                        <span class="text-muted small" style="text-decoration: line-through;">${originalTotal.toFixed(2)} ج.م</span>
                        <span class="fw-bold mt-1">${currentTotal.toFixed(2)} ج.م</span>
                    </div>
                </td>
                <td>${item.returnedQuantity || 0}</td>
                <td>${itemStatus}</td>
            `;
            tbody.appendChild(row);
        });

        const modal = new bootstrap.Modal(document.getElementById("invoiceItemsModal"));
        modal.show();
    }
},
     


        
        selectAllInvoices() {
          document.querySelectorAll(".invoice-checkbox").forEach((checkbox) => {
            checkbox.checked = true;
          });
          this.updateSelectedCount();
        },

        selectNonWorkOrderInvoices() {
          document.querySelectorAll(".invoice-checkbox").forEach((checkbox) => {
            const invoiceId = parseInt(
              checkbox.getAttribute("data-invoice-id")
            );
            const invoice = this.getInvoiceById(invoiceId);
            checkbox.checked = !invoice.workOrderId;
          });
          this.updateSelectedCount();
        },

        updateSelectedCount() {
          const selectedCount = document.querySelectorAll(
            ".invoice-checkbox:checked"
          ).length;
          const printBtn = document.getElementById("printSelectedInvoices");
          printBtn.disabled = selectedCount === 0;
          printBtn.innerHTML = `<i class="fas fa-print me-2"></i>طباعة (${selectedCount})`;
        },
};

export default InvoiceManager;