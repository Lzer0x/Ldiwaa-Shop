<?php
session_start();
include 'includes/db_connect.php';
include 'includes/header.php';

// ✅ เมื่อกดปุ่มสมัครสมาชิก
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm'];

    if (empty($username) || empty($email) || empty($password)) {
        $error = "กรุณากรอกข้อมูลให้ครบถ้วน";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "อีเมลไม่ถูกต้อง";
    } elseif ($password !== $confirm) {
        $error = "รหัสผ่านไม่ตรงกัน";
    } else {
        $check = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->rowCount() > 0) {
            $error = "อีเมลนี้มีอยู่ในระบบแล้ว";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'user')");
            $stmt->execute([$username, $email, $hashedPassword]);

            $_SESSION['success'] = "✅ สมัครสมาชิกสำเร็จ! กรุณาเข้าสู่ระบบ";
            header("Location: login.php");
            exit;
        }
    }
}
?>

<link rel="stylesheet" href="assets/css/login.css">

<div class="login-container">
  <!-- ฝั่งซ้าย -->
  <div class="login-left">
    <h2>สร้างบัญชีใหม่!</h2>
    <p>เข้าร่วมกับเราเพื่อรับสิทธิพิเศษ เติมเงิน ซื้อรหัสเกม และดูประวัติคำสั่งซื้อของคุณได้ทันที</p>
  </div>

  <!-- ฝั่งขวา (ฟอร์มสมัครสมาชิก) -->
  <div class="login-right">
    <div class="card">
      <h4>📝 สมัครสมาชิก</h4>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger text-center"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" autocomplete="off">
        <div class="mb-3">
          <label class="form-label">ชื่อผู้ใช้</label>
          <input type="text" name="username" class="form-control" required>
        </div>

        <div class="mb-3">
          <label class="form-label">อีเมล</label>
          <input type="email" name="email" class="form-control" required>
        </div>

        <div class="mb-3">
          <label class="form-label">รหัสผ่าน</label>
          <input type="password" name="password" class="form-control" required>
        </div>

        <div class="mb-3">
          <label class="form-label">ยืนยันรหัสผ่าน</label>
          <input type="password" name="confirm" class="form-control" required>
        </div>

        <button type="submit" class="btn-login">สมัครสมาชิก</button>
      </form>

      <p class="text-center mt-3 mb-0">
        มีบัญชีอยู่แล้ว? <a href="login.php">เข้าสู่ระบบ</a>
      </p>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
