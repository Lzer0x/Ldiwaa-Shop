<?php
session_start();
header("Content-Type: text/html; charset=utf-8");

require_once 'includes/auth_user.php';
include 'includes/db_connect.php';
include 'includes/header.php';
include 'includes/csrf.php';

// ✅ ถ้าไม่มีสินค้าในตะกร้า
if (empty($_SESSION['cart'])) {
  echo "<div class='alert alert-warning text-center'>ไม่มีสินค้าในตะกร้า กรุณาเลือกสินค้าก่อนชำระเงิน</div>";
  include 'includes/footer.php';
  exit;
}

$user_id = $_SESSION['user']['user_id'] ?? null;
$cart_items = $_SESSION['cart'];
$total_price = 0;
foreach ($cart_items as $item) {
  $total_price += $item['price'] * $item['quantity'];
}

// ✅ เมื่อกด “ดำเนินการชำระเงิน”
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_order'])) {
  if (!csrf_verify($_POST['csrf'] ?? '')) {
    echo "<div class='alert alert-danger text-center'>❌ CSRF token ไม่ถูกต้อง</div>";
    exit;
  }

  $method = $_POST['payment_method'];
  if (!$user_id) $user_id = 1; // จำลอง guest ถ้ายังไม่ได้ login

  try {
    $conn->beginTransaction();

    // ✅ 1. บันทึกคำสั่งซื้อหลัก
    $stmt = $conn->prepare("INSERT INTO orders (user_id, total_price, payment_method, payment_status, order_status, created_at)
                            VALUES (?, ?, ?, 'pending', 'processing', NOW())");
    $stmt->execute([$user_id, $total_price, $method]);
    $order_id = $conn->lastInsertId();

    // ✅ 2. บันทึกรายการสินค้าใน order_details
    // ตรวจสอบว่ามีคอลัมน์ uid หรือไม่
    $hasUidCol = false;
    try {
      $chk = $conn->query("SHOW COLUMNS FROM order_details LIKE 'uid'");
      $hasUidCol = (bool)$chk->fetchColumn();
    } catch (Exception $e) { $hasUidCol = false; }

    if ($hasUidCol) {
      $detailStmt = $conn->prepare("INSERT INTO order_details (order_id, product_id, package_id, quantity, price, uid)
                                    VALUES (?, ?, ?, ?, ?, ?)");
      foreach ($cart_items as $item) {
        $detailStmt->execute([
          $order_id,
          $item['product_id'],
          $item['package_id'],
          $item['quantity'],
          $item['price'],
          $item['uid'] ?? null
        ]);
      }
    } else {
      $detailStmt = $conn->prepare("INSERT INTO order_details (order_id, product_id, package_id, quantity, price)
                                    VALUES (?, ?, ?, ?, ?)");
      foreach ($cart_items as $item) {
        $detailStmt->execute([
          $order_id,
          $item['product_id'],
          $item['package_id'],
          $item['quantity'],
          $item['price']
        ]);
      }
    }

    // ✅ 3. สร้างรายการ payment เบื้องต้น (รออัปโหลดสลิป)
    $payStmt = $conn->prepare("INSERT INTO payments (order_id, method, amount, status, paid_at)
                               VALUES (?, ?, ?, 'pending', NOW())");
    $payStmt->execute([$order_id, $method, $total_price]);

    $conn->commit();

    // ✅ 4. ล้างตะกร้าและเก็บ order_id ล่าสุด
    if (!empty($_SESSION['user']['user_id'])) {
      $conn->prepare("DELETE FROM carts WHERE user_id = ?")->execute([$_SESSION['user']['user_id']]);
    }

    unset($_SESSION['cart']);
    $_SESSION['latest_order_id'] = $order_id;

    // ✅ 5. ส่งไปหน้า payment_gateway พร้อม order_id
    header("Location: payment_gateway.php?method=" . urlencode($method) . "&order_id=" . $order_id);
    exit;

  } catch (Exception $e) {
    $conn->rollBack();
    echo "<div class='alert alert-danger text-center mt-5'>❌ เกิดข้อผิดพลาด: {$e->getMessage()}</div>";
  }
}
?>

<link rel="stylesheet" href="assets/css/checkout.css">

<div class="checkout-container">
  <!-- ✅ ฝั่งซ้าย: วิธีการชำระ -->
  <div class="payment-left">
    <h4>เลือกวิธีการชำระเงิน</h4>

    <form method="POST" id="checkoutForm">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
      <div class="method-group">
        <div class="method-item">
          <input type="radio" name="payment_method" id="payPromptPay" value="PromptPay" checked>
          <label for="payPromptPay">
            <img src="images/th_thaipromptpayqr_bank.png" alt="PromptPay Logo" class="method-logo">
            <div class="method-info">
              <span class="method-title">PromptPay</span>
              <span class="method-sub">พร้อมเพย์</span>
            </div>
          </label>
        </div>

        <div class="method-item">
          <input type="radio" name="payment_method" id="payTrueMoney" value="TrueMoney">
          <label for="payTrueMoney">
            <img src="images/truemoney.png" alt="TrueMoney Logo" class="method-logo">
            <div class="method-info">
              <span class="method-title">TrueMoney eWallet</span>
              <span class="method-sub">ทรูมันนี่ วอลเล็ท</span>
            </div>
          </label>
        </div>
      </div>

      <button type="submit" name="confirm_order" class="btn-paynow">
        ✅ ดำเนินการชำระเงิน
      </button>
    </form>
  </div>

  <!-- ✅ ฝั่งขวา: สรุปการชำระ -->
  <div class="payment-right">
    <h4>ข้อมูลการสั่งซื้อ</h4>

    <div class="summary-box">
      <div class="summary-row"><span>จำนวนสินค้า</span> <strong><?= count($cart_items) ?> รายการ</strong></div>
      <div class="summary-row"><span>วันที่สั่งซื้อ</span> <strong><?= date("Y-m-d H:i:s") ?></strong></div>
      <hr>
      <div class="summary-row total">
        <span>รวมทั้งหมด</span>
        <strong>THB <?= number_format($total_price, 2) ?></strong>
      </div>
    </div>

    <div class="pay-box">
      <div class="pay-with">
        <span>PAY WITH</span>
        <img src="images/th_thaipromptpayqr_bank.png" alt="PromptPay" id="selectedPayLogo">
      </div>
      <div class="service-fee">
        <span>ค่าธรรมเนียม</span>
        <strong>THB <?= number_format($total_price * 0.015, 2) ?> <small>(1.5%)</small></strong>
      </div>
      <div class="pay-total">
        <span>รวมสุทธิ</span>
        <strong>THB <?= number_format($total_price * 1.015, 2) ?></strong>
      </div>
    </div>

    <div class="summary-items" style="margin-top:16px;">
      <h5 style="margin-bottom:8px;">รายการสินค้า</h5>
      <ul style="list-style:none;padding:0;margin:0;display:grid;gap:8px;">
        <?php foreach ($cart_items as $it): ?>
          <li style="background:#1a1f2b;border:1px solid #2e3447;border-radius:10px;padding:10px 12px;">
            <div><strong><?= htmlspecialchars($it['name']) ?></strong> (<?= htmlspecialchars($it['title']) ?>)</div>
            <div>จำนวน: <?= (int)$it['quantity'] ?> | ราคา/หน่วย: <?= number_format($it['price'], 2) ?> ฿</div>
            <?php if (!empty($it['uid'])): ?>
              <div style="color:#00d1ff;">UID: <?= htmlspecialchars($it['uid']) ?></div>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <p class="agree-text">
      โดยการดำเนินการต่อ ถือว่าคุณยอมรับ 
      <a href="#">เงื่อนไขการขาย</a> และ <a href="#">นโยบายความเป็นส่วนตัว</a>.
    </p>
  </div>
</div>

<script>
document.querySelectorAll('input[name="payment_method"]').forEach((radio) => {
  radio.addEventListener('change', (e) => {
    const logo = document.getElementById('selectedPayLogo');
    logo.src = (e.target.value === 'TrueMoney') 
      ? 'images/truemoney.png' 
      : 'images/th_thaipromptpayqr_bank.png';
  });
});
</script>

<?php include 'includes/footer.php'; ?>
