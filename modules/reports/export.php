<?php
/**
 * Xuất báo cáo
 */

// Include config
require_once '../../config/config.php';

// Kiểm tra đăng nhập
if (!is_logged_in()) {
    redirect('index.php');
}

// Kiểm tra quyền truy cập báo cáo
if (!has_permission('admin') && !has_permission('project_manager') && !has_permission('department_manager')) {
    set_flash_message('Bạn không có quyền xuất báo cáo', 'danger');
    redirect('dashboard.php');
}

// Lấy loại báo cáo
$type = isset($_GET['type']) ? $_GET['type'] : '';
$period = isset($_GET['period']) ? $_GET['period'] : 'all';

// Tiêu đề và tên file
$filename = '';
$header = [];
$data = [];

// Tùy thuộc vào loại báo cáo
switch ($type) {
    case 'project':
        $filename = 'bao-cao-du-an_' . date('Y-m-d') . '.csv';
        $header = ['ID', 'Tên dự án', 'Người quản lý', 'Ngày bắt đầu', 'Ngày kết thúc', 'Trạng thái', 'Tiến độ (%)', 'Số công việc', 'Công việc hoàn thành'];
        
        // Điều kiện thời gian
        $where_condition = '';
        if ($period != 'all') {
            $time_range = '';
            switch ($period) {
                case 'week':
                    $time_range = '1 WEEK';
                    break;
                case 'month':
                    $time_range = '1 MONTH';
                    break;
                case 'quarter':
                    $time_range = '3 MONTH';
                    break;
                case 'year':
                    $time_range = '1 YEAR';
                    break;
            }
            $where_condition = " WHERE p.created_at >= DATE_SUB(CURDATE(), INTERVAL $time_range)";
        }
        
        // Lấy dữ liệu
        $sql = "SELECT p.id, p.name, u.name as manager_name, p.start_date, p.end_date, 
                s.name as status_name, p.progress,
                (SELECT COUNT(*) FROM tasks WHERE project_id = p.id) as total_tasks,
                (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND status_id = 3) as completed_tasks
                FROM projects p
                LEFT JOIN users u ON p.manager_id = u.id
                LEFT JOIN statuses s ON p.status_id = s.id
                $where_condition
                ORDER BY p.end_date ASC";
        $result = query($sql);
        
        // Tạo dữ liệu CSV
        while ($row = fetch_array($result)) {
            $data[] = [
                $row['id'],
                $row['name'],
                $row['manager_name'],
                $row['start_date'],
                $row['end_date'],
                $row['status_name'],
                $row['progress'],
                $row['total_tasks'],
                $row['completed_tasks']
            ];
        }
        break;
        
    case 'task':
        $filename = 'bao-cao-cong-viec_' . date('Y-m-d') . '.csv';
        $header = ['ID', 'Tên công việc', 'Dự án', 'Người thực hiện', 'Ngày bắt đầu', 'Hạn hoàn thành', 'Trạng thái', 'Ưu tiên', 'Hoàn thành (%)', 'Thời gian (giờ)'];
        
        // Điều kiện thời gian
        $where_condition = '';
        if ($period != 'all') {
            $time_range = '';
            switch ($period) {
                case 'week':
                    $time_range = '1 WEEK';
                    break;
                case 'month':
                    $time_range = '1 MONTH';
                    break;
                case 'quarter':
                    $time_range = '3 MONTH';
                    break;
                case 'year':
                    $time_range = '1 YEAR';
                    break;
            }
            $where_condition = " WHERE t.created_at >= DATE_SUB(CURDATE(), INTERVAL $time_range)";
        }
        
        // Lọc theo dự án
        if (isset($_GET['project_id']) && $_GET['project_id'] > 0) {
            $project_id = (int)$_GET['project_id'];
            $where_condition = empty($where_condition) ? " WHERE t.project_id = $project_id" : "$where_condition AND t.project_id = $project_id";
        }
        
        // Lấy dữ liệu
        $sql = "SELECT t.id, t.name, p.name as project_name, u.name as assigned_to,
                t.start_date, t.due_date, s.name as status, t.priority, t.progress, t.estimated_hours
                FROM tasks t
                LEFT JOIN projects p ON t.project_id = p.id
                LEFT JOIN users u ON t.assigned_to = u.id
                LEFT JOIN statuses s ON t.status_id = s.id
                $where_condition
                ORDER BY t.due_date ASC";
        $result = query($sql);
        
        // Tạo dữ liệu CSV
        while ($row = fetch_array($result)) {
            $priority_text = '';
            switch($row['priority']) {
                case 1: $priority_text = 'Thấp'; break;
                case 2: $priority_text = 'Bình thường'; break;
                case 3: $priority_text = 'Cao'; break;
                case 4: $priority_text = 'Khẩn cấp'; break;
                default: $priority_text = 'Không xác định';
            }
            
            $data[] = [
                $row['id'],
                $row['name'],
                $row['project_name'],
                $row['assigned_to'],
                $row['start_date'],
                $row['due_date'],
                $row['status'],
                $priority_text,
                $row['progress'],
                $row['estimated_hours']
            ];
        }
        break;
        
    case 'user':
        $filename = 'bao-cao-nhan-vien_' . date('Y-m-d') . '.csv';
        $header = ['Họ tên', 'Email', 'Phòng ban', 'Vai trò', 'Dự án tham gia', 'Tổng công việc', 'Hoàn thành', 'Đúng hạn', 'Tỉ lệ đúng hạn (%)', 'Thời gian trung bình (ngày)'];
        
        // Điều kiện thời gian
        $where_condition = '';
        if ($period != 'all') {
            $time_range = '';
            switch ($period) {
                case 'week':
                    $time_range = '1 WEEK';
                    break;
                case 'month':
                    $time_range = '1 MONTH';
                    break;
                case 'quarter':
                    $time_range = '3 MONTH';
                    break;
                case 'year':
                    $time_range = '1 YEAR';
                    break;
            }
            $where_condition = " AND t.created_at >= DATE_SUB(CURDATE(), INTERVAL $time_range)";
        }
        
        // Lọc theo phòng ban
        if (isset($_GET['department_id']) && $_GET['department_id'] > 0) {
            $department_id = (int)$_GET['department_id']; 
            $where_condition .= " AND u.department_id = $department_id";
        }
        
        // Lọc theo vai trò
        if (isset($_GET['role']) && !empty($_GET['role'])) {
            $role = escape_string($_GET['role']);
            $where_condition .= " AND u.role = '$role'";
        }
        
        // Vai trò người dùng
        $roles = [
            'admin' => 'Quản trị viên',
            'project_manager' => 'Quản lý dự án',
            'department_manager' => 'Quản lý phòng ban',
            'staff' => 'Nhân viên'
        ];
        
        // Lấy dữ liệu
        $sql = "SELECT u.name, u.email, d.name as department_name, u.role,
               COUNT(DISTINCT p.id) as project_count,
               COUNT(t.id) as task_count,
               SUM(CASE WHEN t.status_id = 3 THEN 1 ELSE 0 END) as completed_count,
               SUM(CASE WHEN t.status_id = 3 AND t.completed_date <= t.due_date THEN 1 ELSE 0 END) as on_time_count,
               AVG(CASE WHEN t.status_id = 3 THEN DATEDIFF(t.completed_date, t.start_date) ELSE NULL END) as avg_completion_days
               FROM users u
               LEFT JOIN departments d ON u.department_id = d.id
               LEFT JOIN tasks t ON u.id = t.assigned_to
               LEFT JOIN projects p ON t.project_id = p.id
               WHERE u.role != 'admin' $where_condition
               GROUP BY u.name, u.email, d.name, u.role
               ORDER BY completed_count DESC, on_time_count DESC";
        $result = query($sql);
        
        // Tạo dữ liệu CSV
        while ($row = fetch_array($result)) {
            $on_time_rate = $row['completed_count'] > 0 ? 
                round(($row['on_time_count'] / $row['completed_count']) * 100) : 0;
            
            $avg_days = is_null($row['avg_completion_days']) ? 'N/A' : round($row['avg_completion_days'], 1);
            
            $data[] = [
                $row['name'],
                $row['email'],
                $row['department_name'],
                $roles[$row['role']],
                $row['project_count'],
                $row['task_count'],
                $row['completed_count'],
                $row['on_time_count'],
                $on_time_rate,
                $avg_days
            ];
        }
        break;
        
    case 'department':
        $filename = 'bao-cao-phong-ban_' . date('Y-m-d') . '.csv';
        $header = ['Phòng ban', 'Quản lý', 'Số nhân viên', 'Dự án tham gia', 'Tổng công việc', 'Hoàn thành', 'Đúng hạn', 'Quá hạn', 'Tỉ lệ hoàn thành (%)', 'Tỉ lệ đúng hạn (%)'];
        
        // Điều kiện thời gian
        $where_condition = '';
        if ($period != 'all') {
            $time_range = '';
            switch ($period) {
                case 'week':
                    $time_range = '1 WEEK';
                    break;
                case 'month':
                    $time_range = '1 MONTH';
                    break;
                case 'quarter':
                    $time_range = '3 MONTH';
                    break;
                case 'year':
                    $time_range = '1 YEAR';
                    break;
            }
            $where_condition = " AND t.created_at >= DATE_SUB(CURDATE(), INTERVAL $time_range)";
        }
        
        // Lấy dữ liệu
        $sql = "SELECT d.name, u.name as manager_name,
                COUNT(DISTINCT u2.id) as user_count,
                COUNT(DISTINCT p.id) as project_count,
                COUNT(t.id) as task_count,
                SUM(CASE WHEN t.status_id = 3 THEN 1 ELSE 0 END) as completed_tasks,
                SUM(CASE WHEN t.status_id = 3 AND t.completed_date <= t.due_date THEN 1 ELSE 0 END) as on_time_tasks,
                SUM(CASE WHEN t.due_date < CURDATE() AND t.status_id IN (1, 2, 4) THEN 1 ELSE 0 END) as overdue_tasks
                FROM departments d
                LEFT JOIN users u ON d.manager_id = u.id
                LEFT JOIN users u2 ON d.id = u2.department_id
                LEFT JOIN tasks t ON u2.id = t.assigned_to
                LEFT JOIN projects p ON t.project_id = p.id
                WHERE 1=1 $where_condition
                GROUP BY d.name, u.name
                ORDER BY completed_tasks DESC";
        $result = query($sql);
        
        // Tạo dữ liệu CSV
        while ($row = fetch_array($result)) {
            $completion_rate = $row['task_count'] > 0 ? 
                round(($row['completed_tasks'] / $row['task_count']) * 100) : 0;
            $on_time_rate = $row['completed_tasks'] > 0 ? 
                round(($row['on_time_tasks'] / $row['completed_tasks']) * 100) : 0;
            
            $data[] = [
                $row['name'],
                $row['manager_name'] ?: 'Chưa phân công',
                $row['user_count'],
                $row['project_count'],
                $row['task_count'],
                $row['completed_tasks'],
                $row['on_time_tasks'],
                $row['overdue_tasks'],
                $completion_rate,
                $on_time_rate
            ];
        }
        break;
        
    default:
        set_flash_message('Loại báo cáo không hợp lệ', 'danger');
        redirect('index.php');
}

// Xuất dữ liệu ra CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Mở output stream
$output = fopen('php://output', 'w');

// Thêm BOM (Byte Order Mark) để Excel nhận dạng đúng Unicode
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Ghi tiêu đề
fputcsv($output, $header);

// Ghi dữ liệu
foreach ($data as $row) {
    fputcsv($output, $row);
}

// Đóng output stream
fclose($output);
exit();
?> 