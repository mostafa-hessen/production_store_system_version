import AppData from "./app_data.js";
import { CustomReturnManager } from "./return.js";
// import { CustomReturnManager } from "./return.js";
import { updateInvoiceStats } from "./helper.js";
import CustomerManager from "./customer.js";
import PrintManager from "./print.js";
import PaymentManager from "./payment.js";
import apis from "./constant/api_links.js";

const InvoiceManager = {
  isLoading: false,
  currentCustomerId: null,

  async init() {
    await this.loadCustomerInvoices();
    this.setupGlobalListeners();
    this.setupTooltipStyles();
  },

  // ========== دوال API ==========

  async loadCustomerInvoices() {
    try {
      const customer = CustomerManager.getCustomer();
      if (!customer?.id) {
        this.showError("العميل غير محدد");
        return;
      }

      this.currentCustomerId = customer.id;
      this.isLoading = true;
      this.showLoading();

      const response = await fetch(`${apis.getCustomerInvoices}${customer.id}`);
      const data = await response.json();

      if (data.success) {
        // حفظ البيانات
        AppData.invoices = data.invoices;
        AppData.invoiceSummary = data.summary || {};

        // تحديث الواجهة
        this.updateInvoicesTable();
        this.updateStatsCards(data.summary);
      } else {
        this.showError(data.message || "فشل في تحميل الفواتير");
      }
    } catch (error) {
      console.error("❌ Error loading invoices:", error);
      this.showError("خطأ في الاتصال بالخادم");
    } finally {
      this.isLoading = false;
    }
  },

  async loadInvoiceDetails(invoiceId) {
    // try {
    //     const response = await fetch(`${apis.getInvoiceDetails}${invoiceId}`);
    //     const data = await response.json();

    //     if (data.success) {
    //         return data.invoice;
    //     } else {
    //         throw new Error(data.message);
    //     }
    // } catch (error) {
    //     console.error("❌ Error loading invoice details:", error);
    //     throw error;
    // }

    return AppData.invoices.find((inv) => +inv.id === +invoiceId);
  },

  // ========== الواجهة الرئيسية (كما كانت) ==========

  updateInvoicesTable() {
    const tbody = document.getElementById("invoicesTableBody");
    tbody.innerHTML = "";

    // إذا لم توجد فواتير
    if (!AppData.invoices || AppData.invoices.length === 0) {
      tbody.innerHTML = `
                    <tr>
                        <td colspan="9" class="text-center py-5">
                            <div class="text-muted">
                                <i class="fas fa-file-invoice fa-2x mb-3"></i>
                                <p>لا توجد فواتير</p>
                            </div>
                        </td>
                    </tr>
                `;
      return;
    }

    // تطبيق الفلاتر
    let filteredInvoices = this.filterInvoices(AppData.invoices);

    // إذا لم توجد نتائج بعد الفلترة
    if (filteredInvoices.length === 0) {
      tbody.innerHTML = `
                    <tr>
                        <td colspan="9" class="text-center py-5">
                            <div class="text-warning">
                                <i class="fas fa-search fa-2x mb-3"></i>
                                <p>لا توجد فواتير تطابق معايير البحث</p>
                            </div>
                        </td>
                    </tr>
                `;
      return;
    }

    // بناء الجدول بنفس التصميم الأصلي
    filteredInvoices.forEach((invoice) => {
      const row = this.createInvoiceRow(invoice);
      tbody.appendChild(row);
    });

    // إضافة Event Listeners بعد بناء الجدول
    this.attachInvoiceEventListeners();

    // تحديث عدد المحدد
    this.updateSelectedCount();
  },

  createInvoiceRow(invoice) {
    
    const row = document.createElement("tr");
    row.className = `invoice-row ${invoice.status}`;

    // 1. تحديد حالة الفاتورة
    let statusBadge = "";
    if (invoice.status === "pending") {
      statusBadge = '<span class="status-badge badge-pending">مؤجل</span>';
    } else if (invoice.status === "partial") {
      statusBadge = '<span class="status-badge badge-partial">جزئي</span>';
    } else if (invoice.status === "paid") {
      statusBadge = '<span class="status-badge badge-paid">مسلم</span>';
    } else if (invoice.status === "returned") {
      statusBadge = '<span class="status-badge badge-returned">مرتجع</span>';
    }

    // 2. حساب بيانات الخصم
    const discountAmount = parseFloat(invoice.discount_amount || 0);
    const discountValue = parseFloat(invoice.discount_value || 0);
    const discountType = invoice.discount_type || "percent";
    const beforeDiscount = parseFloat(
      invoice.total_before_discount || invoice.total || 0
    );
    const afterDiscount = parseFloat(
      invoice.total_after_discount || invoice.total || 0
    );

    // 2. حساب بيانات الخصم

    const discountScope = invoice.discount_scope || "invoice"; // ← إضافة هذا

    // 2.1 إضافة بادج لتمييز نوع الخصم
    let discountScopeBadge = "";
    if (discountAmount > 0) {
      if (discountScope === "items") {
        discountScopeBadge = `<span class="badge bg-info me-1" title="خصم على مستوى البنود"><i class="fas fa-tag"></i> ع البنود</span>`;
      } else {
        discountScopeBadge = `<span class="badge bg-secondary me-1" title="خصم على مستوى الفاتورة"><i class="fas fa-file-invoice"></i> ع الفاتورة</span>`;
      }
    }
    // 3. إنشاء خلية الإجمالي مع عرض الخصم - دي اللي هتتعدل
    // 3. إنشاء خلية الإجمالي مع عرض الخصم
    let totalCellHTML = "";
    if (discountAmount > 0) {
      const discountPercentage =
        discountType === "percent"
          ? discountValue
          : (discountAmount / beforeDiscount) * 100;

      totalCellHTML = `
            <div class="d-flex flex-column align-items-start">
                <!-- نوع الخصم (بادج) -->
                ${discountScopeBadge}
                
                <!-- السعر الأصلي -->
                <span class="text-muted text-decoration-line-through" style="font-size: 11px;">
                    ${beforeDiscount.toFixed(2)}
                </span>
                
                <!-- السعر النهائي -->
                <span class="fw-bold text-success" style="font-size: 13px;">
                    ${afterDiscount.toFixed(2)}
                </span>
                
                <!-- بادج الخصم مع Tooltip -->
                <span class="badge bg-danger mt-1" 
                    style="font-size: 9px; padding: 2px 6px;"
                    title="مبلغ الخصم: ${discountAmount.toFixed(2)} ج.م">
                    خصم ${discountPercentage.toFixed(1)}%
                </span>
            </div>
        `;
    } else {
      totalCellHTML = `
            <span class="fw-bold">${afterDiscount.toFixed(2)}</span>
        `;
    }
    // 4. تحديد لون المبلغ المتبقي
    let remainingColor = "text-danger";
    if (parseFloat(invoice.remaining) === 0) {
      remainingColor = "text-success";
    } else if (invoice.status === "partial") {
      remainingColor = "text-warning";
    }

    // 5. تحضير الـ Tooltip
    const tooltipContainer = this.createTooltipContainer(invoice);

    // 6. بناء HTML الصف
    row.setAttribute("data-invoice-id", invoice.id);
    row.innerHTML = `
          
       ${
  invoice.status !== "returned"
    ? `
        <td>
          <input 
            type="checkbox" 
            class="form-check-input invoice-checkbox print-invoice-checkbox" 
            data-invoice-id="${invoice.id}">
        </td>
       
      `
    : `
        <td colspan="1"></td>
      `
}

             <td>
          <strong>${invoice.invoice_number || invoice.id}</strong>
          <br>
          <small class="text-muted">
            ${
              invoice.workOrderName
                ? `<i class="fa-solid fa-hammer"></i> ${invoice.workOrderName}`
                : ""
            }
          </small>
        </td>  
            <td>${invoice.date}<br><small>${invoice.time}</small></td>
            <td class="invoice-item-hover position-relative">
                <div class="items-count" data-invoice-id="${invoice.id}">
                    ${invoice.items_count || 0} بند
                    ${
                      invoice.has_returns
                        ? '<br><small class="text-warning">(يوجد مرتجعات)</small>'
                        : '<br><small class="text-muted">(مرر للعرض)</small>'
                    }
                </div>
                ${tooltipContainer}
                </td>
            <!-- هذا هو عمود الإجمالي اللي هيتعدل -->
            <td>
                ${totalCellHTML}
                
            </td>
            <td>${parseFloat(invoice.paid).toFixed(2) || 0}</td>
            <td>
                <span class="${remainingColor} fw-bold">
                    ${parseFloat(invoice.remaining).toFixed(2) || 0}
                </span>
            </td>
            <td>${statusBadge}</td>
            <td>
                <div class="action-buttons">
                    <button class="btn btn-sm btn-outline-info view-invoice" 
                            data-invoice-id="${invoice.id}">
                        <i class="fas fa-eye"></i>
                    </button>
                    ${
                      invoice.status !== "paid" && invoice.status !== "returned"
                        ? `
                    <button class="btn btn-sm btn-outline-success pay-invoice" 
                            data-invoice-id="${invoice.id}">
                        <i class="fas fa-money-bill-wave"></i>
                    </button>
                    `
                        : ""
                    }
                    ${
                      invoice.status !== "returned"
                        ? `
                    <button class="btn btn-sm btn-outline-warning custom-return-invoice" 
                            data-invoice-id="${invoice.id}">
                        <i class="fas fa-undo"></i>
                    </button>
                    `
                        : ""
                    }
                    ${ 
                      invoice.status !== "returned"
                    ? `
                    <button class="btn btn-sm btn-outline-secondary print-invoice" 
                            data-invoice-id="${invoice.id}">
                        <i class="fas fa-print"></i>
                    </button>`:""}
                </div>
            </td>
        `;

    // 7. إضافة event لتحميل البنود عند hover
    this.setupTooltipHover(row, invoice.id);

    return row;
  },

  createModalItemRowWithDiscount(item, discountScope, invoiceDiscountAmount) {
    const row = document.createElement("tr");

    const currentQuantity =
      item.current_quantity || item.quantity - (item.returned_quantity || 0);
    const originalTotal =
      item.quantity * (item.selling_price || item.price || 0);

    // حساب بيانات الخصم
    const itemDiscountAmount = parseFloat(
      item.discount_amount || item.discount_amount || 0
    );
    const itemDiscountValue = parseFloat(
      item.discount_value || item.discount_value || 0
    );
    const itemDiscountType =
      item.discount_type || item.discount_type || "amount";
    const itemBeforeDiscount = parseFloat(
      item.total_before_discount || item.total_before_discount || originalTotal
    );
    const itemAfterDiscount = parseFloat(
      item.total_after_discount ||
        item.total_after_discount ||
        originalTotal - itemDiscountAmount
    );
    const hasReturn = item.returned_quantity > 0;
    const itemPriceAfterDiscount = item?.unit_price_after_discount;
    const currentAfterDiscountTotal = itemPriceAfterDiscount * currentQuantity;

    // التعديل: نستخدم السعر الحالي مع مراعاة المرتجعات
    const currentTotal =
      item.current_total ||
      currentQuantity * (item.selling_price || item.price || 0);

    let rowClass = "";
    let itemStatus = "";

    if (item.fully_returned || item.returned_quantity >= item.quantity) {
      itemStatus = '<span class="badge bg-danger">مرتجع كلي</span>';
      rowClass = "fully-returned";
    } else if (item.returned_quantity > 0) {
      itemStatus = '<span class="badge bg-warning">مرتجع جزئي</span>';
      rowClass = "partially-returned";
    }

    row.className = rowClass;

    // بناء HTML للبند
    let itemHTML = `
            <td>
                <strong>${item.product_name || "منتج"}</strong>
                ${
                  item.returned_quantity > 0
                    ? `<div class="mt-1">
                        <span class="badge bg-warning return-history-badge">
                            مرتجع: ${item.returned_quantity}
                        </span>
                    </div>`
                    : ""
                }
            </td>
            <td>
                <div class="d-flex flex-column">
                    <span class="text-muted small">أصلي: ${item.quantity}</span>
                    <span class="fw-bold mt-1">حالي: ${currentQuantity}</span>
                </div>
            </td>
            <td>${(item.selling_price || item.price || 0).toFixed(2)} ج.م</td>
        `;

    // **الحالة 1: فاتورة عليها خصم بنود (عرض جميع الأعمدة للجميع)**
    if (discountScope === "items" && invoiceDiscountAmount > 0) {
      const itemDiscountPercentage =
        itemDiscountType === "percent"
          ? itemDiscountValue
          : (itemDiscountAmount / itemBeforeDiscount) * 100;

      // تحديد أنماط التنسيق حسب وجود الخصم
      const beforeDiscountClass =
        itemDiscountAmount > 0
          ? "text-decoration-line-through text-muted"
          : "text-muted";

      const discountValueClass =
        itemDiscountAmount > 0 ? "text-danger fw-bold" : "text-muted";

      const discountPercentText =
        itemDiscountAmount > 0
          ? `(${itemDiscountPercentage.toFixed(1)}%)`
          : "(0.0%)";

      itemHTML += `
                <!-- قبل الخصم -->
                <td>
                    <span class="${beforeDiscountClass}">
                        ${itemBeforeDiscount.toFixed(2)} ج.م
                    </span>
                </td>
                
                <!-- قيمة الخصم -->
                <td class="${discountValueClass}">
                    <div>-${itemDiscountAmount.toFixed(2)} ج.م</div>
                    <small class="text-muted">
                        ${discountPercentText}
                    </small>
                </td>

                <td>
                    <div class="d-flex flex-column">
                    ${itemPriceAfterDiscount}
                            </div>
                </td>

    <td class="fw-bold">
        ${
          hasReturn
            ? `
                <div class="text-muted text-decoration-line-through">
                    ${itemAfterDiscount.toFixed(2)} ج.م
                </div>
                <div class="text-success fw-bold">
                    ${currentAfterDiscountTotal.toFixed(2)} ج.م
                </div>
            `
            : `
                <div class="text-success fw-bold">
                    ${itemAfterDiscount.toFixed(2)} ج.م
                </div>
            `
        }
    </td>



            `;
    }
    // **الحالة 2: فاتورة ليس عليها خصم بنود (عرض عمود واحد فقط)**
    else {
      itemHTML += `
                <td class="fw-bold">
                    ${currentTotal.toFixed(2)} ج.م
                </td>
            `;
    }

    itemHTML += `
            <td>${item.returned_quantity || 0}</td>
        `;

    row.innerHTML = itemHTML;
    return row;
  },

  

  // ========== Event Listeners (نفس الكود الأصلي) ==========
  createTooltipContainer(invoice) {
    return `
            <div class="invoice-items-tooltip " id="tooltip-${invoice.id}" 
                style="display: none;  z-index: 9999;">
                <div class="tooltip-content " id="tooltip-content-${invoice.id}">
                
                </div>
            </div>
        `;
  },

  
  buildItemsTooltip(invoice) {
    const items = invoice.items || [];

    const discountAmount = parseFloat(invoice.discount_amount || 0);
    const discountValue = parseFloat(invoice.discount_value || 0);
    const discountType = invoice.discount_type || "percent";
    const discountScope = invoice.discount_scope || "invoice";
    const beforeDiscount = parseFloat(
      invoice.total_before_discount || invoice.total || 0
    );
    const afterDiscount = parseFloat(
      invoice.total_after_discount || invoice.total || 0
    );

    if (items.length === 0) {
      return `
                <div class="tooltip-header">
                    بنود الفاتورة ${invoice.invoice_number || invoice.id}
                </div>
                <div class="text-center py-3 text-muted">
                    لا توجد بنود
                </div>
            `;
    }

    let totalReturnedAmount = 0; // جديد: لحساب إجمالي المرتجعات

    const itemsList = items
      .map((item) => {
        const returnedQuantity = item.returned_quantity || 0;
        const currentQuantity = item.quantity - returnedQuantity;
        const originalTotal =
          item.total_before_discount ||
          item.quantity * (item.selling_price || item.price || 0);
        const discountedUnitPrice =
          item.unit_price_after_discount ||
          item.selling_price ||
          item.price ||
          0;
        const currentTotal = currentQuantity * discountedUnitPrice; // جديد: الإجمالي بعد الخصم والمرتجع

        // حساب إجمالي المرتجع
        if (returnedQuantity > 0) {
          totalReturnedAmount += returnedQuantity * discountedUnitPrice;
        }

        const itemDiscount =
          discountScope === "items" ? parseFloat(item.discount_amount || 0) : 0;
        const hasDiscount = itemDiscount > 0;

        let discountHTML = "";
        if (hasDiscount) {
          const itemDiscountPercent = (
            (itemDiscount / originalTotal) *
            100
          ).toFixed(1);
          discountHTML = `
                        <div class="tooltip-item-discount">
                            <small class="text-danger">
                                <i class="fas fa-tag me-1"></i>
                                خصم: ${itemDiscount.toFixed(
                                  2
                                )} ج.م (${itemDiscountPercent}%)
                            </small>
                        </div>
                    `;
        }

        const returnedText =
          returnedQuantity > 0
            ? `<br><small class="text-warning">(مرتجع: ${returnedQuantity})</small>`
            : "";

        return `
                    <div class="tooltip-item">
                        <div>
                            <div class="tooltip-item-name">${
                              item.product_name || "منتج"
                            }</div>
                            <div class="tooltip-item-details">
                                الكمية: ${currentQuantity} من ${
          item.quantity
        }${returnedText}
                                <br>
                                السعر: <span style="${
                                  hasDiscount
                                    ? "text-decoration: line-through;"
                                    : ""
                                }">${(
          item.selling_price ||
          item.price ||
          0
        ).toFixed(2)}</span>
                                ${
                                  hasDiscount
                                    ? ` → ${discountedUnitPrice.toFixed(2)} ج.م`
                                    : ""
                                }
                                ${discountHTML}
                            </div>
                        </div>
                        <div class="fw-bold">
                            ${currentTotal.toFixed(2)} ج.م
                        </div>
                    </div>
                `;
      })
      .join("");

    // بناء قسم الخصم + المرتجعات
    let discountSection = "";
    if (discountAmount > 0 || totalReturnedAmount > 0) {
      const discountPercent =
        discountType === "percent"
          ? discountValue
          : (discountAmount / beforeDiscount) * 100;

      discountSection = `
                <div class="tooltip-discount-section">
                    <div class="tooltip-discount-row">
                        <span>الإجمالي قبل الخصم:</span>
                        <span>${beforeDiscount.toFixed(2)} ج.م</span>
                    </div>
                    ${
                      discountAmount > 0
                        ? `
                    <div class="tooltip-discount-row text-danger">
                        <span>قيمة الخصم:</span>
                        <span>-${discountAmount.toFixed(2)} ج.م</span>
                    </div>`
                        : ""
                    }
                    ${
                      totalReturnedAmount > 0
                        ? `
                    <div class="tooltip-discount-row text-warning">
                        <span>إجمالي المرتجع:</span>
                        <span>- ${totalReturnedAmount.toFixed(2)} ج.م</span>
                    </div>`
                        : ""
                    }
                    ${
                      discountAmount > 0
                        ? `
                    <div class="tooltip-discount-row">
                        <small class="text-muted">
                            نوع الخصم: ${
                              discountScope === "items"
                                ? "على البنود"
                                : "على الفاتورة"
                            } (${discountPercent.toFixed(1)}%)
                        </small>
                    </div>`
                        : ""
                    }
                </div>
            `;
    }

    return `
            <div class="tooltip-header">
                بنود الفاتورة ${invoice.invoice_number || invoice.id}
            </div>
            ${itemsList}
            ${discountSection}
            <div class="tooltip-total">
                <span>الإجمالي النهائي:</span>
                <span class="fw-bold">${afterDiscount.toFixed(2)} ج.م</span>
            </div>
        `;
  },

  // تحديث CSS للـ Tooltip
  setupTooltipStyles() {
    const style = document.createElement("style");
    style.textContent = `
            .tooltip-discount-badge {
                background: linear-gradient(135deg, #6c757d, #495057);
                color: white;
                padding: 4px 8px;
                border-radius: 4px 4px 0 0;
                font-size: 12px;
                text-align: center;
            }
            
            .tooltip-discount-section {
                margin-top: 10px;
                padding-top: 10px;
                border-top: 1px dashed #dee2e6;
            }
            
            .tooltip-discount-row {
                display: flex;
                justify-content: space-between;
                margin-bottom: 3px;
                font-size: 12px;
            }
            
            .tooltip-item-discount {
                margin-top: 2px;
                padding: 2px 5px;
                background: #fff3cd;
                border-radius: 3px;
                border: 1px solid #ffeaa7;
            }
        `;
    document.head.appendChild(style);
  },

  setupTooltipHover(row, invoiceId) {
    const itemsCell = row.querySelector(".invoice-item-hover");
    const tooltip = row.querySelector(`#tooltip-${invoiceId}`);
    const tooltipContent = tooltip.querySelector(
      `#tooltip-content-${invoiceId}`
    );

    let timeoutId;

    itemsCell.addEventListener("mouseenter", async () => {
      // إلغاء أي timeout سابق
      clearTimeout(timeoutId);

      // إظهار الـ tooltip فوراً
      tooltip.style.display = "block";

      // البحث عن الفاتورة في البيانات المحلية
      const invoice = AppData.invoices?.find((inv) => inv.id == invoiceId);

      if (invoice?.items) {
        // إذا كانت البيانات موجودة محلياً
        const tooltipHTML = this.buildItemsTooltip(invoice);
        tooltipHTML;

        tooltipContent.innerHTML = tooltipHTML;
      } else {
        try {
          // إذا لم توجد محلياً، تحميل من API
          const invoiceDetails = await this.loadInvoiceDetails(invoiceId);

          if (invoiceDetails?.items) {
            // حفظ في البيانات المحلية لاستخدامها لاحقاً
            if (!invoice.items) {
              invoice.items = invoiceDetails.items;
            }

            const tooltipHTML = this.buildItemsTooltip(invoiceDetails);
            tooltipContent.innerHTML = tooltipHTML;
          }
        } catch (error) {
          tooltipContent.innerHTML = `
                        <div class="tooltip-error text-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            فشل في تحميل البنود
                        </div>
                    `;
        }
      }
    });

    itemsCell.addEventListener("mouseleave", () => {
      // تأخير إخفاء الـ tooltip لمدة 300ms لتجنب الاختفاء السريع
      timeoutId = setTimeout(() => {
        tooltip.style.display = "none";
        // إعادة تعيين الـ loading للمرة القادمة
        tooltipContent.innerHTML = `
                    <div class="tooltip-loading">
                        <i class="fas fa-spinner fa-spin me-2"></i> جاري تحميل البنود...
                    </div>
                `;
      }, 300);
    });

    tooltip.addEventListener("mouseenter", () => {
      clearTimeout(timeoutId);
      tooltip.style.display = "block";
      ("Tooltip mouseenter - remain visible");
    });

    tooltip.addEventListener("mouseleave", () => {
      timeoutId = setTimeout(() => {
        tooltip.style.display = "none";
        tooltipContent.innerHTML = `
                    <div class="tooltip-loading">
                        <i class="fas fa-spinner fa-spin me-2"></i> جاري تحميل البنود...
                    </div>
                `;
      }, 300);
    });
  },
  attachInvoiceEventListeners() {
    // 1. زر عرض الفاتورة
    document.querySelectorAll(".view-invoice").forEach((btn) => {
      btn.addEventListener("click", async function () {
        const invoiceId = parseInt(this.getAttribute("data-invoice-id"));
        await InvoiceManager.showInvoiceDetails(invoiceId);
      });
    });

    // 2. زر سداد الفاتورة
    document.querySelectorAll(".pay-invoice").forEach((btn) => {
      btn.addEventListener("click", function () {
        const invoiceId = parseInt(this.getAttribute("data-invoice-id"));
        PaymentManager.openSingleInvoicePayment(invoiceId);
      });
    });

    // 3. زر إرجاع الفاتورة المخصص
    document.querySelectorAll(".custom-return-invoice").forEach((btn) => {
      btn.addEventListener("click", function () {
        const invoiceId = parseInt(this.getAttribute("data-invoice-id"));
        CustomReturnManager.openReturnModal(invoiceId);
      });
    });

    // 4. زر طباعة الفاتورة
    document.querySelectorAll(".print-invoice").forEach((btn) => {
      btn.addEventListener("click", function () {
        const invoiceId = parseInt(this.getAttribute("data-invoice-id"));
        PrintManager.printSingleInvoice(invoiceId);
      });
    });

    // إضافة مستمع الحدث لزر طباعة الفاتورة في المودال
    document
      .getElementById("printInvoiceItemsBtn")
      ?.addEventListener("click", function () {
        // الحصول على رقم الفاتورة من المودال
        const invoiceNumber =
          document.getElementById("invoiceItemsNumber").textContent;


        // البحث عن الفاتورة في البيانات
        const invoice = AppData.invoices?.find(
          (inv) =>
            inv.id == invoiceNumber || inv.invoice_number == invoiceNumber
        );



        if (invoice&& invoice.status!=="returned") {
          PrintManager.printSingleInvoice(invoice.id);
        } else  if (invoice&& invoice.status==="returned"){
          // إذا لم تجدها في البيانات المحلية، استخدم الرقم الظاهر
          PrintManager.printSingleInvoice(invoiceNumber);
        }
        else {
                     Swal.fire('خطأ', 'فاتورة مرتجع لا يمكن طباعتها', 'error');

        }
      });

    // 5. تحديد/إلغاء تحديد الفواتير
    document.querySelectorAll(".invoice-checkbox").forEach((checkbox) => {
      checkbox.addEventListener("change", () => {
        InvoiceManager.updateSelectedCount();
      });
    });

    // 6. تحديد الكل
    document
      .getElementById("selectAllInvoices")
      ?.addEventListener("change", function () {
        const checkboxes = document.querySelectorAll(".invoice-checkbox");
        checkboxes.forEach((cb) => (cb.checked = this.checked));
        InvoiceManager.updateSelectedCount();
      });
  },

  // ========== دالة عرض تفاصيل الفاتورة (تستخدم API) ==========

  async showInvoiceDetails(invoiceId) {
    try {
      // إظهار loading في المودال
      this.showModalLoading();

      // تحميل التفاصيل من API
      const invoice = await this.loadInvoiceDetails(invoiceId);

      // تعبئة المودال
      this.populateInvoiceModal(invoice);

      // إظهار المودال
      const modal = new bootstrap.Modal(
        document.getElementById("invoiceItemsModal")
      );
      modal.show();
    } catch (error) {
      console.error("Error showing invoice details:", error);
      this.showModalError(error.message);
    } finally {
      this.hideModalLoading();
    }
  },

  populateInvoiceModal(invoice) {
    // العناصر الأساسية
    document.getElementById("invoiceItemsNumber").textContent =
      invoice.invoice_number || invoice.id;
    document.getElementById(
      "invoiceItemsDate"
    ).textContent = `${invoice.date} - ${invoice.time}`;
    document.getElementById("invoiceItemsStatus").textContent =
      this.getInvoiceStatusText(invoice.status);
    document.getElementById("invoiceItemsNotes").textContent =
      invoice.notes || invoice.description || "لا يوجد";

    // عرض اسم الشغلانة
    document.getElementById("invoiceItemsWorkOrder").textContent =
      invoice.workOrderName || "لا يوجد";

    // حساب بيانات الخصم
    const discountAmount = parseFloat(invoice.discount_amount || 0);
    const discountValue = parseFloat(invoice.discount_value || 0);
    const discountType = invoice.discount_type || "percent";
    const beforeDiscount = parseFloat(
      invoice.total_before_discount || invoice.total || 0
    );
    const afterDiscount = parseFloat(
      invoice.total_after_discount || invoice.total || 0
    );
    const discountScope = invoice.discount_scope || "invoice"; // ← إضافة هذا
    // **تحديث عرض الإجمالي بالشكل الجديد**
    // **تحديث عرض الإجمالي بالشكل الجديد**
    const totalElement = document.getElementById("invoiceItemsTotal");
    if (discountAmount > 0) {
      const discountPercentage =
        discountType === "percent"
          ? discountValue
          : (discountAmount / beforeDiscount) * 100;

      // حساب إجمالي المرتجع
      let totalReturnedAmount = 0;
      (invoice.items || []).forEach((item) => {
        const returnedQuantity = item.returned_quantity || 0;
        const unitPriceAfterDiscount =
          item.unit_price_after_discount ||
          item.selling_price ||
          item.price ||
          0;
        totalReturnedAmount += returnedQuantity * unitPriceAfterDiscount;
      });

      totalElement.innerHTML = `
        <div class="amount-with-discount position-relative">
            <!-- Tooltip عند المرور -->
            <div class="discount-tooltip" 
                data-bs-toggle="tooltip" 
                data-bs-html="true"
                data-bs-placement="top"
                data-bs-title="
                    الإجمالي قبل الخصم: ${beforeDiscount.toFixed(2)} ج.م<br>
                    قيمة الخصم: -${discountAmount.toFixed(2)} ج.م<br>
                    ${
                      totalReturnedAmount > 0
                        ? `إجمالي المرتجع: -${totalReturnedAmount.toFixed(
                            2
                          )} ج.م<br>`
                        : ""
                    }
                    نوع الخصم: ${
                      discountScope === "items" ? "على البنود" : "على الفاتورة"
                    }
                ">
                <!-- السعر الأصلي -->
                <div class="amount-original text-muted text-decoration-line-through">
                    ${beforeDiscount.toFixed(2)} ج.م
                </div>
                <div
                class="discount-separator my-1 border-top border-dashed text-primary">
                    قيمة الخصم: -${discountAmount.toFixed(2)} ج.م<br>

                
                </div>
                <!-- تفاصيل الخصم -->
                
                <div class="discount-details text-danger">
                    ${
                      totalReturnedAmount > 0
                        ? `المرتجعات: -${totalReturnedAmount.toFixed(
                            2
                          )} ج.م<br>`
                        : ""
                    }
    </div>

                <!-- السعر النهائي بعد الخصم والمرتجع -->
                <div class="amount-final fw-bold">
                    ${afterDiscount.toFixed(2)} ج.م
                </div>
                <!-- بادج الخصم -->
                <div class="discount-badge badge ${
                  discountScope === "items" ? "bg-info" : "bg-secondary"
                } position-top-10 end-10">
                    ${
                      discountScope === "items"
                        ? '<i class="fas fa-tag me-1"></i>'
                        : '<i class="fas fa-file-invoice me-1"></i>'
                    }
                    ${
                      discountType === "percent"
                        ? `${discountValue}%`
                        : `${discountAmount.toFixed(2)} ج.م`
                    }
                </div>
            </div>
        </div>
    `;

      // تفعيل الـ tooltips
      const tooltipTriggerList = document.querySelectorAll(
        '[data-bs-toggle="tooltip"]'
      );
      tooltipTriggerList.forEach((tooltipTriggerEl) => {
        new bootstrap.Tooltip(tooltipTriggerEl, {
          html: true,
          boundary: "viewport",
        });
      });

      // **عرض تفاصيل الخصم في قسم خاص**
      // **عرض تفاصيل الخصم في قسم خاص**
      const discountDetailsElement = document.getElementById(
        "invoiceDiscountDetails"
      );
      if (discountDetailsElement) {
        discountDetailsElement.innerHTML = `
            <div class="card ${
              discountScope === "items" ? "border-info" : "border-secondary"
            } mb-3">
                <div class="card-header ${
                  discountScope === "items" ? "bg-info" : "bg-secondary"
                } text-white py-2 d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas ${
                          discountScope === "items"
                            ? "fa-tag"
                            : "fa-file-invoice"
                        } me-2"></i>
                        تفاصيل الخصم - ${
                          discountScope === "items"
                            ? "على البنود"
                            : "على الفاتورة"
                        }
                    </div>
                    <span class="badge ${
                      discountScope === "items"
                        ? "bg-light text-info"
                        : "bg-light text-secondary"
                    }">
                        ${
                          discountType === "percent"
                            ? "نسبة مئوية"
                            : "مبلغ ثابت"
                        }
                    </span>
                </div>
                <div class="card-body p-3">
                    <div class="row">
                        <div class="col-6">
                            <small class="text-muted">الإجمالي قبل الخصم</small>
                            <div class="fw-bold">${beforeDiscount.toFixed(
                              2
                            )} ج.م</div>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">قيمة الخصم</small>
                            <div class="${
                              discountScope === "items"
                                ? "text-info"
                                : "text-secondary"
                            } fw-bold">
                                -${discountAmount.toFixed(2)} ج.م
                            </div>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-6">
                            <small class="text-muted">${
                              discountType === "percent" ? "النسبة" : "المبلغ"
                            }</small>
                            <div class="fw-bold">
                                ${
                                  discountType === "percent"
                                    ? `${discountValue}%`
                                    : `${discountValue} ج.م`
                                }
                            </div>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">المبلغ المخصوم</small>
                            <div class="fw-bold text-danger">
                                ${discountAmount.toFixed(2)} ج.م
                            </div>
                        </div>
                    </div>
                    ${
                      discountScope === "items"
                        ? `
                    <div class="row mt-2">
                        <div class="col-12">
                            <small class="text-muted">ملاحظة</small>
                            <div class="alert alert-info alert-sm py-1 mb-0">
                                <i class="fas fa-info-circle me-1"></i>
                                الخصم تم تطبيقه على مستوى البنود (كل بند له خصم منفصل)
                            </div>
                        </div>
                    </div>
                    `
                        : ""
                    }
                </div>
            </div>
        `;

        // في نهاية populateInvoiceModal()
        this.setupItemDiscountTooltips();
      }
    } else {
      totalElement.textContent = `${afterDiscount.toFixed(2)} ج.م`;
    }

    // **تحديث باقي المبالغ**
    document.getElementById("invoiceItemsPaid").textContent =
      parseFloat(invoice.paid_amount || invoice.paid || 0).toFixed(2) + " ج.م";
    document.getElementById("invoiceItemsRemaining").textContent =
      parseFloat(invoice.remaining_amount || invoice.remaining || 0).toFixed(
        2
      ) + " ج.م";

    // التحقق من وجود مرتجعات
    if (invoice.returns?.length > 0) {
      document.getElementById("invoiceReturnsSection").style.display = "block";
      document.getElementById("viewInvoiceReturns").onclick = (e) => {
        e.preventDefault();
        CustomReturnManager.showInvoiceReturns(invoice.id);
      };
    } else {
      document.getElementById("invoiceReturnsSection").style.display = "none";
    }

    // **تعبئة جدول البنود بدون عرض الخصم على كل بند**
    // **تعبئة جدول البنود مع الخصم**
    const tbody = document.getElementById("invoiceItemsDetails");
    const thead = document
      .querySelector("#invoiceItemsDetails")
      .closest("table")
      .querySelector("thead tr");

    // تحديث رؤوس الأعمدة حسب نوع الخصم
    // if (discountScope === 'items' && discountAmount > 0) {
    //     thead.innerHTML = `
    //         <tr>
    //           <th>الصنف</th>
    //           <th>الكمية</th>
    //           <th>السعر</th>
    //           <th>قبل الخصم</th>
    //           <th>الخصم</th>
    //           <th>بعد الخصم</th>
    //           <th>مرتجع</th>
    //         </tr>
    //     `;
    // } else {
    //     thead.innerHTML = `
    //         <tr>
    //           <th>الصنف</th>
    //           <th>الكمية</th>
    //           <th>السعر</th>
    //           <th>الإجمالي</th>
    //           <th>مرتجع</th>
    //         </tr>
    //     `;
    // }

    // تحديث رؤوس الأعمدة حسب نوع الخصم
    if (discountScope === "items" && discountAmount > 0) {
      // **فواتير عليها خصم بنود: تظهر جميع الأعمدة حتى لو بعض البنود بدون خصم**
      thead.innerHTML = `
            <tr>
            <th>الصنف</th>
            <th>الكمية</th>
            <th>سعر القطعه قبل الخصم</th>
            <th>اجمالي قبل الخصم</th>
            <th>الخصم</th>
            <th>سعر القطعه بعد الخصم</th>
            <th>اجمالي بعد الخصم</th>
            <th>مرتجع</th>
            </tr>
        `;
    } else {
      // **فواتير بدون خصم بنود: تظهر العمود العادي فقط**
      thead.innerHTML = `
            <tr>
            <th>الصنف</th>
            <th>الكمية</th>
            <th>السعر</th>
            <th>الإجمالي</th>
            <th>مرتجع</th>
            </tr>
        `;
    }

    tbody.innerHTML = "";

    if (invoice.items?.length > 0) {
      let totalBeforeDiscount = 0;
      let totalDiscountAmount = 0;
      let totalAfterDiscount = 0;

      // حساب المجاميع
      invoice.items.forEach((item) => {
        const itemDiscountAmount = parseFloat(
          item.discount_amount || item.item_discount_amount || 0
        );
        const itemBeforeDiscount = parseFloat(
          item.total_before_discount ||
            item.item_total_before_discount ||
            item.quantity * (item.selling_price || item.price || 0)
        );
        const itemAfterDiscount = itemBeforeDiscount - itemDiscountAmount;

        totalBeforeDiscount += itemBeforeDiscount;
        totalDiscountAmount += itemDiscountAmount;
        totalAfterDiscount += itemAfterDiscount;
      });

      // عرض البنود
      invoice.items.forEach((item) => {
        const row = this.createModalItemRowWithDiscount(
          item,
          discountScope,
          invoice.discount_amount || 0
        );
        tbody.appendChild(row);
      });

      // إضافة صف الإجماليات - التعديل هنا
      const totalRow = document.createElement("tr");
      totalRow.className = "table-active fw-bold";

      if (discountScope === "items" && discountAmount > 0) {
        const totalDiscountPercentage =
          totalBeforeDiscount > 0
            ? (totalDiscountAmount / totalBeforeDiscount) * 100
            : 0;

        totalRow.innerHTML = `
                <td colspan="3" class="text-end">المجاميع:</td>
                <td class="text-muted">
                    ${totalBeforeDiscount.toFixed(2)} ج.م
                </td>
                <td class="text-danger">
                    -${totalDiscountAmount.toFixed(2)} ج.م
                    <div class="small text-muted">
                        (${totalDiscountPercentage.toFixed(1)}%)
                    </div>
                </td>
                <td class="text-success">
                    ${totalAfterDiscount.toFixed(2)} ج.م
                </td>
                <td></td>
            `;
      } else if (discountAmount > 0) {
        // خصم على الفاتورة ككل
        const discountPercentage =
          discountType === "percent"
            ? discountValue
            : (discountAmount / beforeDiscount) * 100;

        totalRow.innerHTML = `
                <td colspan="${
                  discountScope === "items" ? "4" : "3"
                }" class="text-end">المبلغ الإجمالي:</td>
                <td colspan="${discountScope === "items" ? "3" : "2"}">
                    <div class="amount-with-discount">
                        <div class="amount-original text-muted text-decoration-line-through">
                            ${beforeDiscount.toFixed(2)} ج.م
                        </div>
                        <div class="amount-final">
                            ${afterDiscount.toFixed(2)} ج.م
                        </div>
                        <div class="discount-badge badge ${
                          discountScope === "items" ? "bg-info" : "bg-secondary"
                        }">
                            ${
                              discountScope === "items"
                                ? '<i class="fas fa-tag me-1"></i>'
                                : '<i class="fas fa-file-invoice me-1"></i>'
                            }
                            ${
                              discountType === "percent"
                                ? `${discountValue}%`
                                : `${discountAmount.toFixed(2)} ج.م`
                            } خصم
                        </div>
                    </div>
                </td>
            `;
      } else {
        // بدون خصم
        totalRow.innerHTML = `
                <td colspan="${
                  discountScope === "items" ? "4" : "3"
                }" class="text-end">المبلغ الإجمالي:</td>
                <td colspan="${
                  discountScope === "items" ? "3" : "2"
                }" class="fw-bold">
                    ${afterDiscount.toFixed(2)} ج.م
                </td>
            `;
      }

      // بدلاً من:
    }
  },
  setupItemDiscountTooltips() {
    const tooltipTriggerList = document.querySelectorAll(
      '[data-bs-toggle="tooltip"]'
    );
    tooltipTriggerList.forEach((tooltipTriggerEl) => {
      new bootstrap.Tooltip(tooltipTriggerEl, {
        html: true,
        boundary: "viewport",
      });
    });
  },
  createModalItemRow(item) {
    const row = document.createElement("tr");

    const currentQuantity =
      item.current_quantity || item.quantity - (item.returned_quantity || 0);
    const currentTotal =
      item.current_total ||
      currentQuantity * (item.selling_price || item.price || 0);
    const originalTotal =
      item.quantity * (item.selling_price || item.price || 0);

    let itemStatus = "سليم";
    let rowClass = "";

    if (item.fully_returned || item.returned_quantity >= item.quantity) {
      itemStatus = '<span class="badge bg-danger">مرتجع كلي</span>';
      rowClass = "fully-returned";
    } else if (item.returned_quantity > 0) {
      itemStatus = '<span class="badge bg-warning">مرتجع جزئي</span>';
      rowClass = "partially-returned";
    }

    row.className = rowClass;
    row.innerHTML = `
                <td>
                    <strong>${item.product_name || "منتج"}</strong>
                    ${
                      item.returned_quantity > 0
                        ? `<div class="mt-1">
                            <span class="badge bg-warning return-history-badge">
                                مرتجع: ${item.returned_quantity}
                            </span>
                        </div>`
                        : ""
                    }
                </td>



                <td>
                    <div class="d-flex flex-column">
                    ${
                      item.returned_quantity > 0
                        ? `  <span class="text-muted small">أصلي: ${item.quantity}</span>
                        <span class="fw-bold mt-1">حالي: ${currentQuantity}</span>`
                        : `<span class="text-muted small">أصلي: ${item.quantity}</span>`
                    }
                    </div>
                </td>

                <td>${(item.selling_price || item.price || 0).toFixed(
                  2
                )} ج.م</td>
                
                <td>

                    <div class="d-flex flex-column">
        ${
          item.returned_quantity >= item.quantity
            ? `<span class="text-muted small" style="text-decoration: line-through;">
            ${originalTotal.toFixed(2)} ج.م
        </span>`
            : ""
        }

    <span class="fw-bold mt-1">${currentTotal.toFixed(2)} ج.م</span>

                    </div>
                </td>
                <td>${item.returned_quantity || 0}</td>
            `;

    return row;
  },

  // ========== دوال مساعدة ==========

  getInvoiceStatusText(status) {
    const statusMap = {
      pending: "مؤجل",
      partial: "جزئي",
      paid: "مسلم",
      returned: "مرتجع",
    };
    return statusMap[status] || status;
  },

  filterInvoices(invoices) {
    // نفس الكود الأصلي
    let filtered = [...invoices];

    if (AppData.activeFilters.dateFrom) {
      filtered = filtered.filter(
        (inv) => inv.date >= AppData.activeFilters.dateFrom
      );
    }
    if (AppData.activeFilters.dateTo) {
      filtered = filtered.filter(
        (inv) => inv.date <= AppData.activeFilters.dateTo
      );
    }
    if (AppData.activeFilters.invoiceType) {
      filtered = filtered.filter(
        (inv) => inv.status === AppData.activeFilters.invoiceType
      );
    }
    if (AppData.activeFilters.invoiceId) {
      filtered = filtered.filter(
        (inv) => inv.id === AppData.activeFilters.invoiceId
      );
    }
    if (AppData.activeFilters.productSearch) {
      const searchTerm = AppData.activeFilters.productSearch.toLowerCase();
      // هنا سنحتاج لتحسين الـ API لدعم البحث في البنود
      filtered = filtered.filter(
        (inv) =>
          inv.description?.toLowerCase().includes(searchTerm) ||
          inv.items?.some((item) =>
            item.product_name.toLowerCase().includes(searchTerm)
          ) ||
          inv.invoice_number?.toString().includes(searchTerm)
      );
    }

    return filtered;
  },

  updateStatsCards(summary) {
    if (!summary) return;

    // 1. تحديث الأعداد (كما كان)
    document.getElementById("totalInvoicesCount").textContent =
      summary.total_invoices || 0;
    document.getElementById("pendingInvoicesCount").textContent =
      summary.pending_count || 0;
    document.getElementById("partialInvoicesCount").textContent =
      summary.partial_count || 0;
    document.getElementById("paidInvoicesCount").textContent =
      summary.paid_count || 0;
    // document.getElementById("returnedInvoicesCount").textContent =
    //   summary.returned_count || 0;

    // 2. تحديث المبالغ - هذا هو المطلوب
    this.updateAmounts(summary);
  },

  updateAmounts(summary) {
    // دالة تنسيق المبلغ
    const formatCurrency = (amount) => {
      const num = parseFloat(amount || 0);
      return num.toFixed(2) + " ج.م";
    };

    // تحديث كل كارت حسب data-filter
    const cards = document.querySelectorAll(".invoice-stat-card");

    cards.forEach((card) => {
      const filter = card.getAttribute("data-filter");
      const amountElement = card.querySelector(".stat-amount");

      if (!amountElement) return;

      let amount = 0;

      // تحديد المبلغ حسب نوع الكارت
      switch (filter) {
        case "all":
          amount = summary.total_amount || summary.total_invoices || 0;
          break;
        case "pending":
          amount = summary.pending_amount || summary.pending_count || 0;
          break;
        case "partial":
          amount = summary.partial_amount || summary.partial_count || 0;
          break;
        case "paid":
          amount = summary.paid_amount || summary.paid_count || 0;
          break;
        case "returned":
          amount = summary.returned_amount || summary.returned_count || 0;
          break;
      }

      // تحديث النص
      amountElement.textContent = formatCurrency(amount);

      // الحفاظ على اللون الأصلي من HTML
      // HTML فيه: text-primary, text-warning, text-info, etc
      // لنغيرها، فقط تأكد من وجود اللون
    });
  },

  updateSelectedCount() {
    const selectedCount = document.querySelectorAll(
      ".invoice-checkbox:checked"
    ).length;
    const printBtn = document.getElementById("printSelectedInvoices");
    if (printBtn) {
      printBtn.disabled = selectedCount === 0;
      printBtn.innerHTML = `<i class="fas fa-print me-2"></i>طباعة (${selectedCount})`;
    }
  },

  showLoading() {
    const tbody = document.getElementById("invoicesTableBody");
    tbody.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">جاري التحميل...</span>
                        </div>
                        <p class="mt-2 text-muted">جاري تحميل الفواتير...</p>
                    </td>
                </tr>
            `;
  },

  showError(message) {
    const tbody = document.getElementById("invoicesTableBody");
    tbody.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center py-5">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            ${message}
                        </div>
                        <button class="btn btn-sm btn-outline-primary mt-2" 
                                onclick="InvoiceManager.loadCustomerInvoices()">
                            <i class="fas fa-redo me-1"></i> إعادة المحاولة
                        </button>
                    </td>
                </tr>
            `;
  },

  showModalLoading() {
    const modalBody = document.querySelector("#invoiceItemsModal .modal-body");
    const loadingDiv = document.createElement("div");
    loadingDiv.className = "modal-loading";
    loadingDiv.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">جاري التحميل...</span>
                    </div>
                    <p class="mt-2">جاري تحميل تفاصيل الفاتورة...</p>
                </div>
            `;
    modalBody.appendChild(loadingDiv);
  },

  hideModalLoading() {
    const loadingDiv = document.querySelector(".modal-loading");
    if (loadingDiv) loadingDiv.remove();
  },

  showModalError(message) {
    const modalBody = document.querySelector("#invoiceItemsModal .modal-body");
    const errorDiv = document.createElement("div");
    errorDiv.className = "alert alert-danger text-center";
    errorDiv.innerHTML = `
                <i class="fas fa-exclamation-triangle me-2"></i>
                ${message}
            `;
    modalBody.appendChild(errorDiv);
  },

  setupGlobalListeners() {
    // تحديث عند تغيير الفلاتر
    document
      .getElementById("invoiceTypeFilter")
      ?.addEventListener("change", (e) => {
        AppData.activeFilters.invoiceType = e.target.value || null;
        this.loadCustomerInvoices();
      });

    // تحديث عند تغيير التواريخ
    ["dateFrom", "dateTo"].forEach((id) => {
      document.getElementById(id)?.addEventListener("change", (e) => {
        AppData.activeFilters[id] = e.target.value;
        this.loadCustomerInvoices();
      });
    });

    document.addEventListener("click", function (e) {
      if (e.target.closest("#printSelectedInvoices")) {
        PrintManager.printMultipleInvoices();
      }
    });
  },

  // دوال أخرى كما هي
  getInvoiceById(invoiceId) {
    return AppData.invoices.find((inv) => inv.id === invoiceId);
  },

  selectAllInvoices() {
    document.querySelectorAll(".invoice-checkbox").forEach((checkbox) => {
      checkbox.checked = true;
    });
    this.updateSelectedCount();
  },

  selectNonWorkOrderInvoices() {
    document.querySelectorAll(".invoice-checkbox").forEach((checkbox) => {
      const invoiceId = parseInt(checkbox.getAttribute("data-invoice-id"));
      const invoice = this.getInvoiceById(invoiceId);
      checkbox.checked = !invoice?.workOrderId;
    });
    this.updateSelectedCount();
  },
};

export default InvoiceManager;
