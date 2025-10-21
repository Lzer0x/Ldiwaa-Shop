<?php
session_start();
require_once __DIR__ . '/../includes/auth_user.php'; // ตรวจสอบสิทธิ์ก่อน
include __DIR__ . '/../includes/db_connect.php';    // เชื่อมต่อ DB
include __DIR__ . '/../includes/header.php';      // โหลด <head>

// ตรวจสอบสิทธิ์แอดมินโดยรวม (ถ้าทุกหน้าต้องใช้)
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
  echo "<div class='alert alert-danger text-center mt-5'>สำหรับผู้ดูแลระบบเท่านั้น</div>";
  include __DIR__ . '/../includes/footer.php';
  exit;
}

// โหลด CSS หลักและ Font
?>
<link rel="stylesheet" href="../assets/css/admin.css">

<div class="admin-wrapper">

  <?php include __DIR__ . '/includes/sidebar.php'; // 1. โหลด Sidebar ?>

  <main class="main-content">
    <?php
    // 2. ส่วนควบคุมการสลับหน้า (Routing)
    $page = $_GET['page'] ?? 'dashboard'; // หน้าเริ่มต้นคือ dashboard

    // รายการหน้าที่อนุญาต (ป้องกัน Path Traversal)
    $allowedPages = [
        'dashboard'    => __DIR__ . '/pages/dashboard.php',
        'orders'       => __DIR__ . '/pages/orders.php',
        'products'     => __DIR__ . '/pages/products.php',
        'add_product'  => __DIR__ . '/pages/add_product.php',
        'edit_product' => __DIR__ . '/pages/edit_product.php',
        'users'        => __DIR__ . '/pages/users.php',
        'edit_user'    => __DIR__ . '/pages/edit_user.php',
    ];

    // 3. โหลด "ไส้ใน"
    if (array_key_exists($page, $allowedPages)) {
        // $conn ถูกส่งต่อไปยังไฟล์ที่ include
        include $allowedPages[$page]; 
    } else {
        // ถ้าหน้าไม่มีในรายการ
        echo "<h1>404 - ไม่พบหน้า</h1>";
    }
    ?>
  </main></div><?php include __DIR__ . '/../includes/footer.php'; // โหลด JavaScript และปิด </body> ?>
