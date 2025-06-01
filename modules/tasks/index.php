<?php
/**
 * Danh sách công việc
 */

// Include config
require_once '../../config/config.php';

// Kiểm tra đăng nhập
if (!is_logged_in()) {
    redirect('index.php');
}

// Phân trang
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Các điều kiện lọc
$where_conditions = [];

// Lọc theo dự án
$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
if ($project_id > 0) {
    $where_conditions[] = "t.project_id = $project_id";
    
    // Lấy thông tin dự án
    $project_query = query("SELECT * FROM projects WHERE id = $project_id");
    if (num_rows($project_query) > 0) {
        $project = fetch_array($project_query);
    }
}

// Lọc theo phòng ban
$department_id = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
if ($department_id > 0) {
    $where_conditions[] = "t.department_id = $department_id";
    
    // Lấy thông tin phòng ban
    $department_query = query("SELECT * FROM departments WHERE id = $department_id");
    if (num_rows($department_query) > 0) {
        $department = fetch_array($department_query);
    }
}

// Lọc theo người được giao
$assigned_to = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($assigned_to > 0) {
    $where_conditions[] = "t.assigned_to = $assigned_to";
    
    // Lấy thông tin người dùng
    $user_query = query("SELECT * FROM users WHERE id = $assigned_to");
    if (num_rows($user_query) > 0) {
        $assigned_user = fetch_array($user_query);
    }
}

// Lọc theo trạng thái
$status_id = isset($_GET['status_id']) ? (int)$_GET['status_id'] : 0;
if ($status_id > 0) {
    $where_conditions[] = "t.status_id = $status_id";
}

// Lọc theo mức độ ưu tiên
$priority = isset($_GET['priority']) ? (int)$_GET['priority'] : 0;
if ($priority > 0) {
    $where_conditions[] = "t.priority = $priority";
}

// Tìm kiếm
$search = isset($_GET['search']) ? escape_string($_GET['search']) : '';
if (!empty($search)) {
    $where_conditions[] = "(t.title LIKE '%$search%' OR t.description LIKE '%$search%')";
}

// Lọc theo thời gian
$date_filter = isset($_GET['date_filter']) ? $_GET['date_filter'] : '';
if (!empty($date_filter)) {
    switch ($date_filter) {
        case 'today':
            $where_conditions[] = "t.due_date = CURDATE()";
            break;
        case 'tomorrow':
            $where_conditions[] = "t.due_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'this_week':
            $where_conditions[] = "YEARWEEK(t.due_date, 1) = YEARWEEK(CURDATE(), 1)";
            break;
        case 'next_week':
            $where_conditions[] = "YEARWEEK(t.due_date, 1) = YEARWEEK(DATE_ADD(CURDATE(), INTERVAL 1 WEEK), 1)";
            break;
        case 'overdue':
            $where_conditions[] = "t.due_date < CURDATE() AND t.status_id != 3";
            break;
    }
}

// Lọc theo công việc chưa có người thực hiện
if (isset($_GET['unassigned']) && $_GET['unassigned'] == 1) {
    $where_conditions[] = "t.assigned_to IS NULL";
}

// Quyền xem của người dùng
if (!has_permission('admin') && !has_permission('project_manager')) {
    $user_id = $_SESSION['user_id'];
    
    if (has_permission('department_manager')) {
        // Quản lý phòng ban chỉ xem được công việc của phòng ban mình quản lý
        $managed_dept_query = query("SELECT id FROM departments WHERE manager_id = $user_id");
        if (num_rows($managed_dept_query) > 0) {
            $managed_dept = fetch_array($managed_dept_query);
            $managed_dept_id = $managed_dept['id'];
            
            // Lấy danh sách nhân viên trong phòng ban
            $staff_ids = [];
            $staff_query = query("SELECT id FROM users WHERE department_id = $managed_dept_id");
            while ($staff = fetch_array($staff_query)) {
                $staff_ids[] = $staff['id'];
            }
            
            if (!empty($staff_ids)) {
                $staff_list = implode(',', $staff_ids);
                $where_conditions[] = "(t.department_id = $managed_dept_id OR t.assigned_to IN ($staff_list) OR t.assigned_by = $user_id OR t.assigned_to = $user_id)";
            } else {
                $where_conditions[] = "(t.department_id = $managed_dept_id OR t.assigned_by = $user_id OR t.assigned_to = $user_id)";
            }
        } else {
            $where_conditions[] = "(t.assigned_by = $user_id OR t.assigned_to = $user_id)";
        }
    } else {
        // Nhân viên chỉ xem được công việc liên quan đến mình
        $where_conditions[] = "(t.assigned_to = $user_id OR t.assigned_by = $user_id)";
    }
}

