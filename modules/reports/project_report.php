<?php
/**
 * Báo cáo dự án
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
        $where_condition = " WHERE p.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)";
        break;
    case 'month':
        $where_condition = " WHERE p.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
        break;
    case 'quarter':
        $where_condition = " WHERE p.created_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
        break;
    case 'year':
        $where_condition = " WHERE p.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
        break;
    default:
        $where_condition = "";
        break;
}

// Lấy dữ liệu trạng thái dự án
$status_sql = "SELECT p.status_id, COUNT(*) as count
              FROM projects p
              $where_condition
              GROUP BY p.status_id
              ORDER BY p.status_id";
$status_result = query($status_sql);

$status_data = [
    1 => 0, // Chuẩn bị
    2 => 0, // Đang thực hiện
    3 => 0, // Hoàn thành
    4 => 0, // Tạm dừng
    5 => 0  // Đã hủy
];

$status_labels = [
    1 => 'Chuẩn bị',
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

// Lấy tổng số dự án
$total_projects = array_sum($status_data);

// Dự án quá hạn
$overdue_sql = "SELECT COUNT(*) as count
               FROM projects p
               WHERE p.end_date < CURDATE() AND p.status_id != 3 AND p.status_id != 5";
$overdue_result = query($overdue_sql);
$overdue_count = fetch_array($overdue_result)['count'];

// Dự án sắp đến hạn (còn 7 ngày)
$upcoming_sql = "SELECT COUNT(*) as count
                FROM projects p
                WHERE p.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                AND p.status_id != 3 AND p.status_id != 5";
$upcoming_result = query($upcoming_sql);
$upcoming_count = fetch_array($upcoming_result)['count'];

// Dự án hoàn thành trong thời gian qua
$completed_sql = "SELECT COUNT(*) as count
                 FROM projects p
                 WHERE p.status_id = 3";
if ($period != 'all') {
    $completed_sql .= " AND " . ltrim(str_replace('p.created_at', 'p.updated_at', $where_condition), " WHERE");
}
$completed_result = query($completed_sql);
$completed_count = fetch_array($completed_result)['count'];

// Tỉ lệ hoàn thành đúng hạn
$on_time_sql = "SELECT COUNT(*) as count
               FROM projects p
               WHERE p.status_id = 3 AND p.end_date >= p.updated_at";
$on_time_result = query($on_time_sql);
$on_time_count = fetch_array($on_time_result)['count'];

$on_time_rate = $completed_count > 0 ? round(($on_time_count / $completed_count) * 100) : 0;

// Thống kê theo phòng ban
$dept_sql = "SELECT d.id, d.name, COUNT(DISTINCT pd.project_id) as project_count,
            SUM(CASE WHEN p.status_id = 3 THEN 1 ELSE 0 END) as completed_count
            FROM departments d
            LEFT JOIN project_departments pd ON d.id = pd.department_id
            LEFT JOIN projects p ON pd.project_id = p.id
            GROUP BY d.id, d.name
            ORDER BY project_count DESC
            LIMIT 5";
$dept_result = query($dept_sql);

// Danh sách dự án gần đây
$recent_projects_sql = "SELECT p.id, p.name, p.start_date, p.end_date, p.status_id, 
                       (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id) as task_count,
                       (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status_id = 3) as completed_tasks
                       FROM projects p
                       ORDER BY p.created_at DESC
                       LIMIT 5";
$recent_projects_result = query($recent_projects_sql);

// Tiêu đề trang
$page_title = "Báo cáo dự án";

// Include header
include_once '../../templates/header.php';
?>

<!-- Thêm thư viện Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Báo cáo dự án</h1>
        <div>
            <a href="export.php?type=project" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
                <i class="fas fa-download fa-sm text-white-50"></i> Xuất báo cáo
            </a>
            <a href="index.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm ml-2">
                <i class="fas fa-arrow-left fa-sm text-white-50"></i> Quay lại
            </a>
        </div>
    </div>

    <!-- Lọc thời gian -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Lọc theo thời gian</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-12">
                    <div class="btn-group mb-3" role="group">
                        <a href="?period=all" class="btn btn-<?php echo $period == 'all' ? 'primary' : 'outline-primary'; ?>">Tất cả</a>
                        <a href="?period=week" class="btn btn-<?php echo $period == 'week' ? 'primary' : 'outline-primary'; ?>">Tuần này</a>
                        <a href="?period=month" class="btn btn-<?php echo $period == 'month' ? 'primary' : 'outline-primary'; ?>">Tháng này</a>
                        <a href="?period=quarter" class="btn btn-<?php echo $period == 'quarter' ? 'primary' : 'outline-primary'; ?>">Quý này</a>
                        <a href="?period=year" class="btn btn-<?php echo $period == 'year' ? 'primary' : 'outline-primary'; ?>">Năm nay</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tổng quan -->
    <div class="row">
        <!-- Tổng số dự án -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Tổng số dự án</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_projects; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dự án hoàn thành -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Dự án hoàn thành</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $completed_count; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dự án đang thực hiện -->
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
                            <i class="fas fa-hourglass-half fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dự án quá hạn -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Dự án quá hạn</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $overdue_count; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Biểu đồ và thống kê -->
    <div class="row">
        <!-- Biểu đồ trạng thái dự án -->
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Trạng thái dự án</h6>
                </div>
                <div class="card-body">
                    <div class="chart-pie pt-4 pb-2">
                        <canvas id="projectStatusChart"></canvas>
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

        <!-- Thống kê hiệu suất -->
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Hiệu suất dự án</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-xl-6 col-md-6 mb-4">
                            <div class="card border-left-success h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                Tỉ lệ hoàn thành đúng hạn</div>
                                            <div class="row no-gutters align-items-center">
                                                <div class="col-auto">
                                                    <div class="h5 mb-0 mr-3 font-weight-bold text-gray-800"><?php echo $on_time_rate; ?>%</div>
                                                </div>
                                                <div class="col">
                                                    <div class="progress progress-sm mr-2">
                                                        <div class="progress-bar bg-success" role="progressbar"
                                                             style="width: <?php echo $on_time_rate; ?>%"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-calendar-check fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-6 col-md-6 mb-4">
                            <div class="card border-left-warning h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                                Sắp đến hạn (7 ngày)</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $upcoming_count; ?> dự án</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-responsive mt-3">
                        <h6 class="font-weight-bold">Phòng ban tham gia dự án nhiều nhất</h6>
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Phòng ban</th>
                                    <th>Số dự án</th>
                                    <th>Đã hoàn thành</th>
                                    <th>Tỉ lệ hoàn thành</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($dept = fetch_array($dept_result)): ?>
                                    <?php 
                                    $completion_rate = $dept['project_count'] > 0 ? 
                                        round(($dept['completed_count'] / $dept['project_count']) * 100) : 0;
                                    ?>
                                    <tr>
                                        <td><?php echo $dept['name']; ?></td>
                                        <td><?php echo $dept['project_count']; ?></td>
                                        <td><?php echo $dept['completed_count']; ?></td>
                                        <td>
                                            <div class="progress progress-sm">
                                                <div class="progress-bar bg-info" role="progressbar" 
                                                     style="width: <?php echo $completion_rate; ?>%"
                                                     aria-valuenow="<?php echo $completion_rate; ?>" 
                                                     aria-valuemin="0" aria-valuemax="100">
                                                    <?php echo $completion_rate; ?>%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dự án gần đây -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Dự án gần đây</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Tên dự án</th>
                            <th>Ngày bắt đầu</th>
                            <th>Ngày kết thúc</th>
                            <th>Trạng thái</th>
                            <th>Tiến độ</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($project = fetch_array($recent_projects_result)): ?>
                            <?php
                            $status_colors_css = [
                                1 => 'primary',
                                2 => 'warning',
                                3 => 'success',
                                4 => 'danger',
                                5 => 'secondary'
                            ];
                            $progress = $project['task_count'] > 0 ? 
                                round(($project['completed_tasks'] / $project['task_count']) * 100) : 0;
                            ?>
                            <tr>
                                <td><?php echo $project['name']; ?></td>
                                <td><?php echo format_date($project['start_date']); ?></td>
                                <td><?php echo format_date($project['end_date']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $status_colors_css[$project['status_id']]; ?>">
                                        <?php echo $status_labels[$project['status_id']]; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="progress progress-sm">
                                        <div class="progress-bar bg-<?php echo $status_colors_css[$project['status_id']]; ?>" 
                                             role="progressbar" style="width: <?php echo $progress; ?>%"
                                             aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100">
                                            <?php echo $progress; ?>%
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <a href="../projects/view.php?id=<?php echo $project['id']; ?>" class="btn btn-info btn-sm">
                                        <i class="fas fa-eye"></i> Xem
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Biểu đồ trạng thái dự án
var ctx = document.getElementById('projectStatusChart').getContext('2d');
var projectStatusChart = new Chart(ctx, {
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
</script>

<?php
// Include footer
include_once '../../templates/footer.php';
?> 