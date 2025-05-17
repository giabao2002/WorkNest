<?php
/**
 * Cấu hình chung cho hệ thống WorkNest
 */

// URL cơ sở của trang web
define('BASE_URL', 'http://localhost/WorkNest/');

// Đường dẫn gốc của ứng dụng
define('ROOT_PATH', __DIR__ . '/../');

// Múi giờ
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Cấu hình session
session_start();

// Hàm điều hướng trang
function redirect($url) {
    header("Location: " . BASE_URL . $url);
    exit;
}

// Hàm kiểm tra người dùng đã đăng nhập hay chưa
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Hàm kiểm tra quyền hạn người dùng
function has_permission($required_role) {
    if (!is_logged_in()) {
        return false;
    }
    
    return $_SESSION['user_role'] === $required_role || 
           ($_SESSION['user_role'] === 'admin') || 
           ($required_role === 'department_manager' && $_SESSION['user_role'] === 'project_manager');
}

// Hàm lấy thông báo flash
function get_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return '';
}

// Hàm đặt thông báo flash
function set_flash_message($message, $type = 'success') {
    $_SESSION['flash_message'] = [
        'message' => $message,
        'type' => $type
    ];
}

// Hàm định dạng ngày tháng
function format_date($date) {
    return date('d/m/Y', strtotime($date));
}

// Hàm định dạng ngày giờ
function format_datetime($datetime) {
    return date('d/m/Y H:i', strtotime($datetime));
}

// Include thư viện database
require_once ROOT_PATH . 'config/database.php'; 