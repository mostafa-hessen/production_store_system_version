// اريد ان ارسل لك باقي الخورزميات لكي تفهم ماهيه التطبيق
// الان فقط اريدك ان تفهم كل التطبيق الخطوه التاليه سارسل لك قاعده البيانات + التعديلات وانت قيم 

//     // app_data.js
// const AppData = {
//     currentUser: "مدير النظام",
//     customers: [],
//     currentCustomer: { name: "عميل افتراضي", walletBalance: 0 },
//     invoices: [],
//     returns: [],
//     workOrders: [],
//      products: [
//           { id: 1, name: "شباك ألوميتال 2×1.5", price: 800, stock: 15 },
//           { id: 2, name: "باب خشب", price: 1200, stock: 8 },
//           { id: 3, name: "مفصلات ستانلس", price: 150, stock: 50 },
//           { id: 4, name: "أقفال أمنية", price: 300, stock: 20 },
//           { id: 5, name: "زجاج عاكس", price: 400, stock: 25 },
//         ],
//     walletTransactions: [],
//     nextReturnId: 2,
//     nextInvoiceId: 126,
//     nextWorkOrderId: 3,
//     nextWalletTransactionId: 6,
//     activeFilters: {},
//     paymentMethods: [
//         { id: 1, name: "نقدي", icon: "fas fa-money-bill-wave" },
//         { id: 2, name: "فيزا", icon: "fas fa-credit-card" },
//         { id: 3, name: "شيك", icon: "fas fa-file-invoice" },
//         { id: 4, name: "محفظة", icon: "fas fa-wallet" },
//         { id: 5, name: "آجل", icon: "fas fa-calendar" } // أضفنا الآجل
//     ]
// };
// export default AppData;

// import AppData from './app_data.js';

//       const CustomerManager = {
//         init() {
//           // بيانات العميل الحالي
//           AppData.currentCustomer = {
//             id: 1,
//             name: "محمد أحمد",
//             phone: "01234567890",
//             address: "القاهرة - المعادي",
//             joinDate: "2024-01-20",
//             walletBalance: 500,
//           };

//           AppData.customers.push(AppData.currentCustomer);
//           this.updateCustomerInfo();
//         },

//         updateCustomerInfo() {
//           const customer = AppData.currentCustomer;
//           document.getElementById("customerName").textContent = customer.name;
//           document.getElementById("customerPhone").textContent = customer.phone;
//           document.getElementById("customerAddress").textContent =
//             customer.address;
//           document.getElementById("customerJoinDate").textContent =
//             customer.joinDate;
//           document.getElementById("walletBalance").textContent =
//             customer.walletBalance.toFixed(2);

//           // تحديث الصورة الرمزية بناءً على الاسم
//           const avatar = document.getElementById("customerAvatar");
//           avatar.textContent = customer.name
//             .split(" ")
//             .map((n) => n[0])
//             .join("")
//             .substring(0, 2);

//           this.updateCustomerBalance();
//         },

//         updateCustomerBalance() {
//           // حساب الرصيد الحالي (إجمالي الفواتير - المسدد - المرتجعات)
//           const totalInvoices = AppData.invoices.reduce((sum, i) => {
//             const invoiceTotal = i.total_before_discount || i.total || 0;
//             return sum + invoiceTotal;
//           }, 0);

//           const totalPaid = AppData.invoices.reduce(
//             (sum, i) => sum + (i.paid || 0),
//             0
//           );
//           const totalReturns = AppData.returns.reduce(
//             (sum, r) => sum + (r.amount || 0),
//             0
//           );

//           const currentBalance = totalInvoices - totalPaid;

//           document.getElementById("currentBalance").textContent =
//             currentBalance.toFixed(2);
//         },
//       };
//         export default CustomerManager;

//   import AppData  from "./app_data.js";
//   import { CustomReturnManager } from "./return.js";
//   import { updateInvoiceStats } from "./helper.js";
//   import CustomerManager from "./customer.js";
//   import PrintManager from "./print.js";
//   import PaymentManager from "./payment.js";

// const InvoiceManager = {
//     init() {
//         // بيانات الفواتير الابتدائية المحدثة
//         AppData.invoices = [
//             {
//                 id: 123,
//                 number: "#123",
//                 date: "2024-01-15",
//                 time: "02:30 م",
//                 total: 800,
//                 paid: 500,
//                 remaining: 300,
//                 status: "pending",
//                 description: "فاتورة مرتبطة بشغلانة #WO-001",
//                 workOrderId: 1,
//                 paymentMethod: "credit",
//                 items: [
//                     {
//                         productId: 1,
//                         productName: "شباك ألوميتال 2×1.5",
//                         quantity: 1,
//                         price: 800,
//                         total: 800,
//                         returnedQuantity: 0,
//                         currentQuantity: 1,
//                         currentTotal: 800,
//                         fullyReturned: false,
//                     },
//                 ],
//                 createdBy: "مدير النظام",
//             },
//             {
//                 id: 125,
//                 number: "#125",
//                 date: "2024-01-15",
//                 time: "02:30 م",
//                 total: 800,
//                 paid: 500,
//                 remaining: 300,
//                 status: "pending",
//                 description: "فاتورة مرتبطة بشغلانة #WO-001",
//                 workOrderId: 1,
//                 paymentMethod: "credit",
//                 items: [
//                     {
//                         productId: 1,
//                         productName: "شباك ألوميتال 2×1.5",
//                         quantity: 1,
//                         price: 800,
//                         total: 800,
//                         returnedQuantity: 0,
//                         currentQuantity: 1,
//                         currentTotal: 800,
//                         fullyReturned: false,
//                     },
//                 ],
//                 createdBy: "مدير النظام",
//             },
//             {
//                 id: 121,
//                 number: "#121",
//                 date: "2024-01-05",
//                 time: "03:45 م",
//                 total: 500,
//                 paid: 200,
//                 remaining: 300,
//                 status: "partial",
//                 description: "فاتورة جزئية",
//                 paymentMethod: "cash",
//                 items: [
//                     {
//                         productId: 3,
//                         productName: "مفصلات ستانلس",
//                         quantity: 2,
//                         price: 150,
//                         total: 300,
//                         returnedQuantity: 0,
//                         currentQuantity: 2,
//                         currentTotal: 300,
//                         fullyReturned: false,
//                     },
//                     {
//                         productId: 5,
//                         productName: "زجاج عاكس",
//                         quantity: 0.5,
//                         price: 400,
//                         total: 200,
//                         returnedQuantity: 0,
//                         currentQuantity: 0.5,
//                         currentTotal: 200,
//                         fullyReturned: false,
//                     },
//                 ],
//                 createdBy: "مدير النظام",
//             },
//         ];

//         this.updateInvoicesTable();
//     },

// updateInvoiceAfterReturn(invoiceId, returnData) {
//     const invoice = this.getInvoiceById(invoiceId);
//     if (!invoice) return false;

//     // 1. تحديث بنود الفاتورة
//     returnData.items.forEach((returnItem) => {
//         const invoiceItem = invoice.items.find(
//             (item) => item.productId === returnItem.productId
//         );
//         if (invoiceItem) {
//             // تحديث الكمية المرتجعة
//             invoiceItem.returnedQuantity = (invoiceItem.returnedQuantity || 0) + returnItem.quantity;
            
//             // حساب الكمية الحالية
//             invoiceItem.currentQuantity = invoiceItem.quantity - invoiceItem.returnedQuantity;
            
//             // حساب الإجمالي الحالي
//             invoiceItem.currentTotal = invoiceItem.currentQuantity * invoiceItem.price;
            
//             // تحديد إذا كان مرتجع كلي
//             if (invoiceItem.returnedQuantity >= invoiceItem.quantity) {
//                 invoiceItem.fullyReturned = true;
//                 invoiceItem.currentQuantity = 0;
//                 invoiceItem.currentTotal = 0;
//             }
//         }
//     });

//     // 2. تحديث القيم المالية باستخدام البيانات المحسوبة مسبقاً
//     invoice.total = returnData.newTotal;
//     invoice.paid = returnData.newPaid;
//     invoice.remaining = returnData.newRemaining;

//     // 3. تحديث حالة الفاتورة
//     if (Math.abs(invoice.remaining) < 0.01) {
//         if (Math.abs(invoice.paid - invoice.total) < 0.01) {
//             invoice.status = "paid";
//         } else {
//             invoice.status = "pending";
//         }
//     } else if (invoice.paid > 0) {
//         invoice.status = "partial";
//     } else {
//         invoice.status = "pending";
//     }

//     // 4. التحقق إذا تم إرجاع جميع البنود
//     const allItemsFullyReturned = invoice.items.every((item) => item.fullyReturned);
//     if (allItemsFullyReturned) {
//         invoice.status = "returned";
//         invoice.paid = 0;
//         invoice.remaining = 0;
//         invoice.total = 0;
//     }

//     // 5. تحديث الواجهة
//     this.updateInvoicesTable();
//     updateInvoiceStats();
//     CustomerManager.updateCustomerBalance();

//     return true;
// },


//     // باقي دوال InvoiceManager كما هي مع تعديلات طفيفة
//     updateInvoicesTable() {
//         const tbody = document.getElementById("invoicesTableBody");
//         tbody.innerHTML = "";

//         // تطبيق الفلاتر
//         let filteredInvoices = this.filterInvoices(AppData.invoices);

//         filteredInvoices.forEach((invoice) => {
//             const row = document.createElement("tr");
//             row.className = `invoice-row ${invoice.status}`;

//             // تحديد حالة الفاتورة
//             let statusBadge = "";
//             if (invoice.status === "pending") {
//                 statusBadge = '<span class="status-badge badge-pending">مؤجل</span>';
//             } else if (invoice.status === "partial") {
//                 statusBadge = '<span class="status-badge badge-partial">جزئي</span>';
//             } else if (invoice.status === "paid") {
//                 statusBadge = '<span class="status-badge badge-paid">مسلم</span>';
//             } else if (invoice.status === "returned") {
//                 statusBadge = '<span class="status-badge badge-returned">مرتجع</span>';
//             }

//             // تحديد لون المبلغ المتبقي
//             let remainingColor = "text-danger";
//             if (invoice.remaining === 0) {
//                 remainingColor = "text-success";
//             } else if (invoice.status === "partial") {
//                 remainingColor = "text-warning";
//             }

//             // عرض البنود مع المرتجعات
//             let itemsTooltip = "";
//             if (invoice.items && invoice.items.length > 0) {
//                 const itemsList = invoice.items
//                     .map((item) => {
//                         const currentQuantity = item.currentQuantity || (item.quantity - (item.returnedQuantity || 0));
//                         const currentTotal = item.currentTotal || (currentQuantity * item.price);
//                         const returnedText = item.returnedQuantity > 0 ? ` (مرتجع: ${item.returnedQuantity})` : "";
                        
//                         return `
//                             <div class="tooltip-item">
//                                 <div>
//                                     <div class="tooltip-item-name">${item.productName || "منتج"}</div>
//                                     <div class="tooltip-item-details">
//                                         الكمية: ${currentQuantity} من ${item.quantity}${returnedText}<br>
//                                         السعر: ${item.price.toFixed(2)} ج.م
//                                     </div>
//                                 </div>
//                                 <div class="fw-bold">${currentTotal.toFixed(2)} ج.م</div>
//                             </div>
//                         `;
//                     })
//                     .join("");

//                 itemsTooltip = `
//                     <div class="invoice-items-tooltip">
//                         <div class="tooltip-header">بنود الفاتورة ${invoice.number}</div>
//                         ${itemsList}
//                         <div class="tooltip-total">
//                             <span>الإجمالي الحالي:</span>
//                             <span>${invoice.total.toFixed(2)} ج.م</span>
//                         </div>
//                     </div>
//                 `;
//             }

//             // إنشاء صف الفاتورة
//             row.setAttribute("data-invoice-id", invoice.id);
//             row.innerHTML = `
//                 <td>
//                     <input type="checkbox" class="form-check-input invoice-checkbox" data-invoice-id="${invoice.id}">
//                 </td>
//                 <td>
//                     <strong>${invoice.number}</strong>
                    
//                 </td>
//                 <td>${invoice.date}<br><small>${invoice.time}</small></td>
//                 <td class="invoice-item-hover position-relative">
//                     ${invoice.items.length} بند
//                     ${invoice.items.some(i => i.returnedQuantity > 0) ? 
//                         '<br><small class="text-warning">(يوجد مرتجعات)</small>' : 
//                         '<br><small class="text-muted">(مرر للعرض)</small>'}
//                     ${itemsTooltip}
//                 </td>
//                 <td>${invoice.total.toFixed(2)} ج.م</td>
//                 <td>${invoice.paid.toFixed(2)} ج.م</td>
//                 <td>
//                     <span class="${remainingColor} fw-bold">${invoice.remaining.toFixed(2)} ج.م</span>
//                 </td>
//                 <td>${statusBadge}</td>
//                 <td>
//                     <div class="action-buttons">
//                         <button class="btn btn-sm btn-outline-info view-invoice" data-invoice-id="${invoice.id}">
//                             <i class="fas fa-eye"></i>
//                         </button>
//                         ${invoice.status !== "paid" && invoice.status !== "returned" ? `
//                         <button class="btn btn-sm btn-outline-success pay-invoice" data-invoice-id="${invoice.id}">
//                             <i class="fas fa-money-bill-wave"></i>
//                         </button>
//                         ` : ""}
//                         ${invoice.status !== "returned" ? `
//                         <button class="btn btn-sm btn-outline-warning custom-return-invoice" data-invoice-id="${invoice.id}">
//                             <i class="fas fa-undo"></i>
//                         </button>
//                         ` : ""}
//                         <button class="btn btn-sm btn-outline-secondary print-invoice" data-invoice-id="${invoice.id}">
//                             <i class="fas fa-print"></i>
//                         </button>
//                     </div>
//                 </td>
//             `;

//             tbody.appendChild(row);
//         });

//         this.attachInvoiceEventListeners();
//     },

//     getInvoiceById(invoiceId) {
//         return AppData.invoices.find((inv) => inv.id === invoiceId);
//     },

//     getInvoiceStatusText(status) {
//         const statusMap = {
//             pending: "مؤجل",
//             partial: "جزئي",
//             paid: "مسلم",
//             returned: "مرتجع",
//         };
//         return statusMap[status] || status;
//     },

//       filterInvoices(invoices) {
//           let filtered = [...invoices];

//           if (AppData.activeFilters.dateFrom) {
//             filtered = filtered.filter(
//               (inv) => inv.date >= AppData.activeFilters.dateFrom
//             );
//           }

//           if (AppData.activeFilters.dateTo) {
//             filtered = filtered.filter(
//               (inv) => inv.date <= AppData.activeFilters.dateTo
//             );
//           }

//           if (AppData.activeFilters.invoiceType) {
//             filtered = filtered.filter(
//               (inv) => inv.status === AppData.activeFilters.invoiceType
//             );
//           }

//           // فلتر حسب رقم الفاتورة (للعرض من نتائج البحث)
//           if (AppData.activeFilters.invoiceId) {
//             filtered = filtered.filter(
//               (inv) => inv.id === AppData.activeFilters.invoiceId
//             );
//           }

//           if (AppData.activeFilters.productSearch) {
//             const searchTerm =
//               AppData.activeFilters.productSearch.toLowerCase();
//             filtered = filtered.filter((invoice) =>
//               invoice.items.some((item) =>
//                 item.productName.toLowerCase().includes(searchTerm)
//               )
//             );
//           }

//           return filtered;
//         },

//         attachInvoiceEventListeners() {
//           // زر عرض الفاتورة
//           document.querySelectorAll(".view-invoice").forEach((btn) => {
//             btn.addEventListener("click", function () {
//               const invoiceId = parseInt(this.getAttribute("data-invoice-id"));
//               InvoiceManager.showInvoiceDetails(invoiceId);
//             });
//           });

//           // زر سداد الفاتورة
//           // زر سداد الفاتورة
//           document.querySelectorAll(".pay-invoice").forEach((btn) => {
//             btn.addEventListener("click", function () {
//               const invoiceId = parseInt(this.getAttribute("data-invoice-id"));
//               PaymentManager.openSingleInvoicePayment(invoiceId);
//             });
//           });

//           // زر إرجاع الفاتورة المخصص
//           document.querySelectorAll(".custom-return-invoice").forEach((btn) => {
//             btn.addEventListener("click", function () {
//               const invoiceId = parseInt(this.getAttribute("data-invoice-id"));
//               CustomReturnManager.openReturnModal(invoiceId);
//             });
//           });

