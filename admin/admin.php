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
  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <main class="main-content">
    <?php
    $page = $_GET['page'] ?? 'dashboard';

    $allowedPages = [
        'dashboard'    => __DIR__ . '/pages/dashboard.php',
        'orders'       => __DIR__ . '/pages/orders.php',
        'products'     => __DIR__ . '/pages/products.php',
        'add_product'  => __DIR__ . '/pages/add_product.php',
        'edit_product' => __DIR__ . '/pages/edit_product.php',
        'users'        => __DIR__ . '/pages/users.php',
        'edit_user'    => __DIR__ . '/pages/edit_user.php',
        'redeem_keys'  => __DIR__ . '/pages/redeem_keys.php', // ✅ เพิ่มบรรทัดนี้
    ];

    if (array_key_exists($page, $allowedPages)) {
        include $allowedPages[$page];
    } else {
        echo "<h1 class='text-center mt-5'>404 - ไม่พบหน้า</h1>";
    }
    ?>
  </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
