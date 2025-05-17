<?php
// Include file cấu hình
require_once 'config/config.php';
require_once 'includes/functions.php';

// Hủy session
session_start();
session_unset();
session_destroy();

// Kiểm tra xem có phải tự động đăng xuất do timeout hay không
if (isset($_GET['timeout']) && $_GET['timeout'] == 1) {
    // Chuyển hướng với thông báo timeout
    header("Location: " . BASE_URL . "index.php?timeout=1");
} else {
    // Chuyển hướng đến trang đăng nhập với thông báo thành công
    header("Location: " . BASE_URL . "index.php?logout=success");
}
exit;
?> 