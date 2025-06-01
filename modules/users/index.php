<?php
// Include file cấu hình và functions
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Kiểm tra đăng nhập và quyền hạn
if (!is_logged_in()) {
    redirect('index.php');
}

// Chỉ admin và quản lý dự án có thể xem toàn bộ danh sách người dùng
if (!has_permission('admin') && !has_permission('project_manager')) {
    set_flash_message('Bạn không có quyền truy cập trang này', 'danger');
    redirect('dashboard.php');
}

// Phân trang
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Xử lý xóa người dùng
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    
    // Kiểm tra chỉ admin mới có thể xóa người dùng
    if (!has_permission('admin')) {
        set_flash_message('Bạn không có quyền xóa người dùng', 'danger');
        redirect('modules/users/');
    }
    
    // Không thể tự xóa tài khoản đang đăng nhập
    if ($user_id == $_SESSION['user_id']) {
        set_flash_message('Không thể xóa tài khoản đang sử dụng', 'danger');
        redirect('modules/users/');
    }
    
    // Kiểm tra người dùng có phải quản lý phòng ban không
    $department_check_sql = "SELECT COUNT(*) as count FROM departments WHERE manager_id = $user_id";
    $department_check = fetch_array(query($department_check_sql));
    
    if ($department_check['count'] > 0) {
        set_flash_message('Không thể xóa người dùng đang là quản lý phòng ban', 'danger');
        redirect('modules/users/');
    }
    
    // Kiểm tra người dùng có phải quản lý dự án không
    $project_check_sql = "SELECT COUNT(*) as count FROM projects WHERE manager_id = $user_id";
    $project_check = fetch_array(query($project_check_sql));
    
    if ($project_check['count'] > 0) {
        set_flash_message('Không thể xóa người dùng đang là quản lý dự án', 'danger');
        redirect('modules/users/');
    }
    
    // Xóa người dùng
    $delete_sql = "DELETE FROM users WHERE id = $user_id";
    if (query($delete_sql)) {
        set_flash_message('Xóa người dùng thành công', 'success');
    } else {
        set_flash_message('Xóa người dùng thất bại', 'danger');
    }
    
    redirect('modules/users/');
}

// Include header
include_once '../../templates/header.php';

// Lấy danh sách người dùng
$search = isset($_GET['search']) ? escape_string($_GET['search']) : '';
$filter_role = isset($_GET['role']) ? escape_string($_GET['role']) : '';
$filter_department = isset($_GET['department']) ? (int)$_GET['department'] : 0;

// Xây dựng câu truy vấn
$base_sql = "FROM users u 
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE 1=1";

// Thêm điều kiện tìm kiếm nếu có
if (!empty($search)) {
    $base_sql .= " AND (u.name LIKE '%$search%' OR u.email LIKE '%$search%')";
}

// Thêm điều kiện lọc theo vai trò
if (!empty($filter_role)) {
    $base_sql .= " AND u.role = '$filter_role'";
}

// Thêm điều kiện lọc theo phòng ban
if ($filter_department > 0) {
    $base_sql .= " AND u.department_id = $filter_department";
}

// Đếm tổng số người dùng theo điều kiện
$count_sql = "SELECT COUNT(*) as total " . $base_sql;
$count_result = query($count_sql);
$count_row = fetch_array($count_result);
$total = $count_row['total'];
$total_pages = ceil($total / $limit);

// Lấy danh sách người dùng có phân trang
$sql = "SELECT u.*, d.name as department_name " . $base_sql . " ORDER BY u.id ASC LIMIT $offset, $limit";
$result = query($sql);

// Lấy danh sách phòng ban cho bộ lọc
$departments_sql = "SELECT * FROM departments ORDER BY name ASC";
$departments_result = query($departments_sql);
$departments = [];
while ($department = fetch_array($departments_result)) {
    $departments[$department['id']] = $department;
}
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2 class="mb-0">
                <i class="fas fa-users me-2"></i> Quản lý người dùng
            </h2>
        </div>
        <div class="col-md-6 text-md-end">
            <?php if (has_permission('admin')): ?>
            <a href="add.php" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Thêm người dùng
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bộ lọc và tìm kiếm -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" action="" class="row g-3">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" name="search" placeholder="Tìm theo tên hoặc email..." value="<?php echo $search; ?>">
                    </div>
                </div>
                
                <div class="col-md-3">
                    <select name="role" class="form-select">
                        <option value="">-- Tất cả vai trò --</option>
                        <option value="admin" <?php echo $filter_role == 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="project_manager" <?php echo $filter_role == 'project_manager' ? 'selected' : ''; ?>>Quản lý dự án</option>
                        <option value="department_manager" <?php echo $filter_role == 'department_manager' ? 'selected' : ''; ?>>Quản lý phòng ban</option>
                        <option value="staff" <?php echo $filter_role == 'staff' ? 'selected' : ''; ?>>Nhân viên</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <select name="department" class="form-select">
                        <option value="0">-- Tất cả phòng ban --</option>
                        <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo $dept['id']; ?>" <?php echo $filter_department == $dept['id'] ? 'selected' : ''; ?>>
                            <?php echo $dept['name']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Lọc</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Danh sách người dùng -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Họ tên</th>
                            <th scope="col">Email</th>
                            <th scope="col">Vai trò</th>
                            <th scope="col">Phòng ban</th>
                            <th scope="col">Đăng nhập lần cuối</th>
                            <th scope="col">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (num_rows($result) > 0): ?>
                            <?php while ($user = fetch_array($result)): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center justify-content-evenly">
                                            <img src="<?php echo BASE_URL . $user['avatar']; ?>" alt="Avatar" class="rounded-circle me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                            <?php echo $user['name']; ?>
                                        </div>
                                    </td>
                                    <td><?php echo $user['email']; ?></td>
                                    <td>
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
                                        <span class="badge bg-<?php echo $role_badge_class; ?>"><?php echo $role_text; ?></span>
                                    </td>
                                    <td><?php echo $user['department_name'] ?? 'Chưa phân công'; ?></td>
                                    <td><?php echo $user['last_login'] ? format_datetime($user['last_login']) : 'Chưa đăng nhập'; ?></td>
                                    <td>
                                        <a href="view.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="Xem chi tiết">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if (has_permission('admin')): ?>
                                        <a href="edit.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="Chỉnh sửa">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <a href="index.php?action=delete&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-danger delete-confirm" data-bs-toggle="tooltip" title="Xóa">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                        <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">Không tìm thấy người dùng nào</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Phân trang -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo !empty($search) ? '&search='.$search : ''; ?><?php echo !empty($filter_role) ? '&role='.$filter_role : ''; ?><?php echo $filter_department > 0 ? '&department='.$filter_department : ''; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search='.$search : ''; ?><?php echo !empty($filter_role) ? '&role='.$filter_role : ''; ?><?php echo $filter_department > 0 ? '&department='.$filter_department : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo !empty($search) ? '&search='.$search : ''; ?><?php echo !empty($filter_role) ? '&role='.$filter_role : ''; ?><?php echo $filter_department > 0 ? '&department='.$filter_department : ''; ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../../templates/footer.php';
?> 