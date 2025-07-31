<?php
// Include file cấu hình và functions
require_once 'config/config.php';
require_once 'includes/functions.php';

// Kiểm tra đăng nhập
if (!is_logged_in()) {
    redirect('index.php');
}

// Include header
include_once 'templates/header.php';

// Lấy thông tin người dùng hiện tại
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Lấy dữ liệu thống kê theo vai trò
$stats = [];
$assigned_tasks = 0;
$pending_tasks = 0;
$completed_tasks = 0;
$recent_tasks = [];
$my_projects = [];
$system_stats = false;
$dept_stats = false;
$dept_id = 0;

// Xác định xem người dùng có xem được thống kê toàn hệ thống hay không
if (has_permission('admin') || has_permission('project_manager')) {
    $system_stats = true;
}

// Nếu là quản lý phòng ban, lấy ID phòng ban họ quản lý
if (has_permission('department_manager')) {
    $dept_manager_sql = "SELECT id FROM departments WHERE manager_id = $user_id";
    $dept_result = query($dept_manager_sql);
    if (num_rows($dept_result) > 0) {
        $dept_id = fetch_array($dept_result)['id'];
        $dept_stats = true;
    }
}

// Thống kê cho tất cả người dùng (chỉ cho admin và project_manager)
if ($system_stats) {
    $user_sql = "SELECT COUNT(*) as count FROM users";
    $stats['total_users'] = fetch_array(query($user_sql))['count'];

    $dept_sql = "SELECT COUNT(*) as count FROM departments";
    $stats['total_departments'] = fetch_array(query($dept_sql))['count'];

    $project_sql = "SELECT COUNT(*) as count FROM projects";
    $stats['total_projects'] = fetch_array(query($project_sql))['count'];

    $task_sql = "SELECT COUNT(*) as count FROM tasks";
    $stats['total_tasks'] = fetch_array(query($task_sql))['count'];
    
    // Thống kê tổng số công việc cho admin và project_manager
    $assigned_tasks = $stats['total_tasks'];
    
    // Thống kê công việc chưa hoàn thành toàn hệ thống
    $pending_tasks_sql = "SELECT COUNT(*) as count FROM tasks WHERE status_id NOT IN (3, 5)";
    $pending_tasks = fetch_array(query($pending_tasks_sql))['count'];
    
    // Thống kê công việc đã hoàn thành toàn hệ thống
    $completed_tasks_sql = "SELECT COUNT(*) as count FROM tasks WHERE status_id = 3";
    $completed_tasks = fetch_array(query($completed_tasks_sql))['count'];
} elseif ($dept_stats) {
    // Thống kê cho quản lý phòng ban (tất cả công việc của phòng ban)
    
    // Đếm số dự án mà phòng ban tham gia
    $dept_projects_sql = "SELECT COUNT(DISTINCT p.id) as count 
                          FROM projects p 
                          JOIN project_departments pd ON p.id = pd.project_id 
                          WHERE pd.department_id = $dept_id";
    $stats['dept_projects'] = fetch_array(query($dept_projects_sql))['count'];
    
    // Đếm số nhân viên trong phòng ban
    $dept_users_sql = "SELECT COUNT(*) as count FROM users WHERE department_id = $dept_id";
    $stats['dept_users'] = fetch_array(query($dept_users_sql))['count'];
    
    // Lấy danh sách nhân viên trong phòng ban
    $dept_staff_sql = "SELECT id FROM users WHERE department_id = $dept_id";
    $dept_staff_result = query($dept_staff_sql);
    $staff_ids = [];
    while ($staff = fetch_array($dept_staff_result)) {
        $staff_ids[] = $staff['id'];
    }
    
    if (!empty($staff_ids)) {
        $staff_list = implode(',', $staff_ids);
        
        // Tổng số công việc của phòng ban
        $dept_tasks_sql = "SELECT COUNT(*) as count 
                           FROM tasks t
                           WHERE t.department_id = $dept_id OR t.assigned_to IN ($staff_list)";
        $assigned_tasks = fetch_array(query($dept_tasks_sql))['count'];
        
        // Công việc chưa hoàn thành của phòng ban
        $pending_tasks_sql = "SELECT COUNT(*) as count 
                              FROM tasks t
                              WHERE (t.department_id = $dept_id OR t.assigned_to IN ($staff_list))
                              AND t.status_id NOT IN (3, 5)";
        $pending_tasks = fetch_array(query($pending_tasks_sql))['count'];
        
        // Công việc đã hoàn thành của phòng ban
        $completed_tasks_sql = "SELECT COUNT(*) as count 
                                FROM tasks t
                                WHERE (t.department_id = $dept_id OR t.assigned_to IN ($staff_list))
                                AND t.status_id = 3";
        $completed_tasks = fetch_array(query($completed_tasks_sql))['count'];
    } else {
        // Nếu không có nhân viên, chỉ lấy công việc gắn với phòng ban
        $dept_tasks_sql = "SELECT COUNT(*) as count FROM tasks WHERE department_id = $dept_id";
        $assigned_tasks = fetch_array(query($dept_tasks_sql))['count'];
        
        $pending_tasks_sql = "SELECT COUNT(*) as count FROM tasks WHERE department_id = $dept_id AND status_id NOT IN (3, 5)";
        $pending_tasks = fetch_array(query($pending_tasks_sql))['count'];
        
        $completed_tasks_sql = "SELECT COUNT(*) as count FROM tasks WHERE department_id = $dept_id AND status_id = 3";
        $completed_tasks = fetch_array(query($completed_tasks_sql))['count'];
    }
} else {
    // Lấy thông tin công việc được giao cho người dùng
    $assigned_tasks_sql = "SELECT COUNT(*) as count FROM tasks WHERE assigned_to = $user_id";
    $assigned_tasks = fetch_array(query($assigned_tasks_sql))['count'];

    // Lấy thông tin công việc chưa hoàn thành
    $pending_tasks_sql = "SELECT COUNT(*) as count FROM tasks WHERE assigned_to = $user_id AND status_id NOT IN (3, 5)";
    $pending_tasks = fetch_array(query($pending_tasks_sql))['count'];

    // Lấy thông tin công việc đã hoàn thành
    $completed_tasks_sql = "SELECT COUNT(*) as count FROM tasks WHERE assigned_to = $user_id AND status_id = 3";
    $completed_tasks = fetch_array(query($completed_tasks_sql))['count'];
}

