<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WorkNest - Hệ thống quản lý công việc</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Font: Roboto -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body>

    <?php if (is_logged_in()): ?>
        <!-- Navbar -->
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
            <div class="container-fluid">
                <a class="navbar-brand d-flex align-items-center" href="<?php echo BASE_URL; ?>">
                    <i class="fas fa-tasks me-2"></i>
                    <span class="fw-bold">WorkNest</span>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                    aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>">
                                <i class="fas fa-home me-1"></i> Trang chủ
                            </a>
                        </li>

                        <?php if (has_permission('project_manager') || has_permission('department_manager')): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>modules/projects/">
                                    <i class="fas fa-project-diagram me-1"></i> Dự án
                                </a>
                            </li>
                        <?php endif; ?>

                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>modules/tasks/">
                                <i class="fas fa-clipboard-list me-1"></i> Công việc
                            </a>
                        </li>

                        <?php if (has_permission('project_manager') || has_permission('department_manager')): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>modules/reports/">
                                    <i class="fas fa-chart-bar me-1"></i> Báo cáo
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php if (has_permission('admin')): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>modules/users/">
                                    <i class="fas fa-users me-1"></i> Người dùng
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>modules/departments/">
                                    <i class="fas fa-building me-1"></i> Phòng ban
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>

                    <!-- Notifications Dropdown -->
                    <ul class="navbar-nav">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle position-relative" href="#" id="notificationsDropdown"
                                role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-bell"></i>
                                <?php
                                // Đếm số thông báo chưa đọc
                                $user_id = $_SESSION['user_id'];
                                $unread_count_sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = $user_id AND is_read = 0";
                                $unread_result = query($unread_count_sql);
                                $unread_data = fetch_array($unread_result);

                                if ($unread_data['count'] > 0):
                                ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                        <?php echo $unread_data['count']; ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end notifications-dropdown" aria-labelledby="notificationsDropdown">
                                <h6 class="dropdown-header">Thông báo</h6>
                                <div class="notifications-container">
                                    <?php
                                    // Lấy danh sách thông báo gần nhất
                                    $notifications_sql = "SELECT * FROM notifications WHERE user_id = $user_id ORDER BY created_at DESC LIMIT 5";
                                    $notifications_result = query($notifications_sql);

                                    if (num_rows($notifications_result) > 0) {
                                        while ($notification = fetch_array($notifications_result)) {
                                            $is_read_class = $notification['is_read'] ? 'text-muted' : 'fw-bold';
                                            echo '<a class="dropdown-item ' . $is_read_class . '" href="' . BASE_URL . 'modules/notifications/mark_read.php?id=' . $notification['id'] . '&redirect=' . urlencode($notification['link']) . '">';
                                            echo '<small class="text-muted">' . format_datetime($notification['created_at']) . '</small><br>';
                                            echo $notification['message'];
                                            echo '</a>';
                                            echo '<div class="dropdown-divider"></div>';
                                        }
                                    } else {
                                        echo '<div class="dropdown-item">Không có thông báo mới</div>';
                                    }
                                    ?>
                                </div>
                                <a class="dropdown-item text-center text-primary" href="<?php echo BASE_URL; ?>modules/notifications/">
                                    Xem tất cả
                                </a>
                            </div>
                        </li>

                        <!-- User Dropdown -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown"
                                role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <?php
                                // Lấy thông tin người dùng hiện tại
                                $current_user_id = $_SESSION['user_id'];
                                $user_sql = "SELECT * FROM users WHERE id = $current_user_id";
                                $user_result = query($user_sql);
                                $user_data = fetch_array($user_result);
                                ?>
                                <img src="<?php echo BASE_URL . $user_data['avatar']; ?>" class="rounded-circle me-1"
                                    alt="Avatar" style="width: 24px; height: 24px; object-fit: cover;">
                                <span class="d-none d-lg-inline-block"><?php echo $user_data['name']; ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li>
                                    <a class="dropdown-item" href="<?php echo BASE_URL; ?>modules/users/profile.php">
                                        <i class="fas fa-user me-1"></i> Hồ sơ cá nhân
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo BASE_URL; ?>modules/users/change_password.php">
                                        <i class="fas fa-key me-1"></i> Đổi mật khẩu
                                    </a>
                                </li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo BASE_URL; ?>logout.php">
                                        <i class="fas fa-sign-out-alt me-1"></i> Đăng xuất
                                    </a>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    <?php endif; ?>

    <!-- Main Content Container -->
    <div class="container-fluid mt-4 pb-5">
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