<?php
// Include file cấu hình
require_once 'config/config.php';
require_once 'includes/functions.php';

// Nếu đã đăng nhập, chuyển hướng đến dashboard
if (is_logged_in()) {
    redirect('dashboard.php');
}

// Khởi tạo biến
$success = false;
$new_password = '';

// Xử lý form quên mật khẩu
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';

    // Validate dữ liệu
    if (empty($email)) {
        set_flash_message('Vui lòng nhập email của bạn', 'danger');
    } else {
        // Tìm người dùng theo email
        $email = escape_string($email);
        $sql = "SELECT * FROM users WHERE email = '$email'";
        $result = query($sql);

        if (num_rows($result) === 1) {
            $user = fetch_array($result);

            // Tạo mật khẩu mới ngẫu nhiên
            $new_password = generate_random_password();
            $hashed_password = hash_password($new_password);

            // Cập nhật mật khẩu mới cho người dùng
            $user_id = $user['id'];
            $update_sql = "UPDATE users SET password = '$hashed_password' WHERE id = $user_id";

            if (query($update_sql)) {
                $success = true;
            } else {
                set_flash_message('Có lỗi xảy ra khi đặt lại mật khẩu. Vui lòng thử lại sau.', 'danger');
            }
        } else {
            set_flash_message('Không tìm thấy tài khoản với email này', 'danger');
        }
    }
}

/**
 * Tạo mật khẩu ngẫu nhiên bao gồm chữ thường, chữ hoa và số
 * @param int $length Độ dài mật khẩu
 * @return string Mật khẩu ngẫu nhiên
 */
function generate_random_password($length = 10)
{
    $lowercase = 'abcdefghijklmnopqrstuvwxyz';
    $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $numbers = '0123456789';

    $all_chars = $lowercase . $uppercase . $numbers;
    $password = '';

    // Đảm bảo mật khẩu có ít nhất 1 ký tự từ mỗi loại
    $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
    $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
    $password .= $numbers[random_int(0, strlen($numbers) - 1)];

    // Điền các ký tự còn lại
    for ($i = 4; $i < $length; $i++) {
        $password .= $all_chars[random_int(0, strlen($all_chars) - 1)];
    }

    // Trộn chuỗi để không bị dự đoán
    return str_shuffle($password);
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quên mật khẩu - WorkNest</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Font: Roboto -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>

<body class="auth-page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">

                <div class="card">
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <h1 class="login-title">Quên mật khẩu</h1>
                        </div>
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <h5 class="text-white"><i class="fas fa-check-circle"></i> Thành công!</h5>
                                <p>Mật khẩu mới của bạn đã được tạo:</p>
                                <div class="password-display p-2 mb-3 border rounded text-center">
                                    <span class="fw-bold"><?php echo $new_password; ?></span>
                                </div>
                                <p class="small">Vui lòng ghi nhớ mật khẩu này và đổi mật khẩu sau khi đăng nhập.</p>
                                <div class="d-grid mt-3">
                                    <a href="index.php" class="btn btn-primary">
                                        Đăng nhập ngay
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php
                            // Hiển thị thông báo flash nếu có
                            $flash_message = get_flash_message();
                            if (!empty($flash_message)):
                            ?>
                                <div class="alert alert-<?php echo $flash_message['type']; ?> alert-dismissible fade show" role="alert">
                                    <?php echo $flash_message['message']; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>

                            <form method="post" action="">
                                <div class="mb-4">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                        <input type="email" class="form-control" id="email" name="email" placeholder="Nhập email" required>
                                    </div>
                                    <div class="form-text text-white-50 small mt-2">
                                        Nhập email của tài khoản để khôi phục mật khẩu.
                                    </div>
                                </div>

                                <div class="d-grid mb-3">
                                    <button type="submit" class="btn btn-primary">
                                        Đặt lại mật khẩu
                                    </button>
                                </div>

                                <div class="text-center">
                                    <a href="index.php" class="text-decoration-none small">
                                        <i class="fas fa-arrow-left"></i> Quay lại đăng nhập
                                    </a>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script>
        $(document).ready(function() {
            // Xóa thông báo flash sau 5 giây
            setTimeout(function() {
                $('.alert.alert-dismissible').alert('close');
            }, 5000);
        });
    </script>
</body>

</html>