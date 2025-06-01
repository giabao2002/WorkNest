<?php

/**
 * Báo cáo hiệu suất nhân viên
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
$period_text = 'Tất cả thời gian';

switch ($period) {
    case 'week':
        $where_condition = " AND t.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)";
        $period_text = 'Tuần này';
        break;
    case 'month':
        $where_condition = " AND t.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
        $period_text = 'Tháng này';
        break;
    case 'quarter':
        $where_condition = " AND t.created_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
        $period_text = 'Quý này';
        break;
    case 'year':
        $where_condition = " AND t.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
        $period_text = 'Năm nay';
        break;
    default:
        $where_condition = "";
        break;
}

// Lọc theo phòng ban
$department_id = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
if ($department_id > 0) {
    $where_condition .= " AND u.department_id = $department_id";
}

// Lọc theo vai trò
$role_filter = isset($_GET['role']) ? escape_string($_GET['role']) : '';
if (!empty($role_filter)) {
    $where_condition .= " AND u.role = '$role_filter'";
}

// Lấy danh sách phòng ban để lọc
$departments_sql = "SELECT id, name FROM departments ORDER BY name";
$departments_result = query($departments_sql);
$departments = [];
while ($dept = fetch_array($departments_result)) {
    $departments[$dept['id']] = $dept['name'];
}

// Vai trò người dùng
$roles = [
    'admin' => 'Quản trị viên',
    'project_manager' => 'Quản lý dự án',
    'department_manager' => 'Quản lý phòng ban',
    'staff' => 'Nhân viên'
];

// Thống kê hiệu suất người dùng
$users_stats_sql = "SELECT u.id, u.name, u.email, u.avatar, u.role, d.name as department_name,
                  COUNT(t.id) as task_count,
                  SUM(CASE WHEN t.status_id = 3 THEN 1 ELSE 0 END) as completed_count,
                  SUM(CASE WHEN t.status_id = 3 AND t.completed_date <= t.due_date THEN 1 ELSE 0 END) as on_time_count,
                  AVG(CASE WHEN t.status_id = 3 THEN DATEDIFF(t.completed_date, t.start_date) ELSE NULL END) as avg_completion_days,
                  COUNT(DISTINCT p.id) as project_count
                  FROM users u
                  LEFT JOIN departments d ON u.department_id = d.id
                  LEFT JOIN tasks t ON u.id = t.assigned_to
                  LEFT JOIN projects p ON t.project_id = p.id
                  WHERE u.role != 'admin' $where_condition
                  GROUP BY u.id, u.name, u.email, u.avatar, u.role, d.name
                  ORDER BY completed_count DESC, on_time_count DESC";
$users_stats_result = query($users_stats_sql);

// Top 5 nhân viên hiệu quả nhất (hoàn thành nhiều công việc nhất)
$top_users_sql = "SELECT u.id, u.name, u.avatar, d.name as department_name,
                 COUNT(t.id) as task_count,
                 SUM(CASE WHEN t.status_id = 3 THEN 1 ELSE 0 END) as completed_count,
                 SUM(CASE WHEN t.status_id = 3 AND t.completed_date <= t.due_date THEN 1 ELSE 0 END) as on_time_count
                 FROM users u
                 LEFT JOIN departments d ON u.department_id = d.id
                 LEFT JOIN tasks t ON u.id = t.assigned_to
                 WHERE u.role != 'admin' $where_condition
                 GROUP BY u.id, u.name, u.avatar, d.name
                 HAVING completed_count > 0
                 ORDER BY completed_count DESC, on_time_count DESC
                 LIMIT 5";
$top_users_result = query($top_users_sql);

// Tiêu đề trang
$page_title = "Báo cáo nhân viên";

// Include header
include_once '../../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Báo cáo hiệu suất nhân viên</h1>
        <div>
            <a href="export.php?type=user<?php echo $period != 'all' ? '&period=' . $period : ''; ?><?php echo $department_id > 0 ? '&department_id=' . $department_id : ''; ?><?php echo !empty($role_filter) ? '&role=' . $role_filter : ''; ?>"
                class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
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
                        <label for="role">Vai trò</label>
                        <select class="form-control" id="role" name="role" onchange="this.form.submit()">
                            <option value="">Tất cả vai trò</option>
                            <?php foreach ($roles as $role_key => $role_name): ?>
                                <option value="<?php echo $role_key; ?>" <?php echo $role_filter == $role_key ? 'selected' : ''; ?>>
                                    <?php echo $role_name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label>&nbsp;</label>
                        <a href="user_report.php" class="btn btn-secondary btn-block">Đặt lại bộ lọc</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Tổng quan -->
    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Top 5 nhân viên hiệu quả nhất (<?php echo $period_text; ?>)</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php if (num_rows($top_users_result) > 0): ?>
                            <?php while ($user = fetch_array($top_users_result)): ?>
                                <?php
                                $on_time_rate = $user['completed_count'] > 0 ?
                                    round(($user['on_time_count'] / $user['completed_count']) * 100) : 0;
                                ?>
                                <div class="col-xl-2 col-md-4 mb-4">
                                    <div class="card border-left-primary shadow h-100 py-2">
                                        <div class="card-body">
                                            <div class="text-center mb-2">
                                                <img src="<?php echo BASE_URL . $user['avatar']; ?>" class="rounded-circle" width="60" height="60">
                                                <h5 class="mt-2 mb-0 font-weight-bold"><?php echo $user['name']; ?></h5>
                                                <div class="text-xs text-muted"><?php echo $user['department_name']; ?></div>
                                            </div>
                                            <div class="text-center">
                                                <div class="mb-1">
                                                    <span class="font-weight-bold"><?php echo $user['completed_count']; ?></span>
                                                    công việc hoàn thành
                                                </div>
                                                <div class="mb-1">
                                                    <span class="font-weight-bold"><?php echo $user['on_time_count']; ?></span>
                                                    đúng hạn
                                                </div>
                                                <div class="progress progress-sm mx-auto" style="width: 80%;">
                                                    <div class="progress-bar bg-success" role="progressbar"
                                                        style="width: <?php echo $on_time_rate; ?>%">
                                                        <?php echo $on_time_rate; ?>%
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <div class="alert alert-info">
                                    Không có dữ liệu hiệu suất nhân viên trong khoảng thời gian đã chọn.
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bảng chi tiết hiệu suất nhân viên -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Chi tiết hiệu suất nhân viên</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="userStatsTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Nhân viên</th>
                            <th>Phòng ban</th>
                            <th>Vai trò</th>
                            <th>Số dự án</th>
                            <th>Tổng công việc</th>
                            <th>Hoàn thành</th>
                            <th>Đúng hạn</th>
                            <th>Trung bình (ngày)</th>
                            <th>Tỉ lệ đúng hạn</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (num_rows($users_stats_result) > 0): ?>
                            <?php while ($user = fetch_array($users_stats_result)): ?>
                                <?php
                                $on_time_rate = $user['completed_count'] > 0 ?
                                    round(($user['on_time_count'] / $user['completed_count']) * 100) : 0;
                                $avg_days = is_null($user['avg_completion_days']) ? 'N/A' : round($user['avg_completion_days'], 1);
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center justify-content-evenly">
                                            <img src="<?php echo BASE_URL . $user['avatar']; ?>" class="rounded-circle mr-2" width="30" height="30">
                                            <div>
                                                <div><?php echo $user['name']; ?></div>
                                                <div class="small text-muted"><?php echo $user['email']; ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo $user['department_name']; ?></td>
                                    <td><?php echo $roles[$user['role']]; ?></td>
                                    <td><?php echo $user['project_count']; ?></td>
                                    <td><?php echo $user['task_count']; ?></td>
                                    <td><?php echo $user['completed_count']; ?></td>
                                    <td><?php echo $user['on_time_count']; ?></td>
                                    <td><?php echo $avg_days; ?></td>
                                    <td>
                                        <div class="progress progress-sm">
                                            <div class="progress-bar bg-success" role="progressbar"
                                                style="width: <?php echo $on_time_rate; ?>%">
                                                <?php echo $on_time_rate; ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center">Không có dữ liệu</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Khởi tạo bảng với DataTables để có phân trang, tìm kiếm
        $('#userStatsTable').DataTable({
            language: {
                url: "//cdn.datatables.net/plug-ins/1.10.24/i18n/Vietnamese.json"
            },
            pageLength: 10,
            order: [
                [5, 'desc']
            ] // Sắp xếp theo số công việc hoàn thành (giảm dần)
        });
    });
</script>

<?php
// Include footer
include_once '../../templates/footer.php';
?>