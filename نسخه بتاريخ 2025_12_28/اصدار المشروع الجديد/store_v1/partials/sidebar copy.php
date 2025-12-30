<?php
// session أو تعريف المستخدم لو عاوز
$user = isset($_SESSION['username']) ? $_SESSION['username'] : "admin";

// تحديد الصفحة الحالية لتفعيل Active
$current_page = basename($_SERVER['PHP_SELF']);

// جلب الإشعارات من قاعدة البيانات (مثال)
$notifications = [
    [
        'type' => 'low_stock',
        'message' => 'منتج على وشك النفاذ',
        'icon' => 'fas fa-exclamation-triangle',
        'color' => 'warning',
        'url' => BASE_URL . 'admin/low_stock_report.php',
      
    ],
    [
        'type' => 'new_customer',
        'message' => 'عميل جديد مسجل',
        'icon' => 'fas fa-user-plus',
        'color' => 'success',
        'url' => BASE_URL . 'customer/insert.php',
  
    ],
  
];

// حساب إجمالي الإشعارات
$total_notifications = 0;
foreach ($notifications as $notif) {
    // $total_notifications += $notif['count'];
}

// تعريف أهم الاختصارات مع ترتيب حسب الأهمية
$shortcuts = [
    [
        'title' => 'إنشاء فاتورة',
        'icon' => 'fas fa-file-invoice-dollar',
        'url' => BASE_URL . 'invoice_out/create_invoice.php',
        'color' => 'primary',
        'badge' => 'جديد',
        'priority' => 1
    ],
    [
        'title' => 'العملاء',
        'icon' => 'fas fa-users',
        'url' => BASE_URL . 'admin/manage_customer.php',
        'color' => 'success',
        'badge' => '',
        'priority' => 2
    ],
    [
        'title' => 'المنتجات',
        'icon' => 'fas fa-boxes',
        'url' => BASE_URL . 'admin/manage_products.php',
        'color' => 'info',
        'badge' => '',
        'priority' => 3
    ],
    [
        'title' => 'المشتريات',
        'icon' => 'fas fa-shopping-cart',
        'url' => BASE_URL . 'admin/manage_purchase_invoices.php',
        'color' => 'warning',
        'badge' => '',
        'priority' => 4
    ],
    [
        'title' => 'التقارير',
        'icon' => 'fas fa-chart-pie',
        'url' => BASE_URL . 'admin/gross_profit_report.php',
        'color' => 'danger',
        'badge' => '',
        'priority' => 5
    ],
    [
        'title' => 'منتجات قاربت على النفاذ',
        'icon' => 'fas fa-exclamation-circle',
        'url' => BASE_URL . 'admin/low_stock_report.php',
        'color' => 'warning',
        // 'badge' => $notifications[0]['count'] ?? 0,
        'priority' => 6
    ]
];

// ترتيب الاختصارات حسب الأولوية
usort($shortcuts, function($a, $b) {
    return $a['priority'] <=> $b['priority'];
});
?>

