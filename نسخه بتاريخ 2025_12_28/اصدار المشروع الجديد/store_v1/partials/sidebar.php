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
          'title' => 'لوحه التحكم',
          'icon' => 'fa fa-dashboard',
          'url' => BASE_URL . 'user/welcome.php',
          'color' => 'danger',
          'badge' => '',
          'priority' => 2

      ],
      [
          'title' => 'إنشاء فاتورة',
          'icon' => 'fas fa-file-invoice-dollar',
          'url' => BASE_URL . 'invoices_out/create_invoice.php',
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
          'priority' => 3
      ],
      [
          'title' => 'المنتجات',
          'icon' => 'fas fa-boxes',
          'url' => BASE_URL . 'admin/manage_products.php',
          'color' => 'info',
          'badge' => '',
          'priority' => 4
      ],
      [
          'title' => 'المشتريات',
          'icon' => 'fas fa-shopping-cart',
          'url' => BASE_URL . 'admin/manage_purchase_invoices.php',
          'color' => 'warning',
          'badge' => '',
          'priority' => 5
      ],
      [
          'title' => 'التقارير',
          'icon' => 'fas fa-chart-pie',
          'url' => BASE_URL . 'admin/gross_profit_report.php',
          'color' => 'danger',
          'badge' => '',
          'priority' => 6
      ],
      [
          'title' => 'منتجات قاربت على النفاذ',
          'icon' => 'fas fa-exclamation-circle',
          'url' => BASE_URL . 'admin/low_stock_report.php',
          'color' => 'warning',
          // 'badge' => $notifications[0]['count'] ?? 0,
          'priority' => 7
      ],
        [
          'title' => 'الموردين', 
          'icon' => 'fas fa-users',
          'url' => BASE_URL . 'admin/manage_suppliers.php',
          'color' => 'info',
          // 'badge' => $notifications[0]['count'] ?? 0,
          'priority' => 8
      ],
  ];

  // ترتيب الاختصارات حسب الأولوية
  usort($shortcuts, function($a, $b) {
      return $a['priority'] <=> $b['priority'];
  });
  ?>
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
      width: 40px;
      height: 40px;
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
      /* background: var(--bg);  */
      /* color: var(--text); */
      transition: background 1s ease ;
      /* background: var(text); */
      /* background: var(text); */
    }
    
    .theme-toggle:hover {
      transform: rotate(180deg);
      background: var(text);
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


  .sidebar {
      background: linear-gradient(180deg, #2c3e50 0%, #1a252f 100%);
      width: 280px;
      height: 100vh;
      position: fixed;
      left: 0;
      top: 0;
      z-index: 1030;
      display: flex;
      flex-direction: column;
      overflow-y: auto;
  }

  .sidebar-header {
      background: rgba(0, 0, 0, 0.2);
      border-bottom: 1px solid rgba(255,255,255,0.1);
      min-height: 70px;
      padding: 1rem;
  }

  .sidebar-header h5 {
      font-size: 1rem;
      font-weight: 600;
  }

  .sidebar-body {
      flex: 1;
      padding: 10px 0;
      overflow-y: auto;
  }

  /* مربع البحث */
  .search-box .form-control {
      border: 1px solid rgba(255,255,255,0.1);
      background: rgba(0,0,0,0.2) !important;
      color: white;
      border-radius: 6px;
      font-size: 0.85rem;
  }

  .search-box .form-control:focus {
      border-color: #667eea;
      box-shadow: 0 0 0 0.2rem rgba(102,126,234,0.25);
  }

  .search-box .form-control::placeholder {
      color: rgba(255,255,255,0.4);
  }

  /* إزالة scrollbar أو جعله مخفي */
  .sidebar::-webkit-scrollbar,
  .sidebar-body::-webkit-scrollbar {
      width: 5px;
  }

  .sidebar::-webkit-scrollbar-track,
  .sidebar-body::-webkit-scrollbar-track {
      background: transparent;
  }

  .sidebar::-webkit-scrollbar-thumb,
  .sidebar-body::-webkit-scrollbar-thumb {
      background: rgba(255,255,255,0.1);
      border-radius: 10px;
  }

  /* تحسينات القائمة */
  .nav-link {
      padding: 12px 20px;
      color: rgba(255,255,255,0.8);
      border-left: 3px solid transparent;
      transition: all 0.3s;
      margin: 2px 10px;
      border-radius: 8px;
      display: flex;
      align-items: center;
  }

  .nav-link:hover {
      background: rgba(255,255,255,0.05);
      color: white;
      padding-right: 25px;
      border-left-color: rgba(102,126,234,0.5);
  }

  .nav-link.active {
      background: linear-gradient(90deg, rgba(102,126,234,0.2) 0%, transparent 100%);
      color: white;
      border-left-color: #667eea;
      font-weight: 500;
  }

  /* القوائم المنسدلة */
  .nav-link.dropdown-toggle {
      position: relative;
  }

  .nav-link.dropdown-toggle::after {
      display: none;
  }

  .nav-link.dropdown-toggle .fa-chevron-down {
      transition: transform 0.3s ease;
      font-size: 0.8rem;
      margin-right: auto;
  }

  .nav-link.dropdown-toggle[aria-expanded="true"] .fa-chevron-down {
      transform: rotate(180deg);
  }

  .collapse {
      background: rgba(0, 0, 0, 0.15);
      border-radius: 0 0 8px 8px;
      margin-top: -2px;
  }

  .collapse .nav-link {
      padding: 10px 20px 10px 40px;
      font-size: 0.9rem;
      margin: 0;
      border-left: none;
  }

  .collapse .nav-link:hover {
      padding-right: 25px;
      border-left: none;
  }

  /* الفواصل */
  .small.text-white-50 {
      font-size: 0.75rem;
      letter-spacing: 0.5px;
      font-weight: 500;
      margin-bottom: 5px;
      display: block;
  }

  /* تحسينات للجوال */
  @media (max-width: 768px) {
      .sidebar {
          width: 280px;
          transform: translateX(-280px);
          transition: transform 0.3s ease;
      }
      
      .sidebar.show {
          transform: translateX(0);
      }
  }
  </style>

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
                  
                      <div>
                          <h1 class="h4 mb-0 system-name">محل الاخوه</h1>
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
                          <span class="position-absolute top-0 start-0 translate-middle badge rounded-pill bg-danger">
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
                  <button id="themeToggle" class="btn btn-sm btn-ligh me-2 theme-toggle">
                      <i class="fas fa-sun"></i>
                  </button>

            
                  <button class="btn btn-sm btn-success me-2 backup-btn"
        data-bs-toggle="modal"
        data-bs-target="#backupModal">
    <i class="fas fa-database me-1"></i>
</button>

            <div class="dropdown me-2">

    <a class=" position-relative m-1 btn btn-sm btn-info dropdown-toggle text-white" href="#" id="notificationsDropdown"
       role="button" data-bs-toggle="dropdown" aria-expanded="false">

        <i class="fas fa-bell fs-5"></i>

        <span class="badge bg-danger position-absolute top-0 start-0 translate-middle d-none">
            0
        </span>
    </a>

    <div class="dropdown-menu dropdown-menu-end shadow-lg p-0"
         aria-labelledby="notificationsDropdown"
         style="min-width:350px;">

        <!-- Header -->
        <div class="notifications-header d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
            <span class="fw-bold">الإشعارات</span>
            <button class="btn btn-sm btn-outline-success" id="markAllReadBtn">
                <i class="fas fa-check-double"></i>
            </button>
        </div>

        <!-- Notifications list (JS fills it) -->
        <div class="notifications-list"
             style="max-height:400px; overflow-y:auto;">
        </div>

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
                       
                          <li>
                              <a class="dropdown-item d-flex align-items-center" href="<?php echo BASE_URL; ?>admin/manage_users.php">
                                  <i class="fas fa-cog me-3"></i>
                                  <span>الإعدادات</span>
                              </a>
                          </li>
                          <li>
                              <a class="dropdown-item d-flex align-items-center" href="<?php echo BASE_URL; ?>admin/registration_settings.php">
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
          <button class="btn btn-sm btn-light sidebar-close d-md-none">
              <i class="fas fa-times"></i>
          </button>
      </div>
      
      <div class="sidebar-body">
          <!-- بحث داخل السايدبار -->
          <div class="search-box px-3 mb-3">
              <div class="input-group input-group-sm">
                  <input type="text" class="form-control bg-dark border-dark text-white" placeholder="بحث في القائمة...">
                  <button class="btn btn-primary" type="button">
                      <i class="fas fa-search"></i>
                  </button>
              </div>
          </div>

          <ul class="nav flex-column">
              <li class="nav-item">
                  <a class="nav-link <?php echo ($current_page == 'welcome.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>user/welcome.php">
                      <i class="fas fa-tachometer-alt me-3"></i>
                      <span>لوحة التحكم</span>
                  </a>
              </li>
              
              <!-- قسم الفواتير -->
              <li class="nav-item mt-3">
                  <small class="text-white-50 px-3">الفواتير</small>
              </li>
              <li class="nav-item">
                  <a class="nav-link <?php echo ($current_page == 'create_invoice.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>invoices_out/create_invoice.php">
                      <i class="fas fa-file-invoice-dollar me-3"></i>
                      <span>إنشاء فاتورة</span>
                      <span class="badge bg-primary float-end">جديد</span>
                  </a>
              </li>
       
              
              <!-- قسم إدارة المنتجات -->
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
                  </a>
              </li>
              
              <!-- قسم إدارة العملاء -->
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
              
              <!-- قسم إدارة الموردين -->
              <li class="nav-item mt-3">
                  <small class="text-white-50 px-3">إدارة الموردين</small>
              </li>
              <li class="nav-item">
                  <a class="nav-link <?php echo ($current_page == 'manage_suppliers.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/manage_suppliers.php">
                      <i class="fas fa-truck me-3"></i>
                      <span>الموردين</span>
                  </a>
              </li>
              
              <!-- قسم المشتريات -->
              <li class="nav-item mt-3">
                  <small class="text-white-50 px-3">المشتريات</small>
              </li>
              <li class="nav-item">
                  <a class="nav-link <?php echo ($current_page == 'manage_purchase_invoices.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/manage_purchase_invoices.php">
                      <i class="fas fa-shopping-cart me-3"></i>
                      <span>المشتريات</span>
                  </a>
              </li>
   
              
              <!-- قسم التقارير مع قائمة منسدلة -->
              <li class="nav-item mt-3">
                  <small class="text-white-50 px-3">التقارير</small>
              </li>
              <li class="nav-item">
                  <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="collapse" data-bs-target="#reportsCollapse" aria-expanded="false">
                      <i class="fas fa-chart-pie me-3"></i>
                      <span>التقارير</span>
                      <i class="fas fa-chevron-down ms-auto"></i>
                  </a>
                  <div class="collapse" id="reportsCollapse">
                      <ul class="nav flex-column ps-4">
                          <li class="nav-item">
                              <a class="nav-link <?php echo ($current_page == 'gross_profit_report.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/gross_profit_report.php">
                                  <i class="fas fa-chart-line me-2"></i>
                                  <span>الأرباح الإجمالية</span>
                              </a>
                          </li>
                          <li class="nav-item">
                              <a class="nav-link" href="<?php echo BASE_URL; ?>admin/net_profit_report.php">
                                  <i class="fas fa-money-bill-wave me-2"></i>
                                  <span>صافي الأرباح</span>
                              </a>
                          </li>
                          <li class="nav-item">
                              <a class="nav-link" href="<?php echo BASE_URL; ?>admin/sales_report_period.php">
                                  <i class="fas fa-chart-bar me-2"></i>
                                  <span>تقارير المبيعات</span>
                              </a>
                          </li>
                        
                      </ul>
                  </div>
              </li>
              
           
              <!-- قسم الإعدادات -->
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
  </div>
<!-- Backup Modal -->
<div class="modal fade" id="backupModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">
          <i class="fas fa-database me-2"></i> النسخ الاحتياطي
        </h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <label class="form-label">مكان حفظ النسخة</label>
        <select id="backupPath" class="form-select">
          <option value="drive">Google Drive</option>
          <option value="local">داخل المشروع</option>
          <!-- <option value="drive_d">Drive D</option>
          <option value="monthly">نسخ شهرية</option> -->
        </select>

        <div class="form-check mt-3">
          <input class="form-check-input" type="checkbox" id="zipBackup">
          <label class="form-check-label">
            ضغط النسخة (ZIP)
          </label>
        </div>

      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
        <button class="btn btn-success" id="confirmBackup">
          <i class="fas fa-play me-1"></i> تنفيذ
        </button>
      </div>

    </div>
  </div>
</div>


  <script>
  document.addEventListener("DOMContentLoaded", function() {
      // إدارة القوائم المنسدلة في السايدبار
      const dropdownToggles = document.querySelectorAll('.sidebar .dropdown-toggle');
      
      dropdownToggles.forEach(toggle => {
          toggle.addEventListener('click', function(e) {
              e.preventDefault();
              const target = this.getAttribute('data-bs-target');
              const collapse = document.querySelector(target);
              
              if (collapse) {
                  // إغلاق باقي القوائم المنسدلة
                  document.querySelectorAll('.sidebar .collapse.show').forEach(openCollapse => {
                      if (openCollapse !== collapse) {
                          bootstrap.Collapse.getInstance(openCollapse)?.hide();
                      }
                  });
              }
          });
      });
      
      // زر إغلاق السايدبار للجوال
    
      
      // البحث في القائمة
      const sidebarSearch = document.querySelector('.sidebar .search-box input');
      if (sidebarSearch) {
          sidebarSearch.addEventListener('input', function() {
              const searchTerm = this.value.toLowerCase().trim();
              const navItems = document.querySelectorAll('.sidebar .nav-item:not(.mt-3)');
              
              navItems.forEach(item => {
                  const text = item.textContent.toLowerCase();
                  if (text.includes(searchTerm) || searchTerm === '') {
                      item.style.display = '';
                  } else {
                      item.style.display = 'none';
                  }
              });
          });
          
          // زر البحث
          const searchBtn = document.querySelector('.sidebar .search-box button');
          if (searchBtn) {
              searchBtn.addEventListener('click', function() {
                  const searchTerm = sidebarSearch.value.trim();
                  if (searchTerm) {
                      // يمكنك إضافة منطق البحث هنا
                      console.log('بحث عن:', searchTerm);
                  }
              });
              
              // البحث عند الضغط على Enter
              sidebarSearch.addEventListener('keypress', function(e) {
                  if (e.key === 'Enter') {
                      searchBtn.click();
                  }
              });
          }
      }
  });
  </script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const sidebar = document.getElementById("sidebar");
    const sidebarBody = document.querySelector('.sidebar-body');
    
    // حفظ موضع الـ scroll عند الضغط على رابط
    document.querySelectorAll('.sidebar .nav-link').forEach(link => {
        link.addEventListener('click', function(e) {
            // إذا كان الرابط لنفس الصفحة الحالية، لا تمنع السلوك الافتراضي
            if (this.classList.contains('active')) {
                return;
            }
            
            // حفظ موضع الـ scroll قبل الانتقال
            localStorage.setItem('sidebarScrollPosition', sidebarBody.scrollTop);
            
            // تأخير الانتقال قليلاً لضمان حفظ الموضع
            setTimeout(() => {
                // يمكنك إزالة هذا إذا كنت تريد الانتقال فوراً
            }, 100);
        });
    });
    
    // استعادة موضع الـ scroll عند تحميل الصفحة
    window.addEventListener('load', function() {
        const savedPosition = localStorage.getItem('sidebarScrollPosition');
        if (savedPosition && sidebarBody) {
            // تأخير التمرير قليلاً لضمان تحميل العناصر
            setTimeout(() => {
                sidebarBody.scrollTop = parseInt(savedPosition);
            }, 50);
        }
    });
    
    // حفظ موضع الـ scroll عند التمرير
    if (sidebarBody) {
        sidebarBody.addEventListener('scroll', function() {
            localStorage.setItem('sidebarScrollPosition', this.scrollTop);
        });
    }
    
    // البحث في القائمة
    const sidebarSearch = document.querySelector('.sidebar .search-box input');
    if (sidebarSearch) {
        sidebarSearch.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            const navItems = document.querySelectorAll('.sidebar .nav-item:not(.mt-3)');
            
            navItems.forEach(item => {
                const text = item.textContent.toLowerCase();
                if (text.includes(searchTerm) || searchTerm === '') {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }
});
</script>


<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.getElementById('confirmBackup').addEventListener('click', async () => {
    const btn = document.getElementById('confirmBackup');
    const pathKey = document.getElementById('backupPath').value;
    const zip     = document.getElementById('zipBackup').checked;

    // تعطيل الزر لمنع الضغط أكثر من مرة
    btn.disabled = true;

    // إظهار نافذة تحميل
    Swal.fire({
        title: 'جاري إنشاء النسخة الاحتياطية...',
        text: 'يرجى الانتظار',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    try {
        const res = await fetch('http://localhost/store_v1/api/backup.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                path_key: pathKey,
                zip: zip,
                csrf: '<?php echo $csrf_token; ?>'
            })
        });

        const data = await res.json();

        Swal.close(); // إغلاق نافذة التحميل

        if (data.status === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'تم بنجاح',
                text: data.message,
                confirmButtonText: 'حسناً'
            });

            // إغلاق المودال
            bootstrap.Modal.getInstance(
                document.getElementById('backupModal')
            ).hide();
        } else {
            Swal.fire({
                icon: 'error',
                title: 'حدث خطأ',
                text: data.message,
                confirmButtonText: 'حسناً'
            });
        }

    } catch (error) {
        Swal.close();
        Swal.fire({
            icon: 'error',
            title: 'فشل الاتصال',
            text: error.message,
            confirmButtonText: 'حسناً'
        });
    } finally {
        btn.disabled = false; // إعادة تفعيل الزر
    }
});
</script>


