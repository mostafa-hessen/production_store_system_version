  <div class="modal fade" id="customReturnModal" tabindex="-1" aria-labelledby="customReturnModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title" id="customReturnModalLabel">
              <i class="fas fa-undo me-2"></i> إدارة مرتجع الفاتورة
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
           
            <div class="card mb-4">
              <div class="card-header bg-light d-flex align-items-center">
                <i class="fas fa-file-invoice me-2 text-primary"></i>
                <h6 class="mb-0 fw-bold">معلومات الفاتورة</h6>
              </div>

              <div class="card-body">

               
                <div class="row g-3 mb-2">
                  <div class="col-md-3">
                    <div class="small text-muted">رقم الفاتورة</div>
                    <div class="fw-bold text-primary" id="returnInvoiceNumber">-</div>
                  </div>

                  <div class="col-md-3">
                    <div class="small text-muted">التاريخ</div>
                    <div class="fw-semibold note-text" id="returnInvoiceDate">-</div>
                  </div>

                  <div class="col-md-3">
                    <div class="small text-muted">إجمالي الفاتورة</div>
                    <div class="fw-bold note-text" id="returnInvoiceTotal">-</div>
                  </div>

                  <div class="col-md-3">
                    <div class="small text-muted">طريقة الدفع</div>
                    <div class="fw-semibold" id="originalPaymentMethod">-</div>
                  </div>
                </div>

                <hr class="my-3">

                <div class="row g-3">
                  <div class="col-md-4">
                    <div class="small text-muted">حالة الدفع</div>
                    <div class="fw-semibold" id="paymentStatus">-</div>
                  </div>

                  <div class="col-md-4">
                    <div class="small text-muted">المبلغ المدفوع</div>
                    <div class="fw-bold text-success" id="invoicePaidAmount">-</div>
                  </div>

                  <div class="col-md-4">
                    <div class="small text-muted">المبلغ المتبقي</div>
                    <div class="fw-bold text-warning" id="invoiceRemainingAmount">-</div>
                  </div>
                </div>

              </div>
            </div>

            <div class="card mb-4">
              <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-boxes me-2"></i>بنود الفاتورة للإرجاع</h6>
                <div>
                  <button type="button" class="btn btn-sm btn-warning me-2" id="returnAllBtn">
                    <i class="fas fa-undo me-1"></i> إرجاع كلي
                  </button>
                  <button type="button" class="btn btn-sm btn-info" id="returnPartialBtn">
                    <i class="fas fa-undo-alt me-1"></i> إرجاع جزئي
                  </button>
                </div>
              </div>
              <div class="card-body">
                <div id="customReturnItemsContainer">
                </div>
              </div>
            </div>

            
            <div class="card mb-4">
              <div class="card-header bg-light">
                <h6 class="mb-0"><i class="fas fa-calculator me-2"></i>ملخص المرتجع</h6>
              </div>
              <div class="card-body">
                <div class="row">
                  <div class="col-md-12">
                    <div class="alert alert-primary">
                      <strong>المبلغ الإجمالي للإرجاع:</strong>
                      <h4 class="mt-2" id="customReturnTotalAmount">0.00 ج.م</h4>
                    </div>
                  </div>
                  <div class="col-md-4">
                                  <div class="alert alert-warning">
                                      <strong>يخصم من المتبقي:</strong>
                                      <h4 class="mt-2" id="deductFromRemaining">0.00 ج.م</h4>
                                  </div>
                              </div>
                              <div class="col-md-4">
                                  <div class="alert alert-info">
                                      <strong>يُرد للعميل:</strong>
                                      <h4 class="mt-2" id="refundToCustomer">0.00 ج.م</h4>
                                  </div>
                              </div> -
                </div>

                <div id="impactDetails" style="display: none;"></div>

                <div class="row mt-3" id="refundMethodSection" style="display: none;">
                  <div class="col-md-12">
                    <div class="border p-3 rounded bg-light">
                      <h6 class="mb-3"><i class="fas fa-money-bill-wave me-2"></i>اختيار طريقة استرداد المبلغ</h6>
                      <div class="row">
                        <div class="col-md-6">
                          <label for="refundMethod" class="form-label fw-bold">طريقة الاسترداد:</label>
                          <select class="form-select" id="refundMethod" required>
                            <option value="cash">استرجاع نقدي</option>
                            <option value="wallet">إضافة للمحفظة</option>
                          </select>
                          <div class="form-text mt-2">
                            <i class="fas fa-info-circle me-1"></i>
                            اختر طريقة استرداد المبلغ للعميل في حالة وجود مبالغ مدفوعة سابقاً
                          </div>
                        </div>
                        <div class="col-md-6">
                          <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>ملاحظة:</strong>
                            <div class="mt-1">
                              في حالة الفواتير الآجلة، يتم خصم من المتبقي أولاً
                              وإذا تجاوز المرتجع المتبقي يتم إرجاع الفرق للعميل
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <!-- بدلاً من العرض الحالي، اقترح هذا التصميم -->

    <div class="distribution-chart mb-4">
      <div class="distribution-item bg-warning" id="deductFromRemainingBar" style="width: 0%">
        <span>يخصم من المتبقي</span>
        <strong id="deductAmount">0 ج.م</strong>
      </div>
      <div class="distribution-item bg-success" id="refundToCustomerBar" style="width: 0%">
        <span>يُرد للعميل</span>
        <strong id="refundAmount">0 ج.م</strong>
      </div>
    </div>
    
    <!-- تفاصيل السيناريو -->
    <div class="scenario-explanation alert" id="scenarioExplanation">
      <i class="fas fa-info-circle me-2"></i>
      <span id="scenarioText">جارٍ تحليل حالة الفاتورة...</span>
    </div>
  </div>