<!-- الهيدر -->
<header class="main-header">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center">
            
            <!-- الجزء الأيسر (الشعار وزر القائمة) -->
            <div class="d-flex align-items-center">
                <button class="btn btn-sm btn-light me-3" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="brand-logo d-flex align-items-center">
                    <div class="logo-icon me-2">
                        <i class="fas fa-warehouse"></i>
                    </div>
                    <div>
                        <h1 class="h4 mb-0 system-name">نظام إدارة المخازن</h1>
                        <small class="text-muted d-none d-md-block">الإصدار 2.0</small>
                    </div>
                </div>
            </div>

            <!-- الجزء الأوسط (الاختصارات - تظهر فقط على الشاشات المتوسطة والكبيرة) -->
            <div class="d-none d-md-flex align-items-center mx-3 shortcuts-container">
                <!-- اختصارات رئيسية -->
                <div class="shortcuts-wrapper">
                    <?php foreach (array_slice($shortcuts, 0, 4) as $shortcut): ?>
                    <a href="<?php echo $shortcut['url']; ?>" class="shortcut-link btn btn-sm btn-<?php echo $shortcut['color']; ?> mx-1 position-relative"
                       data-bs-toggle="tooltip" data-bs-placement="bottom" title="<?php echo $shortcut['title']; ?>">
                        <i class="<?php echo $shortcut['icon']; ?>"></i>
                        <?php if (!empty($shortcut['badge'])): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?php echo $shortcut['badge']; ?>
                        </span>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                
                <!-- زر المزيد للاختصارات الإضافية -->
                <div class="dropdown">
                    <button class="btn btn-sm btn-light dropdown-toggle more-shortcuts" type="button" 
                            id="moreShortcuts" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-ellipsis-h"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg" aria-labelledby="moreShortcuts" style="min-width: 250px;">
                        <li class="dropdown-header">
                            <i class="fas fa-bolt me-2"></i>اختصارات سريعة
                        </li>
                        <?php foreach (array_slice($shortcuts, 4) as $shortcut): ?>
                        <li>
                            <a class="dropdown-item d-flex align-items-center py-2" href="<?php echo $shortcut['url']; ?>">
                                <div class="icon-container me-3">
                                    <i class="<?php echo $shortcut['icon']; ?> fa-fw text-<?php echo $shortcut['color']; ?>"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold"><?php echo $shortcut['title']; ?></div>
                                    <?php if (!empty($shortcut['badge'])): ?>
                                    <small class="text-muted"><?php echo $shortcut['badge']; ?> تنبيه</small>
                                    <?php endif; ?>
                                </div>
                                <i class="fas fa-chevron-left text-muted"></i>
                            </a>
                        </li>
                        <?php endforeach; ?>
                        <li><hr class="dropdown-divider"></li>
                        <!-- <li>
                            <a class="dropdown-item d-flex align-items-center text-center justify-content-center py-2" href="#" id="customizeShortcuts">
                                <i class="fas fa-sliders-h me-2"></i>تخصيص الاختصارات
                            </a>
                        </li> -->
                    </ul>
                </div>
            </div>

            <!-- الجزء الأيمن (المستخدم والإعدادات) -->
            <div class="d-flex align-items-center">
                
                <!-- اختصار سريع للجوال -->
                <div class="dropdown d-md-none me-2">
                    <button class="btn btn-sm btn-light dropdown-toggle" type="button" 
                            id="mobileShortcuts" data-bs-toggle="dropdown">
                        <i class="fas fa-bolt text-warning"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-3 shadow" style="min-width: 280px;">
                        <h6 class="dropdown-header mb-2">
                            <i class="fas fa-rocket me-2"></i>الوصول السريع
                        </h6>
                        <div class="row g-2">
                            <?php foreach (array_slice($shortcuts, 0, 4) as $shortcut): ?>
                            <div class="col-6">
                                <a href="<?php echo $shortcut['url']; ?>" class="btn btn-<?php echo $shortcut['color']; ?> btn-hover w-100 d-flex flex-column align-items-center py-2">
                                    <i class="<?php echo $shortcut['icon']; ?> fa-lg mb-1"></i>
                                    <small class="text-truncate" style="max-width: 100px;"><?php echo $shortcut['title']; ?></small>
                                    <?php if (!empty($shortcut['badge'])): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                        <?php echo $shortcut['badge']; ?>
                                    </span>
                                    <?php endif; ?>
                                </a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- زر البحث السريع -->
                <div class="dropdown me-2 quick-search">
                    <button class="btn btn-sm btn-light" type="button" id="quickSearchBtn" data-bs-toggle="dropdown">
                        <i class="fas fa-search"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-3 shadow" style="min-width: 300px;">
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="ابحث عن منتج، عميل، فاتورة..." id="globalSearch">
                            <button class="btn btn-primary" type="button">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">اضغط Ctrl+K للبحث السريع</small>
                        </div>
                    </div>
                </div>

                <!-- زر تغيير السمة -->
                <button id="themeToggle" class="btn btn-sm btn-light me-2 theme-toggle">
                    <i class="fas fa-sun"></i>
                </button>

                <!-- زر النسخ الاحتياطي -->
                <button id="backupBtn" class="btn btn-sm btn-success me-2 backup-btn">
                    <i class="fas fa-database me-1"></i>
                    <span class="d-none d-md-inline">نسخة احتياطية</span>
                </button>

                <!-- زر الإشعارات -->
                <div class="dropdown me-2">
                    <button class="btn btn-sm btn-light position-relative" type="button" id="notificationsDropdown" data-bs-toggle="dropdown">
                        <i class="fas fa-bell"></i>
                        <?php if ($total_notifications > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger pulse">
                            <?php echo $total_notifications; ?>
                        </span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end shadow-lg" aria-labelledby="notificationsDropdown" style="min-width: 350px;">
                        <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                            <h6 class="mb-0">
                                <i class="fas fa-bell me-2"></i>الإشعارات
                            </h6>
                            <!-- <span class="badge bg-primary"><?php echo $total_notifications; ?> جديد</span> -->
                        </div>
                        <div class="notifications-list" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($notifications as $notification): ?>
                            <a href="<?php echo $notification['url']; ?>" class="dropdown-item notification-item">
                                <div class="d-flex align-items-start py-2">
                                    <div class="notification-icon me-3">
                                        <i class="<?php echo $notification['icon']; ?> fa-lg text-<?php echo $notification['color']; ?>"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-semibold"><?php echo $notification['message']; ?></div>
                                        <div class="d-flex justify-content-between align-items-center mt-1">
                                            <!-- <small class="text-muted"><?php echo $notification['time']; ?></small> -->
                                            <!-- <?php if ($notification['count'] > 0): ?>
                                            <span class="badge bg-<?php echo $notification['color']; ?>"><?php echo $notification['count']; ?></span>
                                            <?php endif; ?> -->
                                        </div>
                                    </div>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <!-- <div class="dropdown-footer text-center p-2 border-top">
                            <a href="#" class="text-decoration-none">
                                <small>عرض كل الإشعارات</small>
                            </a>
                        </div> -->
                    </div>
                </div>

                <!-- الملف الشخصي -->
                <div class="dropdown profile-dropdown">
                    <button class="btn btn-light dropdown-toggle d-flex align-items-center" type="button" 
                            id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="user-avatar me-2">
                            <i class="fas fa-user-circle fa-lg"></i>
                        </div>
                        <div class="user-info d-none d-md-block text-start">
                            <div class="fw-semibold" style="font-size: 0.9rem;"><?php echo $user; ?></div>
                            <small class="text-muted" style="font-size: 0.75rem;">مدير النظام</small>
                        </div>
                        <i class="fas fa-chevron-down ms-2"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg" aria-labelledby="userDropdown">
                        <li class="dropdown-header">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <i class="fas fa-user-circle fa-2x text-primary"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold"><?php echo $user; ?></div>
                                    <small class="text-muted">مدير النظام</small>
                                </div>
                            </div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <!-- <li>
                            <a class="dropdown-item d-flex align-items-center" href="#">
                                <i class="fas fa-user me-3"></i>
                                <span>الملف الشخصي</span>
                            </a>
                        </li> -->
                        <li>
                            <a class="dropdown-item d-flex align-items-center" href="<?php echo BASE_URL; ?>admin/manage_users.php">
                                <i class="fas fa-cog me-3"></i>
                                <span>الإعدادات</span>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center" href="#">
                                <i class="fas fa-shield-alt me-3"></i>
                                <span>الأمان</span>
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center text-danger" href="<?php echo BASE_URL; ?>auth/logout.php">
                                <i class="fas fa-sign-out-alt me-3"></i>
                                <span>تسجيل الخروج</span>
                            </a>
                        </li>
                    </ul>
                </div>

            </div>
        </div>
    </div>
</header>

<!-- السايدبار -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header d-flex justify-content-between align-items-center p-3">
        <div class="d-flex align-items-center">
            <div class="logo-icon me-2">
                <i class="fas fa-warehouse fa-lg text-white"></i>
            </div>
            <h5 class="mb-0 text-white fw-semibold">القائمة الرئيسية</h5>
        </div>
        <button class="btn btn-sm btn-outline-light rounded-circle" id="sidebarClose" style="width: 32px; height: 32px;">
            <i class="fas fa-times"></i>
        </button>
    </div>
    
    <div class="sidebar-body">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'welcome.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>user/welcome.php">
                    <i class="fas fa-tachometer-alt me-3"></i>
                    <span>لوحة التحكم</span>
                </a>
            </li>
            
            <li class="nav-item mt-3">
                <small class="text-white-50 px-3">إدارة المنتجات</small>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'manage_products.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/manage_products.php">
                    <i class="fas fa-boxes me-3"></i>
                    <span>المنتجات</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'low_stock_report.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/low_stock_report.php">
                    <i class="fas fa-exclamation-triangle me-3"></i>
                    <span>منتجات قاربت على النفاذ</span>
                    <!-- <span class="badge bg-warning float-end"><?php echo $notifications[0]['count'] ?? 0; ?></span> -->
                </a>
            </li>
            
            <li class="nav-item mt-3">
                <small class="text-white-50 px-3">إدارة العملاء</small>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'manage_customer.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/manage_customer.php">
                    <i class="fas fa-users me-3"></i>
                    <span>العملاء</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo BASE_URL; ?>customer/insert.php">
                    <i class="fas fa-user-plus me-3"></i>
                    <span>إضافة عميل جديد</span>
                </a>
            </li>
            
            <li class="nav-item mt-3">
                <small class="text-white-50 px-3">الفواتير</small>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'create_invoice.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>invoice_out/create.php">
                    <i class="fas fa-file-invoice-dollar me-3"></i>
                    <span>إنشاء فاتورة</span>
                    <span class="badge bg-primary float-end">جديد</span>
                </a>
            </li>
            
            <li class="nav-item mt-3">
                <small class="text-white-50 px-3">المشتريات</small>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'manage_purchase_invoices.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/manage_purchase_invoices.php">
                    <i class="fas fa-shopping-cart me-3"></i>
                    <span>المشتريات</span>
                </a>
            </li>
            
            <li class="nav-item mt-3">
                <small class="text-white-50 px-3">التقارير</small>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'gross_profit_report.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/gross_profit_report.php">
                    <i class="fas fa-chart-pie me-3"></i>
                    <span>التقارير</span>
                </a>
            </li>
            
            <li class="nav-item mt-3">
                <small class="text-white-50 px-3">الإعدادات</small>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'manage_users.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/manage_users.php">
                    <i class="fas fa-cog me-3"></i>
                    <span>الإعدادات</span>
                </a>
            </li>
        </ul>
    </div>
    
    <div class="sidebar-footer p-3 border-top">
        <div class="d-flex justify-content-between align-items-center">
            <small class="text-white-50">آخر تحديث: اليوم</small>
            <span class="badge bg-success">متصل</span>
        </div>
    </div>
</div>

<!-- إضافة ستايلات مخصصة متقدمة -->
<style>
/* تنسيقات عامة */
:root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    --sidebar-width: 270px;
    --header-height: 70px;
}

