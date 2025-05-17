<?php
// Include file cấu hình và functions
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Kiểm tra đăng nhập và quyền hạn
if (!is_logged_in()) {
    redirect('index.php');
}

// Chỉ admin mới có thể thêm người dùng
if (!has_permission('admin')) {
    set_flash_message('Bạn không có quyền thêm người dùng', 'danger');
    redirect('modules/users/');
}

// Xử lý form thêm người dùng
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Kiểm tra CSRF token
    check_csrf_token();
    
    // Lấy dữ liệu từ form
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'staff';
    $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : 'NULL';
    $phone = $_POST['phone'] ?? '';
    
    // Validate dữ liệu
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Vui lòng nhập họ tên';
    }
    
    if (empty($email)) {
        $errors[] = 'Vui lòng nhập email';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email không hợp lệ';
    } else {
        // Kiểm tra email đã tồn tại chưa
        $email = escape_string($email);
        $check_email_sql = "SELECT COUNT(*) as count FROM users WHERE email = '$email'";
        $check_email = fetch_array(query($check_email_sql));
        
        if ($check_email['count'] > 0) {
            $errors[] = 'Email đã được sử dụng, vui lòng chọn email khác';
        }
    }
    
    if (empty($password)) {
        $errors[] = 'Vui lòng nhập mật khẩu';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Mật khẩu phải có ít nhất 6 ký tự';
    } elseif ($password !== $confirm_password) {
        $errors[] = 'Mật khẩu xác nhận không khớp';
    }
    
    if (!in_array($role, ['admin', 'project_manager', 'department_manager', 'staff'])) {
        $errors[] = 'Vai trò không hợp lệ';
    }
    
    // Nếu không có lỗi, thêm người dùng mới
    if (empty($errors)) {
        $name = escape_string($name);
        $phone = escape_string($phone);
        $password_hash = hash_password($password);
        
        $sql = "INSERT INTO users (name, email, password, role, department_id, phone, created_at) 
                VALUES ('$name', '$email', '$password_hash', '$role', $department_id, '$phone', NOW())";
        
        if (query($sql)) {
            $user_id = last_id();
            
            // Kiểm tra xử lý ảnh đại diện nếu có
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
                $avatar_path = upload_file($_FILES['avatar'], 'uploads/avatars/');
                
                if ($avatar_path) {
                    $avatar_path = escape_string($avatar_path);
                    $update_avatar_sql = "UPDATE users SET avatar = '$avatar_path' WHERE id = $user_id";
                    query($update_avatar_sql);
                }
            }
            
            set_flash_message('Thêm người dùng thành công', 'success');
            redirect('modules/users/');
        } else {
            $errors[] = 'Đã xảy ra lỗi khi thêm người dùng';
        }
    }
}

// Include header
include_once '../../templates/header.php';

// Lấy danh sách phòng ban
$departments_sql = "SELECT * FROM departments ORDER BY name ASC";
$departments_result = query($departments_sql);
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../../dashboard.php">Trang chủ</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Quản lý người dùng</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Thêm người dùng mới</li>
                </ol>
            </nav>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-user-plus me-2"></i> Thêm người dùng mới
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (isset($errors) && !empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" action="" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Họ tên <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo $_POST['name'] ?? ''; ?>" required>
                                <div class="invalid-feedback">Vui lòng nhập họ tên</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo $_POST['email'] ?? ''; ?>" required>
                                <div class="invalid-feedback">Vui lòng nhập email hợp lệ</div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="password" class="form-label">Mật khẩu <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <button class="btn btn-outline-secondary password-toggle" type="button" data-target="#password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">Mật khẩu phải có ít nhất 6 ký tự</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label">Xác nhận mật khẩu <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    <button class="btn btn-outline-secondary password-toggle" type="button" data-target="#confirm_password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div id="passwordMatch"></div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="role" class="form-label">Vai trò <span class="text-danger">*</span></label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="staff" <?php echo (isset($_POST['role']) && $_POST['role'] == 'staff') ? 'selected' : ''; ?>>Nhân viên</option>
                                    <option value="department_manager" <?php echo (isset($_POST['role']) && $_POST['role'] == 'department_manager') ? 'selected' : ''; ?>>Quản lý phòng ban</option>
                                    <option value="project_manager" <?php echo (isset($_POST['role']) && $_POST['role'] == 'project_manager') ? 'selected' : ''; ?>>Quản lý dự án</option>
                                    <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                                </select>
                                <div class="invalid-feedback">Vui lòng chọn vai trò</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="department_id" class="form-label">Phòng ban</label>
                                <select class="form-select" id="department_id" name="department_id">
                                    <option value="">-- Chọn phòng ban --</option>
                                    <?php while ($department = fetch_array($departments_result)): ?>
                                        <option value="<?php echo $department['id']; ?>" <?php echo (isset($_POST['department_id']) && $_POST['department_id'] == $department['id']) ? 'selected' : ''; ?>>
                                            <?php echo $department['name']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Số điện thoại</label>
                                <input type="text" class="form-control" id="phone" name="phone" value="<?php echo $_POST['phone'] ?? ''; ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="avatar" class="form-label">Ảnh đại diện</label>
                                <input type="file" class="form-control" id="avatar" name="avatar" accept="image/*">
                                <div class="form-text">Tải lên ảnh đại diện (nếu có)</div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12 text-end">
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-1"></i> Quay lại
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Lưu
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../../templates/footer.php';
?> 