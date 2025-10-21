<?php
session_start();
if (!isset($_SESSION['user'])) {
  header("Location: login.php");
  exit;
}

include 'includes/db_connect.php';
include 'includes/header.php';

if (!isset($_GET['order_id'])) {
  header("Location: order_history.php");
  exit;
}

$order_id = $_GET['order_id'];
$user_id = $_SESSION['user']['user_id'];

// ✅ ตรวจสอบว่าเป็นออเดอร์ของผู้ใช้นี้
$stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ? AND user_id = ?");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
  echo "<div class='container mt-5'><div class='alert alert-danger text-center'>❌ ไม่พบคำสั่งซื้อนี้</div></div>";
  include 'includes/footer.php';
  exit;
}

// ✅ ดึงสินค้าที่อยู่ในออเดอร์นี้
$stmt = $conn->prepare("
  SELECT p.name, od.price, od.quantity, pp.title
  FROM order_details od
  JOIN products p ON od.product_id = p.product_id
  JOIN product_prices pp ON od.package_id = pp.id
  WHERE od.order_id = ?
");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ ดึงข้อมูลการชำระเงิน (ถ้ามี)
$payStmt = $conn->prepare("SELECT slip_path, status FROM payments WHERE order_id = ? ORDER BY payment_id DESC LIMIT 1");
$payStmt->execute([$order_id]);
$payment = $payStmt->fetch(PDO::FETCH_ASSOC);
?>

<link rel="stylesheet" href="assets/css/order_detail.css">

<div class="order-detail-container">
  <div class="order-card">
    <div class="order-header">
      <h2>🧾 รายละเอียดคำสั่งซื้อ</h2>
      <span class="order-id">#<?= htmlspecialchars($order_id) ?></span>
    </div>

    <div class="order-info">
      <p><strong>วันที่สั่งซื้อ:</strong> <?= htmlspecialchars($order['created_at']) ?></p>
      <p><strong>ยอดรวม:</strong> ฿<?= number_format($order['total_price'], 2) ?></p>
      <p><strong>ช่องทางชำระเงิน:</strong> <?= htmlspecialchars($order['payment_method']) ?></p>

      <!-- ✅ สถานะคำสั่งซื้อ -->
      <p><strong>สถานะคำสั่งซื้อ:</strong> 
        <?php if ($order['order_status'] === 'completed'): ?>
          <span class="badge completed">เสร็จสิ้น</span>
        <?php elseif ($order['order_status'] === 'processing'): ?>
          <span class="badge processing">กำลังดำเนินการ</span>
        <?php elseif ($order['order_status'] === 'failed'): ?>
          <span class="badge failed">ล้มเหลว / ถูกยกเลิก</span>
        <?php else: ?>
          <span class="badge canceled">ยกเลิก</span>
        <?php endif; ?>
      </p>

      <!-- ✅ สถานะการชำระเงิน -->
      <p><strong>สถานะการชำระเงิน:</strong> 
        <?php if ($order['payment_status'] === 'paid'): ?>
          <span class="badge paid">ชำระแล้ว</span>
        <?php elseif ($order['payment_status'] === 'pending'): ?>
          <span class="badge pending">รอตรวจสอบ</span>
        <?php elseif ($order['payment_status'] === 'failed'): ?>
          <span class="badge failed">ล้มเหลว / ถูกปฏิเสธ</span>
        <?php elseif ($order['payment_status'] === 'unpaid'): ?>
          <span class="badge waiting">รอชำระเงิน</span>
        <?php else: ?>
          <span class="badge unknown">ไม่ทราบสถานะ</span>
        <?php endif; ?>
      </p>
    </div>

    <!-- ✅ แสดงสลิปการชำระเงิน -->
    <?php if ($payment && !empty($payment['slip_path'])): ?>
      <div class="payment-slip">
        <h4>🧾 หลักฐานการชำระเงิน</h4>
        <img src="<?= htmlspecialchars($payment['slip_path']) ?>" alt="สลิปการชำระเงิน">
        <p class="slip-status">
          สถานะสลิป: 
          <?php if ($payment['status'] === 'verified'): ?>
            <span class="badge verified">ยืนยันแล้ว</span>
          <?php elseif ($payment['status'] === 'pending'): ?>
            <span class="badge pending">รอตรวจสอบ</span>
          <?php else: ?>
            <span class="badge rejected">ถูกปฏิเสธ</span>
          <?php endif; ?>
        </p>
      </div>
    <?php endif; ?>

    <!-- ✅ ตารางสินค้า -->
    <div class="order-items">
      <h4>📦 รายการสินค้า</h4>
      <table>
        <thead>
          <tr>
            <th>สินค้า</th>
            <th>แพ็กเกจ</th>
            <th>จำนวน</th>
            <th>ราคา</th>
            <th>รวม</th>
          </tr>
        </thead>
        <tbody>
          <?php $total = 0; foreach ($items as $item): 
            $subtotal = $item['price'] * $item['quantity'];
            $total += $subtotal; ?>
            <tr>
              <td><?= htmlspecialchars($item['name']) ?></td>
              <td><?= htmlspecialchars($item['title']) ?></td>
              <td><?= $item['quantity'] ?></td>
              <td><?= number_format($item['price'], 2) ?> ฿</td>
              <td><?= number_format($subtotal, 2) ?> ฿</td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div class="order-total">
        💰 รวมทั้งหมด: <strong>฿<?= number_format($total, 2) ?></strong>
      </div>
    </div>

    <!-- ✅ ปุ่มกลับ -->
    <div class="footer-btn">
      <?php if ($order['payment_status'] === 'unpaid'): ?>
        <a href="payment_gateway.php?method=<?= urlencode($order['payment_method'] ?? 'PromptPay') ?>&id=<?= $order['order_id'] ?>" class="btn pay">💰 ชำระเงิน</a>
      <?php endif; ?>
      <a href="order_history.php" class="btn back">← กลับไปหน้าคำสั่งซื้อ</a>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
