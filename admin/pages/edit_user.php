<?php
// $conn ถูกส่งมาจาก admin.php

if (!isset($_GET['id'])) {
  echo "<div class='alert alert-warning text-center mt-5'>ไม่พบผู้ใช้</div>";
  exit;
}

$user_id = intval($_GET['id']);
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
  echo "<div class='alert alert-danger text-center mt-5'>ไม่พบผู้ใช้</div>";
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username']);
  $email = trim($_POST['email']);
  $role = $_POST['role'];
  $status = $_POST['status'];
  $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;

  try {
    if ($password) {
      $update = $conn->prepare("UPDATE users SET username=?, email=?, password=?, role=?, status=? WHERE user_id=?");
      $update->execute([$username, $email, $password, $role, $status, $user_id]);
    } else {
      $update = $conn->prepare("UPDATE users SET username=?, email=?, role=?, status=? WHERE user_id=?");
      $update->execute([$username, $email, $role, $status, $user_id]);
    }
    $_SESSION['flash_message'] = "อัปเดตผู้ใช้เรียบร้อย!";
    header("Location: admin.php?page=users"); // <-- อัปเดต
    exit;
  } catch (Exception $e) {
    echo "<div class='alert alert-danger text-center mt-3'>Error: {$e->getMessage()}</div>";
  }
}
?>

<!-- Using unified admin.css from admin.php -->

<div class="container mt-5 mb-5" style="max-width:700px;">
  <div class="card shadow border-0">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
      <h4>แก้ไขผู้ใช้</h4>
      <a href="admin.php?page=users" class="btn btn-light btn-sm">⬅ กลับ</a> </div>

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
          <label class="form-label">รหัสผ่าน (เว้นว่างไว้หากไม่เปลี่ยน)</label>
          <input type="password" name="password" class="form-control" placeholder="New password">
        </div>
        <div class="mb-3">
          <label class="form-label">สิทธิ์</label>
          <select name="role" class="form-select">
            <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>User</option>
            <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">สถานะ</label>
          <select name="status" class="form-select">
            <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="banned" <?= $user['status'] === 'banned' ? 'selected' : '' ?>>Banned</option>
            <option value="pending" <?= $user['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
          </select>
        </div>
        <hr>
        <button type="submit" class="btn btn-primary w-100 mt-2">บันทึก</button>
      </form>
    </div>
  </div>
</div>
