<?php
// Include file cấu hình
require_once 'config/config.php';
require_once 'includes/functions.php';

// Nếu đã đăng nhập, chuyển hướng đến dashboard
if (is_logged_in()) {
    redirect('dashboard.php');
}

// Xử lý đăng nhập
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']) ? true : false;

    // Validate dữ liệu
    if (empty($email) || empty($password)) {
        set_flash_message('Vui lòng nhập đầy đủ email và mật khẩu', 'danger');
    } else {
        // Tìm người dùng theo email
        $email = escape_string($email);
        $sql = "SELECT * FROM users WHERE email = '$email'";
        $result = query($sql);

        if (num_rows($result) === 1) {
            $user = fetch_array($result);

            // Kiểm tra mật khẩu
            if (verify_password($password, $user['password'])) {
                // Đăng nhập thành công
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];

                // Cập nhật thời gian đăng nhập
                $user_id = $user['id'];
                $sql = "UPDATE users SET last_login = NOW() WHERE id = $user_id";
                query($sql);

                // Chuyển hướng đến dashboard
                redirect('dashboard.php');
            } else {
                set_flash_message('Mật khẩu không đúng', 'danger');
            }
        } else {
            set_flash_message('Không tìm thấy tài khoản với email này', 'danger');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - WorkNest</title>
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

                <div class="card">
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <h1 class="login-title">Đăng nhập</h1>
                        </div>
                        <form method="post" action="">
                            <div class="mb-3">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" placeholder="Nhập email" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" placeholder="Nhập mật khẩu" required>
                                    <button class="btn btn-outline-light password-toggle" type="button" data-target="#password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                    <label class="form-check-label" for="remember">Ghi nhớ tôi</label>
                                </div>
                                <a href="forgot_password.php" class="text-decoration-none small">Quên mật khẩu?</a>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    Đăng nhập
                                </button>
                            </div>
                        </form>
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
            // Hiện/ẩn mật khẩu
            $('.password-toggle').on('click', function() {
                var input = $($(this).data('target'));
                var icon = $(this).find('i');
                
                if (input.attr('type') === 'password') {
                    input.attr('type', 'text');
                    icon.removeClass('fa-eye').addClass('fa-eye-slash');
                } else {
                    input.attr('type', 'password');
                    icon.removeClass('fa-eye-slash').addClass('fa-eye');
                }
            });
            
            // Xóa thông báo flash sau 5 giây
            setTimeout(function() {
                $('.alert.alert-dismissible').alert('close');
            }, 5000);
        });
    </script>
</body>

</html>