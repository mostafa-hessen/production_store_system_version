<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo isset($page_title) ? $page_title : 'لوحة التحكم'; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet" />
    
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/print_invoice.css" media="print">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/index.css" >


       <?php if (!empty($page_css)): ?>
        <!-- CSS خاص بالصفحة -->
        <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/<?php echo $page_css; ?>">
    <?php endif; ?>
  
</head>
<body data-app data-theme="dark">

<!-- المحتوى يبدأ هنا -->
<div class="main-content">
