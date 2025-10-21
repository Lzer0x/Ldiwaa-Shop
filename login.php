<?php
session_start();
include 'includes/db_connect.php';
include 'includes/header.php';

// ตรวจสอบว่ามีการส่งฟอร์มหรือไม่
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // รับค่า identifier ที่อาจจะเป็น username หรือ email
  $identifier = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';

  if ($identifier === '' || $password === '') {
    $error = "กรุณากรอกอีเมลหรือชื่อผู้ใช้ และรหัสผ่านให้ครบถ้วน";
  } else {
    // คิวรีหา user ตาม email หรือ username
    $stmt = $conn->prepare("SELECT user_id, username, email, password, role, status FROM users WHERE email = ? OR username = ? LIMIT 1");
    $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            if (($user['status'] ?? 'active') === 'banned') {
                $error = "บัญชีนี้ถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ";
            } else {
                $_SESSION['user'] = [
                    'user_id'  => (int)$user['user_id'],
                    'username' => $user['username'],
                    'email'    => $user['email'],
                    'role'     => $user['role'],
                ];
                $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?")->execute([$user['user_id']]);

                $cartStmt = $conn->prepare("
                    SELECT c.product_id, c.package_id, c.quantity,
                           p.name, p.image_url, pp.title, pp.price_thb
                    FROM carts c
                    INNER JOIN products p ON c.product_id = p.product_id
                    INNER JOIN product_prices pp ON c.package_id = pp.id
                    WHERE c.user_id = ?
                ");
                $cartStmt->execute([$user['user_id']]);
                $savedCart = $cartStmt->fetchAll(PDO::FETCH_ASSOC);

                if ($savedCart) {
                    $_SESSION['cart'] = [];
                    foreach ($savedCart as $item) {
                        $key = $item['product_id'] . "_" . $item['package_id'];
                        $_SESSION['cart'][$key] = [
                            'product_id' => $item['product_id'],
                            'package_id' => $item['package_id'],
                            'name'       => $item['name'],
                            'title'      => $item['title'],
                            'price'      => $item['price_thb'],
                            'image_url'  => $item['image_url'],
                            'quantity'   => $item['quantity']
                        ];
                    }
                }

                header("Location: " . ($user['role'] === 'admin' ? "admin_orders.php" : "index.php"));
                exit;
            }
        } else {
            $error = "อีเมลหรือรหัสผ่านไม่ถูกต้อง";
        }
    }
}
?>
<link rel="stylesheet" href="assets/css/login.css">

<div class="login-container">
  <div class="login-left">
    <h2>ยินดีต้อนรับกลับ!</h2>
    <p>เข้าสู่ระบบเพื่อเข้าถึงคำสั่งซื้อของคุณ เติมเงิน หรือดูประวัติธุรกรรมได้ทันที</p>
  </div>

  <div class="login-right">
    <div class="card">
      <h4>🔐 เข้าสู่ระบบ</h4>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="post" action="login.php" autocomplete="off" novalidate>
        <div class="mb-3">
          <label for="email" class="form-label">อีเมลหรือชื่อผู้ใช้</label>
          <input type="text" id="email" name="email" class="form-control"
                 required placeholder="อีเมลหรือชื่อผู้ใช้" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>

        <div class="mb-3">
          <label for="password" class="form-label">รหัสผ่าน</label>
          <input type="password" id="password" name="password" class="form-control" required>
        </div>

        <button type="submit" class="btn-login">เข้าสู่ระบบ</button>
      </form>

      <p class="text-center mt-3 mb-0">
        ยังไม่มีบัญชี? <a href="register.php">สมัครสมาชิก</a>
      </p>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