//           // زر طباعة الفاتورة
//           document.querySelectorAll(".print-invoice").forEach((btn) => {
//             btn.addEventListener("click", function () {
//               const invoiceId = parseInt(this.getAttribute("data-invoice-id"));
//               PrintManager.printSingleInvoice(invoiceId);
//             });
//           });
//         },

      
//         showInvoiceDetails(invoiceId) {
//     const invoice = AppData.invoices.find((i) => i.id === invoiceId);
//     if (invoice) {
//         document.getElementById("invoiceItemsNumber").textContent = invoice.number;
//         document.getElementById("invoiceItemsDate").textContent = invoice.date + " - " + invoice.time;
//         document.getElementById("invoiceItemsStatus").textContent = this.getInvoiceStatusText(invoice.status);
//         document.getElementById("invoiceItemsTotal").textContent = invoice.total.toFixed(2) + " ج.م";
//         document.getElementById("invoiceItemsPaid").textContent = invoice.paid.toFixed(2) + " ج.م";
//         document.getElementById("invoiceItemsRemaining").textContent = invoice.remaining.toFixed(2) + " ج.م";
//         document.getElementById("invoiceItemsNotes").textContent = invoice.description || "لا يوجد";

//         // عرض اسم الشغلانة إذا كانت مرتبطة
//         let workOrderName = "لا يوجد";
//         if (invoice.workOrderId) {
//             const workOrder = AppData.workOrders.find((wo) => wo.id === invoice.workOrderId);
//             if (workOrder) {
//                 workOrderName = workOrder.name;
//             }
//         }
//         document.getElementById("invoiceItemsWorkOrder").textContent = workOrderName;

//         // التحقق من وجود مرتجعات
//         const hasReturns = AppData.returns.some((r) => r.invoiceId === invoiceId);
//         if (hasReturns) {
//             document.getElementById("invoiceReturnsSection").style.display = "block";
//             document.getElementById("viewInvoiceReturns").addEventListener("click", function (e) {
//                 e.preventDefault();
//                 CustomReturnManager.showInvoiceReturns(invoiceId);
//             });
//         } else {
//             document.getElementById("invoiceReturnsSection").style.display = "none";
//         }

//         const tbody = document.getElementById("invoiceItemsDetails");
//         tbody.innerHTML = "";

//         invoice.items.forEach((item) => {
//             const row = document.createElement("tr");
            
//             // حساب الكمية الحالية والإجمالي الحالي
//             const currentQuantity = item.currentQuantity || (item.quantity - (item.returnedQuantity || 0));
//             const currentTotal = item.currentTotal || (currentQuantity * item.price);
//             const originalTotal = item.quantity * item.price;
            
//             // عرض تاريخ آخر مرتجع
//             let lastReturnDate = "";
//             if (item.returnedQuantity > 0) {
//                 const lastReturn = AppData.returns
//                     .filter(r => r.invoiceId === invoiceId)
//                     .map(r => r.items.find(i => i.productId === item.productId))
//                     .filter(i => i)
//                     .sort((a, b) => new Date(b.date) - new Date(a.date))[0];
                
//                 if (lastReturn) {
//                     lastReturnDate = lastReturn.date || "";
//                 }
//             }

//             let itemStatus = "سليم";
//             let rowClass = "";
//             if (item.fullyReturned) {
//                 itemStatus = '<span class="badge bg-danger">مرتجع كلي</span>';
//                 rowClass = "fully-returned";
//             } else if (item.returnedQuantity > 0) {
//                 itemStatus = '<span class="badge bg-warning">مرتجع جزئي</span>';
//                 rowClass = "partially-returned";
//             }

//             row.className = rowClass;
//             row.innerHTML = `
//                 <td>
//                     <strong>${item.productName}</strong>
//                     ${item.returnedQuantity > 0 ? 
//                         `<div class="mt-1">
//                             <span class="badge bg-warning return-history-badge">
//                                 مرتجع: ${item.returnedQuantity}
//                                 ${lastReturnDate ? ` (${lastReturnDate})` : ''}
//                             </span>
//                         </div>` : 
//                         ''}
//                 </td>
//                 <td>
//                     <div class="d-flex flex-column">
//                         <span class="text-muted small">أصلي: ${item.quantity}</span>
//                         <span class="fw-bold mt-1">حالي: ${currentQuantity}</span>
//                     </div>
//                 </td>
//                 <td>${item.price.toFixed(2)} ج.م</td>
//                 <td>
//                     <div class="d-flex flex-column">
//                         <span class="text-muted small" style="text-decoration: line-through;">${originalTotal.toFixed(2)} ج.م</span>
//                         <span class="fw-bold mt-1">${currentTotal.toFixed(2)} ج.م</span>
//                     </div>
//                 </td>
//                 <td>${item.returnedQuantity || 0}</td>
//                 <td>${itemStatus}</td>
//             `;
//             tbody.appendChild(row);
//         });

//         const modal = new bootstrap.Modal(document.getElementById("invoiceItemsModal"));
//         modal.show();
//     }
// },
     


        
//         selectAllInvoices() {
//           document.querySelectorAll(".invoice-checkbox").forEach((checkbox) => {
//             checkbox.checked = true;
//           });
//           this.updateSelectedCount();
//         },

//         selectNonWorkOrderInvoices() {
//           document.querySelectorAll(".invoice-checkbox").forEach((checkbox) => {
//             const invoiceId = parseInt(
//               checkbox.getAttribute("data-invoice-id")
//             );
//             const invoice = this.getInvoiceById(invoiceId);
//             checkbox.checked = !invoice.workOrderId;
//           });
//           this.updateSelectedCount();
//         },

//         updateSelectedCount() {
//           const selectedCount = document.querySelectorAll(
//             ".invoice-checkbox:checked"
//           ).length;
//           const printBtn = document.getElementById("printSelectedInvoices");
//           printBtn.disabled = selectedCount === 0;
//           printBtn.innerHTML = `<i class="fas fa-print me-2"></i>طباعة (${selectedCount})`;
//         },
// };

// export default InvoiceManager;

// import  AppData  from './app_data.js';
// import WalletManager from './wallet.js';
// const PaymentMethods = [
//         { id: 1, name: "نقدي", icon: "fas fa-money-bill-wave" },
//         { id: 2, name: "فيزا", icon: "fas fa-credit-card" },
//         { id: 3, name: "شيك", icon: "fas fa-file-invoice" },
//         { id: 4, name: "محفظة", icon: "fas fa-wallet" },
//       ];
// const PaymentManager = {
//     init() {
//         this.setupPaymentEventListeners();
//     },
//     setupPaymentEventListeners() {
//         // تغيير نوع السداد
//         document.querySelectorAll('input[name="paymentType"]').forEach(radio => {
//             radio.addEventListener('change', function () {
//                 const paymentType = this.value;

//                 // إظهار/إخفاء الأقسام
//                 document.getElementById('invoicesPaymentSection').style.display =
//                     paymentType === 'invoices' ? 'block' : 'none';
//                 document.getElementById('workOrderPaymentSection').style.display =
//                     paymentType === 'workOrder' ? 'block' : 'none';

//                 // إعادة تعيين الحقول
//                 PaymentManager.resetPaymentForm();

//                 // تحميل البيانات حسب النوع
//                 if (paymentType === 'invoices') {
//                     PaymentManager.loadInvoicesForPayment();
//                 } else {
//                     PaymentManager.resetWorkOrderSearch();
//                 }
//             });
//         });

//         // بحث في الفواتير
//         document.getElementById('invoiceSearch').addEventListener('input', function (e) {
//             PaymentManager.filterInvoicesForPayment(e.target.value);
//         });

//         // بحث في الشغلانات
//         document.getElementById('workOrderSearch').addEventListener('input', function (e) {
//             PaymentManager.searchWorkOrders(e.target.value);
//         });

//         // تحديد/إلغاء تحديد جميع الفواتير
//         document.getElementById('selectAllInvoicesForPayment').addEventListener('change', function () {
//             PaymentManager.toggleSelectAllInvoices(this.checked);
//         });

//         // إضافة طريقة دفع
//         document.getElementById('addPaymentMethodBtn').addEventListener('click', function () {
//             PaymentManager.addPaymentMethod();
//         });

//         // معالجة السداد
//         document.getElementById('processPaymentBtn').addEventListener('click', function () {
//             PaymentManager.processPayment();
//         });

//         // تحديث المبالغ عند الإدخال
//         document.addEventListener('input', function (e) {
//             if (e.target.classList.contains('invoice-payment-amount') ||
//                 e.target.classList.contains('workorder-invoice-payment-amount') ||
//                 e.target.classList.contains('payment-method-amount')) {
//                 PaymentManager.updatePaymentSummary();
//             }
//         });

//         // تغيير طريقة الدفع
//         document.addEventListener('change', function (e) {
//             if (e.target.classList.contains('payment-method-select')) {
//                 PaymentManager.updatePaymentSummary();
//             }
//         });
//         // في setupPaymentEventListeners()
// // أزرار التحديد في قسم الفواتير
// document.getElementById('selectAllInvoicesBtn').addEventListener('click', function() {
//     PaymentManager.loadInvoicesForPayment();
//     PaymentManager.selectAllForPayment();
// });

// document.getElementById('selectNonWorkOrderBtn').addEventListener('click', function() {
//     PaymentManager.selectNonWorkOrderForPayment();
// });

// // زر التحديد في قسم الشغلانات
// // document.getElementById('selectAllWorkOrderInvoicesBtn').addEventListener('click', function() {
// //     PaymentManager.selectAllWorkOrderInvoices();
// // });

// // زر التوزيع التلقائي
// document.getElementById('autoDistributeBtn').addEventListener('click', function() {
//     PaymentManager.autoDistribute();
//     PaymentManager.updatePaymentSummary();
// });

// // تحديث التحقق عند أي تغيير
// document.addEventListener('input', function(e) {
//     if (e.target.classList.contains('payment-method-amount') ||
//         e.target.classList.contains('invoice-payment-amount') ||
//         e.target.classList.contains('workorder-invoice-payment-amount')) {
//         PaymentManager.validatePayment();
//     }
// });

// // تحديث المبلغ المطلوب عند تحديد/إلغاء الفواتير
// document.addEventListener('change', function(e) {
//     if (e.target.classList.contains('invoice-payment-checkbox')) {
//         this.updateRequiredAmountAndValidate();
//         this.toggleInvoicePaymentInput(e.target);
//     }
// });
// // تحديث التحقق عند أي تغيير في طرق الدفع
// document.addEventListener('input', function(e) {
//     if (e.target.classList.contains('payment-method-amount')) {
//         PaymentManager.calculatePaymentMethodsTotal();
//         PaymentManager.validatePayment();
//     }
// });

// // تحديث التحقق عند أي تغيير في مبالغ الفواتير
// document.addEventListener('input', function(e) {
//     if (e.target.classList.contains('invoice-payment-amount') ||
//         e.target.classList.contains('workorder-invoice-payment-amount')) {
//         PaymentManager.validatePayment();
//     }
// });
//     },

//     updateManualPaymentTable() {
//         const tbody = document.getElementById("manualPaymentTableBody");
//         tbody.innerHTML = "";

//         const unpaidInvoices = AppData.invoices.filter(
//             (i) => i.remaining > 0
//         );

//         unpaidInvoices.forEach((invoice) => {
//             const row = document.createElement("tr");
//             row.className = "invoice-item-hover";

//             // إنشاء tooltip للبنود
//             let itemsTooltip = "";
//             if (invoice.items && invoice.items.length > 0) {
//                 const itemsList = invoice.items
//                     .map((item) => {
//                         const itemTotal = (item.quantity || 0) * (item.price || 0);
//                         return `
//                                 <div class="tooltip-item">
//                                     <div>
//                                         <div class="tooltip-item-name">${item.productName || "منتج"
//                             }</div>
//                                         <div class="tooltip-item-details">الكمية: ${item.quantity || 0
//                             } | السعر: ${(item.price || 0).toFixed(
//                                 2
//                             )} ج.م</div>
//                                     </div>
//                                     <div class="fw-bold">${itemTotal.toFixed(
//                                 2
//                             )} ج.م</div>
//                                 </div>
//                             `;
//                     })
//                     .join("");

//                 itemsTooltip = `
//                             <div class="invoice-items-tooltip">
//                                 <div class="tooltip-header">بنود الفاتورة ${invoice.number
//                     }</div>
//                                 ${itemsList}
//                                 <div class="tooltip-total">
//                                     <span>الإجمالي:</span>
//                                     <span>${invoice.total.toFixed(2)} ج.م</span>
//                                 </div>
//                             </div>
//                         `;
//             }

//             row.innerHTML = `
//                         <td class="position-relative">
//                             ${invoice.number}
//                             ${itemsTooltip}
//                         </td>
//                         <td>${invoice.date}</td>
//                         <td>${invoice.total.toFixed(2)} ج.م</td>
//                         <td>${invoice.remaining.toFixed(2)} ج.م</td>
//                         <td>
//                             <input type="number" class="form-control form-control-sm manual-payment-amount" 
//                                    data-invoice-id="${invoice.id}" 
//                                    min="0" max="${invoice.remaining}" 
//                                    value="0" step="0.01">
//                         </td>
//                     `;
//             tbody.appendChild(row);
//         });

//         this.resetPaymentMethods(
//             "manualPaymentMethods",
//             "manualPaymentTotal"
//         );
//         this.updateManualPaymentTotal();
//     },

//     updateWorkOrderPaymentSelect() {
//         const select = document.getElementById("workOrderPaymentSelect");
//         select.innerHTML = '<option value="">اختر الشغلانة</option>';

//         AppData.workOrders.forEach((workOrder) => {
//             const option = document.createElement("option");
//             option.value = workOrder.id;
//             option.textContent = workOrder.name;
//             select.appendChild(option);
//         });

//         document.getElementById("workOrderInvoicesSection").style.display =
//             "none";
//     },

//     updateWorkOrderInvoices(workOrderId) {
//         const invoices = WorkOrderManager.getWorkOrderInvoices(workOrderId);
//         const tbody = document.getElementById("workOrderInvoicesTableBody");
//         tbody.innerHTML = "";

//         let hasUnpaidInvoices = false;

//         invoices.forEach((invoice) => {
//             if (invoice.remaining > 0) {
//                 hasUnpaidInvoices = true;
//                 const row = document.createElement("tr");
//                 row.className = "invoice-item-hover";

//                 // إنشاء tooltip للبنود
//                 let itemsTooltip = "";
//                 if (invoice.items && invoice.items.length > 0) {
//                     const itemsList = invoice.items
//                         .map((item) => {
//                             const itemTotal = (item.quantity || 0) * (item.price || 0);
//                             return `
//                                     <div class="tooltip-item">
//                                         <div>
//                                             <div class="tooltip-item-name">${item.productName || "منتج"
//                                 }</div>
//                                             <div class="tooltip-item-details">الكمية: ${item.quantity || 0
//                                 } | السعر: ${(
//                                     item.price || 0
//                                 ).toFixed(2)} ج.م</div>
//                                         </div>
//                                         <div class="fw-bold">${itemTotal.toFixed(
//                                     2
//                                 )} ج.م</div>
//                                     </div>
//                                 `;
//                         })
//                         .join("");

//                     itemsTooltip = `
//                                 <div class="invoice-items-tooltip">
//                                     <div class="tooltip-header">بنود الفاتورة ${invoice.number
//                         }</div>
//                                     ${itemsList}
//                                     <div class="tooltip-total">
//                                         <span>الإجمالي:</span>
//                                         <span>${invoice.total.toFixed(
//                             2
//                         )} ج.م</span>
//                                     </div>
//                                 </div>
//                             `;
//                 }

//                 row.innerHTML = `
//                             <td class="position-relative">
//                                 ${invoice.number}
//                                 ${itemsTooltip}
//                             </td>
//                             <td>${invoice.date}</td>
//                             <td>${invoice.total.toFixed(2)} ج.م</td>
//                             <td>${invoice.remaining.toFixed(2)} ج.م</td>
//                             <td>
//                                 <input type="number" class="form-control form-control-sm workorder-payment-amount" 
//                                        data-invoice-id="${invoice.id}" 
//                                        min="0" max="${invoice.remaining}" 
//                                        value="${invoice.remaining}" step="0.01">
//                             </td>
//                         `;
//                 tbody.appendChild(row);
//             }
//         });

