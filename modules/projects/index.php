<?php
/**
 * Danh sách dự án
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

// Tìm kiếm và lọc
$search = isset($_GET['search']) ? escape_string($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? (int)$_GET['status'] : 0;
$department_filter = isset($_GET['department']) ? (int)$_GET['department'] : 0;

// Điều kiện lọc
$where_conditions = [];
if (!empty($search)) {
    $where_conditions[] = "(p.name LIKE '%$search%' OR p.description LIKE '%$search%')";
}

if ($status_filter > 0) {
    $where_conditions[] = "p.status_id = $status_filter";
}

// Lấy danh sách phòng ban cho người dùng hiện tại nếu không phải admin hoặc quản lý dự án
if (!has_permission('admin') && !has_permission('project_manager')) {
    // Nếu là quản lý phòng ban, chỉ hiển thị dự án của phòng ban đó
    if (has_permission('department_manager')) {
        // Lấy phòng ban của người dùng hiện tại
        $user_id = $_SESSION['user_id'];
        $dept_query = query("SELECT id FROM departments WHERE manager_id = $user_id");
        
        if (num_rows($dept_query) > 0) {
            $dept = fetch_array($dept_query);
            $department_filter = $dept['id'];
        }
    } else {
        // Nếu là nhân viên, chỉ hiển thị dự án mà họ tham gia
        $user_id = $_SESSION['user_id'];
        $where_conditions[] = "EXISTS (
            SELECT 1 FROM tasks t 
            WHERE t.project_id = p.id AND (t.assigned_to = $user_id OR t.assigned_by = $user_id)
        )";
    }
}

// Điều kiện lọc theo phòng ban
if ($department_filter > 0) {
    $where_conditions[] = "EXISTS (
        SELECT 1 FROM project_departments pd
        WHERE pd.project_id = p.id AND pd.department_id = $department_filter
    )";
}

// Tổng hợp điều kiện WHERE
$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

// Lấy tổng số dự án
$total_query = query("SELECT COUNT(*) as total FROM projects p $where_clause");
$total_row = fetch_array($total_query);
$total = $total_row['total'];
$total_pages = ceil($total / $limit);

// Lấy danh sách dự án
$sql = "SELECT p.*, u.name as manager_name,
        (SELECT COUNT(*) FROM tasks WHERE project_id = p.id) as total_tasks,
        (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND status_id = 3) as completed_tasks
        FROM projects p
        LEFT JOIN users u ON p.manager_id = u.id
        $where_clause
        ORDER BY p.status_id ASC, p.end_date ASC 
        LIMIT $offset, $limit";
$result = query($sql);

// Lấy danh sách trạng thái dự án
$status_list = [
    1 => 'Chuẩn bị',
    2 => 'Đang thực hiện',
    3 => 'Hoàn thành',
    4 => 'Tạm dừng',
    5 => 'Đã hủy'
];

// Lấy danh sách phòng ban cho bộ lọc
$departments_sql = "SELECT * FROM departments ORDER BY name ASC";
$departments_result = query($departments_sql);

// Tiêu đề trang
$page_title = "Quản lý dự án";

// Include header
include_once '../../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Quản lý dự án</h1>
        
        <?php if (has_permission('admin') || has_permission('project_manager')): ?>
        <a href="add.php" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
            <i class="fas fa-plus fa-sm text-white-50"></i> Thêm dự án mới
        </a>
        <?php endif; ?>
    </div>

    <!-- Bộ lọc -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Bộ lọc dự án</h6>
        </div>
        <div class="card-body">
            <form method="GET" action="">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" placeholder="Tìm kiếm dự án..." value="<?php echo $search; ?>">
                            <div class="input-group-append">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search fa-sm"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <select class="form-control" name="status" onchange="this.form.submit()">
                            <option value="0">-- Tất cả trạng thái --</option>
                            <?php foreach ($status_list as $id => $name): ?>
                                <option value="<?php echo $id; ?>" <?php echo ($status_filter == $id) ? 'selected' : ''; ?>>
                                    <?php echo $name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <select class="form-control" name="department" onchange="this.form.submit()">
                            <option value="0">-- Tất cả phòng ban --</option>
                            <?php while ($dept = fetch_array($departments_result)): ?>
                                <option value="<?php echo $dept['id']; ?>" <?php echo ($department_filter == $dept['id']) ? 'selected' : ''; ?>>
                                    <?php echo $dept['name']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2 mb-3">
                        <a href="index.php" class="btn btn-secondary btn-block">Đặt lại</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Danh sách dự án -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Danh sách dự án</h6>
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
                    <table class="table table-bordered" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tên dự án</th>
                                <th>Quản lý</th>
                                <th>Ngày bắt đầu</th>
                                <th>Ngày kết thúc</th>
                                <th>Trạng thái</th>
                                <th>Tiến độ</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = fetch_array($result)): ?>
                                <?php 
                                // Tính phần trăm hoàn thành
                                $progress = 0;
                                if ($row['total_tasks'] > 0) {
                                    $progress = round(($row['completed_tasks'] / $row['total_tasks']) * 100);
                                }
                                
                                // Xác định màu cho trạng thái
                                $status_colors = [
                                    1 => 'primary',   // Chuẩn bị
                                    2 => 'warning',   // Đang thực hiện
                                    3 => 'success',   // Hoàn thành
                                    4 => 'danger',    // Tạm dừng
                                    5 => 'secondary'  // Đã hủy
                                ];
                                $status_color = $status_colors[$row['status_id']] ?? 'secondary';
                                
                                // Xác định class cho thời hạn
                                $now = strtotime(date('Y-m-d'));
                                $end_date = strtotime($row['end_date']);
                                $date_class = '';
                                
                                if ($row['status_id'] != 3 && $row['status_id'] != 5) { // Nếu không phải đã hoàn thành hoặc đã hủy
                                    if ($end_date < $now) {
                                        $date_class = 'text-danger font-weight-bold'; // Quá hạn
                                    } elseif ($end_date < strtotime('+7 days', $now)) {
                                        $date_class = 'text-warning font-weight-bold'; // Sắp đến hạn
                                    }
                                }
                                ?>
                                <tr>
                                    <td><?php echo $row['id']; ?></td>
                                    <td><?php echo $row['name']; ?></td>
                                    <td><?php echo $row['manager_name']; ?></td>
                                    <td><?php echo format_date($row['start_date']); ?></td>
                                    <td class="<?php echo $date_class; ?>"><?php echo format_date($row['end_date']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $status_color; ?>">
                                            <?php echo $status_list[$row['status_id']]; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="progress">
                                            <div class="progress-bar bg-<?php echo $status_color; ?>" role="progressbar" 
                                                 style="width: <?php echo $progress; ?>%" 
                                                 aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100">
                                                <?php echo $progress; ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="view.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if (has_permission('admin') || has_permission('project_manager') || 
                                                (has_permission('department_manager') && $row['manager_id'] == $_SESSION['user_id'])): ?>
                                        <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php endif; ?>
                                        <?php if (has_permission('admin') || has_permission('project_manager')): ?>
                                        <a href="delete.php?id=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" 
                                           onclick="return confirm('Bạn có chắc muốn xóa dự án này? Tất cả công việc liên quan sẽ bị xóa.');">
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
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center mt-4">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo !empty($search) ? '&search='.$search : ''; ?><?php echo $status_filter > 0 ? '&status='.$status_filter : ''; ?><?php echo $department_filter > 0 ? '&department='.$department_filter : ''; ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search='.$search : ''; ?><?php echo $status_filter > 0 ? '&status='.$status_filter : ''; ?><?php echo $department_filter > 0 ? '&department='.$department_filter : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo !empty($search) ? '&search='.$search : ''; ?><?php echo $status_filter > 0 ? '&status='.$status_filter : ''; ?><?php echo $department_filter > 0 ? '&department='.$department_filter : ''; ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php else: ?>
                <div class="alert alert-info">
                    Không có dự án nào. <?php echo has_permission('admin') || has_permission('project_manager') ? '<a href="add.php" class="alert-link">Thêm dự án mới</a>.' : ''; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../../templates/footer.php';
?> 