/* تحسينات الهيدر */
.main-header {
    background: var(--primary-gradient);
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    height: var(--header-height);
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1030;
}

.brand-logo {
    padding: 5px;
    border-radius: 10px;
    background: rgba(255,255,255,0.1);
    backdrop-filter: blur(10px);
}

.logo-icon {
    width: 40px;
    height: 40px;
    background: white;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #667eea;
}

.system-name {
    font-weight: 700;
    background: linear-gradient(45deg, #fff, #f8f9fa);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* تنسيقات الاختصارات */
.shortcuts-container {
    flex-grow: 1;
    justify-content: center;
}

.shortcuts-wrapper {
    display: flex;
    align-items: center;
    background: rgba(255,255,255,0.15);
    border-radius: 50px;
    padding: 8px 15px;
    backdrop-filter: blur(15px);
    border: 1px solid rgba(255,255,255,0.2);
}

.shortcut-link {
    border-radius: 50%;
    width: 45px;
    height: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    text-decoration: none;
    border: 2px solid transparent;
}

.shortcut-link:hover {
    transform: translateY(-5px) scale(1.1);
    box-shadow: 0 10px 20px rgba(0,0,0,0.2);
    border-color: white;
}

.shortcut-link i {
    font-size: 1.1rem;
}

.more-shortcuts {
    border-radius: 50%;
    width: 45px;
    height: 45px;
    margin-left: 10px;
    background: rgba(255,255,255,0.2);
    border: 1px solid rgba(255,255,255,0.3);
    transition: all 0.3s;
}

.more-shortcuts:hover {
    background: rgba(255,255,255,0.3);
    transform: rotate(90deg);
}

/* تحسينات السايدبار */
.sidebar {
    background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
    width: var(--sidebar-width);
    height: 100vh;
    position: fixed;
    left: 0;
    top: var(--header-height);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 5px 0 25px rgba(0,0,0,0.1);
    z-index: 1020;
}

.sidebar.collapsed {
    transform: translateX(calc(var(--sidebar-width) * -1));
}

.sidebar-header {
    background: rgba(0,0,0,0.2);
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.sidebar-body {
    padding: 20px 0;
    height: calc(100vh - var(--header-height) - 100px);
    overflow-y: auto;
}

.sidebar-body::-webkit-scrollbar {
    width: 5px;
}

.sidebar-body::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.2);
    border-radius: 10px;
}

.nav-link {
    padding: 12px 25px;
    color: rgba(255,255,255,0.8);
    border-left: 4px solid transparent;
    transition: all 0.3s;
    margin: 2px 15px;
    border-radius: 8px;
}

.nav-link:hover {
    background: rgba(255,255,255,0.1);
    color: white;
    border-left-color: #667eea;
    padding-left: 30px;
}

.nav-link.active {
    background: linear-gradient(90deg, rgba(102,126,234,0.3) 0%, transparent 100%);
    color: white;
    border-left-color: #667eea;
    font-weight: 600;
}

/* تحسينات الإشعارات */
.notification-item {
    border-left: 3px solid transparent;
    transition: all 0.3s;
    border-bottom: 1px solid rgba(0,0,0,0.05);
}

.notification-item:hover {
    background: rgba(0,123,255,0.05);
    border-left-color: #007bff;
    padding-left: 20px;
}

.pulse {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

/* تحسينات البروفايل */
.profile-dropdown .dropdown-toggle {
    background: rgba(255,255,255,0.2);
    border: 1px solid rgba(255,255,255,0.3);
    border-radius: 50px;
    padding: 5px 15px;
    transition: all 0.3s;
}

.profile-dropdown .dropdown-toggle:hover {
    background: rgba(255,255,255,0.3);
    transform: translateY(-2px);
}

.user-avatar {
    width: 35px;
    height: 35px;
    background: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #667eea;
}

/* أزرار خاصة */
.theme-toggle {
    border-radius: 50%;
    width: 40px;
    height: 40px;
    transition: all 0.5s;
}

.theme-toggle:hover {
    transform: rotate(180deg);
    background: #f8f9fa;
}

.backup-btn {
    border-radius: 20px;
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    border: none;
    transition: all 0.3s;
}

.backup-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(40,167,69,0.3);
}

/* تأثيرات خاصة */
.btn-hover {
    position: relative;
    overflow: hidden;
}

.btn-hover::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 5px;
    height: 5px;
    background: rgba(255,255,255,0.5);
    opacity: 0;
    border-radius: 100%;
    transform: scale(1, 1) translate(-50%);
    transform-origin: 50% 50%;
}

