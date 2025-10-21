<?php
session_start();
require_once 'includes/auth_user.php';
include 'includes/db_connect.php';
include 'includes/header.php';

// ✅ ดึงข้อมูลจาก session ที่ได้จาก checkout.php
$order_id = $_SESSION['latest_order_id'] ?? null;
$method = $_GET['method'] ?? 'PromptPay';

// ถ้าไม่มี order_id → กลับไปหน้าแรก
if (!$order_id) {
  header("Location: index.php");
  exit;
}

// ✅ ดึงข้อมูลคำสั่งซื้อจากฐานข้อมูล
$stmt = $conn->prepare("SELECT total_price FROM orders WHERE order_id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
  echo "<div class='alert alert-danger text-center'>❌ ไม่พบคำสั่งซื้อ</div>";
  include 'includes/footer.php';
  exit;
}

$total_price = (float)$order['total_price'];
$service_fee = $total_price * 0.015;
$grand_total = $total_price + $service_fee;

$expire_time = date("Y-m-d H:i:s", strtotime("+3 minutes"));
$payment_number = 'P' . rand(1000000, 9999999);

// ✅ โฟลเดอร์อัปโหลดสลิป
$upload_dir = __DIR__ . "/uploads/slips/";
if (!file_exists($upload_dir)) {
  mkdir($upload_dir, 0755, true);
}

// ✅ จัดการอัปโหลดสลิป
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['payment_slip'])) {
  $file = $_FILES['payment_slip'];
  if ($file['error'] === UPLOAD_ERR_OK) {
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (in_array($file['type'], $allowed) && $file['size'] <= 5 * 1024 * 1024) {
      $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
      $new_name = 'slip_' . $order_id . '_' . time() . '.' . $ext;
      $target = $upload_dir . $new_name;

      if (move_uploaded_file($file['tmp_name'], $target)) {
        $slip_path = "uploads/slips/" . $new_name;

        // ✅ อัปเดตข้อมูลในตาราง payments
        $stmt = $conn->prepare("UPDATE payments 
                                SET slip_path = ?, status = 'pending', paid_at = NOW() 
                                WHERE order_id = ?");
        $stmt->execute([$slip_path, $order_id]);

        // ✅ อัปเดตสถานะคำสั่งซื้อให้เป็น 'pending'
        $conn->prepare("UPDATE orders 
                        SET payment_status = 'pending', order_status = 'processing' 
                        WHERE order_id = ?")->execute([$order_id]);

        // ✅ เมื่ออัปโหลดสลิปสำเร็จ ให้ไปหน้า order_success.php
        header("Location: order_success.php?id=" . $order_id);
        exit;
      }
    }
  }
}
?>

<link rel="stylesheet" href="assets/css/payment_gateway.css">

<div class="gateway-container">
  <!-- ✅ ฝั่งซ้าย: QR และอัปโหลดสลิป -->
  <div class="gateway-left">
    <h4><?= htmlspecialchars($method) ?> QR</h4>

    <div class="qr-box">
      <?php if ($method === 'PromptPay'): ?>
        <img src="images/promptpay_qr.png" alt="PromptPay QR">
        <p>สแกนด้วยแอปธนาคาร หรือพร้อมเพย์</p>
      <?php else: ?>
        <img src="images/truemoney_qr.png" alt="TrueMoney QR">
        <p>สแกนด้วยแอป TrueMoney Wallet</p>
      <?php endif; ?>
    </div>

    <div class="timer" id="countdown">QR code จะหมดอายุใน 03:00 นาที</div>

    <hr>

    <h5>📤 อัปโหลดสลิปการโอนเงิน</h5>
    <form method="POST" enctype="multipart/form-data" class="upload-form">
      <input type="file" name="payment_slip" accept="image/*" required class="file-input">
      <button type="submit" class="btn-upload">📎 ยืนยันการชำระเงิน</button>
    </form>

    <div class="btn-group">
      <a href="index.php" class="btn-back">กลับหน้าหลัก</a>
    </div>
  </div>

  <!-- ✅ ฝั่งขวา: ข้อมูลการชำระ -->
  <div class="gateway-right">
    <h4>ข้อมูลการชำระเงิน</h4>

    <div class="summary-box">
      <div class="summary-row"><span>Order ID</span> <strong>#<?= $order_id ?></strong></div>
      <div class="summary-row"><span>Payment Number</span> <strong>#<?= $payment_number ?></strong></div>
      <div class="summary-row"><span>สร้าง</span> <strong><?= date("Y-m-d H:i:s") ?></strong></div>
      <div class="summary-row"><span>หมดอายุ</span> <strong><?= $expire_time ?></strong></div>
      <hr>
      <div class="summary-row"><span>ยอดคำสั่งซื้อ</span> <strong>THB <?= number_format($total_price, 2) ?></strong></div>
      <div class="summary-row"><span>Service Fee</span> <strong>THB <?= number_format($service_fee, 2) ?> (1.5%)</strong></div>
      <div class="summary-row total"><span>ยอดสุทธิ</span> <strong>THB <?= number_format($grand_total, 2) ?></strong></div>
    </div>

    <div class="paywith-box">
      <span>PAY WITH</span>
      <img src="<?= $method === 'PromptPay' ? 'images/th_thaipromptpayqr_bank.png' : 'images/truemoney.png' ?>" alt="Method Logo">
    </div>

    <p class="note">หลังจากอัปโหลดสลิปแล้ว ระบบจะตรวจสอบและยืนยันภายใน 3–5 นาที</p>
  </div>
</div>

<script>
// ✅ Countdown Timer 3 นาที
let timeLeft = 180;
const countdown = document.getElementById("countdown");

const timer = setInterval(() => {
  const minutes = String(Math.floor(timeLeft / 60)).padStart(2, '0');
  const seconds = String(timeLeft % 60).padStart(2, '0');
  countdown.textContent = `QR code จะหมดอายุใน ${minutes}:${seconds} นาที`;
  if (timeLeft <= 0) {
    clearInterval(timer);
    countdown.textContent = "❌ QR code หมดอายุแล้ว กรุณาทำรายการใหม่";
    countdown.style.color = "#ff4d4d";
  }
  timeLeft--;
}, 1000);
</script>

<?php include 'includes/footer.php'; ?>
