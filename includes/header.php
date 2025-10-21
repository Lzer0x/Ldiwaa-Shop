<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$total_items = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
  foreach ($_SESSION['cart'] as $it) { $total_items += (int)$it['quantity']; }
}
// Base path for assets/links when page is under /admin/
$isAdminPath = (strpos($_SERVER['PHP_SELF'] ?? '', '/admin/') !== false);
$base = $isAdminPath ? '../' : '';
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ldiwaa+</title>
<link rel="icon" type="image/png" sizes="32x32" href="<?= $base ?>favicon.png">
<link rel="apple-touch-icon" href="<?= $base ?>favicon.png">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= $base ?>assets/css/shared1.css">
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  
  <style>
    .nav-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }
    .searchbar {
      display: flex;
      align-items: center;
      background: #1b1f28;
      border-radius: 24px;
      padding: 4px 12px;
      border: 1px solid #2b2f3b;
      width: 320px;
    }
    .searchbar .icon {
      color: #888;
      font-size: 1rem;
    }
    .searchbar input {
      flex: 1;
      border: none;
      outline: none;
      background: transparent;
      color: #fff;
      padding: 6px 10px;
      font-size: 0.95rem;
    }
    .searchbar input::placeholder {
      color: #777;
    }
  </style>
</head>
<body>

<header class="navbar-sea shadow-lg">
  <div class="nav-left container-wide" style="gap:24px;">

    <a class="brand glow-text" href="<?= $base ?>index.php">Ldiwa<span>a+</span></a>

    <!-- ✅ ค้นหาย้ายมาไว้ที่นี่ -->
    <form action="<?= $base ?>products.php" method="get" class="searchbar">
      <i class="bi bi-search icon"></i>
      <input type="text" name="q" placeholder="  ค้นหาเกมหรือรหัสเติมเงิน...">
    </form>
  </div>

  <div class="nav-right container-wide" style="justify-content:end;">
     <a href="<?= $base ?>products.php" class="btn-pill nav-btn">
      <i class="bi bi-shop"></i> 
      <span>สินค้าทั้งหมด</span>
    </a>

    <a href="<?= $base ?>cart.php" class="btn-pill nav-btn">
      <i class="bi bi-cart3"></i>
      <span>ตะกร้า</span>
      <?php if($total_items): ?>
        <span class="count"><?= $total_items ?></span>
      <?php endif; ?>
    </a>

    <?php if(!empty($_SESSION['user'])): ?>
      <a href="<?= $base ?>profile.php" class="btn-pill nav-btn">
        <i class="bi bi-person-fill"></i>
        <span><?= htmlspecialchars($_SESSION['user']['username']) ?></span>
      </a>

      <?php if($_SESSION['user']['role'] === 'admin'): ?>
        <a href="<?= $base ?>admin/dashboard.php" class="btn-pill btn-admin nav-btn">
          <i class="bi bi-bag-check"></i>
          <span>Dashboard</span>
        </a>
      <?php endif; ?>

      <a href="<?= $base ?>logout.php" class="btn-pill nav-btn">
        <i class="bi bi-box-arrow-right"></i>
        <span>ออกจากระบบ</span>
      </a>
    <?php else: ?>
      <a href="<?= $base ?>login.php" class="btn-pill btn-accent">เข้าสู่ระบบ / สมัครสมาชิก</a>
    <?php endif; ?>
  </div>
</header>

<!-- Page content continues; scripts and closing tags are in includes/footer.php -->

