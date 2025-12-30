import AppData from "./app_data.js";
import WalletManager from "./transaction.js";
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

    // generateInvoicePrintContent(invoice) {
    //     const customer = AppData.currentCustomer;

    //     // تنسيق التاريخ
    //     const date = new Date(invoice.date);
    //     const options = { year: 'numeric', month: '2-digit', day: '2-digit' };
    //     const formattedDate = date.toLocaleDateString('ar-SA', options);
    //     const timeString = invoice.time || '12:00 م';

    //     // حساب المدفوع والمتبقي
    //     const paid = invoice.paid || 0;
    //     const remaining = invoice.remaining || 0;
    //     const status = invoice.status;

    //     // إنشاء بنود الفاتورة
    //     let itemsHTML = '';
    //     let itemNumber = 1;
    //     let subtotal = 0;

    //     (invoice);
    //     invoice.items.forEach((item) => {

    //         if (!item.fullyReturned) {
    //             const remainingQuantity = item.quantity - (item.returnedQuantity || 0);
    //             if (remainingQuantity > 0) {
    //                 const itemTotal = remainingQuantity * item.selling_price;
    //                 subtotal += itemTotal;

    //                 itemsHTML += `
    //         <tr>
    //             <td style="width:10%; text-align:center;">
    //                 ${item.id}
    //             </td>

    //             <td style="width:40%; text-align:right; padding-right:5px;">
    //                 ${item.product_name}
    //             </td>

    //             <td style="width:15%; text-align:center;">
    //                 ${remainingQuantity.toFixed(2)}
    //             </td>

    //             <td style="width:15%; text-align:left; padding-left:5px;">
    //                 ${item.selling_price.toFixed(2)}
    //             </td>

    //             <td style="width:20%; text-align:left; padding-left:5px;">
    //                 ${itemTotal.toFixed(2)}
    //             </td>
    //         </tr>
    //     `;
    //                 // itemNumber++;
    //             }
    //         }
    //     });

    //     // بناء HTML كامل للطباعة
    //     return `
    //     <!DOCTYPE html>
    //     <html lang="ar" dir="rtl">
    //     <head>
    //         <meta charset="UTF-8">
    //         <meta name="viewport" content="width=device-width, initial-scale=1.0">
    //         <title>فاتورة ${invoice.id}</title>
    //         <style>
    //             * {
    //                 margin: 0;
    //                 padding: 0;
    //                 box-sizing: border-box;
    //                 font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    //             }
                
    //             body {
    //                 padding: 10px;
    //                 background: white;
    //                 color: #000;
    //                 font-size: 12px;
    //             }
                
    //             .invoice {
    //                 width: 280px;
    //                 margin: 0 auto;
    //                 padding: 10px;
    //                 border: 1px solid #000;
    //             }
                
    //             .header {
    //                 text-align: center;
    //                 padding-bottom: 10px;
    //                 margin-bottom: 10px;
    //                 border-bottom: 2px dashed #000;
    //             }
                
    //             .store-name {
    //                 font-weight: 900;
    //                 font-size: 16px;
    //                 margin-bottom: 5px;
    //                 color: #000;
    //             }
                
    //             .store-info {
    //                 font-weight: 700;
    //                 font-size: 10px;
    //                 margin-bottom: 2px;
    //                 color: #555;
    //             }
                
    //             .invoice-info {
    //                 display: flex;
    //                 justify-content: space-between;
    //                 margin-bottom: 10px;
    //                 font-weight: 700;
    //                 font-size: 10px;
    //             }
                
    //             .customer-info {
    //                 margin-bottom: 10px;
    //                 padding: 8px;
    //                 background: #f8f9fa;
    //                 border-radius: 4px;
    //                 font-weight: 700;
    //                 font-size: 10px;
    //             }
                
    //             table {
    //                 width: 100%;
    //                 border-collapse: collapse;
    //                 margin-bottom: 10px;
    //                 font-weight: 700;
    //                 font-size: 10px;
    //             }
                
    //             th, td {
    //                 padding: 6px 2px;
    //                 text-align: center;
    //                 border-bottom: 1px dashed #ddd;
    //             }
                
    //             th {
    //                 background: #f1f8ff;
    //                 font-weight: 900;
    //             }
                
    //             .totals {
    //                 margin-top: 10px;
    //                 font-size: 11px;
    //             }
                
    //             .total-row {
    //                 display: flex;
    //                 justify-content: space-between;
    //                 padding: 4px 0;
    //             }
                
    //             .total-final {
    //                 border-top: 2px dashed #000;
    //                 margin-top: 5px;
    //                 padding-top: 8px;
    //                 font-weight: 900;
    //             }
                
    //             .payment-info {
    //                 margin: 10px 0;
    //                 padding: 8px;
    //                 background: #f8f9fa;
    //                 border-radius: 4px;
    //                 font-weight: 700;
    //                 font-size: 10px;
    //             }
                
    //             .payment-details {
    //                 margin-top: 5px;
    //             }
                
    //             .payment-row {
    //                 display: flex;
    //                 justify-content: space-between;
    //                 padding: 2px 0;
    //             }
                
    //             .footer {
    //                 text-align: center;
    //                 margin-top: 15px;
    //                 padding-top: 10px;
    //                 border-top: 2px dashed #000;
    //                 font-weight: 700;
    //                 font-size: 9px;
    //                 color: #555;
    //             }
                
    //             .barcode {
    //                 text-align: center;
    //                 margin: 10px 0;
    //                 font-family: monospace;
    //                 font-size: 16px;
    //                 letter-spacing: 3px;
    //                 font-weight: 900;
    //             }
                
    //             .status {
    //                 display: inline-block;
    //                 padding: 2px 8px;
    //                 border-radius: 3px;
    //                 font-size: 10px;
    //                 font-weight: 700;
    //                 margin-top: 5px;
    //             }
                
    //             .status-pending { background: #fff3cd; color: #856404; }
    //             .status-partial { background: #d1ecf1; color: #0c5460; }
    //             .status-paid { background: #d4edda; color: #155724; }
    //             .status-returned { background: #f8d7da; color: #721c24; }
                
    //             @media print {
    //                 body {
    //                     padding: 0;
    //                     margin: 0;
    //                 }
                    
    //                 .invoice {
    //                     border: none;
    //                     width: 100%;
    //                     max-width: 280px;
    //                 }
    //             }
    //         </style>
    //     </head>
    //     <body>
    //         <div class="invoice">
    //             <div class="header">
    //                 <div class="store-name">نظام الفواتير الإلكتروني</div>
    //                 <div class="store-info">السجل التجاري: 1234567890</div>
    //                 <div class="store-info">الهاتف: 01096590768</div>
    //             </div>
                
    //             <div class="invoice-info">
    //                 <div>
    //                     <div>رقم الفاتورة: ${invoice.id}</div>
    //                     <div>التاريخ: ${formattedDate}</div>
    //                 </div>
    //                 <div>
    //                     <div>الوقت: ${timeString}</div>
    //                     <div>الكاشير: ${invoice.createdByName || 'مدير النظام'}</div>
    //                 </div>
    //             </div>
                
    //             <div class="customer-info">
    //                 <div>العميل: ${customer.name}</div>
    //                 <div>الهاتف: ${customer.mobile}</div>
    //                 <div class="status status-${status}">
    //                     حالة الفاتورة:
    //                     ${status === 'pending' ? 'مؤجل' :
    //             status === 'partial' ? 'جزئي' :
    //                 status === 'paid' ? 'مسلم' : 'مرتجع'}
    //                 </div>
    //             </div>
                
    //             <table>
    //                 <thead>
    //                     <tr>
    //                         <th>#</th>
    //                         <th>المنتج</th>
    //                         <th>الكمية</th>
    //                         <th>السعر</th>
    //                         <th>الإجمالي</th>
    //                     </tr>
    //                 </thead>
    //                 <tbody>
    //                     ${itemsHTML}
    //                 </tbody>
    //             </table>
                
    //             <div class="totals">
    //                 <div class="total-row">
    //                     <span>الإجمالي:</span>
    //                     <span>${invoice.total.toFixed(2)} ج.م</span>
    //                 </div>
                    
    //                 <div class="total-row">
    //                     <span>المدفوع:</span>
    //                     <span>${paid.toFixed(2)} ج.م</span>
    //                 </div>
                    
    //                 <div class="total-row">
    //                     <span>المتبقي:</span>
    //                     <span>${remaining.toFixed(2)} ج.م</span>
    //                 </div>
                    
    //                 <div class="total-row total-final">
    //                     <span>المبلغ الإجمالي:</span>
    //                     <span>${invoice.remaining.toFixed(2)} ج.م</span>
    //                 </div>
    //             </div>
                

    //             <div class="barcode">*${invoice.id}*</div>
                
    //             <div class="footer">
    //                 <div>شكراً لتعاملكم معنا</div>
    //                 <div>للاستفسار: 01096590768</div>
    //                 <div style="margin-top: 5px; font-size: 8px;">${new Date().toLocaleDateString('ar-EG')} ${new Date().toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' })}</div>
    //             </div>
    //         </div>
            
    //         <script>
    //             // طباعة تلقائية بعد تحميل الصفحة
    //             window.onload = function() {
    //                 setTimeout(() => {
    //                     window.print();
    //                 }, 300);
    //             };
    //         </script>
    //     </body>
    //     </html>
    // `;
    // },
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
    

    
    // حساب بيانات الخصم
    const discountAmount = parseFloat(invoice.discount_amount || 0);
    const discountValue = parseFloat(invoice.discount_value || 0);
    const discountType = invoice.discount_type || 'percent';
    let beforeDiscount =0;
    const afterDiscount = parseFloat(invoice.total_after_discount || invoice.total || 0);

    // إنشاء بنود الفاتورة
    let itemsHTML = '';
    let subtotal = 0;

    invoice.items.forEach((item) => {
        
        if (!item.fullyReturned) {
            const remainingQuantity = (item.available_for_return|| 0);
            if (remainingQuantity > 0) {
                const itemTotal = remainingQuantity * item.selling_price;
                beforeDiscount += itemTotal;

                itemsHTML += `
            <tr>
                <td style="width:10%; text-align:center;">
                    ${item.id}
                </td>

                <td style="width:40%; text-align:right; padding-right:5px;">
                    ${item.product_name}
                </td>

                <td style="width:15%; text-align:center;">
                    ${remainingQuantity.toFixed(2)}
                </td>

                <td style="width:15%; text-align:left; padding-left:5px;">
                    ${item.selling_price.toFixed(2)}
                </td>

                <td style="width:20%; text-align:left; padding-left:5px;">
                    ${itemTotal.toFixed(2)}
                </td>
            </tr>
        `;
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
        <title>فاتورة ${invoice.id}</title>
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
            
            /* تصميم الخصم الجديد */
            .discount-section {
                margin: 10px 0;
                padding: 8px;
                background: #fff3cd;
                border-radius: 4px;
                border: 1px dashed #856404;
            }
            
            .discount-row {
                display: flex;
                justify-content: space-between;
                padding: 3px 0;
            }
            
            .original-price {
                text-decoration: line-through;
                color: #6c757d;
            }
            
            .discount-badge {
                display: inline-block;
                padding: 2px 8px;
                background: #dc3545;
                color: white;
                border-radius: 12px;
                font-size: 10px;
                font-weight: bold;
            }
            
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
                <div class="store-info">الهاتف: 01096590768</div>
            </div>
            
            <div class="invoice-info">
                <div>
                    <div>رقم الفاتورة: ${invoice.id}</div>
                    <div>التاريخ: ${formattedDate}</div>
                </div>
                <div>
                    <div>الوقت: ${timeString}</div>
                    <div>الكاشير: ${invoice.createdByName || 'مدير النظام'}</div>
                </div>
            </div>
            
            <div class="customer-info">
                <div>العميل: ${customer.name}</div>
                <div>الهاتف: ${customer.mobile}</div>
                <div class="status status-${status}">
                    حالة الفاتورة:
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
            
            <!-- قسم الخصم إذا كان موجودًا -->
            ${discountAmount > 0 ? `
            <div class="discount-section">
                <div style="text-align: center; font-weight: 900; margin-bottom: 5px; color: #856404;">
                    <i class="fas fa-tag"></i> تفاصيل الخصم
                </div>
                <div class="discount-row">
                    <span>الإجمالي قبل الخصم:</span>
                    <span class="original-price">${beforeDiscount.toFixed(2)} ج.م</span>
                </div>
                <div class="discount-row">
                    <span>قيمة الخصم:</span>
                    <span class="text-danger">-${discountAmount.toFixed(2)} ج.م</span>
                </div>
              
                <div class="discount-row" style="border-top: 1px dashed #856404; padding-top: 5px;">
                    <span>الإجمالي بعد الخصم:</span>
                    <span class="fw-bold">${afterDiscount.toFixed(2)} ج.م</span>
                </div>
            </div>
            ` : ''}
            
            <div class="totals">
              
                
                <div class="total-row">
                    <span>المدفوع:</span>
                    <span>${paid.toFixed(2)} ج.م</span>
                </div>
                
                <div class="total-row">
                    <span>المتبقي:</span>
                    <span>${remaining.toFixed(2)} ج.م</span>
                </div>
                
                <div class="total-row total-final">
                    <span>صافي المبلغ:</span>
                    <span>${remaining.toFixed(2)} ج.م</span>
                </div>
            </div>
            

            <div class="barcode">*${invoice.id}*</div>
            
            <div class="footer">
                <div>شكراً لتعاملكم معنا</div>
                <div>للاستفسار: 01096590768</div>
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
    const transactions = WalletManager.getStatementTransactions(dateFrom, dateTo);
    
    // افتح نافذة طباعة جديدة
    const printWindow = window.open('', '_blank', 'width=1000,height=700');
    
    // إنشاء HTML كامل
    let html = `
        <!DOCTYPE html>
        <html dir="rtl">
        <head>
            <meta charset="UTF-8">
            <title>كشف حساب العميل - ${AppData.currentCustomer?.name || ""}</title>
            <style>
                body {
                    font-family: 'Arial', 'Tahoma', sans-serif;
                    margin: 20px;
                    direction: rtl;
                    
                }
                @media print {
                    body {
                        margin: 0;
                        padding: 15px;
                    }
                }
                .header {
                    text-align: center;
                    margin-bottom: 30px;
                    border-bottom: 3px double #000;
                    padding-bottom: 15px;
                }
                .header h1 {
                    margin: 0 0 10px 0;
                    color: #333;
                }
                .company-info, .customer-info {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 10px;
                    font-size: 14px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 20px 0;
                    font-size: 12px;
                }
                th {
                    background-color: #f2f2f2;
                    color: #333;
                    font-weight: bold;
                    text-align: center;
                    padding: 10px 8px;
                    border: 1px solid #000;
                    white-space: nowrap;
                }
                td {
                    padding: 8px;
                    border: 1px solid #ddd;
                    text-align: center;
                }
                tr:nth-child(even) {
                    background-color: #f9f9f9;
                }
                .positive {
                    color: #28a745;
                }
                .negative {
                    color: #dc3545;
                }
                .summary {
                    margin-top: 30px;
                    padding: 15px;
                    border: 2px solid #333;
                    background-color: #f8f9fa;
                }
                .summary-row {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 8px;
                    font-size: 14px;
                }
                .footer {
                    text-align: center;
                    margin-top: 40px;
                    padding-top: 20px;
                    border-top: 1px solid #ddd;
                    font-size: 12px;
                    color: #666;
                }
                .signature {
                    margin-top: 30px;
                    text-align: left;
                    padding: 20px 0;
                }
                .signature-line {
                    width: 200px;
                    border-top: 1px solid #000;
                    display: inline-block;
                    margin: 0 20px;
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>كشف حساب العميل</h1>
                <div class="company-info">
                    <div>التاريخ: ${new Date().toLocaleDateString('ar-EG')}</div>
                    <div>الوقت: ${new Date().toLocaleTimeString('ar-EG')}</div>
                </div>
                <div class="customer-info">
                    <div><strong>العميل:</strong> ${AppData.currentCustomer?.name || ""}</div>
                    <div><strong>الهاتف:</strong> ${AppData.currentCustomer?.mobile || AppData.currentCustomer?.mobil || ""}</div>
                    <div><strong>الفترة:</strong> ${dateFrom || "البداية"} - ${dateTo || "النهاية"}</div>
                </div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>تاريخ إنشاء المعاملة</th>
                        <th>تاريخ تسجيل المعاملة</th>
                        <th>نوع الحركة</th>
                        <th>الوصف</th>
                        <th>المبلغ</th>
                        <th>المحفظة قبل</th>
                        <th>المحفظة بعد</th>
                        <th>الديون قبل</th>
                        <th>الديون بعد</th>
                    </tr>
                </thead>
                <tbody>
    `;

    // حساب المجاميع
    let totalIn = 0;
    let totalOut = 0;
    let lastBalance = 0;
    let lastWallet = 0;

    transactions.forEach((transaction, index) => {
        // استخراج البيانات بطرق مختلفة للتأكد من وجودها
        const createDate = transaction.created_at || transaction.transaction_date || transaction.date || "";
        const recordDate = transaction.updated_at || transaction.created_at || "";
        const type = transaction.type_text || transaction.type || "";
        const description = transaction.description || "";
        
        const amount = parseFloat(transaction.amount) || 0;
        const walletBefore = parseFloat(transaction.wallet_before) || 0;
        const walletAfter = parseFloat(transaction.wallet_after) || 0;
        const balanceBefore = parseFloat(transaction.balance_before) || 0;
        const balanceAfter = parseFloat(transaction.balance_after) || 0;
        
       

        const amountClass = amount > 0 ? 'positive' : 'negative';
        const amountSign = amount > 0 ? '+' : '';
        
        html += `
            <tr>
                <td>${this.formatDateForPrint(createDate)}</td>
                <td>${this.formatDateForPrint(recordDate)}</td>
                <td>${type}</td>
                <td>${description}</td>
                <td class="${amountClass}">${amountSign}${amount.toFixed(2)}</td>
                <td>${walletBefore.toFixed(2)}</td>
                <td>${walletAfter.toFixed(2)}</td>
                <td>${balanceBefore.toFixed(2)}</td>
                <td>${balanceAfter.toFixed(2)}</td>
            </tr>
        `;
    });

    html += `
                </tbody>
            </table>
            
        
            
            <div class="footer">
                <div>شكراً لتعاملكم معنا</div>
                <div>هذا الكشف صادر عن نظام إدارة المبيعات</div>
                <div>توقيع: _________________</div>
            </div>
            
            <script>
                // دالة تنسيق التاريخ
                function formatDateForPrint(dateStr) {
                    if (!dateStr) return "";
                    try {
                        const date = new Date(dateStr);
                        return date.toLocaleDateString('ar-EG') + " " + date.toLocaleTimeString('ar-EG').slice(0, 5);
                    } catch {
                        return dateStr;
                    }
                }
                
                // طباعة عند تحميل الصفحة
                window.onload = function() {
                    setTimeout(function() {
                        window.print();
                        // إغلاق النافذة بعد ثانيتين من الطباعة
                        setTimeout(function() {
                            window.close();
                        }, 2000);
                    }, 500);
                }
            </script>
        </body>
        </html>
    `;

    // كتابة HTML واغلاق المستند
    printWindow.document.write(html);
    printWindow.document.close();
},

// أضف هذه الدالة المساعدة خارج printStatement
 formatDateForPrint(dateStr) {
    if (!dateStr) return "";
    try {
        const date = new Date(dateStr);
        const formattedDate = date.toLocaleDateString('ar-EG', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        });
        const formattedTime = date.toLocaleTimeString('ar-EG', {
            hour: '2-digit',
            minute: '2-digit'
        });
        return formattedDate + " " + formattedTime;
    } catch {
        return dateStr;
    }
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

   printMultipleInvoices(invoices=[],workOrder=null) {
    let invoiceIds = invoices;

    if(!workOrder){

        const selectedCheckboxes = document.querySelectorAll(
            ".print-invoice-checkbox:checked"
        );
        if (selectedCheckboxes.length === 0) {
            Swal.fire("تحذير", "يرجى اختيار فواتير للطباعة.", "warning");
            return;
        }
    
         invoiceIds = Array.from(selectedCheckboxes).map((checkbox) =>
            parseInt(checkbox.dataset.invoiceId)
        );
    }

    
    // إنشاء تقرير مجمع
    const report = {
        invoicesCount: invoiceIds.length,
        items: [],
        totals: {
            beforeDiscount: 0,
            afterDiscount: 0,
            discountAmount: 0,
            totalCost: 0,
            profitAmount: 0,
            discountType: 'percent' // الافتراضي
        },
        payments: {
            totalPaid: 0,
            totalRemaining: 0
        },
        invoices: [],
        customerName: AppData.currentCustomer?.name || 'غير محدد',
        workOrder: workOrder?workOrder.name: null
    };

    // تجميع بيانات الفواتير المحددة
    invoiceIds.forEach((inv) => {

        const invoice = workOrder ? inv : AppData.invoices.find((i) => i.id === inv);
        
        if (invoice) {
            // بناء كائن الفاتورة كما في قاعدة البيانات
            report.invoices.push({
                id: invoice.id,
                customer_id: invoice.customer_id,
                delivered: invoice.delivered,
                invoice_group: invoice.invoice_group,
                total_before_discount: invoice.total_before_discount || invoice.total || 0,
                total_after_discount: invoice.total_after_discount || invoice.total || 0,
                discount_amount: invoice.discount_amount || 0,
                discount_type: invoice.discount_type || 'percent',
                discount_value: invoice.discount_value || 0,
                total_cost: invoice.total_cost || 0,
                profit_amount: invoice.profit_amount || 0,
                paid_amount: invoice.paid_amount || invoice.paid || 0,
                remaining_amount: invoice.remaining_amount || invoice.remaining || 0,
                notes: invoice.notes,
                created_at: invoice.created_at || invoice.date,
                customer_name: invoice.customer_name || AppData.currentCustomer?.name
            });

            // جمع الإجماليات
            report.totals.beforeDiscount += invoice.total_before_discount || invoice.total || 0;
            report.totals.afterDiscount += invoice.total_after_discount || invoice.total || 0;
            report.totals.discountAmount += invoice.discount_amount || 0;
            report.totals.totalCost += invoice.total_cost || 0;
            report.totals.profitAmount += invoice.profit_amount || 0;
            
            // جمع المدفوعات والمتبقي
            report.payments.totalPaid += invoice.paid_amount || invoice.paid || 0;
            report.payments.totalRemaining += invoice.remaining_amount || invoice.remaining || 0;

            // إضافة البنود غير المرتجعة بالكامل
            invoice.items.forEach((item) => {
                if (!item.fullyReturned) {
                    const remainingQuantity =
                        item.quantity - (item.returned_quantity || 0);
                    if (remainingQuantity > 0) {
                        // البحث عن المنتج إذا كان موجودًا بالفعل
                        const existingItem = report.items.find(
                            (i) =>
                                i.name === item.product_name && 
                                i.price === item.selling_price
                        );
                        if (existingItem) {
                            existingItem.quantity += remainingQuantity;
                            existingItem.total += remainingQuantity * item.selling_price;
                            existingItem.cost_total += remainingQuantity * (item.cost_price || 0);
                        } else {
                            report.items.push({
                                id: item.id,
                                name: item.product_name,
                                quantity: remainingQuantity,
                                price: item.selling_price,
                                total: remainingQuantity * item.selling_price,
                                cost_price: item.cost_price || 0,
                                cost_total: remainingQuantity * (item.cost_price || 0)
                            });
                        }
                    }
                }
            });
        }
    });

    // استخدام دالة الطباعة المجمعة
    this.printAggregatedReport(report);

    // إغلاق المودال
    // const modal = bootstrap.Modal.getInstance(
    //     document.getElementById("printMultipleModal")
    // );
    // modal.hide();
},
    printAggregatedReport(report) {
        const printWindow = window.open("", "_blank", "width=400,height=600");
        if (!printWindow) {
            Swal.fire('تحذير', 'يرجى السماح بالنوافذ المنبثقة للطباعة', 'warning');
            return;
        }

        // إنشاء محتوى الطباعة المجمع
        const receiptContent = this.generateAggregatedReportContent(report);

        printWindow.document.write(receiptContent);
        printWindow.document.close();

        setTimeout(() => {
            printWindow.print();
            printWindow.close();
        }, 500);
    },

   generateAggregatedReportContent(report) {
    
    
    const today = new Date();
    const formattedDate = today.toLocaleDateString('ar-SA');
    const formattedTime = today.toLocaleTimeString('ar-SA', {
        hour: '2-digit',
        minute: '2-digit'
    });

    // استخدام بيانات المدفوعات من الـ report
    const totalPaid = report.payments.totalPaid || 0;
    const totalRemaining = report.payments.totalRemaining || 0;
    
    // حساب إجمالي تكلفة وربح المنتجات
    const totalCost = report.items.reduce((sum, item) => sum + (item.cost_total || 0), 0);
    const totalSales = report.items.reduce((sum, item) => sum + (item.total || 0), 0);
    const totalProfit = totalSales - totalCost;

    // إنشاء بنود المنتجات
    let itemsHTML = '';
    report.items.forEach((item, index) => {
        itemsHTML += `
            <tr>
                <td style="width:10%; text-align:center;">${index + 1}</td>
                <td style="width:45%; text-align:right; padding-right:5px;">
                    ${item.name}
                </td>
                <td style="width:15%; text-align:center;">${item.quantity.toFixed(2)}</td>
                <td style="width:15%; text-align:center;">${item.price?.toFixed(2)}</td>
                <td style="width:20%; text-align:left; padding-left:5px;">
                    ${item.total.toFixed(2)} 
                </td>
            </tr>
        `;
    });

    // إنشاء قائمة الفواتير المختارة
    let invoicesListHTML = '';
    if (report.invoices && report.invoices.length > 0) {
        report.invoices.forEach((inv, index) => {
            const status = inv.delivered === 'yes' ? 'مسلم' : 
                          inv.delivered === 'partial' ? 'جزئي' : 
                          inv.delivered === 'no' ? 'معلق' :
                          inv.delivered === 'canceled' ? 'ملغى' : 'مرتجع';
            
            invoicesListHTML += `
            <div style="padding: 3px 0; border-bottom: 1px dashed #eee; font-size: 9px;">
                <div style="display: flex; justify-content: space-between;">
                    <span>#${inv.id}</span>
                    <span>${status}</span>
                    <span>${inv.total_after_discount?.toFixed(2) || '0.00'}</span>
                </div>
            </div>
            `;
        });
    }

    return `
    <!DOCTYPE html>
    <html lang="ar" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
          ${report.workOrder ? `<title> فواتير شغلانه ${report.workOrder}</title>`: `<title>تقرير فواتير مجمع</title>`}
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
            
            .report-info {
                display: flex;
                justify-content: space-between;
                margin-bottom: 10px;
                font-weight: 700;
                font-size: 10px;
            }
            
            .stats {
                display: flex;
                justify-content: space-between;
                margin-bottom: 10px;
                padding: 10px;
                background: #f8f9fa;
                border-radius: 4px;
            }
            
            .stat-item {
                text-align: center;
            }
            
            .stat-value {
                font-weight: 900;
                font-size: 14px;
                display: block;
            }
            
            .stat-label {
                font-size: 9px;
                color: #555;
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
            
            .invoices-list {
                margin: 10px 0;
                padding: 8px;
                background: #f0f7ff;
                border-radius: 4px;
                max-height: 120px;
                overflow-y: auto;
            }
            
            .invoices-header {
                font-weight: 900;
                text-align: center;
                margin-bottom: 5px;
                padding-bottom: 3px;
                border-bottom: 1px solid #ccc;
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
            
            .positive { color: #28a745; }
            .negative { color: #dc3545; }
            .neutral { color: #6c757d; }
            
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
          ${report.workOrder ? `<div class="report-title"> فواتير شغلانه ${report.workOrder}</div>`: `                <div class="report-title">تقرير فواتير مجمع</div>
`}

                <div style="font-size: 10px;">نظام الفواتير الإلكتروني</div>
            </div>
            
            <div class="report-info">
                <div>
                    <div>عدد الفواتير: ${report.invoicesCount}</div>
                    <div>التاريخ: ${formattedDate}</div>
                </div>
                <div>
                    <div>الوقت: ${formattedTime}</div>
                    <div>العميل: ${report.customerName}</div>
                </div>
            </div>
            
            <div class="stats">
                <div class="stat-item">
                    <span class="stat-value">${report.invoicesCount}</span>
                    <span class="stat-label">فواتير</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value">${report.items.length}</span>
                    <span class="stat-label">منتج</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value">${report.totals.afterDiscount.toFixed(2)}</span>
                    <span class="stat-label">الإجمالي</span>
                </div>
            </div>
            
          
            
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>المنتج</th>
                        <th>الكمية</th>
                        <th>س. البيع</th>
                        <th>الإجمالي</th>
                    </tr>
                </thead>
                <tbody>
                    ${itemsHTML}
                </tbody>
            </table>
            
            <div class="totals">
                <div class="total-row">
                    <span>إجمالي المبيعات:</span>
                    <span>${totalSales.toFixed(2)} ج.م</span>
                </div>
                
                
                
                <div class="total-row">
                    <span>الخصومات:</span>
                    <span class="negative">- ${report.totals.discountAmount.toFixed(2)} ج.م</span>
                </div>
                <div class="total-row">
                    <span>المطلوب بعد الخصم:</span>
                    <span > ${report.totals.afterDiscount.toFixed(2)} ج.م</span>
                </div>
                
                <!-- قسم المدفوعات والمتبقي -->
                <div class="payment-info">
                    <div style="font-weight: 900; margin-bottom: 5px; text-align: center;">بيانات الدفع</div>
                    <div class="payment-details">
                        <div class="payment-row">
                            <span>المدفوع:</span>
                            <span class="positive">${totalPaid.toFixed(2)} ج.م</span>
                        </div>
                        <div class="payment-row">
                            <span>المتبقي:</span>
                            <span class="negative">${totalRemaining.toFixed(2)} ج.م</span>
                        </div>
                        <div class="payment-row" style="border-top: 1px dashed #ccc; padding-top: 4px;">
                            <span>نسبة السداد:</span>
                            <span style="font-weight: 900;">
                                ${report.totals.afterDiscount > 0 ? 
                                    ((totalPaid / report.totals.afterDiscount) * 100).toFixed(1) : 0}%
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="total-row total-final">
                    <span>صافي المطلوب:</span>
                    <span style="font-weight: 900; font-size: 12px;">
                        ${totalRemaining.toFixed(2)} ج.م
                        
                    </span>
                </div>
            </div>
            
            <div class="footer">
                <div>تمت الطباعة بواسطة النظام الإلكتروني</div>
                <div>${formattedDate} - ${formattedTime}</div>
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
},
    // في PrintManager:
    printWorkOrderInvoices(workOrderId) {
        const workOrder = AppData.workOrders.find(wo => wo.id === workOrderId);
        if (!workOrder) {
            Swal.fire('خطأ', 'الشغلانة غير موجودة', 'error');
            return;
        }

        const relatedInvoices =        AppData.invoices.filter(inv =>{
            console.log(inv.status);
            
            return inv.work_order_id === workOrderId && inv.status !== 'returned';
        });


        

        if (relatedInvoices.length === 0) {
            Swal.fire('تحذير', 'لا توجد فواتير في هذه الشغلانة', 'warning');
            return;
        }

        // إنشاء محتوى الطباعة المجمع
         this.printMultipleInvoices( relatedInvoices , workOrder);

        // فتح نافذة طباعة جديدة
     

        // الانتظار قليلاً ثم الطباعة
    
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
    },




// في PrintManager:
printReturn(returnData) {
    if (!returnData) {
        Swal.fire('خطأ', 'بيانات المرتجع غير متوفرة', 'error');
        return;
    }

    // إنشاء محتوى الطباعة للمرتجع
    const printContent = this.generateReturnPrintContent(returnData);

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

 generateReturnPrintContent(returnData) {
    const returnInfo = returnData.return || {};
    const items = returnData.items || [];
    const today = new Date().toLocaleDateString('ar-SA');

    // تنسيق تاريخ المرتجع
    const date = returnInfo.return_date ? new Date(returnInfo.return_date) : null;
    const formattedDate = date ? date.toLocaleDateString('ar-SA', { year: 'numeric', month: '2-digit', day: '2-digit' }) : 'غير محدد';

    // إنشاء قائمة البنود
    let itemsHTML = '';
    let subtotal = 0;
    items.forEach((item, index) => {
        const quantity = parseFloat(item.quantity) || 0;
        const returnPrice = parseFloat(item.return_price) || 0;
        const itemTotal = quantity * returnPrice;
        subtotal += itemTotal;

        itemsHTML += `
            <tr>
                <td style="width:10%; text-align:center;">${index + 1}</td>
                <td style="width:40%; text-align:right; padding-right:5px;">${item.product_name}</td>
                <td style="width:15%; text-align:center;">${quantity.toFixed(2)}</td>
                <td style="width:15%; text-align:left; padding-left:5px;">${returnPrice.toFixed(2)}</td>
                <td style="width:20%; text-align:left; padding-left:5px;">${itemTotal.toFixed(2)}</td>
            </tr>
        `;
    });

    // حالة المرتجع
    const statusText = returnInfo.status === 'completed' ? 'مكتمل' :
                       returnInfo.status === 'pending' ? 'معلق' :
                       returnInfo.status === 'rejected' ? 'مرفوض' : 'معتمد جزئي';

    // نوع المرتجع
    const returnTypeText = returnInfo.return_type === 'full' ? 'مرتجع كامل' :
                           returnInfo.return_type === 'partial' ? 'مرتجع جزئي' :
                           returnInfo.return_type === 'exchange' ? 'استبدال' : 'مرتجع';

    // إجمالي المرتجع
    const totalAmount = parseFloat(returnInfo.total_amount) || subtotal;

    // بناء HTML كامل للطباعة
    return `
    <!DOCTYPE html>
    <html lang="ar" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <title>مرتجع ${returnInfo.return_id || ''}</title>
        <style>
            * { margin:0; padding:0; box-sizing:border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
            body { padding: 10px; font-size: 12px; background: white; color: #000; }
            .invoice { width: 280px; margin: 0 auto; padding: 10px; border: 1px solid #000; }
            .header { text-align:center; padding-bottom:10px; margin-bottom:10px; border-bottom:2px dashed #000; }
            .store-name { font-weight:900; font-size:16px; margin-bottom:5px; }
            .info-box { margin-bottom:10px; padding:8px; background:#f8f9fa; border-radius:4px; font-weight:700; font-size:10px; }
            table { width:100%; border-collapse:collapse; margin-bottom:10px; font-weight:700; font-size:10px; }
            th, td { padding:6px 2px; border-bottom:1px dashed #ddd; text-align:center; }
            th { background:#f1f8ff; font-weight:900; }
            .totals { font-weight:900; margin-top:5px; text-align:right; }
            .reason-box { background:#fff3cd; padding:5px; margin-top:5px; border:1px dashed #856404; font-size:10px; }
            .footer { text-align:center; margin-top:10px; font-size:9px; color:#555; border-top:2px dashed #000; padding-top:5px; }
            @media print { body { padding:0; margin:0; } .invoice { border:none; width:100%; max-width:280px; } }
        </style>
    </head>
    <body>
        <div class="invoice">
            <div class="header">
                <div class="store-name">نظام الفواتير الإلكتروني</div>
                <div>تاريخ الطباعة: ${today}</div>
                <div>                 فاتورة مرتجع
                </div>
            </div>

            <div class="info-box">
                <div>رقم المرتجع: ${returnInfo.return_id || ''}</div>
                <div>اسم العميل: ${returnInfo.customer_name || 'غير محدد'}</div>
                <div>أنشأ بواسطة: ${returnInfo.created_by_name || 'غير محدد'}</div>
                <div> رقم الفاتورة المرتبطه  : ${returnInfo.invoice_id || 'غير محدد'}</div>
                <div>التاريخ: ${formattedDate}</div>
                <div>نوع المرتجع: ${returnTypeText}</div>
            </div>

            ${returnInfo.reason ? `<div class="reason-box">سبب المرتجع: ${returnInfo.reason}</div>` : ''}

            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>المنتج</th>
                        <th>الكمية</th>
                        <th>سعر المرتجع</th>
                        <th>الإجمالي</th>
                    </tr>
                </thead>
                <tbody>
                    ${itemsHTML}
                </tbody>
            </table>

            <div class="totals">إجمالي المرتجع: ${totalAmount.toFixed(2)} ج.م</div>

            <div class="footer">
                تم الطباعة من نظام إدارة الفواتير
            </div>
        </div>

        <script>
            window.onload = function() {
                setTimeout(() => window.print(), 300);
            };
        </script>
    </body>
    </html>
    `;
}


};

export default PrintManager;