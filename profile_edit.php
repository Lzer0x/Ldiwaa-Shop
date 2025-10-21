<?php
session_start();
include 'includes/db_connect.php';
include 'includes/header.php';

// ✅ ตรวจสอบว่าผู้ใช้ล็อกอินหรือไม่
if (empty($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user']['user_id'];
$message = "";
$error = "";

// ✅ ดึงข้อมูลปัจจุบันจากฐานข้อมูล
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// ✅ เมื่อมีการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm'];

    // ✅ ตรวจสอบข้อมูล
    if (empty($username) || empty($email)) {
        $error = "กรุณากรอกชื่อผู้ใช้และอีเมล";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "อีเมลไม่ถูกต้อง";
    } elseif (!empty($password) && $password !== $confirm) {
        $error = "รหัสผ่านไม่ตรงกัน";
    } else {
        try {
            // ✅ ตรวจสอบอีเมลซ้ำ (ยกเว้นของตัวเอง)
            $check = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $check->execute([$email, $user_id]);
            if ($check->rowCount() > 0) {
                $error = "อีเมลนี้ถูกใช้งานแล้ว";
            } else {
                // ✅ เตรียมอัปเดตข้อมูล
                if (!empty($password)) {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $sql = "UPDATE users SET username=?, email=?, password=? WHERE user_id=?";
                    $params = [$username, $email, $hashed, $user_id];
                } else {
                    $sql = "UPDATE users SET username=?, email=? WHERE user_id=?";
                    $params = [$username, $email, $user_id];
                }
                $update = $conn->prepare($sql);
                $update->execute($params);

                // ✅ อัปเดต session
                $_SESSION['user']['username'] = $username;
                $_SESSION['user']['email'] = $email;

                $message = "✅ อัปเดตข้อมูลเรียบร้อยแล้ว";
                // ดึงข้อมูลใหม่อีกครั้ง
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}
?>

<link rel="stylesheet" href="assets/css/profile_edit.css">

<div class="profile-edit-container">
  <div class="card">
    <h2>⚙️ แก้ไขโปรไฟล์</h2>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php elseif (!empty($message)): ?>
      <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="mb-3">
        <label>ชื่อผู้ใช้</label>
        <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required>
      </div>

      <div class="mb-3">
        <label>อีเมล</label>
        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
      </div>

      <div class="mb-3">
        <label>รหัสผ่านใหม่ (เว้นว่างถ้าไม่เปลี่ยน)</label>
        <input type="password" name="password" class="form-control" placeholder="••••••••">
      </div>

      <div class="mb-3">
        <label>ยืนยันรหัสผ่านใหม่</label>
        <input type="password" name="confirm" class="form-control" placeholder="••••••••">
      </div>

      <button type="submit" class="btn-save">บันทึกการเปลี่ยนแปลง</button>
      <a href="profile.php" class="btn-back">กลับ</a>
    </form>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
