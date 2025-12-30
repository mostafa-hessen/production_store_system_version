// js/main.js
import AppData from './app_data.js';
import CustomerManager from './customer.js';
import InvoiceManager from './invoices.js';
import PaymentManager from './payment.js';
import WalletManager from './wallet.js';
// import ReturnManager from './return.js';
import PrintManager from './print.js';
import { setupNumberInputPrevention, updateInvoiceStats } from './helper.js';

// الحصول على معرف العميل من URL
function getCustomerIdFromUrl() {
  const urlParams = new URLSearchParams(window.location.search);
  const id = urlParams.get('customer_id') || urlParams.get('id');
  return id ? parseInt(id) : 1; // افتراضي 1 إذا لم يوجد
}

// عرض مؤشر التحميل
function showLoading(show) {
  const loader = document.getElementById('loadingIndicator');
  if (loader) {
    loader.style.display = show ? 'flex' : 'none';
  }
}

// إعداد مستمعي الأحداث
function setupEventListeners() {
  // إحصائيات الفواتير
  document.querySelectorAll('.invoice-stat-card').forEach((card) => {
    card.addEventListener('click', function () {
      document.querySelectorAll('.invoice-stat-card').forEach((c) => {
        c.classList.remove('active');
      });
      this.classList.add('active');

      const filter = this.getAttribute('data-filter');
      AppData.activeFilters.invoiceType = filter === 'all' ? null : filter;
      InvoiceManager.updateInvoicesTable();
    });
  });

  // البحث المتقدم
  document.getElementById('advancedProductSearch').addEventListener('input', (e) => {
    const searchTerm = e.target.value;
    const results = ReturnManager.searchProductsInInvoices(searchTerm);
    ReturnManager.displayAdvancedSearchResults(results);
  });

  // زر الطباعة المتعددة
  document.getElementById('printMultipleBtn').addEventListener('click', function () {
    PrintManager.openPrintMultipleModal();
  });

  // تحديث بيانات العميل عند التحميل
  document.addEventListener('customerDataLoaded', function() {

    // يمكنك إضافة أي كود هنا بعد تحميل بيانات العميل
  });
}

// تهيئة التطبيق
async function initializeApp() {
  try {
    showLoading(true);
    
    const customerId = getCustomerIdFromUrl();

    
    // تهيئة بيانات التطبيق
    AppData.currentCustomerId = customerId;
    
    // تحميل بيانات العميل من الـ API
    await CustomerManager.loadCustomerData(customerId);
    
    // تهيئة المكونات الأخرى
    setupNumberInputPrevention();
    
    // إذا كانت هناك بيانات محلية مؤقتة، يمكنك تحميلها هنا
    // await InvoiceManager.loadInvoicesFromApi(customerId);
    // await WalletManager.loadWalletTransactions(customerId);
    
    // إعداد مستمعي الأحداث
    setupEventListeners();
    
    // تحديث الإحصائيات
    updateInvoiceStats();
    
    // إرسال حدث أن البيانات تم تحميلها
    document.dispatchEvent(new Event('customerDataLoaded'));
    
  } catch (error) {
    console.error('خطأ في تهيئة التطبيق:', error);
    Swal.fire({
      icon: 'error',
      title: 'خطأ',
      text: 'فشل في تحميل بيانات العميل. الرجاء المحاولة مرة أخرى.',
      confirmButtonText: 'إعادة تحميل'
    }).then(() => {
      location.reload();
    });
  } finally {
    showLoading(false);
  }
}

// بدء التطبيق عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', initializeApp);

// تصدير الدوال للاستخدام من ملفات أخرى
export { initializeApp, showLoading, getCustomerIdFromUrl };