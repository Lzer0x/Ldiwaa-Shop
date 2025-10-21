<?php
session_start();

// ล้างข้อมูลทั้งหมดใน session
$_SESSION = [];

// ถ้ามี cookie session ให้ลบทิ้งด้วย (เพื่อความชัวร์)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// ทำลาย session
session_destroy();

// เปลี่ยนเส้นทางกลับไปหน้าเข้าสู่ระบบ
header("Location: login.php");
exit;
?>
