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

// ✅ ตรวจสอบว่ามี ID ผู้ใช้ไหม
if (!isset($_GET['id'])) {
  echo "<div class='alert alert-warning text-center mt-5'>ไม่พบผู้ใช้</div>";
  include 'includes/footer.php';
  exit;
}

$user_id = intval($_GET['id']);

// ✅ ดึงข้อมูลผู้ใช้
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
  echo "<div class='alert alert-danger text-center mt-5'>❌ ไม่พบข้อมูลผู้ใช้</div>";
  include 'includes/footer.php';
  exit;
}

// ✅ เมื่อกดบันทึกการแก้ไข
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username']);
  $email = trim($_POST['email']);
  $role = $_POST['role'];
  $status = $_POST['status'];

  // ✅ เปลี่ยนรหัสผ่านถ้ามีกรอก
  $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;

  try {
    if ($password) {
      $update = $conn->prepare("UPDATE users SET username=?, email=?, password=?, role=?, status=? WHERE user_id=?");
      $update->execute([$username, $email, $password, $role, $status, $user_id]);
    } else {
      $update = $conn->prepare("UPDATE users SET username=?, email=?, role=?, status=? WHERE user_id=?");
      $update->execute([$username, $email, $role, $status, $user_id]);
    }

    $_SESSION['flash_message'] = "✅ อัปเดตข้อมูลผู้ใช้เรียบร้อยแล้ว!";
    header("Location: admin_users.php");
    exit;

  } catch (Exception $e) {
    echo "<div class='alert alert-danger text-center mt-3'>❌ เกิดข้อผิดพลาด: {$e->getMessage()}</div>";
  }
}
?>

<link rel="stylesheet" href="assets/css/admin_edit_user.css">

<div class="container mt-5 mb-5" style="max-width:700px;">
  <div class="card shadow border-0">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
      <h4>👤 แก้ไขข้อมูลผู้ใช้</h4>
      <a href="admin_users.php" class="btn btn-light btn-sm">⬅ กลับ</a>
    </div>

    <div class="card-body">
      <form method="POST">
        <div class="mb-3">
          <label class="form-label">ชื่อผู้ใช้</label>
          <input type="text" name="username" class="form-control" required value="<?= htmlspecialchars($user['username']) ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">อีเมล</label>
          <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($user['email']) ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">รหัสผ่านใหม่ (ไม่บังคับ)</label>
          <input type="password" name="password" class="form-control" placeholder="ใส่เฉพาะถ้าต้องการเปลี่ยนรหัสผ่าน">
        </div>

        <div class="mb-3">
          <label class="form-label">บทบาท</label>
          <select name="role" class="form-select">
            <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>ผู้ใช้ทั่วไป</option>
            <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>ผู้ดูแลระบบ</option>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">สถานะบัญชี</label>
          <select name="status" class="form-select">
            <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Active (ใช้งาน)</option>
            <option value="banned" <?= $user['status'] === 'banned' ? 'selected' : '' ?>>Banned (ระงับ)</option>
            <option value="pending" <?= $user['status'] === 'pending' ? 'selected' : '' ?>>Pending (รออนุมัติ)</option>
          </select>
        </div>

        <hr>
        <button type="submit" class="btn btn-primary w-100 mt-2">💾 บันทึกการแก้ไข</button>
      </form>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
