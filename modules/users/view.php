<?php
// Include file cấu hình và functions
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Kiểm tra đăng nhập
if (!is_logged_in()) {
    redirect('index.php');
}

// Kiểm tra tham số ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    set_flash_message('ID người dùng không hợp lệ', 'danger');
    redirect('modules/users/');
}

$user_id = (int)$_GET['id'];

// Lấy thông tin người dùng
$sql = "SELECT u.*, d.name as department_name 
        FROM users u 
        LEFT JOIN departments d ON u.department_id = d.id 
        WHERE u.id = $user_id";
$result = query($sql);

if (num_rows($result) == 0) {
    set_flash_message('Không tìm thấy người dùng', 'danger');
    redirect('modules/users/');
}

$user = fetch_array($result);

// Kiểm tra quyền truy cập: admin và quản lý dự án có thể xem mọi người dùng, người dùng khác chỉ xem thông tin của chính mình
if (!has_permission('admin') && !has_permission('project_manager') && $_SESSION['user_id'] != $user_id) {
    set_flash_message('Bạn không có quyền xem thông tin người dùng này', 'danger');
    redirect('dashboard.php');
}

// Lấy danh sách công việc được giao cho người dùng này
$tasks_sql = "SELECT t.*, p.name as project_name 
              FROM tasks t 
              JOIN projects p ON t.project_id = p.id 
              WHERE t.assigned_to = $user_id 
              ORDER BY t.due_date ASC";
$tasks_result = query($tasks_sql);
$tasks = [];
while ($task = fetch_array($tasks_result)) {
    $tasks[] = $task;
}

// Lấy danh sách dự án mà người dùng quản lý (nếu là quản lý dự án)
$managed_projects = [];
if ($user['role'] == 'project_manager') {
    $projects_sql = "SELECT p.*, 
                          (SELECT COUNT(*) FROM tasks WHERE project_id = p.id) as total_tasks,
                          (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND status_id = 3) as completed_tasks
                    FROM projects p 
                    WHERE p.manager_id = $user_id 
                    ORDER BY p.end_date ASC";
    $projects_result = query($projects_sql);
    while ($project = fetch_array($projects_result)) {
        // Tính tỷ lệ hoàn thành
        $progress = 0;
        if ($project['total_tasks'] > 0) {
            $progress = round(($project['completed_tasks'] / $project['total_tasks']) * 100);
        }
        $project['progress'] = $progress;
        $managed_projects[] = $project;
    }
}

// Lấy phòng ban mà người dùng quản lý (nếu là quản lý phòng ban)
$managed_department = null;
if ($user['role'] == 'department_manager') {
    $department_sql = "SELECT * FROM departments WHERE manager_id = $user_id";
    $department_result = query($department_sql);
    if (num_rows($department_result) > 0) {
        $managed_department = fetch_array($department_result);
        
        // Lấy danh sách nhân viên trong phòng ban
        $dept_id = $managed_department['id'];
        $staff_sql = "SELECT id, name, email, phone, avatar FROM users WHERE department_id = $dept_id AND id != $user_id";
        $staff_result = query($staff_sql);
        $department_staff = [];
        while ($staff = fetch_array($staff_result)) {
            $department_staff[] = $staff;
        }
        $managed_department['staff'] = $department_staff;
    }
}

// Include header
include_once '../../templates/header.php';
?>

