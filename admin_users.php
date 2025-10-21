<?php
session_start();
require_once 'includes/auth_user.php';
include 'includes/db_connect.php';
include 'includes/header.php';

// ✅ ตรวจสอบสิทธิ์
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    echo "<div class='alert alert-danger text-center mt-5'>🚫 เฉพาะผู้ดูแลระบบเท่านั้น</div>";
    include 'includes/footer.php';
    exit;
}

// ✅ ลบผู้ใช้
if (isset($_GET['delete'])) {
    $uid = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->execute([$uid]);
    $_SESSION['flash_message'] = "ลบผู้ใช้ #$uid สำเร็จ";
    header("Location: admin_users.php");
    exit;
}

// ✅ เปลี่ยนสถานะผู้ใช้
if (isset($_GET['toggle'])) {
    $uid = intval($_GET['toggle']);
    $conn->query("UPDATE users SET status = IF(status='active','banned','active') WHERE user_id = $uid");
    $_SESSION['flash_message'] = "อัปเดตสถานะผู้ใช้ #$uid แล้ว";
    header("Location: admin_users.php");
    exit;
}

// ✅ ดึงข้อมูลผู้ใช้ทั้งหมด
$users = $conn->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<link rel="stylesheet" href="assets/css/admin_users.css">

<div class="container mt-5 mb-5">
  <div class="card shadow border-0">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
      <h4 class="m-0">👥 จัดการผู้ใช้ทั้งหมด</h4>
      <a href="admin_dashboard.php" class="btn btn-light btn-sm">⬅ กลับ</a>
    </div>

    <div class="card-body">
      <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-success"><?= $_SESSION['flash_message']; unset($_SESSION['flash_message']); ?></div>
      <?php endif; ?>

      <?php if (empty($users)): ?>
        <div class="text-center text-muted py-4">ยังไม่มีผู้ใช้ในระบบ</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle text-center">
            <thead class="table-dark">
              <tr>
                <th>ID</th>
                <th>ชื่อผู้ใช้</th>
                <th>อีเมล</th>
                <th>บทบาท</th>
                <th>สถานะ</th>
                <th>วันที่สมัคร</th>
                <th>การจัดการ</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($users as $u): ?>
                <tr>
                  <td><?= $u['user_id'] ?></td>
                  <td><?= htmlspecialchars($u['username']) ?></td>
                  <td><?= htmlspecialchars($u['email']) ?></td>
                  <td>
                    <?php if ($u['role'] === 'admin'): ?>
                      <span class="badge bg-primary">Admin</span>
                    <?php else: ?>
                      <span class="badge bg-secondary">User</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($u['status'] === 'active'): ?>
                      <span class="badge bg-success">Active</span>
                    <?php elseif ($u['status'] === 'banned'): ?>
                      <span class="badge bg-danger">Banned</span>
                    <?php else: ?>
                      <span class="badge bg-warning">Pending</span>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($u['created_at']) ?></td>
                  <td>
                    <a href="admin_edit_user.php?id=<?= $u['user_id'] ?>" class="btn btn-sm btn-outline-primary">
                      ✏️ แก้ไข
                    </a>
                    <a href="admin_users.php?toggle=<?= $u['user_id'] ?>" class="btn btn-sm btn-outline-warning"
                       onclick="return confirm('ยืนยันเปลี่ยนสถานะผู้ใช้นี้หรือไม่?')">
                      🔄 สถานะ
                    </a>
                    <?php if ($u['role'] !== 'admin'): ?>
                      <a href="admin_users.php?delete=<?= $u['user_id'] ?>" class="btn btn-sm btn-outline-danger"
                         onclick="return confirm('ยืนยันการลบผู้ใช้นี้หรือไม่?')">
                        🗑️ ลบ
                      </a>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
