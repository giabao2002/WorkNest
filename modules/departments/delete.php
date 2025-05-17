<?php
/**
 * Xóa phòng ban
 */

// Include config
require_once '../../config/config.php';

// Kiểm tra đăng nhập và quyền hạn
if (!is_logged_in() || (!has_permission('admin') && !has_permission('project_manager'))) {
    set_flash_message('Bạn không có quyền truy cập trang này', 'danger');
    redirect('dashboard.php');
}

// Kiểm tra ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    set_flash_message('ID phòng ban không hợp lệ', 'danger');
    redirect('modules/departments/index.php');
}

$department_id = (int)$_GET['id'];

// Kiểm tra phòng ban tồn tại
$department_query = query("SELECT * FROM departments WHERE id = $department_id");
if (num_rows($department_query) === 0) {
    set_flash_message('Không tìm thấy phòng ban', 'danger');
    redirect('modules/departments/index.php');
}

$department = fetch_array($department_query);

// Kiểm tra có nhân viên nào thuộc phòng ban này không
$staff_query = query("SELECT COUNT(*) as total FROM users WHERE department_id = $department_id");
$staff_count = fetch_array($staff_query)['total'];

// Kiểm tra có dự án nào được phân công cho phòng ban này không
$project_query = query("SELECT COUNT(*) as total FROM project_departments WHERE department_id = $department_id");
$project_count = fetch_array($project_query)['total'];

// Nếu có nhân viên hoặc dự án liên quan, không cho phép xóa
if ($staff_count > 0 || $project_count > 0) {
    $message = "Không thể xóa phòng ban này vì:";
    if ($staff_count > 0) {
        $message .= "<br>- Có $staff_count nhân viên thuộc phòng ban";
    }
    if ($project_count > 0) {
        $message .= "<br>- Có $project_count dự án được giao cho phòng ban";
    }
    $message .= "<br><br>Vui lòng chuyển nhân viên sang phòng ban khác và hủy phân công dự án trước khi xóa.";
    
    set_flash_message($message, 'danger');
    redirect('modules/departments/view.php?id=' . $department_id);
    exit;
}

// Thực hiện xóa phòng ban
$delete_query = query("DELETE FROM departments WHERE id = $department_id");

if ($delete_query) {
    set_flash_message('Xóa phòng ban thành công');
} else {
    set_flash_message('Có lỗi xảy ra khi xóa phòng ban', 'danger');
}

redirect('modules/departments/index.php');
?> 