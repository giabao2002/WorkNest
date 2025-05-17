<?php
/**
 * Đánh dấu thông báo đã đọc
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
$redirect_url = isset($_GET['redirect']) ? $_GET['redirect'] : BASE_URL . 'modules/notifications/';

// Kiểm tra hợp lệ và thực hiện đánh dấu đã đọc
if ($notification_id > 0) {
    // Kiểm tra thông báo có thuộc về user hiện tại không
    $sql = "SELECT * FROM notifications WHERE id = $notification_id AND user_id = $user_id";
    $result = query($sql);
    
    if (num_rows($result) > 0) {
        $notification = fetch_array($result);
        
        // Đánh dấu đã đọc
        mark_notification_as_read($notification_id, $user_id);
        
        // Chuyển hướng đến link của thông báo hoặc trang thông báo
        if (!empty($notification['link']) && $notification['link'] != '#') {
            $redirect_url = $notification['link'];
        }
    }
}

// Trở về trang được chỉ định
redirect($redirect_url);
?> 