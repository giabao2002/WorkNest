<?php
/**
 * Báo cáo công việc
 */

// Include config
require_once '../../config/config.php';

// Kiểm tra đăng nhập
if (!is_logged_in()) {
    redirect('index.php');
}

// Kiểm tra quyền truy cập báo cáo
if (!has_permission('admin') && !has_permission('project_manager') && !has_permission('department_manager')) {
    set_flash_message('Bạn không có quyền xem báo cáo', 'danger');
    redirect('dashboard.php');
}

// Lấy thời gian báo cáo
$period = isset($_GET['period']) ? $_GET['period'] : 'all';
$where_condition = '';

switch ($period) {
    case 'week':
        $where_condition = " AND t.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)";
        break;
    case 'month':
        $where_condition = " AND t.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
        break;
    case 'quarter':
        $where_condition = " AND t.created_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
        break;
    case 'year':
        $where_condition = " AND t.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
        break;
    default:
        $where_condition = "";
        break;
}

// Lọc theo dự án
$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
if ($project_id > 0) {
    $where_condition .= " AND t.project_id = $project_id";
}

// Lọc theo phòng ban
$department_id = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
if ($department_id > 0) {
    $where_condition .= " AND t.department_id = $department_id";
}

// Lấy danh sách dự án để lọc
$projects_sql = "SELECT id, name FROM projects ORDER BY name";
$projects_result = query($projects_sql);
$projects = [];
while ($project = fetch_array($projects_result)) {
    $projects[$project['id']] = $project['name'];
}

// Lấy danh sách phòng ban để lọc
$departments_sql = "SELECT id, name FROM departments ORDER BY name";
$departments_result = query($departments_sql);
$departments = [];
while ($dept = fetch_array($departments_result)) {
    $departments[$dept['id']] = $dept['name'];
}

// Lấy dữ liệu trạng thái công việc
$status_sql = "SELECT t.status_id, COUNT(*) as count
              FROM tasks t
              WHERE 1=1 $where_condition
              GROUP BY t.status_id
              ORDER BY t.status_id";
$status_result = query($status_sql);

$status_data = [
    1 => 0, // Chưa bắt đầu
    2 => 0, // Đang thực hiện
    3 => 0, // Hoàn thành
    4 => 0, // Tạm dừng
    5 => 0  // Đã hủy
];

$status_labels = [
    1 => 'Chưa bắt đầu',
    2 => 'Đang thực hiện',
    3 => 'Hoàn thành',
    4 => 'Tạm dừng',
    5 => 'Đã hủy'
];

$status_colors = [
    1 => '#4e73df', // primary
    2 => '#f6c23e', // warning
    3 => '#1cc88a', // success
    4 => '#e74a3b', // danger
    5 => '#858796'  // secondary
];

while ($row = fetch_array($status_result)) {
    $status_data[$row['status_id']] = (int)$row['count'];
}

// Lấy tổng số công việc
$total_tasks = array_sum($status_data);

// Công việc quá hạn
$overdue_sql = "SELECT COUNT(*) as count
               FROM tasks t
               WHERE t.due_date < CURDATE() AND t.status_id != 3 AND t.status_id != 5
               $where_condition";
$overdue_result = query($overdue_sql);
$overdue_count = fetch_array($overdue_result)['count'];

// Công việc sắp đến hạn (còn 3 ngày)
$upcoming_sql = "SELECT COUNT(*) as count
                FROM tasks t
                WHERE t.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
                AND t.status_id != 3 AND t.status_id != 5
                $where_condition";
$upcoming_result = query($upcoming_sql);
$upcoming_count = fetch_array($upcoming_result)['count'];

// Mức độ ưu tiên
$priority_sql = "SELECT t.priority, COUNT(*) as count
                FROM tasks t
                WHERE 1=1 $where_condition
                GROUP BY t.priority
                ORDER BY t.priority";
$priority_result = query($priority_sql);

$priority_data = [
    1 => 0, // Thấp
    2 => 0, // Trung bình
    3 => 0, // Cao
    4 => 0  // Khẩn cấp
];

$priority_labels = [
    1 => 'Thấp',
    2 => 'Trung bình',
    3 => 'Cao',
    4 => 'Khẩn cấp'
];

$priority_colors = [
    1 => '#858796', // secondary
    2 => '#36b9cc', // info
    3 => '#f6c23e', // warning
    4 => '#e74a3b'  // danger
];

while ($row = fetch_array($priority_result)) {
    $priority_data[$row['priority']] = (int)$row['count'];
}

