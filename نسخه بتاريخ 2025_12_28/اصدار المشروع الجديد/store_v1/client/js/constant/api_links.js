// api_links.js
const BASE = "http://localhost/store_v1/api/";

const apis = {
    getCustomerInfo:        BASE + "get_customer_info.php?customer_id=",
    getCustomerInvoices:    BASE + "get_customer_invoices.php?customer_id=",
    getReturns:             BASE + "get_customer_returns.php",
    getInvoiceDetails:      BASE + "get_invoice_detailes.php?invoice_id=",
    getCustomerWorkOrders:  BASE + "get_customer_work_orders.php?customer_id=",
    getWorkOrderDetails:    BASE + "get_customer_work_order_detailes.php?work_order_id=",
    getCustomerTransactions: BASE + "get_customer_transactions.php?customer_id=",
    getWalletTransactions: BASE + "get_customer_wallet_transaction.php?customer_id=",
    
    createWorkOrder:        BASE + "create_work_order.php",
    createWalletTransaction: BASE + "create_wallet_transaction.php",
  

    processPayment: BASE + "create_payment.php",           // سداد فاتورة/دفعة
    processReturn: BASE + "create_return.php",  
    getReturnItems : BASE + "get_customer_return_detailes.php",        // إرجاع فاتورة/دفعة

};

export default apis;