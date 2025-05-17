<?php
/**
 * Tập hợp các hàm tiện ích cho hệ thống WorkNest
 */

// Hàm tạo và kiểm tra token CSRF
function csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function check_csrf_token() {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        set_flash_message('Phiên làm việc hết hạn hoặc không hợp lệ', 'danger');
        redirect('index.php');
    }
}

// Hàm tạo mật khẩu băm
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Hàm kiểm tra mật khẩu
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

// Hàm lấy trạng thái công việc
function get_task_status($status_id) {
    $statuses = [
        1 => 'Chưa bắt đầu',
        2 => 'Đang thực hiện',
        3 => 'Hoàn thành',
        4 => 'Tạm hoãn',
        5 => 'Hủy bỏ'
    ];
    
    return isset($statuses[$status_id]) ? $statuses[$status_id] : 'Không xác định';
}

// Hàm lấy màu cho trạng thái công việc
function get_task_status_color($status_id) {
    $colors = [
        1 => 'secondary',
        2 => 'primary',
        3 => 'success',
        4 => 'warning',
        5 => 'danger'
    ];
    
    return isset($colors[$status_id]) ? $colors[$status_id] : 'dark';
}

// Hàm lấy độ ưu tiên công việc
function get_task_priority($priority_id) {
    $priorities = [
        1 => 'Thấp',
        2 => 'Trung bình',
        3 => 'Cao',
        4 => 'Khẩn cấp'
    ];
    
    return isset($priorities[$priority_id]) ? $priorities[$priority_id] : 'Không xác định';
}

// Hàm lấy màu cho độ ưu tiên công việc
function get_task_priority_color($priority_id) {
    $colors = [
        1 => 'success',
        2 => 'info',
        3 => 'warning',
        4 => 'danger'
    ];
    
    return isset($colors[$priority_id]) ? $colors[$priority_id] : 'secondary';
}

// Hàm tính tỷ lệ hoàn thành dự án
function calculate_project_progress($project_id) {
    $sql = "SELECT COUNT(*) as total_tasks, 
                  SUM(CASE WHEN status_id = 3 THEN 1 ELSE 0 END) as completed_tasks 
           FROM tasks 
           WHERE project_id = " . (int)$project_id;
    
    $result = query($sql);
    $data = fetch_array($result);
    
    if ($data['total_tasks'] > 0) {
        return round(($data['completed_tasks'] / $data['total_tasks']) * 100);
    }
    
    return 0;
}

// Hàm tạo thông báo
function create_notification($user_id, $message, $link = '#') {
    $user_id = (int)$user_id;
    $message = escape_string($message);
    $link = escape_string($link);
    
    $sql = "INSERT INTO notifications (user_id, message, link, created_at, is_read) 
            VALUES ($user_id, '$message', '$link', NOW(), 0)";
    
    return query($sql);
}

// Hàm tạo và lưu file tải lên
function upload_file($file, $destination_dir = 'uploads/') {
    // Kiểm tra thư mục đích
    if (!is_dir(ROOT_PATH . $destination_dir)) {
        mkdir(ROOT_PATH . $destination_dir, 0755, true);
    }
    
    // Tạo tên file ngẫu nhiên để tránh trùng lặp
    $file_name = uniqid() . '_' . basename($file['name']);
    $target_path = ROOT_PATH . $destination_dir . $file_name;
    
    // Di chuyển file tải lên vào thư mục đích
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return $destination_dir . $file_name;
    }
    
    return false;
}

// Hàm cắt chuỗi
function truncate_string($string, $length = 100, $append = '...') {
    if (strlen($string) > $length) {
        $string = substr($string, 0, $length) . $append;
    }
    
    return $string;
}

// Hàm tạo breadcrumb
function breadcrumb($items) {
    $html = '<nav aria-label="breadcrumb"><ol class="breadcrumb">';
    
    $count = count($items);
    $i = 0;
    
    foreach ($items as $title => $link) {
        $i++;
        
        if ($i === $count) {
            $html .= '<li class="breadcrumb-item active" aria-current="page">' . $title . '</li>';
        } else {
            $html .= '<li class="breadcrumb-item"><a href="' . $link . '">' . $title . '</a></li>';
        }
    }
    
    $html .= '</ol></nav>';
    
    return $html;
}

// Hàm định dạng thời gian
function format_datetime($datetime, $format = 'd/m/Y H:i') {
    $date = new DateTime($datetime);
    
    $now = new DateTime();
    $diff = $now->diff($date);
    
    // Nếu thời gian trong ngày hôm nay
    if ($diff->days == 0) {
        if ($diff->h == 0 && $diff->i < 5) {
            return 'Vừa xong';
        } elseif ($diff->h == 0) {
            return $diff->i . ' phút trước';
        } else {
            return $diff->h . ' giờ trước';
        }
    } 
    // Nếu thời gian trong ngày hôm qua
    elseif ($diff->days == 1) {
        return 'Hôm qua lúc ' . $date->format('H:i');
    } 
    // Nếu thời gian trong tuần này
    elseif ($diff->days < 7) {
        return $diff->days . ' ngày trước';
    } 
    // Các trường hợp khác
    else {
        return $date->format($format);
    }
}

// Hàm đếm số thông báo chưa đọc
function count_unread_notifications($user_id) {
    $user_id = (int)$user_id;
    $sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = $user_id AND is_read = 0";
    $result = query($sql);
    $data = fetch_array($result);
    
    return $data['count'];
}

// Hàm lấy danh sách thông báo
function get_notifications($user_id, $limit = 10, $offset = 0) {
    $user_id = (int)$user_id;
    $limit = (int)$limit;
    $offset = (int)$offset;
    
    $sql = "SELECT * FROM notifications 
            WHERE user_id = $user_id 
            ORDER BY created_at DESC 
            LIMIT $offset, $limit";
    
    $result = query($sql);
    $notifications = [];
    
    while ($row = fetch_array($result)) {
        $notifications[] = $row;
    }
    
    return $notifications;
}

// Hàm đánh dấu thông báo đã đọc
function mark_notification_as_read($notification_id, $user_id) {
    $notification_id = (int)$notification_id;
    $user_id = (int)$user_id;
    
    $sql = "UPDATE notifications 
            SET is_read = 1 
            WHERE id = $notification_id AND user_id = $user_id";
    
    return query($sql);
}

// Hàm đánh dấu tất cả thông báo đã đọc
function mark_all_notifications_as_read($user_id) {
    $user_id = (int)$user_id;
    
    $sql = "UPDATE notifications 
            SET is_read = 1 
            WHERE user_id = $user_id AND is_read = 0";
    
    return query($sql);
} 