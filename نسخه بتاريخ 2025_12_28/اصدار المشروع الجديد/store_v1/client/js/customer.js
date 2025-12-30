import AppData from './app_data.js';
import apis from './constant/api_links.js';

export const CustomerManager = {
    currentCustomer: null,
    
    async init(customerId = null) {
        // 1. تحديد معرف العميل
        const id = customerId || this.getCustomerIdFromURL();
       
        
        if (!id) {
            console.error("❌ Customer ID not found");
            this.showError("معرف العميل غير موجود");
            return;
        }
        
        
        // 2. جلب البيانات من API
        const result = await this.fetchCustomerInfo(id);
        
        if (result.success) {
            this.handleSuccess(result.data);
        } else {
            // this.handleError(result.message);
        }
    },
    
    getCustomerIdFromURL() {
        // طريقة 1: من query string
        const urlParams = new URLSearchParams(window.location.search);
        const id = urlParams.get('customer_id') || urlParams.get('id');
        
        // طريقة 2: من data attribute
        if (!id) {
            const dataId = document.body.getAttribute('data-customer-id');
            if (dataId) return dataId;
        }
        
        // طريقة 3: من متغير global
        if (!id && window.customerId) {
            return window.customerId;
        }
        
        return id;
    },
    
    async fetchCustomerInfo(customerId) {
        try {
            
            const response = await fetch(
                `${apis.getCustomerInfo}${encodeURIComponent(customerId)}`,
                {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    cache: 'no-cache'
                }
            );
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            // ('✅ API Response:', data);
            return data;
            
        } catch (error) {
            // console.error('❌ API Error:', error);
            return {
                success: false,
                message: 'فشل في الاتصال بالخادم'
            };
        }
    },
    
    handleSuccess(apiData) {
        
        // 1. حفظ البيانات في AppData
        this.currentCustomer = apiData;
        AppData.currentCustomer = apiData;
        
        // 2. تحديث واجهة المستخدم
        this.updateCustomerInfo(apiData);
        
        // 3. تحديث بقية المانجرز
        
    },
    
    updateCustomerInfo(customer) {
        // معلومات العميل
        document.getElementById("customerName").textContent = customer.name || 'غير محدد';
        document.getElementById("customerPhone").textContent = customer.mobile || 'غير محدد';
        
        // العنوان
        let address = customer.city || '';
        if (customer.address) {
            address += address ? ` - ${customer.address}` : customer.address;
        }
        document.getElementById("customerAddress").textContent = address || 'غير محدد';
        
        // تاريخ الانضمام
        const joinDate = customer.join_date || customer.created_at || 'غير محدد';
        document.getElementById("customerJoinDate").textContent = joinDate;
        
        // الأرصدة
        const balance = parseFloat(customer.balance) || 0;
        const wallet = parseFloat(customer.wallet) || 0;
        
        document.getElementById("currentBalance").textContent = 
            Math.abs(balance).toFixed(2);
        document.getElementById("walletBalance").textContent = 
            wallet.toFixed(2);
        
        // تحديث حالة الرصيد (مدين/دائن)
        const balanceCard = document.querySelector('.stat-card.negative');
        if (balanceCard) {
            const label = balance > 0 ? 'مدين' : 'دائن';
            const colorClass = balance > 0 ? 'text-danger' : 'text-success';
            
            balanceCard.querySelector('small').textContent = label;
            balanceCard.querySelector('small').className = colorClass;
        }
        
        // الصورة الرمزية (الأحرف الأولى)
        const avatar = document.getElementById("customerAvatar");
        if (avatar && customer.name) {
            const initials = customer.name
                .split(' ')
                .map(word => word.charAt(0))
                .join('')
                .substring(0, 2)
                .toUpperCase();
            avatar.textContent = initials;
        }
        
        // حفظ في AppData للاستخدام لاحقاً
        AppData.customerBalance = balance;
        AppData.customerWallet = wallet;
    },
    
 
  
    
   

    showError(message) {
        // عرض رسالة خطأ للمستخدم
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-danger alert-dismissible fade show mt-3';
        alertDiv.innerHTML = `
            <i class="fas fa-exclamation-triangle me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        const container = document.querySelector('.container-fluid');
        if (container) {
            container.insertBefore(alertDiv, container.firstChild);
        }
        
        // إخفاء الرسالة بعد 5 ثواني
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    },
    

    
    // جلب بيانات العميل الحالي
    getCustomer() {
        return this.currentCustomer;
    }
};

// جعل CustomerManager متاحاً بشكل global
export default CustomerManager;