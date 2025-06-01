<?php
/**
 * Báo cáo hiệu suất phòng ban
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

// Thống kê hiệu suất theo phòng ban
$dept_stats_sql = "SELECT d.id, d.name, d.description, u.name as manager_name,
                  COUNT(DISTINCT u2.id) as user_count,
                  COUNT(t.id) as task_count,
                  SUM(CASE WHEN t.status_id = 3 THEN 1 ELSE 0 END) as completed_tasks,
                  SUM(CASE WHEN t.status_id = 3 AND t.completed_date <= t.due_date THEN 1 ELSE 0 END) as on_time_tasks,
                  COUNT(DISTINCT p.id) as project_count,
                  SUM(CASE WHEN t.status_id = 3 THEN t.priority ELSE 0 END) as priority_score,
                  AVG(CASE WHEN t.status_id = 3 THEN DATEDIFF(t.completed_date, t.start_date) ELSE NULL END) as avg_completion_days
                  FROM departments d
                  LEFT JOIN users u ON d.manager_id = u.id
                  LEFT JOIN users u2 ON d.id = u2.department_id
                  LEFT JOIN tasks t ON u2.id = t.assigned_to
                  LEFT JOIN projects p ON t.project_id = p.id
                  WHERE 1=1 $where_condition
                  GROUP BY d.id, d.name, d.description, u.name
                  ORDER BY completed_tasks DESC";
$dept_stats_result = query($dept_stats_sql);

// Thống kê công việc quá hạn theo phòng ban
$overdue_stats_sql = "SELECT d.id, d.name, 
                     COUNT(t.id) as overdue_count
                     FROM departments d
                     LEFT JOIN users u ON d.id = u.department_id
                     LEFT JOIN tasks t ON u.id = t.assigned_to
                     WHERE t.due_date < CURDATE() AND t.status_id IN (1, 2, 4) $where_condition
                     GROUP BY d.id, d.name
                     ORDER BY overdue_count DESC";
$overdue_stats_result = query($overdue_stats_sql);

// Chuyển kết quả truy vấn thành mảng
$overdue_by_dept = [];
while ($row = fetch_array($overdue_stats_result)) {
    $overdue_by_dept[$row['id']] = $row['overdue_count'];
}

// Thống kê dự án theo phòng ban
$project_stats_sql = "SELECT d.id, d.name,
                     COUNT(DISTINCT pd.project_id) as total_projects,
                     SUM(CASE WHEN p.status_id = 3 THEN 1 ELSE 0 END) as completed_projects,
                     SUM(CASE WHEN p.end_date < CURDATE() AND p.status_id IN (1, 2, 4) THEN 1 ELSE 0 END) as overdue_projects
                     FROM departments d
                     LEFT JOIN project_departments pd ON d.id = pd.department_id
                     LEFT JOIN projects p ON pd.project_id = p.id
                     WHERE 1=1 " . str_replace('t.', 'p.', $where_condition) . "
                     GROUP BY d.id, d.name
                     ORDER BY total_projects DESC";
$project_stats_result = query($project_stats_sql);

// Chuyển kết quả thành mảng
$projects_by_dept = [];
while ($row = fetch_array($project_stats_result)) {
    $projects_by_dept[$row['id']] = [
        'total' => $row['total_projects'],
        'completed' => $row['completed_projects'],
        'overdue' => $row['overdue_projects']
    ];
}

// Phòng ban hàng đầu (Top department) - phòng ban hoàn thành nhiều công việc nhất
$top_dept_sql = "SELECT d.id, d.name, COUNT(t.id) as task_count, 
                SUM(CASE WHEN t.status_id = 3 THEN 1 ELSE 0 END) as completed_count,
                ROUND((SUM(CASE WHEN t.status_id = 3 THEN 1 ELSE 0 END) / COUNT(t.id)) * 100) as completion_rate
                FROM departments d
                LEFT JOIN users u ON d.id = u.department_id
                LEFT JOIN tasks t ON u.id = t.assigned_to
                WHERE 1=1 $where_condition
                GROUP BY d.id, d.name
                HAVING task_count > 0
                ORDER BY completion_rate DESC, completed_count DESC
                LIMIT 1";
$top_dept_result = query($top_dept_sql);
$top_department = fetch_array($top_dept_result);

// Tiêu đề trang
$page_title = "Báo cáo phòng ban";

// Include header
include_once '../../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Báo cáo hiệu suất phòng ban</h1>
        <div>
            <a href="export.php?type=department<?php echo $period != 'all' ? '&period='.$period : ''; ?>" 
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
            <h6 class="m-0 font-weight-bold text-primary">Lọc theo thời gian</h6>
        </div>
        <div class="card-body">
            <form method="GET" action="">
                <div class="row">
                    <div class="col-md-12">
                        <div class="btn-group" role="group">
                            <a href="?period=all" class="btn btn-<?php echo $period == 'all' ? 'primary' : 'outline-primary'; ?>">Tất cả thời gian</a>
                            <a href="?period=week" class="btn btn-<?php echo $period == 'week' ? 'primary' : 'outline-primary'; ?>">Tuần này</a>
                            <a href="?period=month" class="btn btn-<?php echo $period == 'month' ? 'primary' : 'outline-primary'; ?>">Tháng này</a>
                            <a href="?period=quarter" class="btn btn-<?php echo $period == 'quarter' ? 'primary' : 'outline-primary'; ?>">Quý này</a>
                            <a href="?period=year" class="btn btn-<?php echo $period == 'year' ? 'primary' : 'outline-primary'; ?>">Năm nay</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Tổng quan -->
    <?php if (isset($top_department) && !empty($top_department)): ?>
    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Phòng ban xuất sắc</h6>
                </div>
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-lg-4 text-center">
                            <i class="fas fa-award text-warning fa-5x mb-3"></i>
                            <h4 class="font-weight-bold"><?php echo $top_department['name']; ?></h4>
                            <div class="text-muted mb-3">Tỉ lệ hoàn thành công việc cao nhất</div>
                            <div class="display-4 font-weight-bold text-primary"><?php echo $top_department['completion_rate']; ?>%</div>
                        </div>
                        <div class="col-lg-8">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card border-left-success shadow h-100 py-2">
                                        <div class="card-body">
                                            <div class="row no-gutters align-items-center">
                                                <div class="col mr-2">
                                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                        Công việc hoàn thành</div>
                                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $top_department['completed_count']; ?> / <?php echo $top_department['task_count']; ?></div>
                                                </div>
                                                <div class="col-auto">
                                                    <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="card border-left-info shadow h-100 py-2">
                                        <div class="card-body">
                                            <div class="row no-gutters align-items-center">
                                                <div class="col mr-2">
                                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                                        Tỉ lệ hoàn thành</div>
                                                    <div class="row no-gutters align-items-center">
                                                        <div class="col-auto">
                                                            <div class="h5 mb-0 mr-3 font-weight-bold text-gray-800"><?php echo $top_department['completion_rate']; ?>%</div>
                                                        </div>
                                                        <div class="col">
                                                            <div class="progress progress-sm mr-2">
                                                                <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo $top_department['completion_rate']; ?>%"></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-auto">
                                                    <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Bảng hiệu suất phòng ban -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Hiệu suất các phòng ban (<?php echo $period_text; ?>)</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="departmentTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Phòng ban</th>
                            <th>Quản lý</th>
                            <th>Nhân viên</th>
                            <th>Dự án tham gia</th>
                            <th>Công việc</th>
                            <th>Hoàn thành</th>
                            <th>Đúng hạn</th>
                            <th>Quá hạn</th>
                            <th>Tỉ lệ hoàn thành</th>
                            <th>Tỉ lệ đúng hạn</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (num_rows($dept_stats_result) > 0): ?>
                            <?php while ($dept = fetch_array($dept_stats_result)): ?>
                                <?php 
                                $completion_rate = $dept['task_count'] > 0 ? 
                                    round(($dept['completed_tasks'] / $dept['task_count']) * 100) : 0;
                                $on_time_rate = $dept['completed_tasks'] > 0 ? 
                                    round(($dept['on_time_tasks'] / $dept['completed_tasks']) * 100) : 0;
                                
                                // Lấy số lượng công việc quá hạn
                                $overdue_count = isset($overdue_by_dept[$dept['id']]) ? $overdue_by_dept[$dept['id']] : 0;
                                
                                // Lấy thông tin dự án
                                $project_data = isset($projects_by_dept[$dept['id']]) ? $projects_by_dept[$dept['id']] : ['total' => 0, 'completed' => 0, 'overdue' => 0];
                                ?>
                                <tr>
                                    <td class="font-weight-bold"><?php echo $dept['name']; ?></td>
                                    <td><?php echo $dept['manager_name'] ?: 'Chưa phân công'; ?></td>
                                    <td><?php echo $dept['user_count']; ?></td>
                                    <td>
                                        <?php echo $project_data['total']; ?>
                                        <?php if($project_data['total'] > 0): ?>
                                            <div class="small text-success"><?php echo $project_data['completed']; ?> hoàn thành</div>
                                            <?php if($project_data['overdue'] > 0): ?>
                                                <div class="small text-danger"><?php echo $project_data['overdue']; ?> quá hạn</div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $dept['task_count']; ?></td>
                                    <td><?php echo $dept['completed_tasks']; ?></td>
                                    <td><?php echo $dept['on_time_tasks']; ?></td>
                                    <td>
                                        <?php if($overdue_count > 0): ?>
                                            <span class="badge badge-danger"><?php echo $overdue_count; ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-success">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="progress progress-sm">
                                            <div class="progress-bar bg-<?php echo $completion_rate >= 70 ? 'success' : ($completion_rate >= 40 ? 'warning' : 'danger'); ?>" 
                                                 role="progressbar" style="width: <?php echo $completion_rate; ?>%">
                                                <?php echo $completion_rate; ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="progress progress-sm">
                                            <div class="progress-bar bg-info" role="progressbar" 
                                                 style="width: <?php echo $on_time_rate; ?>%">
                                                <?php echo $on_time_rate; ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center">Không có dữ liệu phòng ban</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Biểu đồ so sánh hiệu suất phòng ban -->
    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">So sánh hiệu suất phòng ban</h6>
                </div>
                <div class="card-body">
                    <div class="chart-bar">
                        <canvas id="departmentComparisonChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
$(document).ready(function() {
    // Khởi tạo bảng với DataTables
    $('#departmentTable').DataTable({
        language: {
            url: "//cdn.datatables.net/plug-ins/1.10.24/i18n/Vietnamese.json"
        },
        order: [[8, 'desc']], // Sắp xếp theo tỉ lệ hoàn thành
        pageLength: 10
    });
    
    // Khởi tạo biểu đồ so sánh phòng ban
    var ctx = document.getElementById('departmentComparisonChart').getContext('2d');
    var departmentChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: [
                <?php 
                mysqli_data_seek($dept_stats_result, 0);
                $labels = [];
                $completion_data = [];
                $ontime_data = [];
                
                while ($dept = fetch_array($dept_stats_result)) {
                    if ($dept['task_count'] > 0) {
                        $labels[] = "'" . $dept['name'] . "'";
                        
                        $completion_rate = round(($dept['completed_tasks'] / $dept['task_count']) * 100);
                        $completion_data[] = $completion_rate;
                        
                        $on_time_rate = $dept['completed_tasks'] > 0 ? 
                            round(($dept['on_time_tasks'] / $dept['completed_tasks']) * 100) : 0;
                        $ontime_data[] = $on_time_rate;
                    }
                }
                
                echo implode(', ', $labels);
                ?>
            ],
            datasets: [
                {
                    label: 'Tỉ lệ hoàn thành (%)',
                    data: [<?php echo implode(', ', $completion_data); ?>],
                    backgroundColor: 'rgba(78, 115, 223, 0.8)',
                    borderColor: 'rgba(78, 115, 223, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Tỉ lệ đúng hạn (%)',
                    data: [<?php echo implode(', ', $ontime_data); ?>],
                    backgroundColor: 'rgba(28, 200, 138, 0.8)',
                    borderColor: 'rgba(28, 200, 138, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        callback: function(value) {
                            return value + '%';
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.raw + '%';
                        }
                    }
                }
            }
        }
    });
});
</script>

<?php
// Include footer
include_once '../../templates/footer.php';
?> 