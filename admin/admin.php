<?php
// ✅ เริ่ม session และเปิด output buffering (กัน Warning header sent)
session_start();
ob_start();

require_once __DIR__ . '/../includes/auth_user.php'; // ตรวจสอบสิทธิ์ผู้ใช้
include __DIR__ . '/../includes/db_connect.php';      // เชื่อมต่อฐานข้อมูล
include __DIR__ . '/../includes/header.php';          // โหลด <head> ส่วนบนของเว็บ

// ✅ ตรวจสอบสิทธิ์แอดมิน
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
  echo "<div class='alert alert-danger text-center mt-5'>🚫 สำหรับผู้ดูแลระบบเท่านั้น</div>";
  include __DIR__ . '/../includes/footer.php';
  ob_end_flush();
  exit;
}
?>

<!-- ✅ โหลด CSS หลักของระบบแอดมิน -->
<link rel="stylesheet" href="../assets/css/admin.css">

<div class="admin-wrapper">
  <?php include __DIR__ . '/includes/sidebar.php'; // ✅ โหลด Sidebar ?>

  <main class="main-content">
    <?php
    // ✅ ระบบสลับหน้า (Router)
    $page = $_GET['page'] ?? 'dashboard';

    // ✅ รายการไฟล์ที่อนุญาตให้โหลด (กัน Path Traversal)
    $allowedPages = [
      'dashboard'     => __DIR__ . '/pages/dashboard.php',
      'orders'        => __DIR__ . '/pages/orders.php',
      'products'      => __DIR__ . '/pages/products.php',
      'add_product'   => __DIR__ . '/pages/add_product.php',
      'edit_product'  => __DIR__ . '/pages/edit_product.php',
      'users'         => __DIR__ . '/pages/users.php',
      'edit_user'     => __DIR__ . '/pages/edit_user.php',
      'redeem_keys'   => __DIR__ . '/pages/redeem_keys.php', // ✅ เพิ่มหน้า Redeem Key ใหม่
    ];

    // ✅ โหลดไฟล์หน้า
    if (array_key_exists($page, $allowedPages)) {
      include $allowedPages[$page];
    } else {
      echo "<h1 style='padding:2rem;text-align:center;'>404 - ไม่พบหน้า</h1>";
    }
    ?>
  </main>
</div>

<?php 
include __DIR__ . '/../includes/footer.php'; 
// ✅ ปิด output buffer เพื่อเคลียร์ header ปัญหา "already sent"
ob_end_flush(); 
?>
