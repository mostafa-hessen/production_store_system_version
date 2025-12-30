import AppData from "./app_data.js";
import InvoiceManager from "./invoices.js";
import PrintManager from "./print.js";
import WalletManager from "./wallet.js";

const ReturnManager = {
    init() {
        // بيانات المرتجعات الابتدائية المحدثة
        AppData.returns = [
            {
                id: 1,
                number: "#RET-001",
                invoiceId: 120,
                invoiceNumber: "#120",
                type: "full",
                amount: 300,
                method: "wallet",
                status: "completed",
                date: "2024-01-05",
                reason: "شباك معيب",
                amountFromRemaining: 0,
                amountFromPaid: 300,
                originalPaymentMethod: "cash",
                items: [
                    {
                        productId: 1,
                        productName: "شباك ألوميتال 2×1.5",
                        quantity: 1,
                        price: 300,
                        total: 300,
                        date: "2024-01-05"
                    },
                ],
                createdBy: "مدير النظام",
            },
        ];

        this.updateReturnsTable();
    },

    updateReturnsTable() {
        const tbody = document.getElementById("returnsTableBody");
        tbody.innerHTML = "";

        AppData.returns.forEach((returnItem) => {
            const row = document.createElement("tr");

            // تحديد نوع المرتجع
            let typeBadge = returnItem.type === "full"
                ? '<span class="badge bg-danger">كامل</span>'
                : '<span class="badge bg-warning">جزئي</span>';

            // تحديد طريقة الاسترجاع
            let methodBadge = "";
            if (returnItem.method === "wallet") {
                methodBadge = '<span class="badge bg-info">محفظة</span>';
            } else if (returnItem.method === "cash") {
                methodBadge = '<span class="badge bg-success">نقدي</span>';
            } else if (returnItem.method === "credit_adjustment") {
                methodBadge = '<span class="badge bg-secondary">تعديل آجل</span>';
            }

            // تحديد حالة المرتجع
            let statusBadge = returnItem.status === "completed"
                ? '<span class="status-badge badge-paid">مكتمل</span>'
                : '<span class="status-badge badge-pending">معلق</span>';

            // عرض بنود المرتجع
            let itemsList = "";
            if (returnItem.items && returnItem.items.length > 0) {
                returnItem.items.forEach((item) => {
                    itemsList += `<div class="d-flex justify-content-between small border-bottom pb-1 mb-1">
                                    <span>${item.productName}</span>
                                    <span>${item.quantity} × ${item.price.toFixed(2)} = ${item.total.toFixed(2)} ج.م</span>
                                </div>`;
                });
            }

            // عرض تفاصيل الدفع
            let paymentDetails = "";
            if (returnItem.amountFromRemaining > 0) {
                paymentDetails += `<div class="small text-muted">من المتبقي: ${returnItem.amountFromRemaining.toFixed(2)} ج.م</div>`;
            }
            if (returnItem.amountFromPaid > 0) {
                paymentDetails += `<div class="small text-muted">مرتجع: ${returnItem.amountFromPaid.toFixed(2)} ج.م</div>`;
            }

            row.innerHTML = `
                <td>
                    <strong>${returnItem.number}</strong>
                    <br>
                    <button class="btn btn-sm btn-outline-info mt-1 view-original-invoice" data-invoice-id="${returnItem.invoiceId}">
                        <i class="fas fa-external-link-alt"></i> عرض الفاتورة
                    </button>
                </td>
                <td>
                    <a href="#" class="text-decoration-none view-invoice-from-return" data-invoice-id="${returnItem.invoiceId}">
                        ${returnItem.invoiceNumber}
                    </a>
                    <br>
                    <small class="text-muted">${returnItem.reason}</small>
                    ${paymentDetails}
                </td>
                <td>
                    <div class="items-preview">
                        ${itemsList}
                    </div>
                </td>
                <td>
                    ${returnItem.items ? returnItem.items.reduce((sum, i) => sum + i.quantity, 0) : 1}
                </td>
                <td>
                    <div class="fw-bold">${returnItem.amount.toFixed(2)} ج.م</div>
                    ${returnItem.originalPaymentMethod === "credit" ?
                    '<small class="text-muted">(فاتورة آجلة)</small>' : ''}
                </td>
                <td>${methodBadge}</td>
                <td>${statusBadge}</td>
                <td>${returnItem.date}</td>
                <td>${returnItem.createdBy}</td>
            `;

            tbody.appendChild(row);
        });

        // إضافة مستمعي الأحداث لعرض الفاتورة الأصلية
        document.querySelectorAll(".view-invoice-from-return, .view-original-invoice").forEach((btn) => {
            btn.addEventListener("click", function (e) {
                e.preventDefault();
                const invoiceId = parseInt(this.getAttribute("data-invoice-id"));
                InvoiceManager.showInvoiceDetails(invoiceId);
            });
        });
    },

    getReturnsByInvoiceId(invoiceId) {
        return AppData.returns.filter((r) => r.invoiceId === invoiceId);
    },

    addReturn(returnData) {
        const newReturn = {
            id: AppData.nextReturnId++,
            number: `#RET-00${AppData.nextReturnId - 1}`,
            invoiceId: returnData.invoiceId,
            invoiceNumber: returnData.invoiceNumber,
            type: returnData.type,
            amount: returnData.amount,
            method: returnData.method,
            status: "completed",
            date: new Date().toISOString().split("T")[0],
            reason: returnData.reason,
            items: returnData.items,
            createdBy: AppData.currentUser,
            amountFromRemaining: returnData.amountFromRemaining || 0,
            amountFromPaid: returnData.amountFromPaid || 0,
            originalPaymentMethod: returnData.originalPaymentMethod
        };

        AppData.returns.unshift(newReturn);

        // إضافة حركة للمحفظة إذا كانت طريقة الاسترجاع للمحفظة وكان هناك مبلغ مدفوع
        if (returnData.method === "wallet" && returnData.amountFromPaid > 0) {
            WalletManager.addTransaction({
                type: "return",
                amount: returnData.amountFromPaid,
                description: `مرتجع ${returnData.type === "full" ? "كامل" : "جزئي"} لفاتورة ${returnData.invoiceNumber}`,
                date: newReturn.date,
            });
        }

        this.updateReturnsTable();
        return newReturn;
    }
};



