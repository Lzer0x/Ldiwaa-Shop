<?php
session_start();
include 'includes/db_connect.php';
include 'includes/header.php';

// ✅ รับค่าจาก URL (q มาจากช่องค้นหาใน header)
$category = $_GET['category'] ?? '';
$search = $_GET['q'] ?? '';

// ✅ ดึงรายการหมวดหมู่ทั้งหมด
$categories = $conn->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);

// ✅ ดึงสินค้าตามหมวดหรือคำค้น
$query = "SELECT p.*, MIN(pp.price_thb) AS min_price
          FROM products p
          LEFT JOIN product_prices pp ON p.product_id = pp.product_id
          WHERE p.status = 'active'";

$params = [];
if (!empty($category)) {
  $query .= " AND p.category = ?";
  $params[] = $category;
}
if (!empty($search)) {
  $query .= " AND p.name LIKE ?";
  $params[] = "%$search%";
}

$query .= " GROUP BY p.product_id ORDER BY p.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<link rel="stylesheet" href="assets/css/products.css">

<div class="products-page">
  <!-- 🔹 Sidebar หมวดหมู่ -->
  <aside class="filter-sidebar">
    <h4>หมวดหมู่</h4>
    <ul class="category-list">
      <li><a href="products.php" class="<?= empty($category) ? 'active' : '' ?>">ทั้งหมด</a></li>
      <?php foreach ($categories as $cat): ?>
        <li>
          <a href="products.php?category=<?= urlencode($cat) ?>" class="<?= ($category === $cat) ? 'active' : '' ?>">
            <?= htmlspecialchars($cat) ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </aside>

  <!-- 🔹 แสดงสินค้า -->
  <section class="products-list">
      <h1>All products</h1>
    
    <div class="row">
      <?php if ($products): ?>
        <?php foreach ($products as $p): ?>
          <div class="card">
            <img src="<?= htmlspecialchars($p['image_url'] ?: 'https://via.placeholder.com/400x250') ?>" alt="">
            <div class="info">
              <h5><?= htmlspecialchars($p['name']) ?></h5>
              <p class="region"><?= htmlspecialchars($p['region']) ?></p>
              <p class="price">เริ่มต้นที่ <?= number_format($p['min_price'], 2) ?> ฿</p>
              <a href="product_detail.php?id=<?= $p['product_id'] ?>" class="btn-detail">ดูรายละเอียด</a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p class="no-products">ไม่พบสินค้า</p>
      <?php endif; ?>
    </div>
  </section>
</div>

<?php include 'includes/footer.php'; ?>
