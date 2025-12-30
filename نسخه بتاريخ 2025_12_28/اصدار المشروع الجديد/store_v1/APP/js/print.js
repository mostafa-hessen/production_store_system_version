import AppData from "./app_data.js";
import WalletManager from "./wallet.js";
const PrintManager = {
    printSingleInvoice(invoiceId) {
        const invoice = AppData.invoices.find(i => i.id === invoiceId);
        if (!invoice) {
            Swal.fire('خطأ', 'الفاتورة غير موجودة', 'error');
            return;
        }

        // إنشاء محتوى الطباعة على شكل فاتورة صغيرة
        const printContent = this.generateInvoicePrintContent(invoice);

        // فتح نافذة طباعة جديدة
        const printWindow = window.open('', '_blank', 'width=400,height=600');
        if (!printWindow) {
            Swal.fire('تحذير', 'يرجى السماح بالنوافذ المنبثقة للطباعة', 'warning');
            return;
        }

        printWindow.document.write(printContent);
        printWindow.document.close();

        // الانتظار قليلاً ثم الطباعة
        setTimeout(() => {
            printWindow.print();
            printWindow.close();
        }, 500);
    },

    generateInvoicePrintContent(invoice) {
        const customer = AppData.currentCustomer;

        // تنسيق التاريخ
        const date = new Date(invoice.date);
        const options = { year: 'numeric', month: '2-digit', day: '2-digit' };
        const formattedDate = date.toLocaleDateString('ar-SA', options);
        const timeString = invoice.time || '12:00 م';

        // حساب المدفوع والمتبقي
        const paid = invoice.paid || 0;
        const remaining = invoice.remaining || 0;
        const status = invoice.status;

        // إنشاء بنود الفاتورة
        let itemsHTML = '';
        let itemNumber = 1;
        let subtotal = 0;

        invoice.items.forEach((item) => {
            if (!item.fullyReturned) {
                const remainingQuantity = item.quantity - (item.returnedQuantity || 0);
                if (remainingQuantity > 0) {
                    const itemTotal = remainingQuantity * item.price;
                    subtotal += itemTotal;

                    itemsHTML += `
                    <tr>
                        <td style="width: 10%; text-align: center;">${itemNumber}</td>
                        <td style="width: 40%; text-align: right; padding-right: 5px;">${item.productName}</td>
                        <td style="width: 15%; text-align: center;">${remainingQuantity.toFixed(2)}</td>
                        <td style="width: 15%; text-align: left; padding-left: 5px;">${item.price.toFixed(2)}</td>
                        <td style="width: 20%; text-align: left; padding-left: 5px;">${itemTotal.toFixed(2)}</td>
                    </tr>
                `;
                    itemNumber++;
                }
            }
        });

        // بناء HTML كامل للطباعة
        return `
        <!DOCTYPE html>
        <html lang="ar" dir="rtl">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>فاتورة ${invoice.number}</title>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                }
                
                body {
                    padding: 10px;
                    background: white;
                    color: #000;
                    font-size: 12px;
                }
                
                .invoice {
                    width: 280px;
                    margin: 0 auto;
                    padding: 10px;
                    border: 1px solid #000;
                }
                
                .header {
                    text-align: center;
                    padding-bottom: 10px;
                    margin-bottom: 10px;
                    border-bottom: 2px dashed #000;
                }
                
                .store-name {
                    font-weight: 900;
                    font-size: 16px;
                    margin-bottom: 5px;
                    color: #000;
                }
                
                .store-info {
                    font-weight: 700;
                    font-size: 10px;
                    margin-bottom: 2px;
                    color: #555;
                }
                
                .invoice-info {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 10px;
                    font-weight: 700;
                    font-size: 10px;
                }
                
                .customer-info {
                    margin-bottom: 10px;
                    padding: 8px;
                    background: #f8f9fa;
                    border-radius: 4px;
                    font-weight: 700;
                    font-size: 10px;
                }
                
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 10px;
                    font-weight: 700;
                    font-size: 10px;
                }
                
                th, td {
                    padding: 6px 2px;
                    text-align: center;
                    border-bottom: 1px dashed #ddd;
                }
                
                th {
                    background: #f1f8ff;
                    font-weight: 900;
                }
                
                .totals {
                    margin-top: 10px;
                    font-size: 11px;
                }
                
                .total-row {
                    display: flex;
                    justify-content: space-between;
                    padding: 4px 0;
                }
                
                .total-final {
                    border-top: 2px dashed #000;
                    margin-top: 5px;
                    padding-top: 8px;
                    font-weight: 900;
                }
                
                .payment-info {
                    margin: 10px 0;
                    padding: 8px;
                    background: #f8f9fa;
                    border-radius: 4px;
                    font-weight: 700;
                    font-size: 10px;
                }
                
                .payment-details {
                    margin-top: 5px;
                }
                
                .payment-row {
                    display: flex;
                    justify-content: space-between;
                    padding: 2px 0;
                }
                
                .footer {
                    text-align: center;
                    margin-top: 15px;
                    padding-top: 10px;
                    border-top: 2px dashed #000;
                    font-weight: 700;
                    font-size: 9px;
                    color: #555;
                }
                
                .barcode {
                    text-align: center;
                    margin: 10px 0;
                    font-family: monospace;
                    font-size: 16px;
                    letter-spacing: 3px;
                    font-weight: 900;
                }
                
                .status {
                    display: inline-block;
                    padding: 2px 8px;
                    border-radius: 3px;
                    font-size: 10px;
                    font-weight: 700;
                    margin-top: 5px;
                }
                
                .status-pending { background: #fff3cd; color: #856404; }
                .status-partial { background: #d1ecf1; color: #0c5460; }
                .status-paid { background: #d4edda; color: #155724; }
                .status-returned { background: #f8d7da; color: #721c24; }
                
                @media print {
                    body {
                        padding: 0;
                        margin: 0;
                    }
                    
                    .invoice {
                        border: none;
                        width: 100%;
                        max-width: 280px;
                    }
                }
            </style>
        </head>
        <body>
            <div class="invoice">
                <div class="header">
                    <div class="store-name">نظام الفواتير الإلكتروني</div>
                    <div class="store-info">السجل التجاري: 1234567890</div>
                    <div class="store-info">الهاتف: 01234567890</div>
                </div>
                
                <div class="invoice-info">
                    <div>
                        <div>رقم الفاتورة: ${invoice.number}</div>
                        <div>التاريخ: ${formattedDate}</div>
                    </div>
                    <div>
                        <div>الوقت: ${timeString}</div>
                        <div>الكاشير: ${invoice.createdBy || 'مدير النظام'}</div>
                    </div>
                </div>
                
                <div class="customer-info">
                    <div>العميل: ${customer.name}</div>
                    <div>الهاتف: ${customer.phone}</div>
                    <div class="status status-${status}">
                        ${status === 'pending' ? 'مؤجل' :
                status === 'partial' ? 'جزئي' :
                    status === 'paid' ? 'مسلم' : 'مرتجع'}
                    </div>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>المنتج</th>
                            <th>الكمية</th>
                            <th>السعر</th>
                            <th>الإجمالي</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${itemsHTML}
                    </tbody>
                </table>
                
                <div class="totals">
                    <div class="total-row">
                        <span>الإجمالي:</span>
                        <span>${invoice.total.toFixed(2)} ج.م</span>
                    </div>
                    
                    <div class="total-row">
                        <span>المدفوع:</span>
                        <span>${paid.toFixed(2)} ج.م</span>
                    </div>
                    
                    <div class="total-row">
                        <span>المتبقي:</span>
                        <span>${remaining.toFixed(2)} ج.م</span>
                    </div>
                    
                    <div class="total-row total-final">
                        <span>المبلغ الإجمالي:</span>
                        <span>${invoice.total.toFixed(2)} ج.م</span>
                    </div>
                </div>
                

                <div class="barcode">*${invoice.number}*</div>
                
                <div class="footer">
                    <div>شكراً لتعاملكم معنا</div>
                    <div>للاستفسار: 01234567890</div>
                    <div style="margin-top: 5px; font-size: 8px;">${new Date().toLocaleDateString('ar-EG')} ${new Date().toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' })}</div>
                </div>
            </div>
            
            <script>
                // طباعة تلقائية بعد تحميل الصفحة
                window.onload = function() {
                    setTimeout(() => {
                        window.print();
                    }, 300);
                };
            </script>
        </body>
        </html>
    `;
    },
    printStatement(dateFrom, dateTo) {
        const printSection = document.getElementById("printSection");
        const transactions = WalletManager.getStatementTransactions(
            dateFrom,
            dateTo
        );

        let content = `
                    <div class="pos-header">
                        <h2>كشف حساب العميل</h2>
                        <div class="pos-details">
                            <div>العميل: ${AppData.currentCustomer.name}</div>
                            <div>الفترة: ${dateFrom || "البداية"} - ${dateTo || "النهاية"
            }</div>
                            <div>التاريخ: ${new Date().toLocaleDateString(
                "ar-EG"
            )}</div>
                        </div>
                    </div>
                    
                    <table class="pos-items">
                        <thead>
                            <tr>
                                <th>التاريخ</th>
                                <th>الوصف</th>
                                <th>المبلغ</th>
                                <th>الرصيد</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

        let currentBalance = 0;

        transactions.forEach((transaction) => {
            currentBalance = transaction.balanceAfter;
            const amountSign = transaction.amount > 0 ? "+" : "";

            content += `
                        <tr>
                            <td>${transaction.date}</td>
                            <td>${transaction.description}</td>
                            <td>${amountSign}${transaction.amount.toFixed(
                2
            )}</td>
                            <td>${transaction.balanceAfter.toFixed(2)}</td>
                        </tr>
                    `;
        });

        content += `
                        </tbody>
                    </table>
                    
                    <div class="pos-totals">
                        <div class="d-flex justify-content-between">
                            <span>الرصيد النهائي:</span>
                            <span>${currentBalance.toFixed(2)} ج.م</span>
                        </div>
                    </div>
                    
                    <div class="pos-footer">
                        <div>شكراً لتعاملكم معنا</div>
                        <div>للاستفسار: ${AppData.currentCustomer.phone}</div>
                    </div>
                `;

        printSection.innerHTML = content;

        // الطباعة
        window.print();
    },

    openPrintMultipleModal() {
        const container = document.getElementById("printInvoicesList");
        container.innerHTML = "";

        AppData.invoices.forEach((invoice) => {
            const div = document.createElement("div");
            div.className = "form-check";
            div.innerHTML = `
                        <input class="form-check-input print-invoice-checkbox" type="checkbox" value="${invoice.id
                }" id="printInvoice${invoice.id}">
                        <label class="form-check-label" for="printInvoice${invoice.id
                }">
                            ${invoice.number} - ${invoice.date
                } - ${invoice.total.toFixed(2)} ج.م
                        </label>
                    `;
            container.appendChild(div);
        });

        const modal = new bootstrap.Modal(
            document.getElementById("printMultipleModal")
        );
        modal.show();
    },

    printMultipleInvoices() {
        const selectedCheckboxes = document.querySelectorAll(
            ".print-invoice-checkbox:checked"
        );
        if (selectedCheckboxes.length === 0) {
            Swal.fire("تحذير", "يرجى اختيار فواتير للطباعة.", "warning");
            return;
        }

        const invoiceIds = Array.from(selectedCheckboxes).map((checkbox) =>
            parseInt(checkbox.value)
        );

        // إنشاء تقرير مجمع
        const report = {
            invoicesCount: invoiceIds.length,
            items: [],
            totals: {
                beforeDiscount: 0,
                afterDiscount: 0,
                discount: 0,
            },
            invoices: [],
        };

        // تجميع بيانات الفواتير المحددة
        invoiceIds.forEach((invoiceId) => {
            const invoice = AppData.invoices.find((i) => i.id === invoiceId);
            if (invoice) {
                report.invoices.push({
                    id: invoice.id,
                    customer: AppData.currentCustomer.name,
                    total: invoice.total,
                });

                report.totals.beforeDiscount += invoice.total;
                report.totals.afterDiscount += invoice.total;

                // إضافة البنود غير المرتجعة بالكامل
                invoice.items.forEach((item) => {
                    if (!item.fullyReturned) {
                        const remainingQuantity =
                            item.quantity - (item.returnedQuantity || 0);
                        if (remainingQuantity > 0) {
                            // البحث عن المنتج إذا كان موجودًا بالفعل
                            const existingItem = report.items.find(
                                (i) =>
                                    i.name === item.productName && i.price === item.price
                            );
                            if (existingItem) {
                                existingItem.quantity += remainingQuantity;
                                existingItem.total += remainingQuantity * item.price;
                            } else {
                                report.items.push({
                                    name: item.productName,
                                    quantity: remainingQuantity,
                                    price: item.price,
                                    total: remainingQuantity * item.price,
                                });
                            }
                        }
                    }
                });
            }
        });

        // استخدام دالة الطباعة المجمعة
        printAggregatedReport(report);

        // إغلاق المودال
        const modal = bootstrap.Modal.getInstance(
            document.getElementById("printMultipleModal")
        );
        modal.hide();
    },
    printAggregatedReport(report) {
    const printWindow = window.open("", "_blank", "width=300,height=600");
    if (!printWindow) {
        Swal.fire('تحذير', 'يرجى السماح بالنوافذ المنبثقة للطباعة', 'warning');
        return;
    }
    
    // ... إنشاء receiptContent
    printWindow.document.write(receiptContent);
    printWindow.document.close();
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 500);
},
    // في PrintManager:
    printWorkOrderInvoices(workOrderId) {
        const workOrder = AppData.workOrders.find(wo => wo.id === workOrderId);
        if (!workOrder) {
            Swal.fire('خطأ', 'الشغلانة غير موجودة', 'error');
            return;
        }

        const relatedInvoices = AppData.invoices.filter(inv =>
            workOrder.invoices.includes(inv.id)
        );

        if (relatedInvoices.length === 0) {
            Swal.fire('تحذير', 'لا توجد فواتير في هذه الشغلانة', 'warning');
            return;
        }

        // إنشاء محتوى الطباعة المجمع
        const printContent = this.generateWorkOrderPrintContent(workOrder, relatedInvoices);

        // فتح نافذة طباعة جديدة
        const printWindow = window.open('', '_blank', 'width=400,height=600');
        if (!printWindow) {
            Swal.fire('تحذير', 'يرجى السماح بالنوافذ المنبثقة للطباعة', 'warning');
            return;
        }

        printWindow.document.write(printContent);
        printWindow.document.close();

        // الانتظار قليلاً ثم الطباعة
        setTimeout(() => {
            printWindow.print();
            printWindow.close();
        }, 500);
    },

    generateWorkOrderPrintContent(workOrder, invoices) {
        const customer = AppData.currentCustomer;
        const today = new Date().toLocaleDateString('ar-SA');

        // حساب الإجماليات
        const totalInvoices = invoices.reduce((sum, inv) => sum + inv.total, 0);
        const totalPaid = invoices.reduce((sum, inv) => sum + inv.paid, 0);
        const totalRemaining = totalInvoices - totalPaid;

        // إنشاء قائمة الفواتير
        let invoicesHTML = '';
        invoices.forEach((invoice, index) => {
            invoicesHTML += `
            <tr>
                <td style="width: 10%; text-align: center;">${index + 1}</td>
                <td style="width: 20%; text-align: center;">${invoice.number}</td>
                <td style="width: 20%; text-align: center;">${invoice.date}</td>
                <td style="width: 25%; text-align: left; padding-left: 5px;">${invoice.total.toFixed(2)}</td>
                <td style="width: 25%; text-align: left; padding-left: 5px;">${invoice.remaining.toFixed(2)}</td>
            </tr>
        `;
        });

        // بناء HTML كامل
        return `
        <!DOCTYPE html>
        <html lang="ar" dir="rtl">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>تقرير الشغلانة - ${workOrder.name}</title>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                }
                
                body {
                    padding: 10px;
                    background: white;
                    color: #000;
                    font-size: 12px;
                }
                
                .report {
                    width: 280px;
                    margin: 0 auto;
                    padding: 10px;
                    border: 1px solid #000;
                }
                
                .header {
                    text-align: center;
                    padding-bottom: 10px;
                    margin-bottom: 10px;
                    border-bottom: 2px dashed #000;
                }
                
                .report-title {
                    font-weight: 900;
                    font-size: 16px;
                    margin-bottom: 5px;
                    color: #000;
                }
                
                .work-order-info {
                    margin-bottom: 15px;
                    padding: 10px;
                    background: #f8f9fa;
                    border-radius: 4px;
                    font-weight: 700;
                    font-size: 10px;
                }
                
                .work-order-detail {
                    margin-bottom: 5px;
                }
                
                .stats {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 15px;
                }
                
                .stat-card {
                    text-align: center;
                    padding: 10px;
                    background: #f1f8ff;
                    border-radius: 4px;
                    width: 32%;
                }
                
                .stat-value {
                    font-weight: 900;
                    font-size: 14px;
                    margin-bottom: 2px;
                }
                
                .stat-label {
                    font-size: 9px;
                    color: #555;
                }
                
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 15px;
                    font-weight: 700;
                    font-size: 10px;
                }
                
                th, td {
                    padding: 6px 2px;
                    text-align: center;
                    border-bottom: 1px dashed #ddd;
                }
                
                th {
                    background: #f1f8ff;
                    font-weight: 900;
                }
                
                .summary {
                    margin-top: 15px;
                    padding-top: 10px;
                    border-top: 2px dashed #000;
                }
                
                .summary-row {
                    display: flex;
                    justify-content: space-between;
                    padding: 4px 0;
                }
                
                .summary-total {
                    font-weight: 900;
                    border-top: 1px solid #000;
                    padding-top: 8px;
                    margin-top: 8px;
                }
                
                .footer {
                    text-align: center;
                    margin-top: 15px;
                    padding-top: 10px;
                    border-top: 1px dashed #000;
                    font-weight: 700;
                    font-size: 9px;
                    color: #555;
                }
                
                @media print {
                    body {
                        padding: 0;
                        margin: 0;
                    }
                    
                    .report {
                        border: none;
                        width: 100%;
                        max-width: 280px;
                    }
                }
            </style>
        </head>
        <body>
            <div class="report">
                <div class="header">
                    <div class="report-title">تقرير فواتير الشغلانة</div>
                    <div style="font-size: 10px;">تاريخ التقرير: ${today}</div>
                </div>
                
                <div class="work-order-info">
                    <div class="work-order-detail"><strong>اسم الشغلانة:</strong> ${workOrder.name}</div>
                    <div class="work-order-detail"><strong>الوصف:</strong> ${workOrder.description}</div>
                    <div class="work-order-detail"><strong>تاريخ البدء:</strong> ${workOrder.startDate}</div>
                    <div class="work-order-detail"><strong>عدد الفواتير:</strong> ${invoices.length}</div>
                </div>
                
               
                
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>رقم الفاتورة</th>
                            <th>التاريخ</th>
                            <th>الإجمالي</th>
                            <th>المتبقي</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${invoicesHTML}
                    </tbody>
                </table>
                
                <div class="summary">
                    <div class="summary-row">
                        <span>إجمالي قيمة الفواتير:</span>
                        <span>${totalInvoices.toFixed(2)} ج.م</span>
                    </div>
                    <div class="summary-row">
                        <span>إجمالي المدفوع:</span>
                        <span>${totalPaid.toFixed(2)} ج.م</span>
                    </div>
                    <div class="summary-row summary-total">
                        <span>إجمالي المتبقي:</span>
                        <span>${totalRemaining.toFixed(2)} ج.م</span>
                    </div>
                </div>
                
                <div class="footer">
                    <div>تم الطباعة من نظام إدارة العملاء والمخزون</div>
                    <div>التاريخ: ${new Date().toLocaleDateString('ar-EG')}</div>
                    <div>الوقت: ${new Date().toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' })}</div>
                </div>
            </div>
            
            <script>
                window.onload = function() {
                    setTimeout(() => {
                        window.print();
                    }, 300);
                };
            </script>
        </body>
        </html>
    `;
    }

};

export default PrintManager;