<div class="container-fluid py-4">
    <!-- Breadcrumb -->
    <div class="row mb-4">
        <div class="col-md-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../../dashboard.php">Trang chủ</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Quản lý người dùng</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Thông tin người dùng</li>
                </ol>
            </nav>
        </div>
    </div>
    
    <!-- Thông tin người dùng -->
    <div class="row">
        <div class="col-md-4">
            <!-- Thông tin cơ bản -->
            <div class="card mb-4">
                <div class="card-body text-center pt-4">
                    <img src="<?php echo BASE_URL . $user['avatar']; ?>" alt="Avatar" class="rounded-circle img-fluid" style="width: 150px; height: 150px; object-fit: cover;">
                    <h5 class="my-3"><?php echo $user['name']; ?></h5>
                    
                    <?php
                    $role_badge_class = 'secondary';
                    $role_text = 'Không xác định';
                    
                    switch ($user['role']) {
                        case 'admin':
                            $role_badge_class = 'danger';
                            $role_text = 'Admin';
                            break;
                        case 'project_manager':
                            $role_badge_class = 'primary';
                            $role_text = 'Quản lý dự án';
                            break;
                        case 'department_manager':
                            $role_badge_class = 'info';
                            $role_text = 'Quản lý phòng ban';
                            break;
                        case 'staff':
                            $role_badge_class = 'success';
                            $role_text = 'Nhân viên';
                            break;
                    }
                    ?>
                    <span class="badge bg-<?php echo $role_badge_class; ?> mb-3"><?php echo $role_text; ?></span>
                    
                    <p class="text-muted mb-1"><?php echo $user['department_name'] ?? 'Chưa phân công phòng ban'; ?></p>
                    
                    <div class="d-flex justify-content-center mb-2 mt-3">
                        <?php if (has_permission('admin') || $_SESSION['user_id'] == $user_id): ?>
                        <a href="edit.php?id=<?php echo $user['id']; ?>" class="btn btn-primary me-2">
                            <i class="fas fa-edit me-1"></i> Chỉnh sửa
                        </a>
                        <?php endif; ?>
                        
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Quay lại
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Thông tin liên hệ -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-address-card me-2"></i> Thông tin liên hệ</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-sm-4">
                            <p class="mb-0">Email</p>
                        </div>
                        <div class="col-sm-8">
                            <p class="text-muted mb-0"><?php echo $user['email']; ?></p>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-sm-4">
                            <p class="mb-0">Phone</p>
                        </div>
                        <div class="col-sm-8">
                            <p class="text-muted mb-0"><?php echo $user['phone'] ?: 'Chưa cập nhật'; ?></p>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-sm-4">
                            <p class="mb-0">Phòng ban</p>
                        </div>
                        <div class="col-sm-8">
                            <p class="text-muted mb-0"><?php echo $user['department_name'] ?: 'Chưa phân công'; ?></p>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-sm-4">
                            <p class="mb-0">Đăng nhập</p>
                        </div>
                        <div class="col-sm-8">
                            <p class="text-muted mb-0"><?php echo $user['last_login'] ? format_datetime($user['last_login']) : 'Chưa đăng nhập'; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <!-- Thống kê công việc -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i> Thống kê công việc</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php
                        // Tính toán thống kê công việc
                        $total_tasks = count($tasks);
                        $pending_tasks = 0;
                        $completed_tasks = 0;
                        $overdue_tasks = 0;
                        
                        foreach ($tasks as $task) {
                            if ($task['status_id'] == 3) {
                                $completed_tasks++;
                            } else {
                                $pending_tasks++;
                                
                                // Kiểm tra công việc quá hạn
                                $due_date = strtotime($task['due_date']);
                                $today = strtotime(date('Y-m-d'));
                                if ($due_date < $today && $task['status_id'] != 3) {
                                    $overdue_tasks++;
                                }
                            }
                        }
                        
                        // Tỷ lệ hoàn thành
                        $completion_rate = $total_tasks > 0 ? round(($completed_tasks / $total_tasks) * 100) : 0;
                        ?>
                        
                        <div class="col-md-3 mb-3 mb-md-0">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body text-center">
                                    <h3 class="fw-bold text-primary"><?php echo $total_tasks; ?></h3>
                                    <p class="mb-0">Tổng công việc</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3 mb-md-0">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body text-center">
                                    <h3 class="fw-bold text-success"><?php echo $completed_tasks; ?></h3>
                                    <p class="mb-0">Đã hoàn thành</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3 mb-md-0">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body text-center">
                                    <h3 class="fw-bold text-info"><?php echo $pending_tasks; ?></h3>
                                    <p class="mb-0">Đang thực hiện</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body text-center">
                                    <h3 class="fw-bold text-danger"><?php echo $overdue_tasks; ?></h3>
                                    <p class="mb-0">Quá hạn</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <h6 class="mb-3">Tỷ lệ hoàn thành công việc</h6>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar" role="progressbar" style="width: <?php echo $completion_rate; ?>%;" aria-valuenow="<?php echo $completion_rate; ?>" aria-valuemin="0" aria-valuemax="100">
                                    <?php echo $completion_rate; ?>%
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Danh sách công việc -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-tasks me-2"></i> Danh sách công việc</h5>
                    <a href="<?php echo BASE_URL; ?>modules/tasks/?user_id=<?php echo $user_id; ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-external-link-alt"></i>
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($tasks)): ?>
                    <p class="text-center text-muted my-4">Người dùng này chưa được giao công việc nào</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Tên công việc</th>
                                    <th>Dự án</th>
                                    <th>Hạn hoàn thành</th>
                                    <th>Trạng thái</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($tasks, 0, 5) as $task): ?>
                                <tr>
                                    <td><?php echo $task['title']; ?></td>
                                    <td><?php echo $task['project_name']; ?></td>
                                    <td><?php echo format_date($task['due_date']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo get_task_status_color($task['status_id']); ?>">
                                            <?php echo get_task_status($task['status_id']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?php echo BASE_URL; ?>modules/tasks/view.php?id=<?php echo $task['id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if (count($tasks) > 5): ?>
                    <div class="text-center mt-3">
                        <a href="<?php echo BASE_URL; ?>modules/tasks/?user_id=<?php echo $user_id; ?>" class="btn btn-sm btn-outline-primary">
                            Xem tất cả (<?php echo count($tasks); ?>) công việc
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($managed_projects)): ?>
            <!-- Dự án quản lý (nếu là quản lý dự án) -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-project-diagram me-2"></i> Dự án đang quản lý</h5>
                </div>
                <div class="card-body">
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
                                <?php foreach ($managed_projects as $project): ?>
                                <tr>
                                    <td><?php echo $project['name']; ?></td>
                                    <td><?php echo format_date($project['end_date']); ?></td>
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
                                    <td>
                                        <a href="<?php echo BASE_URL; ?>modules/projects/view.php?id=<?php echo $project['id']; ?>" 
                                           class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($managed_department): ?>
            <!-- Phòng ban quản lý (nếu là quản lý phòng ban) -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-building me-2"></i> Phòng ban đang quản lý</h5>
                </div>
                <div class="card-body">
                    <h6><?php echo $managed_department['name']; ?></h6>
                    <p class="text-muted"><?php echo $managed_department['description']; ?></p>
                    
                    <hr>
                    
                    <h6 class="mb-3">Nhân viên trong phòng ban (<?php echo count($managed_department['staff']); ?>)</h6>
                    
                    <?php if (empty($managed_department['staff'])): ?>
                    <p class="text-muted">Không có nhân viên nào trong phòng ban</p>
                    <?php else: ?>
                    <div class="row">
                        <?php foreach ($managed_department['staff'] as $staff): ?>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex align-items-center justify-content-evenly">
                                <img src="<?php echo BASE_URL . $staff['avatar']; ?>" alt="Avatar" class="rounded-circle me-3" style="width: 40px; height: 40px; object-fit: cover;">
                                <div>
                                    <h6 class="mb-0"><?php echo $staff['name']; ?></h6>
                                    <small class="text-muted"><?php echo $staff['email']; ?></small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../../templates/footer.php';
?> 