//         if (hasUnpaidInvoices) {
//             document.getElementById("workOrderInvoicesSection").style.display =
//                 "block";
//             this.resetPaymentMethods(
//                 "workOrderPaymentMethods",
//                 "workOrderPaymentTotal"
//             );
//             this.updateWorkOrderPaymentTotal();
//         } else {
//             document.getElementById("workOrderInvoicesSection").style.display =
//                 "none";
//             Swal.fire(
//                 "تنبيه",
//                 "لا توجد فواتير غير مسددة في هذه الشغلانة.",
//                 "info"
//             );
//         }
//     },

   

//     // إضافة دالة resetPaymentForm لإعادة تعيين المودال

//     resetPaymentMethods(containerId, totalElementId) {
//         document.getElementById(containerId).innerHTML = "";
//         document.getElementById(totalElementId).textContent = "0.00 ج.م";
//         this.addPaymentMethod(containerId, totalElementId);
//         // إعادة تعيين الحقول
//     document.getElementById('invoicesTotalAmount').value = '0.00';
//     document.getElementById('invoicesTotalAmountWorkOrder').value = '0.00';
//     document.getElementById('workOrderTotalAmount').value = '0.00';
//     document.getElementById('totalPaymentMethodsAmount').value = '0.00';
//     document.getElementById('paymentRequiredAmount').value = '0.00';
    
//     // إخفاء رسائل التحقق
//     document.getElementById('paymentValid').style.display = 'none';
//     document.getElementById('paymentInvalid').style.display = 'none';
//     document.getElementById('paymentExceeds').style.display = 'none';
    
//     // تعطيل زر السداد
//     document.getElementById('processPaymentBtn').disabled = true;
//     },

//     updateManualPaymentTotal() {
//         let total = 0;

//         // جمع المبالغ من المدخلات
//         document
//             .querySelectorAll(".manual-payment-amount")
//             .forEach((input) => {
//                 total += parseFloat(input.value) || 0;
//             });

//         document.getElementById("manualPaymentTotal").textContent =
//             total.toFixed(2) + " ج.م";

//         // تحديث الرصيد المتاح والمتبقي
//         const availableBalance = WalletManager.getAvailableBalance();
//         const remainingBalance = availableBalance - total;

//         document.getElementById("manualPaymentAvailableBalance").textContent =
//             availableBalance.toFixed(2) + " ج.م";
//         document.getElementById("manualPaymentRemainingBalance").textContent =
//             remainingBalance.toFixed(2) + " ج.م";

//         // تغيير لون المتبقي إذا كان سالباً
//         const remainingElement = document.getElementById(
//             "manualPaymentRemainingBalance"
//         );
//         if (remainingBalance < 0) {
//             remainingElement.classList.add("text-danger");
//             remainingElement.classList.remove("text-success");
//         } else {
//             remainingElement.classList.add("text-success");
//             remainingElement.classList.remove("text-danger");
//         }
//     },

//     updateWorkOrderPaymentTotal() {
//         let total = 0;

//         // جمع المبالغ من المدخلات
//         document
//             .querySelectorAll(".workorder-payment-amount")
//             .forEach((input) => {
//                 total += parseFloat(input.value) || 0;
//             });

//         document.getElementById("workOrderPaymentTotal").textContent =
//             total.toFixed(2) + " ج.م";

//         // تحديث الرصيد المتاح والمتبقي
//         const availableBalance = WalletManager.getAvailableBalance();
//         const remainingBalance = availableBalance - total;

//         document.getElementById(
//             "workOrderPaymentAvailableBalance"
//         ).textContent = availableBalance.toFixed(2) + " ج.م";
//         document.getElementById(
//             "workOrderPaymentRemainingBalance"
//         ).textContent = remainingBalance.toFixed(2) + " ج.م";

//         // تغيير لون المتبقي إذا كان سالباً
//         const remainingElement = document.getElementById(
//             "workOrderPaymentRemainingBalance"
//         );
//         if (remainingBalance < 0) {
//             remainingElement.classList.add("text-danger");
//             remainingElement.classList.remove("text-success");
//         } else {
//             remainingElement.classList.add("text-success");
//             remainingElement.classList.remove("text-danger");
//         }
//     },

//     collectPaymentMethods(containerId) {
//         const methods = [];
//         const container = document.getElementById(containerId);

//         container.querySelectorAll(".payment-method-item").forEach((item) => {
//             const methodSelect = item.querySelector(".payment-method-select");
//             const amountInput = item.querySelector(".payment-method-amount");

//             const methodId = parseInt(methodSelect.value);
//             const amount = parseFloat(amountInput.value) || 0;

//             if (methodId && amount > 0) {
//                 const method = PaymentMethods.find((pm) => pm.id === methodId);
//                 if (method) {
//                     methods.push({
//                         method: method.name,
//                         amount: amount,
//                     });
//                 }
//             }
//         });

//         return methods;
//     },

//    processPayment() {
//     const totalPayment = PaymentManager.calculatePaymentMethodsTotal();
//     let totalInvoicesAmount = 0;
//     const paymentType = document.querySelector('input[name="paymentType"]:checked').value;
    
//     if (paymentType === 'invoices') {
//         document.querySelectorAll('.invoice-payment-amount:not(:disabled)').forEach(input => {
//             totalInvoicesAmount += parseFloat(input.value) || 0;
//         });
//     } else {
//         document.querySelectorAll('.workorder-invoice-payment-amount').forEach(input => {
//             totalInvoicesAmount += parseFloat(input.value) || 0;
//         });
//     }
    
//     const diff = Math.abs(totalPayment - totalInvoicesAmount);
//     if (diff > 0.01) {
//         Swal.fire({
//             icon: 'error',
//             title: 'خطأ في المبلغ',
//             html: `مجموع طرق الدفع (${totalPayment.toFixed(2)}) لا يساوي مجموع المبالغ المدخلة للفواتير (${totalInvoicesAmount.toFixed(2)})<br>الفرق: ${Math.abs(totalPayment - totalInvoicesAmount).toFixed(2)} ج.م`,
//             confirmButtonText: 'حسناً'
//         });
//         return;
//     }
    
//     const paymentMethods = this.collectPaymentMethods();
    
//     if (paymentMethods.length === 0) {
//         Swal.fire('تحذير', 'يرجى إدخال طرق دفع صحيحة.', 'warning');
//         return;
//     }
    
//     let totalPaid = 0;
//     let paidInvoices = [];
    
//     if (paymentType === 'invoices') {
//         // معالجة سداد الفواتير
//         document.querySelectorAll('.invoice-payment-checkbox:checked').forEach(checkbox => {
//             const invoiceId = parseInt(checkbox.getAttribute('data-invoice-id'));
//             const amountInput = document.querySelector(`.invoice-payment-amount[data-invoice-id="${invoiceId}"]`);
//             const amount = parseFloat(amountInput.value) || 0;
            
//             if (amount > 0) {
//                 this.addPaymentToInvoice(invoiceId, amount, paymentMethods);
//                 totalPaid += amount;
//                 paidInvoices.push(invoiceId);
//             }
//         });
//     } else {
//         // معالجة سداد فواتير الشغلانة
//         document.querySelectorAll('.workorder-invoice-payment-amount').forEach(input => {
//             const amount = parseFloat(input.value) || 0;
//             const invoiceId = parseInt(input.getAttribute('data-invoice-id'));
            
//             if (amount > 0) {
//                 this.addPaymentToInvoice(invoiceId, amount, paymentMethods);
//                 totalPaid += amount;
//                 paidInvoices.push(invoiceId);
//             }
//         });
//     }
    
//     if (totalPaid > 0) {
//         Swal.fire({
//             icon: 'success',
//             title: 'تم السداد بنجاح',
//             html: `تم سداد ${totalPaid.toFixed(2)} ج.م<br>عدد الفواتير: ${paidInvoices.length}`,
//             confirmButtonText: 'حسناً'
//         });
        
//         const paymentModal = bootstrap.Modal.getInstance(document.getElementById('paymentModal'));
//         paymentModal.hide();
        
//         // تحديث الواجهة
//         InvoiceManager.updateInvoicesTable();
//         WorkOrderManager.updateWorkOrdersTable();
//         CustomerManager.updateCustomerBalance();
//         updateInvoiceStats();
        
//         // إعادة تعيين المودال
//         this.resetPaymentModal();
//     } else {
//         Swal.fire('تحذير', 'لم يتم سداد أي مبلغ.', 'warning');
//     }
// },

// // دالة إعادة تعيين المودال
// resetPaymentModal() {
//     // إعادة تعيين الفواتير المحددة
//     document.querySelectorAll('.invoice-payment-checkbox').forEach(checkbox => {
//         checkbox.checked = false;
//     });
    
//     // إعادة تعيين المبالغ
//     document.querySelectorAll('.invoice-payment-amount').forEach(input => {
//         input.value = 0;
//         input.disabled = true;
//     });
    
//     document.querySelectorAll('.workorder-invoice-payment-amount').forEach(input => {
//         input.value = 0;
//     });
    
//     // إعادة تعيين طرق الدفع
//     document.getElementById('paymentMethodsContainer').innerHTML = '';
//     this.addPaymentMethod();
    
//     // إعادة تعيين الحقول
//     document.getElementById('invoicesTotalAmount').value = '0.00';
//     document.getElementById('invoicesTotalAmountWorkOrder').value = '0.00';
    
//         document.getElementById('totalRequiredAmount').textContent = 0 + ' ج.م';

//     document.getElementById('workOrderTotalAmount').value = '0.00';
//     document.getElementById('totalPaymentMethodsAmount').value = '0.00';
//     document.getElementById('paymentRequiredAmount').value = '0.00';
    
//     // إخفاء رسائل التحقق
//     document.getElementById('paymentValid').style.display = 'none';
//     document.getElementById('paymentInvalid').style.display = 'none';
//     document.getElementById('paymentExceeds').style.display = 'none';
    
//     // تعطيل زر السداد
//     document.getElementById('processPaymentBtn').disabled = true;
// },

//     processManualPayment() {
//         const paymentMethods = this.collectPaymentMethods(
//             "manualPaymentMethods"
//         );
//         const totalPayment = paymentMethods.reduce(
//             (sum, pm) => sum + pm.amount,
//             0
//         );

//         if (totalPayment <= 0) {
//             Swal.fire("تحذير", "يرجى إدخال مبالغ صحيحة للدفع.", "warning");
//             return;
//         }

//         let totalPaid = 0;
//         const paymentInputs = document.querySelectorAll(
//             ".manual-payment-amount"
//         );

//         paymentInputs.forEach((input) => {
//             const amount = parseFloat(input.value) || 0;
//             const invoiceId = parseInt(input.getAttribute("data-invoice-id"));
//             const invoice = AppData.invoices.find((i) => i.id === invoiceId);

//             if (invoice && amount > 0 && amount <= invoice.remaining) {
//                 this.addPaymentToInvoice(invoiceId, amount, paymentMethods);
//                 totalPaid += amount;
//             }
//         });

//         if (totalPaid > 0) {
//             Swal.fire(
//                 "نجاح",
//                 `تم سداد ${totalPaid.toFixed(2)} ج.م بنجاح.`,
//                 "success"
//             );
//             const paymentModal = bootstrap.Modal.getInstance(
//                 document.getElementById("paymentModal")
//             );
//             paymentModal.hide();
//         } else {
//             Swal.fire(
//                 "تحذير",
//                 "لم يتم تحديد أي مبالغ للدفع أو القيم غير صالحة.",
//                 "warning"
//             );
//         }
//     },

//     processWorkOrderPayment() {
//         const workOrderId = parseInt(
//             document.getElementById("workOrderPaymentSelect").value
//         );
//         if (!workOrderId) {
//             Swal.fire("تحذير", "يرجى اختيار شغلانة.", "warning");
//             return;
//         }

//         const paymentMethods = this.collectPaymentMethods(
//             "workOrderPaymentMethods"
//         );
//         const totalPayment = paymentMethods.reduce(
//             (sum, pm) => sum + pm.amount,
//             0
//         );

//         if (totalPayment <= 0) {
//             Swal.fire("تحذير", "يرجى إدخال مبالغ صحيحة للدفع.", "warning");
//             return;
//         }

//         let totalPaid = 0;
//         const paymentInputs = document.querySelectorAll(
//             ".workorder-payment-amount"
//         );

//         paymentInputs.forEach((input) => {
//             const amount = parseFloat(input.value) || 0;
//             const invoiceId = parseInt(input.getAttribute("data-invoice-id"));
//             const invoice = AppData.invoices.find((i) => i.id === invoiceId);

//             if (invoice && amount > 0 && amount <= invoice.remaining) {
//                 this.addPaymentToInvoice(invoiceId, amount, paymentMethods);
//                 totalPaid += amount;
//             }
//         });

//         if (totalPaid > 0) {
//             Swal.fire(
//                 "نجاح",
//                 `تم سداد ${totalPaid.toFixed(2)} ج.م للشغلانة بنجاح.`,
//                 "success"
//             );
//             const paymentModal = bootstrap.Modal.getInstance(
//                 document.getElementById("paymentModal")
//             );
//             paymentModal.hide();
//         } else {
//             Swal.fire(
//                 "تحذير",
//                 "لم يتم تحديد أي مبالغ للدفع أو القيم غير صالحة.",
//                 "warning"
//             );
//         }
//     },

//     openSingleInvoicePayment(invoiceId) {
//         // تعيين نوع السداد إلى فواتير
//         document.getElementById('payInvoicesRadio').checked = true;
//         document.getElementById('invoicesPaymentSection').style.display = 'block';
//         document.getElementById('workOrderPaymentSection').style.display = 'none';

//         // تحميل الفواتير
//         PaymentManager.loadInvoicesForPayment();

//         // تحديد الفاتورة المطلوبة فقط
//         setTimeout(() => {
//             const checkbox = document.querySelector(`.invoice-payment-checkbox[data-invoice-id="${invoiceId}"]`);
//             if (checkbox) {
//                 checkbox.checked = true;
//                 checkbox.dispatchEvent(new Event('change'));

//                 const amountInput = document.querySelector(`.invoice-payment-amount[data-invoice-id="${invoiceId}"]`);
//                 if (amountInput) {
//                     amountInput.value = amountInput.getAttribute('max');
//                     amountInput.dispatchEvent(new Event('input'));
//                 }

//                 // التمرير إلى الصف المحدد
//                 const row = checkbox.closest('tr');
//                 if (row) {
//                     row.scrollIntoView({ behavior: 'smooth', block: 'center' });
//                     row.style.backgroundColor = 'rgba(13, 110, 253, 0.1)';
//                     setTimeout(() => {
//                         row.style.backgroundColor = '';
//                     }, 3000);
//                 }
//             }
//         }, 100);

//         // فتح المودال
//         const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
//         paymentModal.show();
//     }
//     , openWorkOrderPayment(workOrderId) {
//         document.getElementById("workOrderPayment").checked = true;
//         document.getElementById("manualPaymentSection").style.display =
//             "none";
//         document.getElementById("workOrderPaymentSection").style.display =
//             "block";

//         // تحديد الشغلانة
//         document.getElementById("workOrderPaymentSelect").value = workOrderId;
//         this.updateWorkOrderInvoices(workOrderId);

//         // فتح مودال السداد
//         const paymentModal = new bootstrap.Modal(
//             document.getElementById("paymentModal")
//         );
//         paymentModal.show();
//     },
//     // في دالة loadInvoicesForPayment داخل PaymentManager
//     loadInvoicesForPayment() {
//         const tbody = document.getElementById('invoicesPaymentTableBody');
//         tbody.innerHTML = '';

//         const unpaidInvoices = AppData.invoices.filter(i => i.remaining > 0);

//         unpaidInvoices.forEach(invoice => {
//             const row = document.createElement('tr');
//             row.className = 'invoice-item-hover';

//             // الحصول على اسم الشغلانة إذا كانت مرتبطة
//             let workOrderName = '';
//             if (invoice.workOrderId) {
//                 const workOrder = AppData.workOrders.find(wo => wo.id === invoice.workOrderId);
//                 if (workOrder) {
//                     workOrderName = workOrder.name;
//                 }
//             }

//             // إنشاء tooltip للبنود
//             let itemsTooltip = '';
//             if (invoice.items && invoice.items.length > 0) {
//                 const itemsList = invoice.items.map(item => {
//                     const itemTotal = (item.quantity || 0) * (item.price || 0);
//                     return `
//                     <div class="tooltip-item">
//                         <div>
//                             <div class="tooltip-item-name">${item.productName || 'منتج'}</div>
//                             <div class="tooltip-item-details">الكمية: ${item.quantity || 0} | السعر: ${(item.price || 0).toFixed(2)} ج.م</div>
//                         </div>
//                         <div class="fw-bold">${itemTotal.toFixed(2)} ج.م</div>
//                     </div>
//                 `;
//                 }).join('');

