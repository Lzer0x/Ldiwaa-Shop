<?php
session_start();
require_once 'includes/auth_user.php';
include 'includes/db_connect.php';
include 'includes/header.php';

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

<!-- ✅ เชื่อมต่อไฟล์ CSS -->
<link rel="stylesheet" href="assets/css/product_detail.css">

<div class="product-header">
  <img src="<?= htmlspecialchars($product['image_url'] ?: 'images/sample_product.jpg') ?>" alt="product">
  <div>
    <h2><?= htmlspecialchars($product['name']) ?></h2>
    <div class="region-tag">🌐 <?= htmlspecialchars($product['region'] ?: 'Global') ?></div>
    <p class="mt-2" style="max-width:600px;"><?= htmlspecialchars($product['description'] ?: 'ไม่มีรายละเอียดสินค้าเพิ่มเติม') ?></p>
  </div>
</div>

<form method="POST" action="cart_action.php" id="packageForm">
  <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>">
  <input type="hidden" name="package_id" id="selected_package">

  <div class="section-content">
    <!-- 🟢 ซ้าย: รายการแพ็กเกจ -->
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

    <!-- 🟢 ขวา: กล่องคำสั่งซื้อ -->
    <div class="order-box">
      <h5>ข้อมูลการสั่งซื้อ</h5>
      <input type="text" class="order-input" placeholder="กรอก Player ID (ถ้ามี)">
      <hr>
      <p class="mb-1">รวมทั้งหมด</p>
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

packages.forEach(pkg => {
  pkg.addEventListener('click', () => {
    packages.forEach(p => p.classList.remove('selected'));
    pkg.classList.add('selected');
    const price = pkg.dataset.price;
    selectedInput.value = pkg.dataset.id;
    totalDisplay.textContent = '฿' + parseFloat(price).toFixed(2);
  });
});

document.getElementById('packageForm').addEventListener('submit', e => {
  if (!selectedInput.value) {
    e.preventDefault();
    alert('กรุณาเลือกแพ็กเกจก่อนทำรายการ');
  }
});
</script>

<?php include 'includes/footer.php'; ?>