// Tổng hợp điều kiện WHERE
$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

// Lấy tổng số công việc
$total_query = query("SELECT COUNT(*) as total FROM tasks t $where_clause");
$total_row = fetch_array($total_query);
$total = $total_row['total'];
$total_pages = ceil($total / $limit);

// Sắp xếp
$order_by = isset($_GET['order_by']) ? $_GET['order_by'] : 'due_date';
$order_dir = isset($_GET['order_dir']) && $_GET['order_dir'] == 'desc' ? 'DESC' : 'ASC';

$valid_order_fields = ['id', 'title', 'priority', 'status_id', 'due_date', 'progress'];
if (!in_array($order_by, $valid_order_fields)) {
    $order_by = 'due_date';
}

// Lấy danh sách công việc
$sql = "SELECT t.*, 
        p.name as project_name, 
        d.name as department_name,
        u1.name as assigned_name, u1.avatar as assigned_avatar,
        u2.name as assigned_by_name
        FROM tasks t 
        LEFT JOIN projects p ON t.project_id = p.id
        LEFT JOIN departments d ON t.department_id = d.id
        LEFT JOIN users u1 ON t.assigned_to = u1.id
        LEFT JOIN users u2 ON t.assigned_by = u2.id
        $where_clause
        ORDER BY t.$order_by $order_dir
        LIMIT $offset, $limit";
$result = query($sql);

// Danh sách trạng thái công việc
$status_list = [
    1 => ['name' => 'Chưa bắt đầu', 'color' => 'primary'],
    2 => ['name' => 'Đang thực hiện', 'color' => 'warning'],
    3 => ['name' => 'Hoàn thành', 'color' => 'success'],
    4 => ['name' => 'Tạm dừng', 'color' => 'danger'],
    5 => ['name' => 'Đã hủy', 'color' => 'secondary']
];

// Danh sách ưu tiên
$priority_list = [
    1 => ['name' => 'Thấp', 'color' => 'secondary'],
    2 => ['name' => 'Trung bình', 'color' => 'info'],
    3 => ['name' => 'Cao', 'color' => 'warning'],
    4 => ['name' => 'Khẩn cấp', 'color' => 'danger']
];

// Danh sách lọc theo thời gian
$date_filter_list = [
    'today' => 'Hôm nay',
    'tomorrow' => 'Ngày mai',
    'this_week' => 'Tuần này',
    'next_week' => 'Tuần sau',
    'overdue' => 'Quá hạn'
];

// Lấy danh sách dự án cho bộ lọc
$projects_query = query("SELECT id, name FROM projects ORDER BY name");
$projects = [];
while ($project_row = fetch_array($projects_query)) {
    $projects[$project_row['id']] = $project_row['name'];
}

// Lấy danh sách phòng ban cho bộ lọc
$departments_query = query("SELECT id, name FROM departments ORDER BY name");
$departments = [];
while ($dept_row = fetch_array($departments_query)) {
    $departments[$dept_row['id']] = $dept_row['name'];
}

// Tiêu đề trang
$page_title = isset($project) ? "Công việc: " . $project['name'] : 
             (isset($department) ? "Công việc: " . $department['name'] : 
             (isset($assigned_user) ? "Công việc của: " . $assigned_user['name'] : "Quản lý công việc"));

