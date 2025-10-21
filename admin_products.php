<?php
session_start();
require_once 'includes/auth_user.php';
include 'includes/db_connect.php';
include 'includes/header.php';

// ✅ ตรวจสอบสิทธิ์ผู้ดูแลระบบ
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
  echo "<div class='alert alert-danger text-center mt-5'>🚫 เฉพาะผู้ดูแลระบบเท่านั้น</div>";
  include 'includes/footer.php';
  exit;
}

// ✅ ลบสินค้า
if (isset($_GET['delete'])) {
  $id = intval($_GET['delete']);
  $conn->prepare("DELETE FROM products WHERE product_id=?")->execute([$id]);
  $conn->prepare("DELETE FROM product_prices WHERE product_id=?")->execute([$id]);
  $_SESSION['flash_message'] = "ลบสินค้าสำเร็จ";
  header("Location: admin_products.php");
  exit;
}

// ✅ เปลี่ยนสถานะเปิด/ปิดการขาย
if (isset($_GET['toggle'])) {
  $id = intval($_GET['toggle']);
  $conn->query("UPDATE products SET status = IF(status='active', 'inactive', 'active') WHERE product_id=$id");
  $_SESSION['flash_message'] = "อัปเดตสถานะสินค้าเรียบร้อย";
  header("Location: admin_products.php");
  exit;
}

// ✅ ดึงข้อมูลสินค้า
$stmt = $conn->query("
  SELECT 
    p.*, 
    MIN(pp.price_thb) AS min_price
  FROM products p
  LEFT JOIN product_prices pp ON p.product_id = pp.product_id
  GROUP BY p.product_id
  ORDER BY p.created_at DESC
");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<link rel="stylesheet" href="assets/css/admin_products.css">

<div class="container mt-5 mb-5">
  <div class="card shadow-sm border-0">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
      <h4 class="mb-0">🧩 จัดการสินค้า</h4>
      <a href="admin_add_product.php" class="btn btn-success btn-sm">➕ เพิ่มสินค้าใหม่</a>
    </div>

    <div class="card-body">
      <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-info text-center"><?= $_SESSION['flash_message']; unset($_SESSION['flash_message']); ?></div>
      <?php endif; ?>

      <div class="table-responsive">
        <table class="table table-hover align-middle text-center">
          <thead class="table-dark">
            <tr>
              <th>#</th>
              <th>ภาพสินค้า</th>
              <th>ชื่อสินค้า</th>
              <th>หมวดหมู่</th>
              <th>ราคาเริ่มต้น</th>
              <th>สถานะ</th>
              <th>การจัดการ</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($products): ?>
              <?php foreach ($products as $p): ?>
              <tr>
                <td><?= $p['product_id'] ?></td>
                <td><img src="<?= htmlspecialchars($p['image_url'] ?: 'images/sample_product.jpg') ?>" width="60" height="60" style="border-radius:8px;object-fit:cover;"></td>
                <td><?= htmlspecialchars($p['name']) ?></td>
                <td><?= htmlspecialchars($p['category'] ?? '-') ?></td>
                <td>฿<?= number_format($p['min_price'], 2) ?></td>
                <td>
                  <?php if ($p['status'] === 'active'): ?>
                    <span class="badge bg-success">ขายอยู่</span>
                  <?php else: ?>
                    <span class="badge bg-secondary">ปิดการขาย</span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="d-flex justify-content-center gap-1 flex-wrap">
                    <a href="admin_edit_product.php?id=<?= $p['product_id'] ?>" class="btn btn-sm btn-primary">📝 แก้ไข</a>
                    <a href="?toggle=<?= $p['product_id'] ?>" class="btn btn-sm btn-warning">🔁 สถานะ</a>
                    <a href="?delete=<?= $p['product_id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('ต้องการลบสินค้านี้หรือไม่?')">❌ ลบ</a>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="7" class="text-muted">ยังไม่มีสินค้าในระบบ</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
