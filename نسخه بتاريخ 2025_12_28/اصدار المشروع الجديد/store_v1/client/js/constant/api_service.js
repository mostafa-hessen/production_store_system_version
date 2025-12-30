// api_service.js
import AppData from '../app_data.js';
import apis from './api_links.js';

class ApiService {
    constructor() {
        this.baseUrl = apis.BASE || "http://localhost/store_v1/api/";
    }

    // دالة مساعدة للطلبات
    async makeRequest(url, method = 'GET', data = null) {
     
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            credentials: 'include'
        };

        if (data) {
            options.body = JSON.stringify(data);
        }

        try {
            const response = await fetch(url, options);
            
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('API Request Error:', error);
            throw error;
        }
    }

    // جلب بيانات الفاتورة للإرجاع
    async getInvoiceForReturn(invoiceId) {
        try {
            console.log(invoiceId);
            
            // const response = await this.makeRequest(`${apis.getInvoiceDetails}${invoiceId}`);
            const response= AppData.invoices.find(inv => +inv.id === +invoiceId);
            console.log(response);
            
            return response;
        } catch (error) {
            console.error('Error fetching invoice for return:', error);
            throw error;
        }
    }

    // جلب بنود المرتجع فقط (للمودال)
async getReturnDetails(returnId) {
    try {
        const response = await this.makeRequest(
            `${apis.getReturnItems}?return_id=${returnId}`,
            'GET'
        );

        return response;
    } catch (error) {
        console.error('Error fetching return items:', error);
        throw error;
    }
}


    async getReturns(customerId) {
        try {
            const response = await this.makeRequest(`${apis.getReturns}?customer_id=${customerId}`, 'GET');
            return response;
        } catch (error) {
            console.error('Error fetching returns:', error);
            throw error;
        }
    }
    // إنشاء عملية إرجاع
    async createReturn(returnData) {
        console.log(returnData,'createReturn');
        
        try {
            const response = await this.makeRequest(apis.processReturn, 'POST', returnData);
            return response;
        } catch (error) {
            console.error('Error creating return:', error);
            throw error;
        }
    }

    // جلب تفاصيل الفاتورة
    async getInvoiceDetails(invoiceId) {
        try {
            const response = await this.makeRequest(`${apis.getInvoiceDetails}${invoiceId}`);
            return response;
        } catch (error) {
            console.error('Error fetching invoice details:', error);
            throw error;
        }
    }

    // جلب معاملات العميل
    async getCustomerTransactions(customerId) {
        try {
            const response = await this.makeRequest(`${apis.getCustomerTransactions}${customerId}`);
            return response;
        } catch (error) {
            console.error('Error fetching customer transactions:', error);
            throw error;
        }
    }

    // جلب معاملات المحفظة
    async getWalletTransactions(customerId) {
        try {
            const response = await this.makeRequest(`${apis.getWalletTransactions}${customerId}`);
            return response;
        } catch (error) {
            console.error('Error fetching wallet transactions:', error);
            throw error;
        }
    }
}

// إنشاء instance واحد من ApiService
const apiService = new ApiService();
export default apiService;