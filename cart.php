<?php
session_start();
include 'includes/db_connect.php';
include 'includes/header.php';
?>

<link rel="stylesheet" href="assets/css/cart.css">

<div class="cart-page">
  <div class="cart-container">
    <h2 class="cart-title">ตะกร้าสินค้าของคุณ</h2>

    <?php if (isset($_SESSION['flash_message'])): ?>
      <div class="alert alert-success">
        <?= $_SESSION['flash_message']; unset($_SESSION['flash_message']); ?>
      </div>
    <?php endif; ?>

    <?php
    if (empty($_SESSION['cart'])) {
        echo "<div class='empty-cart'>ยังไม่มีสินค้าในตะกร้า</div>";
        include 'includes/footer.php';
        exit;
    }

    $total = 0;
    ?>

    <div class="cart-list">
      <?php foreach ($_SESSION['cart'] as $key => $item): ?>
        <?php
          $subtotal = $item['price'] * $item['quantity'];
          $total += $subtotal;
        ?>
        <div class="cart-item">
          <!-- ซ้าย -->
          <div class="cart-left">
            <input type="checkbox" checked class="cart-check">
          <img src="<?= htmlspecialchars($item['image_url'] ?: 'images/sample_product.jpg') ?>" 
     alt="<?= htmlspecialchars($item['name']) ?>" class="cart-thumb">


            <div class="cart-info">
              <div class="cart-name"><?= htmlspecialchars($item['name']) ?></div>
              <div class="cart-detail"><?= htmlspecialchars($item['title']) ?></div>
              <?php if (!empty($item['uid'])): ?>
                <div class="cart-uid" style="color:#00d1ff;">UID: <?= htmlspecialchars($item['uid']) ?></div>
              <?php endif; ?>
            </div>
          </div>

          <!-- ขวา -->
          <div class="cart-right">
            <div class="cart-price">฿<?= number_format($item['price'], 2) ?></div>

            <div class="cart-quantity">
  <a href="update_cart.php?key=<?= urlencode($key) ?>&action=decrease" class="qty-btn">−</a>
  <span><?= $item['quantity'] ?></span>
  <a href="update_cart.php?key=<?= urlencode($key) ?>&action=increase" class="qty-btn">+</a>
</div>


            <div class="cart-subtotal">฿<?= number_format($subtotal, 2) ?></div>

            <a href="remove_from_cart.php?key=<?= urlencode($key) ?>" class="cart-remove" title="ลบสินค้า">🗑️</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- สรุปราคา -->
    <div class="cart-footer">
      <div class="cart-summary">
        รวมทั้งหมด <span class="cart-total">฿<?= number_format($total, 2) ?></span>
      </div>
      <a href="checkout.php" class="checkout-btn">เช็คเอาท์</a>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