// Top 5 người thực hiện nhiều công việc nhất
$top_users_sql = "SELECT u.id, u.name, u.avatar,
                 COUNT(t.id) as task_count,
                 SUM(CASE WHEN t.status_id = 3 THEN 1 ELSE 0 END) as completed_count,
                 SUM(CASE WHEN t.status_id = 3 AND t.completed_date <= t.due_date THEN 1 ELSE 0 END) as on_time_count
                 FROM users u
                 JOIN tasks t ON u.id = t.assigned_to
                 WHERE 1=1 $where_condition
                 GROUP BY u.id, u.name, u.avatar
                 ORDER BY task_count DESC
                 LIMIT 5";
$top_users_result = query($top_users_sql);

// Danh sách công việc quá hạn
$overdue_tasks_sql = "SELECT t.id, t.title, t.due_date, p.name as project_name,
                     u.name as assigned_name, u.avatar as assigned_avatar
                     FROM tasks t
                     LEFT JOIN projects p ON t.project_id = p.id
                     LEFT JOIN users u ON t.assigned_to = u.id
                     WHERE t.due_date < CURDATE() AND t.status_id != 3 AND t.status_id != 5
                     $where_condition
                     ORDER BY t.due_date ASC
                     LIMIT 5";
$overdue_tasks_result = query($overdue_tasks_sql);

// Thống kê theo dự án
$project_stats_sql = "SELECT p.id, p.name,
                     COUNT(t.id) as task_count,
                     SUM(CASE WHEN t.status_id = 3 THEN 1 ELSE 0 END) as completed_count,
                     AVG(t.progress) as avg_progress
                     FROM projects p
                     LEFT JOIN tasks t ON p.id = t.project_id
                     WHERE 1=1 " . str_replace('t.', 'p.', $where_condition) . 
                     " GROUP BY p.id, p.name
                     ORDER BY task_count DESC
                     LIMIT 5";
$project_stats_result = query($project_stats_sql);

// Tiêu đề trang
$page_title = "Báo cáo công việc";

// Include header
include_once '../../templates/header.php';
?>

