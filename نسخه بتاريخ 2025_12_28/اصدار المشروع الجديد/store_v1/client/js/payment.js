
import AppData from './app_data.js';

import apis from './constant/api_links.js';
import { PaymentMethods, toggleSection, updateInvoiceStats } from './helper.js';
import InvoiceManager from './invoices.js';
import CustomerManager from './customer.js';
import CustomerTransactionManager from './transaction.js';
import WalletManager from './wallet.js';
import WorkOrderManager from './work_order.js';
import UIManager from './ui.js';
// import { log } from 'winjs';
const PaymentManager = {
    init() {
        this.setupPaymentEventListeners();

    },
    setupPaymentEventListeners() {
        // تغيير نوع السداد
        document.querySelectorAll('input[name="paymentType"]').forEach(radio => {
            radio.addEventListener('change', function () {
                const paymentType = this.value;

                // إظهار/إخفاء الأقسام
                document.getElementById('invoicesPaymentSection').style.display =
                    paymentType === 'invoices' ? 'block' : 'none';
                document.getElementById('workOrderPaymentSection').style.display =
                    paymentType === 'workOrder' ? 'block' : 'none';

                // إعادة تعيين الحقول
                PaymentManager.resetPaymentForm();

                // تحميل البيانات حسب النوع
                if (paymentType === 'invoices') {
                    PaymentManager.loadInvoicesForPayment();
                } else {
                    PaymentManager.resetWorkOrderSearch();
                }
            });
        });

        // بحث في الفواتير
        document.getElementById('invoiceSearch').addEventListener('input', function (e) {
            PaymentManager.filterInvoicesForPayment(e.target.value);
        });

        // بحث في الشغلانات
        document.getElementById('workOrderSearch').addEventListener('input', function (e) {
            PaymentManager.searchWorkOrders(e.target.value);
        });

        // تحديد/إلغاء تحديد جميع الفواتير
        document.getElementById('selectAllInvoicesForPayment').addEventListener('change', function () {

            PaymentManager.toggleSelectAllInvoices(this.checked);
        });

        // إضافة طريقة دفع
        document.getElementById('addPaymentMethodBtn').addEventListener('click', function () {

            PaymentManager.addPaymentMethod();
        });

        // معالجة السداد
        document.getElementById('processPaymentBtn').addEventListener('click', function () {
            PaymentManager.processPayment();
        });

        // تحديث المبالغ عند الإدخال
        document.addEventListener('input', function (e) {
            if (e.target.classList.contains('invoice-payment-amount') ||
                e.target.classList.contains('workorder-invoice-payment-amount') ||
                e.target.classList.contains('payment-method-amount')) {
                PaymentManager.updatePaymentSummary();
            }
        });

        // تغيير طريقة الدفع
        document.addEventListener('change', function (e) {
            if (e.target.classList.contains('payment-method-select')) {
                PaymentManager.updatePaymentSummary();
            }
        });
        // في setupPaymentEventListeners()
        // أزرار التحديد في قسم الفواتير
        document.getElementById('selectAllInvoicesBtn').addEventListener('click', function () {
            PaymentManager.loadInvoicesForPayment();
            PaymentManager.selectAllForPayment();
            PaymentManager.updatePaymentSummary();



            // PaymentManager.toggleSelectAllInvoices(this.checked);

        });

        document.getElementById('selectNonWorkOrderBtn').addEventListener('click', function () {
            PaymentManager.selectNonWorkOrderForPayment();
            PaymentManager.updatePaymentSummary();

        });

        // زر التحديد في قسم الشغلانات
        // document.getElementById('selectAllWorkOrderInvoicesBtn').addEventListener('click', function() {
        //     PaymentManager.selectAllWorkOrderInvoices();
        // });

        // زر التوزيع التلقائي
        document.getElementById('autoDistributeBtn').addEventListener('click', function () {
            PaymentManager.autoDistribute();
            PaymentManager.updatePaymentSummary();
        });

        // تحديث التحقق عند أي تغيير
        document.addEventListener('input', function (e) {
            if (e.target.classList.contains('payment-method-amount') ||
                e.target.classList.contains('invoice-payment-amount') ||
                e.target.classList.contains('workorder-invoice-payment-amount')) {
                PaymentManager.validatePayment();
            }
        });

        // تحديث المبلغ المطلوب عند تحديد/إلغاء الفواتير
        document.addEventListener('change', function (e) {
            if (e.target.classList.contains('invoice-payment-checkbox')) {
                this.toggleInvoicePaymentInput(e.target);
            }
        });
        // تحديث التحقق عند أي تغيير في طرق الدفع
        document.addEventListener('input', function (e) {
            if (e.target.classList.contains('payment-method-amount')) {
                PaymentManager.calculatePaymentMethodsTotal();
                PaymentManager.validatePayment();
            }
        });

        // تحديث التحقق عند أي تغيير في مبالغ الفواتير
        document.addEventListener('input', function (e) {
            if (e.target.classList.contains('invoice-payment-amount') ||
                e.target.classList.contains('workorder-invoice-payment-amount')) {
                PaymentManager.validatePayment();
            }
        });
    },

    updateManualPaymentTable() {
        const tbody = document.getElementById("manualPaymentTableBody");
        tbody.innerHTML = "";

        const unpaidInvoices = AppData.invoices.filter(
            (i) => i.remaining > 0
        );

        unpaidInvoices.forEach((invoice) => {
            const row = document.createElement("tr");
            row.className = "invoice-item-hover";

            // إنشاء tooltip للبنود
            let itemsTooltip = "";
            if (invoice.items && invoice.items.length > 0) {
                const itemsList = invoice.items
                    .map((item) => {
                        const itemTotal = (item.quantity || 0) * (item.price || 0);
                        return `
                                <div class="tooltip-item">
                                    <div>
                                        <div class="tooltip-item-name">${item.productName || "منتج"
                            }</div>
                                        <div class="tooltip-item-details">الكمية: ${item.quantity || 0
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
                                <div class="tooltip-header">بنود الفاتورة ${invoice.number
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
                            ${invoice.workOrderId ?? ''}
                        </td>
                        <td>${invoice.date}</td>
                        <td>${invoice.total.toFixed(2)} ج.م</td>
                        <td>${invoice.remaining.toFixed(2)} ج.م</td>
                        <td>
                            <input type="number" class="form-control form-control-sm manual-payment-amount" 
                                   data-invoice-id="${invoice.id}" 
                                   min="0" max="${invoice.remaining}" 
                                   value="0" >
                        </td>
                    `;
            tbody.appendChild(row);
        });

        this.resetPaymentMethods(
            "manualPaymentMethods",
            "manualPaymentTotal"
        );
        this.updateManualPaymentTotal();
    },

    updateWorkOrderPaymentSelect() {
        const select = document.getElementById("workOrderPaymentSelect");
        select.innerHTML = '<option value="">اختر الشغلانة</option>';

        AppData.workOrders.forEach((workOrder) => {
            const option = document.createElement("option");
            option.value = workOrder.id;
            option.textContent = workOrder.name;
            select.appendChild(option);
        });

        document.getElementById("workOrderInvoicesSection").style.display =
            "none";
    },

    updateWorkOrderInvoices(workOrderId) {
        const invoices = WorkOrderManager.getWorkOrderInvoices(workOrderId);
        const tbody = document.getElementById("workOrderInvoicesTableBody");
        tbody.innerHTML = "";

        let hasUnpaidInvoices = false;

        invoices.forEach((invoice) => {

            if (invoice.remaining > 0) {
                hasUnpaidInvoices = true;
                const row = document.createElement("tr");
                row.className = "invoice-item-hover";

                // إنشاء tooltip للبنود
                let itemsTooltip = "";
                if (invoice.items && invoice.items.length > 0) {
                    const itemsList = invoice.items
                        .map((item) => {
                            const itemTotal = (item.quantity || 0) * (item.price || 0);
                            return `
                                    <div class="tooltip-item">
                                        <div>
                                            <div class="tooltip-item-name">${item.productName || "منتج"
                                }</div>
                                            <div class="tooltip-item-details">الكمية: ${item.quantity || 0
                                } | السعر: ${(
                                    item.price || 0
                                ).toFixed(2)} ج.م</div>
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
                                    <div class="tooltip-header">بنود الفاتورة ${invoice.number
                        }</div>
                                    ${itemsList}
                                    <div class="tooltip-total">
                                        <span>الإجمالي:</span>
                                        <span>${invoice.total.toFixed(
                            2
                        )} ج.م</span>
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
                            <td>${invoice.remaining.toFixed(2)} ج.م</td>
                            <td>
                                <input type="number" class="form-control form-control-sm workorder-payment-amount" 
                                       data-invoice-id="${invoice.id}" 
                                       min="0" max="${invoice.remaining}" 
                                       value="${invoice.remaining}" step="0.01">
                            </td>
                        `;
                tbody.appendChild(row);
            }
        });

        if (hasUnpaidInvoices) {
            document.getElementById("workOrderInvoicesSection").style.display =
                "block";
            this.resetPaymentMethods(
                "workOrderPaymentMethods",
                "workOrderPaymentTotal"
            );
            this.updateWorkOrderPaymentTotal();
        } else {
            document.getElementById("workOrderInvoicesSection").style.display =
                "none";
            Swal.fire(
                "تنبيه",
                "لا توجد فواتير غير مسددة في هذه الشغلانة.",
                "info"
            );
        }
    },


    toggleInvoicePaymentInput(checkbox) {
        const invoiceId = checkbox.getAttribute('data-invoice-id');
        const amountInput = document.querySelector(`.invoice-payment-amount[data-invoice-id="${invoiceId}"]`);

        if (amountInput) {
            amountInput.disabled = !checkbox.checked;
            if (!checkbox.checked) {
                amountInput.value = 0;
            }
            amountInput.dispatchEvent(new Event('input'));
        }
    },


    // إضافة دالة resetPaymentForm لإعادة تعيين المودال

    resetPaymentMethods(containerId, totalElementId) {
        document.getElementById(containerId).innerHTML = "";
        document.getElementById(totalElementId).textContent = "0.00 ج.م";
        this.addPaymentMethod(containerId, totalElementId);
        // إعادة تعيين الحقول
        document.getElementById('invoicesTotalAmount').value = '0.00';
        document.getElementById('invoicesTotalAmountWorkOrder').value = '0.00';
        document.getElementById('workOrderTotalAmount').value = '0.00';
        document.getElementById('totalPaymentMethodsAmount').value = '0.00';
        document.getElementById('paymentRequiredAmount').value = '0.00';

        // إخفاء رسائل التحقق
        document.getElementById('paymentValid').style.display = 'none';
        document.getElementById('paymentInvalid').style.display = 'none';
        document.getElementById('paymentExceeds').style.display = 'none';

        // تعطيل زر السداد
        document.getElementById('processPaymentBtn').disabled = true;
    },

    updateManualPaymentTotal() {
        let total = 0;

        // جمع المبالغ من المدخلات
        document
            .querySelectorAll(".manual-payment-amount")
            .forEach((input) => {
                total += parseFloat(input.value) || 0;
            });

        document.getElementById("manualPaymentTotal").textContent =
            total.toFixed(2) + " ج.م";

        // تحديث الرصيد المتاح والمتبقي
        const availableBalance = WalletManager.getAvailableBalance();
        const remainingBalance = availableBalance - total;

        document.getElementById("manualPaymentAvailableBalance").textContent =
            availableBalance.toFixed(2) + " ج.م";
        document.getElementById("manualPaymentRemainingBalance").textContent =
            remainingBalance.toFixed(2) + " ج.م";

        // تغيير لون المتبقي إذا كان سالباً
        const remainingElement = document.getElementById(
            "manualPaymentRemainingBalance"
        );
        if (remainingBalance < 0) {
            remainingElement.classList.add("text-danger");
            remainingElement.classList.remove("text-success");
        } else {
            remainingElement.classList.add("text-success");
            remainingElement.classList.remove("text-danger");
        }
    },

    updateWorkOrderPaymentTotal() {
        let total = 0;

        // جمع المبالغ من المدخلات
        document
            .querySelectorAll(".workorder-payment-amount")
            .forEach((input) => {
                total += parseFloat(input.value) || 0;
            });

        document.getElementById("workOrderPaymentTotal").textContent =
            total.toFixed(2) + " ج.م";

        // تحديث الرصيد المتاح والمتبقي
        const availableBalance = WalletManager.getAvailableBalance();
        const remainingBalance = availableBalance - total;

        document.getElementById(
            "workOrderPaymentAvailableBalance"
        ).textContent = availableBalance.toFixed(2) + " ج.م";
        document.getElementById(
            "workOrderPaymentRemainingBalance"
        ).textContent = remainingBalance.toFixed(2) + " ج.م";

        // تغيير لون المتبقي إذا كان سالباً
        const remainingElement = document.getElementById(
            "workOrderPaymentRemainingBalance"
        );
        if (remainingBalance < 0) {
            remainingElement.classList.add("text-danger");
            remainingElement.classList.remove("text-success");
        } else {
            remainingElement.classList.add("text-success");
            remainingElement.classList.remove("text-danger");
        }
    },

    collectPaymentMethods(containerId) {
        const methods = [];
        const container = document.getElementById(containerId);

        container.querySelectorAll(".payment-method-item").forEach((item) => {
            const methodSelect = item.querySelector(".payment-method-select");
            const amountInput = item.querySelector(".payment-method-amount");

            const methodId = parseInt(methodSelect.value);
            const amount = parseFloat(amountInput.value) || 0;

            if (methodId && amount > 0) {
                const method = PaymentMethods.find((pm) => pm.id === methodId);
                if (method) {
                    methods.push({
                        method: method.name,
                        amount: amount,
                    });
                }
            }
        });

        return methods;
    },



    // دالة إعادة تعيين المودال
    resetPaymentModal() {
        // إعادة تعيين الفواتير المحددة
        document.querySelectorAll('.invoice-payment-checkbox').forEach(checkbox => {
            checkbox.checked = false;
        });

        // إعادة تعيين المبالغ
        document.querySelectorAll('.invoice-payment-amount').forEach(input => {
            input.value = 0;
            input.disabled = true;
        });

        document.querySelectorAll('.workorder-invoice-payment-amount').forEach(input => {
            input.value = 0;
        });

        // إعادة تعيين طرق الدفع
        document.getElementById('paymentMethodsContainer').innerHTML = '';
        this.addPaymentMethod();

        // إعادة تعيين الحقول
        document.getElementById('invoicesTotalAmount').value = '0.00';
        document.getElementById('invoicesTotalAmountWorkOrder').value = '0.00';

        document.getElementById('totalRequiredAmount').textContent = 0 + ' ج.م';

        document.getElementById('workOrderTotalAmount').value = '0.00';
        document.getElementById('totalPaymentMethodsAmount').value = '0.00';
        document.getElementById('paymentRequiredAmount').value = '0.00';

        // إخفاء رسائل التحقق
        document.getElementById('paymentValid').style.display = 'none';
        document.getElementById('paymentInvalid').style.display = 'none';
        document.getElementById('paymentExceeds').style.display = 'none';

        // تعطيل زر السداد
        document.getElementById('processPaymentBtn').disabled = true;
    },

    processManualPayment() {
        const paymentMethods = this.collectPaymentMethods(
            "manualPaymentMethods"
        );
        const totalPayment = paymentMethods.reduce(
            (sum, pm) => sum + pm.amount,
            0
        );

        if (totalPayment <= 0) {
            Swal.fire("تحذير", "يرجى إدخال مبالغ صحيحة للدفع.", "warning");
            return;
        }

        let totalPaid = 0;
        const paymentInputs = document.querySelectorAll(
            ".manual-payment-amount"
        );

        paymentInputs.forEach((input) => {
            const amount = parseFloat(input.value) || 0;
            const invoiceId = parseInt(input.getAttribute("data-invoice-id"));
            const invoice = AppData.invoices.find((i) => i.id === invoiceId);

            if (invoice && amount > 0 && amount <= invoice.remaining) {
                this.addPaymentToInvoice(invoiceId, amount, paymentMethods);
                totalPaid += amount;
            }
        });

        if (totalPaid > 0) {
            Swal.fire(
                "نجاح",
                `تم سداد ${totalPaid.toFixed(2)} ج.م بنجاح.`,
                "success"
            );
            const paymentModal = bootstrap.Modal.getInstance(
                document.getElementById("paymentModal")
            );
            paymentModal.hide();

            setTimeout(() => {
                this.fixBodyScroll();
            }, 300);

        } else {
            Swal.fire(
                "تحذير",
                "لم يتم تحديد أي مبالغ للدفع أو القيم غير صالحة.",
                "warning"
            );
        }
    },

    processWorkOrderPayment() {
        const workOrderId = parseInt(
            document.getElementById("workOrderPaymentSelect").value
        );
        if (!workOrderId) {
            Swal.fire("تحذير", "يرجى اختيار شغلانة.", "warning");
            return;
        }

        const paymentMethods = this.collectPaymentMethods(
            "workOrderPaymentMethods"
        );
        const totalPayment = paymentMethods.reduce(
            (sum, pm) => sum + pm.amount,
            0
        );

        if (totalPayment <= 0) {
            Swal.fire("تحذير", "يرجى إدخال مبالغ صحيحة للدفع.", "warning");
            return;
        }

        let totalPaid = 0;
        const paymentInputs = document.querySelectorAll(
            ".workorder-payment-amount"
        );

        paymentInputs.forEach((input) => {
            const amount = parseFloat(input.value) || 0;
            const invoiceId = parseInt(input.getAttribute("data-invoice-id"));
            const invoice = AppData.invoices.find((i) => i.id === invoiceId);

            if (invoice && amount > 0 && amount <= invoice.remaining) {
                this.addPaymentToInvoice(invoiceId, amount, paymentMethods);
                totalPaid += amount;
            }
        });

        if (totalPaid > 0) {
            Swal.fire(
                "نجاح",
                `تم سداد ${totalPaid.toFixed(2)} ج.م للشغلانة بنجاح.`,
                "success"
            );
            const paymentModal = bootstrap.Modal.getInstance(
                document.getElementById("paymentModal")
            );
            paymentModal.hide();
            setTimeout(() => {
                this.fixBodyScroll();
            }, 300);

        } else {
            Swal.fire(
                "تحذير",
                "لم يتم تحديد أي مبالغ للدفع أو القيم غير صالحة.",
                "warning"
            );
        }
    },

    openSingleInvoicePayment(invoiceId) {
        // تعيين نوع السداد إلى فواتير
        document.getElementById('payInvoicesRadio').checked = true;
        document.getElementById('invoicesPaymentSection').style.display = 'block';
        document.getElementById('workOrderPaymentSection').style.display = 'none';

        // تحميل الفواتير
        this.loadInvoicesForPayment();


        // تحديد الفاتورة المطلوبة فقط
        setTimeout(() => {
            const checkbox = document.querySelector(`.invoice-payment-checkbox[data-invoice-id="${invoiceId}"]`);
            if (checkbox) {
                const targetInvoice = document.getElementById('invoiceSearch');
                targetInvoice.value = invoiceId;
                targetInvoice.dispatchEvent(new Event('input'));
                checkbox.checked = true;
                checkbox.dispatchEvent(new Event('change'));

                const amountInput = document.querySelector(`.invoice-payment-amount[data-invoice-id="${invoiceId}"]`);
                if (amountInput) {
                    amountInput.value = amountInput.getAttribute('max');
                    amountInput.dispatchEvent(new Event('input'));
                }

                // التمرير إلى الصف المحدد
                const row = checkbox.closest('tr');
                if (row) {
                    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    row.style.backgroundColor = 'rgba(13, 110, 253, 0.1)';
                    setTimeout(() => {
                        row.style.backgroundColor = '';
                    }, 3000);
                }
            }
        }, 100);

        // فتح المودال
        const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
        paymentModal.show();
    }
    , openWorkOrderPayment(workOrderId) {
        document.getElementById("workOrderPayment").checked = true;
        document.getElementById("manualPaymentSection").style.display =
            "none";
        document.getElementById("workOrderPaymentSection").style.display =
            "block";

        // تحديد الشغلانة
        document.getElementById("workOrderPaymentSelect").value = workOrderId;
        this.updateWorkOrderInvoices(workOrderId);

        // فتح مودال السداد
        const paymentModal = new bootstrap.Modal(
            document.getElementById("paymentModal")
        );
        paymentModal.show();
    },
    // في دالة loadInvoicesForPayment داخل PaymentManager
    loadInvoicesForPayment() {
        const tbody = document.getElementById('invoicesPaymentTableBody');
        tbody.innerHTML = '';

        const unpaidInvoices = AppData.invoices.filter(i => i.remaining > 0);

        unpaidInvoices.forEach(invoice => {

            const row = document.createElement('tr');
            row.className = 'invoice-item-hover';

            // الحصول على اسم الشغلانة إذا كانت مرتبطة


            // إنشاء tooltip للبنود
            // let itemsTooltip = '';
            // if (invoice.items && invoice.items.length > 0) {
            //     const itemsList = invoice.items.map(item => {
            //         const itemTotal = (item.quantity || 0) * (item.price || 0);
            //         return `
            //         <div class="tooltip-item">
            //             <div>
            //                 <div class="tooltip-item-name">${item.productName || 'منتج'}</div>
            //                 <div class="tooltip-item-details">الكمية: ${item.quantity || 0} | السعر: ${(item.price || 0).toFixed(2)} ج.م</div>
            //             </div>
            //             <div class="fw-bold">${itemTotal.toFixed(2)} ج.م</div>
            //         </div>
            //     `;
            //     }).join('');

            //     itemsTooltip = `
            //     <div class="invoice-items-tooltip">
            //         <div class="tooltip-header">بنود الفاتورة ${invoice.number}</div>
            //         ${itemsList}
            //         <div class="tooltip-total">
            //             <span>الإجمالي:</span>
            //             <span>${invoice.total.toFixed(2)} ج.م</span>
            //         </div>
            //     </div>
            // `;
            // }

            row.innerHTML = `
            <td>
                <input type="checkbox" class="form-check-input invoice-payment-checkbox" 
                       data-invoice-id="${invoice.id}"
                       data-has-workorder="${invoice.workOrderId ? 'true' : 'false'}">
            </td>
            <td class="position-relative">
                <strong>${invoice.id}</strong>
${invoice.workOrderName ? `<br><small class="text-muted"><i class="fas fa-tools me-1"></i>${invoice.workOrderName}</small>` : ''}
            </td>
            <td>${invoice.date}</td>
            <td>${invoice.total.toFixed(2)} ج.م</td>
            <td>${invoice.remaining.toFixed(2)} ج.م</td>
            <td>
                <input type="number" class="form-control form-control-sm invoice-payment-amount" 
                       data-invoice-id="${invoice.id}" 
                       data-workorder-id="${invoice.workOrderId || ''}"
                       min="0" max="${invoice.remaining}" 
                       value="0" step="0.01" disabled>
            </td>
        `;
            tbody.appendChild(row);

            // تفعيل/تعطيل حقل الإدخال بناءً على التحديد
            const checkbox = row.querySelector('.invoice-payment-checkbox');
            const amountInput = row.querySelector('.invoice-payment-amount');

            checkbox.addEventListener('change', function () {
                amountInput.disabled = !this.checked;
                if (!this.checked) {
                    amountInput.value = 0;
                }
                PaymentManager.updatePaymentSummary();
            });

            amountInput.addEventListener('input', function () {
                const maxAmount = parseFloat(this.getAttribute('max'));
                const currentValue = parseFloat(this.value) || 0;

                if (currentValue > maxAmount) {
                    this.value = maxAmount;
                    Swal.fire('تحذير', `لا يمكن سداد أكثر من ${maxAmount.toFixed(2)} ج.م لهذه الفاتورة`, 'warning');
                }

                // التحقق من القيمة
                if (currentValue < 0) {
                    this.value = 0;
                    Swal.fire('تحذير', 'لا يمكن إدخال قيمة سالبة', 'warning');
                }

                PaymentManager.updatePaymentSummary();
            });
        });

        PaymentManager.resetPaymentMethods();
        PaymentManager.updatePaymentSummary();
    },
    filterInvoicesForPayment(searchTerm) {
        const rows = document.querySelectorAll('#invoicesPaymentTableBody tr');

        rows.forEach(row => {
            const invoiceNumber = row.querySelector('td:nth-child(2)').textContent;
            const isVisible = invoiceNumber.toLowerCase().includes(searchTerm.toLowerCase());
            row.style.display = isVisible ? '' : 'none';
        });
    },

    searchWorkOrders(searchTerm) {
        const container = document.getElementById('workOrderSearchResults');

        if (!searchTerm || searchTerm.length < 1) {
            container.style.display = 'none';
            return;
        }



        const results = AppData.workOrders.filter(workOrder =>
            workOrder.title.toLowerCase().includes(searchTerm.toLowerCase()) ||
            workOrder.description.toLowerCase().includes(searchTerm.toLowerCase())
        );

        container.innerHTML = '';

        if (results.length === 0) {
            container.innerHTML = '<div class="p-3 text-muted text-center">لا توجد نتائج</div>';
            container.style.display = 'block';
            return;
        }

        results.forEach(workOrder => {
            const div = document.createElement('div');
            div.className = 'search-result-item';
            div.innerHTML = `
            <div class="fw-bold">${workOrder.name}</div>
            <div class="small text-muted">${workOrder.description}</div>
            <div class="small">تاريخ البدء: ${workOrder.startDate} | الحالة: ${workOrder.status === 'pending' ? 'قيد التنفيذ' : 'مكتمل'}</div>

        `;

            div.addEventListener('click', function () {
                PaymentManager.selectWorkOrderForPayment(workOrder.id);
                container.style.display = 'none';
                document.getElementById('workOrderSearch').value = workOrder.name;
            });

            container.appendChild(div);
        });

        container.style.display = 'block';
    },


    selectWorkOrderForPayment(workOrderId) {
        const workOrder = AppData.workOrders.find(wo => wo.id === workOrderId);
        if (!workOrder) return;

        // تحديث تفاصيل الشغلانة
        document.getElementById('selectedWorkOrderName').textContent = workOrder.name;
        document.getElementById('selectedWorkOrderDescription').textContent = workOrder.description;
        document.getElementById('selectedWorkOrderStartDate').textContent = workOrder.startDate;
        document.getElementById('selectedWorkOrderStatus').textContent =
            workOrder.status === 'pending' ? 'قيد التنفيذ' : 'مكتمل';

        // الحصول على الفواتير المرتبطة
        const relatedInvoices = AppData.invoices.filter(inv =>
            inv.work_order_id == workOrderId && inv.remaining > 0
        );

        document.getElementById('selectedWorkOrderInvoicesCount').textContent =
            `${relatedInvoices.length} فاتورة`;

        // ✅ إضافة تحذير إذا لم تكن هناك فواتير
        const noInvoicesAlert = document.getElementById('noInvoicesAlert');
        if (relatedInvoices.length === 0) {
            if (noInvoicesAlert) {
                noInvoicesAlert.style.display = 'block';
                noInvoicesAlert.innerHTML = `
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>تنبيه:</strong> هذه الشغلانة لا تحتوي على فواتير للدفع.
                    يمكنك إضافة فاتورة جديدة أو اختيار شغلانة أخرى.
                </div>
            `;
            }

            // إخفاء جدول الفواتير
            const invoiceTable = document.getElementById('workOrderInvoicesTable');
            if (invoiceTable) invoiceTable.style.display = 'none';

            // تعطيل زر السداد مؤقتاً
            const payButton = document.getElementById('processWorkOrderPaymentBtn');
            if (payButton) payButton.disabled = true;

        } else {
            // إخفاء التحذير إذا كانت هناك فواتير
            if (noInvoicesAlert) noInvoicesAlert.style.display = 'none';

            // إظهار جدول الفواتير
            const invoiceTable = document.getElementById('workOrderInvoicesTable');
            if (invoiceTable) invoiceTable.style.display = 'table';

            // تفعيل زر السداد
            const payButton = document.getElementById('processWorkOrderPaymentBtn');
            if (payButton) payButton.disabled = false;
        }

        // تحديث جدول فواتير الشغلانة (فقط إذا كانت هناك فواتير)
        const tbody = document.getElementById('workOrderInvoicesTableBody');
        tbody.innerHTML = '';

        if (relatedInvoices.length > 0) {
            relatedInvoices.forEach(invoice => {
                const row = document.createElement('tr');
                // row.className = 'invoice-item-hover';

                // // إنشاء tooltip للبنود
                // let itemsTooltip = '';
                // if (invoice.items && invoice.items.length > 0) {
                //     const itemsList = invoice.items.map(item => {
                //         const itemTotal = (item.quantity || 0) * (item.price || 0);
                //         return `
                //         <div class="tooltip-item">
                //             <div>
                //                 <div class="tooltip-item-name">${item.productName || 'منتج'}</div>
                //                 <div class="tooltip-item-details">الكمية: ${item.quantity || 0} | السعر: ${(item.price || 0).toFixed(2)} ج.م</div>
                //             </div>
                //             <div class="fw-bold">${itemTotal.toFixed(2)} ج.م</div>
                //         </div>
                //     `;
                //     }).join('');

                //     itemsTooltip = `
                //     <div class="invoice-items-tooltip">
                //         <div class="tooltip-header">بنود الفاتورة ${invoice.number}</div>
                //         ${itemsList}
                //         <div class="tooltip-total">
                //             <span>الإجمالي:</span>
                //             <span>${invoice.total.toFixed(2)} ج.م</span>
                //         </div>
                //     </div>
                // `;
                // ${itemsTooltip}
                // }

                row.innerHTML = `
                  <td>
          
            <td class="position-relative">
                ${invoice.id}
            </td>
            <td>${invoice.date}</td>
            <td>${invoice.total?.toFixed(2)} ج.م</td>
            <td>${invoice.paid.toFixed(2)} ج.م</td>
            <td>${invoice.remaining.toFixed(2)} ج.م</td>
            <td>
                <input type="number" class="form-control form-control-sm workorder-invoice-payment-amount" 
                       data-invoice-id="${invoice.id}" 
                       min="0" max="${invoice.remaining}" 
                       value="0" step="0.01">
            </td>
        `;
                tbody.appendChild(row);

                // إضافة مستمع للأحداث للمبلغ المدخل
                const amountInput = row.querySelector('.workorder-invoice-payment-amount');
                amountInput.addEventListener('input', function () {
                    const maxAmount = parseFloat(this.getAttribute('max'));
                    const currentValue = parseFloat(this.value) || 0;

                    if (currentValue > maxAmount) {
                        this.value = maxAmount;
                        Swal.fire('تحذير', `لا يمكن سداد أكثر من ${maxAmount.toFixed(2)} ج.م لهذه الفاتورة`, 'warning');
                    }

                    PaymentManager.updatePaymentSummary();
                });
            });
        } else {
            // ✅ إضافة رسالة في الجدول نفسه
            const row = document.createElement('tr');
            row.innerHTML = `
            <td colspan="5" class="text-center py-4 text-muted">
                <i class="fas fa-file-invoice fa-2x mb-2"></i>
                <p class="mb-0">لا توجد فواتير مرتبطة بهذه الشغلانة</p>
            </td>
        `;
            tbody.appendChild(row);
        }

        document.getElementById('selectedWorkOrderDetails').style.display = 'block';
        PaymentManager.resetPaymentMethods();
        PaymentManager.updatePaymentSummary();
    },
    resetWorkOrderSearch() {
        document.getElementById('workOrderSearch').value = '';
        document.getElementById('workOrderSearchResults').style.display = 'none';
        document.getElementById('selectedWorkOrderDetails').style.display = 'none';
        document.getElementById('workOrderInvoicesTableBody').innerHTML = '';
        PaymentManager.resetPaymentMethods();
        PaymentManager.updatePaymentSummary();
    },

    toggleSelectAllInvoices(checked) {
        const checkboxes = document.querySelectorAll('.invoice-payment-checkbox');
        const amountInputs = document.querySelectorAll('.invoice-payment-amount');

        checkboxes.forEach((checkbox, index) => {
            if (checkbox.closest('tr').style.display !== 'none') {
                checkbox.checked = checked;
                amountInputs[index].disabled = !checked;

                if (!checked) {
                    amountInputs[index].value = 0;
                }
            }
        });

        PaymentManager.updatePaymentSummary();
    },

    // تعديل دالة addPaymentMethod لجعل النقدي هو الافتراضي
    addPaymentMethod() {

        const container = document.getElementById('paymentMethodsContainer');
        const methodCount = container.children.length;

        // إذا كانت هذه هي أول طريقة دفع، اجعلها نقدي
        let defaultMethod = '2'; // نقدي
        if (methodCount === 0) {
            defaultMethod = '1'; // نقدي (الرقم 1 حسب PaymentMethods)
        }

        const methodElement = document.createElement('div');
        methodElement.className = 'payment-method-item mb-3 border p-3 rounded';
        methodElement.innerHTML = `
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small">طريقة الدفع</label>
                <select class="form-select payment-method-select" data-method-index="${methodCount}" required>
                    <option value="">اختر طريقة...</option>
                    ${PaymentMethods.map(pm =>
            `<option value="${pm.id}" ${pm.id === 1 ? 'selected' : ''}>${pm.name}</option>`
        ).join('')}
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">المبلغ</label>
                <input type="number" class="form-control payment-method-amount" 
                       data-method-index="${methodCount}" step="0.01" value="0" required>
            </div>
            <div class="col-md-4">
                <label class="form-label small">ملاحظات (اختياري)</label>
                <input type="text" class="form-control payment-method-notes" 
                       data-method-index="${methodCount}" placeholder="ملاحظات حول هذه الدفعة...">
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-danger btn-sm remove-payment-method" 
                        data-method-index="${methodCount}" ${methodCount === 0 ? 'disabled' : ''}>
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    `;
        container.appendChild(methodElement);

        // إضافة مستمع حدث لحذف طريقة الدفع
        const removeBtn = methodElement.querySelector('.remove-payment-method');
        removeBtn.addEventListener('click', function () {
            if (container.children.length > 1) {
                methodElement.remove();
                PaymentManager.calculatePaymentMethodsTotal();
                PaymentManager.validatePayment();
            }
        });

        // إضافة مستمع للأحداث
        // في دالة addPaymentMethod، بعد إنشاء العنصر:
        const amountInput = methodElement.querySelector('.payment-method-amount');
        amountInput.addEventListener('input', function () {
            PaymentManager.calculatePaymentMethodsTotal();
            PaymentManager.validatePayment();
        });

        PaymentManager.calculatePaymentMethodsTotal();
        PaymentManager.validatePayment();


    },

    resetPaymentMethods() {
        document.getElementById('paymentMethodsContainer').innerHTML = '';
        PaymentManager.addPaymentMethod();
    },

    updatePaymentSummary() {
        let totalRequired = 0;
        let totalEntered = 0;
        let walletPayment = 0;
        const paymentType = document.querySelector('input[name="paymentType"]:checked').value;

        // حساب المبلغ المطلوب بناءً على نوع السداد
        if (paymentType === 'invoices') {
            // جمع المبالغ المطلوبة من الفواتير المحددة
            document.querySelectorAll('.invoice-payment-checkbox:checked').forEach(checkbox => {
                const invoiceId = parseInt(checkbox.getAttribute('data-invoice-id'));
                const invoice = AppData.invoices.find(i => i.id === invoiceId);
                if (invoice) {
                    totalRequired += invoice.remaining;
                }
            });

            // جمع المبالغ المدخلة
            document.querySelectorAll('.invoice-payment-amount:not(:disabled)').forEach(input => {
                totalEntered += parseFloat(input.value) || 0;
            });
        } else {
            // جمع المبالغ من فواتير الشغلانة
            document.querySelectorAll('.workorder-invoice-payment-amount').forEach(input => {
                const amount = parseFloat(input.value) || 0;
                totalEntered += amount;

                const invoiceId = parseInt(input.getAttribute('data-invoice-id'));
                const invoice = AppData.invoices.find(i => i.id === invoiceId);
                if (invoice) {
                    totalRequired += invoice.remaining;
                }
            });
        }

        // حساب مبلغ المحفظة من طرق الدفع
        walletPayment = 0;
        document.querySelectorAll('.payment-method-item').forEach(item => {
            const methodSelect = item.querySelector('.payment-method-select');
            const amountInput = item.querySelector('.payment-method-amount');

            if (methodSelect.value === '4') { // 4 هو id طريقة دفع المحفظة
                walletPayment += parseFloat(amountInput.value) || 0;
            }
        });

        // تحديث العناصر
        document.getElementById('invoicesTotalAmount').value = totalRequired.toFixed(2);
        document.getElementById('invoicesTotalAmountWorkOrder').value = totalRequired.toFixed(2);
        document.getElementById('totalRequiredAmount').textContent = totalRequired.toFixed(2) + ' ج.م';
        document.getElementById('totalEnteredAmount').textContent = totalEntered.toFixed(2) + ' ج.م';
        document.getElementById('paymentRequiredAmount').value = totalEntered.toFixed(2);

        // عرض/إخفاء تفاصيل المحفظة
        const walletDetails = document.getElementById('walletPaymentDetails');
        if (walletPayment > 0) {
            const availableBalance = parseFloat(AppData.currentCustomer.wallet);

            const remainingBalance = parseFloat(availableBalance) - walletPayment;

            document.getElementById('availableWalletBalance').textContent = availableBalance.toFixed(2) + ' ج.م';
            document.getElementById('walletPaymentAmount').textContent = walletPayment.toFixed(2) + ' ج.م';
            document.getElementById('remainingWalletBalance').textContent = remainingBalance.toFixed(2) + ' ج.م';

            walletDetails.style.display = 'block';

            // التحقق من أن مبلغ المحفظة لا يتجاوز الرصيد المتاح
            if (walletPayment > availableBalance) {
                document.getElementById('paymentError').textContent =
                    'مبلغ المحفظة المطلوب يتجاوز الرصيد المتاح!';
                document.getElementById('paymentError').style.display = 'block';
                document.getElementById('processPaymentBtn').disabled = true;
                return;
            }
        } else {
            walletDetails.style.display = 'none';
        }

        // التحقق من أن المبلغ المدخل لا يتجاوز المبلغ المطلوب
        if (totalEntered > totalRequired) {
            document.getElementById('paymentError').textContent =
                'المبلغ المدخل يتجاوز المبلغ المطلوب!';
            document.getElementById('paymentError').style.display = 'block';
            document.getElementById('processPaymentBtn').disabled = true;
            return;
        }

        // التحقق من أن المبلغ المدخل يساوي المبلغ المطلوب (إذا كان المستخدم يريد السداد الكامل)
        const remainingAfterPayment = totalRequired - totalEntered;
        document.getElementById('totalRemainingAfterPayment').textContent =
            remainingAfterPayment.toFixed(2) + ' ج.م';

        // إخفاء رسالة الخطأ إذا لم توجد أخطاء
        document.getElementById('paymentError').style.display = 'none';

        // تفعيل/تعطيل زر المعالجة
        document.getElementById('processPaymentBtn').disabled = totalEntered <= 0;
    },

    // resetPaymentForm() {
    //     PaymentManager.resetPaymentMethods();
    //     document.getElementById('totalRequiredAmount').textContent = '0.00 ج.م';
    //     document.getElementById('totalEnteredAmount').textContent = '0.00 ج.م';
    //     document.getElementById('walletPaymentDetails').style.display = 'none';
    //     document.getElementById('paymentError').style.display = 'none';
    //     document.getElementById('processPaymentBtn').disabled = true;
    //     document.getElementById('invoiceSearch').value = '';

    // },

    resetPaymentForm() {
        PaymentManager.resetPaymentMethods();
        document.getElementById('paymentMethodsContainer').innerHTML = '';
        this.addPaymentMethod();
        document.getElementById('totalRequiredAmount').textContent = '0.00 ج.م';
        document.getElementById('totalEnteredAmount').textContent = '0.00 ج.م';
        document.getElementById('totalRemainingAfterPayment').textContent = '0.00 ج.م';
        document.getElementById('paymentError').style.display = 'none';
        document.getElementById('processPaymentBtn').disabled = true;
        document.getElementById('invoiceSearch').value = '';
        
    },






    // new
    // دالة تحديث المبلغ المطلوب والمقارنة
    updateAndValidate() {
        const paymentType = document.querySelector('input[name="paymentType"]:checked').value;
        let totalRequired = 0;

        if (paymentType === 'invoices') {
            // حساب المبلغ المطلوب الكلي (مجموع المتبقي للفواتير المحددة)
            document.querySelectorAll('.invoice-payment-checkbox:checked').forEach(checkbox => {
                const invoiceId = parseInt(checkbox.getAttribute('data-invoice-id'));
                const invoice = AppData.invoices.find(i => i.id === invoiceId);
                if (invoice) {
                    totalRequired += invoice.remaining;
                }
            });

            document.getElementById('invoicesTotalAmount').value = totalRequired.toFixed(2);
        } else {
            // حساب المبلغ المطلوب الكلي لفواتير الشغلانة
            document.querySelectorAll('.workorder-invoice-payment-amount').forEach(input => {
                const invoiceId = parseInt(input.getAttribute('data-invoice-id'));
                const invoice = AppData.invoices.find(i => i.id === invoiceId);
                if (invoice) {
                    totalRequired += invoice.remaining;
                }
            });

            document.getElementById('workOrderTotalAmount').value = totalRequired.toFixed(2);
        }

        document.getElementById('paymentRequiredAmount').value = totalRequired.toFixed(2);

        // التحقق من المدفوعات
        this.validatePayment();
    },

    // دالة حساب مجموع طرق الدفع
    calculatePaymentMethodsTotal() {
        let total = 0;
        document.querySelectorAll('.payment-method-amount').forEach(input => {
            total += parseFloat(input.value) || 0;
        });

        document.getElementById('totalPaymentMethodsAmount').value = total.toFixed(2);
        return total;
    },

    // دالة التحقق من المدفوعات
    validatePayment() {
        const totalPayment = this.calculatePaymentMethodsTotal();

        // حساب مجموع المبالغ المدخلة في الفواتير المحددة
        let totalInvoicesAmount = 0;
        const paymentType = document.querySelector('input[name="paymentType"]:checked').value;

        if (paymentType === 'invoices') {
            // الفواتير العامة: نجمع المبالغ من الحقول المفعلة (المرتبطة بالفواتير المحددة)
            document.querySelectorAll('.invoice-payment-amount:not(:disabled)').forEach(input => {
                totalInvoicesAmount += parseFloat(input.value) || 0;
            });
        } else {
            // فواتير الشغلانة: نجمع كل الحقول
            document.querySelectorAll('.workorder-invoice-payment-amount').forEach(input => {
                totalInvoicesAmount += parseFloat(input.value) || 0;
            });
        }

        // إخفاء جميع رسائل التحقق
        document.getElementById('paymentValid').style.display = 'none';
        document.getElementById('paymentInvalid').style.display = 'none';
        document.getElementById('paymentExceeds').style.display = 'none';

        // تفعيل/تعطيل زر السداد
        const processBtn = document.getElementById('processPaymentBtn');

        if (totalPayment === 0) {
            processBtn.disabled = true;
            return;
        }

        // التحقق باستخدام هامش خطأ صغير للنقاط العشرية
        const diff = Math.abs(totalPayment - totalInvoicesAmount);

        if (diff <= 0.01) { // هامش خطأ 0.01
            document.getElementById('paymentValid').style.display = 'block';
            processBtn.disabled = false;
        } else if (totalPayment > totalInvoicesAmount) {
            document.getElementById('paymentExceeds').style.display = 'block';
            processBtn.disabled = true;
        } else {
            document.getElementById('paymentInvalid').style.display = 'block';
            processBtn.disabled = true;
        }

        // تحديث عرض المبالغ في واجهة التحقق
        document.getElementById('totalPaymentMethodsAmount').value = totalPayment.toFixed(2);
        // لا نعرض المبلغ المطلوب الكلي هنا، بل نعرض مجموع الفواتير المحددة
        // يمكننا إضافة عنصر جديد لعرض مجموع الفواتير المحددة إذا أردنا
    },

    // دالة التوزيع التلقائي
    // دالة التوزيع التلقائي المعدلة
    autoDistribute() {
        const paymentType = document.querySelector('input[name="paymentType"]:checked').value;
        const totalPayment = this.calculatePaymentMethodsTotal(); // مجموع طرق الدفع

        if (totalPayment <= 0) {
            Swal.fire('تحذير', 'يرجى إدخال مبلغ في طرق الدفع أولاً', 'warning');
            return;
        }

        if (paymentType === 'invoices') {
            this.autoDistributeToInvoices(totalPayment);
        } else {
            this.autoDistributeToWorkOrder(totalPayment);
        }
    }
    ,

    // توزيع على فواتير الشغلانة (مرن)
//     autoDistributeToWorkOrder(totalPayment) {
//         const invoices = [];

//         document.querySelectorAll('.workorder-invoice-payment-amount').forEach(input => {
//             const invoiceId = parseInt(input.getAttribute('data-invoice-id'));
//             const invoice = AppData.invoices.find(i => i.id === invoiceId);
//             if (invoice) {
//                 invoices.push({
//                     invoice: invoice,
//                     input: input
//                 });
//             }
//         });

//         // ترتيب الفواتير من الأقدم للأحدث
//  invoices.sort((a, b) => {
//             const dateTimeA = new Date(`${a.invoice.date} ${a.invoice.time}`);
//             const dateTimeB = new Date(`${b.invoice.date} ${b.invoice.time}`);
//             return dateTimeA - dateTimeB;
//         });
//         let remainingPayment = totalPayment;

//         // توزيع المبلغ
//         invoices.forEach(item => {
//             if (remainingPayment > 0) {
//                 const amountToPay = Math.min(item.invoice.remaining, remainingPayment);
//                 item.input.value = amountToPay.toFixed(2);
//                 item.input.dispatchEvent(new Event('input'));
//                 remainingPayment -= amountToPay;
//             } else {
//                 item.input.value = 0;
//                 item.input.dispatchEvent(new Event('input'));
//             }
//         });

//         // إذا بقي مبلغ بعد التوزيع
//         if (remainingPayment > 0) {
//             Swal.fire({
//                 icon: 'warning',
//                 title: 'تنبيه',
//                 text: `المبلغ الموجود في طرق الدفع (${totalPayment.toFixed(2)}) أكبر من المطلوب للفواتير المحددة (${(totalPayment - remainingPayment).toFixed(2)}). لم يتم توزيع ${remainingPayment.toFixed(2)} ج.م`,
//                 confirmButtonText: 'حسناً'
//             });
//         }

//         this.validatePayment();
//     },

    // توزيع على الفواتير العامة
    autoDistributeToInvoices(totalPayment) {
        // الحصول على الفواتير المحددة والمرتبطة وغير المرتبطة
        const selectedInvoices = [];
        const checkboxes = document.querySelectorAll('.invoice-payment-checkbox:checked');

        checkboxes.forEach(checkbox => {
            const invoiceId = parseInt(checkbox.getAttribute('data-invoice-id'));
            const invoice = AppData.invoices.find(i => i.id === invoiceId);
            if (invoice) {
                selectedInvoices.push(invoice);
            }
        });

        // ترتيب الفواتير من الأقدم للأحدث
 selectedInvoices.sort((a, b) => {
    ('Sorting invoices:', a, b);
            const dateTimeA = new Date(`${a.date} ${a.time}`);
            const dateTimeB = new Date(`${b.date} ${b.time}`);
            return dateTimeA - dateTimeB;
        });
        let remainingPayment = totalPayment;

        // توزيع المبلغ
        selectedInvoices.forEach(invoice => {
            const amountInput = document.querySelector(`.invoice-payment-amount[data-invoice-id="${invoice.id}"]`);
            if (amountInput && remainingPayment > 0) {
                const amountToPay = Math.min(invoice.remaining, remainingPayment);
                amountInput.value = amountToPay.toFixed(2);
                remainingPayment -= amountToPay;
            }
        });
        // 1. عند عدم تحديد فواتير للدفع
        if (!selectedInvoices || selectedInvoices.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'لم يتم تحديد فواتير',
                text: 'يجب تحديد فواتير على الأقل للدفع',
                confirmButtonText: 'حسناً'
            });
            return;
        }

        const totalRequired = selectedInvoices.reduce((sum, invoice) => {
            (sum, invoice);

            return sum + invoice.remaining;
        }, 0);

        (totalRequired, totalPayment);

        // التحقق من الدفع الزائد
        if (totalPayment > totalRequired) {
            ('jh');

            Swal.fire({
                icon: 'info',
                title: 'مبلغ زائد',
                html: `المبلغ المدفوع: ${totalPayment.toFixed(2)} ج.م<br>
                   المبلغ المطلوب: ${totalRequired.toFixed(2)} ج.م<br>
                   <strong>يجب تعديل المبلغ المدفوع ليكون ${totalRequired.toFixed(2)} ج.م</strong>`,
                confirmButtonText: 'متابعة'
            });
        }

        // التحقق من النتائج
        // if (remainingPayment > 0) {
        //     Swal.fire({
        //         icon: 'info',
        //         title: 'توزيع جزئي',
        //         text: `لم يكف المبلغ لسداد الكل. المبلغ المتبقي: ${remainingPayment.toFixed(2)} ج.م`,
        //         confirmButtonText: 'حسناً'
        //     });
        // }

        this.validatePayment();
    },

    // توزيع على فواتير الشغلانة
    autoDistributeToWorkOrder(totalPayment) {
        const invoices = [];

        document.querySelectorAll('.workorder-invoice-payment-amount').forEach(input => {
            const invoiceId = parseInt(input.getAttribute('data-invoice-id'));
            const invoice = AppData.invoices.find(i => i.id === invoiceId);
            if (invoice) {
                invoices.push({
                    invoice: invoice,
                    input: input
                });
            }
        });

        (invoices);

        // ترتيب الفواتير من الأقدم للأحدث
        invoices.sort((a, b) => {
            const dateTimeA = new Date(`${a.invoice.date} ${a.invoice.time}`);
            const dateTimeB = new Date(`${b.invoice.date} ${b.invoice.time}`);
            return dateTimeA - dateTimeB;
        });

        let remainingPayment = totalPayment;

        // توزيع المبلغ
        invoices.forEach(item => {
            if (remainingPayment > 0) {
                const amountToPay = Math.min(item.invoice.remaining, remainingPayment);
                item.input.value = amountToPay.toFixed(2);
                remainingPayment -= amountToPay;
            } else {
                item.input.value = 0;
            }
        });


        if (remainingPayment > 0) {
            Swal.fire({
                icon: 'info',
                title: 'توزيع جزئي',
                text: `لم يكف المبلغ لسداد الكل. المبلغ المتبقي: ${remainingPayment.toFixed(2)} ج.م`,
                confirmButtonText: 'حسناً'
            });
        }

        this.validatePayment();
    },

    // تحديد كل الفواتير للدفع
    selectAllForPayment() {
        document.querySelectorAll('.invoice-payment-checkbox').forEach(checkbox => {
            checkbox.checked = true;
            this.toggleInvoicePaymentInput(checkbox);
        });
        // thi();

    },

    // تحديد الفواتير غير المرتبطة بشغلانة
    selectNonWorkOrderForPayment() {
        document.querySelectorAll('.invoice-payment-checkbox').forEach(checkbox => {
            const invoiceId = parseInt(checkbox.getAttribute('data-invoice-id'));
            const invoice = AppData.invoices.find(i => i.id === invoiceId);
            checkbox.checked = !invoice.work_order_id;
            this.toggleInvoicePaymentInput(checkbox);
        });
    },

    // تحديد كل فواتير الشغلانة
    selectAllWorkOrderInvoices() {
        document.querySelectorAll('.workorder-invoice-payment-amount').forEach(input => {
            const invoiceId = parseInt(input.getAttribute('data-invoice-id'));
            const invoice = AppData.invoices.find(i => i.id === invoiceId);
            if (invoice) {
                input.value = invoice.remaining.toFixed(2);
                input.dispatchEvent(new Event('input'));
            }
        });
    },

    // تفعيل/تعطيل حقل الإدخال للفاتورة
    toggleInvoicePaymentInput(checkbox) {
        const invoiceId = checkbox.getAttribute('data-invoice-id');
        const amountInput = document.querySelector(`.invoice-payment-amount[data-invoice-id="${invoiceId}"]`);

        if (amountInput) {
            amountInput.disabled = !checkbox.checked;
            if (!checkbox.checked) {
                amountInput.value = 0;
            }
            // عند التغيير في حالة الحقل (تفعيل/تعطيل) نحدث التحقق
            PaymentManager.validatePayment();
        }
    },














    // ⭐ استبدال دالة processPayment الحالية بهذه:


    async processPayment() {
        const paymentType = document.querySelector('input[name="paymentType"]:checked').value;

        debugger
        // جمع بيانات السداد
        const paymentData = this.collectPaymentData(paymentType);

        (paymentData);


            if (!paymentData) {
                return;
            }

            // التحقق النهائي
            if (!this.validateFinalPayment(paymentData)) {
                Swal.fire('تحذير', 'البيانات غير صحيحة', 'warning');
                return;
            }

            try {
                // عرض تحميل
                Swal.fire({
                    title: 'جاري المعالجة...',
                    text: 'برجاء الانتظار',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });





                // ⭐ الإرسال للسيرفر
                const response = await fetch(apis.processPayment, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(paymentData)
                });

                const result = await response.json();

               if (result.success) {
            // أغلق Loading Swal أولاً (لو موجود)
            // try { Swal.close(); } catch (e) { /* ignore */ }

            const paymentModalEl = document.getElementById('paymentModal');
            const paymentModal = bootstrap.Modal.getInstance(paymentModalEl);

            // Helper عرض رسالة النجاح بعد تنظيف الصفحة
            const showSuccess = async () => {
                // أخيراً عرض رسالة النجاح
                Swal.fire({
                    icon: 'success',
                    title: 'تم السداد',
                    html: this.generateSuccessMessage(result.data),
                    confirmButtonText: 'حسناً',
                    allowOutsideClick: true,
                    scrollbarPadding: false
                });

                // تحديث البيانات بعد عرض الرسالة
                try {
                    await this.refreshDataAfterPayment(paymentData.customer_id);
                } catch (e) {
                    console.error('Refresh error:', e);
                }
            };

            // لو فيه مودال: نخفيه ونستنى حدث الإخفاء لننظف ونعرض الرسالة
            if (paymentModalEl && paymentModal) {
                // تأكد من إزالة أي listener قديم ثم أضف listener جديد
                const onHidden = () => {
                    // تنظيف السك롤 والـ backdrops
                    this.fixBodyScroll();

                    // عرض الرسالة بعد 50ms (أمان للـ transition)
                    setTimeout(showSuccess, 50);
                };

                paymentModalEl.addEventListener('hidden.bs.modal', onHidden, { once: true });

                // أخفي المودال (سيطلق حدث hidden.bs.modal)
                paymentModal.hide();

                // Fail-safe: لو لم يحدث الحدث بعد 700ms، ننفذ العرض والتنظيف
                setTimeout(() => {
                    // إذا مازال الـ body عنده modal-open أو مفيش Swal ظاهرة، نفعل fallback
                    if (document.body.classList.contains('modal-open')) {
                        this.fixBodyScroll();
                    }
                    // لو لسه ما فيش Swal مرئية، اعرض الرسالة
                    if (!document.querySelector('.swal2-container')) {
                        showSuccess();
                    }
                }, 700);

            } else {
                // لا يوجد مودال — فقط نظف و اعرض
                this.fixBodyScroll();
                await showSuccess();
            }
        } else {
            Swal.fire('خطأ', result.message || 'فشلت عملية السداد', 'error');
        }


            } catch (error) {
                console.error('Error:', error);
                Swal.fire('خطأ', 'حدث خطأ في الاتصال بالسيرفر', 'error');
            }

    },

    
    async processPayment() {
    const paymentType = document.querySelector('input[name="paymentType"]:checked').value;

    // جمع بيانات السداد
    const paymentData = this.collectPaymentData(paymentType);

    if (!paymentData) {
        return;
    }

    // التحقق النهائي
    if (!this.validateFinalPayment(paymentData)) {
        Swal.fire('تحذير', 'البيانات غير صحيحة', 'warning');
        return;
    }

    try {
        // عرض تحميل
        Swal.fire({
            title: 'جاري المعالجة...',
            text: 'برجاء الانتظار',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // ⭐ الإرسال للسيرفر
        const response = await fetch(apis.processPayment, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(paymentData)
        });

        const result = await response.json();

        if (result.success) {
            const paymentModalEl = document.getElementById('paymentModal');
            const paymentModal = bootstrap.Modal.getInstance(paymentModalEl);

            const showSuccess = async () => {
                Swal.fire({
                    icon: 'success',
                    title: 'تم السداد',
                    html: this.generateSuccessMessage(result.data),
                    confirmButtonText: 'حسناً',
                    allowOutsideClick: true,
                    scrollbarPadding: false
                });

                try {
                    await this.refreshDataAfterPayment(paymentData.customer_id);
                } catch (e) {
                    console.error('Refresh error:', e);
                }
            };

            if (paymentModalEl && paymentModal) {
                const onHidden = () => {
                    this.fixBodyScroll();
                    setTimeout(showSuccess, 50);
                };

                paymentModalEl.addEventListener('hidden.bs.modal', onHidden, { once: true });
                paymentModal.hide();

                setTimeout(() => {
                    if (document.body.classList.contains('modal-open')) {
                        this.fixBodyScroll();
                    }
                    if (!document.querySelector('.swal2-container')) {
                        showSuccess();
                    }
                }, 700);
            } else {
                this.fixBodyScroll();
                await showSuccess();
            }
        } else {
            Swal.fire('خطأ', result.message || 'فشلت عملية السداد', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire('خطأ', 'حدث خطأ في الاتصال بالسيرفر', 'error');
    }
},

collectPaymentData(paymentType) {
    const customer = AppData.currentCustomer;
    if (!customer) {
        Swal.fire('تحذير', 'لم يتم تحديد عميل', 'warning');
        return null;
    }

    // ⭐ جمع طرق الدفع
    const payment_methods = [];
    document.querySelectorAll('.payment-method-item').forEach(item => {
        const methodSelect = item.querySelector('.payment-method-select');
        const amountInput = item.querySelector('.payment-method-amount');
        const notesInput = item.querySelector('.payment-method-notes');

        const methodId = parseInt(methodSelect.value);
        const amount = parseFloat(amountInput.value) || 0;
        const notes = notesInput?.value?.trim() || '';

        if (methodId && amount > 0) {
            const method = PaymentMethods.find(pm => pm.id === methodId);
            if (method) {
                let methodEnglish;
                switch (method.name) {
                    case 'نقدي': methodEnglish = 'cash'; break;
                    case 'فيزا': methodEnglish = 'card'; break;
                    case 'شيك': methodEnglish = 'check'; break;
                    case 'محفظة': methodEnglish = 'wallet'; break;
                    default: methodEnglish = 'cash';
                }

                payment_methods.push({
                    method: methodEnglish,
                    amount: amount,
                    notes: notes
                });
            }
        }
    });

    if (payment_methods.length === 0) {
        Swal.fire('تحذير', 'يرجى إدخال طرق دفع', 'warning');
        return null;
    }

    // ⭐ البيانات الأساسية
    const data = {
        customer_id: customer.id,
        notes: document.getElementById('paymentNotes')?.value || '',
        payment_methods: payment_methods
    };

    // ⭐ حالة الدفع للفواتير العادية
    if (paymentType === 'invoices') {
        return this.collectInvoicesPaymentData(data);
    } 
    // ⭐ حالة الدفع للشغلانة
    else if (paymentType === 'workOrder') {
        return this.collectWorkOrderPaymentData(data);
    }
    
    return null;
},

// ⭐ دالة جديدة: جمع بيانات فواتير الشغلانة
collectWorkOrderPaymentData(baseData) {
    // الحصول على الفواتير من جدول الشغلانة
    const invoices = [];
    let totalAmount = 0;
    let workOrderId = null;

    document.querySelectorAll('.workorder-invoice-payment-amount').forEach(input => {
        const invoiceId = parseInt(input.getAttribute('data-invoice-id'));
        const amount = parseFloat(input.value) || 0;

        if (amount > 0) {
            const invoice = AppData.invoices.find(i => i.id === invoiceId);
            if (invoice) {
                // استخدام work_order_id الخاص بالفاتورة
                if (invoice.work_order_id) {
                    workOrderId = invoice.work_order_id;
                }
                
                invoices.push({
                    id: invoiceId,
                    amount: amount,
                    work_order_id: invoice.work_order_id || null
                });
                totalAmount += amount;
            }
        }
    });

    if (invoices.length === 0) {
        Swal.fire('تحذير', 'لم يتم تحديد مبالغ للدفع', 'warning');
        return null;
    }

    baseData.invoices = invoices;
    baseData.total_amount = totalAmount;
    
    // إضافة work_order_id إذا كان هناك شغلانة واحدة
    if (workOrderId) {
        baseData.work_order_id = workOrderId;
    }

    // تحديد نوع الدفع للشغلانة
    if (invoices.length === 1) {
        const singleInvoice = invoices[0];
        baseData.invoice_id = singleInvoice.id;
        baseData.amount = singleInvoice.amount;
        
        if (baseData.payment_methods.length === 1) {
            baseData.payment_type = 'single';
            baseData.payment_method = baseData.payment_methods[0].method;
        } else {
            baseData.payment_type = 'mixed_single';
        }
    } else {
        baseData.payment_type = 'batch';
        
        // إذا كانت كل الفواتير تابعة لنفس الشغلانة، يمكن إضافة work_order_id
        const uniqueWorkOrderIds = [...new Set(invoices.map(inv => inv.work_order_id))];
        if (uniqueWorkOrderIds.length === 1 && uniqueWorkOrderIds[0]) {
            baseData.work_order_id = uniqueWorkOrderIds[0];
        }
    }

    return baseData;
},

// ⭐ دالة موجودة بالفعل (للحفاظ على الوظيفة السابقة)
collectInvoicesPaymentData(baseData) {
    const checkedInvoices = Array.from(document.querySelectorAll('.invoice-payment-checkbox:checked'));

    if (checkedInvoices.length === 0) {
        Swal.fire('تحذير', 'لم يتم تحديد فواتير للدفع', 'warning');
        return null;
    }

    const invoices = [];
    let totalAmount = 0;

    checkedInvoices.forEach(checkbox => {
        const invoiceId = parseInt(checkbox.getAttribute('data-invoice-id'));
        const amountInput = document.querySelector(`.invoice-payment-amount[data-invoice-id="${invoiceId}"]`);
        const amount = parseFloat(amountInput.value) || 0;

        if (amount > 0) {
            const workOrderId = this.getInvoiceWorkOrderId(invoiceId);
            invoices.push({
                id: invoiceId,
                amount: amount,
                work_order_id: workOrderId
            });
            totalAmount += amount;
        }
    });

    if (invoices.length === 0) {
        Swal.fire('تحذير', 'لم يتم تحديد مبالغ للدفع', 'warning');
        return null;
    }

    baseData.invoices = invoices;
    baseData.total_amount = totalAmount;

    if (invoices.length === 1) {
        const singleInvoice = invoices[0];
        baseData.invoice_id = singleInvoice.id;
        baseData.amount = singleInvoice.amount;
        baseData.work_order_id = singleInvoice.work_order_id;

        if (baseData.payment_methods.length === 1) {
            baseData.payment_type = 'single';
            baseData.payment_method = baseData.payment_methods[0].method;
        } else {
            baseData.payment_type = 'mixed_single';
        }
    } else {
        baseData.payment_type = 'batch';
    }

    return baseData;
},

// ⭐ دالة التحقق النهائي المعدلة
validateFinalPayment(paymentData) {
    const paymentMethods = paymentData.payment_methods || [];
    const invoices = paymentData.invoices || [];

    const totalPayment = paymentMethods.reduce((sum, pm) => sum + (pm.amount || 0), 0);
    const totalInvoices = invoices.reduce((sum, inv) => sum + (inv.amount || 0), 0);

    if (Math.abs(totalPayment - totalInvoices) > 0.01) {
        Swal.fire('خطأ', `المبلغ المدخل (${totalPayment.toFixed(2)}) لا يساوي المطلوب (${totalInvoices.toFixed(2)})`, 'error');
        return false;
    }

    const walletPayment = paymentMethods
        .filter(pm => pm.method === 'wallet')
        .reduce((sum, pm) => sum + (pm.amount || 0), 0);

    if (walletPayment > 0) {
        const walletBalance = AppData.currentCustomer?.wallet || 0;
        if (walletPayment > walletBalance) {
            Swal.fire('خطأ', `رصيد المحفظة غير كافي. المتوفر: ${walletBalance.toFixed(2)}`, 'error');
            return false;
        }
    }

    return true;
},

// collectPaymentData(paymentType) {

//     console.log(paymentType);


//     const customer = AppData.currentCustomer;
//     if (!customer) {
//         Swal.fire('تحذير', 'لم يتم تحديد عميل', 'warning');
//         return null;
//     }

//     // ⭐ جمع طرق الدفع
//     const payment_methods = [];
//     document.querySelectorAll('.payment-method-item').forEach(item => {
//         const methodSelect = item.querySelector('.payment-method-select');
//         const amountInput = item.querySelector('.payment-method-amount');
//         const notesInput = item.querySelector('.payment-method-notes');

//         const methodId = parseInt(methodSelect.value);
//         const amount = parseFloat(amountInput.value) || 0;
//         const notes = notesInput?.value?.trim() || '';

//         if (methodId && amount > 0) {
//             const method = PaymentMethods.find(pm => pm.id === methodId);
//             if (method) {
//                 let methodEnglish;
//                 switch (method.name) {
//                     case 'نقدي': methodEnglish = 'cash'; break;
//                     case 'فيزا': methodEnglish = 'card'; break;
//                     case 'شيك': methodEnglish = 'check'; break;
//                     case 'محفظة': methodEnglish = 'wallet'; break;
//                     default: methodEnglish = 'cash';
//                 }

//                 payment_methods.push({
//                     method: methodEnglish,
//                     amount: amount,
//                     notes: notes
//                 });
//             }
//         }
//     });

//     if (payment_methods.length === 0) {
//         Swal.fire('تحذير', 'يرجى إدخال طرق دفع', 'warning');
//         return null;
//     }

//     // ⭐ البيانات الأساسية
//     const data = {
//         customer_id: customer.id,
//         notes: document.getElementById('paymentNotes')?.value || '',
//         payment_methods: payment_methods
//     };

//     // ⭐ حالة الدفع للفواتير العادية
//     if (paymentType === 'invoices') {
//         const checkedInvoices = Array.from(document.querySelectorAll('.invoice-payment-checkbox:checked'));

//         if (checkedInvoices.length === 0) {
//             Swal.fire('تحذير', 'لم يتم تحديد فواتير للدفع', 'warning');
//             return null;
//         }

//         const invoices = [];
//         let totalAmount = 0;

//         checkedInvoices.forEach(checkbox => {
//             const invoiceId = parseInt(checkbox.getAttribute('data-invoice-id'));
//             const amountInput = document.querySelector(`.invoice-payment-amount[data-invoice-id="${invoiceId}"]`);
//             const amount = parseFloat(amountInput.value) || 0;

//             if (amount > 0) {
//                 const workOrderId = this.getInvoiceWorkOrderId(invoiceId);
//                 invoices.push({
//                     id: invoiceId,
//                     amount: amount,
//                     work_order_id: workOrderId // ⭐ نرسل مع كل فاتورة
//                 });
//                 totalAmount += amount;
//             }
//         });

//         if (invoices.length === 0) {
//             Swal.fire('تحذير', 'لم يتم تحديد مبالغ للدفع', 'warning');
//             return null;
//         }

//         data.invoices = invoices;
//         data.total_amount = totalAmount;

//         // ⭐ تحديد نوع الدفع
//         if (invoices.length === 1) {
//             const singleInvoice = invoices[0];
//             data.invoice_id = singleInvoice.id;
//             data.amount = singleInvoice.amount;
//             data.work_order_id = singleInvoice.work_order_id;

//             if (payment_methods.length === 1) {
//                 data.payment_type = 'single'; // فاتورة واحدة + طريقة واحدة
//                 data.payment_method = payment_methods[0].method;
//             } else {
//                 data.payment_type = 'mixed_single'; // فاتورة واحدة + طرق متعددة
//             }
//         } else {
//             // ⭐ فواتير متعددة
//             data.payment_type = 'batch';
//         }
//     }

//     return data;
// }
// ,

// ⭐ دالة مساعدة للحصول على work_order_id الخاص بالفاتورة
getInvoiceWorkOrderId(invoiceId) {
                const invoiceData = AppData.invoices.find(inv => inv.id === invoiceId);

               const workOrderId = invoiceData ? (invoiceData.work_order_id || null) : null;

    
    return workOrderId;
},
    // ⭐ دالة جديدة: التحقق النهائي
    // validateFinalPayment(paymentData) {
    //     // استخدام مصفوفة فارغة كقيمة افتراضية
    //     const paymentMethods = paymentData.payment_methods || [];
    //     const invoices = paymentData.invoices || [];

    //     // حساب إجمالي طرق الدفع
    //     const totalPayment = paymentMethods.reduce((sum, pm) => sum + (pm.amount || 0), 0);

    //     // حساب إجمالي الفواتير
    //     const totalInvoices = invoices.reduce((sum, inv) => sum + (inv.amount || 0), 0);

    //     // التحقق من التساوي (هامش خطأ 0.01)
    //     if (Math.abs(totalPayment - totalInvoices) > 0.01) {
    //         Swal.fire('خطأ', `المبلغ المدخل (${totalPayment}) لا يساوي المطلوب (${totalInvoices})`, 'error');
    //         return false;
    //     }

    //     // التحقق من رصيد المحفظة إذا كان هناك سحب
    //     const walletPayment = paymentMethods
    //         .filter(pm => pm.method === 'محفظة' || pm.method === 'wallet')
    //         .reduce((sum, pm) => sum + (pm.amount || 0), 0);

    //     if (walletPayment > 0) {
    //         const walletBalance = AppData.currentCustomer?.wallet || 0;
    //         if (walletPayment > walletBalance) {
    //             Swal.fire('خطأ', `رصيد المحفظة غير كافي. المتوفر: ${walletBalance}`, 'error');
    //             return false;
    //         }
    //     }

    //     return true;
    // },


    // ⭐ دالة جديدة: تحديث البيانات بعد السداد
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

  
    generateSuccessMessage(data) {
        // حاول قراءة الإجمالي من أي حقل ممكن
        const total = Number(
            data.total_paid ??
            data.amount_paid ??
            data.amount ??
            data.total_amount ??
            data.mount_paid ?? // in case of old typo
            0
        );

        let message = `
        <div class="text-start">
            <p class="mb-2">✅ تم السداد بنجاح</p>
            <p class="mb-1"><strong>رقم العملية:</strong> ${data.transaction_id || '-'}</p>
            <p class="mb-1"><strong>المبلغ الإجمالي:</strong> ${total.toFixed(2)} ج.م</p>
    `;

        if (typeof data.wallet_deduction !== 'undefined' && Number(data.wallet_deduction) > 0) {
            message += `<p class="mb-1"><strong>المسحوب من المحفظة:</strong> ${Number(data.wallet_deduction).toFixed(2)} ج.م</p>`;
        }

        // عرض تفصيل طرق الدفع لو متاح
        const pmList = data.payment_methods ?? data.payment_methods_summary ?? data.payment_methods_summary ?? null;
        if (Array.isArray(pmList) && pmList.length) {
            const mapAr = { cash: 'نقدي', wallet: 'محفظة', card: 'بطاقة', check: 'شيك', bank_transfer: 'تحويل بنكي', mixed: 'مختلط' };
            const lines = pmList.map(pm => {
                const amount = Number(pm.amount ?? pm.payment_amount ?? 0).toFixed(2);
                const method = pm.method ?? pm.payment_method ?? '';
                const methodAr = mapAr[method] ?? method ?? '';
                return `${amount} ج.م ${methodAr}`;
            });
            message += `<p class="mb-1"><strong>تفصيل طرق الدفع:</strong> ${lines.join(' + ')}</p>`;
        } else if (data.payment_method) {
            const mapAr = { cash: 'نقدي', wallet: 'محفظة', card: 'بطاقة', check: 'شيك', bank_transfer: 'تحويل بنكي', mixed: 'مختلط' };
            const methodAr = mapAr[data.payment_method] ?? data.payment_method;
            message += `<p class="mb-1"><strong>طريقة الدفع:</strong> ${methodAr}</p>`;
        }

        // if (data.invoices_count) {
        //     message += `<p class="mb-1"><strong>عدد الفواتير:</strong> ${data.invoices_count}</p>`;
        // }

        message += '</div>';
        return message;
    },

    fixBodyScroll() {
        // إزالة كلاس modal-open
        document.body.classList.remove('modal-open');
        document.documentElement.style.overflow = '';
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';

        // إزالة أي backdrop متبقي
        document.querySelectorAll('.modal-backdrop, .modal-backdrop.show').forEach(b => b.remove());

        // أزل أي inline style قد يكون سببًا في حجب السكрол
        document.querySelectorAll('html, body').forEach(el => {
            el.style.overflow = '';
        });
    }


};
export default PaymentManager;