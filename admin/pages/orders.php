<?php
// $conn ถูกส่งมาจาก admin.php
require_once __DIR__ . '/../../includes/redeem_service.php'; // <-- อัปเดต path

if (isset($_POST['action'])) {
  $order_id = $_POST['order_id'];

  if ($_POST['action'] === 'mark_paid') {
    $conn->prepare("UPDATE orders SET payment_status='paid' WHERE order_id=?")->execute([$order_id]);
    $conn->prepare("UPDATE payments SET status='verified' WHERE order_id=?")->execute([$order_id]);

    $assign = assignRedeemKeys($conn, (int)$order_id);
    if (!empty($assign['success'])) {
      $conn->prepare("UPDATE orders SET order_status='completed' WHERE order_id=?")->execute([$order_id]);
      header("Location: admin.php?page=orders&msg=paid_assigned"); // <-- อัปเดต
    } else {
      $conn->prepare("UPDATE orders SET order_status='processing' WHERE order_id=?")->execute([$order_id]);
      header("Location: admin.php?page=orders&msg=paid_pending"); // <-- อัปเดต
    }
    exit;
  }

  if ($_POST['action'] === 'mark_pending') {
    $conn->prepare("UPDATE orders SET payment_status='pending', order_status='processing' WHERE order_id=?")->execute([$order_id]);
    header("Location: admin.php?page=orders&msg=pending"); // <-- อัปเดต
    exit;
  }

  if ($_POST['action'] === 'mark_cancelled') {
    $conn->prepare("UPDATE orders SET payment_status='failed', order_status='failed' WHERE order_id=?")->execute([$order_id]);
    $conn->prepare("UPDATE payments SET status='rejected' WHERE order_id=?")->execute([$order_id]);
    header("Location: admin.php?page=orders&msg=cancelled"); // <-- อัปเดต
    exit;
  }
}

$stmt = $conn->query("SELECT 
    o.order_id, o.total_price, o.payment_status, o.order_status, 
    o.created_at, o.payment_method, u.username,
    (SELECT slip_path FROM payments WHERE order_id = o.order_id ORDER BY payment_id DESC LIMIT 1) AS slip_image
  FROM orders o
  LEFT JOIN users u ON o.user_id = u.user_id
  ORDER BY o.created_at DESC");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($orders as &$oRow) {
  if (($oRow['payment_status'] ?? '') === 'paid' && ($oRow['order_status'] ?? '') === 'processing') {
    $res = assignRedeemKeys($conn, (int)$oRow['order_id']);
    if (!empty($res['success']) && empty($res['shortages'])) {
      $conn->prepare("UPDATE orders SET order_status='completed' WHERE order_id=?")->execute([$oRow['order_id']]);
      $oRow['order_status'] = 'completed';
    }
  }
}
unset($oRow);
?>
<?php
// ตรวจสอบว่ามีคอลัมน์ uid ใน order_details หรือไม่
$hasUidCol = false;
try {
  $c2 = $conn->query("SHOW COLUMNS FROM order_details LIKE 'uid'");
  $hasUidCol = (bool)$c2->fetchColumn();
} catch (Exception $e) { $hasUidCol = false; }
if ($hasUidCol) {
  foreach ($orders as &$row2) {
    $chk = $conn->prepare("SELECT COUNT(*) FROM order_details WHERE order_id=? AND uid IS NOT NULL AND uid!=''");
    $chk->execute([$row2['order_id']]);
    $row2['has_uid'] = ($chk->fetchColumn() > 0);
  }
  unset($row2);
}
?>

<!-- Using unified admin.css from admin.php -->

