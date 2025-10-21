<?php
// $conn ถูกส่งมาจาก admin.php
include __DIR__ . '/../../includes/csrf.php'; // <-- อัปเดต path

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_verify($_POST['csrf'] ?? '')) {
    echo "<div class='alert alert-danger text-center'>CSRF token invalid</div>";
    include __DIR__ . '/../../includes/footer.php'; // <-- อัปเดต path
    exit;
  }

  $name = trim($_POST['name']);
  $category_id = intval($_POST['category_id']);
  $region = trim($_POST['region']);
  $short_desc = trim($_POST['short_desc']);
  $description = trim($_POST['description']);
  $status = $_POST['status'];

  $image_path = null;
  if (!empty($_FILES['image']['name'])) {
    $uploadDir = '../uploads/products/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    $fileName = time() . '_' . basename($_FILES['image']['name']);
    $targetPath = $uploadDir . $fileName;
    if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
      $image_path = 'uploads/products/' . $fileName;
    }
  }

  try {
    $conn->beginTransaction();
    $stmt = $conn->prepare("INSERT INTO products (category_id, name, short_desc, description, image_url, region, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$category_id, $name, $short_desc, $description, $image_path, $region, $status]);
    $product_id = $conn->lastInsertId();

    if (!empty($_POST['pkg_title'])) {
      $pkgStmt = $conn->prepare("INSERT INTO product_prices (product_id, title, price_thb, discount_percent)
                                 VALUES (?, ?, ?, ?)");
      foreach ($_POST['pkg_title'] as $i => $title) {
        if (trim($title) === '') continue;
        $price = (float)$_POST['pkg_price'][$i];
        $discount = (float)$_POST['pkg_discount'][$i];
        $pkgStmt->execute([$product_id, $title, $price, $discount]);
      }
    }

    $conn->commit();
    $_SESSION['flash_message'] = "เพิ่มสินค้าเรียบร้อย!";
    header("Location: admin.php?page=products"); // <-- อัปเดต
    exit;
  } catch (Exception $e) {
    $conn->rollBack();
    echo "<div class='alert alert-danger text-center mt-4'>เกิดข้อผิดพลาด: " . $e->getMessage() . "</div>";
  }
}
?>

<!-- Using unified admin.css from admin.php -->

<div class="container mt-5 mb-5">
  <div class="card shadow-sm border-0">
    <div class="card-header bg-dark text-white">
      <h4>เพิ่มสินค้าใหม่</h4>
    </div>
    <div class="card-body">
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">ชื่อสินค้า</label>
            <input type="text" name="name" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">หมวดหมู่</label>
            <select name="category_id" class="form-select" required>
              <option value="">เลือกหมวดหมู่</option>
              <?php
                $cats = $conn->query("SELECT category_id, name_th AS name FROM categories ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($cats as $ct) {
                  echo '<option value="'.(int)$ct['category_id'].'">'.htmlspecialchars($ct['name']).'</option>';
                }
              ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Region</label>
            <input type="text" name="region" class="form-control" placeholder="Thailand / Global / Asia">
          </div>
          <div class="col-md-6">
            <label class="form-label">สถานะ</label>
            <select name="status" class="form-select">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">คำอธิบายสั้น</label>
            <input type="text" name="short_desc" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label">รูปภาพ</label>
            <input type="file" name="image" class="form-control" accept="image/*">
          </div>
        </div>

        <div class="mt-3">
          <label class="form-label">รายละเอียด</label>
          <textarea name="description" class="form-control" rows="3"></textarea>
        </div>

        <hr>
        <h5>แพ็กเกจ / ราคา</h5>
        <div id="pkg-list">
          <div class="pkg-item">
            <input type="text" name="pkg_title[]" class="form-control" placeholder="แพ็กเกจ เช่น 100 Diamonds" required>
            <input type="number" step="0.01" name="pkg_price[]" class="form-control" placeholder="ราคา (THB)" required>
            <input type="number" step="0.01" name="pkg_discount[]" class="form-control" placeholder="ส่วนลด (%)">
            <button type="button" class="btn btn-danger btn-sm" onclick="removePkg(this)">ลบ</button>
          </div>
        </div>
        <button type="button" class="btn btn-outline-success btn-sm mt-2" onclick="addPkg()">➕ เพิ่มแพ็กเกจ</button>

        <hr>
        <button type="submit" class="btn btn-success w-100 mt-3">บันทึกสินค้า</button>
      </form>
    </div>
  </div>
</div>

<script>
function addPkg() {
  const pkgList = document.getElementById("pkg-list");
  const div = document.createElement("div");
  div.className = "pkg-item";
  div.innerHTML = `
    <input type="text" name="pkg_title[]" class="form-control" placeholder="แพ็กเกจ" required>
    <input type="number" step="0.01" name="pkg_price[]" class="form-control" placeholder="ราคา (THB)" required>
    <input type="number" step="0.01" name="pkg_discount[]" class="form-control" placeholder="ส่วนลด (%)">
    <button type="button" class="btn btn-danger btn-sm" onclick="removePkg(this)">ลบ</button>
  `;
  pkgList.appendChild(div);
}
function removePkg(btn) { btn.parentElement.remove(); }
</script>
