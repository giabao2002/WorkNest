<?php
/**
 * Trang chủ module báo cáo
 */

// Include config
require_once '../../config/config.php';

// Kiểm tra đăng nhập
if (!is_logged_in()) {
    redirect('index.php');
}

// Kiểm tra quyền truy cập báo cáo (chỉ admin, project manager và department manager có quyền)
if (!has_permission('admin') && !has_permission('project_manager') && !has_permission('department_manager')) {
    set_flash_message('Bạn không có quyền xem báo cáo', 'danger');
    redirect('dashboard.php');
}

// Tiêu đề trang
$page_title = "Báo cáo";

// Include header
include_once '../../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Báo cáo hệ thống</h1>
    </div>

    <!-- Danh sách báo cáo -->
    <div class="row">
        <!-- Báo cáo tiến độ dự án -->
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="h5 mb-0 font-weight-bold text-gray-800">Báo cáo dự án</div>
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Tiến độ, thống kê dự án
                            </div>
                            <p class="mt-2 small text-muted">
                                Thống kê tổng quan về tiến độ, tình trạng các dự án trong hệ thống.
                            </p>
                            <a href="project_report.php" class="btn btn-primary btn-sm mt-2">
                                <i class="fas fa-chart-bar fa-sm text-white-50"></i> Xem báo cáo
                            </a>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-project-diagram fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Báo cáo công việc -->
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="h5 mb-0 font-weight-bold text-gray-800">Báo cáo công việc</div>
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Tiến độ, hiệu suất công việc
                            </div>
                            <p class="mt-2 small text-muted">
                                Thống kê chi tiết về tình trạng công việc, công việc quá hạn, hoàn thành.
                            </p>
                            <a href="task_report.php" class="btn btn-success btn-sm mt-2">
                                <i class="fas fa-tasks fa-sm text-white-50"></i> Xem báo cáo
                            </a>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Báo cáo người dùng -->
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="h5 mb-0 font-weight-bold text-gray-800">Báo cáo nhân viên</div>
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Hiệu suất, đánh giá nhân viên
                            </div>
                            <p class="mt-2 small text-muted">
                                Báo cáo về hiệu suất làm việc, khối lượng công việc của nhân viên.
                            </p>
                            <a href="user_report.php" class="btn btn-info btn-sm mt-2">
                                <i class="fas fa-user-check fa-sm text-white-50"></i> Xem báo cáo
                            </a>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Báo cáo phòng ban -->
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="h5 mb-0 font-weight-bold text-gray-800">Báo cáo phòng ban</div>
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Hiệu suất phòng ban
                            </div>
                            <p class="mt-2 small text-muted">
                                Thống kê về hiệu suất, khối lượng công việc của từng phòng ban.
                            </p>
                            <a href="department_report.php" class="btn btn-warning btn-sm mt-2">
                                <i class="fas fa-building fa-sm text-white-50"></i> Xem báo cáo
                            </a>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-sitemap fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Báo cáo tổng hợp -->
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="h5 mb-0 font-weight-bold text-gray-800">Báo cáo tổng hợp</div>
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Tổng quan hệ thống
                            </div>
                            <p class="mt-2 small text-muted">
                                Báo cáo tổng quan chung về tình hình hoạt động của toàn bộ hệ thống.
                            </p>
                            <a href="summary_report.php" class="btn btn-danger btn-sm mt-2">
                                <i class="fas fa-chart-pie fa-sm text-white-50"></i> Xem báo cáo
                            </a>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Xuất báo cáo -->
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-secondary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="h5 mb-0 font-weight-bold text-gray-800">Xuất báo cáo</div>
                            <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">
                                Xuất báo cáo ra file
                            </div>
                            <p class="mt-2 small text-muted">
                                Xuất các loại báo cáo ra các định dạng Excel, CSV, PDF để lưu trữ.
                            </p>
                            <a href="export.php" class="btn btn-secondary btn-sm mt-2">
                                <i class="fas fa-file-export fa-sm text-white-50"></i> Xuất báo cáo
                            </a>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-file-download fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../../templates/footer.php';
?> 