// Lấy danh sách công việc gần đây
if ($system_stats) {
    // Admin và project_manager xem tất cả công việc mới nhất trong hệ thống
    $recent_tasks_sql = "SELECT t.*, p.name as project_name, u.name as assigned_name 
                         FROM tasks t 
                         JOIN projects p ON t.project_id = p.id 
                         LEFT JOIN users u ON t.assigned_to = u.id
                         ORDER BY t.updated_at DESC 
                         LIMIT 5";
} elseif ($dept_stats) {
    // Department_manager xem công việc của phòng ban họ quản lý
    $staff_condition = "";
    if (!empty($staff_ids)) {
        $staff_list = implode(',', $staff_ids);
        $staff_condition = " OR t.assigned_to IN ($staff_list)";
    }
    
    $recent_tasks_sql = "SELECT t.*, p.name as project_name, u.name as assigned_name 
                         FROM tasks t 
                         JOIN projects p ON t.project_id = p.id 
                         LEFT JOIN users u ON t.assigned_to = u.id
                         WHERE t.department_id = $dept_id $staff_condition
                         ORDER BY t.updated_at DESC 
                         LIMIT 5";
} else {
    // Staff chỉ xem công việc của họ
    $recent_tasks_sql = "SELECT t.*, p.name as project_name 
                         FROM tasks t 
                         JOIN projects p ON t.project_id = p.id 
                         WHERE t.assigned_to = $user_id 
                         ORDER BY t.updated_at DESC 
                         LIMIT 5";
}

$recent_tasks_result = query($recent_tasks_sql);
while ($task = fetch_array($recent_tasks_result)) {
    $recent_tasks[] = $task;
}