//                 itemsTooltip = `
//                 <div class="invoice-items-tooltip">
//                     <div class="tooltip-header">بنود الفاتورة ${invoice.number}</div>
//                     ${itemsList}
//                     <div class="tooltip-total">
//                         <span>الإجمالي:</span>
//                         <span>${invoice.total.toFixed(2)} ج.م</span>
//                     </div>
//                 </div>
//             `;
//             }

//             row.innerHTML = `
//             <td>
//                 <input type="checkbox" class="form-check-input invoice-payment-checkbox" 
//                        data-invoice-id="${invoice.id}"
//                        data-has-workorder="${invoice.workOrderId ? 'true' : 'false'}">
//             </td>
//             <td class="position-relative">
//                 <strong>${invoice.number}</strong>
//                 ${workOrderName ? `<br><small class="text-muted"><i class="fas fa-tools me-1"></i>${workOrderName}</small>` : ''}
//                 ${itemsTooltip}
//             </td>
//             <td>${invoice.date}</td>
//             <td>${invoice.total.toFixed(2)} ج.م</td>
//             <td>${invoice.remaining.toFixed(2)} ج.م</td>
//             <td>
//                 <input type="number" class="form-control form-control-sm invoice-payment-amount" 
//                        data-invoice-id="${invoice.id}" 
//                        data-workorder-id="${invoice.workOrderId || ''}"
//                        min="0" max="${invoice.remaining}" 
//                        value="0" step="0.01" disabled>
//             </td>
//         `;
//             tbody.appendChild(row);

//             // تفعيل/تعطيل حقل الإدخال بناءً على التحديد
//             const checkbox = row.querySelector('.invoice-payment-checkbox');
//             const amountInput = row.querySelector('.invoice-payment-amount');

//             checkbox.addEventListener('change', function () {
//                 amountInput.disabled = !this.checked;
//                 if (!this.checked) {
//                     amountInput.value = 0;
//                 }
//                 PaymentManager.updatePaymentSummary();
//             });

//             amountInput.addEventListener('input', function () {
//                 const maxAmount = parseFloat(this.getAttribute('max'));
//                 const currentValue = parseFloat(this.value) || 0;

//                 if (currentValue > maxAmount) {
//                     this.value = maxAmount;
//                     Swal.fire('تحذير', `لا يمكن سداد أكثر من ${maxAmount.toFixed(2)} ج.م لهذه الفاتورة`, 'warning');
//                 }

//                 // التحقق من القيمة
//                 if (currentValue < 0) {
//                     this.value = 0;
//                     Swal.fire('تحذير', 'لا يمكن إدخال قيمة سالبة', 'warning');
//                 }

//                 PaymentManager.updatePaymentSummary();
//             });
//         });

//         PaymentManager.resetPaymentMethods();
//         PaymentManager.updatePaymentSummary();
//     },
//     filterInvoicesForPayment(searchTerm) {
//         const rows = document.querySelectorAll('#invoicesPaymentTableBody tr');

//         rows.forEach(row => {
//             const invoiceNumber = row.querySelector('td:nth-child(2)').textContent;
//             const isVisible = invoiceNumber.toLowerCase().includes(searchTerm.toLowerCase());
//             row.style.display = isVisible ? '' : 'none';
//         });
//     },

//     searchWorkOrders(searchTerm) {
//         const container = document.getElementById('workOrderSearchResults');

//         if (!searchTerm || searchTerm.length < 2) {
//             container.style.display = 'none';
//             return;
//         }

//         const results = AppData.workOrders.filter(workOrder =>
//             workOrder.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
//             workOrder.description.toLowerCase().includes(searchTerm.toLowerCase())
//         );

//         container.innerHTML = '';

//         if (results.length === 0) {
//             container.innerHTML = '<div class="p-3 text-muted text-center">لا توجد نتائج</div>';
//             container.style.display = 'block';
//             return;
//         }

//         results.forEach(workOrder => {
//             const div = document.createElement('div');
//             div.className = 'search-result-item';
//             div.innerHTML = `
//             <div class="fw-bold">${workOrder.name}</div>
//             <div class="small text-muted">${workOrder.description}</div>
//             <div class="small">تاريخ البدء: ${workOrder.startDate} | الحالة: ${workOrder.status === 'pending' ? 'قيد التنفيذ' : 'مكتمل'}</div>
//         `;

//             div.addEventListener('click', function () {
//                 PaymentManager.selectWorkOrderForPayment(workOrder.id);
//                 container.style.display = 'none';
//                 document.getElementById('workOrderSearch').value = workOrder.name;
//             });

//             container.appendChild(div);
//         });

//         container.style.display = 'block';
//     },

//     selectWorkOrderForPayment(workOrderId) {
//         const workOrder = AppData.workOrders.find(wo => wo.id === workOrderId);
//         if (!workOrder) return;

//         // تحديث تفاصيل الشغلانة
//         document.getElementById('selectedWorkOrderName').textContent = workOrder.name;
//         document.getElementById('selectedWorkOrderDescription').textContent = workOrder.description;
//         document.getElementById('selectedWorkOrderStartDate').textContent = workOrder.startDate;
//         document.getElementById('selectedWorkOrderStatus').textContent =
//             workOrder.status === 'pending' ? 'قيد التنفيذ' : 'مكتمل';

//         // الحصول على الفواتير المرتبطة
//         const relatedInvoices = AppData.invoices.filter(inv =>
//             workOrder.invoices.includes(inv.id) && inv.remaining > 0
//         );

//         document.getElementById('selectedWorkOrderInvoicesCount').textContent =
//             `${relatedInvoices.length} فاتورة`;

//         // تحديث جدول فواتير الشغلانة
//         const tbody = document.getElementById('workOrderInvoicesTableBody');
//         tbody.innerHTML = '';

//         relatedInvoices.forEach(invoice => {
//             const row = document.createElement('tr');
//             row.className = 'invoice-item-hover';

//             // إنشاء tooltip للبنود
//             let itemsTooltip = '';
//             if (invoice.items && invoice.items.length > 0) {
//                 const itemsList = invoice.items.map(item => {
//                     const itemTotal = (item.quantity || 0) * (item.price || 0);
//                     return `
//                     <div class="tooltip-item">
//                         <div>
//                             <div class="tooltip-item-name">${item.productName || 'منتج'}</div>
//                             <div class="tooltip-item-details">الكمية: ${item.quantity || 0} | السعر: ${(item.price || 0).toFixed(2)} ج.م</div>
//                         </div>
//                         <div class="fw-bold">${itemTotal.toFixed(2)} ج.م</div>
//                     </div>
//                 `;
//                 }).join('');

//                 itemsTooltip = `
//                 <div class="invoice-items-tooltip">
//                     <div class="tooltip-header">بنود الفاتورة ${invoice.number}</div>
//                     ${itemsList}
//                     <div class="tooltip-total">
//                         <span>الإجمالي:</span>
//                         <span>${invoice.total.toFixed(2)} ج.م</span>
//                     </div>
//                 </div>
//             `;
//             }

//             row.innerHTML = `
//             <td class="position-relative">
//                 ${invoice.number}
//                 ${itemsTooltip}
//             </td>
//             <td>${invoice.date}</td>
//             <td>${invoice.total.toFixed(2)} ج.م</td>
//             <td>${invoice.remaining.toFixed(2)} ج.م</td>
//             <td>
//                 <input type="number" class="form-control form-control-sm workorder-invoice-payment-amount" 
//                        data-invoice-id="${invoice.id}" 
//                        min="0" max="${invoice.remaining}" 
//                        value="0" step="0.01">
//             </td>
//         `;
//             tbody.appendChild(row);

//             // إضافة مستمع للأحداث للمبلغ المدخل
//             const amountInput = row.querySelector('.workorder-invoice-payment-amount');
//             amountInput.addEventListener('input', function () {
//                 const maxAmount = parseFloat(this.getAttribute('max'));
//                 const currentValue = parseFloat(this.value) || 0;

//                 if (currentValue > maxAmount) {
//                     this.value = maxAmount;
//                     Swal.fire('تحذير', `لا يمكن سداد أكثر من ${maxAmount.toFixed(2)} ج.م لهذه الفاتورة`, 'warning');
//                 }

//                 PaymentManager.updatePaymentSummary();
//             });
//         });

//         document.getElementById('selectedWorkOrderDetails').style.display = 'block';
//         PaymentManager.resetPaymentMethods();
//         PaymentManager.updatePaymentSummary();
//     },

//     resetWorkOrderSearch() {
//         document.getElementById('workOrderSearch').value = '';
//         document.getElementById('workOrderSearchResults').style.display = 'none';
//         document.getElementById('selectedWorkOrderDetails').style.display = 'none';
//         document.getElementById('workOrderInvoicesTableBody').innerHTML = '';
//         PaymentManager.resetPaymentMethods();
//         PaymentManager.updatePaymentSummary();
//     },

//     toggleSelectAllInvoices(checked) {
//         const checkboxes = document.querySelectorAll('.invoice-payment-checkbox');
//         const amountInputs = document.querySelectorAll('.invoice-payment-amount');

//         checkboxes.forEach((checkbox, index) => {
//             if (checkbox.closest('tr').style.display !== 'none') {
//                 checkbox.checked = checked;
//                 amountInputs[index].disabled = !checked;

//                 if (!checked) {
//                     amountInputs[index].value = 0;
//                 }
//             }
//         });

//         PaymentManager.updatePaymentSummary();
//     },

//    // تعديل دالة addPaymentMethod لجعل النقدي هو الافتراضي
// addPaymentMethod() {
//     const container = document.getElementById('paymentMethodsContainer');
//     const methodCount = container.children.length;
    
//     // إذا كانت هذه هي أول طريقة دفع، اجعلها نقدي
//     let defaultMethod = '2'; // نقدي
//     if (methodCount === 0) {
//         defaultMethod = '1'; // نقدي (الرقم 1 حسب PaymentMethods)
//     }
    
//     const methodElement = document.createElement('div');
//     methodElement.className = 'payment-method-item mb-3 border p-3 rounded';
//     methodElement.innerHTML = `
//         <div class="row g-2 align-items-end">
//             <div class="col-md-4">
//                 <label class="form-label small">طريقة الدفع</label>
//                 <select class="form-select payment-method-select" data-method-index="${methodCount}" required>
//                     <option value="">اختر طريقة...</option>
//                     ${PaymentMethods.map(pm => 
//                         `<option value="${pm.id}" ${pm.id === 1 ? 'selected' : ''}>${pm.name}</option>`
//                     ).join('')}
//                 </select>
//             </div>
//             <div class="col-md-3">
//                 <label class="form-label small">المبلغ</label>
//                 <input type="number" class="form-control payment-method-amount" 
//                        data-method-index="${methodCount}" step="0.01" value="0" required>
//             </div>
//             <div class="col-md-4">
//                 <label class="form-label small">ملاحظات (اختياري)</label>
//                 <input type="text" class="form-control payment-method-notes" 
//                        data-method-index="${methodCount}" placeholder="ملاحظات حول هذه الدفعة...">
//             </div>
//             <div class="col-md-1">
//                 <button type="button" class="btn btn-danger btn-sm remove-payment-method" 
//                         data-method-index="${methodCount}" ${methodCount === 0 ? 'disabled' : ''}>
//                     <i class="fas fa-times"></i>
//                 </button>
//             </div>
//         </div>
//     `;
//     container.appendChild(methodElement);
    
//     // إضافة مستمع حدث لحذف طريقة الدفع
//     const removeBtn = methodElement.querySelector('.remove-payment-method');
//     removeBtn.addEventListener('click', function() {
//         if (container.children.length > 1) {
//             methodElement.remove();
//             PaymentManager.calculatePaymentMethodsTotal();
//             PaymentManager.validatePayment();
//         }
//     });
    
//     // إضافة مستمع للأحداث
//   // في دالة addPaymentMethod، بعد إنشاء العنصر:
// const amountInput = methodElement.querySelector('.payment-method-amount');
// amountInput.addEventListener('input', function() {
//     PaymentManager.calculatePaymentMethodsTotal();
//     PaymentManager.validatePayment();
// });
    
//     PaymentManager.calculatePaymentMethodsTotal();
//     PaymentManager.validatePayment();


// },

//     resetPaymentMethods() {
//         document.getElementById('paymentMethodsContainer').innerHTML = '';
//         PaymentManager.addPaymentMethod();
//     },

//     updatePaymentSummary() {
//         let totalRequired = 0;
//         let totalEntered = 0;
//         let walletPayment = 0;
//         const paymentType = document.querySelector('input[name="paymentType"]:checked').value;

//         // حساب المبلغ المطلوب بناءً على نوع السداد
//         if (paymentType === 'invoices') {
//             // جمع المبالغ المطلوبة من الفواتير المحددة
//             document.querySelectorAll('.invoice-payment-checkbox:checked').forEach(checkbox => {
//                 const invoiceId = parseInt(checkbox.getAttribute('data-invoice-id'));
//                 const invoice = AppData.invoices.find(i => i.id === invoiceId);
//                 if (invoice) {
//                     totalRequired += invoice.remaining;
//                 }
//             });

//             // جمع المبالغ المدخلة
//             document.querySelectorAll('.invoice-payment-amount:not(:disabled)').forEach(input => {
//                 totalEntered += parseFloat(input.value) || 0;
//             });
//         } else {
//             // جمع المبالغ من فواتير الشغلانة
//             document.querySelectorAll('.workorder-invoice-payment-amount').forEach(input => {
//                 const amount = parseFloat(input.value) || 0;
//                 totalEntered += amount;

//                 const invoiceId = parseInt(input.getAttribute('data-invoice-id'));
//                 const invoice = AppData.invoices.find(i => i.id === invoiceId);
//                 if (invoice) {
//                     totalRequired += invoice.remaining;
//                 }
//             });
//         }

//         // حساب مبلغ المحفظة من طرق الدفع
//         walletPayment = 0;
//         document.querySelectorAll('.payment-method-item').forEach(item => {
//             const methodSelect = item.querySelector('.payment-method-select');
//             const amountInput = item.querySelector('.payment-method-amount');

//             if (methodSelect.value === '4') { // 4 هو id طريقة دفع المحفظة
//                 walletPayment += parseFloat(amountInput.value) || 0;
//             }
//         });

//         // تحديث العناصر
//         document.getElementById('invoicesTotalAmount').value = totalRequired.toFixed(2) ;
//         document.getElementById('invoicesTotalAmountWorkOrder').value = totalRequired.toFixed(2) ;
//         document.getElementById('totalRequiredAmount').textContent = totalRequired.toFixed(2) + ' ج.م';
//         document.getElementById('totalEnteredAmount').textContent = totalEntered.toFixed(2) + ' ج.م';
//         document.getElementById('paymentRequiredAmount').value = totalEntered.toFixed(2) ;

//         // عرض/إخفاء تفاصيل المحفظة
//         const walletDetails = document.getElementById('walletPaymentDetails');
//         if (walletPayment > 0) {
//             const availableBalance = WalletManager.getAvailableBalance();
//             const remainingBalance = availableBalance - walletPayment;

//             document.getElementById('availableWalletBalance').textContent = availableBalance.toFixed(2) + ' ج.م';
//             document.getElementById('walletPaymentAmount').textContent = walletPayment.toFixed(2) + ' ج.م';
//             document.getElementById('remainingWalletBalance').textContent = remainingBalance.toFixed(2) + ' ج.م';

//             walletDetails.style.display = 'block';

//             // التحقق من أن مبلغ المحفظة لا يتجاوز الرصيد المتاح
//             if (walletPayment > availableBalance) {
//                 document.getElementById('paymentError').textContent =
//                     'مبلغ المحفظة المطلوب يتجاوز الرصيد المتاح!';
//                 document.getElementById('paymentError').style.display = 'block';
//                 document.getElementById('processPaymentBtn').disabled = true;
//                 return;
//             }
//         } else {
//             walletDetails.style.display = 'none';
//         }

//         // التحقق من أن المبلغ المدخل لا يتجاوز المبلغ المطلوب
//         if (totalEntered > totalRequired) {
//             document.getElementById('paymentError').textContent =
//                 'المبلغ المدخل يتجاوز المبلغ المطلوب!';
//             document.getElementById('paymentError').style.display = 'block';
//             document.getElementById('processPaymentBtn').disabled = true;
//             return;
//         }

