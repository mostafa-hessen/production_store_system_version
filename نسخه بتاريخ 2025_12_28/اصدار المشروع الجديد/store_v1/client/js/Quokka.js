// مثال بيانات
const invoice = {
    items: [
        { id: 1, name: "منتج A", qty: 2, price: 50 },
        { id: 2, name: "منتج B", qty: 1, price: 30 },
        { id: 3, name: "منتج C", qty: 5, price: 10 },
    ]
};

invoice.items.forEach(item => {
    item;  // Quokka هيعرضلك قيمة كل item مباشرة inline
});
