// CustomerTransactionManager.js
import AppData from './app_data.js';
import CustomerManager from './customer.js';
import apis from './constant/api_links.js';
import { splitDateTime } from './helper.js';

const CustomerTransactionManager = {
    isLoading: false,
    activeTab: 'transaction', // wallet, returns, statement
    
    async init(tab = 'transaction') {
        this.activeTab = tab;
        await this.loadTabData(tab);
        this.setupTransactionDateFilters();
    },
    
    // // جلب البيانات حسب التبويب
    async loadTabData(tab) {
        try {
            const customer = CustomerManager.getCustomer();
            if (!customer?.id) {
                console.error('❌ Customer not found');
                return;
            }
            
            this.isLoading = true;
            this.showTabLoading(tab);
            
            // بناء رابط API مع الفلاتر
            let apiUrl = `${apis.getCustomerTransactions}${customer.id}`;
            
            // إضافة فلاتر حسب التبويب
            const params = new URLSearchParams();
            
            // فلاتر التاريخ المشتركة
            if (AppData.activeFilters.dateFrom) {
                params.append('date_from', AppData.activeFilters.dateFrom);
            }
            if (AppData.activeFilters.dateTo) {
                params.append('date_to', AppData.activeFilters.dateTo);
            }
            
            // فلتر التبويب النشط
            if (tab === 'returns') {
                params.append('type', 'return');
            }
            // للتبويبات الأخرى، نعرض جميع الحركات
            params.append('include_summary', '1');
            
            // بناء URL كامل
            if (params.toString()) {
                apiUrl += `&${params.toString()}`;
            }
            
            // جلب البيانات
            const response = await fetch(apiUrl);
            const data = await response.json();
            
            if (data.success) {
                // حفظ البيانات في AppData
                this.saveTabData(tab, data);
                
                // تحديث الواجهة
                this.updateTabTable(tab, data.transactions);
                
                // تحديث الـ summary
              
                
                // تحديث رصيد العميل من آخر حركة
                if (data.transactions && data.transactions.length > 0) {
                    this.updateCustomerBalance(data.transactions[0]);
                }
            } else {
                this.showTabError(tab, data.message);
            }
            
        } catch (error) {
        } finally {
            this.isLoading = false;
        }
    },
    
    // حفظ البيانات في AppData حسب التبويب
    saveTabData(tab, data) {
        AppData.customerTransactions = data.transactions || [];
        AppData.transactionSummary = data.summary || {};
        
        if (tab === 'returns') {
            AppData.returnTransactions = data.transactions.filter(t => t.type === 'return');
        }
    },
    
    // تحديث جدول التبويب
    updateTabTable(tab, transactions) {
        switch(tab) {
            case 'transaction':
                this.updateTransactionTable(transactions);
                break;
            case 'returns':
                this.updateReturnsTable(transactions);
                break;
            case 'statement':
                this.updateStatementTable(transactions);
                break;
        }
    },
    
 

    // تحديث جدول المحفظة مع إضافة الديون
    updateTransactionTable(transactions) {
        const tbody = document.getElementById("transactionTableBody");
        if (!tbody) return;
        
        tbody.innerHTML = "";
        
        if (!transactions || transactions.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center py-4 text-muted">
                        <i class="fas fa-wallet fa-2x mb-3"></i>
                        <p>لا توجد حركات للعميل</p>
                    </td>
                </tr>
            `;
            return;
        }
        
        transactions.forEach((transaction) => {
            const row = document.createElement("tr");
            
            
            // استخدام transaction_date بدلاً من created_at
            



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
                    ${this.getInvoiceReference(transaction)}
                </td>
                <td class="${transaction.amount_class} fw-bold">
                    ${transaction.amount_sign} ${transaction.formatted_amount}
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
                    <small class="text-muted">${transaction.transaction_date || transaction.created_at}</small>
                </td>
            `;
            
            tbody.appendChild(row);
        });
    },
    
    // الحصول على مرجع الفاتورة
    getInvoiceReference(transaction) {
        if (transaction.invoice_number) {
            return `<small class="text-muted">فاتورة #${transaction.invoice_number}</small>`;
        }
        if (transaction.invoice_id) {
            return `<small class="text-muted">فاتورة #${transaction.invoice_id}</small>`;
        }
        return '';
    },
    
    // تحديث جدول المرتجعات
    updateReturnsTable(transactions) {
        const tbody = document.getElementById("returnsTableBody");
        if (!tbody) return;
        
        tbody.innerHTML = "";
        
        
        
        
   
    },
    
    // // تحديث جدول كشف الحساب
    updateStatementTable(transactions) {
        const tbody = document.getElementById("statementTableBody");
        if (!tbody) return;
        
        tbody.innerHTML = "";
        
        if (!transactions || transactions.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-4 text-muted">
                        <i class="fas fa-file-invoice fa-2x mb-3"></i>
                        <p>لا توجد حركات في الفترة المحددة</p>
                    </td>
                </tr>
            `;
            return;
        }
        
        // حساب الأرصدة الجارية
        let runningWalletBalance = 0;
        let runningDebtBalance = 0;
        
        transactions.forEach((transaction) => {
            const row = document.createElement("tr");
            const transactionDate = transaction.transaction_date || transaction.date;
            
            // تحديث الأرصدة الجارية
            runningWalletBalance = transaction.wallet_after;
            runningDebtBalance = transaction.debt_after || 0;
            
            row.innerHTML = `
                <td>
                    <div class="fw-semibold">${transactionDate}</div>
                    <small class="text-muted">${transaction.time}</small>
                </td>
                <td>
                    <span class="badge ${transaction.badge_class}">
                        ${transaction.type_text}
                    </span>
                </td>
                <td>${transaction.description}</td>
                <td class="${transaction.amount_class} fw-bold">
                    ${transaction.amount_sign} ${transaction.formatted_amount}
                </td>
                <td>
                    <div class="text-center">
                        <div class="fw-semibold">${runningWalletBalance.toFixed(2)} ج.م</div>
                        <small class="text-muted d-block">رصيد المحفظة</small>
                    </div>
                </td>
                <td>
                    <div class="text-center">
                        <div class="fw-semibold ${runningDebtBalance >= 0 ? 'text-danger' : 'text-success'}">
                            ${Math.abs(runningDebtBalance).toFixed(2)} ج.م
                        </div>
                        <small class="text-muted d-block">رصيد الديون</small>
                    </div>
                </td>
                <td>
                    <div>${transaction.created_by}</div>
                    <small class="text-muted">${transaction.created_at}</small>
                </td>
                <td>
                    ${this.getTransactionActions(transaction)}
                </td>
            `;
            
            tbody.appendChild(row);
        });
    },
    
    // الحصول على أزرار الإجراءات للحركة
    getTransactionActions(transaction) {
        let actions = '';
        
        if (transaction.invoice_number) {
            actions += `
                <button class="btn btn-sm btn-outline-primary me-1" 
                        onclick="CustomerTransactionManager.viewInvoice(${transaction.invoice_id})">
                    <i class="fas fa-file-invoice"></i>
                </button>
            `;
        }
        
        if (['payment', 'deposit'].includes(transaction.type)) {
            actions += `
                <button class="btn btn-sm btn-outline-success" 
                        onclick="CustomerTransactionManager.printReceipt(${transaction.id})">
                    <i class="fas fa-receipt"></i>
                </button>
            `;
        }
        
        return actions || '-';
    },
    
  
    
   
    // // ========== دوال مساعدة ==========
    
    showTabLoading(tab) {
        const tbody = this.getTabBody(tab);
        if (tbody) {
            tbody.innerHTML = `
                <tr>
                    <td  class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">جاري التحميل...</span>
                        </div>
                        <p class="mt-2 text-muted">جاري تحميل البيانات...</p>
                    </td>
                </tr>
            `;
        }
    },
    
  
  
    
    getTabBody(tab) {
        switch(tab) {
            case 'transaction': return document.getElementById("transactionTableBody");
            case 'returns': return document.getElementById("returnsTableBody");
            case 'statement': return document.getElementById("statementTableBody");
            default: return null;
        }
    },
    
  
    
   
    setupTransactionDateFilters() {
        // فلتر تاريخ الحركة
        const transactionDateFrom = document.getElementById('transactionDateFrom');
        const transactionDateTo = document.getElementById('transactionDateTo');
        
        if (transactionDateFrom) {
            transactionDateFrom.addEventListener('change', (e) => {
                AppData.activeFilters.transactionDateFrom = e.target.value;
                this.applyFilters();
            });
        }
        
        if (transactionDateTo) {
            transactionDateTo.addEventListener('change', (e) => {
                AppData.activeFilters.transactionDateTo = e.target.value;
                this.applyFilters();
            });
        }
        
        // زر مسح الفلاتر
        const clearFiltersBtn = document.getElementById('clearTransactionFilters');
        if (clearFiltersBtn) {
            clearFiltersBtn.addEventListener('click', () => {
                this.clearFilters();
            });
        }
    },
    
    applyFilters() {
        this.loadTabData(this.activeTab);
    },
    
    clearFilters() {
        // مسح الفلاتر من AppData
        AppData.activeFilters = {
            dateFrom: null,
            dateTo: null,
            transactionDateFrom: null,
            transactionDateTo: null,
            type: null
        };
        
        // مسح حقول الإدخال
        document.querySelectorAll('.transaction-filter').forEach(input => {
            if (input.type === 'date' || input.type === 'text') {
                input.value = '';
            } else if (input.type === 'select-one') {
                input.selectedIndex = 0;
            }
        });
        
        // إعادة تحميل البيانات
        this.loadTabData(this.activeTab);
    },
    
    // // دالة لإضافة حركة جديدة
    // async addTransaction(transactionData) {
    //     try {
    //         // تأكد من وجود transaction_date
    //         if (!transactionData.transaction_date) {
    //             transactionData.transaction_date = new Date().toISOString().split('T')[0];
    //         }
            
    //         const response = await fetch(apis.addTransaction, {
    //             method: 'POST',
    //             headers: {
    //                 'Content-Type': 'application/json',
    //             },
    //             body: JSON.stringify(transactionData)
    //         });
            
    //         const data = await response.json();
            
    //         if (data.success) {
    //             // إعادة تحميل البيانات
    //             await this.loadTabData(this.activeTab);
    //             return data.transaction_id;
    //         } else {
    //             throw new Error(data.message);
    //         }
    //     } catch (error) {
    //         console.error('❌ Error adding transaction:', error);
    //         throw error;
    //     }
    // },

    

    
  

        getStatementTransactions(dateFrom, dateTo) {
          let transactions = [...AppData.customerTransactions];

          

          if (dateFrom) {
            transactions = transactions.filter((t) => t.date
 >= dateFrom);
          }

          if (dateTo) {
            transactions = transactions.filter((t) => t.date
 <= dateTo);
          }

          return transactions;
        },

           getTransactionTypeText(type) {
          const typeMap = {
            payment: "سداد",
            deposit: "إيداع",
            return: "مرتجع",
          };
          return typeMap[type] || type;
        },
};

export default CustomerTransactionManager;
