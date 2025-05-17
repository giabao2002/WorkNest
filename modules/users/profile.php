<?php
/**
 * Trang hồ sơ cá nhân
 */

// Include config
require_once '../../config/config.php';

// Kiểm tra đăng nhập
if (!is_logged_in()) {
    redirect('index.php');
}
// Lấy thông tin user hiện tại
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id = $user_id";
$result = query($sql);

if (num_rows($result) === 0) {
    set_flash_message('Không tìm thấy thông tin người dùng', 'danger');
    redirect('dashboard.php');
}

$user = fetch_array($result);

// Xử lý cập nhật thông tin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = escape_string($_POST['name']);
    $email = escape_string($_POST['email']);
    $phone = escape_string($_POST['phone']);
    
    // Kiểm tra dữ liệu
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Vui lòng nhập họ tên';
    }
    
    if (empty($email)) {
        $errors[] = 'Vui lòng nhập email';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email không hợp lệ';
    } else {
        // Kiểm tra email đã tồn tại chưa (nếu thay đổi email)
        if ($email !== $user['email']) {
            $check_email_sql = "SELECT id FROM users WHERE email = '$email' AND id != $user_id";
            $check_email_result = query($check_email_sql);
            
            if (num_rows($check_email_result) > 0) {
                $errors[] = 'Email này đã được sử dụng bởi tài khoản khác';
            }
        }
    }
    
    // Xử lý upload avatar (nếu có)
    $new_avatar = $user['avatar']; // Giữ nguyên avatar cũ nếu không có upload mới
    
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        if (!in_array($_FILES['avatar']['type'], $allowed_types)) {
            $errors[] = 'Avatar phải là file ảnh (jpg, png, gif)';
        } elseif ($_FILES['avatar']['size'] > $max_size) {
            $errors[] = 'Avatar không được vượt quá 2MB';
        } else {
            // Tạo thư mục lưu trữ avatar nếu chưa có
            $upload_dir = 'assets/images/avatars/';
            $full_upload_dir = ROOT_PATH . $upload_dir;
            
            if (!is_dir($full_upload_dir)) {
                mkdir($full_upload_dir, 0755, true);
            }
            
            // Tạo tên file ngẫu nhiên để tránh trùng lặp
            $file_extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $file_name = 'avatar_' . $user_id . '_' . uniqid() . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;
            $full_file_path = $full_upload_dir . $file_name;
            
            // Upload file
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $full_file_path)) {
                // Xóa avatar cũ nếu không phải avatar mặc định
                if ($user['avatar'] !== 'assets/images/avatar-default.png' && file_exists(ROOT_PATH . $user['avatar'])) {
                    unlink(ROOT_PATH . $user['avatar']);
                }
                
                $new_avatar = $file_path;
            } else {
                $errors[] = 'Không thể tải lên avatar. Vui lòng thử lại sau.';
            }
        }
    }
    
    // Cập nhật thông tin nếu không có lỗi
    if (empty($errors)) {
        $update_sql = "UPDATE users SET 
                      name = '$name', 
                      email = '$email', 
                      phone = '$phone', 
                      avatar = '$new_avatar',
                      updated_at = NOW() 
                      WHERE id = $user_id";
        
        if (query($update_sql)) {
            // Cập nhật lại thông tin session
            $_SESSION['user_name'] = $name;
            
            set_flash_message('Cập nhật thông tin thành công');
            redirect('modules/users/profile.php');
        } else {
            $errors[] = 'Có lỗi xảy ra khi cập nhật thông tin. Vui lòng thử lại.';
        }
    }
}

// Lấy thông tin phòng ban
$department_name = 'Chưa phân công';
if ($user['department_id']) {
    $dept_sql = "SELECT name FROM departments WHERE id = {$user['department_id']}";
    $dept_result = query($dept_sql);
    
    if (num_rows($dept_result) > 0) {
        $dept = fetch_array($dept_result);
        $department_name = $dept['name'];
    }
}

// Tiêu đề trang
$page_title = "Hồ sơ cá nhân";

// Include header
include_once '../../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Hồ sơ cá nhân</h1>
    </div>

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

    // Hiển thị lỗi nếu có
    if (!empty($errors)) {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0">';
        foreach ($errors as $error) {
            echo '<li>' . $error . '</li>';
        }
        echo '</ul>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>';
    }
    ?>

    <div class="row">
        <div class="col-xl-4 col-lg-5">
            <!-- Thông tin cá nhân -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Thông tin cá nhân</h6>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <img class="img-profile rounded-circle" src="<?php echo BASE_URL . $user['avatar']; ?>" width="150" height="150">
                        <h5 class="mt-3"><?php echo $user['name']; ?></h5>
                        <span class="badge badge-primary"><?php echo ucfirst($user['role']); ?></span>
                    </div>
                    
                    <div class="list-group">
                        <div class="list-group-item">
                            <div class="row">
                                <div class="col-md-4"><strong>Phòng ban:</strong></div>
                                <div class="col-md-8"><?php echo $department_name; ?></div>
                            </div>
                        </div>
                        <div class="list-group-item">
                            <div class="row">
                                <div class="col-md-4"><strong>Email:</strong></div>
                                <div class="col-md-8"><?php echo $user['email']; ?></div>
                            </div>
                        </div>
                        <div class="list-group-item">
                            <div class="row">
                                <div class="col-md-4"><strong>Điện thoại:</strong></div>
                                <div class="col-md-8"><?php echo $user['phone'] ?: 'Chưa cập nhật'; ?></div>
                            </div>
                        </div>
                        <div class="list-group-item">
                            <div class="row">
                                <div class="col-md-4"><strong>Ngày tham gia:</strong></div>
                                <div class="col-md-8"><?php echo format_date($user['created_at']); ?></div>
                            </div>
                        </div>
                        <div class="list-group-item">
                            <div class="row">
                                <div class="col-md-4"><strong>Đăng nhập cuối:</strong></div>
                                <div class="col-md-8"><?php echo $user['last_login'] ? format_datetime($user['last_login']) : 'Chưa có dữ liệu'; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-8 col-lg-7">
            <!-- Chỉnh sửa thông tin cá nhân -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Chỉnh sửa thông tin</h6>
                </div>
                <div class="card-body">
                    <form action="" method="post" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="name">Họ tên <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required 
                                   value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : (isset($user['name']) && $user['name'] !== null ? htmlspecialchars($user['name']) : ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" required 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : (isset($user['email']) && $user['email'] !== null ? htmlspecialchars($user['email']) : ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Điện thoại</label>
                            <input type="text" class="form-control" id="phone" name="phone" 
                                   value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : (isset($user['phone']) && $user['phone'] !== null ? htmlspecialchars($user['phone']) : ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="avatar">Avatar</label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="avatar" name="avatar" accept="image/*">
                                <label class="custom-file-label" for="avatar">Chọn file...</label>
                            </div>
                            <small class="form-text text-muted">Kích thước tối đa: 2MB. Định dạng: JPG, PNG, GIF</small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Lưu thay đổi
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Liên kết đổi mật khẩu -->
            <div class="alert alert-info">
                <i class="fas fa-key"></i> Bạn muốn thay đổi mật khẩu? 
                <a href="change_password.php" class="alert-link">Nhấn vào đây</a> để đổi mật khẩu.
            </div>
        </div>
    </div>
</div>

<script>
// Hiển thị tên file khi chọn avatar
$(document).ready(function() {
    // Hiển thị tên file khi chọn
    $('.custom-file-input').on('change', function() {
        var fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').html(fileName);
    });
});
</script>

<?php
// Include footer
include_once '../../templates/footer.php';
?> 