// Lấy danh sách dự án theo vai trò
if ($system_stats) {
    // Admin và project_manager có thể xem tất cả dự án
    $projects_sql = "SELECT p.*, 
                          (SELECT COUNT(*) FROM tasks WHERE project_id = p.id) as task_count,
                          (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND status_id = 3) as completed_tasks 
                   FROM projects p 
                   ORDER BY p.end_date ASC
                   LIMIT 10";
} elseif ($user_role == 'project_manager') {
    // Dự án do người dùng quản lý
    $projects_sql = "SELECT p.*, 
                            (SELECT COUNT(*) FROM tasks WHERE project_id = p.id) as task_count,
                            (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND status_id = 3) as completed_tasks 
                     FROM projects p 
                     WHERE p.manager_id = $user_id 
                     ORDER BY p.end_date ASC";
} elseif ($user_role == 'department_manager') {
    // Dự án của phòng ban do người dùng quản lý
    $dept_id_sql = "SELECT id FROM departments WHERE manager_id = $user_id";
    $dept_result = query($dept_id_sql);
    if (num_rows($dept_result) > 0) {
        $dept_id = fetch_array($dept_result)['id'];
        
        $projects_sql = "SELECT p.*, 
                                (SELECT COUNT(*) FROM tasks WHERE project_id = p.id) as task_count,
                                (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND status_id = 3) as completed_tasks 
                         FROM projects p 
                         JOIN project_departments pd ON p.id = pd.project_id 
                         WHERE pd.department_id = $dept_id 
                         ORDER BY p.end_date ASC";
    }
} else {
    // Dự án có công việc được giao cho người dùng
    $projects_sql = "SELECT p.*, 
                            (SELECT COUNT(*) FROM tasks WHERE project_id = p.id) as task_count,
                            (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND status_id = 3) as completed_tasks 
                     FROM projects p 
                     JOIN tasks t ON p.id = t.project_id 
                     WHERE t.assigned_to = $user_id 
                     GROUP BY p.id 
                     ORDER BY p.end_date ASC";
}

// Lấy dự án
if (isset($projects_sql)) {
    $projects_result = query($projects_sql);
    while ($project = fetch_array($projects_result)) {
        // Tính tỷ lệ hoàn thành
        $progress = 0;
        if ($project['task_count'] > 0) {
            $progress = round(($project['completed_tasks'] / $project['task_count']) * 100);
        }
        $project['progress'] = $progress;
        
        $my_projects[] = $project;
    }
}

// Lấy thông báo gần đây
$notifications_sql = "SELECT * FROM notifications 
                     WHERE user_id = $user_id 
                     ORDER BY created_at DESC 
                     LIMIT 5";
$notifications_result = query($notifications_sql);
$recent_notifications = [];
while ($notification = fetch_array($notifications_result)) {
    $recent_notifications[] = $notification;
}

// Dữ liệu cho biểu đồ
$task_status_data = [];
if ($system_stats) {
    // Dữ liệu biểu đồ cho admin và project_manager (toàn bộ hệ thống)
    $task_status_sql = "SELECT status_id, COUNT(*) as count 
                        FROM tasks 
                        GROUP BY status_id";
} elseif ($dept_stats) {
    // Dữ liệu biểu đồ cho department_manager (phòng ban họ quản lý)
    $staff_condition = "";
    if (!empty($staff_ids)) {
        $staff_list = implode(',', $staff_ids);
        $staff_condition = " OR t.assigned_to IN ($staff_list)";
    }
    
    $task_status_sql = "SELECT t.status_id, COUNT(*) as count 
                        FROM tasks t
                        WHERE t.department_id = $dept_id $staff_condition
                        GROUP BY t.status_id";
} else {
    // Dữ liệu biểu đồ cho staff (chỉ công việc của họ)
    $task_status_sql = "SELECT status_id, COUNT(*) as count 
                        FROM tasks 
                        WHERE assigned_to = $user_id 
                        GROUP BY status_id";
}
$task_status_result = query($task_status_sql);
while ($status = fetch_array($task_status_result)) {
    $task_status_data[$status['status_id']] = $status['count'];
}

// Chuẩn bị dữ liệu JavaScript cho biểu đồ
$chart_labels = [];
$chart_data = [];
$chart_colors = [];

