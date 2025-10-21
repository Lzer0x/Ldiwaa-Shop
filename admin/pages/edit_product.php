<?php
// $conn ถูกส่งมาจาก admin.php
include __DIR__ . '/../../includes/csrf.php'; // <-- อัปเดต path

$product_id = intval($_GET['id'] ?? 0);
if (!$product_id) {
  echo "<div class='alert alert-warning text-center mt-5'>ไม่พบสินค้า</div>";
  exit;
}

$stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$product) {
  echo "<div class='alert alert-danger text-center mt-5'>ไม่พบสินค้า</div>";
  exit;
}

$pkgStmt = $conn->prepare("SELECT * FROM product_prices WHERE product_id = ? ORDER BY id ASC");
$pkgStmt->execute([$product_id]);
$packages = $pkgStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
  if (!csrf_verify($_POST['csrf'] ?? '')) {
    echo "<div class='alert alert-danger text-center'>CSRF token invalid</div>";
    exit;
  }

  $name = trim($_POST['name']);
  $category_id = intval($_POST['category_id']);
  $region = trim($_POST['region']);
  $desc = trim($_POST['description']);
  $status = $_POST['status'];
  $image = $product['image_url'];

  if (!empty($_FILES['image']['name'])) {
    $dir = __DIR__ . '/../../uploads/products/'; // <-- อัปเดต path
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $fname = time() . '_' . basename($_FILES['image']['name']);
    $path = $dir . $fname;
    if (move_uploaded_file($_FILES['image']['tmp_name'], $path)) {
      $image = 'uploads/products/' . $fname;
    }
  }

  $update = $conn->prepare("UPDATE products SET name=?, category_id=?, region=?, description=?, image_url=?, status=? WHERE product_id=?");
  $update->execute([$name, $category_id, $region, $desc, $image, $status, $product_id]);

  $_SESSION['flash_message'] = "อัปเดตสินค้าเรียบร้อย";
  header("Location: admin.php?page=edit_product&id=$product_id"); // <-- อัปเดต
  exit;
}
?>

<!-- Using unified admin.css from admin.php -->

<div class="container mt-5 mb-5">
  <div class="card shadow border-0">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
      <h4>แก้ไขสินค้า: <?= htmlspecialchars($product['name']) ?></h4>
      <a href="admin.php?page=products" class="btn btn-light btn-sm">⬅ กลับ</a> </div>

    <div class="card-body">
      <?php if (!empty($_SESSION['flash_message'])): ?>
        <div class="alert alert-success"><?= $_SESSION['flash_message']; unset($_SESSION['flash_message']); ?></div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">ชื่อสินค้า</label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($product['name']) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">หมวดหมู่</label>
            <select name="category_id" class="form-select" required>
              <?php
                $cats = $conn->query("SELECT category_id, name_th AS name FROM categories ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($cats as $ct) {
                  $sel = ($product['category_id'] == $ct['category_id']) ? 'selected' : '';
                  echo '<option value="'.(int)$ct['category_id'].'" '.$sel.'>'.htmlspecialchars($ct['name']).'</option>';
                }
              ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Region</label>
            <input type="text" name="region" class="form-control" value="<?= htmlspecialchars($product['region']) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">สถานะ</label>
            <select name="status" class="form-select">
              <option value="active" <?= $product['status']==='active'?'selected':''; ?>>Active</option>
              <option value="inactive" <?= $product['status']!=='active'?'selected':''; ?>>Inactive</option>
            </select>
          </div>
          <div class="col-md-12">
            <label class="form-label">รายละเอียด</label>
            <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($product['description']) ?></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label">รูปภาพ</label>
            <input type="file" name="image" class="form-control" accept="image/*">
            <?php if (!empty($product['image_url'])): ?>
              <img src="../<?= htmlspecialchars($product['image_url']) ?>" class="mt-2 rounded" style="width:100px;"> <?php endif; ?>
          </div>
        </div>

        <hr>
        <h5>แพ็กเกจ / ราคา</h5>
        <div id="pkg-list">
          <?php foreach($packages as $pkg): ?>
          <div class="pkg-item d-flex gap-2 align-items-center mb-2" data-id="<?= $pkg['id'] ?>">
            <input type="text" class="form-control pkg-title" value="<?= htmlspecialchars($pkg['title']) ?>" placeholder="ชื่อแพ็กเกจ">
            <input type="number" step="0.01" class="form-control pkg-price" value="<?= $pkg['price_thb'] ?>" placeholder="ราคา">
            <input type="number" step="0.01" class="form-control pkg-discount" value="<?= $pkg['discount_percent'] ?>" placeholder="ส่วนลด (%)">
            <button type="button" class="btn btn-danger btn-sm" onclick="deletePkg(<?= $pkg['id'] ?>, this)">ลบ</button>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-outline-success btn-sm mt-2" onclick="addPkg()">➕ เพิ่มแพ็กเกจ</button>
        <hr>
        <button type="submit" name="save_product" class="btn btn-primary w-100 mt-3">บันทึกการเปลี่ยนแปลง</button>
      </form>
    </div>
  </div>
</div>

<script>
// Path ของ AJAX `../ajax_pkg_action.php` ยังคงถูกต้อง
// เพราะมันอ้างอิงจาก URL (admin.php) ซึ่งอยู่ใน /admin/
function addPkg() {
  const pkgList = document.getElementById('pkg-list');
  const div = document.createElement('div');
  div.className = 'pkg-item d-flex gap-2 align-items-center mb-2';
  div.innerHTML = `
    <input type="text" class="form-control pkg-title" placeholder="ชื่อแพ็กเกจ">
    <input type="number" step="0.01" class="form-control pkg-price" placeholder="ราคา">
    <input type="number" step="0.01" class="form-control pkg-discount" placeholder="ส่วนลด (%)">
    <button type="button" class="btn btn-success btn-sm" onclick="saveNewPkg(this)">บันทึก</button>
    <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.parentElement.remove()">ลบ</button>
  `;
  pkgList.appendChild(div);
}

function saveNewPkg(btn) {
  const parent = btn.parentElement;
  const title = parent.querySelector('.pkg-title').value.trim();
  const price = parseFloat(parent.querySelector('.pkg-price').value) || 0;
  const discount = parseFloat(parent.querySelector('.pkg-discount').value) || 0;
  if (!title || price <= 0) { alert('กรอกชื่อและราคาให้ถูกต้อง'); return; }
  btn.disabled = true;
  fetch('../ajax_pkg_action.php', {
    method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `action=add&product_id=<?= $product_id ?>&title=${encodeURIComponent(title)}&price=${price}&discount=${discount}`
  }).then(res => res.text()).then(res => {
    if (res.startsWith('OK')) {
      parent.querySelector('.btn-success').remove();
      parent.querySelector('.btn-outline-danger').outerHTML = `<button type=\"button\" class=\"btn btn-danger btn-sm\" onclick=\"deletePkg(${res.split(':')[1]}, this)\">ลบ</button>`;
      alert('เพิ่มแพ็กเกจแล้ว');
    } else alert('Error: ' + res);
  });
}

function deletePkg(id, el) {
  if (!confirm('ยืนยันลบแพ็กเกจ?')) return;
  fetch('../ajax_pkg_action.php', {
    method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `action=delete&id=${id}`
  }).then(res => res.text()).then(res => {
    if (res === 'OK') { el.parentElement.remove(); }
    else { alert('Error: ' + res); }
  });
}
</script>