//         // التحقق من أن المبلغ المدخل يساوي المبلغ المطلوب (إذا كان المستخدم يريد السداد الكامل)
//         const remainingAfterPayment = totalRequired - totalEntered;
//         document.getElementById('totalRemainingAfterPayment').textContent =
//             remainingAfterPayment.toFixed(2) + ' ج.م';

//         // إخفاء رسالة الخطأ إذا لم توجد أخطاء
//         document.getElementById('paymentError').style.display = 'none';

//         // تفعيل/تعطيل زر المعالجة
//         document.getElementById('processPaymentBtn').disabled = totalEntered <= 0;
//     },

//     // resetPaymentForm() {
//     //     PaymentManager.resetPaymentMethods();
//     //     document.getElementById('totalRequiredAmount').textContent = '0.00 ج.م';
//     //     document.getElementById('totalEnteredAmount').textContent = '0.00 ج.م';
//     //     document.getElementById('walletPaymentDetails').style.display = 'none';
//     //     document.getElementById('paymentError').style.display = 'none';
//     //     document.getElementById('processPaymentBtn').disabled = true;
//     //     document.getElementById('invoiceSearch').value = '';

//     // },

//     resetPaymentForm() {
//         PaymentManager.resetPaymentMethods();
//         document.getElementById('paymentMethodsContainer').innerHTML = '';
//         this.addPaymentMethod();
//         document.getElementById('totalRequiredAmount').textContent = '0.00 ج.م';
//         document.getElementById('totalEnteredAmount').textContent = '0.00 ج.م';
//         document.getElementById('totalRemainingAfterPayment').textContent = '0.00 ج.م';
//         document.getElementById('paymentError').style.display = 'none';
//         document.getElementById('processPaymentBtn').disabled = true;
//     },

//     processPayment() {
//         const paymentType = document.querySelector('input[name="paymentType"]:checked').value;

//         // جمع طرق الدفع
//         const paymentMethods = [];
//         document.querySelectorAll('.payment-method-item').forEach(item => {
//             const methodSelect = item.querySelector('.payment-method-select');
//             const amountInput = item.querySelector('.payment-method-amount');

//             const methodId = parseInt(methodSelect.value);
//             const amount = parseFloat(amountInput.value) || 0;

//             if (methodId && amount > 0) {
//                 const method = PaymentMethods.find(pm => pm.id === methodId);
//                 if (method) {
//                     paymentMethods.push({
//                         method: method.name,
//                         methodId: methodId,
//                         amount: amount
//                     });
//                 }
//             }
//         });

//         if (paymentMethods.length === 0) {
//             Swal.fire('تحذير', 'يرجى إدخال طرق دفع صحيحة.', 'warning');
//             return;
//         }

//         let totalPaid = 0;

//         if (paymentType === 'invoices') {
//             // معالجة سداد الفواتير
//             document.querySelectorAll('.invoice-payment-checkbox:checked').forEach(checkbox => {
//                 const invoiceId = parseInt(checkbox.getAttribute('data-invoice-id'));
//                 const amountInput = document.querySelector(`.invoice-payment-amount[data-invoice-id="${invoiceId}"]`);
//                 const amount = parseFloat(amountInput.value) || 0;

//                 if (amount > 0) {
//                     PaymentManager.addPaymentToInvoice(invoiceId, amount, paymentMethods);
//                     totalPaid += amount;
//                 }
//             });
//         } else {
//             // معالجة سداد فواتير الشغلانة
//             document.querySelectorAll('.workorder-invoice-payment-amount').forEach(input => {
//                 const amount = parseFloat(input.value) || 0;
//                 const invoiceId = parseInt(input.getAttribute('data-invoice-id'));

//                 if (amount > 0) {
//                     PaymentManager.addPaymentToInvoice(invoiceId, amount, paymentMethods);
//                     totalPaid += amount;
//                 }
//             });
//         }

//         if (totalPaid > 0) {
//             Swal.fire('نجاح', `تم سداد ${totalPaid.toFixed(2)} ج.م بنجاح.`, 'success');
//             const paymentModal = bootstrap.Modal.getInstance(document.getElementById('paymentModal'));
//             paymentModal.hide();

//             // تحديث الواجهة
//             InvoiceManager.updateInvoicesTable();
//             WorkOrderManager.updateWorkOrdersTable();
//             CustomerManager.updateCustomerBalance();
//             updateInvoiceStats();
//         } else {
//             Swal.fire('تحذير', 'لم يتم تحديد أي مبالغ للدفع.', 'warning');
//         }
//     },

//     addPaymentToInvoice(invoiceId, amount, paymentMethods) {
//         const invoice = AppData.invoices.find(i => i.id === invoiceId);
//         if (invoice) {
//             // تحديث الفاتورة
//             invoice.paid += amount;
//             invoice.remaining = invoice.total - invoice.paid;

//             // تحديث حالة الفاتورة
//             if (invoice.remaining === 0) {
//                 invoice.status = 'paid';
//             } else if (invoice.paid > 0) {
//                 invoice.status = 'partial';
//             }

//             // إضافة حركة للمحفظة إذا كانت هناك طريقة دفع بالمحفظة
//             const walletPayment = paymentMethods.find(pm => pm.methodId === 4); // 4 هو id المحفظة
//             if (walletPayment) {
//                 WalletManager.addTransaction({
//                     type: 'payment',
//                     amount: -walletPayment.amount,
//                     description: `سداد للفاتورة ${invoice.number}`,
//                     date: new Date().toISOString().split('T')[0],
//                     paymentMethods: [walletPayment]
//                 });
//             }
//         }
//     },




//     // new
//     // دالة تحديث المبلغ المطلوب والمقارنة
// updateAndValidate() {
//     const paymentType = document.querySelector('input[name="paymentType"]:checked').value;
//     let totalRequired = 0;
    
//     if (paymentType === 'invoices') {
//         // حساب المبلغ المطلوب الكلي (مجموع المتبقي للفواتير المحددة)
//         document.querySelectorAll('.invoice-payment-checkbox:checked').forEach(checkbox => {
//             const invoiceId = parseInt(checkbox.getAttribute('data-invoice-id'));
//             const invoice = AppData.invoices.find(i => i.id === invoiceId);
//             if (invoice) {
//                 totalRequired += invoice.remaining;
//             }
//         });
        
//         document.getElementById('invoicesTotalAmount').value = totalRequired.toFixed(2);
//     } else {
//         // حساب المبلغ المطلوب الكلي لفواتير الشغلانة
//         document.querySelectorAll('.workorder-invoice-payment-amount').forEach(input => {
//             const invoiceId = parseInt(input.getAttribute('data-invoice-id'));
//             const invoice = AppData.invoices.find(i => i.id === invoiceId);
//             if (invoice) {
//                 totalRequired += invoice.remaining;
//             }
//         });
        
//         document.getElementById('workOrderTotalAmount').value = totalRequired.toFixed(2);
//     }
    
//     document.getElementById('paymentRequiredAmount').value = totalRequired.toFixed(2);
    
//     // التحقق من المدفوعات
//     this.validatePayment();
// },

// // دالة حساب مجموع طرق الدفع
// calculatePaymentMethodsTotal() {
//     let total = 0;
//     document.querySelectorAll('.payment-method-amount').forEach(input => {
//         total += parseFloat(input.value) || 0;
//     });
    
//     document.getElementById('totalPaymentMethodsAmount').value = total.toFixed(2);
//     return total;
// },

// // دالة التحقق من المدفوعات
// validatePayment() {
//     const totalPayment = this.calculatePaymentMethodsTotal();
    
//     // حساب مجموع المبالغ المدخلة في الفواتير المحددة
//     let totalInvoicesAmount = 0;
//     const paymentType = document.querySelector('input[name="paymentType"]:checked').value;
    
//     if (paymentType === 'invoices') {
//         // الفواتير العامة: نجمع المبالغ من الحقول المفعلة (المرتبطة بالفواتير المحددة)
//         document.querySelectorAll('.invoice-payment-amount:not(:disabled)').forEach(input => {
//             totalInvoicesAmount += parseFloat(input.value) || 0;
//         });
//     } else {
//         // فواتير الشغلانة: نجمع كل الحقول
//         document.querySelectorAll('.workorder-invoice-payment-amount').forEach(input => {
//             totalInvoicesAmount += parseFloat(input.value) || 0;
//         });
//     }
    
//     // إخفاء جميع رسائل التحقق
//     document.getElementById('paymentValid').style.display = 'none';
//     document.getElementById('paymentInvalid').style.display = 'none';
//     document.getElementById('paymentExceeds').style.display = 'none';
    
//     // تفعيل/تعطيل زر السداد
//     const processBtn = document.getElementById('processPaymentBtn');
    
//     if (totalPayment === 0) {
//         processBtn.disabled = true;
//         return;
//     }
    
//     // التحقق باستخدام هامش خطأ صغير للنقاط العشرية
//     const diff = Math.abs(totalPayment - totalInvoicesAmount);
    
//     if (diff <= 0.01) { // هامش خطأ 0.01
//         document.getElementById('paymentValid').style.display = 'block';
//         processBtn.disabled = false;
//     } else if (totalPayment > totalInvoicesAmount) {
//         document.getElementById('paymentExceeds').style.display = 'block';
//         processBtn.disabled = true;
//     } else {
//         document.getElementById('paymentInvalid').style.display = 'block';
//         processBtn.disabled = true;
//     }
    
//     // تحديث عرض المبالغ في واجهة التحقق
//     document.getElementById('totalPaymentMethodsAmount').value = totalPayment.toFixed(2);
//     // لا نعرض المبلغ المطلوب الكلي هنا، بل نعرض مجموع الفواتير المحددة
//     // يمكننا إضافة عنصر جديد لعرض مجموع الفواتير المحددة إذا أردنا
// },

// // دالة التوزيع التلقائي
// // دالة التوزيع التلقائي المعدلة
// autoDistribute() {
//     const paymentType = document.querySelector('input[name="paymentType"]:checked').value;
//     const totalPayment = this.calculatePaymentMethodsTotal(); // مجموع طرق الدفع
    
//     if (totalPayment <= 0) {
//         Swal.fire('تحذير', 'يرجى إدخال مبلغ في طرق الدفع أولاً', 'warning');
//         return;
//     }
    
//     if (paymentType === 'invoices') {
//         this.autoDistributeToInvoices(totalPayment);
//     } else {
//         this.autoDistributeToWorkOrder(totalPayment);
//     }
// }
// ,
// // توزيع على الفواتير العامة (مرن)
// autoDistributeToInvoices(totalPayment) {
//     // الحصول على الفواتير المحددة
//     const selectedInvoices = [];
//     const checkboxes = document.querySelectorAll('.invoice-payment-checkbox:checked');
    
//     checkboxes.forEach(checkbox => {
//         const invoiceId = parseInt(checkbox.getAttribute('data-invoice-id'));
//         const invoice = AppData.invoices.find(i => i.id === invoiceId);
//         if (invoice) {
//             selectedInvoices.push(invoice);
//         }
//     });
    
//     // ترتيب الفواتير من الأقدم للأحدث
//     selectedInvoices.sort((a, b) => new Date(a.date) - new Date(b.date));
    
//     let remainingPayment = totalPayment;
    
//     // توزيع المبلغ
//     selectedInvoices.forEach(invoice => {
//         const amountInput = document.querySelector(`.invoice-payment-amount[data-invoice-id="${invoice.id}"]`);
//         if (amountInput && remainingPayment > 0) {
//             // المبلغ الذي يمكن دفعه لهذه الفاتورة هو الحد الأدنى بين المتبقي والمدفوع المتبقي
//             const amountToPay = Math.min(invoice.remaining, remainingPayment);
//             amountInput.value = amountToPay.toFixed(2);
//             amountInput.dispatchEvent(new Event('input')); // لتحريك الحدث
//             remainingPayment -= amountToPay;
//         }
//     });
    
//     // إذا بقي مبلغ بعد التوزيع (هذا لا يجب أن يحدث لأننا نوزع المبلغ الموجود في طرق الدفع)
//     if (remainingPayment > 0) {
//         // هذا يعني أن المبلغ الموجود في طرق الدفع أكبر من مجموع المتبقي للفواتير المحددة
//         Swal.fire({
//             icon: 'warning',
//             title: 'تنبيه',
//             text: `المبلغ الموجود في طرق الدفع (${totalPayment.toFixed(2)}) أكبر من المطلوب للفواتير المحددة (${(totalPayment - remainingPayment).toFixed(2)}). لم يتم توزيع ${remainingPayment.toFixed(2)} ج.م`,
//             confirmButtonText: 'حسناً'
//         });
//     }
    
//     this.validatePayment();
// },

// // توزيع على فواتير الشغلانة (مرن)
// autoDistributeToWorkOrder(totalPayment) {
//     const invoices = [];
    
//     document.querySelectorAll('.workorder-invoice-payment-amount').forEach(input => {
//         const invoiceId = parseInt(input.getAttribute('data-invoice-id'));
//         const invoice = AppData.invoices.find(i => i.id === invoiceId);
//         if (invoice) {
//             invoices.push({
//                 invoice: invoice,
//                 input: input
//             });
//         }
//     });
    
//     // ترتيب الفواتير من الأقدم للأحدث
//     invoices.sort((a, b) => new Date(a.invoice.date) - new Date(b.invoice.date));
    
//     let remainingPayment = totalPayment;
    
//     // توزيع المبلغ
//     invoices.forEach(item => {
//         if (remainingPayment > 0) {
//             const amountToPay = Math.min(item.invoice.remaining, remainingPayment);
//             item.input.value = amountToPay.toFixed(2);
//             item.input.dispatchEvent(new Event('input'));
//             remainingPayment -= amountToPay;
//         } else {
//             item.input.value = 0;
//             item.input.dispatchEvent(new Event('input'));
//         }
//     });
    
//     // إذا بقي مبلغ بعد التوزيع
//     if (remainingPayment > 0) {
//         Swal.fire({
//             icon: 'warning',
//             title: 'تنبيه',
//             text: `المبلغ الموجود في طرق الدفع (${totalPayment.toFixed(2)}) أكبر من المطلوب للفواتير المحددة (${(totalPayment - remainingPayment).toFixed(2)}). لم يتم توزيع ${remainingPayment.toFixed(2)} ج.م`,
//             confirmButtonText: 'حسناً'
//         });
//     }
    
//     this.validatePayment();
// },

// // توزيع على الفواتير العامة
// autoDistributeToInvoices(totalPayment) {
//     // الحصول على الفواتير المحددة والمرتبطة وغير المرتبطة
//     const selectedInvoices = [];
//     const checkboxes = document.querySelectorAll('.invoice-payment-checkbox:checked');
    
//     checkboxes.forEach(checkbox => {
//         const invoiceId = parseInt(checkbox.getAttribute('data-invoice-id'));
//         const invoice = AppData.invoices.find(i => i.id === invoiceId);
//         if (invoice) {
//             selectedInvoices.push(invoice);
//         }
//     });
    
//     // ترتيب الفواتير من الأقدم للأحدث
//     selectedInvoices.sort((a, b) => new Date(a.date) - new Date(b.date));
    
//     let remainingPayment = totalPayment;
    
//     // توزيع المبلغ
//     selectedInvoices.forEach(invoice => {
//         const amountInput = document.querySelector(`.invoice-payment-amount[data-invoice-id="${invoice.id}"]`);
//         if (amountInput && remainingPayment > 0) {
//             const amountToPay = Math.min(invoice.remaining, remainingPayment);
//             amountInput.value = amountToPay.toFixed(2);
//             remainingPayment -= amountToPay;
//         }
//     });
    
//     // التحقق من النتائج
//     if (remainingPayment > 0) {
//         Swal.fire({
//             icon: 'info',
//             title: 'توزيع جزئي',
//             text: `لم يكف المبلغ لسداد الكل. المبلغ المتبقي: ${remainingPayment.toFixed(2)} ج.م`,
//             confirmButtonText: 'حسناً'
//         });
//     }
    
//     this.validatePayment();
// },

// // توزيع على فواتير الشغلانة
// autoDistributeToWorkOrder(totalPayment) {
//     const invoices = [];
    
//     document.querySelectorAll('.workorder-invoice-payment-amount').forEach(input => {
//         const invoiceId = parseInt(input.getAttribute('data-invoice-id'));
//         const invoice = AppData.invoices.find(i => i.id === invoiceId);
//         if (invoice) {
//             invoices.push({
//                 invoice: invoice,
//                 input: input
//             });
//         }
//     });
    