<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>





<script>
function loadNotifications(){
    fetch('/store_v1/api/notifications/get_latest_notification.php')
    .then(res => res.json())
    .then(data => {
        const list  = document.querySelector('.notifications-list');
        const badge = document.querySelector('#notificationsDropdown .badge');
        const markAllBtn = document.getElementById('markAllReadBtn');

        list.innerHTML = '';

        if(data.status === 'empty'){
            list.innerHTML = `
                <div class="p-3 text-center text-muted">
                    لا توجد إشعارات جديدة
                </div>`;
            badge.classList.add('d-none');
            markAllBtn.classList.add('d-none');
            return;
        }

        badge.innerText = data.notifications.length;
        badge.classList.remove('d-none');
        markAllBtn.classList.remove('d-none');

        data.notifications.forEach(notif => {
            const item = document.createElement('div');
            item.className = 'notification-item d-flex align-items-start px-3 py-2 border-bottom';

            item.innerHTML = `
                <div class="me-3 text-primary mt-1">
                    <i class="fas fa-bell"></i>
                </div>

                <div class="flex-grow-1">
                    <div class="fw-bold">${notif.title}</div>
                    <div class="small text-muted">${notif.message}</div>
                </div>

                <button class="btn btn-sm btn-outline-success mark-read-btn"
                        title="تحديد كمقروء">
                    <i class="fas fa-check"></i>
                </button>
            `;

            item.querySelector('.mark-read-btn')
                .addEventListener('click', (e) => {
                    e.stopPropagation();
                    fetch('/store_v1/api/notifications/update_notification.php', {
                        method: 'POST',
                        headers: {'Content-Type':'application/x-www-form-urlencoded'},
                        body: 'id=' + notif.id
                    }).then(() => loadNotifications());
                });

            list.appendChild(item);
        });
    });
}

// تحديد الكل كمقروء
document.getElementById('markAllReadBtn')
?.addEventListener('click', () => {
    fetch('/store_v1/api/notifications/mark_all_notifications_read.php')
    .then(() => loadNotifications());
});

// تحميل عند فتح الجرس
document.getElementById('notificationsDropdown')
.addEventListener('click', loadNotifications);

// تحميل أولي
loadNotifications();
</script>