const CustomReturnManager = {
    currentInvoiceId: null,
    returnItems: [],

    openReturnModal(invoiceId) {
        this.currentInvoiceId = invoiceId;
        this.returnItems = [];

        const invoice = InvoiceManager.getInvoiceById(invoiceId);
        if (!invoice) {
            Swal.fire("خطأ", "الفاتورة غير موجودة", "error");
            return;
        }

        // تعبئة معلومات الفاتورة
        document.getElementById("returnInvoiceNumber").textContent = invoice.number;
        document.getElementById("returnInvoiceDate").textContent = invoice.date;
        document.getElementById("returnInvoiceTotal").textContent = invoice.total.toFixed(2) + " ج.م";

        // عرض معلومات الدفع
        document.getElementById("originalPaymentMethod").textContent =
            invoice.paymentMethod === "credit" ? "آجل" :
                invoice.paymentMethod === "wallet" ? "محفظة" : "نقدي";

        document.getElementById("paymentStatus").textContent =
            invoice.paid === 0 ? "لم يدفع" :
                invoice.paid >= invoice.total ? "مدفوع بالكامل" : "مدفوع جزئياً";

        document.getElementById("invoicePaidAmount").textContent = invoice.paid.toFixed(2) + " ج.م";
        document.getElementById("invoiceRemainingAmount").textContent = invoice.remaining.toFixed(2) + " ج.م";

        // تعبئة بنود الفاتورة
        this.populateReturnItems(invoice);

        // إضافة مستمعي الأحداث للأزرار
        document.getElementById("returnAllBtn").onclick = () => this.returnAllItems();
        document.getElementById("returnPartialBtn").onclick = () => this.returnPartialItems();
        document.getElementById("processCustomReturnBtn").onclick = () => this.processReturn();

        // فتح المودال
        const modal = new bootstrap.Modal(document.getElementById("customReturnModal"));
        modal.show();
    },

    populateReturnItems(invoice) {
        const container = document.getElementById("customReturnItemsContainer");
        container.innerHTML = "";

        invoice.items.forEach((item, index) => {
            const availableQuantity = item.quantity - (item.returnedQuantity || 0);

            if (availableQuantity > 0 && !item.fullyReturned) {
                const itemElement = document.createElement("div");
                itemElement.className = "return-item-card border p-3 mb-3 rounded";
                itemElement.setAttribute("data-item-index", index);

                itemElement.innerHTML = `
                    <div class="row align-items-center">
                        <div class="col-md-3">
                            <label class="form-label fw-bold">المنتج</label>
                            <input type="text" class="form-control" value="${item.productName}" readonly>
                            <div class="mt-1">
                                <small class="text-muted">متاح للإرجاع: ${availableQuantity}</small>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">الكمية الأصلية</label>
                            <input type="number" class="form-control bg-light" value="${item.quantity}" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">مرتجع سابق</label>
                            <input type="number" class="form-control bg-warning text-white" 
                                   value="${item.returnedQuantity || 0}" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label text-success">الكمية الحالية</label>
                            <input type="number" class="form-control bg-success text-white" 
                                   value="${availableQuantity}" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label text-primary">كمية الإرجاع</label>
                            <input type="number" class="form-control custom-return-quantity border-primary" 
                                   data-item-index="${index}" min="0" max="${availableQuantity}" 
                                   value="0" data-max="${availableQuantity}" 
                                   placeholder="أدخل الكمية">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">الإجمالي</label>
                            <input type="number" class="form-control custom-return-total bg-info text-white" 
                                   data-item-index="${index}" value="0" readonly>
                        </div>
                    </div>
                    <div class="validation-message mt-2" id="validation-${index}" style="display:none; color:red; font-size:12px;"></div>
                `;
                container.appendChild(itemElement);

                // إضافة مستمعي الأحداث مع التحقق
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
            validationMessage.textContent = `خطأ: لا يمكن إرجاع أكثر من ${max}`;
            validationMessage.style.display = "block";
            inputElement.style.borderColor = "red";
            inputElement.classList.add("is-invalid");
            inputElement.value = max;
            this.updateReturnItem(itemIndex);
            return false;
        } else if (value < 0) {
            validationMessage.textContent = "خطأ: القيمة يجب أن تكون موجبة";
            validationMessage.style.display = "block";
            inputElement.style.borderColor = "red";
            inputElement.classList.add("is-invalid");
            inputElement.value = 0;
            this.updateReturnItem(itemIndex);
            return false;
        } else {
            validationMessage.style.display = "none";
            inputElement.style.borderColor = "";
            inputElement.classList.remove("is-invalid");
            return true;
        }
    },

    updateReturnItem(itemIndex) {
        const quantityInput = document.querySelector(`.custom-return-quantity[data-item-index="${itemIndex}"]`);
        const totalInput = document.querySelector(`.custom-return-total[data-item-index="${itemIndex}"]`);

        const quantity = parseFloat(quantityInput.value) || 0;
        const invoice = InvoiceManager.getInvoiceById(this.currentInvoiceId);
        const item = invoice.items[itemIndex];

        const total = quantity * item.price;
        totalInput.value = total.toFixed(2);

        this.updateReturnTotal();
    },

    updateReturnTotal() {
        let totalAmount = 0;
        let hasErrors = false;

        // جمع المبلغ الإجمالي للإرجاع
        document.querySelectorAll(".custom-return-quantity").forEach((input) => {
            const value = parseFloat(input.value) || 0;
            const max = parseFloat(input.getAttribute("data-max"));

            if (value > max) {
                hasErrors = true;
            }

            const itemIndex = parseInt(input.getAttribute("data-item-index"));
            const invoice = InvoiceManager.getInvoiceById(this.currentInvoiceId);
            const item = invoice.items[itemIndex];

            totalAmount += value * item.price;
        });

        document.getElementById("customReturnTotalAmount").textContent = totalAmount.toFixed(2) + " ج.م";

        // حساب التأثير المالي
        if (totalAmount > 0 && !hasErrors) {
            const invoice = InvoiceManager.getInvoiceById(this.currentInvoiceId);
            const impact = this.calculateReturnImpact(invoice, totalAmount);

            // حفظ النتائج في العناصر المخفية للاستخدام لاحقاً
            document.getElementById("impactData").dataset.impact = JSON.stringify(impact);

            // عرض التفاصيل
            this.displayImpactDetails(impact, invoice);

            // تفعيل زر المعالجة
            document.getElementById("processCustomReturnBtn").disabled = false;
        } else {
            // إخفاء التفاصيل
            document.getElementById("impactDetails").style.display = "none";
            document.getElementById("refundMethodSection").style.display = "none";
            document.getElementById("processCustomReturnBtn").disabled = true;
        }
    }
    ,
    displayImpactDetails(impact, invoice) {
        const detailsContainer = document.getElementById("impactDetails");
        detailsContainer.style.display = "block";

        let detailsHTML = `
        <div class="alert alert-info mb-2">
            <i class="fas fa-calculator me-2"></i>
            <strong>تفاصيل التأثير المالي:</strong>
    `;

        // عرض خصم من المتبقي
        if (impact.amountFromRemaining > 0) {
            detailsHTML += `
            <div class="mt-1">
                <i class="fas fa-minus-circle text-warning me-1"></i>
                <strong>يخصم من المتبقي:</strong> ${impact.amountFromRemaining.toFixed(2)} ج.م
                ${impact.invoiceRemaining > 0 ?
                    `(من ${impact.invoiceRemaining.toFixed(2)} ج.م)` : ''}
            </div>
        `;
        }

        // عرض رد للعميل
        if (impact.amountFromPaid > 0) {
            detailsHTML += `
            <div class="mt-1">
                <i class="fas fa-undo text-success me-1"></i>
                <strong>يُرد للعميل:</strong> ${impact.amountFromPaid.toFixed(2)} ج.م
                ${impact.invoicePaid > 0 ?
                    `(من ${impact.invoicePaid.toFixed(2)} ج.م مدفوع)` : ''}
            </div>
        `;
        }

        // عرض القيم الجديدة للفاتورة
        detailsHTML += `
        </div>
        <div class="alert alert-warning mb-2">
            <i class="fas fa-chart-line me-2"></i>
            <strong>الفاتورة بعد الإرجاع:</strong>
            <div class="row mt-2">
                <div class="col-4">
                    <div class="small text-muted">الإجمالي الجديد</div>
                    <div class="fw-bold">${impact.newTotal.toFixed(2)} ج.م</div>
                </div>
                <div class="col-4">
                    <div class="small text-muted">المدفوع الجديد</div>
                    <div class="fw-bold">${impact.newPaid.toFixed(2)} ج.م</div>
                </div>
                <div class="col-4">
                    <div class="small text-muted">المتبقي الجديد</div>
                    <div class="fw-bold">${impact.newRemaining.toFixed(2)} ج.م</div>
                </div>
            </div>
        </div>
    `;

        detailsContainer.innerHTML = detailsHTML;

        // عرض قسم اختيار طريقة الرد إذا كان هناك مبلغ يرد
        if (impact.amountFromPaid > 0) {
            document.getElementById("refundMethodSection").style.display = "block";
        } else {
            document.getElementById("refundMethodSection").style.display = "none";
        }
    },

    returnAllItems() {
        const invoice = InvoiceManager.getInvoiceById(this.currentInvoiceId);
        document.querySelectorAll(".custom-return-quantity").forEach((input) => {
            const itemIndex = parseInt(input.getAttribute("data-item-index"));
            const availableQuantity = parseFloat(input.getAttribute("data-max"));
            input.value = availableQuantity;
            this.validateReturnItem(itemIndex, input);
            this.updateReturnItem(itemIndex);
        });
    },

    returnPartialItems() {
        document.querySelectorAll(".custom-return-quantity").forEach((input) => {
            input.disabled = false;
            input.focus();
        });
    },
    // في CustomReturnManager
    calculateReturnImpact(invoice, totalReturnAmount) {
        let amountFromRemaining = 0;
        let amountFromPaid = 0;
        let refundMethod = "credit_adjustment";
        let description = "";

        // السيناريو 1: الفاتورة نقدية (cash أو wallet)
        if (invoice.paymentMethod === "cash" || invoice.paymentMethod === "wallet") {
            // نقدي أو محفظة: العميل دفع بالفعل
            if (totalReturnAmount <= invoice.paid) {
                // المرتجع يغطيه المبلغ المدفوع
                amountFromRemaining = 0;
                amountFromPaid = totalReturnAmount;
                refundMethod = invoice.paymentMethod === "wallet" ? "wallet" : "cash";
                description = `يُرد للعميل: ${amountFromPaid.toFixed(2)} ج.م ${refundMethod === "wallet" ? "إلى المحفظة" : "نقداً"}`;
            } else {
                // المرتجع أكبر من المدفوع (سيناريو نادر)
                amountFromRemaining = 0;
                amountFromPaid = invoice.paid; // الحد الأقصى للرد
                refundMethod = invoice.paymentMethod === "wallet" ? "wallet" : "cash";
                description = `يُرد للعميل: ${amountFromPaid.toFixed(2)} ج.م ${refundMethod === "wallet" ? "إلى المحفظة" : "نقداً"} (الحد الأقصى للمدفوع)`;
            }
        }
        // السيناريو 2: الفاتورة آجلة (credit)
        else if (invoice.paymentMethod === "credit") {
            // آجل: لدينا حالتين - مدفوع جزئياً أو غير مدفوع
            if (invoice.paid > 0) {
                // دفع جزئي
                if (totalReturnAmount <= invoice.remaining) {
                    // الحالة 1: المرتجع يغطيه المتبقي فقط
                    amountFromRemaining = totalReturnAmount;
                    amountFromPaid = 0;
                    refundMethod = "credit_adjustment";
                    description = `يخصم من المتبقي: ${amountFromRemaining.toFixed(2)} ج.م`;
                } else {
                    // الحالة 2: المرتجع أكبر من المتبقي
                    amountFromRemaining = invoice.remaining;
                    amountFromPaid = totalReturnAmount - invoice.remaining;

                    // تأكد أن الرد لا يتجاوز المدفوع
                    if (amountFromPaid > invoice.paid) {
                        amountFromPaid = invoice.paid;
                    }

                    // عرض اختيار طريقة الرد (سيعرض في مودال اختياري لاحقاً)
                    refundMethod = "pending_choice"; // يحتاج اختيار
                    description = `يخصم من المتبقي: ${amountFromRemaining.toFixed(2)} ج.م + يُرد: ${amountFromPaid.toFixed(2)} ج.م (يحتاج اختيار طريقة)`;
                }
            } else {
                // لم يدفع أي شيء
                amountFromRemaining = totalReturnAmount;
                amountFromPaid = 0;
                refundMethod = "credit_adjustment";
                description = `يخصم من المتبقي: ${amountFromRemaining.toFixed(2)} ج.م`;
            }
        }

        // حساب القيم الجديدة للفاتورة
        const newTotal = invoice.total - totalReturnAmount;
        const newPaid = invoice.paid - amountFromPaid;
        const newRemaining = newTotal - newPaid;

        return {
            amountFromRemaining,
            amountFromPaid,
            refundMethod,
            description,
            newTotal,
            newPaid,
            newRemaining,
            invoicePaid: invoice.paid,
            invoiceRemaining: invoice.remaining,
            invoiceTotal: invoice.total
        };
    },
    processReturn() {
        // التحقق من صحة البيانات المدخلة
        let hasErrors = false;
        document.querySelectorAll(".custom-return-quantity").forEach((input) => {
            const itemIndex = input.getAttribute("data-item-index");
            if (!this.validateReturnItem(itemIndex, input)) {
                hasErrors = true;
            }
        });

        if (hasErrors) {
            Swal.fire("تحذير", "يوجد أخطاء في الكميات المدخلة، يرجى تصحيحها أولاً", "warning");
            return;
        }

        const returnReason = document.getElementById("customReturnReason").value.trim();
        if (!returnReason) {
            Swal.fire("تحذير", "يرجى إدخال سبب الإرجاع", "warning");
            return;
        }

        const invoice = InvoiceManager.getInvoiceById(this.currentInvoiceId);
        const returnItems = [];
        let totalReturnAmount = 0;
        let isFullReturn = true;

        // جمع البنود المراد إرجاعها
        document.querySelectorAll(".custom-return-quantity").forEach((input) => {
            const itemIndex = parseInt(input.getAttribute("data-item-index"));
            const quantity = parseFloat(input.value) || 0;

            if (quantity > 0) {
                const item = invoice.items[itemIndex];
                const total = quantity * item.price;

                returnItems.push({
                    productId: item.productId,
                    productName: item.productName,
                    quantity: quantity,
                    price: item.price,
                    total: total,
                    date: new Date().toISOString().split('T')[0]
                });

                totalReturnAmount += total;

                const availableQuantity = item.quantity - (item.returnedQuantity || 0);
                if (quantity < availableQuantity) {
                    isFullReturn = false;
                }
            }
        });

        if (returnItems.length === 0) {
            Swal.fire("تحذير", "لم يتم تحديد أي كميات للإرجاع", "warning");
            return;
        }

        // حساب التأثير المالي
        const impact = this.calculateReturnImpact(invoice, totalReturnAmount);

        // عرض مودال اختيار طريقة الرد إذا لزم الأمر
        if (impact.amountFromPaid > 0 && impact.refundMethod === "pending_choice") {
            this.showRefundMethodModal(impact, invoice, returnItems, totalReturnAmount, isFullReturn, returnReason);
        } else {
            // إذا لم يكن هناك حاجة لاختيار طريقة، أكمل مباشرة
            this.confirmReturnProcess(impact, invoice, returnItems, totalReturnAmount, isFullReturn, returnReason);
        }
    },

    showRefundMethodModal(impact, invoice, returnItems, totalReturnAmount, isFullReturn, returnReason) {
        Swal.fire({
            title: "اختر طريقة رد المبلغ",
            html: `
            <div class="text-start">
                <div class="alert alert-info mb-3">
                    <i class="fas fa-money-bill-wave me-2"></i>
                    <strong>تفاصيل المبلغ المراد رده:</strong>
                    <div class="mt-2">
                        <div><strong>المبلغ:</strong> ${impact.amountFromPaid.toFixed(2)} ج.م</div>
                        <div><strong>سبب:</strong> الفاتورة كانت مدفوعة جزئياً والمتبقي لا يكفي لتغطية المرتجع</div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label fw-bold">طريقة رد المبلغ:</label>
                    <div class="mt-2">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="refundMethodChoice" id="cashChoice" value="cash" checked>
                            <label class="form-check-label" for="cashChoice">
                                <i class="fas fa-money-bill-wave me-1"></i> استرجاع نقدي
                            </label>
                        </div>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="radio" name="refundMethodChoice" id="walletChoice" value="wallet">
                            <label class="form-check-label" for="walletChoice">
                                <i class="fas fa-wallet me-1"></i> إضافة للمحفظة
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        `,
            icon: "question",
            showCancelButton: true,
            confirmButtonText: "متابعة",
            cancelButtonText: "إلغاء",
            confirmButtonColor: "#3085d6",
            cancelButtonColor: "#d33",
            width: 500,
            preConfirm: () => {
                const selected = document.querySelector('input[name="refundMethodChoice"]:checked');
                if (!selected) {
                    Swal.showValidationMessage("يرجى اختيار طريقة رد المبلغ");
                    return false;
                }
                return selected.value;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const refundMethod = result.value;

                // تحديث الـ impact بطريقة الرد المختارة
                impact.refundMethod = refundMethod;

                // متابعة عملية التأكيد
                this.confirmReturnProcess(impact, invoice, returnItems, totalReturnAmount, isFullReturn, returnReason);
            }
        });
    },

    confirmReturnProcess(impact, invoice, returnItems, totalReturnAmount, isFullReturn, returnReason) {
        // إنشاء رسالة التأكيد
        let confirmMessage = `
        <div class="text-start">
            <h5 class="mb-3">تأكيد عملية الإرجاع</h5>
            <div class="alert alert-info">
                <i class="fas fa-file-invoice me-2"></i>
                <strong>تفاصيل الفاتورة:</strong> ${invoice.number}
            </div>
            
            <div class="alert alert-warning">
                <i class="fas fa-undo me-2"></i>
                <strong>تفاصيل المرتجع:</strong>
                <div class="mt-2">
                    <div><strong>المبلغ الإجمالي:</strong> ${totalReturnAmount.toFixed(2)} ج.م</div>
                    <div><strong>عدد المنتجات:</strong> ${returnItems.length}</div>
                    <div><strong>النوع:</strong> ${isFullReturn ? "إرجاع كلي" : "إرجاع جزئي"}</div>
                    <div><strong>السبب:</strong> ${returnReason}</div>
                </div>
            </div>
            
            <div class="alert alert-success">
                <i class="fas fa-exchange-alt me-2"></i>
                <strong>التأثير المالي:</strong>
                <div class="mt-2">
    `;

        if (impact.amountFromRemaining > 0) {
            confirmMessage += `
            <div><strong>يخصم من المتبقي:</strong> ${impact.amountFromRemaining.toFixed(2)} ج.م</div>
        `;
        }

        if (impact.amountFromPaid > 0) {
            const methodText = impact.refundMethod === "wallet" ? "إلى المحفظة" : "نقداً";
            confirmMessage += `
            <div><strong>يُرد للعميل:</strong> ${impact.amountFromPaid.toFixed(2)} ج.م ${methodText}</div>
        `;
        }

        confirmMessage += `
                </div>
            </div>
            
            <div class="alert alert-primary">
                <i class="fas fa-chart-line me-2"></i>
                <strong>الفاتورة بعد الإرجاع:</strong>
                <div class="row mt-2">
                    <div class="col-4">
                        <div class="small">الإجمالي</div>
                        <div class="fw-bold">${impact.newTotal.toFixed(2)} ج.م</div>
                    </div>
                    <div class="col-4">
                        <div class="small">المدفوع</div>
                        <div class="fw-bold">${impact.newPaid.toFixed(2)} ج.م</div>
                    </div>
                    <div class="col-4">
                        <div class="small">المتبقي</div>
                        <div class="fw-bold">${impact.newRemaining.toFixed(2)} ج.م</div>
                    </div>
                </div>
            </div>
        </div>
    `;

        // عرض مودال التأكيد النهائي
        Swal.fire({
            title: "التأكيد النهائي",
            html: confirmMessage,
            icon: "question",
            showCancelButton: true,
            confirmButtonText: "نعم، تنفيذ الإرجاع",
            cancelButtonText: "إلغاء",
            confirmButtonColor: "#3085d6",
            cancelButtonColor: "#d33",
            width: 600
        }).then((result) => {
            if (result.isConfirmed) {
                this.executeReturn(invoice, returnItems, totalReturnAmount, isFullReturn, returnReason, impact);
            }
        });
    },

    executeReturn(invoice, returnItems, totalReturnAmount, isFullReturn, returnReason, impact) {
        // إنشاء سجل المرتجع
        const returnData = {
            invoiceId: invoice.id,
            invoiceNumber: invoice.number,
            type: isFullReturn ? "full" : "partial",
            amount: totalReturnAmount,
            method: impact.refundMethod,
            reason: returnReason,
            items: returnItems,
            amountFromRemaining: impact.amountFromRemaining,
            amountFromPaid: impact.amountFromPaid,
            newTotal: impact.newTotal,
            newPaid: impact.newPaid,
            newRemaining: impact.newRemaining,
            originalPaymentMethod: invoice.paymentMethod
        };

        // إضافة المرتجع
        const returnItem = ReturnManager.addReturn(returnData);

        // تحديث الفاتورة
        InvoiceManager.updateInvoiceAfterReturn(invoice.id, returnData);

        // عرض رسالة النجاح
        const toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });

        toast.fire({
            icon: 'success',
            title: `تم معالجة المرتجع ${returnItem.number} بنجاح`
        });

        // إغلاق المودال
        const modal = bootstrap.Modal.getInstance(document.getElementById("customReturnModal"));
        modal.hide();

        // إعادة تعيين الحقول
        document.getElementById("customReturnReason").value = "";
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

                // حساب الكميات الأصلية والحالية
                const originalQuantity = item.quantity;
                const returnedQuantity = item.returnedQuantity || 0;
                const currentQuantity = item.currentQuantity || (originalQuantity - returnedQuantity);

                // حساب الإجماليات الأصلية والحالية
                const originalTotal = originalQuantity * item.price;
                const currentTotal = item.currentTotal || (currentQuantity * item.price);

                // عرض تاريخ المرتجع الأخير
                let lastReturnInfo = "";
                if (returnedQuantity > 0) {
                    const lastReturn = AppData.returns
                        .filter(r => r.invoiceId === invoiceId)
                        .map(r => r.items.find(i => i.productId === item.productId))
                        .filter(i => i)
                        .sort((a, b) => new Date(b.date) - new Date(a.date))[0];

                    if (lastReturn) {
                        lastReturnInfo = `<br><small class="text-muted">آخر إرجاع: ${lastReturn.quantity} بتاريخ ${lastReturn.date}</small>`;
                    }
                }

                let itemStatus = "";
                let rowClass = "";
                if (item.fullyReturned) {
                    itemStatus = '<span class="badge bg-danger">مرتجع كلي</span>';
                    rowClass = "fully-returned";
                } else if (returnedQuantity > 0) {
                    itemStatus = '<span class="badge bg-warning">مرتجع جزئي</span>';
                    rowClass = "partially-returned";
                } else {
                    itemStatus = '<span class="badge bg-success">سليم</span>';
                }

                row.className = rowClass;
                row.innerHTML = `
                <td>
                    <strong>${item.productName}</strong>
                    ${lastReturnInfo}
                    ${returnedQuantity > 0 ?
                        `<div class="mt-1">
                            <span class="badge bg-warning return-history-badge">
                                مرتجع: ${returnedQuantity}
                            </span>
                        </div>` :
                        ''}
                </td>
                <td>
                    <div class="d-flex flex-column">
                        <span class="text-muted small">أصلي: ${originalQuantity}</span>
                        <span class="fw-bold mt-1">حالي: ${currentQuantity}</span>
                    </div>
                </td>
                <td>
                    <div class="fw-bold">${item.price.toFixed(2)} ج.م</div>
                </td>
                <td>
                    <div class="d-flex flex-column">
                        <span class="text-muted small" style="text-decoration: line-through;">
                            ${originalTotal.toFixed(2)} ج.م
                        </span>
                        <span class="fw-bold mt-1">
                            ${currentTotal.toFixed(2)} ج.م
                        </span>
                    </div>
                </td>
                <td>
                    <div class="fw-bold text-warning">${returnedQuantity}</div>
                    ${returnedQuantity > 0 ?
                        `<div class="small text-muted">باقي: ${originalQuantity - returnedQuantity}</div>` :
                        ''}
                </td>
                <td>${itemStatus}</td>
            `;
                tbody.appendChild(row);
            });

            // إضافة مستمع حدث لزر الطباعة
            document.getElementById("printInvoiceItemsBtn").onclick = () => {
                this.printInvoiceDetails(invoiceId);
            };

            const modal = new bootstrap.Modal(document.getElementById("invoiceItemsModal"));
            modal.show();
        }
    },

    printInvoiceReturns(invoiceId) {
        const invoice = InvoiceManager.getInvoiceById(invoiceId);
        const returns = ReturnManager.getReturnsByInvoiceId(invoiceId);

        if (returns.length === 0) {
            Swal.fire("تنبيه", "لا توجد مرتجعات لهذه الفاتورة", "info");
            return;
        }

        const report = {
            invoicesCount: 1,
            items: [],
            totals: {
                beforeDiscount: 0,
                afterDiscount: 0,
                discount: 0,
            },
            invoices: [
                {
                    id: invoice.id,
                    customer: AppData.currentCustomer.name,
                    total: returns.reduce((sum, r) => sum + r.amount, 0),
                },
            ],
        };

        // تجميع بنود المرتجعات
        returns.forEach((returnItem) => {
            returnItem.items.forEach((item) => {
                report.items.push({
                    name: item.productName,
                    quantity: item.quantity,
                    price: item.price,
                    total: item.total,
                });
            });
        });

        report.totals.beforeDiscount = report.invoices[0].total;
        report.totals.afterDiscount = report.invoices[0].total;

        // استخدام دالة الطباعة المجمعة
        printAggregatedReport(report);
    },

    showInvoiceReturns(invoiceId) {
        const invoice = InvoiceManager.getInvoiceById(invoiceId);
        if (!invoice) return;

        const returns = ReturnManager.getReturnsByInvoiceId(invoiceId);

        document.getElementById("returnsInvoiceNumber").textContent =
            invoice.number;

        // حساب إجمالي المرتجعات
        const totalReturns = returns.reduce((sum, r) => sum + r.amount, 0);
        document.getElementById("totalReturnsAmountForInvoice").textContent =
            totalReturns.toFixed(2) + " ج.م";

        // عرض قائمة المرتجعات
        const container = document.getElementById("invoiceReturnsList");
        container.innerHTML = "";

        if (returns.length === 0) {
            container.innerHTML =
                '<div class="alert alert-info">لا توجد مرتجعات لهذه الفاتورة</div>';
        } else {
            returns.forEach((returnItem) => {
                const returnElement = document.createElement("div");
                returnElement.className = "return-item";

                let itemsHTML = "";
                returnItem.items.forEach((item) => {
                    itemsHTML += `
                                <div class="d-flex justify-content-between">
                                    <span>${item.productName}</span>
                                    <span>${item.quantity
                        } × ${item.price.toFixed(
                            2
                        )} = ${item.total.toFixed(2)} ج.م</span>
                                </div>
                            `;
                });

                returnElement.innerHTML = `
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <strong>${returnItem.number}</strong>
                                    <br>
                                    <small class="text-muted">${returnItem.date
                    } - ${returnItem.reason}</small>
                                </div>
                                <div>
                                    <span class="badge ${returnItem.type === "full"
                        ? "bg-danger"
                        : "bg-warning"
                    }">
                                        ${returnItem.type === "full"
                        ? "إرجاع كلي"
                        : "إرجاع جزئي"
                    }
                                    </span>
                                    <span class="badge ${returnItem.method === "wallet"
                        ? "bg-info"
                        : "bg-success"
                    } ms-1">
                                        ${returnItem.method === "wallet"
                        ? "محفظة"
                        : "نقدي"
                    }
                                    </span>
                                </div>
                            </div>
                            <div class="mt-2">
                                ${itemsHTML}
                            </div>
                            <div class="d-flex justify-content-between mt-2 fw-bold">
                                <span>المبلغ الإجمالي:</span>
                                <span>${returnItem.amount.toFixed(2)} ج.م</span>
                            </div>
                        `;

                container.appendChild(returnElement);
            });
        }

        // إضافة مستمع حدث لزر الطباعة
        document.getElementById("printInvoiceReturnsBtn").onclick = () => {
            this.printInvoiceReturns(invoiceId);
        };

        const modal = new bootstrap.Modal(
            document.getElementById("invoiceReturnsModal")
        );
        modal.show();
    },

};


export { ReturnManager, CustomReturnManager };