//     // ترتيب الفواتير من الأقدم للأحدث
//     invoices.sort((a, b) => new Date(a.invoice.date) - new Date(b.invoice.date));
    
//     let remainingPayment = totalPayment;
    
//     // توزيع المبلغ
//     invoices.forEach(item => {
//         if (remainingPayment > 0) {
//             const amountToPay = Math.min(item.invoice.remaining, remainingPayment);
//             item.input.value = amountToPay.toFixed(2);
//             remainingPayment -= amountToPay;
//         } else {
//             item.input.value = 0;
//         }
//     });
    
//     // التحقق من النتائج
//     if (remainingPayment > 0) {
//         Swal.fire({
//             icon: 'info',
//             title: 'توزيع جزئي',
//             text: `لم يكف المبلغ لسداد الكل. المبلغ المتبقي: ${remainingPayment.toFixed(2)} ج.م`,
//             confirmButtonText: 'حسناً'
//         });
//     }
    
//     this.validatePayment();
// },

// // تحديد كل الفواتير للدفع
// selectAllForPayment() {
//     document.querySelectorAll('.invoice-payment-checkbox').forEach(checkbox => {
//         checkbox.checked = true;
//         this.toggleInvoicePaymentInput(checkbox);
//     });
//     this.updateRequiredAmountAndValidate();
// },

// // تحديد الفواتير غير المرتبطة بشغلانة
// selectNonWorkOrderForPayment() {
//     document.querySelectorAll('.invoice-payment-checkbox').forEach(checkbox => {
//         const invoiceId = parseInt(checkbox.getAttribute('data-invoice-id'));
//         const invoice = AppData.invoices.find(i => i.id === invoiceId);
//         checkbox.checked = !invoice.workOrderId;
//         this.toggleInvoicePaymentInput(checkbox);
//     });
//     this.updateRequiredAmountAndValidate();
// },

// // تحديد كل فواتير الشغلانة
// selectAllWorkOrderInvoices() {
//     document.querySelectorAll('.workorder-invoice-payment-amount').forEach(input => {
//         const invoiceId = parseInt(input.getAttribute('data-invoice-id'));
//         const invoice = AppData.invoices.find(i => i.id === invoiceId);
//         if (invoice) {
//             input.value = invoice.remaining.toFixed(2);
//             input.dispatchEvent(new Event('input'));
//         }
//     });
//     this.updateRequiredAmountAndValidate();
// },

// // تفعيل/تعطيل حقل الإدخال للفاتورة
// toggleInvoicePaymentInput(checkbox) {
//     const invoiceId = checkbox.getAttribute('data-invoice-id');
//     const amountInput = document.querySelector(`.invoice-payment-amount[data-invoice-id="${invoiceId}"]`);
    
//     if (amountInput) {
//         amountInput.disabled = !checkbox.checked;
//         if (!checkbox.checked) {
//             amountInput.value = 0;
//         }
//         // عند التغيير في حالة الحقل (تفعيل/تعطيل) نحدث التحقق
//         PaymentManager.validatePayment();
//     }
// }
// };
// export default PaymentManager;
// import AppData from "./app_data.js";
// import InvoiceManager from "./invoices.js";
// import PrintManager from "./print.js";
// import WalletManager from "./wallet.js";

// const ReturnManager = {
//     init() {
//         // بيانات المرتجعات الابتدائية المحدثة
//         AppData.returns = [
//             {
//                 id: 1,
//                 number: "#RET-001",
//                 invoiceId: 120,
//                 invoiceNumber: "#120",
//                 type: "full",
//                 amount: 300,
//                 method: "wallet",
//                 status: "completed",
//                 date: "2024-01-05",
//                 reason: "شباك معيب",
//                 amountFromRemaining: 0,
//                 amountFromPaid: 300,
//                 originalPaymentMethod: "cash",
//                 items: [
//                     {
//                         productId: 1,
//                         productName: "شباك ألوميتال 2×1.5",
//                         quantity: 1,
//                         price: 300,
//                         total: 300,
//                         date: "2024-01-05"
//                     },
//                 ],
//                 createdBy: "مدير النظام",
//             },
//         ];

//         this.updateReturnsTable();
//     },

//     updateReturnsTable() {
//         const tbody = document.getElementById("returnsTableBody");
//         tbody.innerHTML = "";

//         AppData.returns.forEach((returnItem) => {
//             const row = document.createElement("tr");

//             // تحديد نوع المرتجع
//             let typeBadge = returnItem.type === "full"
//                 ? '<span class="badge bg-danger">كامل</span>'
//                 : '<span class="badge bg-warning">جزئي</span>';

//             // تحديد طريقة الاسترجاع
//             let methodBadge = "";
//             if (returnItem.method === "wallet") {
//                 methodBadge = '<span class="badge bg-info">محفظة</span>';
//             } else if (returnItem.method === "cash") {
//                 methodBadge = '<span class="badge bg-success">نقدي</span>';
//             } else if (returnItem.method === "credit_adjustment") {
//                 methodBadge = '<span class="badge bg-secondary">تعديل آجل</span>';
//             }

//             // تحديد حالة المرتجع
//             let statusBadge = returnItem.status === "completed"
//                 ? '<span class="status-badge badge-paid">مكتمل</span>'
//                 : '<span class="status-badge badge-pending">معلق</span>';

//             // عرض بنود المرتجع
//             let itemsList = "";
//             if (returnItem.items && returnItem.items.length > 0) {
//                 returnItem.items.forEach((item) => {
//                     itemsList += `<div class="d-flex justify-content-between small border-bottom pb-1 mb-1">
//                                     <span>${item.productName}</span>
//                                     <span>${item.quantity} × ${item.price.toFixed(2)} = ${item.total.toFixed(2)} ج.م</span>
//                                 </div>`;
//                 });
//             }

//             // عرض تفاصيل الدفع
//             let paymentDetails = "";
//             if (returnItem.amountFromRemaining > 0) {
//                 paymentDetails += `<div class="small text-muted">من المتبقي: ${returnItem.amountFromRemaining.toFixed(2)} ج.م</div>`;
//             }
//             if (returnItem.amountFromPaid > 0) {
//                 paymentDetails += `<div class="small text-muted">مرتجع: ${returnItem.amountFromPaid.toFixed(2)} ج.م</div>`;
//             }

//             row.innerHTML = `
//                 <td>
//                     <strong>${returnItem.number}</strong>
//                     <br>
//                     <button class="btn btn-sm btn-outline-info mt-1 view-original-invoice" data-invoice-id="${returnItem.invoiceId}">
//                         <i class="fas fa-external-link-alt"></i> عرض الفاتورة
//                     </button>
//                 </td>
//                 <td>
//                     <a href="#" class="text-decoration-none view-invoice-from-return" data-invoice-id="${returnItem.invoiceId}">
//                         ${returnItem.invoiceNumber}
//                     </a>
//                     <br>
//                     <small class="text-muted">${returnItem.reason}</small>
//                     ${paymentDetails}
//                 </td>
//                 <td>
//                     <div class="items-preview">
//                         ${itemsList}
//                     </div>
//                 </td>
//                 <td>
//                     ${returnItem.items ? returnItem.items.reduce((sum, i) => sum + i.quantity, 0) : 1}
//                 </td>
//                 <td>
//                     <div class="fw-bold">${returnItem.amount.toFixed(2)} ج.م</div>
//                     ${returnItem.originalPaymentMethod === "credit" ?
//                     '<small class="text-muted">(فاتورة آجلة)</small>' : ''}
//                 </td>
//                 <td>${methodBadge}</td>
//                 <td>${statusBadge}</td>
//                 <td>${returnItem.date}</td>
//                 <td>${returnItem.createdBy}</td>
//             `;

//             tbody.appendChild(row);
//         });

//         // إضافة مستمعي الأحداث لعرض الفاتورة الأصلية
//         document.querySelectorAll(".view-invoice-from-return, .view-original-invoice").forEach((btn) => {
//             btn.addEventListener("click", function (e) {
//                 e.preventDefault();
//                 const invoiceId = parseInt(this.getAttribute("data-invoice-id"));
//                 InvoiceManager.showInvoiceDetails(invoiceId);
//             });
//         });
//     },

//     getReturnsByInvoiceId(invoiceId) {
//         return AppData.returns.filter((r) => r.invoiceId === invoiceId);
//     },

//     addReturn(returnData) {
//         const newReturn = {
//             id: AppData.nextReturnId++,
//             number: `#RET-00${AppData.nextReturnId - 1}`,
//             invoiceId: returnData.invoiceId,
//             invoiceNumber: returnData.invoiceNumber,
//             type: returnData.type,
//             amount: returnData.amount,
//             method: returnData.method,
//             status: "completed",
//             date: new Date().toISOString().split("T")[0],
//             reason: returnData.reason,
//             items: returnData.items,
//             createdBy: AppData.currentUser,
//             amountFromRemaining: returnData.amountFromRemaining || 0,
//             amountFromPaid: returnData.amountFromPaid || 0,
//             originalPaymentMethod: returnData.originalPaymentMethod
//         };

//         AppData.returns.unshift(newReturn);

//         // إضافة حركة للمحفظة إذا كانت طريقة الاسترجاع للمحفظة وكان هناك مبلغ مدفوع
//         if (returnData.method === "wallet" && returnData.amountFromPaid > 0) {
//             WalletManager.addTransaction({
//                 type: "return",
//                 amount: returnData.amountFromPaid,
//                 description: `مرتجع ${returnData.type === "full" ? "كامل" : "جزئي"} لفاتورة ${returnData.invoiceNumber}`,
//                 date: newReturn.date,
//             });
//         }

//         this.updateReturnsTable();
//         return newReturn;
//     }
// };



// const CustomReturnManager = {
//     currentInvoiceId: null,
//     returnItems: [],

//     openReturnModal(invoiceId) {
//         this.currentInvoiceId = invoiceId;
//         this.returnItems = [];

//         const invoice = InvoiceManager.getInvoiceById(invoiceId);
//         if (!invoice) {
//             Swal.fire("خطأ", "الفاتورة غير موجودة", "error");
//             return;
//         }

//         // تعبئة معلومات الفاتورة
//         document.getElementById("returnInvoiceNumber").textContent = invoice.number;
//         document.getElementById("returnInvoiceDate").textContent = invoice.date;
//         document.getElementById("returnInvoiceTotal").textContent = invoice.total.toFixed(2) + " ج.م";

//         // عرض معلومات الدفع
//         document.getElementById("originalPaymentMethod").textContent =
//             invoice.paymentMethod === "credit" ? "آجل" :
//                 invoice.paymentMethod === "wallet" ? "محفظة" : "نقدي";

//         document.getElementById("paymentStatus").textContent =
//             invoice.paid === 0 ? "لم يدفع" :
//                 invoice.paid >= invoice.total ? "مدفوع بالكامل" : "مدفوع جزئياً";

//         document.getElementById("invoicePaidAmount").textContent = invoice.paid.toFixed(2) + " ج.م";
//         document.getElementById("invoiceRemainingAmount").textContent = invoice.remaining.toFixed(2) + " ج.م";

//         // تعبئة بنود الفاتورة
//         this.populateReturnItems(invoice);

//         // إضافة مستمعي الأحداث للأزرار
//         document.getElementById("returnAllBtn").onclick = () => this.returnAllItems();
//         document.getElementById("returnPartialBtn").onclick = () => this.returnPartialItems();
//         document.getElementById("processCustomReturnBtn").onclick = () => this.processReturn();

//         // فتح المودال
//         const modal = new bootstrap.Modal(document.getElementById("customReturnModal"));
//         modal.show();
//     },

//     populateReturnItems(invoice) {
//         const container = document.getElementById("customReturnItemsContainer");
//         container.innerHTML = "";

//         invoice.items.forEach((item, index) => {
//             const availableQuantity = item.quantity - (item.returnedQuantity || 0);

//             if (availableQuantity > 0 && !item.fullyReturned) {
//                 const itemElement = document.createElement("div");
//                 itemElement.className = "return-item-card border p-3 mb-3 rounded";
//                 itemElement.setAttribute("data-item-index", index);

//                 itemElement.innerHTML = `
//                     <div class="row align-items-center">
//                         <div class="col-md-3">
//                             <label class="form-label fw-bold">المنتج</label>
//                             <input type="text" class="form-control" value="${item.productName}" readonly>
//                             <div class="mt-1">
//                                 <small class="text-muted">متاح للإرجاع: ${availableQuantity}</small>
//                             </div>
//                         </div>
//                         <div class="col-md-2">
//                             <label class="form-label">الكمية الأصلية</label>
//                             <input type="number" class="form-control bg-light" value="${item.quantity}" readonly>
//                         </div>
//                         <div class="col-md-2">
//                             <label class="form-label">مرتجع سابق</label>
//                             <input type="number" class="form-control bg-warning text-white" 
//                                    value="${item.returnedQuantity || 0}" readonly>
//                         </div>
//                         <div class="col-md-2">
//                             <label class="form-label text-success">الكمية الحالية</label>
//                             <input type="number" class="form-control bg-success text-white" 
//                                    value="${availableQuantity}" readonly>
//                         </div>
//                         <div class="col-md-2">
//                             <label class="form-label text-primary">كمية الإرجاع</label>
//                             <input type="number" class="form-control custom-return-quantity border-primary" 
//                                    data-item-index="${index}" min="0" max="${availableQuantity}" 
//                                    value="0" data-max="${availableQuantity}" 
//                                    placeholder="أدخل الكمية">
//                         </div>
//                         <div class="col-md-1">
//                             <label class="form-label">الإجمالي</label>
//                             <input type="number" class="form-control custom-return-total bg-info text-white" 
//                                    data-item-index="${index}" value="0" readonly>
//                         </div>
//                     </div>
//                     <div class="validation-message mt-2" id="validation-${index}" style="display:none; color:red; font-size:12px;"></div>
//                 `;
//                 container.appendChild(itemElement);

//                 // إضافة مستمعي الأحداث مع التحقق
//                 const quantityInput = itemElement.querySelector(".custom-return-quantity");
//                 quantityInput.addEventListener("input", (e) => {
//                     this.validateReturnItem(index, e.target);
//                     this.updateReturnItem(index);
//                 });
//             }
//         });

//         this.updateReturnTotal();
//     },

//     validateReturnItem(itemIndex, inputElement) {
//         const value = parseFloat(inputElement.value) || 0;
//         const max = parseFloat(inputElement.getAttribute("data-max"));
//         const validationMessage = document.getElementById(`validation-${itemIndex}`);

//         if (value > max) {
//             validationMessage.textContent = `خطأ: لا يمكن إرجاع أكثر من ${max}`;
//             validationMessage.style.display = "block";
//             inputElement.style.borderColor = "red";
//             inputElement.classList.add("is-invalid");
//             inputElement.value = max;
//             this.updateReturnItem(itemIndex);
//             return false;
//         } else if (value < 0) {
//             validationMessage.textContent = "خطأ: القيمة يجب أن تكون موجبة";
//             validationMessage.style.display = "block";
//             inputElement.style.borderColor = "red";
//             inputElement.classList.add("is-invalid");
//             inputElement.value = 0;
//             this.updateReturnItem(itemIndex);
//             return false;
//         } else {
//             validationMessage.style.display = "none";
//             inputElement.style.borderColor = "";
//             inputElement.classList.remove("is-invalid");
//             return true;
//         }
//     },

//     updateReturnItem(itemIndex) {
//         const quantityInput = document.querySelector(`.custom-return-quantity[data-item-index="${itemIndex}"]`);
//         const totalInput = document.querySelector(`.custom-return-total[data-item-index="${itemIndex}"]`);

//         const quantity = parseFloat(quantityInput.value) || 0;
//         const invoice = InvoiceManager.getInvoiceById(this.currentInvoiceId);
//         const item = invoice.items[itemIndex];

//         const total = quantity * item.price;
//         totalInput.value = total.toFixed(2);

//         this.updateReturnTotal();
//     },

//     updateReturnTotal() {
//         let totalAmount = 0;
//         let hasErrors = false;

//         // جمع المبلغ الإجمالي للإرجاع
//         document.querySelectorAll(".custom-return-quantity").forEach((input) => {
//             const value = parseFloat(input.value) || 0;
//             const max = parseFloat(input.getAttribute("data-max"));

//             if (value > max) {
//                 hasErrors = true;
//             }

