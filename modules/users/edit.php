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
$sql = "SELECT * FROM users WHERE id = $user_id";
$result = query($sql);

if (num_rows($result) == 0) {
    set_flash_message('Không tìm thấy người dùng', 'danger');
    redirect('modules/users/');
}

$user = fetch_array($result);

// Kiểm tra quyền truy cập: chỉ admin và chủ tài khoản có thể chỉnh sửa
if (!has_permission('admin') && $_SESSION['user_id'] != $user_id) {
    set_flash_message('Bạn không có quyền chỉnh sửa thông tin người dùng này', 'danger');
    redirect('dashboard.php');
}

// Xử lý form chỉnh sửa
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lấy dữ liệu từ form
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    
    // Admin có thể thay đổi role và department
    $role = has_permission('admin') ? ($_POST['role'] ?? $user['role']) : $user['role'];
    $department_id = has_permission('admin') ? (!empty($_POST['department_id']) ? (int)$_POST['department_id'] : 'NULL') : $user['department_id'];
    
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
        // Kiểm tra email đã tồn tại chưa (ngoại trừ email hiện tại)
        $email = escape_string($email);
        $check_email_sql = "SELECT COUNT(*) as count FROM users WHERE email = '$email' AND id != $user_id";
        $check_email = fetch_array(query($check_email_sql));
        
        if ($check_email['count'] > 0) {
            $errors[] = 'Email đã được sử dụng, vui lòng chọn email khác';
        }
    }
    
    // Nếu nhập mật khẩu mới thì xác thực
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (!empty($password)) {
        if (strlen($password) < 6) {
            $errors[] = 'Mật khẩu phải có ít nhất 6 ký tự';
        } elseif ($password !== $confirm_password) {
            $errors[] = 'Mật khẩu xác nhận không khớp';
        }
    }
    
    // Nếu không có lỗi, cập nhật thông tin người dùng
    if (empty($errors)) {
        $name = escape_string($name);
        $phone = escape_string($phone);
        
        // Xây dựng câu SQL cập nhật
        $update_sql = "UPDATE users SET 
                        name = '$name', 
                        email = '$email', 
                        phone = '$phone',
                        updated_at = NOW()";
        
        // Thêm password nếu có thay đổi
        if (!empty($password)) {
            $password_hash = hash_password($password);
            $update_sql .= ", password = '$password_hash'";
        }
        
        // Thêm role và department_id nếu là admin
        if (has_permission('admin')) {
            $update_sql .= ", role = '$role', department_id = $department_id";
        }
        
        $update_sql .= " WHERE id = $user_id";
        
        if (query($update_sql)) {
            // Kiểm tra xử lý ảnh đại diện nếu có
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
                $avatar_path = upload_file($_FILES['avatar'], 'uploads/avatars/');
                
                if ($avatar_path) {
                    $avatar_path = escape_string($avatar_path);
                    $update_avatar_sql = "UPDATE users SET avatar = '$avatar_path' WHERE id = $user_id";
                    query($update_avatar_sql);
                    
                    // Xóa avatar cũ nếu không phải mặc định
                    if ($user['avatar'] != 'assets/images/avatar-default.png' && file_exists('../../' . $user['avatar'])) {
                        unlink('../../' . $user['avatar']);
                    }
                }
            }
            
            set_flash_message('Cập nhật thông tin người dùng thành công', 'success');
            
            // Nếu người dùng cập nhật chính mình, cập nhật session
            if ($_SESSION['user_id'] == $user_id) {
                $_SESSION['user_name'] = $name;
                
                // Nếu là admin và thay đổi role của chính mình
                if (has_permission('admin') && $_SESSION['user_role'] != $role) {
                    $_SESSION['user_role'] = $role;
                }
            }
            
            redirect('modules/users/view.php?id=' . $user_id);
        } else {
            $errors[] = 'Đã xảy ra lỗi khi cập nhật thông tin người dùng';
        }
    }
}