<!-- Thêm thư viện Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Báo cáo công việc</h1>
        <div>
            <a href="export.php?type=task" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
                <i class="fas fa-download fa-sm text-white-50"></i> Xuất báo cáo
            </a>
            <a href="index.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm ml-2">
                <i class="fas fa-arrow-left fa-sm text-white-50"></i> Quay lại
            </a>
        </div>
    </div>

    <!-- Bộ lọc -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Bộ lọc</h6>
        </div>
        <div class="card-body">
            <form method="GET" action="">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="period">Thời gian</label>
                        <select class="form-control" id="period" name="period" onchange="this.form.submit()">
                            <option value="all" <?php echo $period == 'all' ? 'selected' : ''; ?>>Tất cả</option>
                            <option value="week" <?php echo $period == 'week' ? 'selected' : ''; ?>>Tuần này</option>
                            <option value="month" <?php echo $period == 'month' ? 'selected' : ''; ?>>Tháng này</option>
                            <option value="quarter" <?php echo $period == 'quarter' ? 'selected' : ''; ?>>Quý này</option>
                            <option value="year" <?php echo $period == 'year' ? 'selected' : ''; ?>>Năm nay</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="project_id">Dự án</label>
                        <select class="form-control" id="project_id" name="project_id" onchange="this.form.submit()">
                            <option value="0">Tất cả dự án</option>
                            <?php foreach ($projects as $id => $name): ?>
                                <option value="<?php echo $id; ?>" <?php echo $project_id == $id ? 'selected' : ''; ?>>
                                    <?php echo $name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="department_id">Phòng ban</label>
                        <select class="form-control" id="department_id" name="department_id" onchange="this.form.submit()">
                            <option value="0">Tất cả phòng ban</option>
                            <?php foreach ($departments as $id => $name): ?>
                                <option value="<?php echo $id; ?>" <?php echo $department_id == $id ? 'selected' : ''; ?>>
                                    <?php echo $name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label>&nbsp;</label>
                        <a href="task_report.php" class="btn btn-secondary btn-block">Đặt lại bộ lọc</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Tổng quan -->
    <div class="row">
        <!-- Tổng số công việc -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Tổng số công việc</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_tasks; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-tasks fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Công việc hoàn thành -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Hoàn thành</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $status_data[3]; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Công việc đang thực hiện -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Đang thực hiện</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $status_data[2]; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-spinner fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Công việc quá hạn -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Quá hạn</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $overdue_count; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Biểu đồ và thống kê -->
    <div class="row">
        <!-- Biểu đồ trạng thái công việc -->
        <div class="col-xl-6 col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Trạng thái công việc</h6>
                </div>
                <div class="card-body">
                    <div class="chart-pie pt-4 pb-2">
                        <canvas id="taskStatusChart"></canvas>
                    </div>
                    <div class="mt-4 text-center small">
                        <?php foreach ($status_labels as $id => $label): ?>
                            <span class="mr-2">
                                <i class="fas fa-circle" style="color: <?php echo $status_colors[$id]; ?>"></i> <?php echo $label; ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Biểu đồ mức độ ưu tiên -->
        <div class="col-xl-6 col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Mức độ ưu tiên</h6>
                </div>
                <div class="card-body">
                    <div class="chart-pie pt-4 pb-2">
                        <canvas id="taskPriorityChart"></canvas>
                    </div>
                    <div class="mt-4 text-center small">
                        <?php foreach ($priority_labels as $id => $label): ?>
                            <span class="mr-2">
                                <i class="fas fa-circle" style="color: <?php echo $priority_colors[$id]; ?>"></i> <?php echo $label; ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Thống kê người dùng và công việc quá hạn -->
    <div class="row">
        <!-- Top người thực hiện nhiều công việc nhất -->
        <div class="col-xl-6 col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Top 5 người thực hiện</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Người dùng</th>
                                    <th>Tổng công việc</th>
                                    <th>Hoàn thành</th>
                                    <th>Đúng hạn</th>
                                    <th>Tỉ lệ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (num_rows($top_users_result) > 0): ?>
                                    <?php while ($user = fetch_array($top_users_result)): ?>
                                        <?php 
                                        $completion_rate = $user['task_count'] > 0 ? 
                                            round(($user['completed_count'] / $user['task_count']) * 100) : 0;
                                        $on_time_rate = $user['completed_count'] > 0 ? 
                                            round(($user['on_time_count'] / $user['completed_count']) * 100) : 0;
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?php echo BASE_URL . $user['avatar']; ?>" class="rounded-circle mr-2" width="30" height="30">
                                                    <?php echo $user['name']; ?>
                                                </div>
                                            </td>
                                            <td><?php echo $user['task_count']; ?></td>
                                            <td><?php echo $user['completed_count']; ?></td>
                                            <td><?php echo $user['on_time_count']; ?></td>
                                            <td>
                                                <div class="progress progress-sm">
                                                    <div class="progress-bar bg-success" role="progressbar" 
                                                         style="width: <?php echo $on_time_rate; ?>%"
                                                         aria-valuenow="<?php echo $on_time_rate; ?>" 
                                                         aria-valuemin="0" aria-valuemax="100">
                                                        <?php echo $on_time_rate; ?>%
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center">Không có dữ liệu</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Công việc quá hạn -->
        <div class="col-xl-6 col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Công việc quá hạn (top 5)</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Công việc</th>
                                    <th>Dự án</th>
                                    <th>Người thực hiện</th>
                                    <th>Hạn hoàn thành</th>
                                    <th>Quá hạn</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (num_rows($overdue_tasks_result) > 0): ?>
                                    <?php while ($task = fetch_array($overdue_tasks_result)): ?>
                                        <?php 
                                        $due_date = strtotime($task['due_date']);
                                        $today = strtotime(date('Y-m-d'));
                                        $days_overdue = floor(($today - $due_date) / (60 * 60 * 24));
                                        ?>
                                        <tr>
                                            <td>
                                                <a href="../tasks/view.php?id=<?php echo $task['id']; ?>">
                                                    <?php echo $task['title']; ?>
                                                </a>
                                            </td>
                                            <td><?php echo $task['project_name']; ?></td>
                                            <td>
                                                <?php if ($task['assigned_name']): ?>
                                                    <div class="d-flex align-items-center">
                                                        <img src="<?php echo BASE_URL . $task['assigned_avatar']; ?>" class="rounded-circle mr-2" width="30" height="30">
                                                        <?php echo $task['assigned_name']; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">Chưa phân công</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo format_date($task['due_date']); ?></td>
                                            <td><span class="badge badge-danger"><?php echo $days_overdue; ?> ngày</span></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center">Không có công việc quá hạn</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($overdue_count > 5): ?>
                        <div class="text-center mt-3">
                            <a href="../tasks/index.php?date_filter=overdue" class="btn btn-sm btn-primary">
                                Xem tất cả công việc quá hạn
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Thống kê theo dự án -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Thống kê theo dự án (top 5)</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Dự án</th>
                            <th>Tổng công việc</th>
                            <th>Đã hoàn thành</th>
                            <th>Tiến độ trung bình</th>
                            <th>Tỉ lệ hoàn thành</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (num_rows($project_stats_result) > 0): ?>
                            <?php while ($project = fetch_array($project_stats_result)): ?>
                                <?php 
                                $avg_progress = round($project['avg_progress']);
                                $completion_rate = $project['task_count'] > 0 ? 
                                    round(($project['completed_count'] / $project['task_count']) * 100) : 0;
                                ?>
                                <tr>
                                    <td>
                                        <a href="../projects/view.php?id=<?php echo $project['id']; ?>">
                                            <?php echo $project['name']; ?>
                                        </a>
                                    </td>
                                    <td><?php echo $project['task_count']; ?></td>
                                    <td><?php echo $project['completed_count']; ?></td>
                                    <td>
                                        <div class="progress progress-sm">
                                            <div class="progress-bar bg-info" role="progressbar" 
                                                 style="width: <?php echo $avg_progress; ?>%"
                                                 aria-valuenow="<?php echo $avg_progress; ?>" 
                                                 aria-valuemin="0" aria-valuemax="100">
                                                <?php echo $avg_progress; ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="progress progress-sm">
                                            <div class="progress-bar bg-success" role="progressbar" 
                                                 style="width: <?php echo $completion_rate; ?>%"
                                                 aria-valuenow="<?php echo $completion_rate; ?>" 
                                                 aria-valuemin="0" aria-valuemax="100">
                                                <?php echo $completion_rate; ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center">Không có dữ liệu</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Biểu đồ trạng thái công việc
