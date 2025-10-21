<?php
session_start();
require_once 'includes/auth_user.php';
if (!isset($_SESSION['user'])) {
  header("Location: login.php");
  exit;
}

include 'includes/db_connect.php';
include 'includes/header.php';

// ดึงข้อมูลผู้ใช้
$user = $_SESSION['user'];
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user['user_id']]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

// ดึงสถิติรวม (เฉพาะออเดอร์)
$statStmt = $conn->prepare("
  SELECT COUNT(order_id) AS total_orders, COALESCE(SUM(total_price),0) AS total_spent
  FROM orders
  WHERE user_id = ? AND payment_status = 'paid'
");

$statStmt->execute([$user['user_id']]);
$stats = $statStmt->fetch(PDO::FETCH_ASSOC);

$total_orders = (int)($stats['total_orders'] ?? 0);
$total_spent  = (float)($stats['total_spent'] ?? 0.0);

// ดึงออเดอร์ล่าสุด
$recentStmt = $conn->prepare("
  SELECT order_id, total_price, payment_status, payment_method, created_at
  FROM orders
  WHERE user_id = ?
  ORDER BY created_at DESC
  LIMIT 5
");
$recentStmt->execute([$user['user_id']]);
$recentOrders = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

// Avatar
$avatar = !empty($userData['avatar']) ? $userData['avatar'] : 'images/default_avatar.png';

// Tier
$tiers = [
  ['name' => 'Bronze',   'min' => 0,     'color' => '#b08d57'],
  ['name' => 'Silver',   'min' => 1000,  'color' => '#c0c0c0'],
  ['name' => 'Gold',     'min' => 5000,  'color' => '#ffd700'],
  ['name' => 'Platinum', 'min' => 15000, 'color' => '#b0f3ff'],
  ['name' => 'Diamond',  'min' => 30000, 'color' => '#9ad6ff'],
];

$currentTierIndex = 0;
foreach ($tiers as $i => $t) {
  if ($total_spent >= $t['min']) $currentTierIndex = $i;
}
$currentTier = $tiers[$currentTierIndex];
$nextTier    = $tiers[min($currentTierIndex + 1, count($tiers)-1)];
$progressMin = $currentTier['min'];
$progressMax = $nextTier['min'];
$progressPct = ($progressMax > $progressMin)
  ? max(0, min(100, (($total_spent - $progressMin) / ($progressMax - $progressMin)) * 100))
  : 100;

$isAdmin = ($userData['role'] ?? 'user') === 'admin';
?>

<link rel="stylesheet" href="assets/css/profile.css">

<div class="profile-wrap">
  <aside class="side">
    <div class="title">เมนู</div>
    <nav class="nav">
      <a href="profile.php"><span class="ico">👤</span> โปรไฟล์</a>
      <a href="order_history.php"><span class="ico">🛒</span> คำสั่งซื้อของฉัน</a>
      <a href="products.php"><span class="ico">🧩</span> สินค้าทั้งหมด</a>
      <a href="support.php"><span class="ico">💬</span> ติดต่อ/ช่วยเหลือ</a>
      <?php if ($isAdmin): ?>
      <div class="title" style="margin-top:14px;">ผู้ดูแลระบบ</div>
      <a href="admin_orders.php"><span class="ico">📦</span> จัดการคำสั่งซื้อ</a>
      <a href="admin_products.php"><span class="ico">📁</span> จัดการสินค้า</a>
      <?php endif; ?>
      <a href="logout.php"><span class="ico">🚪</span> ออกจากระบบ</a>
    </nav>
  </aside>

  <section class="main">
    <div class="card">
      <div class="card-header">
        <h3>ภาพรวมบัญชี</h3>
        <div class="actions">
          <a href="profile_edit.php" class="btn">⚙️ แก้ไขโปรไฟล์</a>
          <a href="order_history.php" class="btn">📚 ประวัติคำสั่งซื้อ</a>
        </div>
      </div>
      <div class="card-body">
        <div class="stats">
          <div class="stat">
            <div class="label">คำสั่งซื้อทั้งหมด</div>
            <div class="value"><?= number_format($total_orders) ?></div>
          </div>
          <div class="stat">
            <div class="label">ยอดใช้จ่ายรวม</div>
            <div class="value">฿<?= number_format($total_spent, 2) ?></div>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h3>ออเดอร์ล่าสุด</h3>
        <div class="actions">
          <a href="order_history.php" class="btn">ดูทั้งหมด →</a>
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
                <th>วันที่</th>
                <th>ช่องทาง</th>
                <th>สถานะ</th>
                <th>ยอดรวม</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($recentOrders as $o): 
              $badgeClass = match($o['payment_status']) {
                'paid' => 'green', 'pending' => 'yellow', 'failed' => 'red', default => 'gray'
              };
            ?>
              <tr>
                <td>#<?= htmlspecialchars($o['order_id']) ?></td>
                <td><?= htmlspecialchars($o['created_at']) ?></td>
                <td><?= htmlspecialchars($o['payment_method'] ?? '-') ?></td>
                <td><span class="badge <?= $badgeClass ?>">
                  <?= $o['payment_status'] === 'paid' ? 'ชำระแล้ว' : ($o['payment_status'] === 'pending' ? 'รอตรวจสอบ' : ($o['payment_status'] === 'failed' ? 'ล้มเหลว' : 'รอชำระ')) ?>
                </span></td>
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

    <div class="card">
      <div class="card-header"><h3>การทำรายการด่วน</h3></div>
      <div class="card-body">
        <div class="actions">
          <a href="products.php" class="btn primary">🧩 เลือกซื้อสินค้า</a>
          <a href="order_history.php" class="btn">🧾 ประวัติคำสั่งซื้อ</a>
          <a href="support.php" class="btn yellow">💬 ติดต่อแอดมิน</a>
        </div>
      </div>
    </div>
  </section>

  <aside class="aside">
    <div class="profile-card">
      <img class="avatar" src="<?= htmlspecialchars($avatar) ?>" alt="avatar">
      <div class="username"><?= htmlspecialchars($userData['username']) ?></div>
      <div class="mail"><?= htmlspecialchars($userData['email']) ?></div>
      <div class="role-badge <?= $isAdmin ? 'role-admin' : 'role-user' ?>">
        <?= $isAdmin ? 'ผู้ดูแลระบบ' : 'ผู้ใช้ทั่วไป' ?>
      </div>
      <div class="row-mini">
        <div class="mini">
          <div class="label">วันที่สมัคร</div>
          <div class="value"><?= htmlspecialchars($userData['created_at']) ?></div>
        </div>
        <div class="mini">
          <div class="label">สถานะบัญชี</div>
          <div class="value"><?= htmlspecialchars($userData['status'] ?? 'active') ?></div>
        </div>
      </div>
    </div>

    <div class="rank-card">
      <div class="rank-top">
        <div class="rank-label">Rank</div>
        <div class="rank-name" style="color: <?= $currentTier['color'] ?>;"><?= $currentTier['name'] ?></div>
      </div>
      <div class="progress"><span style="width: <?= $progressPct ?>%;"></span></div>
      <div class="rank-row">
        <div>ขั้นต่อไป: <?= htmlspecialchars($nextTier['name']) ?></div>
        <div>
          <?php if ($progressPct >= 100): ?>
            <span style="color:#9ad6ff;">ครบระดับ</span>
          <?php else: ?>
            เหลืออีก ฿<?= number_format(max(0, $nextTier['min'] - $total_spent), 2) ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </aside>
</div>

<?php include 'includes/footer.php'; ?>
