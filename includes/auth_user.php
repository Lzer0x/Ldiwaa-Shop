<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentFile = basename($_SERVER['PHP_SELF']);
if (in_array($currentFile, ['login.php', 'register.php'])) {
    return;
}

// ถ้ายังไม่ล็อกอิน → เด้งไป login
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

// เชื่อมต่อฐานข้อมูล (ใช้ PDO จาก db_connect.php)
require_once __DIR__ . '/db_connect.php';

try {
    // ตรวจสอบสถานะผู้ใช้จากฐานข้อมูล
    $userId = (int) ($_SESSION['user']['user_id'] ?? 0);
    if ($userId <= 0) {
        header('Location: login.php');
        exit;
    }

    $stmt = $conn->prepare("SELECT status FROM users WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user || $user['status'] === 'banned') {
        // ทำลาย session ทั้งหมด
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();

        header('Location: login.php?msg=' . urlencode('บัญชีของคุณถูกระงับการใช้งานแล้ว'));
        exit;
    }
} catch (PDOException $e) {
    // ถ้าเกิดปัญหากับ DB → ล็อกเอาต์ทันทีเพื่อความปลอดภัย
    $_SESSION = [];
    session_destroy();
    header('Location: login.php');
    exit;
}
?>
