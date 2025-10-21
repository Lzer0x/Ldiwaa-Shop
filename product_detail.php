<?php
session_start();
require_once 'includes/auth_user.php';
include 'includes/db_connect.php';
include 'includes/header.php';
header("Content-Type: text/html; charset=utf-8");

if (!isset($_GET['id'])) {
    echo "<div class='alert alert-danger text-center'>❌ ไม่พบสินค้าที่เลือก</div>";
    include 'includes/footer.php';
    exit;
}

$product_id = intval($_GET['id']);

// ✅ ดึงข้อมูลสินค้า
$stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    echo "<div class='alert alert-danger text-center'>❌ ไม่พบข้อมูลสินค้า</div>";
    include 'includes/footer.php';
    exit;
}

// ✅ ดึงแพ็กเกจราคาของสินค้า
$pkgStmt = $conn->prepare("SELECT * FROM product_prices WHERE product_id = ? ORDER BY price_thb ASC");
$pkgStmt->execute([$product_id]);
$packages = $pkgStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<link rel="stylesheet" href="assets/css/product_detail.css">

<div class="product-header">
  <img src="<?= htmlspecialchars($product['image_url'] ?: 'images/sample_product.jpg') ?>" alt="product">
  <div>
    <h2><?= htmlspecialchars($product['name']) ?></h2>
    <div class="region-tag">🌐 <?= htmlspecialchars($product['region'] ?: 'Global') ?></div>
    <p class="mt-2" style="max-width:600px;">
      <?= htmlspecialchars($product['description'] ?: 'ไม่มีรายละเอียดสินค้าพิเศษเพิ่มเติม') ?>
    </p>
  </div>
</div>

<form method="POST" action="cart_action.php" id="packageForm">
  <?php include 'includes/csrf.php'; ?>
  <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
  <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>">
  <input type="hidden" name="package_id" id="selected_package">
  <input type="hidden" name="order_uid" id="order_uid_hidden">

  <div class="section-content">
    <!-- 🔹 ซ้าย: รายการแพ็กเกจ -->
    <div>
      <?php if ($packages): ?>
        <?php foreach ($packages as $pkg): 
          $price = (float)$pkg['price_thb'];
          $discount = (float)$pkg['discount_percent'];
          $finalPrice = $discount ? $price - ($price * ($discount / 100)) : $price;
        ?>
        <div class="package-card" data-id="<?= $pkg['id'] ?>" data-price="<?= $finalPrice ?>">
          <div class="package-info">
            <img src="<?= htmlspecialchars($product['image_url'] ?: 'images/sample_card.jpg') ?>" alt="pkg">
            <div>
              <div class="package-title"><?= htmlspecialchars($pkg['title']) ?></div>
              <div class="old-price"><?= $discount ? '฿' . number_format($price, 2) : '' ?></div>
            </div>
          </div>
          <div class="price">฿<?= number_format($finalPrice, 2) ?></div>
        </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="alert alert-warning mt-4">ยังไม่มีแพ็กเกจสำหรับสินค้านี้</div>
      <?php endif; ?>
    </div>

    <!-- 🔹 ขวา: กล่องคำสั่งซื้อ -->
    <div class="order-box">
      <h5>สรุปคำสั่งซื้อ</h5>
      <input type="text" class="order-input" placeholder="กรอก Player ID (ถ้ามี)">
      <div class="qty-control" aria-label="Quantity selector">
        <button type="button" id="qtyMinus" aria-label="ลดจำนวน">-</button>
        <input type="number" name="quantity" id="quantity" value="1" min="1">
        <button type="button" id="qtyPlus" aria-label="เพิ่มจำนวน">+</button>
      </div>
      <hr>
      <p class="mb-1">ยอดรวมทั้งหมด</p>
      <div class="total-price" id="totalDisplay">฿0.00</div>

      <button type="submit" name="action" value="buy" class="btn-buy">ซื้อทันที</button>
      <button type="submit" name="action" value="add" class="btn-cart">เพิ่มลงตะกร้า</button>
    </div>
  </div>
</form>

<script>
const packages = document.querySelectorAll('.package-card');
const totalDisplay = document.getElementById('totalDisplay');
const selectedInput = document.getElementById('selected_package');
const qtyInput = document.getElementById('quantity');
const btnMinus = document.getElementById('qtyMinus');
const btnPlus = document.getElementById('qtyPlus');
const uidInput = document.querySelector('.order-input');
const uidHidden = document.getElementById('order_uid_hidden');
const isTopup = <?= (int)($product['category_id'] ?? 0) === 2 ? 'true' : 'false' ?>;
let unitPrice = 0;

// เลือกแพ็กเกจ
packages.forEach(pkg => {
  pkg.addEventListener('click', () => {
    packages.forEach(p => p.classList.remove('selected'));
    pkg.classList.add('selected');
    selectedInput.value = pkg.dataset.id;
    unitPrice = parseFloat(pkg.dataset.price || '0');
    updateTotal();
  });
});

// อัปเดตราคา
function updateTotal() {
  const q = Math.max(1, parseInt(qtyInput?.value || '1'));
  const total = unitPrice * q;
  totalDisplay.textContent = '฿' + total.toFixed(2);
}
btnMinus?.addEventListener('click', () => {
  const v = Math.max(1, parseInt(qtyInput.value || '1') - 1);
  qtyInput.value = v;
  updateTotal();
});
btnPlus?.addEventListener('click', () => {
  const v = Math.max(1, parseInt(qtyInput.value || '1') + 1);
  qtyInput.value = v;
  updateTotal();
});
qtyInput?.addEventListener('input', updateTotal);

// ตรวจสอบก่อนส่งฟอร์ม
document.getElementById('packageForm').addEventListener('submit', e => {
  if (!selectedInput.value) {
    e.preventDefault();
    alert('กรุณาเลือกแพ็กเกจก่อนทำรายการ');
  }
  if (uidHidden && uidInput) { uidHidden.value = uidInput.value.trim(); }
});

// ซ่อนช่อง UID ถ้าไม่ใช่สินค้าประเภท Top-up
if (!isTopup && uidInput) uidInput.style.display = 'none';
</script>

<?php include 'includes/footer.php'; ?>