</div> 

            <div class="card">
              <div class="card-header bg-light">
                <h6 class="mb-0"><i class="fas fa-comment me-2"></i>سبب الإرجاع</h6>
              </div>
              <div class="card-body">
                <div class="mb-3">
                  <label for="customReturnReason" class="form-label fw-bold">
                    <i class="fas fa-edit me-1"></i>أدخل سبب الإرجاع
                  </label>
                  <textarea class="form-control" id="customReturnReason" rows="3"
                    placeholder="أدخل سبب الإرجاع هنا..." required></textarea>
                  <div class="form-text">مطلوب لتوثيق عملية الإرجاع</div>
                </div>
              </div>
            </div>

         

          
          <input type="hidden" id="impactData">

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
              <i class="fas fa-times me-1"></i> إلغاء
            </button>
            <button type="button" class="btn btn-primary" id="processCustomReturnBtn" disabled>
              <i class="fas fa-check me-1"></i> معالجة الإرجاع
            </button>
          </div>
        </div>
      </div>


    </div> 




    <!-- <div class="modal fade" id="returnDetailsModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">تفاصيل المرتجع</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div id="returnDetailsContent">
              <!-- سيتم تعبئة المحتوى هنا -->
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
            <button type="button" class="btn btn-primary print-return" id="printReturnBtn">
              <i class="fas fa-print me-1"></i> طباعة
            </button>
          </div>
        </div>
      </div>
    </div> -->



 const ReturnManager = {
    async init() {
        this.setupReturnStyles();
        await this.loadReturnsData();
        this.setupTableEventListeners();
    },

    async loadReturnsData() {
        try {
            const response = await apiService.getReturns(AppData.currentCustomer.id);
            
            if (response.success && response.data) {
                AppData.returns = response.data.map(returnItem => {
                    return {
                        ...returnItem,
                        return_date_formatted: returnItem.return_date ? 
                            new Date(returnItem.return_date).toLocaleDateString('ar-EG') : '',
                        created_at_formatted: returnItem.created_at ? 
                            new Date(returnItem.created_at).toLocaleDateString('ar-EG') : '',
                        // إضافة تفاصيل مالية
                        financial_impact: this.extractFinancialImpact(returnItem)
                    };
                });
                
                this.updateReturnsTable();
            } else {
                AppData.returns = [];
                this.updateReturnsTable();
            }
        } catch (error) {
            console.error('Error loading returns:', error);
            AppData.returns = [];
            this.updateReturnsTable();
        }
    },

    extractFinancialImpact(returnItem) {
        // استخراج التأثير المالي من وصف المرتجع
        const description = returnItem.description || '';
        let impact = {
            from_remaining: 0,
            from_paid: 0,
            refund_method: 'credit_adjustment',
            wallet_before: 0,
            wallet_after: 0
        };
        
        // البحث عن "خصم من المتبقي"
        const remainingMatch = description.match(/خصم من المتبقي: (\d+\.?\d*)/);
        if (remainingMatch) {
            impact.from_remaining = parseFloat(remainingMatch[1]);
        }
        
        // البحث عن "تم الرد نقدي" أو "تم الإضافة للمحفظة"
        if (description.includes('تم الرد نقدي')) {
            impact.refund_method = 'cash';
            const cashMatch = description.match(/بمبلغ (\d+\.?\d*)/);
            if (cashMatch) {
                impact.from_paid = parseFloat(cashMatch[1]);
            }
        } else if (description.includes('تم الإضافة للمحفظة')) {
            impact.refund_method = 'wallet';
            const walletMatch = description.match(/بمبلغ (\d+\.?\d*)/);
            if (walletMatch) {
                impact.from_paid = parseFloat(walletMatch[1]);
            }
            
            // استخراج قبل وبعد المحفظة
            const walletBeforeMatch = description.match(/قبل: (\d+\.?\d*)/);
            const walletAfterMatch = description.match(/بعد: (\d+\.?\d*)/);
            if (walletBeforeMatch && walletAfterMatch) {
                impact.wallet_before = parseFloat(walletBeforeMatch[1]);
                impact.wallet_after = parseFloat(walletAfterMatch[1]);
            }
        }
        
        return impact;
    },

    async showReturnDetails(returnId) {
        try {
            const response = await apiService.getReturnDetails(returnId);
            if (response.success && response.data) {
                this.populateReturnModal(response.data);
                const modal = new bootstrap.Modal(document.getElementById('returnDetailsModal'));
                modal.show();
                AppData.currentReturn = response.data;
            }
        } catch (error) {
            console.error('Error loading return details:', error);
            Swal.fire('خطأ', 'حدث خطأ في تحميل تفاصيل المرتجع', 'error');
        } finally {
            this.hideModalLoading();
        }
    },

    hideModalLoading() {
        const loadingDiv = document.querySelector(".modal-loading");
        if (loadingDiv) loadingDiv.remove();
    },

    populateReturnModal(returnData) {
        const modalContent = document.getElementById('returnDetailsContent');
        
        if (!modalContent) {
            console.error('Modal content element not found');
            return;
        }

        const ret = returnData.return || {};
        const items = returnData.items || [];
        const invoice = returnData.invoice || {};
        const customer = returnData.customer || {};

        // استخراج طريقة الاسترداد
        let refundMethodText = "غير محدد";
        let refundMethodClass = "bg-secondary";
        
        if (ret.refund_preference) {
            switch(ret.refund_preference) {
                case 'cash':
                    refundMethodText = "نقدي";
                    refundMethodClass = "bg-success";
                    break;
                case 'wallet':
                    refundMethodText = "محفظة";
                    refundMethodClass = "bg-primary";
                    break;
                case 'credit_adjustment':
                    refundMethodText = "تعديل رصيد";
                    refundMethodClass = "bg-warning";
                    break;
                case 'auto':
                    refundMethodText = "تلقائي";
                    refundMethodClass = "bg-info";
                    break;
            }
        }

        // استخراج التأثير المالي
        const financialImpact = this.extractFinancialImpact(ret);

        // بناء HTML البنود
        let itemsHtml = '';
        if (items.length > 0) {
            items.forEach(item => {
                itemsHtml += `
                    <tr>
                        <td>${item.product_name || `المنتج ${item.product_id}`}</td>
                        <td>${parseFloat(item.quantity).toFixed(2)}</td>
                        <td>${parseFloat(item.return_price).toFixed(2)} ج.م</td>
                        <td>${parseFloat(item.total_amount).toFixed(2)} ج.م</td>
                        <td>
                            <span class="badge ${item.status === 'restocked' ? 'bg-success' : 
                                              item.status === 'discarded' ? 'bg-danger' : 
                                              'bg-warning'}">
                                ${item.status === 'restocked' ? 'مخزن' : 
                                  item.status === 'discarded' ? 'مهمل' : 
                                  'معلق'}
                            </span>
                        </td>
                    </tr>
                `;
            });
        }

        // بناء HTML التأثير المالي
        let financialHtml = '';
        if (financialImpact.from_remaining > 0 || financialImpact.from_paid > 0) {
            financialHtml = `
                <div class="card mt-3">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>التأثير المالي</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            ${financialImpact.from_remaining > 0 ? `
                            <div class="col-md-6">
                                <div class="alert alert-warning mb-2 py-2">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-minus-circle text-amber fa-lg me-3"></i>
                                        <div>
                                            <div class="small text-muted">خصم من المتبقي</div>
                                            <div class="fw-bold text-amber fs-5">
                                                ${financialImpact.from_remaining.toFixed(2)} ج.م
                                            </div>
                                            <small>يتم تخفيض دين العميل</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            ` : ''}
                            
                            ${financialImpact.from_paid > 0 ? `
                            <div class="col-md-6">
                                <div class="alert alert-success mb-2 py-2">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-undo text-teal fa-lg me-3"></i>
                                        <div>
                                            <div class="small text-muted">مبلغ مسترد</div>
                                            <div class="fw-bold text-teal fs-5">
                                                ${financialImpact.from_paid.toFixed(2)} ج.م
                                            </div>
                                            <small>طريقة الاسترداد: <span class="badge ${refundMethodClass}">${refundMethodText}</span></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            ` : ''}
                        </div>
                        
                        ${financialImpact.refund_method === 'wallet' && financialImpact.wallet_before > 0 ? `
                        <div class="alert alert-primary mt-2">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-wallet fa-lg me-3"></i>
                                <div>
                                    <div class="small text-muted">تأثير على المحفظة</div>
                                    <div class="d-flex align-items-center">
                                        <span class="fw-bold">${financialImpact.wallet_before.toFixed(2)} ج.م</span>
                                        <i class="fas fa-arrow-right mx-3 text-primary"></i>
                                        <span class="fw-bold">${financialImpact.wallet_after.toFixed(2)} ج.م</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        ` : ''}
                    </div>
                </div>
            `;
        }

        modalContent.innerHTML = `
            <div class="container-fluid">
                <!-- معلومات المرتجع -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>معلومات المرتجع</h6>
                            </div>
                            <div class="card-body">
                                <div class="row mb-2">
                                    <div class="col-6">
                                        <small class="text-muted">رقم المرتجع</small>
                                        <div class="fw-bold text-primary fs-5">#RET-${ret.id || 'N/A'}</div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">تاريخ المرتجع</small>
                                        <div class="fw-bold">
                                            ${ret.return_date ? new Date(ret.return_date).toLocaleDateString('ar-EG') : 'N/A'}
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mb-2">
                                    <div class="col-6">
                                        <small class="text-muted">نوع المرتجع</small>
                                        <div>
                                            ${ret.return_type === 'full' ? 
                                                '<span class="badge bg-gradient-3">كامل</span>' : 
                                                ret.return_type === 'partial' ? 
                                                '<span class="badge bg-gradient-2">جزئي</span>' : 
                                                '<span class="badge bg-gradient-1">تبادل</span>'}
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">الحالة</small>
                                        <div>
                                            ${ret.status === 'completed' ? 
                                                '<span class="badge bg-success">مكتمل</span>' : 
                                                ret.status === 'approved' ? 
                                                '<span class="badge bg-info">معتمد</span>' : 
                                                ret.status === 'pending' ? 
                                                '<span class="badge bg-warning">معلق</span>' : 
                                                '<span class="badge bg-danger">مرفوض</span>'}
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-12">
                                        <small class="text-muted">طريقة الاسترداد</small>
                                        <div>
                                            <span class="badge ${refundMethodClass} fs-6">${refundMethodText}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-file-invoice me-2"></i>معلومات الفاتورة</h6>
                            </div>
                            <div class="card-body">
                                <div class="row mb-2">
                                    <div class="col-6">
                                        <small class="text-muted">رقم الفاتورة</small>
                                        <div class="fw-bold">#${invoice.id || ret.invoice_id}</div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">تاريخ الفاتورة</small>
                                        <div>${invoice.created_at ? new Date(invoice.created_at).toLocaleDateString('ar-EG') : 'N/A'}</div>
                                    </div>
                                </div>
                                
                                <div class="row mb-2">
                                    <div class="col-6">
                                        <small class="text-muted">إجمالي الفاتورة</small>
                                        <div class="fw-bold">${invoice.total_after_discount ? parseFloat(invoice.total_after_discount).toFixed(2) : '0.00'} ج.م</div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">المبلغ المرتجع</small>
                                        <div class="fw-bold text-success fs-5">
                                            ${parseFloat(ret.total_amount || 0).toFixed(2)} ج.م
                                        </div>
                                    </div>
                                </div>
                                
                                ${ret.reason ? `
                                <div class="row">
                                    <div class="col-12">
                                        <small class="text-muted">سبب الإرجاع</small>
                                        <div class="alert  mt-1" style="background-color: var(--surface-2); border: 1px solid var(--border); color: var(--text);">
                                            <i class="fas fa-comment me-2"></i>
                                            ${ret.reason}
                                        </div>
                                    </div>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- التأثير المالي -->
                ${financialHtml}

                <!-- بنود المرتجع -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-list-ul me-2"></i>بنود المرتجع</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive custom-table-wrapper">
                                    <table class="custom-table">
                                        <thead class="center">
                                            <tr>
                                                <th>المنتج</th>
                                                <th>الكمية</th>
                                                <th>سعر المرتجع</th>
                                                <th>الإجمالي</th>
                                                <th>حالة الصنف</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${itemsHtml || `
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-4">
                                                    <i class="fas fa-box-open fa-2x mb-3"></i>
                                                    <div>لا توجد بنود</div>
                                                </td>
                                            </tr>`}
                                        </tbody>
                                        <tfoot>
                                            <tr class="table-active">
                                                <td colspan="3" class="text-end fw-bold">المجموع:</td>
                                                <td class="fw-bold text-success fs-5">
                                                    ${parseFloat(ret.total_amount || 0).toFixed(2)} ج.م
                                                </td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    },

    setupReturnStyles() {
        const style = document.createElement('style');
        style.textContent = `
            .return-row {
                transition: all var(--fast);
                border-left: 3px solid transparent;
            }
            .return-row:hover {
                background: var(--surface-2);
                border-left-color: var(--primary);
                transform: translateX(2px);
            }
            
            .badge-return {
                padding: 4px 10px;
                border-radius: 20px;
                font-size: 0.85rem;
                font-weight: 500;
            }
            
            .badge-return-full {
                background: var(--grad-3);
                color: white;
            }
            
            .badge-return-partial {
                background: var(--grad-2);
                color: white;
            }
            
            .badge-method-wallet {
                background: var(--grad-1);
                color: white;
            }
            
            .badge-method-cash {
                background: var(--grad-4);
                color: white;
            }
            
            .badge-method-credit {
                background: linear-gradient(135deg, var(--amber), var(--rose));
                color: white;
            }
            
            .status-badge {
                padding: 5px 12px;
                border-radius: var(--radius-sm);
                font-size: 0.85rem;
                font-weight: 500;
                display: inline-block;
            }
            
            .badge-paid {
                background: linear-gradient(135deg, #10b981, #0ea5e9);
                color: white;
            }
            
            .badge-pending {
                background: linear-gradient(135deg, var(--amber), #f97316);
                color: white;
            }
            
            .items-preview {
                max-height: 100px;
                overflow-y: auto;
                padding-right: 8px;
            }
            
            .amount-display {
                position: relative;
                padding: 8px 12px;
                background: var(--surface-2);
                border-radius: var(--radius-sm);
                border: 1px solid var(--border);
            }
            
            .btn-sm-icon {
                width: 32px;
                height: 32px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border-radius: var(--radius-sm);
                transition: all var(--fast);
            }
            
            .btn-sm-icon:hover {
                transform: translateY(-1px);
                box-shadow: var(--shadow-1);
            }
            
            .refund-option-card {
                border: 2px solid var(--border);
                border-radius: var(--radius);
                padding: 15px;
                cursor: pointer;
                transition: all var(--fast);
                background: var(--surface);
            }
            
            .refund-option-card:hover {
                border-color: var(--primary);
                transform: translateY(-2px);
            }
            
            .refund-option-card.selected {
                border-color: var(--primary);
                background: linear-gradient(135deg, var(--surface), var(--surface-2));
                box-shadow: var(--shadow-1);
            }
            
            .return-modal-card {
                background: var(--surface);
                border: 1px solid var(--border);
                border-radius: var(--radius);
                padding: 15px;
                margin-bottom: 15px;
                transition: all var(--fast);
            }
            
            .return-modal-card:hover {
                box-shadow: var(--shadow-1);
            }
            
            .quantity-input-group {
                position: relative;
            }
            
            .quantity-input-group .input-label {
                position: absolute;
                left: 12px;
                top: 32px;
                color: var(--text-secondary);
                font-size: 0.85rem;
            }
            
            .custom-return-quantity {
                padding-left: 45px;
                font-weight: 600;
            }
            
            .validation-message .alert-sm {
                padding: 6px 10px;
                font-size: 0.85rem;
                margin-bottom: 0;
            }
            
            .bg-gradient-1 { background: linear-gradient(135deg, var(--primary), var(--teal)) !important; color: white; }
            .bg-gradient-2 { background: linear-gradient(135deg, var(--teal), #0ea5e9) !important; color: white; }
            .bg-gradient-3 { background: linear-gradient(135deg, var(--amber), #f97316) !important; color: white; }
            .bg-gradient-4 { background: linear-gradient(135deg, var(--rose), #ec4899) !important; color: white; }
            
            [data-theme="dark"] .return-row:hover {
                background: var(--surface);
                border-left-color: var(--primary);
            }
            
            [data-theme="dark"] .refund-option-card {
                background: var(--surface-2);
            }
            
            [data-theme="dark"] .return-modal-card {
                background: var(--surface-2);
            }
        `;
        document.head.appendChild(style);
    },

    getRefundMethodFromItems(items) {
        if (!items || !Array.isArray(items) || items.length === 0) {
            return "credit_adjustment";
        }
        
        const firstItem = items[0];
        if (!firstItem.refund_preference) {
            return "credit_adjustment";
        }
        
        const method = firstItem.refund_preference.toLowerCase();
        
        if (method.includes('wallet') || method.includes('محفظة')) {
            return "wallet";
        } else if (method.includes('cash') || method.includes('نقدي')) {
            return "cash";
        } else if (method.includes('credit') || method.includes('خصم') || method.includes('آجل')) {
            return "credit_adjustment";
        } else if (method.includes('auto') || method.includes('تلقائي')) {
            return "auto";
        }
        
        return "credit_adjustment";
    },

    updateReturnsTable(data = null) {
        const tbody = document.getElementById("returnsTableBody");
        if (!tbody) return;
        
        const returnsData = data || AppData.returns || [];
        tbody.innerHTML = "";

        if (!returnsData || returnsData.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center py-5">
                        <div class="text-muted">
                            <i class="fas fa-undo fa-2x mb-3"></i>
                            <p>لا توجد مرتجعات</p>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        returnsData.forEach((returnItem) => {
            const row = document.createElement("tr");
            row.className = "return-row";

            // تحديد نوع المرتجع
            let typeBadge = "";
            if (returnItem.return_type === "full") {
                typeBadge = '<span class="badge-return badge-return-full">كامل</span>';
            } else if (returnItem.return_type === "partial") {
                typeBadge = '<span class="badge-return badge-return-partial">جزئي</span>';
            } else if (returnItem.return_type === "exchange") {
                typeBadge = '<span class="badge-return badge-return-partial">تبادل</span>';
            } else {
                typeBadge = '<span class="badge-return badge-return-partial">غير محدد</span>';
            }

            // تحديد طريقة الاسترداد
            let refundMethod = this.getRefundMethodFromItems(returnItem.items);
            let methodBadge = "";
            switch(refundMethod) {
                case "wallet":
                    methodBadge = '<span class="badge bg-primary" title="تم الإضافة للمحفظة">محفظة</span>';
                    break;
                case "cash":
                    methodBadge = '<span class="badge bg-success" title="تم الرد نقدي">نقدي</span>';
                    break;
                case "credit_adjustment":
                    methodBadge = '<span class="badge bg-warning" title="خصم من المتبقي">تعديل رصيد</span>';
                    break;
                case "auto":
                    methodBadge = '<span class="badge bg-info" title="تلقائي">تلقائي</span>';
                    break;
                default:
                    methodBadge = '<span class="badge bg-secondary">' + refundMethod + '</span>';
            }

            // تحديد حالة المرتجع
            let statusBadge = "";
            switch(returnItem.status) {
                case "completed":
                    statusBadge = '<span class="status-badge bg-success">مكتمل</span>';
                    break;
                case "approved":
                    statusBadge = '<span class="status-badge bg-info">معتمد</span>';
                    break;
                case "pending":
                    statusBadge = '<span class="status-badge bg-warning">معلق</span>';
                    break;
                case "rejected":
                    statusBadge = '<span class="status-badge bg-danger">مرفوض</span>';
                    break;
                default:
                    statusBadge = `<span class="status-badge bg-warning">${returnItem.status || 'معلق'}</span>`;
            }

            // حساب بنود المرتجع
            let totalReturnedItems = 0;
            let itemsList = "";
            if (returnItem.items && returnItem.items.length > 0) {
                returnItem.items.forEach((item) => {
                    totalReturnedItems += parseFloat(item.quantity) || 0;
                    itemsList += `
                        <div class="d-flex justify-content-between small border-bottom pb-1 mb-1">
                            <span>${item.product_name || `المنتج ${item.product_id}`}</span>
                            <span>${parseFloat(item.quantity).toFixed(2)}</span>
                        </div>
                    `;
                });
            }

            // تحضير البيانات للعرض
            const dateToDisplay = returnItem.return_date_formatted || 
                                returnItem.created_at_formatted || 
                                (returnItem.return_date ? new Date(returnItem.return_date).toLocaleDateString('ar-EG') : '');
            
            const totalAmount = parseFloat(returnItem.total_amount) || 0;
            const financialImpact = returnItem.financial_impact || {};

            // بناء الـ HTML للصف
            row.innerHTML = `
                <td>
                    <div class="d-flex flex-column">
                        <strong class="text-primary">#RET-${returnItem.id}</strong>
                        <button class="btn btn-sm btn-link p-0 mt-1 view-original-invoice" 
                                data-invoice-id="${returnItem.invoice_id}">
                            <i class="fas fa-external-link-alt me-1"></i> عرض الفاتورة
                        </button>
                    </div>
                </td>
                <td>
                    <div class="d-flex flex-column">
                        <a href="#" class="text-decoration-none view-invoice-from-return" 
                           data-invoice-id="${returnItem.invoice_id}">
                            <span class="fw-bold">#${returnItem.invoice_id}</span>
                        </a>
                        <small class="text-muted mt-1">${returnItem.reason || ''}</small>
                    </div>
                </td>
                <td>
                    <div class="items-preview">
                        ${itemsList || '<small class="text-muted">لا توجد بنود</small>'}
                    </div>
                </td>
                <td>
                    <span class="badge bg-light text-dark">
                        ${totalReturnedItems.toFixed(2)}
                    </span>
                </td>
                <td>
                    <div class="amount-display">
                        <div class="fw-bold text-success">${totalAmount.toFixed(2)} ج.م</div>
                        <small class="text-muted">${typeBadge}</small>
                        ${financialImpact.from_remaining > 0 ? 
                            `<div class="small text-amber mt-1">
                                <i class="fas fa-minus-circle"></i> ${financialImpact.from_remaining.toFixed(2)} من المتبقي
                            </div>` : ''}
                        ${financialImpact.from_paid > 0 ? 
                            `<div class="small text-teal mt-1">
                                <i class="fas fa-undo"></i> ${financialImpact.from_paid.toFixed(2)} مسترد
                            </div>` : ''}
                    </div>
                </td>
                <td>${methodBadge}</td>
                <td>${statusBadge}</td>
                <td>
                    <div class="small text-muted">${dateToDisplay}</div>
                    <div class="small text-muted">
                        ${returnItem.created_by_name ? `بواسطة: ${returnItem.created_by_name}` : ''}
                    </div>
                </td>
                <td>
                    <div class="action-buttons">
                        <button class="btn btn-sm btn-outline-info btn-sm-icon view-return-details" 
                                data-return-id="${returnItem.id}"
                                title="عرض تفاصيل المرتجع">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-primary btn-sm-icon view-original-invoice" 
                                data-invoice-id="${returnItem.invoice_id}"
                                title="عرض الفاتورة">
                            <i class="fas fa-file-invoice"></i>
                        </button>
                        ${returnItem.status === 'pending' ? `
                            <button class="btn btn-sm btn-outline-success btn-sm-icon approve-return" 
                                    data-return-id="${returnItem.id}"
                                    title="اعتماد المرتجع">
                                <i class="fas fa-check"></i>
                            </button>
                        ` : ''}
                    </div>
                </td>
            `;

            tbody.appendChild(row);
        });

        this.setupTableEventListeners();
    },

    setupTableEventListeners() {
        const tbody = document.getElementById("returnsTableBody");
        if (!tbody) return;
        
        tbody.addEventListener('click', async (e) => {
            const viewReturnBtn = e.target.closest('.view-return-details');
            const viewInvoiceBtn = e.target.closest('.view-original-invoice');
            const approveBtn = e.target.closest('.approve-return');
            
            if (viewReturnBtn) {
                const returnId = viewReturnBtn.getAttribute('data-return-id');
                await this.showReturnDetails(returnId);
            }
            
            if (viewInvoiceBtn) {
                const invoiceId = viewInvoiceBtn.getAttribute('data-invoice-id');
                if (typeof InvoiceManager !== 'undefined' && InvoiceManager.showInvoiceDetails) {
                    InvoiceManager.showInvoiceDetails(invoiceId);
                }
            }
            
            if (approveBtn) {
                const returnId = approveBtn.getAttribute('data-return-id');
                await this.approveReturn(returnId);
            }
        });
    },

    async addReturn(returnData) {
        try {
            const response = await apiService.createReturn(returnData);
            
            if (response.success) {
                return {
                    success: true,
                    return_id: response.return_id,
                    message: response.message || 'تم إنشاء الإرجاع بنجاح'
                };
            } else {
                throw new Error(response.message || 'فشل في إنشاء الإرجاع');
            }
        } catch (error) {
            console.error('Error adding return:', error);
            return {
                success: false,
                message: error.message || 'حدث خطأ أثناء إنشاء الإرجاع'
            };
        }
    },

    async approveReturn(returnId) {
        const result = await Swal.fire({
            title: "تأكيد الاعتماد",
            text: "هل أنت متأكد من اعتماد هذا المرتجع؟",
            icon: "question",
            showCancelButton: true,
            confirmButtonText: "نعم، اعتماد",
            cancelButtonText: "إلغاء",
            confirmButtonColor: "var(--primary)",
            background: "var(--surface)",
            color: "var(--text)"
        });

        if (result.isConfirmed) {
            try {
                const response = await apiService.approveReturn(returnId);
                
                if (response.success) {
                    Swal.fire({
                        title: "تم الاعتماد",
                        text: "تم اعتماد المرتجع بنجاح",
                        icon: "success",
                        confirmButtonColor: "var(--primary)"
                    });
                    
                    // تحديث البيانات
                    await this.loadReturnsData();
                } else {
                    Swal.fire({
                        title: "خطأ",
                        text: response.message || "فشل في اعتماد المرتجع",
                        icon: "error"
                    });
                }
            } catch (error) {
                Swal.fire({
                    title: "خطأ",
                    text: "حدث خطأ أثناء الاعتماد",
                    icon: "error"
                });
            }
        }
    }
};