//             const itemIndex = parseInt(input.getAttribute("data-item-index"));
//             const invoice = InvoiceManager.getInvoiceById(this.currentInvoiceId);
//             const item = invoice.items[itemIndex];

//             totalAmount += value * item.price;
//         });

//         document.getElementById("customReturnTotalAmount").textContent = totalAmount.toFixed(2) + " ج.م";

//         // حساب التأثير المالي
//         if (totalAmount > 0 && !hasErrors) {
//             const invoice = InvoiceManager.getInvoiceById(this.currentInvoiceId);
//             const impact = this.calculateReturnImpact(invoice, totalAmount);

//             // حفظ النتائج في العناصر المخفية للاستخدام لاحقاً
//             document.getElementById("impactData").dataset.impact = JSON.stringify(impact);

//             // عرض التفاصيل
//             this.displayImpactDetails(impact, invoice);

//             // تفعيل زر المعالجة
//             document.getElementById("processCustomReturnBtn").disabled = false;
//         } else {
//             // إخفاء التفاصيل
//             document.getElementById("impactDetails").style.display = "none";
//             document.getElementById("refundMethodSection").style.display = "none";
//             document.getElementById("processCustomReturnBtn").disabled = true;
//         }
//     }
//     ,
//     displayImpactDetails(impact, invoice) {
//         const detailsContainer = document.getElementById("impactDetails");
//         detailsContainer.style.display = "block";

//         let detailsHTML = `
//         <div class="alert alert-info mb-2">
//             <i class="fas fa-calculator me-2"></i>
//             <strong>تفاصيل التأثير المالي:</strong>
//     `;

//         // عرض خصم من المتبقي
//         if (impact.amountFromRemaining > 0) {
//             detailsHTML += `
//             <div class="mt-1">
//                 <i class="fas fa-minus-circle text-warning me-1"></i>
//                 <strong>يخصم من المتبقي:</strong> ${impact.amountFromRemaining.toFixed(2)} ج.م
//                 ${impact.invoiceRemaining > 0 ?
//                     `(من ${impact.invoiceRemaining.toFixed(2)} ج.م)` : ''}
//             </div>
//         `;
//         }

//         // عرض رد للعميل
//         if (impact.amountFromPaid > 0) {
//             detailsHTML += `
//             <div class="mt-1">
//                 <i class="fas fa-undo text-success me-1"></i>
//                 <strong>يُرد للعميل:</strong> ${impact.amountFromPaid.toFixed(2)} ج.م
//                 ${impact.invoicePaid > 0 ?
//                     `(من ${impact.invoicePaid.toFixed(2)} ج.م مدفوع)` : ''}
//             </div>
//         `;
//         }

//         // عرض القيم الجديدة للفاتورة
//         detailsHTML += `
//         </div>
//         <div class="alert alert-warning mb-2">
//             <i class="fas fa-chart-line me-2"></i>
//             <strong>الفاتورة بعد الإرجاع:</strong>
//             <div class="row mt-2">
//                 <div class="col-4">
//                     <div class="small text-muted">الإجمالي الجديد</div>
//                     <div class="fw-bold">${impact.newTotal.toFixed(2)} ج.م</div>
//                 </div>
//                 <div class="col-4">
//                     <div class="small text-muted">المدفوع الجديد</div>
//                     <div class="fw-bold">${impact.newPaid.toFixed(2)} ج.م</div>
//                 </div>
//                 <div class="col-4">
//                     <div class="small text-muted">المتبقي الجديد</div>
//                     <div class="fw-bold">${impact.newRemaining.toFixed(2)} ج.م</div>
//                 </div>
//             </div>
//         </div>
//     `;

//         detailsContainer.innerHTML = detailsHTML;

//         // عرض قسم اختيار طريقة الرد إذا كان هناك مبلغ يرد
//         if (impact.amountFromPaid > 0) {
//             document.getElementById("refundMethodSection").style.display = "block";
//         } else {
//             document.getElementById("refundMethodSection").style.display = "none";
//         }
//     },

//     returnAllItems() {
//         const invoice = InvoiceManager.getInvoiceById(this.currentInvoiceId);
//         document.querySelectorAll(".custom-return-quantity").forEach((input) => {
//             const itemIndex = parseInt(input.getAttribute("data-item-index"));
//             const availableQuantity = parseFloat(input.getAttribute("data-max"));
//             input.value = availableQuantity;
//             this.validateReturnItem(itemIndex, input);
//             this.updateReturnItem(itemIndex);
//         });
//     },

//     returnPartialItems() {
//         document.querySelectorAll(".custom-return-quantity").forEach((input) => {
//             input.disabled = false;
//             input.focus();
//         });
//     },
//     // في CustomReturnManager
//     calculateReturnImpact(invoice, totalReturnAmount) {
//         let amountFromRemaining = 0;
//         let amountFromPaid = 0;
//         let refundMethod = "credit_adjustment";
//         let description = "";

//         // السيناريو 1: الفاتورة نقدية (cash أو wallet)
//         if (invoice.paymentMethod === "cash" || invoice.paymentMethod === "wallet") {
//             // نقدي أو محفظة: العميل دفع بالفعل
//             if (totalReturnAmount <= invoice.paid) {
//                 // المرتجع يغطيه المبلغ المدفوع
//                 amountFromRemaining = 0;
//                 amountFromPaid = totalReturnAmount;
//                 refundMethod = invoice.paymentMethod === "wallet" ? "wallet" : "cash";
//                 description = `يُرد للعميل: ${amountFromPaid.toFixed(2)} ج.م ${refundMethod === "wallet" ? "إلى المحفظة" : "نقداً"}`;
//             } else {
//                 // المرتجع أكبر من المدفوع (سيناريو نادر)
//                 amountFromRemaining = 0;
//                 amountFromPaid = invoice.paid; // الحد الأقصى للرد
//                 refundMethod = invoice.paymentMethod === "wallet" ? "wallet" : "cash";
//                 description = `يُرد للعميل: ${amountFromPaid.toFixed(2)} ج.م ${refundMethod === "wallet" ? "إلى المحفظة" : "نقداً"} (الحد الأقصى للمدفوع)`;
//             }
//         }
//         // السيناريو 2: الفاتورة آجلة (credit)
//         else if (invoice.paymentMethod === "credit") {
//             // آجل: لدينا حالتين - مدفوع جزئياً أو غير مدفوع
//             if (invoice.paid > 0) {
//                 // دفع جزئي
//                 if (totalReturnAmount <= invoice.remaining) {
//                     // الحالة 1: المرتجع يغطيه المتبقي فقط
//                     amountFromRemaining = totalReturnAmount;
//                     amountFromPaid = 0;
//                     refundMethod = "credit_adjustment";
//                     description = `يخصم من المتبقي: ${amountFromRemaining.toFixed(2)} ج.م`;
//                 } else {
//                     // الحالة 2: المرتجع أكبر من المتبقي
//                     amountFromRemaining = invoice.remaining;
//                     amountFromPaid = totalReturnAmount - invoice.remaining;

//                     // تأكد أن الرد لا يتجاوز المدفوع
//                     if (amountFromPaid > invoice.paid) {
//                         amountFromPaid = invoice.paid;
//                     }

//                     // عرض اختيار طريقة الرد (سيعرض في مودال اختياري لاحقاً)
//                     refundMethod = "pending_choice"; // يحتاج اختيار
//                     description = `يخصم من المتبقي: ${amountFromRemaining.toFixed(2)} ج.م + يُرد: ${amountFromPaid.toFixed(2)} ج.م (يحتاج اختيار طريقة)`;
//                 }
//             } else {
//                 // لم يدفع أي شيء
//                 amountFromRemaining = totalReturnAmount;
//                 amountFromPaid = 0;
//                 refundMethod = "credit_adjustment";
//                 description = `يخصم من المتبقي: ${amountFromRemaining.toFixed(2)} ج.م`;
//             }
//         }

//         // حساب القيم الجديدة للفاتورة
//         const newTotal = invoice.total - totalReturnAmount;
//         const newPaid = invoice.paid - amountFromPaid;
//         const newRemaining = newTotal - newPaid;

//         return {
//             amountFromRemaining,
//             amountFromPaid,
//             refundMethod,
//             description,
//             newTotal,
//             newPaid,
//             newRemaining,
//             invoicePaid: invoice.paid,
//             invoiceRemaining: invoice.remaining,
//             invoiceTotal: invoice.total
//         };
//     },
//     processReturn() {
//         // التحقق من صحة البيانات المدخلة
//         let hasErrors = false;
//         document.querySelectorAll(".custom-return-quantity").forEach((input) => {
//             const itemIndex = input.getAttribute("data-item-index");
//             if (!this.validateReturnItem(itemIndex, input)) {
//                 hasErrors = true;
//             }
//         });

//         if (hasErrors) {
//             Swal.fire("تحذير", "يوجد أخطاء في الكميات المدخلة، يرجى تصحيحها أولاً", "warning");
//             return;
//         }

//         const returnReason = document.getElementById("customReturnReason").value.trim();
//         if (!returnReason) {
//             Swal.fire("تحذير", "يرجى إدخال سبب الإرجاع", "warning");
//             return;
//         }

//         const invoice = InvoiceManager.getInvoiceById(this.currentInvoiceId);
//         const returnItems = [];
//         let totalReturnAmount = 0;
//         let isFullReturn = true;

//         // جمع البنود المراد إرجاعها
//         document.querySelectorAll(".custom-return-quantity").forEach((input) => {
//             const itemIndex = parseInt(input.getAttribute("data-item-index"));
//             const quantity = parseFloat(input.value) || 0;

//             if (quantity > 0) {
//                 const item = invoice.items[itemIndex];
//                 const total = quantity * item.price;

//                 returnItems.push({
//                     productId: item.productId,
//                     productName: item.productName,
//                     quantity: quantity,
//                     price: item.price,
//                     total: total,
//                     date: new Date().toISOString().split('T')[0]
//                 });

//                 totalReturnAmount += total;

//                 const availableQuantity = item.quantity - (item.returnedQuantity || 0);
//                 if (quantity < availableQuantity) {
//                     isFullReturn = false;
//                 }
//             }
//         });

//         if (returnItems.length === 0) {
//             Swal.fire("تحذير", "لم يتم تحديد أي كميات للإرجاع", "warning");
//             return;
//         }

//         // حساب التأثير المالي
//         const impact = this.calculateReturnImpact(invoice, totalReturnAmount);

//         // عرض مودال اختيار طريقة الرد إذا لزم الأمر
//         if (impact.amountFromPaid > 0 && impact.refundMethod === "pending_choice") {
//             this.showRefundMethodModal(impact, invoice, returnItems, totalReturnAmount, isFullReturn, returnReason);
//         } else {
//             // إذا لم يكن هناك حاجة لاختيار طريقة، أكمل مباشرة
//             this.confirmReturnProcess(impact, invoice, returnItems, totalReturnAmount, isFullReturn, returnReason);
//         }
//     },

//     showRefundMethodModal(impact, invoice, returnItems, totalReturnAmount, isFullReturn, returnReason) {
//         Swal.fire({
//             title: "اختر طريقة رد المبلغ",
//             html: `
//             <div class="text-start">
//                 <div class="alert alert-info mb-3">
//                     <i class="fas fa-money-bill-wave me-2"></i>
//                     <strong>تفاصيل المبلغ المراد رده:</strong>
//                     <div class="mt-2">
//                         <div><strong>المبلغ:</strong> ${impact.amountFromPaid.toFixed(2)} ج.م</div>
//                         <div><strong>سبب:</strong> الفاتورة كانت مدفوعة جزئياً والمتبقي لا يكفي لتغطية المرتجع</div>
//                     </div>
//                 </div>
                
//                 <div class="form-group">
//                     <label class="form-label fw-bold">طريقة رد المبلغ:</label>
//                     <div class="mt-2">
//                         <div class="form-check">
//                             <input class="form-check-input" type="radio" name="refundMethodChoice" id="cashChoice" value="cash" checked>
//                             <label class="form-check-label" for="cashChoice">
//                                 <i class="fas fa-money-bill-wave me-1"></i> استرجاع نقدي
//                             </label>
//                         </div>
//                         <div class="form-check mt-2">
//                             <input class="form-check-input" type="radio" name="refundMethodChoice" id="walletChoice" value="wallet">
//                             <label class="form-check-label" for="walletChoice">
//                                 <i class="fas fa-wallet me-1"></i> إضافة للمحفظة
//                             </label>
//                         </div>
//                     </div>
//                 </div>
//             </div>
//         `,
//             icon: "question",
//             showCancelButton: true,
//             confirmButtonText: "متابعة",
//             cancelButtonText: "إلغاء",
//             confirmButtonColor: "#3085d6",
//             cancelButtonColor: "#d33",
//             width: 500,
//             preConfirm: () => {
//                 const selected = document.querySelector('input[name="refundMethodChoice"]:checked');
//                 if (!selected) {
//                     Swal.showValidationMessage("يرجى اختيار طريقة رد المبلغ");
//                     return false;
//                 }
//                 return selected.value;
//             }
//         }).then((result) => {
//             if (result.isConfirmed) {
//                 const refundMethod = result.value;

//                 // تحديث الـ impact بطريقة الرد المختارة
//                 impact.refundMethod = refundMethod;

//                 // متابعة عملية التأكيد
//                 this.confirmReturnProcess(impact, invoice, returnItems, totalReturnAmount, isFullReturn, returnReason);
//             }
//         });
//     },

//     confirmReturnProcess(impact, invoice, returnItems, totalReturnAmount, isFullReturn, returnReason) {
//         // إنشاء رسالة التأكيد
//         let confirmMessage = `
//         <div class="text-start">
//             <h5 class="mb-3">تأكيد عملية الإرجاع</h5>
//             <div class="alert alert-info">
//                 <i class="fas fa-file-invoice me-2"></i>
//                 <strong>تفاصيل الفاتورة:</strong> ${invoice.number}
//             </div>
            
//             <div class="alert alert-warning">
//                 <i class="fas fa-undo me-2"></i>
//                 <strong>تفاصيل المرتجع:</strong>
//                 <div class="mt-2">
//                     <div><strong>المبلغ الإجمالي:</strong> ${totalReturnAmount.toFixed(2)} ج.م</div>
//                     <div><strong>عدد المنتجات:</strong> ${returnItems.length}</div>
//                     <div><strong>النوع:</strong> ${isFullReturn ? "إرجاع كلي" : "إرجاع جزئي"}</div>
//                     <div><strong>السبب:</strong> ${returnReason}</div>
//                 </div>
//             </div>
            
//             <div class="alert alert-success">
//                 <i class="fas fa-exchange-alt me-2"></i>
//                 <strong>التأثير المالي:</strong>
//                 <div class="mt-2">
//     `;

//         if (impact.amountFromRemaining > 0) {
//             confirmMessage += `
//             <div><strong>يخصم من المتبقي:</strong> ${impact.amountFromRemaining.toFixed(2)} ج.م</div>
//         `;
//         }

//         if (impact.amountFromPaid > 0) {
//             const methodText = impact.refundMethod === "wallet" ? "إلى المحفظة" : "نقداً";
//             confirmMessage += `
//             <div><strong>يُرد للعميل:</strong> ${impact.amountFromPaid.toFixed(2)} ج.م ${methodText}</div>
//         `;
//         }

//         confirmMessage += `
//                 </div>
//             </div>
            
//             <div class="alert alert-primary">
//                 <i class="fas fa-chart-line me-2"></i>
//                 <strong>الفاتورة بعد الإرجاع:</strong>
//                 <div class="row mt-2">
//                     <div class="col-4">
//                         <div class="small">الإجمالي</div>
//                         <div class="fw-bold">${impact.newTotal.toFixed(2)} ج.م</div>
//                     </div>
//                     <div class="col-4">
//                         <div class="small">المدفوع</div>
//                         <div class="fw-bold">${impact.newPaid.toFixed(2)} ج.م</div>
//                     </div>
//                     <div class="col-4">
//                         <div class="small">المتبقي</div>
//                         <div class="fw-bold">${impact.newRemaining.toFixed(2)} ج.م</div>
//                     </div>
//                 </div>
//             </div>
//         </div>
//     `;