for ($i = 1; $i <= 5; $i++) {
    $chart_labels[] = get_task_status($i);
    $chart_data[] = $task_status_data[$i] ?? 0;
    $chart_colors[] = 'rgba(' . ($i == 1 ? '108, 117, 125' : ($i == 2 ? '13, 110, 253' : ($i == 3 ? '25, 135, 84' : ($i == 4 ? '255, 193, 7' : '220, 53, 69')))) . ', 0.8)';
}
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <h2 class="mb-4">
                <i class="fas fa-tachometer-alt me-2"></i> Tổng quan
            </h2>
        </div>
    </div>
    
    <!-- Thống kê cơ bản -->
    <div class="row mb-4">
        <?php if ($system_stats): ?>
        <!-- Thống kê toàn hệ thống cho admin và project_manager -->
        <div class="col-md-3 mb-3">
            <div class="card stats-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stats-number"><?php echo $stats['total_users']; ?></div>
                            <div class="stats-text">Người dùng</div>
                        </div>
                        <div class="stats-icon">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card stats-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stats-number"><?php echo $stats['total_departments']; ?></div>
                            <div class="stats-text">Phòng ban</div>
                        </div>
                        <div class="stats-icon">
                            <i class="fas fa-building"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card stats-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stats-number"><?php echo $stats['total_projects']; ?></div>
                            <div class="stats-text">Dự án</div>
                        </div>
                        <div class="stats-icon">
                            <i class="fas fa-project-diagram"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card stats-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stats-number"><?php echo $stats['total_tasks']; ?></div>
                            <div class="stats-text">Công việc</div>
                        </div>
                        <div class="stats-icon">
                            <i class="fas fa-tasks"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php elseif ($dept_stats): ?>
        <!-- Thống kê phòng ban cho department_manager -->
        <div class="col-md-3 mb-3">
            <div class="card stats-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stats-number"><?php echo $stats['dept_users']; ?></div>
                            <div class="stats-text">Nhân viên</div>
                        </div>
                        <div class="stats-icon">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card stats-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stats-number"><?php echo $stats['dept_projects']; ?></div>
                            <div class="stats-text">Dự án</div>
                        </div>
                        <div class="stats-icon">
                            <i class="fas fa-project-diagram"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card stats-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stats-number"><?php echo $pending_tasks; ?></div>
                            <div class="stats-text">Chưa hoàn thành</div>
                        </div>
                        <div class="stats-icon">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card stats-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stats-number"><?php echo $completed_tasks; ?></div>
                            <div class="stats-text">Đã hoàn thành</div>
                        </div>
                        <div class="stats-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- Thống kê cá nhân cho staff -->
        <div class="col-md-3 mb-3">
            <div class="card stats-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stats-number"><?php echo $assigned_tasks; ?></div>
                            <div class="stats-text">Tổng công việc</div>
                        </div>
                        <div class="stats-icon">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card stats-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stats-number"><?php echo $pending_tasks; ?></div>
                            <div class="stats-text">Chưa hoàn thành</div>
                        </div>
                        <div class="stats-icon">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card stats-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stats-number"><?php echo $completed_tasks; ?></div>
                            <div class="stats-text">Đã hoàn thành</div>
                        </div>
                        <div class="stats-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card stats-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stats-number"><?php echo count($my_projects); ?></div>
                            <div class="stats-text">Dự án</div>
                        </div>
                        <div class="stats-icon">
                            <i class="fas fa-project-diagram"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Nội dung chính -->
    <div class="row">
        <!-- Cột trái: Biểu đồ và dự án -->
        <div class="col-lg-8">
            <!-- Biểu đồ -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <?php if ($system_stats): ?>
                            Thống kê công việc toàn hệ thống
                        <?php elseif ($dept_stats): ?>
                            Thống kê công việc phòng ban
                        <?php else: ?>
                            Thống kê công việc cá nhân
                        <?php endif; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div style="height: 300px;">
                        <canvas id="taskStatusChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Dự án -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <?php if ($system_stats): ?>
                            Dự án trong hệ thống
                        <?php elseif ($dept_stats): ?>
                            Dự án của phòng ban
                        <?php else: ?>
                            Dự án của tôi
                        <?php endif; ?>
                    </h5>
                    <a href="<?php echo BASE_URL; ?>modules/projects/" class="btn btn-sm btn-primary">
                        <i class="fas fa-external-link-alt"></i>
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($my_projects)): ?>
                    <p class="text-center text-muted my-4">Bạn chưa tham gia dự án nào</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Tên dự án</th>
                                    <th>Ngày kết thúc</th>
                                    <th>Tiến độ</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($my_projects as $project): ?>
                                <tr>
                                    <td>
                                        <?php echo $project['name']; ?>
                                    </td>
                                    <td>
                                        <?php echo format_date($project['end_date']); ?>
                                    </td>
                                    <td style="width: 30%;">
                                        <div class="progress">
                                            <div class="progress-bar" role="progressbar" 
                                                 style="width: <?php echo $project['progress']; ?>%;" 
                                                 aria-valuenow="<?php echo $project['progress']; ?>" 
                                                 aria-valuemin="0" aria-valuemax="100">
                                                <?php echo $project['progress']; ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?php echo BASE_URL; ?>modules/projects/view.php?id=<?php echo $project['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Cột phải: Công việc gần đây và thông báo -->
        <div class="col-lg-4">
            <!-- Công việc gần đây -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <?php if ($system_stats): ?>
                            Công việc mới nhất trong hệ thống
                        <?php elseif ($dept_stats): ?>
                            Công việc mới nhất của phòng ban
                        <?php else: ?>
                            Công việc gần đây của tôi
                        <?php endif; ?>
                    </h5>
                    <a href="<?php echo BASE_URL; ?>modules/tasks/" class="btn btn-sm btn-primary">
                        <i class="fas fa-external-link-alt"></i>
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_tasks)): ?>
                    <p class="text-center text-muted my-4">Không có công việc gần đây</p>
                    <?php else: ?>
                    <div class="timeline">
                        <?php foreach($recent_tasks as $task): ?>
                        <div class="timeline-item">
                            <div class="timeline-date"><?php echo format_datetime($task['updated_at']); ?></div>
                            <div class="timeline-content">
                                <div class="d-flex justify-content-between">
                                    <h6 class="mb-1"><?php echo $task['title']; ?></h6>
                                    <span class="badge bg-<?php echo get_task_status_color($task['status_id']); ?>">
                                        <?php echo get_task_status($task['status_id']); ?>
                                    </span>
                                </div>
                                <p class="small text-muted mb-1">
                                    <i class="fas fa-project-diagram me-1"></i> <?php echo $task['project_name']; ?>
                                    <?php if (($system_stats || $dept_stats) && isset($task['assigned_name'])): ?>
                                    <br><i class="fas fa-user me-1"></i> <?php echo $task['assigned_name'] ?: 'Chưa giao'; ?>
                                    <?php endif; ?>
                                </p>
                                <div class="d-flex justify-content-end">
                                    <a href="<?php echo BASE_URL; ?>modules/tasks/view.php?id=<?php echo $task['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i> Xem
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Thông báo gần đây -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Thông báo gần đây</h5>
                    <a href="<?php echo BASE_URL; ?>modules/notifications/" class="btn btn-sm btn-primary">
                        <i class="fas fa-external-link-alt"></i>
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_notifications)): ?>
                    <p class="text-center text-muted my-4">Không có thông báo mới</p>
                    <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach($recent_notifications as $notification): ?>
                        <a href="<?php echo BASE_URL; ?>modules/notifications/mark_read.php?id=<?php echo $notification['id']; ?>&redirect=<?php echo urlencode($notification['link']); ?>" 
                           class="list-group-item list-group-item-action <?php echo $notification['is_read'] ? '' : 'fw-bold'; ?>">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><?php echo $notification['message']; ?></h6>
                                <small><?php echo format_datetime($notification['created_at']); ?></small>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Script cho biểu đồ -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Dữ liệu biểu đồ
    var taskStatusLabels = <?php echo json_encode($chart_labels); ?>;
    var taskStatusData = <?php echo json_encode($chart_data); ?>;
    var taskStatusColors = <?php echo json_encode($chart_colors); ?>;
</script>

<?php
// Include footer
include_once 'templates/footer.php';
?>