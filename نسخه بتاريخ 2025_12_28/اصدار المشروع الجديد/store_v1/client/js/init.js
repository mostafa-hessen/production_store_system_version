import PaymentManager from "./payment.js";
import AppData from "./app_data.js";
// import WalletManager from "./wallet.js";
import CustomerManager from "./customer.js";
import PrintManager from "./print.js";
import { setupNumberInputPrevention, escapeHtml, toggleSection } from "./helper.js";
import { ReturnManager, CustomReturnManager } from "./return.js";

import InvoiceManager from "./invoices.js";
import { updateInvoiceStats } from "./helper.js";
import WorkOrderManager from "./work_order.js";
import UIManager from "./ui.js";
import CustomerTransactionManager from "./transaction.js";
import WalletManager from "./wallet.js";

document.addEventListener("DOMContentLoaded", async function () {
    setupNumberInputPrevention();
    await initializeApp();
    setupEventListeners();
});

async function initializeApp() {
    // تهيئة البيانات

    await CustomerManager.init();



    InvoiceManager.init();
    CustomerTransactionManager.init();
    WorkOrderManager.init();
    ReturnManager.init();
    PaymentManager.init();
    await  WalletManager.init();
    UIManager.init();

    // تحديث الإحصائيات
    // updateInvoiceStats();
}

function setupEventListeners() {
    // إحصائيات الفواتير
    document.querySelectorAll(".invoice-stat-card").forEach((card) => {
        card.addEventListener("click", function () {
            // إزالة النشط من جميع الكروت
            document.querySelectorAll(".invoice-stat-card").forEach((c) => {
                c.classList.remove("active");
            });

            // إضافة النشط للكارت المختار
            this.classList.add("active");

            const filter = this.getAttribute("data-filter");
            AppData.activeFilters.invoiceType =
                filter === "all" ? null : filter;
            UIManager.applyFilters();
        });
    });

    // زر الطباعة المتعددة
    document
        .getElementById("printMultipleBtn")
        .addEventListener("click", function () {
            PrintManager.openPrintMultipleModal();
        });

    // زر تأكيد الطباعة المتعددة
    document
        .getElementById("confirmPrintMultipleBtn")
        .addEventListener("click", function () {
            PrintManager.printMultipleInvoices();
        });

    // تحديد/إلغاء تحديد جميع الفواتير للطباعة
    document
        .getElementById("selectAllInvoicesPrint")
        .addEventListener("change", function () {
            const checkboxes = document.querySelectorAll(
                ".print-invoice-checkbox"
            );
            checkboxes.forEach((checkbox) => {
                checkbox.checked = this.checked;
            });
        });
    // البحث في المرتجع المتقدم
    // document
    //     .getElementById("advancedProductSearch")
    //     .addEventListener("input", (e) => {


    //         const searchTerm = e.target.value;
    //         // const results = ReturnManager.searchProductsInInvoices(searchTerm);
    //         this.displayAdvancedSearchResults(results);
    //     });

        document.querySelectorAll('.toggleInvoicesSectionBtn')
  .forEach(btn => {
    btn.addEventListener('click', function () {
      const sectionKey = this.dataset.section;
      toggleSection(sectionKey, this);
    });
  });

}

