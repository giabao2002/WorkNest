<?php
/**
 * Xem chi tiết dự án
 */

// Include config
require_once '../../config/config.php';

// Kiểm tra đăng nhập
if (!is_logged_in()) {
    redirect('index.php');
}

// Kiểm tra tham số ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    set_flash_message('ID dự án không hợp lệ', 'danger');
    redirect('modules/projects/index.php');
}

// Hàm lấy tất cả kết quả dưới dạng mảng
function fetch_all_array($result) {
    $rows = [];
    while ($row = fetch_array($result)) {
        $rows[] = $row;
    }
    return $rows;
}

$project_id = (int)$_GET['id'];

// Lấy thông tin dự án
$project_sql = "SELECT p.*, u.name as manager_name, u.email as manager_email, u.avatar as manager_avatar 
                FROM projects p
                LEFT JOIN users u ON p.manager_id = u.id
                WHERE p.id = $project_id";
$project_result = query($project_sql);

if (num_rows($project_result) === 0) {
    set_flash_message('Không tìm thấy dự án', 'danger');
    redirect('modules/projects/index.php');
}

$project = fetch_array($project_result);

// Kiểm tra quyền truy cập nếu không phải admin hoặc project_manager
if (!has_permission('admin') && !has_permission('project_manager')) {
    $user_id = $_SESSION['user_id'];
    $access_allowed = false;
    
    // Nếu là người quản lý dự án này
    if ($project['manager_id'] == $user_id) {
        $access_allowed = true;
    } else {
        // Nếu là quản lý phòng ban tham gia dự án
        if (has_permission('department_manager')) {
            $dept_check = query("SELECT d.id FROM departments d 
                                JOIN project_departments pd ON d.id = pd.department_id 
                                WHERE pd.project_id = $project_id AND d.manager_id = $user_id");
            if (num_rows($dept_check) > 0) {
                $access_allowed = true;
            }
        }
        
        // Nếu là nhân viên được giao nhiệm vụ trong dự án
        if (!$access_allowed) {
            $task_check = query("SELECT id FROM tasks 
                               WHERE project_id = $project_id AND (assigned_to = $user_id OR assigned_by = $user_id)");
            if (num_rows($task_check) > 0) {
                $access_allowed = true;
            }
        }
    }
    
    if (!$access_allowed) {
        set_flash_message('Bạn không có quyền xem dự án này', 'danger');
        redirect('dashboard.php');
    }
}

// Lấy phòng ban tham gia dự án
$dept_sql = "SELECT d.*, u.name as manager_name, u.id as manager_id 
            FROM departments d
            JOIN project_departments pd ON d.id = pd.department_id
            LEFT JOIN users u ON d.manager_id = u.id
            WHERE pd.project_id = $project_id";
$dept_result = query($dept_sql);
$departments = fetch_all_array($dept_result);

// Lấy danh sách công việc
$task_sql = "SELECT t.*, u.name as assigned_name, u.avatar as assigned_avatar,
            d.name as department_name
            FROM tasks t
            LEFT JOIN users u ON t.assigned_to = u.id
            LEFT JOIN departments d ON t.department_id = d.id
            WHERE t.project_id = $project_id AND t.parent_id IS NULL
            ORDER BY t.status_id ASC, t.priority DESC, t.due_date ASC";
$task_result = query($task_sql);

// Lấy thống kê dự án
$stats_sql = "SELECT 
            COUNT(*) as total_tasks,
            SUM(CASE WHEN status_id = 1 THEN 1 ELSE 0 END) as pending_tasks,
            SUM(CASE WHEN status_id = 2 THEN 1 ELSE 0 END) as progress_tasks,
            SUM(CASE WHEN status_id = 3 THEN 1 ELSE 0 END) as completed_tasks,
            SUM(CASE WHEN status_id = 4 THEN 1 ELSE 0 END) as paused_tasks,
            SUM(CASE WHEN (due_date < CURDATE() AND status_id != 3) THEN 1 ELSE 0 END) as overdue_tasks
            FROM tasks
            WHERE project_id = $project_id";
$stats_result = query($stats_sql);
$stats = fetch_array($stats_result);

// Tính phần trăm hoàn thành
$project_progress = 0;
if ($stats['total_tasks'] > 0) {
    $project_progress = round(($stats['completed_tasks'] / $stats['total_tasks']) * 100);
}

// Danh sách trạng thái dự án
$status_list = [
    1 => ['name' => 'Chuẩn bị', 'color' => 'primary'],
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

// Tiêu đề trang
$page_title = "Chi tiết dự án: " . $project['name'];

// Include header
include_once '../../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <?php echo $project['name']; ?>
            <span class="badge badge-<?php echo $status_list[$project['status_id']]['color']; ?> ml-2">
                <?php echo $status_list[$project['status_id']]['name']; ?>
            </span>
        </h1>
        <div>
            <?php if (has_permission('admin') || has_permission('project_manager') || 
                      ($project['manager_id'] == $_SESSION['user_id'])): ?>
            <a href="edit.php?id=<?php echo $project_id; ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-edit fa-sm text-white-50"></i> Chỉnh sửa
            </a>
            <?php endif; ?>
            <a href="index.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left fa-sm text-white-50"></i> Quay lại
            </a>
        </div>
    </div>

    <!-- Thống kê dự án -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Tổng công việc</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_tasks']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Đã hoàn thành</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['completed_tasks']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Đang thực hiện</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['progress_tasks']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-spinner fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Quá hạn</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['overdue_tasks']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Thông tin dự án và tiến độ -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Thông tin dự án</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Ngày bắt đầu:</strong> <?php echo format_date($project['start_date']); ?></p>
                            <p><strong>Ngày kết thúc:</strong> 
                                <span class="<?php echo (strtotime($project['end_date']) < strtotime('today') && $project['status_id'] != 3) ? 'text-danger font-weight-bold' : ''; ?>">
                                    <?php echo format_date($project['end_date']); ?>
                                </span>
                            </p>
                            <p>
                                <strong>Mức ưu tiên:</strong> 
                                <span class="badge badge-<?php echo $priority_list[$project['priority']]['color']; ?>">
                                    <?php echo $priority_list[$project['priority']]['name']; ?>
                                </span>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Người quản lý:</strong> 
                                <a href="../users/view.php?id=<?php echo $project['manager_id']; ?>">
                                    <?php echo $project['manager_name']; ?>
                                </a>
                            </p>
                            <p><strong>Email:</strong> <?php echo $project['manager_email']; ?></p>
                            <p><strong>Ngày tạo:</strong> <?php echo format_datetime($project['created_at']); ?></p>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <h6 class="font-weight-bold">Mô tả dự án</h6>
                        <p class="text-justify"><?php echo nl2br($project['description']) ?: 'Không có mô tả'; ?></p>
                    </div>
                    
                    <div class="mt-4">
                        <h6 class="font-weight-bold">Tiến độ hoàn thành</h6>
                        <div class="progress mb-4" style="height: 25px;">
                            <div class="progress-bar bg-<?php echo $status_list[$project['status_id']]['color']; ?>" role="progressbar" style="width: <?php echo $project_progress; ?>%"
                                aria-valuenow="<?php echo $project_progress; ?>" aria-valuemin="0" aria-valuemax="100">
                                <?php echo $project_progress; ?>%
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Phòng ban tham gia</h6>
                </div>
                <div class="card-body">
                    <?php if (count($departments) > 0): ?>
                        <ul class="list-group">
                            <?php foreach ($departments as $dept): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <a href="../departments/view.php?id=<?php echo $dept['id']; ?>">
                                            <?php echo $dept['name']; ?>
                                        </a>
                                        <?php if ($dept['manager_name']): ?>
                                            <div class="small text-muted">
                                                Trưởng phòng: 
                                                <a href="../users/view.php?id=<?php echo $dept['manager_id']; ?>">
                                                    <?php echo $dept['manager_name']; ?>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php
                                    // Đếm công việc của phòng ban trong dự án
                                    $dept_task_sql = "SELECT 
                                                    COUNT(*) as total,
                                                    SUM(CASE WHEN status_id = 3 THEN 1 ELSE 0 END) as completed
                                                    FROM tasks 
                                                    WHERE project_id = $project_id AND department_id = {$dept['id']}";
                                    $dept_task_result = query($dept_task_sql);
                                    $dept_task_stats = fetch_array($dept_task_result);
                                    
                                    if ($dept_task_stats['total'] > 0):
                                        $dept_progress = round(($dept_task_stats['completed'] / $dept_task_stats['total']) * 100);
                                    ?>
                                    <span class="badge badge-primary badge-pill" data-toggle="tooltip" title="<?php echo $dept_progress; ?>% hoàn thành">
                                        <?php echo $dept_task_stats['completed']; ?>/<?php echo $dept_task_stats['total']; ?>
                                    </span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-center text-muted">Chưa có phòng ban nào tham gia dự án</p>
                    <?php endif; ?>
                    
                    <?php if (has_permission('admin') || has_permission('project_manager') || 
                             ($project['manager_id'] == $_SESSION['user_id'])): ?>
                    <div class="mt-3 text-center">
                        <a href="edit.php?id=<?php echo $project_id; ?>#departments" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-edit"></i> Quản lý phòng ban
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php
            // Kiểm tra quyền thêm công việc
            $can_add_task = has_permission('admin') || has_permission('project_manager') || 
                            ($project['manager_id'] == $_SESSION['user_id']);
            
            // Nếu là quản lý phòng ban tham gia dự án
            if (!$can_add_task && has_permission('department_manager')) {
                $user_id = $_SESSION['user_id'];
                foreach ($departments as $dept) {
                    if ($dept['manager_id'] == $user_id) {
                        $can_add_task = true;
                        break;
                    }
                }
            }
            
            if ($can_add_task):
            ?>
            <!-- Nút thêm công việc mới -->
            <div class="card shadow mb-4">
                <div class="card-body">
                    <a href="../tasks/add.php?project_id=<?php echo $project_id; ?>" class="btn btn-success btn-block">
                        <i class="fas fa-plus-circle"></i> Thêm công việc mới
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Danh sách công việc -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Danh sách công việc</h6>
            
            <?php if ($stats['total_tasks'] > 0): ?>
            <div>
                <a href="../tasks/index.php?project_id=<?php echo $project_id; ?>" class="btn btn-sm btn-primary">
                    <i class="fas fa-list"></i> Xem tất cả công việc
                </a>
            </div>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (num_rows($task_result) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="taskTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th width="5%">ID</th>
                                <th width="30%">Tên công việc</th>
                                <th width="15%">Phòng ban</th>
                                <th width="15%">Người thực hiện</th>
                                <th width="10%">Hạn hoàn thành</th>
                                <th width="10%">Trạng thái</th>
                                <th width="10%">Tiến độ</th>
                                <th width="5%">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($task = fetch_array($task_result)): ?>
                                <?php
                                // Xác định màu cho trạng thái công việc
                                $task_status_colors = [
                                    1 => 'primary',     // Chưa bắt đầu
                                    2 => 'warning',     // Đang thực hiện
                                    3 => 'success',     // Hoàn thành
                                    4 => 'danger',      // Tạm dừng
                                    5 => 'secondary'    // Đã hủy
                                ];
                                $task_status_names = [
                                    1 => 'Chưa bắt đầu',
                                    2 => 'Đang thực hiện',
                                    3 => 'Hoàn thành',
                                    4 => 'Tạm dừng',
                                    5 => 'Đã hủy'
                                ];
                                $status_color = $task_status_colors[$task['status_id']] ?? 'secondary';
                                
                                // Xác định class cho thời hạn
                                $now = strtotime(date('Y-m-d'));
                                $task_due_date = strtotime($task['due_date']);
                                $date_class = '';
                                
                                if ($task['status_id'] != 3 && $task['status_id'] != 5) { // Nếu không phải đã hoàn thành hoặc đã hủy
                                    if ($task_due_date < $now) {
                                        $date_class = 'text-danger font-weight-bold'; // Quá hạn
                                    } elseif ($task_due_date < strtotime('+3 days', $now)) {
                                        $date_class = 'text-warning font-weight-bold'; // Sắp đến hạn (3 ngày)
                                    }
                                }
                                ?>
                                <tr>
                                    <td><?php echo $task['id']; ?></td>
                                    <td><?php echo $task['title']; ?></td>
                                    <td><?php echo $task['department_name'] ?: 'Chưa phân công'; ?></td>
                                    <td>
                                        <?php if ($task['assigned_to']): ?>
                                            <div class="d-flex align-items-center">
                                                <img class="rounded-circle mr-2" width="30" height="30" 
                                                     src="<?php echo BASE_URL . $task['assigned_avatar']; ?>" alt="">
                                                <?php echo $task['assigned_name']; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">Chưa phân công</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="<?php echo $date_class; ?>">
                                        <?php echo format_date($task['due_date']); ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $status_color; ?>">
                                            <?php echo $task_status_names[$task['status_id']]; ?>
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
                                        <a href="../tasks/view.php?id=<?php echo $task['id']; ?>" class="btn btn-info btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    Chưa có công việc nào trong dự án này.
                    <?php if ($can_add_task): ?>
                    <a href="../tasks/add.php?project_id=<?php echo $project_id; ?>" class="alert-link">Thêm công việc mới</a>.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../../templates/footer.php';
?> 