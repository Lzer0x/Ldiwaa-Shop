<?php
session_start();
include 'includes/db_connect.php';
include 'includes/header.php';

// รับค่าจาก URL: รองรับ ?cat= (category_id) และ ?q= (search)
$catId = null;
$search = trim($_GET['q'] ?? '');
if (isset($_GET['cat']) && $_GET['cat'] !== '') {
  $catId = is_numeric($_GET['cat']) ? (int)$_GET['cat'] : null;
} elseif (!empty($_GET['category'])) {
  // backward-compat: try to resolve category by slug or name
  $raw = $_GET['category'];
  if (is_numeric($raw)) {
    $catId = (int)$raw;
  } else {
    $stmt = $conn->prepare("SELECT category_id FROM categories WHERE slug = ? OR name_th = ? LIMIT 1");
    $stmt->execute([$raw, $raw]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $catId = (int)$row['category_id'];
  }
}

// ดึงรายการหมวดหมู่ทั้งหมดจากตาราง categories
$categories = $conn->query("SELECT category_id, name_th FROM categories ORDER BY sort_order ASC, name_th ASC")->fetchAll(PDO::FETCH_ASSOC);

// ดึงสินค้าตามหมวดหรือคำค้น (prepared)
$query = "SELECT p.*, MIN(pp.price_thb) AS min_price
          FROM products p
          LEFT JOIN product_prices pp ON p.product_id = pp.product_id
          WHERE p.status = 'active'";
$params = [];
if ($catId !== null) {
  $query .= " AND p.category_id = ?";
  $params[] = $catId;
}
if ($search !== '') {
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
