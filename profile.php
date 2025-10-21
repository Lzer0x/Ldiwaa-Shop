<?php
session_start();
require_once 'includes/auth_user.php';
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit; }

include 'includes/db_connect.php';
include 'includes/header.php';

$userId = (int)$_SESSION['user']['user_id'];

$stmt = $conn->prepare('SELECT * FROM users WHERE user_id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$statStmt = $conn->prepare('SELECT COUNT(order_id) AS total_orders, COALESCE(SUM(total_price),0) AS total_spent FROM orders WHERE user_id = ? AND payment_status = "paid"');
$statStmt->execute([$userId]);
$stats = $statStmt->fetch(PDO::FETCH_ASSOC) ?: ['total_orders'=>0,'total_spent'=>0];

$recentStmt = $conn->prepare('SELECT order_id, total_price, payment_status, payment_method, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
$recentStmt->execute([$userId]);
$recentOrders = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

$avatar = !empty($user['avatar']) ? $user['avatar'] : 'images/default_avatar.png';
?>

<link rel="stylesheet" href="assets/css/profile.css">

<div class="profile-wrap">
  <aside class="side">
    <div class="title">เมนู</div>
    <nav class="nav">
      <a href="profile.php"><span class="ico">👤</span> โปรไฟล์</a>
      <a href="order_history.php"><span class="ico">🧾</span> ประวัติคำสั่งซื้อ</a>
      <a href="products.php"><span class="ico">🛒</span> สินค้าทั้งหมด</a>
      <a href="support.php"><span class="ico">💬</span> ติดต่อ/ช่วยเหลือ</a>
      <a href="logout.php"><span class="ico">🚪</span> ออกจากระบบ</a>
    </nav>
  </aside>

  <section class="main">
    <div class="card">
      <div class="card-header">
        <h3>ข้อมูลผู้ใช้</h3>
        <div class="actions">
          <a href="profile_edit.php" class="btn">แก้ไขโปรไฟล์</a>
          <a href="order_history.php" class="btn">ดูคำสั่งซื้อทั้งหมด</a>
        </div>
      </div>
      <div class="card-body">
        <div class="stats">
          <div class="stat">
            <div class="label">จำนวนคำสั่งซื้อ</div>
            <div class="value"><?= number_format((int)$stats['total_orders']) ?></div>
          </div>
          <div class="stat">
            <div class="label">ยอดใช้จ่ายรวม</div>
            <div class="value">฿<?= number_format((float)$stats['total_spent'], 2) ?></div>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h3>รายการล่าสุด</h3>
        <div class="actions">
          <a href="order_history.php" class="btn">ดูทั้งหมด</a>
        </div>
      </div>
      <div class="card-body">
        <?php if (empty($recentOrders)): ?>
          <div class="empty">ยังไม่มีคำสั่งซื้อ</div>
        <?php else: ?>
        <div class="table-scroll">
          <table class="table">
            <thead>
              <tr>
                <th>#</th>
                <th>เมื่อ</th>
                <th>วิธีชำระ</th>
                <th>สถานะ</th>
                <th>ยอดรวม</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($recentOrders as $o): ?>
              <tr>
                <td>#<?= htmlspecialchars($o['order_id']) ?></td>
                <td><?= htmlspecialchars($o['created_at']) ?></td>
                <td><?= htmlspecialchars($o['payment_method'] ?? '-') ?></td>
                <td><?= htmlspecialchars($o['payment_status']) ?></td>
                <td>฿<?= number_format($o['total_price'], 2) ?></td>
                <td><a class="btn" href="order_detail.php?order_id=<?= $o['order_id'] ?>">รายละเอียด</a></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <aside class="aside">
    <div class="profile-card">
      <img class="avatar" src="<?= htmlspecialchars($avatar) ?>" alt="avatar">
      <div class="username"><?= htmlspecialchars($user['username'] ?? '') ?></div>
      <div class="mail"><?= htmlspecialchars($user['email'] ?? '') ?></div>
      <div class="row-mini">
        <div class="mini">
          <div class="label">สมัครเมื่อ</div>
          <div class="value"><?= htmlspecialchars($user['created_at'] ?? '-') ?></div>
        </div>
        <div class="mini">
          <div class="label">สถานะ</div>
          <div class="value"><?= htmlspecialchars($user['status'] ?? 'active') ?></div>
        </div>
      </div>
    </div>
  </aside>
</div>

<?php include 'includes/footer.php'; ?>

