<?php
/**
 * Xóa thông báo
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

// Lấy ID thông báo từ query string
$notification_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Kiểm tra hợp lệ và thực hiện xóa
if ($notification_id > 0) {
    // Kiểm tra thông báo có thuộc về user hiện tại không
    $sql = "SELECT * FROM notifications WHERE id = $notification_id AND user_id = $user_id";
    $result = query($sql);
    
    if (num_rows($result) > 0) {
        // Xóa thông báo
        $delete_sql = "DELETE FROM notifications WHERE id = $notification_id AND user_id = $user_id";
        if (query($delete_sql)) {
            set_flash_message('Đã xóa thông báo thành công');
        } else {
            set_flash_message('Có lỗi xảy ra khi xóa thông báo', 'danger');
        }
    } else {
        set_flash_message('Không tìm thấy thông báo hoặc bạn không có quyền xóa', 'danger');
    }
}

// Trở về trang thông báo
redirect(BASE_URL . 'modules/notifications/');
?> 