// Lấy danh sách phòng ban
$departments_sql = "SELECT * FROM departments ORDER BY name ASC";
$departments_result = query($departments_sql);

// Include header
include_once '../../templates/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../../dashboard.php">Trang chủ</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Quản lý người dùng</a></li>
                    <li class="breadcrumb-item"><a href="view.php?id=<?php echo $user_id; ?>">Thông tin người dùng</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Chỉnh sửa người dùng</li>
                </ol>
            </nav>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-user-edit me-2"></i> Chỉnh sửa người dùng
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
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Họ tên <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : htmlspecialchars($user['name']); ?>" required>
                                <div class="invalid-feedback">Vui lòng nhập họ tên</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : htmlspecialchars($user['email']); ?>" required>
                                <div class="invalid-feedback">Vui lòng nhập email hợp lệ</div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="password" class="form-label">Mật khẩu mới</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password">
                                    <button class="btn btn-outline-secondary toggle-password" type="button" data-field="password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">Để trống nếu không muốn thay đổi mật khẩu</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label">Xác nhận mật khẩu mới</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                    <button class="btn btn-outline-secondary toggle-password" type="button" data-field="confirm_password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (has_permission('admin')): ?>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="role" class="form-label">Vai trò <span class="text-danger">*</span></label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="staff" <?php echo ($user['role'] == 'staff') ? 'selected' : ''; ?>>Nhân viên</option>
                                    <option value="department_manager" <?php echo ($user['role'] == 'department_manager') ? 'selected' : ''; ?>>Quản lý phòng ban</option>
                                    <option value="project_manager" <?php echo ($user['role'] == 'project_manager') ? 'selected' : ''; ?>>Quản lý dự án</option>
                                    <option value="admin" <?php echo ($user['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="department_id" class="form-label">Phòng ban</label>
                                <select class="form-select" id="department_id" name="department_id">
                                    <option value="">-- Chọn phòng ban --</option>
                                    <?php mysqli_data_seek($departments_result, 0); // Reset con trỏ kết quả ?>
                                    <?php while ($department = fetch_array($departments_result)): ?>
                                        <option value="<?php echo $department['id']; ?>" <?php echo ($user['department_id'] == $department['id']) ? 'selected' : ''; ?>>
                                            <?php echo $department['name']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Số điện thoại</label>
                                <input type="text" class="form-control" id="phone" name="phone" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : htmlspecialchars($user['phone'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="avatar" class="form-label">Ảnh đại diện</label>
                                <input type="file" class="form-control" id="avatar" name="avatar" accept="image/*">
                                <div class="form-text">Tải lên ảnh đại diện mới (nếu muốn thay đổi)</div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Ảnh đại diện hiện tại</label>
                                <div>
                                    <img src="<?php echo BASE_URL . $user['avatar']; ?>" alt="Avatar" class="img-thumbnail" style="width: 100px; height: 100px; object-fit: cover;">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12 text-end">
                                <a href="view.php?id=<?php echo $user_id; ?>" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-1"></i> Quay lại
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Lưu thay đổi
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Hiện/ẩn mật khẩu
    document.querySelectorAll('.toggle-password').forEach(function(button) {
        button.addEventListener('click', function() {
            const fieldId = this.getAttribute('data-field');
            const passwordField = document.getElementById(fieldId);
            const icon = this.querySelector('i');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });
    
    // Kiểm tra mật khẩu xác nhận trùng khớp
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    
    function checkPasswordMatch() {
        if (password.value && confirmPassword.value) {
            if (password.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Mật khẩu xác nhận không khớp');
            } else {
                confirmPassword.setCustomValidity('');
            }
        }
    }
    
    password.addEventListener('change', checkPasswordMatch);
    confirmPassword.addEventListener('keyup', checkPasswordMatch);
    
    // Xác thực form
    const form = document.querySelector('form');
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        form.classList.add('was-validated');
    });
});
</script>

<?php
// Include footer
include_once '../../templates/footer.php';
?> 