var statusCtx = document.getElementById('taskStatusChart').getContext('2d');
var taskStatusChart = new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: [
            <?php 
            $labels = [];
            foreach ($status_labels as $id => $label) {
                $labels[] = "'" . $label . "'";
            }
            echo implode(', ', $labels);
            ?>
        ],
        datasets: [{
            data: [
                <?php echo implode(', ', array_values($status_data)); ?>
            ],
            backgroundColor: [
                <?php 
                $colors = [];
                foreach ($status_colors as $color) {
                    $colors[] = "'" . $color . "'";
                }
                echo implode(', ', $colors);
                ?>
            ],
            hoverBackgroundColor: [
                <?php 
                $colors = [];
                foreach ($status_colors as $color) {
                    $colors[] = "'" . $color . "'";
                }
                echo implode(', ', $colors);
                ?>
            ],
            hoverBorderColor: "rgba(234, 236, 244, 1)",
        }],
    },
    options: {
        maintainAspectRatio: false,
        tooltips: {
            backgroundColor: "rgb(255,255,255)",
            bodyFontColor: "#858796",
            borderColor: '#dddfeb',
            borderWidth: 1,
            xPadding: 15,
            yPadding: 15,
            displayColors: false,
            caretPadding: 10,
        },
        legend: {
            display: false
        },
        cutoutPercentage: 80,
    },
});

// Biểu đồ mức độ ưu tiên
var priorityCtx = document.getElementById('taskPriorityChart').getContext('2d');
var taskPriorityChart = new Chart(priorityCtx, {
    type: 'doughnut',
    data: {
        labels: [
            <?php 
            $labels = [];
            foreach ($priority_labels as $id => $label) {
                $labels[] = "'" . $label . "'";
            }
            echo implode(', ', $labels);
            ?>
        ],
        datasets: [{
            data: [
                <?php echo implode(', ', array_values($priority_data)); ?>
            ],
            backgroundColor: [
                <?php 
                $colors = [];
                foreach ($priority_colors as $color) {
                    $colors[] = "'" . $color . "'";
                }
                echo implode(', ', $colors);
                ?>
            ],
            hoverBackgroundColor: [
                <?php 
                $colors = [];
                foreach ($priority_colors as $color) {
                    $colors[] = "'" . $color . "'";
                }
                echo implode(', ', $colors);
                ?>
            ],
            hoverBorderColor: "rgba(234, 236, 244, 1)",
        }],
    },
    options: {
        maintainAspectRatio: false,
        tooltips: {
            backgroundColor: "rgb(255,255,255)",
            bodyFontColor: "#858796",
            borderColor: '#dddfeb',
            borderWidth: 1,
            xPadding: 15,
            yPadding: 15,
            displayColors: false,
            caretPadding: 10,
        },
        legend: {
            display: false
        },
        cutoutPercentage: 80,
    },
});
</script>

<?php
// Include footer
include_once '../../templates/footer.php';
?> 