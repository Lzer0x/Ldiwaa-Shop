<?php
// includes/auth_admin.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// ต้องล็อกอินก่อน
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

// ต้องเป็น role admin
if (($_SESSION['user']['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit;
}

// เชื่อมต่อฐานข้อมูล (PDO)
require_once __DIR__ . '/db_connect.php';

try {
    $userId = (int) ($_SESSION['user']['user_id'] ?? 0);
    if ($userId <= 0) {
        header('Location: login.php');
        exit;
    }

    $stmt = $conn->prepare("SELECT status FROM users WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user || $user['status'] === 'banned') {
        // ล้าง session และเด้งออก
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();

        header('Location: login.php?msg=' . urlencode('บัญชีผู้ดูแลระบบนี้ถูกระงับการใช้งานแล้ว'));
        exit;
    }
} catch (PDOException $e) {
    $_SESSION = [];
    session_destroy();
    header('Location: login.php');
    exit;
}
?>