// Include header
include_once '../../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <?php if (isset($project)): ?>
                Công việc: <?php echo $project['name']; ?>
            <?php elseif (isset($department)): ?>
                Công việc: <?php echo $department['name']; ?>
            <?php elseif (isset($assigned_user)): ?>
                Công việc của: <?php echo $assigned_user['name']; ?>
            <?php else: ?>
                Quản lý công việc
            <?php endif; ?>
        </h1>
        
        <?php if (has_permission('admin') || has_permission('project_manager') || has_permission('department_manager')): ?>
        <a href="add.php<?php echo $project_id > 0 ? '?project_id=' . $project_id : ''; ?>" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
            <i class="fas fa-plus fa-sm text-white-50"></i> Thêm công việc mới
        </a>
        <?php endif; ?>
    </div>

    <!-- Bộ lọc -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Bộ lọc công việc</h6>
        </div>
        <div class="card-body">
            <form method="GET" action="" id="filterForm">
                <!-- Giữ lại tham số project_id, department_id, user_id nếu có -->
                <?php if ($project_id > 0): ?>
                    <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
                <?php endif; ?>
                
                <?php if ($department_id > 0): ?>
                    <input type="hidden" name="department_id" value="<?php echo $department_id; ?>">
                <?php endif; ?>
                
                <?php if ($assigned_to > 0): ?>
                    <input type="hidden" name="user_id" value="<?php echo $assigned_to; ?>">
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" placeholder="Tìm kiếm công việc..." value="<?php echo $search; ?>">
                            <div class="input-group-append">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search fa-sm"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($project_id == 0): ?>
                    <div class="col-md-4 mb-3">
                        <select class="form-control" name="project_id" onchange="this.form.submit()">
                            <option value="0">-- Tất cả dự án --</option>
                            <?php foreach ($projects as $id => $name): ?>
                                <option value="<?php echo $id; ?>" <?php echo $project_id == $id ? 'selected' : ''; ?>>
                                    <?php echo $name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($department_id == 0): ?>
                    <div class="col-md-4 mb-3">
                        <select class="form-control" name="department_id" onchange="this.form.submit()">
                            <option value="0">-- Tất cả phòng ban --</option>
                            <?php foreach ($departments as $id => $name): ?>
                                <option value="<?php echo $id; ?>" <?php echo $department_id == $id ? 'selected' : ''; ?>>
                                    <?php echo $name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <select class="form-control" name="status_id" onchange="this.form.submit()">
                            <option value="0">-- Tất cả trạng thái --</option>
                            <?php foreach ($status_list as $id => $status): ?>
                                <option value="<?php echo $id; ?>" <?php echo $status_id == $id ? 'selected' : ''; ?>>
                                    <?php echo $status['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <select class="form-control" name="priority" onchange="this.form.submit()">
                            <option value="0">-- Tất cả mức ưu tiên --</option>
                            <?php foreach ($priority_list as $id => $priority_item): ?>
                                <option value="<?php echo $id; ?>" <?php echo $priority == $id ? 'selected' : ''; ?>>
                                    <?php echo $priority_item['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <select class="form-control" name="date_filter" onchange="this.form.submit()">
                            <option value="">-- Tất cả thời gian --</option>
                            <?php foreach ($date_filter_list as $key => $label): ?>
                                <option value="<?php echo $key; ?>" <?php echo $date_filter == $key ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="custom-control custom-checkbox mt-2">
                            <input type="checkbox" class="custom-control-input" id="unassigned" name="unassigned" value="1" 
                                   <?php echo isset($_GET['unassigned']) && $_GET['unassigned'] == 1 ? 'checked' : ''; ?> 
                                   onchange="this.form.submit()">
                            <label class="custom-control-label" for="unassigned">Chỉ hiện công việc chưa giao</label>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12 text-center">
                        <a href="index.php" class="btn btn-secondary">Đặt lại bộ lọc</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Danh sách công việc -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Danh sách công việc (<?php echo $total; ?>)</h6>
        </div>
        <div class="card-body">
            <?php 
            // Hiển thị thông báo
            $flash_message = get_flash_message();
            if (!empty($flash_message)) {
                echo '<div class="alert alert-' . $flash_message['type'] . ' alert-dismissible fade show" role="alert">
                    ' . $flash_message['message'] . '
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>';
            }
            ?>
            
            <?php if (num_rows($result) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="taskTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>
                                    <a href="<?php echo build_query_string(['order_by' => 'id', 'order_dir' => ($order_by == 'id' && $order_dir == 'ASC') ? 'desc' : 'asc']); ?>">
                                        ID
                                        <?php if ($order_by == 'id'): ?>
                                            <i class="fas fa-sort-<?php echo $order_dir == 'ASC' ? 'up' : 'down'; ?>"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="<?php echo build_query_string(['order_by' => 'title', 'order_dir' => ($order_by == 'title' && $order_dir == 'ASC') ? 'desc' : 'asc']); ?>">
                                        Tên công việc
                                        <?php if ($order_by == 'title'): ?>
                                            <i class="fas fa-sort-<?php echo $order_dir == 'ASC' ? 'up' : 'down'; ?>"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <?php if ($project_id == 0): ?>
                                <th>Dự án</th>
                                <?php endif; ?>
                                <?php if ($department_id == 0): ?>
                                <th>Phòng ban</th>
                                <?php endif; ?>
                                <th>Người thực hiện</th>
                                <th>
                                    <a href="<?php echo build_query_string(['order_by' => 'due_date', 'order_dir' => ($order_by == 'due_date' && $order_dir == 'ASC') ? 'desc' : 'asc']); ?>">
                                        Hạn hoàn thành
                                        <?php if ($order_by == 'due_date'): ?>
                                            <i class="fas fa-sort-<?php echo $order_dir == 'ASC' ? 'up' : 'down'; ?>"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="<?php echo build_query_string(['order_by' => 'priority', 'order_dir' => ($order_by == 'priority' && $order_dir == 'ASC') ? 'desc' : 'asc']); ?>">
                                        Ưu tiên
                                        <?php if ($order_by == 'priority'): ?>
                                            <i class="fas fa-sort-<?php echo $order_dir == 'ASC' ? 'up' : 'down'; ?>"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="<?php echo build_query_string(['order_by' => 'status_id', 'order_dir' => ($order_by == 'status_id' && $order_dir == 'ASC') ? 'desc' : 'asc']); ?>">
                                        Trạng thái
                                        <?php if ($order_by == 'status_id'): ?>
                                            <i class="fas fa-sort-<?php echo $order_dir == 'ASC' ? 'up' : 'down'; ?>"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="<?php echo build_query_string(['order_by' => 'progress', 'order_dir' => ($order_by == 'progress' && $order_dir == 'ASC') ? 'desc' : 'asc']); ?>">
                                        Tiến độ
                                        <?php if ($order_by == 'progress'): ?>
                                            <i class="fas fa-sort-<?php echo $order_dir == 'ASC' ? 'up' : 'down'; ?>"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($task = fetch_array($result)): ?>
                                <?php 
                                // Xác định màu cho trạng thái
                                $status_color = $status_list[$task['status_id']]['color'];
                                
                                // Xác định màu cho mức ưu tiên
                                $priority_color = $priority_list[$task['priority']]['color'];
                                
                                // Xác định class cho thời hạn
                                $now = strtotime(date('Y-m-d'));
                                $due_date = strtotime($task['due_date']);
                                $date_class = '';
                                
                                if ($task['status_id'] != 3 && $task['status_id'] != 5) { // Nếu không phải đã hoàn thành hoặc đã hủy
                                    if ($due_date < $now) {
                                        $date_class = 'text-danger font-weight-bold'; // Quá hạn
                                    } elseif ($due_date < strtotime('+3 days', $now)) {
                                        $date_class = 'text-warning font-weight-bold'; // Sắp đến hạn (3 ngày)
                                    }
                                }
                                ?>
                                <tr>
                                    <td><?php echo $task['id']; ?></td>
                                    <td><?php echo $task['title']; ?></td>
                                    <?php if ($project_id == 0): ?>
                                    <td>
                                        <a href="index.php?project_id=<?php echo $task['project_id']; ?>">
                                            <?php echo $task['project_name']; ?>
                                        </a>
                                    </td>
                                    <?php endif; ?>
                                    <?php if ($department_id == 0): ?>
                                    <td>
                                        <?php if ($task['department_id']): ?>
                                            <a href="index.php?department_id=<?php echo $task['department_id']; ?>">
                                                <?php echo $task['department_name']; ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">Chưa phân công</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php endif; ?>
                                    <td>
                                        <?php if ($task['assigned_to']): ?>
                                            <div class="d-flex align-items-center justify-content-evenly justify-content-evenly">
                                                <img class="rounded-circle mr-2" width="30" height="30" 
                                                     src="<?php echo BASE_URL . $task['assigned_avatar']; ?>" alt="">
                                                <a href="index.php?user_id=<?php echo $task['assigned_to']; ?>">
                                                    <?php echo $task['assigned_name']; ?>
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">Chưa phân công</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="<?php echo $date_class; ?>">
                                        <?php echo format_date($task['due_date']); ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $priority_color; ?>">
                                            <?php echo $priority_list[$task['priority']]['name']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $status_color; ?>">
                                            <?php echo $status_list[$task['status_id']]['name']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="progress">
                                            <div class="progress-bar bg-<?php echo $status_color; ?>" role="progressbar" 
                                                 style="width: <?php echo $task['progress']; ?>%" 
                                                 aria-valuenow="<?php echo $task['progress']; ?>" aria-valuemin="0" aria-valuemax="100">
                                                <?php echo $task['progress']; ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="view.php?id=<?php echo $task['id']; ?>" class="btn btn-info btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php 
                                        // Kiểm tra quyền sửa
                                        $can_edit = has_permission('admin') || has_permission('project_manager') || 
                                                  $task['assigned_by'] == $_SESSION['user_id'] || 
                                                  $task['assigned_to'] == $_SESSION['user_id'];
                                                  
                                        if (has_permission('department_manager') && !$can_edit) {
                                            // Kiểm tra xem người dùng có phải là quản lý phòng ban chịu trách nhiệm không
                                            $dept_manager_check = query("SELECT id FROM departments WHERE id = {$task['department_id']} AND manager_id = {$_SESSION['user_id']}");
                                            $can_edit = num_rows($dept_manager_check) > 0;
                                        }
                                        
                                        if ($can_edit):
                                        ?>
                                        <a href="edit.php?id=<?php echo $task['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php 
                                        // Chỉ admin, quản lý dự án và người tạo công việc có quyền xóa
                                        $can_delete = has_permission('admin') || has_permission('project_manager') || 
                                                    $task['assigned_by'] == $_SESSION['user_id'];
                                            
                                        if ($can_delete):
                                        ?>
                                        <a href="delete.php?id=<?php echo $task['id']; ?>" class="btn btn-danger btn-sm" 
                                           onclick="return confirm('Bạn có chắc chắn muốn xóa công việc này?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Phân trang -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?php echo build_query_string(['page' => $page-1]); ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                    <a class="page-link" href="<?php echo build_query_string(['page' => $i]); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?php echo build_query_string(['page' => $page+1]); ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Không tìm thấy công việc nào.
                    <?php if ($search || $project_id || $department_id || $status_id || $priority || $date_filter): ?>
                        <a href="index.php" class="alert-link">Xóa bộ lọc</a> và thử lại.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
/**
 * Hàm hỗ trợ tạo URL với tham số truy vấn
 */
function build_query_string($params = []) {
    $current_params = $_GET;
    
    // Gộp tham số hiện tại với tham số mới
    $merged_params = array_merge($current_params, $params);
    
    // Tạo chuỗi truy vấn
    return '?' . http_build_query($merged_params);
}

// Include footer
include_once '../../templates/footer.php';
?> 