<?php
// upload_slip.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/auth_user.php'; // ensure auth_user runs and provides $_SESSION['user']

$user_id = $_SESSION['user']['user_id'] ?? null;
if (!$user_id) { header('Location: login.php'); exit; }

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($order_id <= 0) { http_response_code(400); exit('Invalid order'); }

// ดึงออเดอร์ของผู้ใช้คนนี้เท่านั้น และต้องยัง pending
$sql = "SELECT o.order_id, o.total_price, o.payment_method, o.payment_status
        FROM orders o
        WHERE o.order_id = ? AND o.user_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) { http_response_code(403); exit('ไม่พบคำสั่งซื้อของคุณ'); }
if ($order['payment_status'] !== 'pending') { exit('ออเดอร์นี้ไม่อยู่ในสถานะที่ต้องอัปโหลดสลิป'); }

// CSRF
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
        $msg = 'CSRF token ไม่ถูกต้อง';
    } else if (empty($_FILES['slip']['name'])) {
        $msg = 'กรุณาเลือกไฟล์สลิป';
    } else {
        $file = $_FILES['slip'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $msg = 'อัปโหลดไฟล์ล้มเหลว (error='.$file['error'].')';
        } else {
            $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/jpg'=>'jpg'];
            $finfo   = finfo_open(FILEINFO_MIME_TYPE);
            $mime    = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!isset($allowed[$mime])) {
                $msg = 'อนุญาตเฉพาะไฟล์ JPG/PNG/WebP เท่านั้น';
            } else if ($file['size'] > 3*1024*1024) {
                $msg = 'ไฟล์ใหญ่เกิน 3MB';
            } else {
                $ext = $allowed[$mime];
                $safeName = 'order-'.$order_id.'-'.time().'.'.$ext;
                $targetDir = __DIR__.'/uploads/slips/';
                if (!is_dir($targetDir)) { mkdir($targetDir, 0775, true); }
                $targetPath = $targetDir . $safeName;

                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    // บันทึก/อัปเดตตาราง payments (1 ออร์เดอร์ = 1 แถวล่าสุด: pending)
                    // ถ้ายังไม่มี payment แถวนี้ ให้ insert ใหม่
                    $conn->beginTransaction();

                    // payments: find latest payment row for this order
                    $check = $conn->prepare("SELECT payment_id FROM payments WHERE order_id = ? ORDER BY payment_id DESC LIMIT 1");
                    $check->execute([$order_id]);
                    $pay = $check->fetch(PDO::FETCH_ASSOC);

                    if ($pay) {
                        $upd = $conn->prepare("UPDATE payments 
                            SET method = ?, amount = ?, slip_image = ?, status='pending', paid_at = NOW()
                            WHERE payment_id = ?");
                        $upd->execute([$order['payment_method'] ?? 'PromptPay', $order['total_price'], 'uploads/slips/'.$safeName, $pay['payment_id']]);
                    } else {
                        $ins = $conn->prepare("INSERT INTO payments (order_id, method, amount, slip_image, paid_at, status)
                                              VALUES (?, ?, ?, ?, NOW(), 'pending')");
                        $ins->execute([$order_id, $order['payment_method'] ?? 'PromptPay', $order['total_price'], 'uploads/slips/'.$safeName]);
                    }

                    $conn->commit();
                    $msg = 'อัปโหลดสลิปสำเร็จ! กรุณารอแอดมินตรวจสอบ';
                } else {
                    $msg = 'ย้ายไฟล์ล้มเหลว';
                }
            }
        }
    }
}
?>
<?php include 'header.php'; ?>
<div class="container" style="max-width:680px;padding:20px;">
  <h3>อัปโหลดสลิปการชำระเงิน</h3>
  <p>Order #<?=htmlspecialchars($order_id)?> | ยอดชำระ: <?=number_format($order['total_price'],2)?> บาท</p>
  <?php if(!empty($msg)): ?>
    <div class="alert alert-<?=strpos($msg,'สำเร็จ')!==false?'success':'warning'?>"><?=htmlspecialchars($msg)?></div>
  <?php endif; ?>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['csrf'])?>">
    <div class="mb-3">
      <label class="form-label">ไฟล์สลิป (JPG/PNG/WebP, ≤ 3MB)</label>
      <input type="file" name="slip" accept=".jpg,.jpeg,.png,.webp" class="form-control" required>
    </div>
    <button class="btn btn-primary">อัปโหลด</button>
    <a class="btn btn-secondary" href="order_detail.php?order_id=<?=$order_id?>">กลับไปหน้ารายการ</a>
  </form>
</div>
<?php include 'footer.php'; ?>
