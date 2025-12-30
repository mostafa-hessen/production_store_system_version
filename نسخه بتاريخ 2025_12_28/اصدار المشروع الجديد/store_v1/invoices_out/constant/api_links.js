// api_links.js
const BASE = "http://localhost/store_v1/api/";

const apisForInvoices = {
    
    getCustomerWorkOrders:  BASE + "get_customer_work_orders.php?customer_id=",
    getWorkOrderDetails:    BASE + "get_customer_work_order_detailes.php?work_order_id=",
    createWorkOrder:        BASE + "create_work_order.php",
    createWalletTransaction: BASE + "create_wallet_transaction.php",
    processPayment: BASE + "create_payment.php",
    saveInvoice : BASE + "save_invoice.php"           // سداد فاتورة/دفعة

};

export default apisForInvoices;