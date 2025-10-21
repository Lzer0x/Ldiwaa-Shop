<?php
session_start();
require_once 'includes/auth_user.php';
include 'includes/db_connect.php';
include 'includes/header.php';

// ✅ ตรวจสิทธิ์
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
  echo "<div class='alert alert-danger text-center mt-5'>🚫 เฉพาะผู้ดูแลระบบเท่านั้น</div>";
  include 'includes/footer.php';
  exit;
}

$product_id = intval($_GET['id'] ?? 0);
if (!$product_id) {
  echo "<div class='alert alert-warning text-center mt-5'>ไม่พบสินค้า</div>";
  include 'includes/footer.php';
  exit;
}

// ✅ ดึงข้อมูลสินค้า
$stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$product) {
  echo "<div class='alert alert-danger text-center mt-5'>❌ ไม่พบสินค้านี้</div>";
  include 'includes/footer.php';
  exit;
}

// ✅ ดึงแพ็กเกจ
$pkgStmt = $conn->prepare("SELECT * FROM product_prices WHERE product_id = ? ORDER BY id ASC");
$pkgStmt->execute([$product_id]);
$packages = $pkgStmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ บันทึกการแก้ไขสินค้า
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
  $name = trim($_POST['name']);
  $category = trim($_POST['category']);
  $region = trim($_POST['region']);
  $desc = trim($_POST['description']);
  $status = $_POST['status'];
  $image = $product['image_url'];

  if (!empty($_FILES['image']['name'])) {
    $dir = "uploads/products/";
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $fname = time() . "_" . basename($_FILES['image']['name']);
    $path = $dir . $fname;
    if (move_uploaded_file($_FILES['image']['tmp_name'], $path)) $image = $path;
  }

  $update = $conn->prepare("UPDATE products SET name=?, category=?, region=?, description=?, image_url=?, status=? WHERE product_id=?");
  $update->execute([$name, $category, $region, $desc, $image, $status, $product_id]);

  $_SESSION['flash_message'] = "✅ บันทึกข้อมูลสินค้าเรียบร้อยแล้ว";
  header("Location: admin_edit_product.php?id=$product_id");
  exit;
}
?>

<link rel="stylesheet" href="assets/css/admin_add_product.css">

<div class="container mt-5 mb-5">
  <div class="card shadow border-0">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
      <h4>✏️ แก้ไขสินค้า: <?= htmlspecialchars($product['name']) ?></h4>
      <a href="admin_products.php" class="btn btn-light btn-sm">⬅ กลับ</a>
    </div>

    <div class="card-body">
      <?php if (!empty($_SESSION['flash_message'])): ?>
        <div class="alert alert-success"><?= $_SESSION['flash_message']; unset($_SESSION['flash_message']); ?></div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">ชื่อสินค้า</label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($product['name']) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">หมวดหมู่</label>
            <input type="text" name="category" class="form-control" value="<?= htmlspecialchars($product['category']) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">ภูมิภาค</label>
            <input type="text" name="region" class="form-control" value="<?= htmlspecialchars($product['region']) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">สถานะ</label>
            <select name="status" class="form-select">
              <option value="active" <?= $product['status']==='active'?'selected':'' ?>>Active</option>
              <option value="inactive" <?= $product['status']==='inactive'?'selected':'' ?>>Inactive</option>
            </select>
          </div>
          <div class="col-md-12">
            <label class="form-label">รายละเอียดสินค้า</label>
            <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($product['description']) ?></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label">รูปภาพ</label>
            <input type="file" name="image" class="form-control">
            <?php if($product['image_url']): ?>
              <img src="<?= htmlspecialchars($product['image_url']) ?>" class="mt-2 rounded" style="width:100px;">
            <?php endif; ?>
          </div>
        </div>

        <hr>
        <h5>📦 แพ็กเกจราคา</h5>
        <div id="pkg-list">
          <?php foreach($packages as $pkg): ?>
          <div class="pkg-item d-flex gap-2 align-items-center mb-2" data-id="<?= $pkg['id'] ?>">
            <input type="text" class="form-control pkg-title" value="<?= htmlspecialchars($pkg['title']) ?>" placeholder="ชื่อแพ็กเกจ">
            <input type="number" step="0.01" class="form-control pkg-price" value="<?= $pkg['price_thb'] ?>" placeholder="ราคา">
            <input type="number" step="0.01" class="form-control pkg-discount" value="<?= $pkg['discount_percent'] ?>" placeholder="ส่วนลด (%)">
            <button type="button" class="btn btn-danger btn-sm" onclick="deletePkg(<?= $pkg['id'] ?>, this)">🗑</button>
          </div>
          <?php endforeach; ?>
        </div>

        <button type="button" class="btn btn-outline-success btn-sm mt-2" onclick="addPkg()">➕ เพิ่มแพ็กเกจใหม่</button>
        <hr>
        <button type="submit" name="save_product" class="btn btn-primary w-100 mt-3">💾 บันทึกข้อมูลสินค้า</button>
      </form>
    </div>
  </div>
</div>

<script>
// ✅ เพิ่มแพ็กเกจใหม่
function addPkg() {
  const pkgList = document.getElementById('pkg-list');
  const div = document.createElement('div');
  div.className = 'pkg-item d-flex gap-2 align-items-center mb-2';
  div.innerHTML = `
    <input type="text" class="form-control pkg-title" placeholder="ชื่อแพ็กเกจ">
    <input type="number" step="0.01" class="form-control pkg-price" placeholder="ราคา">
    <input type="number" step="0.01" class="form-control pkg-discount" placeholder="ส่วนลด (%)">
    <button type="button" class="btn btn-success btn-sm" onclick="saveNewPkg(this)">💾</button>
    <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.parentElement.remove()">❌</button>
  `;
  pkgList.appendChild(div);
}

// ✅ บันทึกแพ็กเกจใหม่แบบ AJAX
function saveNewPkg(btn) {
  const parent = btn.parentElement;
  const title = parent.querySelector('.pkg-title').value.trim();
  const price = parseFloat(parent.querySelector('.pkg-price').value) || 0;
  const discount = parseFloat(parent.querySelector('.pkg-discount').value) || 0;

  if (!title || price <= 0) {
    alert('กรุณากรอกชื่อแพ็กเกจและราคามากกว่า 0');
    return;
  }

  btn.disabled = true;
  fetch('ajax_pkg_action.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `action=add&product_id=<?= $product_id ?>&title=${encodeURIComponent(title)}&price=${price}&discount=${discount}`
  })
  .then(res => res.text())
  .then(res => {
    if (res.startsWith('OK')) {
      parent.querySelector('.btn-success').remove();
      parent.querySelector('.btn-outline-danger').outerHTML = `<button type="button" class="btn btn-danger btn-sm" onclick="deletePkg(${res.split(':')[1]}, this)">🗑</button>`;
      alert('✅ เพิ่มแพ็กเกจสำเร็จ');
    } else alert('❌ ' + res);
  });
}

// ✅ ลบแพ็กเกจทันที
function deletePkg(id, el) {
  if (!confirm('ต้องการลบแพ็กเกจนี้หรือไม่?')) return;
  fetch('ajax_pkg_action.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `action=delete&id=${id}`
  })
  .then(res => res.text())
  .then(res => {
    if (res === 'OK') {
      el.parentElement.remove();
    } else {
      alert('❌ ' + res);
    }
  });
}
</script>

<?php include 'includes/footer.php'; ?>
