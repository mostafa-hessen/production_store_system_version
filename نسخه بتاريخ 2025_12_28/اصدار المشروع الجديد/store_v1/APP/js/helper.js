    import  AppData  from "./app_data.js";
    function setupNumberInputPrevention() {
    // اختيار جميع حقول الإدخال العددية
    const numberInputs = document.querySelectorAll('input[type="number"]');


    numberInputs.forEach(input => {
    // منع تغيير القيمة بواسطة عجلة التمرير (scroll)
    input.addEventListener('wheel', function (e) {
        e.preventDefault();

    }, { passive: false });

    // منع تغيير القيمة بواسطة السهمين لأعلى ولأسفل
    input.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
            e.preventDefault();

        }

    });


    });
    }
    function escapeHtml(text) {
    const map = {
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#039;",
    };
    return text.replace(/[&<>"']/g, (m) => map[m]);
    }

    function updateInvoiceStats() {
    const invoices = AppData.invoices;

    const pending = invoices.filter((i) => i.status === "pending");
    const partial = invoices.filter((i) => i.status === "partial");
    const paid = invoices.filter((i) => i.status === "paid");
    const returned = invoices.filter((i) => i.status === "returned");

    document.getElementById("totalInvoicesCount").textContent =
        invoices.length;
    document.getElementById("pendingInvoicesCount").textContent =
        pending.length;
    document.getElementById("partialInvoicesCount").textContent =
        partial.length;
    document.getElementById("paidInvoicesCount").textContent = paid.length;
    document.getElementById("returnedInvoicesCount").textContent =
        returned.length;

    // تحديث المبالغ
    document.querySelector(
        '[data-filter="all"] .stat-amount'
    ).textContent = `${invoices
        .reduce((sum, i) => sum + i.total, 0)
        .toFixed(2)} ج.م`;
    document.querySelector(
        '[data-filter="pending"] .stat-amount'
    ).textContent = `${pending
        .reduce((sum, i) => sum + i.total, 0)
        .toFixed(2)} ج.م`;
    document.querySelector(
        '[data-filter="partial"] .stat-amount'
    ).textContent = `${partial
        .reduce((sum, i) => sum + i.total, 0)
        .toFixed(2)} ج.م`;
    document.querySelector(
        '[data-filter="paid"] .stat-amount'
    ).textContent = `${paid
        .reduce((sum, i) => sum + i.total, 0)
        .toFixed(2)} ج.م`;
    document.querySelector(
        '[data-filter="returned"] .stat-amount'
    ).textContent = `${returned
        .reduce((sum, i) => sum + i.total, 0)
        .toFixed(2)} ج.م`;
    }




export { setupNumberInputPrevention, escapeHtml ,updateInvoiceStats};

