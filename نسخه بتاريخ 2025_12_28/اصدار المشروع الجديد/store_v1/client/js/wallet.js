// wallet_manager.js
import AppData from "./app_data.js";
import CustomerManager from "./customer.js";
import apis from "./constant/api_links.js";
import { splitDateTime } from "./helper.js";
import UIManager from "./ui.js";

const WalletManager = {
    isLoading: false,
    currentCustomerId: null,
    currentModal: null,
    

 
    async init() {
        try {
            // الحصول على معرف العميل
            this.currentCustomerId = this.extractCustomerId();
            
            if (!this.currentCustomerId) {
                console.warn("⚠️ لم يتم العثور على معرف العميل");
                return;
            }
            
            
            // إعداد مستمعي الأحداث
            this.setupEventListeners();
            
            // تعيين التواريخ الافتراضية
            this.setupDatePickers();
            this.setupTimePickers(); //  ه
            await this.loadWalletTransactions();


        } catch (error) {
            console.error("❌ Error initializing WalletManager:", error);
            this.showNotification("فشل تهيئة نظام المحفظة", "error");
        }
    },
    
    /**
     * استخراج معرف العميل من مصادر مختلفة
     */
    extractCustomerId() {
        // 1. من query parameters
        const urlParams = new URLSearchParams(window.location.search);
        let customerId = urlParams.get('customer_id') || urlParams.get('id');
        
        // 2. من data attributes
        if (!customerId) {
            const customerElement = document.querySelector('[data-customer-id]');
            customerId = customerElement ? customerElement.dataset.customerId : null;
        }
        
        // 3. من AppData إذا كان محملاً
        if (!customerId && AppData.currentCustomer) {
            customerId = AppData.currentCustomer.id;
        }
        
        // 4. من localStorage (للتجربة)
        if (!customerId) {
            customerId = localStorage.getItem('current_customer_id');
        }
        
        return customerId ? parseInt(customerId) : null;
    },
    async loadWalletTransactions() {
    try {
        if (!this.currentCustomerId) return;

        const url = `${apis.getWalletTransactions}${this.currentCustomerId}`;



        const response = await fetch(url);
        const data = await response.json();

        if (!data.success) {
            console.warn("⚠️ Failed to load wallet transactions:", data.message);
            return;
        }

        // حفظ البيانات
        AppData.walletTransactions = data.transactions;

        // عرض البيانات في الجدول
        this.renderWalletTransactions();

    } catch (error) {
        console.error("❌ Error loading wallet transactions:", error);
    }
}
,
    
   
 /**
     * إعداد جميع مستمعي الأحداث
     * 
     */
    setupEventListeners() {
        // مستمعي أحداث الإيداع
        this.setupDepositEventListeners();
        
        // مستمعي أحداث السحب
        this.setupWithdrawEventListeners();
        
        // مستمعي أحداث المودال
        this.setupModalEventListeners();
        
        // مستمعي أحداث التبويب
        this.setupTabEventListeners();
    },
    
    /**
     * إعداد مستمعي أحداث الإيداع
     */
    setupDepositEventListeners() {
        const depositBtn = document.getElementById("processDepositBtn");
        const depositForm = document.getElementById("walletDepositForm");
        
        if (depositBtn) {
            depositBtn.addEventListener("click", (e) => {
                e.preventDefault();
                this.handleDeposit();
            });
        }
        
        if (depositForm) {
            depositForm.addEventListener("submit", (e) => {
                e.preventDefault();
                this.handleDeposit();
            });
            
            // التحقق الفوري من صحة البيانات
            depositForm.querySelectorAll('input, textarea').forEach(input => {
                input.addEventListener('input', () => this.validateForm('deposit'));
            });
        }
    },
    
    /**
     * إعداد مستمعي أحداث السحب
     */
    setupWithdrawEventListeners() {
        const withdrawBtn = document.getElementById("confirmWithdrawBtn");
        const withdrawForm = document.getElementById("walletWithdrawForm");
        
        if (withdrawBtn) {
            withdrawBtn.addEventListener("click", (e) => {
                e.preventDefault();
                this.handleWithdraw();
            });
        }
        
        if (withdrawForm) {
            withdrawForm.addEventListener("submit", (e) => {
                e.preventDefault();
                this.handleWithdraw();
            });
            
            // التحقق من المبلغ أثناء الكتابة
            const amountInput = withdrawForm.querySelector('#withdrawAmount');
            if (amountInput) {
                amountInput.addEventListener('input', () => this.validateWithdrawAmount());
            }
        }
    },
    
    /**
     * إعداد مستمعي أحداث المودال
     */
    setupModalEventListeners() {
        // عند فتح مودال الإيداع
        const depositModal = document.getElementById('walletDepositModal');
        if (depositModal) {
            depositModal.addEventListener('show.bs.modal', () => {
                this.currentModal = 'deposit';
                this.prepareDepositModal();
            });
            
            depositModal.addEventListener('hidden.bs.modal', () => {
                this.resetForm('deposit');
            });
        }
        
        // عند فتح مودال السحب
        const withdrawModal = document.getElementById('walletWithdrawModal');
        if (withdrawModal) {
            withdrawModal.addEventListener('show.bs.modal', () => {
                this.currentModal = 'withdraw';
                this.prepareWithdrawModal();
            });
            
            withdrawModal.addEventListener('hidden.bs.modal', () => {
                this.resetForm('withdraw');
            });
        }
    },
    
    /**
     * إعداد مستمعي أحداث التبويب
     */
    setupTabEventListeners() {
        // تحديث بيانات المحفظة عند التبديل بين التبويبات
const walletTab = document.querySelector('[data-bs-target="#walletTransaction"]');
        // if (walletTab) {
        //     walletTab.addEventListener('click', () => {
        //         this.refreshWalletData();
        //     });
        // }
    },
    
    /**
     * إعداد منتقي التاريخ
     */
    setupDatePickers() {
        const today = new Date().toISOString().split('T')[0];
        
        // تعيين التاريخ الحالي كقيمة افتراضية
        const dateInputs = document.querySelectorAll('input[type="date"]');
        dateInputs.forEach(input => {
            if (!input.value) {
                input.value = today;
                input.max = today; // لا يمكن اختيار تاريخ مستقبلي
            }
        });
    },
    
    /**
     * تحضير مودال الإيداع
     */
    prepareDepositModal() {

        
        // تحديث الرصيد الحالي
        this.updateWalletBalanceDisplay();
        
        // تفعيل الزر
        const depositBtn = document.getElementById('processDepositBtn');
        if (depositBtn) {
            depositBtn.disabled = false;
        }
    },
    
    /**
     * تحضير مودال السحب
     */
    prepareWithdrawModal() {

        
        if (!AppData.currentCustomer) {
            this.showNotification("بيانات العميل غير متوفرة", "warning");
            return;
        }
        
        const availableAmountEl = document.getElementById('walletAvailableAmount');
        const amountInput = document.getElementById('withdrawAmount');
        
        if (availableAmountEl) {

            
            availableAmountEl.textContent = AppData.formatCurrency(AppData.currentCustomer.wallet);
        }
        
        if (amountInput) {
            amountInput.max = AppData.currentCustomer.wallet;
            amountInput.placeholder = `الحد الأقصى: ${AppData.formatCurrency(AppData.currentCustomer.wallet)}`;
        }
        
        // إخفاء التحذير
        const warning = document.getElementById('withdrawWarning');
        if (warning) warning.style.display = 'none';
    },
    
    /**
     * معالجة عملية الإيداع
     */
    async handleDeposit() {
        try {
            // التحقق من صحة النموذج
            if (!this.validateForm('deposit')) {
                return;
            }
            
            const formData = this.getFormData('deposit');
            
            // التحقق من البيانات الأساسية
            if (!this.currentCustomerId) {
                this.showNotification("العميل غير محدد", "error");
                return;
            }
            
            // إظهار حالة التحميل
            this.setLoadingState('deposit', true, 'جاري معالجة الإيداع...');
            
            // تحضير البيانات للـ API
            const transactionData = {
                customer_id: this.currentCustomerId,
                type: "deposit",
                amount: parseFloat(formData.amount),
                description: formData.description || this.generateDescription('deposit', formData.amount),
                transaction_date: formData.transaction_date || new Date().toISOString(),

        };
            

            
            // استدعاء الـ API
            const response = await this.callWalletAPI(transactionData);
            
            if (response.success) {
                // تحديث البيانات المحلية
                this.updateLocalData(response);
                
                // إظهار رسالة النجاح
                this.showSuccessMessage('deposit', formData.amount);
                
                // إغلاق المودال
                this.closeModal('deposit');
                
                // إعادة تحميل بيانات العميل
                await this.refreshCustomerData();
               

                
                return response;
            } else {
                throw new Error(response.message || "فشل عملية الإيداع");
            }
            
        } catch (error) {
            console.error("❌ Deposit error:", error);
            this.showNotification(error.message, "error");
            throw error;
        } finally {
            this.setLoadingState('deposit', false);
        }
    },
    
    /**
     * معالجة عملية السحب
     */
    async handleWithdraw() {
        try {
            // التحقق من صحة النموذج
            if (!this.validateForm('withdraw')) {
                return;
            }
            
            const formData = this.getFormData('withdraw');
            
            // التحقق من البيانات الأساسية
            if (!this.currentCustomerId) {
                this.showNotification("العميل غير محدد", "error");
                return;
            }
            
            if (!AppData.currentCustomer) {
                this.showNotification("بيانات العميل غير متوفرة", "error");
                return;
            }
            
            // التحقق من الرصيد الكافي
            const amount = parseFloat(formData.amount);
            if (amount > AppData.currentCustomer.wallet) {
                this.showNotification("رصيد المحفظة غير كافي للسحب", "warning");
                return;
            }
            
            // إظهار حالة التحميل
            this.setLoadingState('withdraw', true, 'جاري معالجة السحب...');
            
            // تحضير البيانات للـ API
            const transactionData = {
                customer_id: this.currentCustomerId,
                type: "withdraw",
                amount: amount,
                description: formData.description || this.generateDescription('withdraw', amount),
                // transaction_date: formData.date ? this.formatDateForAPI(formData.date) : undefined
                transaction_date: formData.transaction_date || new Date().toISOString()
            };
            

            
            // استدعاء الـ API
            const response = await this.callWalletAPI(transactionData);
            
            if (response.success) {
                // تحديث البيانات المحلية
                this.updateLocalData(response);
                
                // إظهار رسالة النجاح
                this.showSuccessMessage('withdraw', amount);
                
                // إغلاق المودال
                this.closeModal('withdraw');
                
                // إعادة تحميل بيانات العميل
                await this.refreshCustomerData();
                
                return response;
            } else {
                throw new Error(response.message || "فشل عملية السحب");
            }
            
        } catch (error) {
            console.error("❌ Withdraw error:", error);
            this.showNotification(error.message, "error");
            throw error;
        } finally {
            this.setLoadingState('withdraw', false);
        }
    },
    
    /**
     * استدعاء الـ API لحركات المحفظة
     */
    async callWalletAPI(data) {
        try {
            const response = await fetch(apis.createWalletTransaction, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return result;
            
        } catch (error) {
            console.error("🚨 API call failed:", error);
            throw new Error(`فشل الاتصال بالخادم: ${error.message}`);
        }
    },
    
    /**
     * التحقق من صحة نموذج الإيداع/السحب
     */
    validateForm(formType) {
        const formId = formType === 'deposit' ? 'walletDepositForm' : 'walletWithdrawForm';
        const form = document.getElementById(formId);
        
        if (!form) return false;
        
        const amountInput = form.querySelector('#depositAmount, #withdrawAmount');
        const descriptionInput = form.querySelector('#depositDescription, #withdrawDescription');
        const dateInput = form.querySelector('#depositDate, #withdrawDate');
        
        let isValid = true;
        
        // التحقق من المبلغ
        if (!amountInput || !amountInput.value || parseFloat(amountInput.value) <= 0) {
            this.markInvalid(amountInput, 'يرجى إدخال مبلغ صحيح');
            isValid = false;
        } else {
            this.markValid(amountInput);
        }
        
        // التحقق من التاريخ
        if (!dateInput || !dateInput.value) {
            this.markInvalid(dateInput, 'يرجى تحديد تاريخ');
            isValid = false;
        } else {
            this.markValid(dateInput);
        }
        
        // للتحقق من الرصيد للسحب
        if (formType === 'withdraw' && AppData.currentCustomer) {
            const amount = parseFloat(amountInput.value);
            if (amount > AppData.currentCustomer.wallet) {
                this.markInvalid(amountInput, 'رصيد غير كافي');
                isValid = false;
                
                // إظهار تحذير
                const warning = document.getElementById('withdrawWarning');
                if (warning) {
                    warning.style.display = 'block';
                }
            }
        }
        
        return isValid;
    },
    
    /**
     * التحقق من مبلغ السحب أثناء الكتابة
     */
    validateWithdrawAmount() {
        const amountInput = document.getElementById('withdrawAmount');
        const warning = document.getElementById('withdrawWarning');
        const submitBtn = document.getElementById('confirmWithdrawBtn');
        
        if (!amountInput || !AppData.currentCustomer) return;
        
        const amount = parseFloat(amountInput.value) || 0;
        const availableBalance = AppData.currentCustomer.wallet;
        
        if (amount > availableBalance) {
            this.markInvalid(amountInput, 'رصيد غير كافي');
            if (warning) warning.style.display = 'block';
            if (submitBtn) submitBtn.disabled = true;
        } else {
            this.markValid(amountInput);
            if (warning) warning.style.display = 'none';
            if (submitBtn) submitBtn.disabled = false;
        }
    },
    
    /**
     * الحصول على بيانات النموذج
     */
   /**
 * الحصول على بيانات النموذج
 */
getFormData(formType) {
    const formId = formType === 'deposit' ? 'walletDepositForm' : 'walletWithdrawForm';
    const form = document.getElementById(formId);
    
    if (!form) return {};
    
    // الحصول على التاريخ
    const date = form.querySelector('#depositDate, #withdrawDate')?.value;
    
    // الحصول على الوقت (من الحقل الجديد)
    let time = '';
    if (formType === 'deposit') {
        time = form.querySelector('#depositTime')?.value || '00:00';
    } else {
        time = form.querySelector('#withdrawTime')?.value || '00:00';
    }
    
    // دمج التاريخ والوقت في حقل واحد
    const transaction_date = date && time ? `${date} ${time}` : null;
    
    return {
        amount: form.querySelector('#depositAmount, #withdrawAmount')?.value,
        description: form.querySelector('#depositDescription, #withdrawDescription')?.value,
        date: date,
        time: time,
        transaction_date: transaction_date
    };
},
    
    /**
     * توليد وصف تلقائي للحركة
     */
    generateDescription(type, amount) {
        const amountFormatted = parseFloat(amount).toFixed(2);
        const typeText = type === 'deposit' ? 'إيداع' : 'سحب';
        
        return `${typeText} محفظة - مبلغ ${amountFormatted} ج.م`;
    },
    
    /**
     * تحديث البيانات المحلية بعد العملية
     */
  updateLocalData(apiResponse) {
    // apiResponse now contains: wallet_transaction, customer_transaction, wallet_update, customer
    if (!apiResponse) return;

    // 1. تحديث رصيد العميل
    if (AppData.currentCustomer) {
        AppData.currentCustomer.wallet = apiResponse.wallet_update?.wallet_after ?? AppData.currentCustomer.wallet;
        // تصحيح اسم الحقل إلى balance
        AppData.currentCustomer.balance = apiResponse.wallet_update?.balance_after ?? AppData.currentCustomer.balance;
    }

    // 2. إضافة الحركة الجديدة في حركات المحفظة (walletTransactions)
    const walletTx = apiResponse.wallet_transaction ?? apiResponse.transaction ?? null;
    if (walletTx) {
        if (!Array.isArray(AppData.walletTransactions)) AppData.walletTransactions = [];
        // قد ترغب في الاحتفاظ بالشكل الكامل كما أرسله السيرفر (formatted) أو عمل map
        AppData.walletTransactions.unshift(walletTx);
    }

    // 3. إضافة الحركة الجديدة في customerTransactions (حركات العميل العام)
    const customerTx = apiResponse.customer_transaction ?? null;
    if (customerTx) {
        if (!Array.isArray(AppData.customerTransactions)) AppData.customerTransactions = [];
        AppData.customerTransactions.unshift(customerTx);
        
    }

    // 4. إعادة رسم جدول المحفظة
    if (typeof this.renderWalletTransactions === 'function') {
        this.renderWalletTransactions();
    }
    // 4.1 إعادة رسم جدول حركات العميل
    if (typeof this.renderCustomerTransactions === 'function') {
        this.renderCustomerTransactions();
        this.updateStatementTable(AppData.customerTransactions)
    }

    // 5. تحديث عرض رصيد المحفظة
    if (typeof this.updateWalletBalanceDisplay === 'function') {
        this.updateWalletBalanceDisplay();
    }
},
  updateStatementTable(transactions) {

    // (transactions.type_text);
    
    const tbody = document.getElementById("statementTableBody");
    
    if (!tbody) return;
    
    tbody.innerHTML = "";
    
    if (transactions.length === 0) {
        tbody.innerHTML = `
        <tr>
        <td colspan="12" class="text-center text-muted">
                    لا توجد حركات للعميل
                    </td>
            </tr>`;
        return;
    }
    
    let row;
    transactions.forEach((transaction) => {
        // احصل على تواريخ آمنة (قد تكون transaction.created_at أو transaction.transaction_date)
        const createdAtStr = transaction.created_at || transaction.created_at_datetime || transaction.created_at_time || '';
        const txDateStr = transaction.transaction_date || transaction.transaction_datetime || '';

        const { date: createdDate, time: createdTime } = splitDateTime(createdAtStr);
        const { date: transactionDate, time: transactionTime } = splitDateTime(txDateStr);

        // قيم افتراضية آمنة
        const badgeClass = transaction.badge_class || (transaction.transaction_type === 'deposit' ? 'bg-success' : (transaction.transaction_type === 'withdraw' ? 'bg-danger' : 'bg-secondary'));
        const typeText = transaction.type_text || (transaction.transaction_type ? (transaction.transaction_type === 'deposit' ? 'إيداع' : (transaction.transaction_type === 'withdraw' ? 'سحب' : transaction.transaction_type)) : '-');
        const amountSign = typeof transaction.amount_sign !== 'undefined' ? transaction.amount_sign : ((transaction.amount >= 0) ? '+' : '-');
        const formattedAmount = transaction.formatted_amount ?? (Math.abs(Number(transaction.amount || 0)).toFixed(2) + ' ج.م');
        const amountClass = transaction.amount_class || ((Number(transaction.amount || 0) >= 0) ? 'text-success' : 'text-danger');

        const walletBefore = (typeof transaction.wallet_before === 'number' ? transaction.wallet_before : Number(transaction.wallet_before || 0));
        const walletAfter  = (typeof transaction.wallet_after === 'number' ? transaction.wallet_after : Number(transaction.wallet_after || 0));
        const balanceBefore = (typeof transaction.balance_before === 'number' ? transaction.balance_before : Number(transaction.balance_before || 0));
        const balanceAfter  = (typeof transaction.balance_after === 'number' ? transaction.balance_after : Number(transaction.balance_after || 0));

        const createdByText = transaction.created_by_name || transaction.created_by || '-';
        const smallDateText = transaction.transaction_date || transaction.created_at || '';

        // build row
         row = document.createElement('tr');
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
                <span class="badge ${badgeClass}">
                    ${typeText}
                </span>
            </td>
            <td>
                <div>${transaction.description || ''}</div>
                ${typeof this.getInvoiceReference === 'function' ? this.getInvoiceReference(transaction) : ''}
            </td>
            <td class="${amountClass} fw-bold">
                ${amountSign} ${formattedAmount}
            </td>
            <td>
                <div class="text-center">
                    <div class="fw-semibold">${walletBefore.toFixed(2)} ج.م</div>
                    <small class="text-muted d-block">المحفظة قبل</small>
                </div>
            </td>
            <td>
                <div class="text-center">
                    <div class="fw-semibold">${walletAfter.toFixed(2)} ج.م</div>
                    <small class="text-muted d-block">المحفظة بعد</small>
                </div>
            </td>
            <td>
                <div class="text-center">
                    <div class="fw-semibold ${balanceBefore >= 0 ? 'text-danger' : 'text-success'}">
                        ${Math.abs(balanceBefore).toFixed(2)} ج.م
                    </div>
                    <small class="text-muted d-block">الديون قبل</small>
                </div>
            </td>
            <td>
                <div class="text-center">
                    <div class="fw-semibold ${balanceAfter >= 0 ? 'text-danger' : 'text-success'}">
                        ${Math.abs(balanceAfter).toFixed(2)} ج.م
                    </div>
                    <small class="text-muted d-block">الديون بعد</small>
                </div>
            </td>
            <td>
                <div>${createdByText}</div>
                <small class="text-muted">${smallDateText}</small>
            </td>
        `;

        tbody.append(row);

        
    });
    
    },
renderCustomerTransactions() {

    // (transactions.type_text);
    
    const tbody = document.getElementById("transactionTableBody");
    if (!tbody) return;

    const transactions = AppData.customerTransactions || [];
    tbody.innerHTML = "";

    if (transactions.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="12" class="text-center text-muted">
                    لا توجد حركات للعميل
                </td>
            </tr>`;
        return;
    }

    transactions.forEach((transaction) => {
        // احصل على تواريخ آمنة (قد تكون transaction.created_at أو transaction.transaction_date)
        const createdAtStr = transaction.created_at || transaction.created_at_datetime || transaction.created_at_time || '';
        const txDateStr = transaction.transaction_date || transaction.transaction_datetime || '';

        const { date: createdDate, time: createdTime } = splitDateTime(createdAtStr);
        const { date: transactionDate, time: transactionTime } = splitDateTime(txDateStr);

        // قيم افتراضية آمنة
        const badgeClass = transaction.badge_class || (transaction.transaction_type === 'deposit' ? 'bg-success' : (transaction.transaction_type === 'withdraw' ? 'bg-danger' : 'bg-secondary'));
        const typeText = transaction.type_text || (transaction.transaction_type ? (transaction.transaction_type === 'deposit' ? 'إيداع' : (transaction.transaction_type === 'withdraw' ? 'سحب' : transaction.transaction_type)) : '-');
        const amountSign = typeof transaction.amount_sign !== 'undefined' ? transaction.amount_sign : ((transaction.amount >= 0) ? '+' : '-');
        const formattedAmount = transaction.formatted_amount ?? (Math.abs(Number(transaction.amount || 0)).toFixed(2) + ' ج.م');
        const amountClass = transaction.amount_class || ((Number(transaction.amount || 0) >= 0) ? 'text-success' : 'text-danger');

        const walletBefore = (typeof transaction.wallet_before === 'number' ? transaction.wallet_before : Number(transaction.wallet_before || 0));
        const walletAfter  = (typeof transaction.wallet_after === 'number' ? transaction.wallet_after : Number(transaction.wallet_after || 0));
        const balanceBefore = (typeof transaction.balance_before === 'number' ? transaction.balance_before : Number(transaction.balance_before || 0));
        const balanceAfter  = (typeof transaction.balance_after === 'number' ? transaction.balance_after : Number(transaction.balance_after || 0));

        const createdByText = transaction.created_by_name || transaction.created_by || '-';
        const smallDateText = transaction.transaction_date || transaction.created_at || '';

        // build row
        const row = document.createElement('tr');
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
                <span class="badge ${badgeClass}">
                    ${typeText}
                </span>
            </td>
            <td>
                <div>${transaction.description || ''}</div>
                ${typeof this.getInvoiceReference === 'function' ? this.getInvoiceReference(transaction) : ''}
            </td>
            <td class="${amountClass} fw-bold">
                ${amountSign} ${formattedAmount}
            </td>
            <td>
                <div class="text-center">
                    <div class="fw-semibold">${walletBefore.toFixed(2)} ج.م</div>
                    <small class="text-muted d-block">المحفظة قبل</small>
                </div>
            </td>
            <td>
                <div class="text-center">
                    <div class="fw-semibold">${walletAfter.toFixed(2)} ج.م</div>
                    <small class="text-muted d-block">المحفظة بعد</small>
                </div>
            </td>
            <td>
                <div class="text-center">
                    <div class="fw-semibold ${balanceBefore >= 0 ? 'text-danger' : 'text-success'}">
                        ${Math.abs(balanceBefore).toFixed(2)} ج.م
                    </div>
                    <small class="text-muted d-block">الديون قبل</small>
                </div>
            </td>
            <td>
                <div class="text-center">
                    <div class="fw-semibold ${balanceAfter >= 0 ? 'text-danger' : 'text-success'}">
                        ${Math.abs(balanceAfter).toFixed(2)} ج.م
                    </div>
                    <small class="text-muted d-block">الديون بعد</small>
                </div>
            </td>
            <td>
                <div>${createdByText}</div>
                <small class="text-muted">${smallDateText}</small>
            </td>
        `;

        tbody.appendChild(row);
    });
}
,

renderWalletTransactions() {
    const tbody = document.getElementById("walletTransactionTableBody");
    if (!tbody) return;

    const transactions = AppData.walletTransactions || [];
    tbody.innerHTML = "";

    if (transactions.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center text-muted">
                    لا توجد حركات محفظة لهذا العميل
                </td>
            </tr>`;
        return;
    }

    transactions.forEach((t) => {
        // حقول التاريخ: transaction_date و created_at
        const createdAtStr = t.created_at || t.created_at_datetime || t.transaction_date || '';
        const txDateStr = t.transaction_date || t.transaction_datetime || '';
        const { date: createdDate, time: createdTime } = splitDateTime(createdAtStr);
        const { date: transactionDate, time: transactionTime } = splitDateTime(txDateStr);

        const badgeClass = t.badge_class || (t.type === 'deposit' ? 'bg-success' : (t.type === 'withdraw' ? 'bg-danger' : 'bg-secondary'));
        const amountClass = t.amount_class || (t.amount >= 0 ? 'text-success' : 'text-danger');
        const formattedAmount = t.formatted_amount ?? ( (t.amount >= 0 ? '+' : '-') + AppData.formatCurrency(Math.abs(t.amount || 0)) );

        const createdByText = t.created_by_name || t.created_by || '-';

        const row = `
            <tr>
                <td>
                    <div class="fw-semibold">${createdDate}</div>
                    <small class="text-muted">${createdTime}</small>
                </td>
                <td>
                    <div class="fw-semibold">${transactionDate}</div>
                    <small class="text-muted">${transactionTime}</small>
                </td>
                <td>
                    <span class="badge ${badgeClass}">${t.type === 'deposit' ? 'إيداع' : (t.type === 'withdraw' ? 'سحب' : (t.type_text || '-'))}</span>
                </td>
                <td>${t.description || ''}</td>
                <td class="${amountClass} fw-bold">${formattedAmount}</td>
                <td>${AppData.formatCurrency(t.wallet_before ?? null)}</td>
                <td>${AppData.formatCurrency(t.wallet_after ?? null)}</td>
                <td>${createdByText}</td>
            </tr>
        `;

        tbody.insertAdjacentHTML("beforeend", row);
    });
},

    /**
     * تحديث عرض رصيد المحفظة
     */
    updateWalletBalanceDisplay() {
        if (!AppData.currentCustomer) return;
        
        const balanceElements = document.querySelectorAll('.wallet-balance-display');
        balanceElements.forEach(el => {
            el.textContent = AppData.formatCurrency(AppData.currentCustomer.wallet);
        });
    },
    
    /**
     * إعادة تحميل بيانات العميل
     */
    async refreshCustomerData() {
        if (typeof CustomerManager === 'object' && CustomerManager.init) {
            await CustomerManager.init(this.currentCustomerId);
        }
        
        // تحديث الواجهة
        this.updateWalletBalanceDisplay();
    },
    
    /**
     * تحديث بيانات المحفظة
     */
    async refreshWalletData() {
        try {
            // جلب أحدث بيانات العميل
            await this.refreshCustomerData();
            
            // جلب حركات المحفظة إذا كان هناك API لذلك
            if (apis.getWalletTransactions) {
                await this.loadWalletTransactions();
            }
            
        } catch (error) {
            console.error("❌ Error refreshing wallet data:", error);
        }
    },
    
    /**
     * تحميل حركات المحفظة
     */
    async loadWalletTransactions() {
        try {
            if (!this.currentCustomerId) return;
            
            const response = await fetch(`${apis.getWalletTransactions}${this.currentCustomerId}`);
            const data = await response.json();
            
            if (data.success && data.transactions) {
                AppData.walletTransactions = data.transactions;
                
                // تحديث العرض إذا كان هناك دالة لذلك
                if (typeof this.renderWalletTransactions === 'function') {
                    this.renderWalletTransactions();
                }
            }
            
        } catch (error) {
            console.error("❌ Error loading wallet transactions:", error);
        }
    },
    
    // ========== دوال المساعدة ==========
    
    /**
     * تنسيق التاريخ للـ API
     */
    formatDateForAPI(dateString) {
        const date = new Date(dateString);
        return date.toISOString().split('T')[0] + ' ' + 
               date.toTimeString().split(' ')[0];
    },
    
    /**
     * تعيين حالة التحميل
     */
    setLoadingState(formType, isLoading, message = '') {
        const btnId = formType === 'deposit' ? 'processDepositBtn' : 'confirmWithdrawBtn';
        const button = document.getElementById(btnId);
        
        if (!button) return;
        
        if (isLoading) {
            button.disabled = true;
            const originalText = button.textContent;
            button.dataset.originalText = originalText;
            button.innerHTML = `
                <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                ${message}
            `;
        } else {
            button.disabled = false;
            if (button.dataset.originalText) {
                button.textContent = button.dataset.originalText;
                delete button.dataset.originalText;
            }
        }
    },
    
    /**
     * إغلاق المودال
     */
    closeModal(formType) {
        const modalId = formType === 'deposit' ? 'walletDepositModal' : 'walletWithdrawModal';
        const modalElement = document.getElementById(modalId);
        
        if (modalElement) {
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) {
                modal.hide();
            }
        }
    },
    
    /**
     * إعادة تعيين النموذج
     */
    resetForm(formType) {
        const formId = formType === 'deposit' ? 'walletDepositForm' : 'walletWithdrawForm';
        const form = document.getElementById(formId);
        
        if (form) {
            form.reset();
            this.setupDatePickers();
                    this.setupTimePickers(); 

        }
    },
    /**
 * إعداد حقول الوقت
 */
setupTimePickers() {
    // تعيين الوقت الحالي كقيمة افتراضية
    const now = new Date();
    const currentTime = now.getHours().toString().padStart(2, '0') + ':' + 
                        now.getMinutes().toString().padStart(2, '0');
    
    const timeInputs = document.querySelectorAll('input[type="time"]');
    timeInputs.forEach(input => {
        if (!input.value) {
            input.value = currentTime;
        }
    });
},
    /**
     * وضع علامة على الحقل كغير صالح
     */
    markInvalid(element, message) {
        if (!element) return;
        
        element.classList.add('is-invalid');
        element.classList.remove('is-valid');
        
        // إضافة أو تحديث رسالة الخطأ
        let feedback = element.nextElementSibling;
        if (!feedback || !feedback.classList.contains('invalid-feedback')) {
            feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            element.parentNode.appendChild(feedback);
        }
        feedback.textContent = message;
    },
    
    /**
     * وضع علامة على الحقل كصالح
     */
    markValid(element) {
        if (!element) return;
        
        element.classList.remove('is-invalid');
        element.classList.add('is-valid');
        
        // إزالة رسالة الخطأ
        const feedback = element.nextElementSibling;
        if (feedback && feedback.classList.contains('invalid-feedback')) {
            feedback.remove();
        }
    },
    
    /**
     * إظهار رسالة النجاح
     */
    showSuccessMessage(formType, amount) {
        const action = formType === 'deposit' ? 'إيداع' : 'سحب';
        const message = `تم ${action} مبلغ ${parseFloat(amount).toFixed(2)} ج.م بنجاح`;
        
        this.showNotification(message, 'success');
    },
    
    /**
     * إظهار الإشعارات
     */
    // showNotification(message, type = 'info') {
    //     // استخدام SweetAlert إذا كان متاحاً
    //     if (typeof Swal !== 'undefined') {
    //         Swal.fire({
    //             title: this.getNotificationTitle(type),
    //             toast: true,
    //             text: message,
    //             icon: type,
    //             confirmButtonText: 'حسناً',
    //             timer: type === 'success' ? 3000 : undefined,
    //             timerProgressBar: type === 'success',
    //             toast: type !== 'error',
    //             position: 'top-end'
    //         });
    //     } 
    //     // أو استخدام Toastify
    //     else if (typeof Toastify !== 'undefined') {
    //         Toastify({
    //             text: message,
    //             toast: true,
    //             duration: 3000,
    //             gravity: "top",
    //             position: "right",
    //             backgroundColor: this.getNotificationColor(type),
    //         }).showToast();
    //     }
    //     // أو استخدام alert عادي
    //     else {
    //         alert(message);
    //     }
    // },
    showNotification(message, type = 'info') {
    if (typeof Swal !== 'undefined') {
        const isToast = (type !== 'error'); // toast لكل الأنواع ماعدا 'error'
        Swal.fire({
            title: this.getNotificationTitle(type),
            text: message,
            icon: type,
            confirmButtonText: 'حسناً',
            toast: isToast,
            position: 'top-end',
            showConfirmButton: !isToast,
            timer: isToast ? 3000 : undefined,
            timerProgressBar: isToast,
        }).then(() => {
            // تنظيف أي تغييرات على body لو حصلت (fallback آمن)
            try {
                // إزالة overflow style إن وُضع
                if (document.body.style.overflow === 'hidden') {
                    document.body.style.overflow = '';
                }
                // إزالة أي backdrops أو كلاسات متبقية لو لزم
                document.body.classList.remove('modal-open');
            } catch (e) {
                console.warn('Cleanup after Swal failed', e);
            }
        });

        return;
    }

    if (typeof Toastify !== 'undefined') {
        Toastify({
            text: message,
            duration: 3000,
            gravity: "top",
            position: "right",
            backgroundColor: this.getNotificationColor(type),
        }).showToast();
        return;
    }

    alert(message);
}
,
    /**
     * الحصول على عنوان الإشعار بناءً على النوع
     */
    getNotificationTitle(type) {
        const titles = {
            'success': 'نجاح',
            'error': 'خطأ',
            'warning': 'تحذير',
            'info': 'معلومات'
        };
        return titles[type] || 'إشعار';
    },
    
    /**
     * الحصول على لون الإشعار
     */
    getNotificationColor(type) {
        const colors = {
            'success': '#28a745',
            'error': '#dc3545',
            'warning': '#ffc107',
            'info': '#17a2b8'
        };
        return colors[type] || '#007bff';
    },
    
    /**
     * تسجيل الأحداث
     */
    logEvent(event, data) {

        
        // يمكن إضافة إرسال إلى خدمة تحليلات هنا
        if (window.gtag) {
            gtag('event', event, data);
        }
    }
};

export default WalletManager;