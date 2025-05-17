<?php
/**
 * Xóa dự án
 */

// Include config
require_once '../../config/config.php';

// Kiểm tra đăng nhập và quyền hạn
if (!is_logged_in() || (!has_permission('admin') && !has_permission('project_manager'))) {
    set_flash_message('Bạn không có quyền truy cập trang này', 'danger');
    redirect('dashboard.php');
}

// Kiểm tra tham số ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    set_flash_message('ID dự án không hợp lệ', 'danger');
    redirect('modules/projects/index.php');
}

$project_id = (int)$_GET['id'];

// Lấy thông tin dự án
$project_sql = "SELECT * FROM projects WHERE id = $project_id";
$project_result = query($project_sql);

if (num_rows($project_result) === 0) {
    set_flash_message('Không tìm thấy dự án', 'danger');
    redirect('modules/projects/index.php');
}

// Kiểm tra xem dự án đã có công việc hay chưa
$task_count_sql = "SELECT COUNT(*) as count FROM tasks WHERE project_id = $project_id";
$task_count_result = query($task_count_sql);
$task_count = fetch_array($task_count_result)['count'];

// Xác nhận xóa
if (isset($_GET['confirm']) && $_GET['confirm'] == 1) {
    // Xóa các công việc liên quan đến dự án
    query("DELETE FROM tasks WHERE project_id = $project_id");
    
    // Xóa các bản ghi phân công phòng ban
    query("DELETE FROM project_departments WHERE project_id = $project_id");
    
    // Xóa dự án
    $delete_result = query("DELETE FROM projects WHERE id = $project_id");
    
    if ($delete_result) {
        set_flash_message('Xóa dự án thành công');
    } else {
        set_flash_message('Có lỗi xảy ra khi xóa dự án', 'danger');
    }
    
    redirect('modules/projects/index.php');
    exit;
}

// Tiêu đề trang
$page_title = "Xóa dự án";

// Include header
include_once '../../templates/header.php';

// Lấy thông tin dự án
$project = fetch_array($project_result);
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Xác nhận xóa dự án</h1>
        <a href="index.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50"></i> Quay lại
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Thông tin dự án</h6>
        </div>
        <div class="card-body">
            <div class="alert alert-danger">
                <h5 class="alert-heading">Cảnh báo!</h5>
                <p>Bạn đang chuẩn bị xóa dự án <strong><?php echo $project['name']; ?></strong>.</p>
                <hr>
                <p class="mb-0">
                    <?php if ($task_count > 0): ?>
                        Dự án này hiện có <strong><?php echo $task_count; ?> công việc</strong> liên quan. Xóa dự án này sẽ xóa tất cả công việc và dữ liệu liên quan.
                    <?php else: ?>
                        Dự án này chưa có công việc nào.
                    <?php endif; ?>
                </p>
                <p class="mb-0 mt-2">Hành động này không thể khôi phục. Bạn có chắc chắn muốn xóa?</p>
            </div>
            
            <div class="table-responsive">
                <table class="table table-bordered">
                    <tr>
                        <th width="200">ID</th>
                        <td><?php echo $project['id']; ?></td>
                    </tr>
                    <tr>
                        <th>Tên dự án</th>
                        <td><?php echo $project['name']; ?></td>
                    </tr>
                    <tr>
                        <th>Ngày bắt đầu</th>
                        <td><?php echo format_date($project['start_date']); ?></td>
                    </tr>
                    <tr>
                        <th>Ngày kết thúc</th>
                        <td><?php echo format_date($project['end_date']); ?></td>
                    </tr>
                    <tr>
                        <th>Ngày tạo</th>
                        <td><?php echo format_datetime($project['created_at']); ?></td>
                    </tr>
                </table>
            </div>
            
            <div class="mt-4 text-center">
                <a href="view.php?id=<?php echo $project_id; ?>" class="btn btn-secondary btn-lg">
                    <i class="fas fa-times"></i> Hủy
                </a>
                <a href="delete.php?id=<?php echo $project_id; ?>&confirm=1" class="btn btn-danger btn-lg">
                    <i class="fas fa-trash"></i> Xác nhận xóa
                </a>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../../templates/footer.php';
?> 