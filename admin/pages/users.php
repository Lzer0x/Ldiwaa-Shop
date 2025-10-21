<?php
// $conn ถูกส่งมาจาก admin.php

if (isset($_GET['delete'])) {
    $uid = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->execute([$uid]);
    $_SESSION['flash_message'] = "ลบผู้ใช้ #$uid เรียบร้อย";
    header("Location: admin.php?page=users"); // <-- อัปเดต
    exit;
}

if (isset($_GET['toggle'])) {
    $uid = intval($_GET['toggle']);
    $conn->query("UPDATE users SET status = IF(status='active','banned','active') WHERE user_id = $uid");
    $_SESSION['flash_message'] = "สลับสถานะผู้ใช้แล้ว";
    header("Location: admin.php?page=users"); // <-- อัปเดต
    exit;
}

$users = $conn->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<link rel="stylesheet" href="../assets/css/admin_users.css">

<div class="container mt-5 mb-5">
  <div class="card shadow border-0">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
      <h4 class="m-0">จัดการผู้ใช้</h4>
      <a href="admin.php?page=dashboard" class="btn btn-light btn-sm">⬅ กลับ</a> </div>

    <div class="card-body">
      <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-success"><?= $_SESSION['flash_message']; unset($_SESSION['flash_message']); ?></div>
      <?php endif; ?>

      <?php if (empty($users)): ?>
        <div class="text-center text-muted py-4">ยังไม่มีผู้ใช้</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle text-center">
            <thead class="table-dark">
              <tr>
                <th>ID</th>
                <th>ชื่อผู้ใช้</th>
                <th>อีเมล</th>
                <th>สิทธิ์</th>
                <th>สถานะ</th>
                <th>สมัครเมื่อ</th>
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
                    <a href="admin.php?page=edit_user&id=<?= $u['user_id'] ?>" class="btn btn-sm btn-outline-primary">แก้ไข</a>
                    <a href="admin.php?page=users&toggle=<?= $u['user_id'] ?>" class="btn btn-sm btn-outline-warning" onclick="return confirm('ยืนยันสลับสถานะผู้ใช้?')">สลับสถานะ</a>
                    <?php if ($u['role'] !== 'admin'): ?>
                      <a href="admin.php?page=users&delete=<?= $u['user_id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('ยืนยันลบผู้ใช้?')">ลบ</a>
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