<div class="container mt-5 mb-5" style="max-width:1100px;">
  <div class="card shadow-sm border-0">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
      <h4 class="mb-0">จัดการคำสั่งซื้อ</h4>
      <div>
        <a href="admin.php?page=dashboard" class="btn btn-outline-light btn-sm me-2">📊 แดชบอร์ด</a>
        <a href="../index.php" class="btn btn-outline-light btn-sm">🏠 กลับหน้าหลัก</a>
      </div>
    </div>

    <div class="card-body">
      <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] === 'paid_assigned'): ?>
          <div class="alert alert-success text-center">ทำเครื่องหมายชำระเงินแล้ว และแจกคีย์เรียบร้อย</div>
        <?php elseif ($_GET['msg'] === 'paid_pending'): ?>
          <div class="alert alert-warning text-center">ทำเครื่องหมายชำระเงินแล้ว แต่คีย์ยังไม่เพียงพอ ระบบตั้งสถานะ Processing</div>
        <?php elseif ($_GET['msg'] === 'pending'): ?>
          <div class="alert alert-warning text-center">อัปเดตเป็น Pending แล้ว</div>
        <?php elseif ($_GET['msg'] === 'cancelled'): ?>
          <div class="alert alert-danger text-center">คำสั่งซื้อถูกยกเลิกแล้ว</div>
        <?php endif; ?>
      <?php endif; ?>

      <div class="table-responsive">
        <table class="table table-striped table-hover align-middle text-center">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>ผู้สั่งซื้อ</th>
              <th>วิธีชำระ</th>
              <th>ยอดรวม</th>
              <th>สถานะชำระ</th>
              <th>สถานะออเดอร์</th>
              <th>วันที่</th>
              <th>การจัดการ</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($orders as $order): ?>
              <tr>
                <td>#<?= $order['order_id'] ?></td>
                <td><?= htmlspecialchars($order['username'] ?? 'Guest') ?></td>
                <td><?= htmlspecialchars($order['payment_method'] ?? '-') ?></td>
                <td><?= number_format($order['total_price'], 2) ?> ฿</td>
                <td>
                  <?php if ($order['payment_status'] === 'paid'): ?>
                    <span class="badge bg-success px-3 py-2">ชำระแล้ว</span>
                  <?php elseif ($order['payment_status'] === 'pending'): ?>
                    <span class="badge bg-warning text-dark px-3 py-2">รอตรวจสอบ</span>
                  <?php elseif ($order['payment_status'] === 'unpaid'): ?>
                    <span class="badge bg-secondary px-3 py-2">ยังไม่ชำระ</span>
                  <?php else: ?>
                    <span class="badge bg-danger px-3 py-2">ล้มเหลว</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($order['order_status'] === 'completed'): ?>
                    <span class="badge bg-primary px-3 py-2">เสร็จสิ้น</span>
                  <?php elseif ($order['order_status'] === 'processing'): ?>
                    <span class="badge bg-info text-dark px-3 py-2">กำลังดำเนินการ</span>
                  <?php elseif ($order['order_status'] === 'cancelled'): ?>
                    <span class="badge bg-dark px-3 py-2">ยกเลิก</span>
                  <?php else: ?>
                    <span class="badge bg-light text-dark px-3 py-2">-</span>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($order['created_at']) ?></td>
                <td>
                  <div class="d-flex flex-wrap justify-content-center gap-1">
                    <?php if (!empty($order['has_uid'])): ?>
                      <span class="badge bg-info">UID</span>
                    <?php endif; ?>
                    <a href="../order_success.php?id=<?= $order['order_id'] ?>" class="btn btn-sm btn-outline-primary">รายละเอียด</a>
                    <?php if ($order['slip_image']): ?>
                      <a href="<?= htmlspecialchars($order['slip_image']) ?>" target="_blank" class="btn btn-sm btn-outline-info">สลิป</a>
                    <?php endif; ?>
                    <?php if ($order['payment_status'] !== 'paid'): ?>
                      <form method="POST"> 
                        <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                        <button name="action" value="mark_paid" class="btn btn-sm btn-success">ชำระแล้ว</button>
                      </form>
                      <form method="POST">
                        <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                        <button name="action" value="mark_pending" class="btn btn-sm btn-warning text-dark">ตั้งเป็น Pending</button>
                      </form>
                      <form method="POST">
                        <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                        <button name="action" value="mark_cancelled" class="btn btn-sm btn-danger">ยกเลิก</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