.btn-hover:hover::after {
    animation: ripple 1s ease-out;
}

@keyframes ripple {
    0% {
        transform: scale(0, 0);
        opacity: 0.5;
    }
    100% {
        transform: scale(20, 20);
        opacity: 0;
    }
}

/* تنسيقات للموبايل */
@media (max-width: 768px) {
    .shortcuts-container {
        display: none !important;
    }
    
    .system-name {
        font-size: 1.1rem;
    }
    
    .sidebar {
        width: 250px;
        transform: translateX(-250px);
    }
    
    .sidebar.show {
        transform: translateX(0);
    }
}

/* تنسيقات للكمبيوتر */
@media (min-width: 1200px) {
    .sidebar:not(.collapsed) + .main-content {
        margin-left: var(--sidebar-width);
    }
}
</style>

<!-- سكربتات JavaScript -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    const sidebar = document.getElementById("sidebar");
    const sidebarToggle = document.getElementById("sidebarToggle");
    const sidebarClose = document.getElementById("sidebarClose");
    const mainContent = document.querySelector(".main-content");
    
    // التحكم في السايدبار
    if (sidebarToggle) {
        sidebarToggle.addEventListener("click", () => {
            sidebar.classList.toggle("collapsed");
            if (mainContent) {
                mainContent.classList.toggle("expanded");
            }
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        });
    }
    
    if (sidebarClose) {
        sidebarClose.addEventListener("click", () => {
            sidebar.classList.add("collapsed");
            if (mainContent) {
                mainContent.classList.add("expanded");
            }
            localStorage.setItem('sidebarCollapsed', 'true');
        });
    }
    
    // تحميل حالة السايدبار
    const sidebarCollapsed = localStorage.getItem('sidebarCollapsed');
    if (sidebarCollapsed === 'true') {
        sidebar.classList.add('collapsed');
        if (mainContent) {
            mainContent.classList.add('expanded');
        }
    }
    
    // تفعيل أدوات التلميح
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // البحث السريع
    const quickSearchBtn = document.getElementById('quickSearchBtn');
    const globalSearch = document.getElementById('globalSearch');
    
    if (globalSearch) {
        globalSearch.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performSearch(this.value);
            }
        });
        
        // اختصار Ctrl+K للبحث
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'k') {
                e.preventDefault();
                globalSearch.focus();
            }
        });
    }
    
    function performSearch(query) {
        if (query.trim()) {
            Swal.fire({
                title: 'جاري البحث...',
                text: `عن: ${query}`,
                icon: 'info',
                timer: 1500,
                showConfirmButton: false
            });
        }
    }
    
    // تخصيص الاختصارات
    const customizeBtn = document.getElementById('customizeShortcuts');
    if (customizeBtn) {
        customizeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            showCustomizeModal();
        });
    }
    
    function showCustomizeModal() {
        Swal.fire({
            title: 'تخصيص الاختصارات السريعة',
            html: `
                <div class="text-start">
                    <p class="mb-3">اسحب وأفلت الأيقونات لتغيير ترتيبها:</p>
                    <div id="sortableContainer" class="list-group">
                        <?php foreach ($shortcuts as $index => $shortcut): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center mb-2 border rounded-3" 
                             data-id="<?php echo $index; ?>">
                            <div class="d-flex align-items-center">
                                <div class="shortcut-preview me-3">
                                    <i class="<?php echo $shortcut['icon']; ?> fa-lg text-<?php echo $shortcut['color']; ?>"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold"><?php echo $shortcut['title']; ?></div>
                                    <small class="text-muted"><?php echo str_replace(BASE_URL, '', $shortcut['url']); ?></small>
                                </div>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" checked 
                                       data-shortcut="<?php echo $shortcut['title']; ?>">
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'حفظ التغييرات',
            cancelButtonText: 'إلغاء',
            width: 600,
            didOpen: () => {
                // يمكن إضافة Sortable.js هنا
                if (typeof Sortable !== 'undefined') {
                    new Sortable(document.getElementById('sortableContainer'), {
                        animation: 150,
                        ghostClass: 'bg-light'
                    });
                }
            },
            preConfirm: () => {
                // حفظ التغييرات
                return new Promise((resolve) => {
                    setTimeout(() => {
                        resolve();
                    }, 500);
                });
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire('تم الحفظ!', 'تم حفظ تخصيصات الاختصارات بنجاح', 'success');
            }
        });
    }
});
</script>