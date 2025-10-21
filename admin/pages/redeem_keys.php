<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../includes/auth_user.php';
require_once __DIR__ . '/../../includes/db_connect.php';

if ($_SESSION['user']['role'] !== 'admin') {
  echo "<div class='alert alert-danger text-center mt-5'>🚫 คุณไม่มีสิทธิ์เข้าถึงหน้านี้</div>";
  exit;
}

// ✅ เพิ่มคีย์ใหม่
if (isset($_POST['action']) && $_POST['action'] === 'add') {
  $product_id = $_POST['product_id'] ?? null;
  $package_id = $_POST['package_id'] ?: null;
  $key_code = trim($_POST['key_code']);
  if ($product_id && $key_code) {
    $stmt = $conn->prepare("INSERT INTO redeem_keys (product_id, package_id, key_code, status) VALUES (?, ?, ?, 'unused')");
    $stmt->execute([$product_id, $package_id, $key_code]);
    $_SESSION['flash_message'] = "✅ เพิ่มคีย์ใหม่เรียบร้อยแล้ว";
    header("Location: ?page=redeem_keys");
    exit;
  }
}

// ✅ อัปเดตคีย์
if (isset($_POST['action']) && $_POST['action'] === 'edit') {
  $key_id = $_POST['key_id'];
  $key_code = trim($_POST['key_code']);
  $status = $_POST['status'];
  $stmt = $conn->prepare("UPDATE redeem_keys SET key_code=?, status=? WHERE key_id=?");
  $stmt->execute([$key_code, $status, $key_id]);
  $_SESSION['flash_message'] = "✏️ แก้ไขข้อมูลเรียบร้อย";
  header("Location: ?page=redeem_keys");
  exit;
}

// ✅ ลบคีย์
if (isset($_GET['delete'])) {
  $id = (int) $_GET['delete'];
  $conn->prepare("DELETE FROM redeem_keys WHERE key_id=?")->execute([$id]);
  $_SESSION['flash_message'] = "🗑️ ลบคีย์เรียบร้อยแล้ว";
  header("Location: ?page=redeem_keys");
  exit;
}

// ✅ ดึงข้อมูลคีย์ทั้งหมด
$stmt = $conn->query("
  SELECT rk.*, p.name AS product_name, pp.title AS package_title
  FROM redeem_keys rk
  LEFT JOIN products p ON rk.product_id = p.product_id
  LEFT JOIN product_prices pp ON rk.package_id = pp.id
  ORDER BY rk.key_id DESC
");
$keys = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ ดึงข้อมูลสินค้า / แพ็กเกจ สำหรับฟอร์มเพิ่ม
$products = $conn->query("SELECT product_id, name FROM products WHERE category_id != 2 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$packages = $conn->query("SELECT id, title FROM product_prices ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid px-4">
  <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <h3 class="fw-bold">🎟️ จัดการ Redeem Keys</h3>
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addModal">➕ เพิ่มคีย์ใหม่</button>
  </div>

  <?php if (!empty($_SESSION['flash_message'])): ?>
    <div class="alert alert-success"><?= $_SESSION['flash_message']; unset($_SESSION['flash_message']); ?></div>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-body table-responsive">
      <table class="table table-bordered table-hover align-middle text-center">
        <thead class="table-light">
          <tr>
            <th>ID</th>
            <th>สินค้า</th>
            <th>แพ็กเกจ</th>
            <th>Key Code</th>
            <th>สถานะ</th>
            <th>Used By</th>
            <th>Used At</th>
            <th>จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($keys)): ?>
            <tr><td colspan="8" class="text-muted">ไม่มีข้อมูล</td></tr>
          <?php else: foreach ($keys as $r): ?>
            <tr>
              <td><?= $r['key_id'] ?></td>
              <td><?= htmlspecialchars($r['product_name'] ?? '-') ?></td>
              <td><?= htmlspecialchars($r['package_title'] ?? '-') ?></td>
              <td><code><?= htmlspecialchars($r['key_code']) ?></code></td>
              <td>
                <span class="badge bg-<?= $r['status'] === 'used' ? 'danger' : 'success' ?>">
                  <?= ucfirst($r['status']) ?>
                </span>
              </td>
              <td><?= htmlspecialchars($r['used_by'] ?? '-') ?></td>
              <td><?= htmlspecialchars($r['used_at'] ?? '-') ?></td>
              <td>
                <button class="btn btn-sm btn-primary" 
                        data-bs-toggle="modal" 
                        data-bs-target="#editModal"
                        data-id="<?= $r['key_id'] ?>"
                        data-code="<?= htmlspecialchars($r['key_code']) ?>"
                        data-status="<?= $r['status'] ?>">
                  ✏️ แก้ไข
                </button>
                <a href="?page=redeem_keys&delete=<?= $r['key_id'] ?>" 
                   onclick="return confirm('ยืนยันการลบคีย์นี้?')" 
                   class="btn btn-sm btn-danger">🗑️ ลบ</a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- 🟩 Modal: เพิ่มคีย์ -->
<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="add">
        <div class="modal-header">
          <h5 class="modal-title">➕ เพิ่ม Redeem Key</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">สินค้า</label>
            <select name="product_id" class="form-select" required>
              <option value="">-- เลือกสินค้า --</option>
              <?php foreach ($products as $p): ?>
                <option value="<?= $p['product_id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">แพ็กเกจ (ถ้ามี)</label>
            <select name="package_id" class="form-select">
              <option value="">-- ไม่ระบุ --</option>
              <?php foreach ($packages as $pkg): ?>
                <option value="<?= $pkg['id'] ?>"><?= htmlspecialchars($pkg['title']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Key Code</label>
            <input type="text" name="key_code" class="form-control" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">บันทึก</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- 🟦 Modal: แก้ไข -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="key_id" id="editKeyId">
        <div class="modal-header">
          <h5 class="modal-title">✏️ แก้ไข Redeem Key</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Key Code</label>
            <input type="text" name="key_code" id="editKeyCode" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">สถานะ</label>
            <select name="status" id="editStatus" class="form-select">
              <option value="unused">Unused</option>
              <option value="used">Used</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">บันทึก</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const editModal = document.getElementById('editModal');
editModal.addEventListener('show.bs.modal', event => {
  const btn = event.relatedTarget;
  document.getElementById('editKeyId').value = btn.dataset.id;
  document.getElementById('editKeyCode').value = btn.dataset.code;
  document.getElementById('editStatus').value = btn.dataset.status;
});
</script>