//         // عرض مودال التأكيد النهائي
//         Swal.fire({
//             title: "التأكيد النهائي",
//             html: confirmMessage,
//             icon: "question",
//             showCancelButton: true,
//             confirmButtonText: "نعم، تنفيذ الإرجاع",
//             cancelButtonText: "إلغاء",
//             confirmButtonColor: "#3085d6",
//             cancelButtonColor: "#d33",
//             width: 600
//         }).then((result) => {
//             if (result.isConfirmed) {
//                 this.executeReturn(invoice, returnItems, totalReturnAmount, isFullReturn, returnReason, impact);
//             }
//         });
//     },

//     executeReturn(invoice, returnItems, totalReturnAmount, isFullReturn, returnReason, impact) {
//         // إنشاء سجل المرتجع
//         const returnData = {
//             invoiceId: invoice.id,
//             invoiceNumber: invoice.number,
//             type: isFullReturn ? "full" : "partial",
//             amount: totalReturnAmount,
//             method: impact.refundMethod,
//             reason: returnReason,
//             items: returnItems,
//             amountFromRemaining: impact.amountFromRemaining,
//             amountFromPaid: impact.amountFromPaid,
//             newTotal: impact.newTotal,
//             newPaid: impact.newPaid,
//             newRemaining: impact.newRemaining,
//             originalPaymentMethod: invoice.paymentMethod
//         };

//         // إضافة المرتجع
//         const returnItem = ReturnManager.addReturn(returnData);

//         // تحديث الفاتورة
//         InvoiceManager.updateInvoiceAfterReturn(invoice.id, returnData);

//         // عرض رسالة النجاح
//         const toast = Swal.mixin({
//             toast: true,
//             position: 'top-end',
//             showConfirmButton: false,
//             timer: 3000,
//             timerProgressBar: true,
//             didOpen: (toast) => {
//                 toast.addEventListener('mouseenter', Swal.stopTimer);
//                 toast.addEventListener('mouseleave', Swal.resumeTimer);
//             }
//         });

//         toast.fire({
//             icon: 'success',
//             title: `تم معالجة المرتجع ${returnItem.number} بنجاح`
//         });

//         // إغلاق المودال
//         const modal = bootstrap.Modal.getInstance(document.getElementById("customReturnModal"));
//         modal.hide();

//         // إعادة تعيين الحقول
//         document.getElementById("customReturnReason").value = "";
//     },

//     showInvoiceDetails(invoiceId) {
//         const invoice = AppData.invoices.find((i) => i.id === invoiceId);
//         if (invoice) {
//             document.getElementById("invoiceItemsNumber").textContent = invoice.number;
//             document.getElementById("invoiceItemsDate").textContent = invoice.date + " - " + invoice.time;
//             document.getElementById("invoiceItemsStatus").textContent = this.getInvoiceStatusText(invoice.status);
//             document.getElementById("invoiceItemsTotal").textContent = invoice.total.toFixed(2) + " ج.م";
//             document.getElementById("invoiceItemsPaid").textContent = invoice.paid.toFixed(2) + " ج.م";
//             document.getElementById("invoiceItemsRemaining").textContent = invoice.remaining.toFixed(2) + " ج.م";
//             document.getElementById("invoiceItemsNotes").textContent = invoice.description || "لا يوجد";

//             // عرض اسم الشغلانة إذا كانت مرتبطة
//             let workOrderName = "لا يوجد";
//             if (invoice.workOrderId) {
//                 const workOrder = AppData.workOrders.find((wo) => wo.id === invoice.workOrderId);
//                 if (workOrder) {
//                     workOrderName = workOrder.name;
//                 }
//             }
//             document.getElementById("invoiceItemsWorkOrder").textContent = workOrderName;

//             // التحقق من وجود مرتجعات
//             const hasReturns = AppData.returns.some((r) => r.invoiceId === invoiceId);
//             if (hasReturns) {
//                 document.getElementById("invoiceReturnsSection").style.display = "block";
//                 document.getElementById("viewInvoiceReturns").addEventListener("click", function (e) {
//                     e.preventDefault();
//                     CustomReturnManager.showInvoiceReturns(invoiceId);
//                 });
//             } else {
//                 document.getElementById("invoiceReturnsSection").style.display = "none";
//             }

//             const tbody = document.getElementById("invoiceItemsDetails");
//             tbody.innerHTML = "";

//             invoice.items.forEach((item) => {
//                 const row = document.createElement("tr");

//                 // حساب الكميات الأصلية والحالية
//                 const originalQuantity = item.quantity;
//                 const returnedQuantity = item.returnedQuantity || 0;
//                 const currentQuantity = item.currentQuantity || (originalQuantity - returnedQuantity);

//                 // حساب الإجماليات الأصلية والحالية
//                 const originalTotal = originalQuantity * item.price;
//                 const currentTotal = item.currentTotal || (currentQuantity * item.price);

//                 // عرض تاريخ المرتجع الأخير
//                 let lastReturnInfo = "";
//                 if (returnedQuantity > 0) {
//                     const lastReturn = AppData.returns
//                         .filter(r => r.invoiceId === invoiceId)
//                         .map(r => r.items.find(i => i.productId === item.productId))
//                         .filter(i => i)
//                         .sort((a, b) => new Date(b.date) - new Date(a.date))[0];

//                     if (lastReturn) {
//                         lastReturnInfo = `<br><small class="text-muted">آخر إرجاع: ${lastReturn.quantity} بتاريخ ${lastReturn.date}</small>`;
//                     }
//                 }

//                 let itemStatus = "";
//                 let rowClass = "";
//                 if (item.fullyReturned) {
//                     itemStatus = '<span class="badge bg-danger">مرتجع كلي</span>';
//                     rowClass = "fully-returned";
//                 } else if (returnedQuantity > 0) {
//                     itemStatus = '<span class="badge bg-warning">مرتجع جزئي</span>';
//                     rowClass = "partially-returned";
//                 } else {
//                     itemStatus = '<span class="badge bg-success">سليم</span>';
//                 }

//                 row.className = rowClass;
//                 row.innerHTML = `
//                 <td>
//                     <strong>${item.productName}</strong>
//                     ${lastReturnInfo}
//                     ${returnedQuantity > 0 ?
//                         `<div class="mt-1">
//                             <span class="badge bg-warning return-history-badge">
//                                 مرتجع: ${returnedQuantity}
//                             </span>
//                         </div>` :
//                         ''}
//                 </td>
//                 <td>
//                     <div class="d-flex flex-column">
//                         <span class="text-muted small">أصلي: ${originalQuantity}</span>
//                         <span class="fw-bold mt-1">حالي: ${currentQuantity}</span>
//                     </div>
//                 </td>
//                 <td>
//                     <div class="fw-bold">${item.price.toFixed(2)} ج.م</div>
//                 </td>
//                 <td>
//                     <div class="d-flex flex-column">
//                         <span class="text-muted small" style="text-decoration: line-through;">
//                             ${originalTotal.toFixed(2)} ج.م
//                         </span>
//                         <span class="fw-bold mt-1">
//                             ${currentTotal.toFixed(2)} ج.م
//                         </span>
//                     </div>
//                 </td>
//                 <td>
//                     <div class="fw-bold text-warning">${returnedQuantity}</div>
//                     ${returnedQuantity > 0 ?
//                         `<div class="small text-muted">باقي: ${originalQuantity - returnedQuantity}</div>` :
//                         ''}
//                 </td>
//                 <td>${itemStatus}</td>
//             `;
//                 tbody.appendChild(row);
//             });

//             // إضافة مستمع حدث لزر الطباعة
//             document.getElementById("printInvoiceItemsBtn").onclick = () => {
//                 this.printInvoiceDetails(invoiceId);
//             };

//             const modal = new bootstrap.Modal(document.getElementById("invoiceItemsModal"));
//             modal.show();
//         }
//     },

//     printInvoiceReturns(invoiceId) {
//         const invoice = InvoiceManager.getInvoiceById(invoiceId);
//         const returns = ReturnManager.getReturnsByInvoiceId(invoiceId);

//         if (returns.length === 0) {
//             Swal.fire("تنبيه", "لا توجد مرتجعات لهذه الفاتورة", "info");
//             return;
//         }

//         const report = {
//             invoicesCount: 1,
//             items: [],
//             totals: {
//                 beforeDiscount: 0,
//                 afterDiscount: 0,
//                 discount: 0,
//             },
//             invoices: [
//                 {
//                     id: invoice.id,
//                     customer: AppData.currentCustomer.name,
//                     total: returns.reduce((sum, r) => sum + r.amount, 0),
//                 },
//             ],
//         };

//         // تجميع بنود المرتجعات
//         returns.forEach((returnItem) => {
//             returnItem.items.forEach((item) => {
//                 report.items.push({
//                     name: item.productName,
//                     quantity: item.quantity,
//                     price: item.price,
//                     total: item.total,
//                 });
//             });
//         });

//         report.totals.beforeDiscount = report.invoices[0].total;
//         report.totals.afterDiscount = report.invoices[0].total;

//         // استخدام دالة الطباعة المجمعة
//         printAggregatedReport(report);
//     },

//     showInvoiceReturns(invoiceId) {
//         const invoice = InvoiceManager.getInvoiceById(invoiceId);
//         if (!invoice) return;

//         const returns = ReturnManager.getReturnsByInvoiceId(invoiceId);

//         document.getElementById("returnsInvoiceNumber").textContent =
//             invoice.number;

//         // حساب إجمالي المرتجعات
//         const totalReturns = returns.reduce((sum, r) => sum + r.amount, 0);
//         document.getElementById("totalReturnsAmountForInvoice").textContent =
//             totalReturns.toFixed(2) + " ج.م";

//         // عرض قائمة المرتجعات
//         const container = document.getElementById("invoiceReturnsList");
//         container.innerHTML = "";

//         if (returns.length === 0) {
//             container.innerHTML =
//                 '<div class="alert alert-info">لا توجد مرتجعات لهذه الفاتورة</div>';
//         } else {
//             returns.forEach((returnItem) => {
//                 const returnElement = document.createElement("div");
//                 returnElement.className = "return-item";

//                 let itemsHTML = "";
//                 returnItem.items.forEach((item) => {
//                     itemsHTML += `
//                                 <div class="d-flex justify-content-between">
//                                     <span>${item.productName}</span>
//                                     <span>${item.quantity
//                         } × ${item.price.toFixed(
//                             2
//                         )} = ${item.total.toFixed(2)} ج.م</span>
//                                 </div>
//                             `;
//                 });

//                 returnElement.innerHTML = `
//                             <div class="d-flex justify-content-between align-items-start mb-2">
//                                 <div>
//                                     <strong>${returnItem.number}</strong>
//                                     <br>
//                                     <small class="text-muted">${returnItem.date
//                     } - ${returnItem.reason}</small>
//                                 </div>
//                                 <div>
//                                     <span class="badge ${returnItem.type === "full"
//                         ? "bg-danger"
//                         : "bg-warning"
//                     }">
//                                         ${returnItem.type === "full"
//                         ? "إرجاع كلي"
//                         : "إرجاع جزئي"
//                     }
//                                     </span>
//                                     <span class="badge ${returnItem.method === "wallet"
//                         ? "bg-info"
//                         : "bg-success"
//                     } ms-1">
//                                         ${returnItem.method === "wallet"
//                         ? "محفظة"
//                         : "نقدي"
//                     }
//                                     </span>
//                                 </div>
//                             </div>
//                             <div class="mt-2">
//                                 ${itemsHTML}
//                             </div>
//                             <div class="d-flex justify-content-between mt-2 fw-bold">
//                                 <span>المبلغ الإجمالي:</span>
//                                 <span>${returnItem.amount.toFixed(2)} ج.م</span>
//                             </div>
//                         `;

//                 container.appendChild(returnElement);
//             });
//         }

//         // إضافة مستمع حدث لزر الطباعة
//         document.getElementById("printInvoiceReturnsBtn").onclick = () => {
//             this.printInvoiceReturns(invoiceId);
//         };

//         const modal = new bootstrap.Modal(
//             document.getElementById("invoiceReturnsModal")
//         );
//         modal.show();
//     },

// };


// export { ReturnManager, CustomReturnManager };
//      import AppData from './app_data.js';
//      import CustomerManager from './customer.js';
//      const WalletManager = {
//         init() {
//           // بيانات حركات المحفظة الابتدائية
//           AppData.walletTransactions = [
//             {
//               id: 1,
//               date: "2024-01-18",
//               type: "deposit",
//               description: "إيداع نقدي",
//               amount: 200,
//               balanceBefore: 0,
//               balanceAfter: 200,
//               createdBy: "مدير النظام",
//             },
//             {
//               id: 2,
//               date: "2024-01-15",
//               type: "payment",
//               description: "سداد فاتورة #123",
//               amount: -500,
//               balanceBefore: 700,
//               balanceAfter: 200,
//               createdBy: "مدير النظام",
//             },
//             {
//               id: 3,
//               date: "2024-01-10",
//               type: "deposit",
//               description: "إيداع نقدي",
//               amount: 500,
//               balanceBefore: 200,
//               balanceAfter: 700,
//               createdBy: "مدير النظام",
//             },
//             {
//               id: 4,
//               date: "2024-01-05",
//               type: "return",
//               description: "مرتجع فاتورة #120",
//               amount: 300,
//               balanceBefore: 200,
//               balanceAfter: 500,
//               createdBy: "مدير النظام",
//             },
//             {
//               id: 5,
//               date: "2024-01-01",
//               type: "deposit",
//               description: "إيداع نقدي",
//               amount: 200,
//               balanceBefore: 0,
//               balanceAfter: 200,
//               createdBy: "مدير النظام",
//             },
//           ];

//           this.updateWalletTable();
//         },

//         updateWalletTable() {
//           const tbody = document.getElementById("walletTableBody");
//           tbody.innerHTML = "";

//           AppData.walletTransactions.forEach((transaction) => {
//             const row = document.createElement("tr");

//             // تحديد لون البادج بناءً على نوع الحركة
//             let badgeClass = "bg-secondary";
//             if (transaction.type === "payment") {
//               badgeClass = "bg-danger";
//             } else if (transaction.type === "deposit") {
//               badgeClass = "bg-success";
//             } else if (transaction.type === "return") {
//               badgeClass = "bg-warning";
//             }

//             // تحديد لون المبلغ
//             let amountClass =
//               transaction.amount > 0 ? "text-success" : "text-danger";
//             let amountSign = transaction.amount > 0 ? "+" : "";

//             row.innerHTML = `
//                         <td>${transaction.date}</td>
//                         <td><span class="badge ${badgeClass}">${this.getTransactionTypeText(
//               transaction.type
//             )}</span></td>
//                         <td>${transaction.description}</td>
//                         <td class="${amountClass}">${amountSign}${transaction.amount.toFixed(
//               2
//             )} ج.م</td>
//                         <td>${transaction.balanceBefore.toFixed(2)} ج.م</td>
//                         <td>${transaction.balanceAfter.toFixed(2)} ج.م</td>
//                         <td>${transaction.createdBy}</td>
//                     `;

//             tbody.appendChild(row);
//           });
//         },

//         getTransactionTypeText(type) {
//           const typeMap = {
//             payment: "سداد",
//             deposit: "إيداع",
//             return: "مرتجع",
//           };
//           return typeMap[type] || type;
//         },

//         addTransaction(transactionData) {
//           const lastTransaction = AppData.walletTransactions[0];
//           const balanceBefore = lastTransaction
//             ? lastTransaction.balanceAfter
//             : AppData.currentCustomer.walletBalance;

//           const newTransaction = {
//             id: AppData.nextWalletTransactionId++,
//             date:
//               transactionData.date || new Date().toISOString().split("T")[0],
//             type: transactionData.type,
//             description: transactionData.description,
//             amount: transactionData.amount,
//             balanceBefore: balanceBefore,
//             balanceAfter: balanceBefore + transactionData.amount,
//             paymentMethods: transactionData.paymentMethods,
//             createdBy: AppData.currentUser,
//           };

//           AppData.walletTransactions.unshift(newTransaction);

//           // تحديث رصيد المحفظة
//           AppData.currentCustomer.walletBalance += transactionData.amount;

//           this.updateWalletTable();
//           CustomerManager.updateCustomerInfo();

//           return newTransaction;
//         },

//         getAvailableBalance() {
//           return AppData.currentCustomer.walletBalance;
//         }

//         getStatementTransactions(dateFrom, dateTo) {
//           let transactions = [...AppData.walletTransactions];

//           if (dateFrom) {
//             transactions = transactions.filter((t) => t.date >= dateFrom);
//           }

//           if (dateTo) {
//             transactions = transactions.filter((t) => t.date <= dateTo);
//           }

//           return transactions;
//         },
//       };
//         export default WalletManager;