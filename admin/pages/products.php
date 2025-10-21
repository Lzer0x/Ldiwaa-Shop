<?php
// $conn ถูกส่งมาจาก admin.php

if (isset($_GET['delete'])) {
  $id = intval($_GET['delete']);
  $conn->prepare("DELETE FROM products WHERE product_id=?")->execute([$id]);
  $conn->prepare("DELETE FROM product_prices WHERE product_id=?")->execute([$id]);
  $_SESSION['flash_message'] = "ลบสินค้าเรียบร้อย";
  header("Location: admin.php?page=products"); // <-- อัปเดต
  exit;
}

if (isset($_GET['toggle'])) {
  $id = intval($_GET['toggle']);
  $conn->query("UPDATE products SET status = IF(status='active', 'inactive', 'active') WHERE product_id=$id");
  $_SESSION['flash_message'] = "สลับสถานะสินค้าแล้ว";
  header("Location: admin.php?page=products"); // <-- อัปเดต
  exit;
}

$stmt = $conn->query("SELECT p.*, c.name_th AS category_name, MIN(pp.price_thb) AS min_price
  FROM products p
  LEFT JOIN categories c ON p.category_id = c.category_id
  LEFT JOIN product_prices pp ON p.product_id = pp.product_id
  GROUP BY p.product_id
  ORDER BY p.created_at DESC");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Using unified admin.css from admin.php -->

<div class="container mt-5 mb-5">
  <div class="card shadow-sm border-0">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
      <h4 class="mb-0">จัดการสินค้า</h4>
      <a href="admin.php?page=add_product" class="btn btn-success btn-sm">➕ เพิ่มสินค้าใหม่</a> </div>

    <div class="card-body">
      <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-info text-center"><?= $_SESSION['flash_message']; unset($_SESSION['flash_message']); ?></div>
      <?php endif; ?>

      <div class="table-responsive">
        <table class="table table-hover align-middle text-center">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>ภาพ</th>
              <th>ชื่อ</th>
              <th>หมวดหมู่</th>
              <th>ราคาเริ่ม</th>
              <th>สถานะ</th>
              <th>จัดการ</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($products): foreach ($products as $p): ?>
              <tr>
                <td><?= $p['product_id'] ?></td>
                <td><img src="../<?= htmlspecialchars($p['image_url'] ?: 'images/sample_product.jpg') ?>" width="60" height="60" style="border-radius:8px;object-fit:cover;"></td> <td><?= htmlspecialchars($p['name']) ?></td>
                <td><?= htmlspecialchars($p['category_name'] ?? '-') ?></td>
                <td>฿<?= number_format($p['min_price'], 2) ?></td>
                <td>
                  <?php if ($p['status'] === 'active'): ?>
                    <span class="badge bg-success">แสดง</span>
                  <?php else: ?>
                    <span class="badge bg-secondary">ซ่อน</span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="d-flex justify-content-center gap-1 flex-wrap">
                    <a href="admin.php?page=edit_product&id=<?= $p['product_id'] ?>" class="btn btn-sm btn-primary">แก้ไข</a>
                    <a href="admin.php?page=products&toggle=<?= $p['product_id'] ?>" class="btn btn-sm btn-warning">สลับสถานะ</a>
                    <a href="admin.php?page=products&delete=<?= $p['product_id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('ยืนยันลบสินค้า?')">ลบ</a>
                  </div>
                </td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="7" class="text-muted">ยังไม่มีสินค้า</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
