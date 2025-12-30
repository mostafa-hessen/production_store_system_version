 import AppData from "./app_data.js";
import { escapeHtml, setupNumberInputPrevention, splitDateTime } from "./helper.js";
import CustomerTransactionManager from "./transaction.js";
import WalletManager from "./wallet.js";
import PaymentManager from "./payment.js";
import WorkOrderManager from "./work_order.js";
import { ReturnManager } from "./return.js";
import InvoiceManager from "./invoices.js";
import PrintManager from "./print.js";
 const UIManager = {
        init() {
          this.setupEventListeners();
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
                this.searchProductsInInvoices(searchTerm);
                AppData.activeFilters.productSearch = searchTerm;
                this.applyFilters();
                this.displayAdvancedSearchResults(results);
              // يمكن إضافة وظيفة البحث هنا إذا لزم الأمر
            });

          // زر حفظ الشغلانة
          document
            .getElementById("saveWorkOrderBtn")
            .addEventListener("click", () => {
              WorkOrderManager.handleCreateWorkOrder();
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

            document.getElementById("printReturnBtn").addEventListener("click", () => {
              PrintManager.printReturn(AppData.currentReturn);
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

          const transactions = CustomerTransactionManager.getStatementTransactions(
            dateFrom,
            dateTo
          );
          const tbody = document.getElementById("statementTableBody");
          tbody.innerHTML = "";

          let currentBalance = 0;

        (AppData.customerTransactions);


          transactions.forEach((transaction) => {
            const row = document.createElement("tr");
            (transaction);


            // تحديد لون المبلغ
            let amountClass =
              transaction.amount > 0 ? "text-success" : "text-danger";
            let amountSign = transaction.amount > 0 ? "+" : "";
 const { date: createdDate, time: createdTime } = splitDateTime(transaction.created_at);
            const { date: transactionDate, time: transactionTime } = splitDateTime(transaction.transaction_date);

            
            row.innerHTML = `

                <td>
                    <div class="fw-semibold">${createdDate}</div>
                    <small class="text-muted">${createdTime}</small>
                    
                </td>
                 <td>
                    <div class="fw-semibold">${transactionDate}</div>
                    <small class="text-muted">${transactionTime}</small>
                </td>
                <td>
                    <span class="badge ${transaction.badge_class}">
                        ${transaction.type_text}
                    </span>
                </td>
                <td>
                    <div>${transaction.description}</div>
                </td>
                <td class="${transaction.amount_class} fw-bold">
                     ${transaction.formatted_amount}
                </td>
                <td>
                    <div class="text-center">
                        <div class="fw-semibold">${transaction.wallet_before.toFixed(2)} ج.م</div>
                        <small class="text-muted d-block">المحفظة قبل</small>
                    </div>
                </td>
                <td>
                    <div class="text-center">
                        <div class="fw-semibold">${transaction.wallet_after.toFixed(2)} ج.م</div>
                        <small class="text-muted d-block">المحفظة بعد</small>
                    </div>
                </td>
                <td>
                    <div class="text-center">
                        <div class="fw-semibold ${transaction.balance_before >= 0 ? 'text-danger' : 'text-success'}">
                            ${Math.abs(transaction.balance_before || 0).toFixed(2)} ج.م
                        </div>
                        <small class="text-muted d-block">الديون قبل</small>
                    </div>
                </td>
                <td>
                    <div class="text-center">
                        <div class="fw-semibold ${transaction.balance_after >= 0 ? 'text-danger' : 'text-success'}">
                            ${Math.abs(transaction.balance_after || 0).toFixed(2)} ج.م
                        </div>
                        <small class="text-muted d-block">الديون بعد</small>
                    </div>
                </td>
                <td>
                    <div>${transaction.created_by}</div>
                </td>

            `;

            tbody.appendChild(row);
            currentBalance = transaction.balanceAfter;
          });

          // إذا لم توجد حركات، إظهار رسالة
          if (transactions.length === 0) {
            const row = document.createElement("tr");
            row.innerHTML = `<td colspan="12" class="text-center text-muted">لا توجد حركات في الفترة المحددة</td>`;
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
              highlighted = highlighted?.replace(
                regex,
                '<span class="search-highlight">$1</span>'
              );
            });

            return highlighted;
          };
        results.forEach((result) => {
          console.log(result);
          
    const div = document.createElement("div");
    div.className = "search-result-item";
    div.style.cursor = "pointer";

    const highlightedProductName = highlightText(
        result.productName,
        result.searchTerms || []
    );

    const highlightedNotes = result.notes
        ? highlightText(result.notes, result.searchTerms || [])
        : "";

 // دالة بسيطة لاختيار لون حسب نوع الفاتورة


// مثال على innerHTML مع عرض حالة الفاتورة
div.innerHTML = `
    <div class="fw-bold">${highlightedProductName}</div>
    <div class="small text-muted">
        الفاتورة: <strong>${result.invoiceNumber}</strong> (${result.invoiceDate}) | 
        الحالة: ${this.getStatusBadge(result.status)} |
        الكمية: ${result.soldQuantity} | 
        ${
          result.availableQuantity > 0
            ? `المتاح للإرجاع: ${result.availableQuantity} | `
            : '<span class="text-danger">غير متاح للإرجاع</span> | '
        }
    </div>
    ${
      highlightedNotes
        ? `<div class="small text-warning">ملاحظات: ${highlightedNotes}</div>`
        : ""
    }
`;


    div.addEventListener("click", () => {
        // عرض الفاتورة في اللوحة الجانبية
        UIManager.showInvoiceInRightPanel(result.invoiceId);

        container.style.display = "none";
        document.getElementById("advancedProductSearch").value = "";
    });

    container.appendChild(div);


      


   
            div.addEventListener("click", () => {
              // عرض الفاتورة في الجانب الأيمن
              this.showInvoiceInRightPanel(result.invoiceId);

              container.style.display = "none";
              document.getElementById("advancedProductSearch").value = "";
            });

            container.appendChild(div);
          });
        },
         getStatusBadge(status) {
    switch (status) {
        case 'pending':
            return `<span class="badge bg-secondary">قيد الانتظار</span>`;
        case 'partial':
            return `<span class="badge bg-warning text-dark">مدفوع جزئيًا</span>`;
        case 'paid':
            return `<span class="badge bg-success">مدفوع بالكامل</span>`;
        case 'returned':
            return `<span class="badge bg-danger">مرتجع</span>`;
        default:
            return `<span class="badge bg-info">غير معروف</span>`;
    }
} ,
// دالة البحث في المنتجات داخل الفواتير
 searchProductsInInvoices(searchTerm) {
    if (!searchTerm) return [];


    const results = [];

    AppData.invoices.forEach(invoice => {
        
        // لكل بند في الفاتورة
       invoice.items.forEach(item => {
    const productName = item.product_name.toLowerCase();
    const notes = invoice.description ? invoice.description.toLowerCase() : ""; // استخدم description بدل notes
    const lowerSearch = searchTerm.toLowerCase();

    // تحقق إذا كان البحث موجود جزئيًا في اسم المنتج أو الوصف
    if (productName.includes(lowerSearch) || notes.includes(lowerSearch)|| invoice.invoice_number==searchTerm) {
        results.push({
            invoiceId: invoice.id,
            invoiceNumber: invoice.invoice_number,   // إضافة رقم الفاتورة
            invoiceDate: invoice.date,
            productName: item.product_name,
            soldQuantity: item.quantity,
            sellingPrice: item.selling_price,
            totalPrice: item.total_price,
            availableQuantity: item.available_for_return || 0,
            returnFlag: item.return_flag,
            returnedQuantity: item.returned_quantity,
            priceType: item.price_type,
            costPricePerUnit: item.cost_price_per_unit,
            searchTerms: [searchTerm],
            notes: invoice.description || "",
            hasReturns: invoice.has_returns,
            status: invoice.status,
        });
    }
});

    });

    return results;
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

      export default UIManager;