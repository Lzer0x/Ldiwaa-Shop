<?php
session_start();
require_once 'includes/auth_user.php';
include 'includes/db_connect.php';
include 'includes/header.php';

// ✅ ตรวจสอบสิทธิ์แอดมิน
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
  echo "<div class='alert alert-danger text-center mt-5'>🚫 เฉพาะผู้ดูแลระบบเท่านั้น</div>";
  include 'includes/footer.php';
  exit;
}

// ✅ เมื่อกด “เพิ่มสินค้า”
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name']);
  $category = trim($_POST['category']);
  $region = trim($_POST['region']);
  $short_desc = trim($_POST['short_desc']);
  $description = trim($_POST['description']);
  $status = $_POST['status'];

  // ✅ อัปโหลดภาพ
  $image_path = null;
  if (!empty($_FILES['image']['name'])) {
    $uploadDir = 'uploads/products/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    $fileName = time() . '_' . basename($_FILES['image']['name']);
    $targetPath = $uploadDir . $fileName;

    if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
      $image_path = $targetPath;
    }
  }

  try {
    $conn->beginTransaction();

    // ✅ 1. เพิ่มสินค้าหลัก
    $stmt = $conn->prepare("INSERT INTO products (category, name, short_desc, description, image_url, region, status, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
$stmt->execute([$category, $name, $short_desc, $description, $image_path, $region, $status]);

    $product_id = $conn->lastInsertId();

    // ✅ 2. เพิ่มแพ็กเกจราคา
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
    $_SESSION['flash_message'] = "✅ เพิ่มสินค้าใหม่เรียบร้อยแล้ว!";
    header("Location: admin_products.php");
    exit;
  } catch (Exception $e) {
    $conn->rollBack();
    echo "<div class='alert alert-danger text-center mt-4'>❌ เกิดข้อผิดพลาด: " . $e->getMessage() . "</div>";
  }
}
?>

<link rel="stylesheet" href="assets/css/admin_add_product.css">

<div class="container mt-5 mb-5">
  <div class="card shadow-sm border-0">
    <div class="card-header bg-dark text-white">
      <h4>➕ เพิ่มสินค้าใหม่</h4>
    </div>
    <div class="card-body">
      <form method="POST" enctype="multipart/form-data">

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">ชื่อสินค้า</label>
            <input type="text" name="name" class="form-control" required>
          </div>
          <div class="col-md-6">
  <label class="form-label">หมวดหมู่</label>
  <input type="text" name="category" class="form-control" placeholder="เช่น Game / Wallet / Gift Card" required>
</div>

          <div class="col-md-6">
            <label class="form-label">ภูมิภาค (region)</label>
            <input type="text" name="region" class="form-control" placeholder="Thailand / Global / Asia">
          </div>
          <div class="col-md-6">
            <label class="form-label">สถานะ</label>
            <select name="status" class="form-select">
              <option value="active">Active (เปิดขาย)</option>
              <option value="inactive">Inactive (ปิดขาย)</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">คำอธิบายสั้น</label>
            <input type="text" name="short_desc" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label">อัปโหลดภาพสินค้า</label>
            <input type="file" name="image" class="form-control" accept="image/*">
          </div>
        </div>

        <div class="mt-3">
          <label class="form-label">รายละเอียดสินค้า</label>
          <textarea name="description" class="form-control" rows="3"></textarea>
        </div>

        <hr>
        <h5>📦 เพิ่มแพ็กเกจราคา</h5>
        <div id="pkg-list">
          <div class="pkg-item">
            <input type="text" name="pkg_title[]" class="form-control" placeholder="ชื่อแพ็กเกจ เช่น 100 Diamonds" required>
            <input type="number" step="0.01" name="pkg_price[]" class="form-control" placeholder="ราคา (THB)" required>
            <input type="number" step="0.01" name="pkg_discount[]" class="form-control" placeholder="ส่วนลด (%)">
            <button type="button" class="btn btn-danger btn-sm" onclick="removePkg(this)">ลบ</button>
          </div>
        </div>
        <button type="button" class="btn btn-outline-success btn-sm mt-2" onclick="addPkg()">➕ เพิ่มแพ็กเกจใหม่</button>

        <hr>
        <button type="submit" class="btn btn-success w-100 mt-3">✅ บันทึกสินค้า</button>
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
    <input type="text" name="pkg_title[]" class="form-control" placeholder="ชื่อแพ็กเกจ" required>
    <input type="number" step="0.01" name="pkg_price[]" class="form-control" placeholder="ราคา (THB)" required>
    <input type="number" step="0.01" name="pkg_discount[]" class="form-control" placeholder="ส่วนลด (%)">
    <button type="button" class="btn btn-danger btn-sm" onclick="removePkg(this)">ลบ</button>
  `;
  pkgList.appendChild(div);
}

function removePkg(btn) {
  btn.parentElement.remove();
}
</script>

<?php include 'includes/footer.php'; ?>
