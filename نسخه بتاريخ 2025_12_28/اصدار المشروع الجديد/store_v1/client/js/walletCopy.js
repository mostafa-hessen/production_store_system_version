
const WalletManager = {
    processWalletDeposit() {
        const amount = parseFloat(
            document.getElementById("depositAmount").value
        );
        const description = document
            .getElementById("depositDescription")
            .value.trim();
        
            const date= document.getElementById("depositDate").value;

        if (!amount || amount <= 0) {
            Swal.fire("تحذير", "يرجى إدخال مبلغ صحيح للإيداع", "warning");
            return;
        }

        if (!description) {
            Swal.fire("تحذير", "يرجى إدخال وصف للإيداع", "warning");
            return;
        }



        Swal.fire(
            "نجاح",
            `تم إيداع ${amount.toFixed(2)} ج.م في المحفظة بنجاح`,
            "success"
        );

        // إغلاق المودال وإعادة التعيين
        const modal = bootstrap.Modal.getInstance(
            document.getElementById("walletDepositModal")
        );
        modal.hide();

        document.getElementById("walletDepositForm").reset();
    },

    processWalletWithdraw() {// تحقق من قيمة السحب
        const available = parseFloat(document.getElementById("walletAvailableAmount").innerText);
        const amount = parseFloat(this.value);
        const warning = document.getElementById("withdrawWarning");

        if (amount > available) {
            warning.style.display = "block";
            document.getElementById("confirmWithdrawBtn").disabled = true;
        } else {
            warning.style.display = "none";
            document.getElementById("confirmWithdrawBtn").disabled = false;
        }
    }
}
export default WalletManager;

