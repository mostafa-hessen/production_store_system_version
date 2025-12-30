     import AppData from './app_data.js';
     import CustomerManager from './customer.js';
     const WalletManager = {
        init() {
          // بيانات حركات المحفظة الابتدائية
          AppData.walletTransactions = [
            {
              id: 1,
              date: "2024-01-18",
              type: "deposit",
              description: "إيداع نقدي",
              amount: 200,
              balanceBefore: 0,
              balanceAfter: 200,
              createdBy: "مدير النظام",
            },
            {
              id: 2,
              date: "2024-01-15",
              type: "payment",
              description: "سداد فاتورة #123",
              amount: -500,
              balanceBefore: 700,
              balanceAfter: 200,
              createdBy: "مدير النظام",
            },
            {
              id: 3,
              date: "2024-01-10",
              type: "deposit",
              description: "إيداع نقدي",
              amount: 500,
              balanceBefore: 200,
              balanceAfter: 700,
              createdBy: "مدير النظام",
            },
            {
              id: 4,
              date: "2024-01-05",
              type: "return",
              description: "مرتجع فاتورة #120",
              amount: 300,
              balanceBefore: 200,
              balanceAfter: 500,
              createdBy: "مدير النظام",
            },
            {
              id: 5,
              date: "2024-01-01",
              type: "deposit",
              description: "إيداع نقدي",
              amount: 200,
              balanceBefore: 0,
              balanceAfter: 200,
              createdBy: "مدير النظام",
            },
          ];

          this.updateWalletTable();
        },

        updateWalletTable() {
          const tbody = document.getElementById("walletTableBody");
          tbody.innerHTML = "";

          AppData.walletTransactions.forEach((transaction) => {
            const row = document.createElement("tr");

            // تحديد لون البادج بناءً على نوع الحركة
            let badgeClass = "bg-secondary";
            if (transaction.type === "payment") {
              badgeClass = "bg-danger";
            } else if (transaction.type === "deposit") {
              badgeClass = "bg-success";
            } else if (transaction.type === "return") {
              badgeClass = "bg-warning";
            }

            // تحديد لون المبلغ
            let amountClass =
              transaction.amount > 0 ? "text-success" : "text-danger";
            let amountSign = transaction.amount > 0 ? "+" : "";

            row.innerHTML = `
                        <td>${transaction.date}</td>
                        <td><span class="badge ${badgeClass}">${this.getTransactionTypeText(
              transaction.type
            )}</span></td>
                        <td>${transaction.description}</td>
                        <td class="${amountClass}">${amountSign}${transaction.amount.toFixed(
              2
            )} ج.م</td>
                        <td>${transaction.balanceBefore.toFixed(2)} ج.م</td>
                        <td>${transaction.balanceAfter.toFixed(2)} ج.م</td>
                        <td>${transaction.createdBy}</td>
                    `;

            tbody.appendChild(row);
          });
        },

        getTransactionTypeText(type) {
          const typeMap = {
            payment: "سداد",
            deposit: "إيداع",
            return: "مرتجع",
          };
          return typeMap[type] || type;
        },

        addTransaction(transactionData) {
          const lastTransaction = AppData.walletTransactions[0];
          const balanceBefore = lastTransaction
            ? lastTransaction.balanceAfter
            : AppData.currentCustomer.walletBalance;

          const newTransaction = {
            id: AppData.nextWalletTransactionId++,
            date:
              transactionData.date || new Date().toISOString().split("T")[0],
            type: transactionData.type,
            description: transactionData.description,
            amount: transactionData.amount,
            balanceBefore: balanceBefore,
            balanceAfter: balanceBefore + transactionData.amount,
            paymentMethods: transactionData.paymentMethods,
            createdBy: AppData.currentUser,
          };

          AppData.walletTransactions.unshift(newTransaction);

          // تحديث رصيد المحفظة
          AppData.currentCustomer.walletBalance += transactionData.amount;

          this.updateWalletTable();
          CustomerManager.updateCustomerInfo();

          return newTransaction;
        },

        getAvailableBalance() {
          return AppData.currentCustomer.walletBalance;
        },

        getStatementTransactions(dateFrom, dateTo) {
          let transactions = [...AppData.walletTransactions];

          if (dateFrom) {
            transactions = transactions.filter((t) => t.date >= dateFrom);
          }

          if (dateTo) {
            transactions = transactions.filter((t) => t.date <= dateTo);
          }

          return transactions;
        },
      };
        export default WalletManager;