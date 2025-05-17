<?php

/**
 * Trang đổi mật khẩu
 */

// Include config
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Kiểm tra đăng nhập
if (!is_logged_in()) {
    redirect('index.php');
}

// Lấy thông tin user hiện tại
$user_id = $_SESSION['user_id'];
$sql = "SELECT id, password FROM users WHERE id = $user_id";
$result = query($sql);

if (num_rows($result) === 0) {
    set_flash_message('Không tìm thấy thông tin người dùng', 'danger');
    redirect('dashboard.php');
}

$user = fetch_array($result);

// Xử lý đổi mật khẩu
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Kiểm tra dữ liệu
    $errors = [];

    // Kiểm tra mật khẩu hiện tại
    if (empty($current_password)) {
        $errors[] = 'Vui lòng nhập mật khẩu hiện tại';
    } elseif (!verify_password($current_password, $user['password'])) {
        $errors[] = 'Mật khẩu hiện tại không đúng';
    }

    // Kiểm tra mật khẩu mới
    if (empty($new_password)) {
        $errors[] = 'Vui lòng nhập mật khẩu mới';
    } elseif (strlen($new_password) < 6) {
        $errors[] = 'Mật khẩu mới phải có ít nhất 6 ký tự';
    } elseif ($new_password === $current_password) {
        $errors[] = 'Mật khẩu mới không được trùng với mật khẩu hiện tại';
    }

    // Kiểm tra xác nhận mật khẩu
    if (empty($confirm_password)) {
        $errors[] = 'Vui lòng xác nhận mật khẩu mới';
    } elseif ($confirm_password !== $new_password) {
        $errors[] = 'Xác nhận mật khẩu không khớp với mật khẩu mới';
    }

    // Cập nhật mật khẩu nếu không có lỗi
    if (empty($errors)) {
        $hashed_password = hash_password($new_password);
        $update_sql = "UPDATE users SET password = '$hashed_password', updated_at = NOW() WHERE id = $user_id";

        if (query($update_sql)) {
            set_flash_message('Đổi mật khẩu thành công');
            redirect('modules/users/profile.php');
        } else {
            $errors[] = 'Có lỗi xảy ra khi cập nhật mật khẩu. Vui lòng thử lại.';
        }
    }
}

// Tiêu đề trang
$page_title = "Đổi mật khẩu";

// Include header
include_once '../../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Đổi mật khẩu</h1>
        <a href="profile.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50"></i> Quay lại hồ sơ
        </a>
    </div>

    <?php
    // Hiển thị thông báo
    $flash_message = get_flash_message();
    if (!empty($flash_message)) {
        echo '<div class="alert alert-' . $flash_message['type'] . ' alert-dismissible fade show" role="alert">
            ' . $flash_message['message'] . '
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
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
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
    }
    ?>

    <div class="row">
        <div class="col-lg-6 mx-auto">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Thay đổi mật khẩu</h6>
                </div>
                <div class="card-body">
                    <form action="" method="post" id="changePasswordForm">
                        <div class="form-group mb-3">
                            <label for="current_password" class="form-label">Mật khẩu hiện tại <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                                <button class="btn btn-outline-secondary toggle-password" type="button" data-field="current_password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label for="new_password" class="form-label">Mật khẩu mới <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                <button class="btn btn-outline-secondary toggle-password" type="button" data-field="new_password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <small class="form-text text-muted">Mật khẩu phải có ít nhất 6 ký tự</small>
                        </div>

                        <div class="form-group mb-3">
                            <label for="confirm_password" class="form-label">Xác nhận mật khẩu mới <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                <button class="btn btn-outline-secondary toggle-password" type="button" data-field="confirm_password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-key"></i> Đổi mật khẩu
                            </button>
                            <a href="profile.php" class="btn btn-secondary ms-2">
                                <i class="fas fa-times"></i> Hủy
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Sau khi đổi mật khẩu thành công, bạn nên đăng nhập lại với mật khẩu mới.
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Xử lý hiện/ẩn mật khẩu
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
    });
</script>

<?php
// Include footer
include_once '../../templates/footer.php';
?>