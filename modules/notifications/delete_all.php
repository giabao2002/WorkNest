<?php
/**
 * Xóa tất cả thông báo
 */

// Include file cấu hình
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Kiểm tra đăng nhập
if (!is_logged_in()) {
    redirect(BASE_URL . 'index.php');
}

// Lấy ID user hiện tại
$user_id = $_SESSION['user_id'];

// Xóa tất cả thông báo của user
$sql = "DELETE FROM notifications WHERE user_id = $user_id";
if (query($sql)) {
    set_flash_message('Đã xóa tất cả thông báo');
} else {
    set_flash_message('Có lỗi xảy ra khi xóa thông báo', 'danger');
}

// Trở về trang thông báo
redirect(BASE_URL . 'modules/notifications/');
?> 