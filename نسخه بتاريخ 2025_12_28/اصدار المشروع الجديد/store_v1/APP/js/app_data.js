const AppData = {
    // بيانات العميل
    currentCustomer: null,
    customerBalance: 0,
    customerWallet: 0,
    
    // البيانات الأخرى
    invoices: [],
    walletTransactions: [],
    returns: [],
    workOrders: [],
    activeFilters: {},
    
    // المستخدم الحالي
    currentUser: "مدير النظام",
    
    // إعدادات
    paymentMethods: [
        { id: 1, name: "نقدي", code: "cash", icon: "fa-money-bill" },
        { id: 2, name: "تحويل بنكي", code: "bank", icon: "fa-university" },
        { id: 3, name: "بطاقة ائتمان", code: "credit", icon: "fa-credit-card" },
        { id: 4, name: "شيك", code: "check", icon: "fa-file-invoice-dollar" }
    ],
    
    // دوال مساعدة
    formatCurrency(amount) {
        return parseFloat(amount).toFixed(2) + ' ج.م';
    },
    
    getInvoiceStatusText(status) {
        const statusMap = {
            'pending': { text: 'مؤجل', class: 'badge-pending' },
            'partial': { text: 'جزئي', class: 'badge-partial' },
            'paid': { text: 'مسلم', class: 'badge-paid' },
            'returned': { text: 'مرتجع', class: 'badge-returned' },
            'canceled': { text: 'ملغي', class: 'badge-canceled' }
        };
        
        return statusMap[status] || { text: 'غير معروف', class: 'badge-secondary' };
    },
    
    // تحديث البيانات بعد عملية ما
    refreshData() {
        // يمكن استدعاء API لتحديث البيانات
        if (CustomerManager && CustomerManager.currentCustomer) {
            CustomerManager.init(CustomerManager.currentCustomer.id);
        }
    }
};

export default AppData;