<?php
/**
 * Xem chi tiết phòng ban
 */

// Include config
require_once '../../config/config.php';

// Kiểm tra đăng nhập
if (!is_logged_in()) {
    redirect('index.php');
}

// Kiểm tra ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    set_flash_message('ID phòng ban không hợp lệ', 'danger');
    redirect('modules/departments/index.php');
}

$department_id = (int)$_GET['id'];

// Lấy thông tin phòng ban
$sql = "SELECT d.*, u.name as manager_name, u.email as manager_email
        FROM departments d
        LEFT JOIN users u ON d.manager_id = u.id
        WHERE d.id = $department_id";
$result = query($sql);

if (num_rows($result) === 0) {
    set_flash_message('Không tìm thấy phòng ban', 'danger');
    redirect('modules/departments/index.php');
}

$department = fetch_array($result);

// Lấy danh sách nhân viên trong phòng ban
$staff_query = query("SELECT id, name, email, phone, role, avatar, last_login 
                     FROM users 
                     WHERE department_id = $department_id 
                     ORDER BY name");

// Lấy danh sách dự án của phòng ban
$project_query = query("SELECT p.id, p.name, p.start_date, p.end_date, p.status_id,
                        (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND department_id = $department_id) as task_count
                        FROM projects p
                        JOIN project_departments pd ON p.id = pd.project_id
                        WHERE pd.department_id = $department_id
                        ORDER BY p.end_date DESC");

// Tiêu đề trang
$page_title = "Chi tiết phòng ban: " . $department['name'];

// Include header
include_once '../../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Chi tiết phòng ban</h1>
        <div>
            <?php if (has_permission('admin') || has_permission('project_manager')): ?>
            <a href="edit.php?id=<?php echo $department_id; ?>" class="btn btn-sm btn-primary shadow-sm">
                <i class="fas fa-edit fa-sm text-white-50"></i> Chỉnh sửa
            </a>
            <?php endif; ?>
            <a href="index.php" class="btn btn-sm btn-secondary shadow-sm">
                <i class="fas fa-arrow-left fa-sm text-white-50"></i> Quay lại
            </a>
        </div>
    </div>

    <!-- Content Row -->
    <div class="row">
        <!-- Thông tin phòng ban -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Thông tin phòng ban</h6>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <tr>
                            <th>Tên phòng ban</th>
                            <td><?php echo $department['name']; ?></td>
                        </tr>
                        <tr>
                            <th>Mô tả</th>
                            <td><?php echo $department['description'] ?: 'Không có mô tả'; ?></td>
                        </tr>
                        <tr>
                            <th>Trưởng phòng</th>
                            <td>
                                <?php if (!empty($department['manager_name'])): ?>
                                    <a href="../users/view.php?id=<?php echo $department['manager_id']; ?>">
                                        <?php echo $department['manager_name']; ?> (<?php echo $department['manager_email']; ?>)
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">Chưa phân công</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Ngày tạo</th>
                            <td><?php echo format_datetime($department['created_at']); ?></td>
                        </tr>
                        <tr>
                            <th>Cập nhật lần cuối</th>
                            <td>
                                <?php echo !empty($department['updated_at']) ? format_datetime($department['updated_at']) : 'Chưa cập nhật'; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Thống kê nhanh -->
        <div class="col-lg-6">
            <div class="row">
                <!-- Số lượng thành viên -->
                <div class="col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Số lượng thành viên</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo num_rows($staff_query); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Số lượng dự án -->
                <div class="col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Số dự án tham gia</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo num_rows($project_query); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-project-diagram fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Biểu đồ hoặc thông tin thêm có thể thêm vào đây -->
            </div>
        </div>
    </div>

    <!-- Danh sách nhân viên -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Danh sách nhân viên</h6>
        </div>
        <div class="card-body">
            <?php if (num_rows($staff_query) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Tên nhân viên</th>
                                <th>Email</th>
                                <th>Số điện thoại</th>
                                <th>Vai trò</th>
                                <th>Đăng nhập cuối</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($staff = fetch_array($staff_query)): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="<?php echo BASE_URL . ($staff['avatar'] ?: 'assets/images/avatar-default.png'); ?>" 
                                                 class="rounded-circle mr-2" width="40" height="40" alt="Avatar">
                                            <?php echo $staff['name']; ?>
                                        </div>
                                    </td>
                                    <td><?php echo $staff['email']; ?></td>
                                    <td><?php echo $staff['phone'] ?: 'Chưa cập nhật'; ?></td>
                                    <td>
                                        <?php
                                        $role_labels = [
                                            'admin' => '<span class="badge badge-danger">Quản trị viên</span>',
                                            'project_manager' => '<span class="badge badge-success">Quản lý dự án</span>',
                                            'department_manager' => '<span class="badge badge-info">Trưởng phòng</span>',
                                            'staff' => '<span class="badge badge-secondary">Nhân viên</span>'
                                        ];
                                        echo $role_labels[$staff['role']];
                                        ?>
                                    </td>
                                    <td><?php echo !empty($staff['last_login']) ? format_datetime($staff['last_login']) : 'Chưa đăng nhập'; ?></td>
                                    <td>
                                        <a href="../users/view.php?id=<?php echo $staff['id']; ?>" class="btn btn-info btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-center">Không có nhân viên nào trong phòng ban này</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Danh sách dự án -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Danh sách dự án</h6>
        </div>
        <div class="card-body">
            <?php if (num_rows($project_query) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tên dự án</th>
                                <th>Ngày bắt đầu</th>
                                <th>Ngày kết thúc</th>
                                <th>Trạng thái</th>
                                <th>Số công việc</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($project = fetch_array($project_query)): ?>
                                <tr>
                                    <td><?php echo $project['id']; ?></td>
                                    <td><?php echo $project['name']; ?></td>
                                    <td><?php echo format_date($project['start_date']); ?></td>
                                    <td><?php echo format_date($project['end_date']); ?></td>
                                    <td>
                                        <?php
                                        $status_labels = [
                                            '1' => '<span class="badge badge-primary">Chuẩn bị</span>',
                                            '2' => '<span class="badge badge-warning">Đang thực hiện</span>',
                                            '3' => '<span class="badge badge-success">Hoàn thành</span>',
                                            '4' => '<span class="badge badge-danger">Tạm dừng</span>',
                                            '5' => '<span class="badge badge-dark">Đã hủy</span>'
                                        ];
                                        echo $status_labels[$project['status_id']];
                                        ?>
                                    </td>
                                    <td><?php echo $project['task_count']; ?></td>
                                    <td>
                                        <a href="../projects/view.php?id=<?php echo $project['id']; ?>" class="btn btn-info btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-center">Phòng ban này chưa tham gia dự án nào</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../../templates/footer.php';
?> 