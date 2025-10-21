<?php
session_start();
if (!isset($_SESSION['user'])) {
  header("Location: login.php");
  exit;
}

include 'includes/db_connect.php';
include 'includes/header.php';

$user = $_SESSION['user'];

// ✅ ดึงข้อมูลคำสั่งซื้อของผู้ใช้
$stmt = $conn->prepare("
  SELECT 
    o.order_id,
    o.total_price,
    o.payment_status,
    o.order_status,
    o.payment_method,
    o.created_at,
    COUNT(od.order_detail_id) AS items,
    (SELECT status FROM payments WHERE order_id = o.order_id ORDER BY payment_id DESC LIMIT 1) AS slip_status
  FROM orders o
  LEFT JOIN order_details od ON o.order_id = od.order_id
  WHERE o.user_id = ?
  GROUP BY o.order_id
  ORDER BY o.created_at DESC
");
$stmt->execute([$user['user_id']]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<link rel="stylesheet" href="assets/css/order_history.css">

<div class="order-history-container">
  <h2 class="page-title">🛍️ คำสั่งซื้อของฉัน</h2>

  <?php if (empty($orders)): ?>
    <div class="empty-box">
      <p>ยังไม่มีคำสั่งซื้อในระบบ</p>
      <a href="index.php" class="btn btn-primary">เลือกซื้อสินค้า</a>
    </div>
  <?php else: ?>
    <div class="order-list">
      <?php foreach ($orders as $order): ?>
        <div class="order-card">
          <div class="order-header">
            <div>
              <h4>#<?= $order['order_id'] ?></h4>
              <span class="order-date"><?= htmlspecialchars($order['created_at']) ?></span>
            </div>

            <!-- ✅ สถานะแสดงแบบแม่นยำ -->
            <div class="status-box">
              <?php if ($order['slip_status'] === 'rejected' || $order['payment_status'] === 'failed' || $order['order_status'] === 'failed' || $order['order_status'] === 'cancelled'): ?>
                <span class="badge failed">ถูกปฏิเสธ / ยกเลิก</span>

              <?php elseif ($order['payment_status'] === 'paid' || $order['order_status'] === 'completed'): ?>
                <span class="badge paid">ชำระแล้ว</span>

              <?php elseif ($order['payment_status'] === 'pending' || $order['slip_status'] === 'pending'): ?>
                <span class="badge pending">รอตรวจสอบ</span>

              <?php else: ?>
                <span class="badge waiting">รอชำระเงิน</span>
              <?php endif; ?>
            </div>
          </div>

          <div class="order-body">
            <div class="info">
              <p>สินค้า: <?= $order['items'] ?> รายการ</p>
              <p>ช่องทางชำระเงิน: <?= htmlspecialchars($order['payment_method'] ?? '-') ?></p>
            </div>
            <div class="price">
              <p>ยอดรวม</p>
              <strong>฿<?= number_format($order['total_price'], 2) ?></strong>
            </div>
          </div>

          <div class="order-footer">
            <?php if ($order['slip_status'] === 'rejected' || $order['payment_status'] === 'failed' || $order['order_status'] === 'cancelled'): ?>
              <span class="btn btn-gray disabled">❌ ถูกปฏิเสธ / ยกเลิก</span>
              <a href="order_detail.php?order_id=<?= $order['order_id'] ?>" class="btn btn-outline">📄 รายละเอียด</a>

            <?php elseif ($order['payment_status'] === 'paid'): ?>
              <a href="order_detail.php?order_id=<?= $order['order_id'] ?>" class="btn btn-green">🔍 ดูรายละเอียด</a>

            <?php elseif ($order['payment_status'] === 'pending' || $order['slip_status'] === 'pending'): ?>
              <button class="btn btn-yellow" disabled>⏳ รอตรวจสอบ</button>
              <a href="order_detail.php?order_id=<?= $order['order_id'] ?>" class="btn btn-outline">📄 รายละเอียด</a>

            <?php else: ?>
              <a href="payment_gateway.php?method=<?= urlencode($order['payment_method'] ?? 'PromptPay') ?>&id=<?= $order['order_id'] ?>" class="btn btn-blue">💰 ชำระเงิน</a>
              <a href="order_detail.php?order_id=<?= $order['order_id'] ?>" class="btn btn-outline">📄 รายละเอียด</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
