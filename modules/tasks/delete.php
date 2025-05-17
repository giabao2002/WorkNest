<?php
/**
 * Xóa công việc
 */

// Include config
require_once '../../config/config.php';

// Kiểm tra đăng nhập
if (!is_logged_in()) {
    redirect('index.php');
}

// Kiểm tra tham số ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    set_flash_message('ID công việc không hợp lệ', 'danger');
    redirect('modules/tasks/index.php');
}

$task_id = (int)$_GET['id'];

// Lấy thông tin công việc
$task_sql = "SELECT t.*, 
            p.name as project_name, p.manager_id as project_manager_id
            FROM tasks t
            LEFT JOIN projects p ON t.project_id = p.id
            WHERE t.id = $task_id";
$task_result = query($task_sql);

if (num_rows($task_result) === 0) {
    set_flash_message('Không tìm thấy công việc', 'danger');
    redirect('modules/tasks/index.php');
}

// Lấy thông tin công việc
$task = fetch_array($task_result);

// Kiểm tra quyền xóa công việc (chỉ admin, project manager, hoặc người tạo công việc có quyền xóa)
$user_id = $_SESSION['user_id'];
$can_delete = has_permission('admin') || has_permission('project_manager');

if (!$can_delete) {
    // Người tạo có quyền xóa
    if ($task['assigned_by'] == $user_id) {
        $can_delete = true;
    }
    
    // Quản lý dự án có quyền xóa
    if ($task['project_manager_id'] == $user_id) {
        $can_delete = true;
    }
}

if (!$can_delete) {
    set_flash_message('Bạn không có quyền xóa công việc này', 'danger');
    redirect('modules/tasks/view.php?id=' . $task_id);
}

// Xác nhận xóa
if (isset($_GET['confirm']) && $_GET['confirm'] == 1) {
    // Kiểm tra xem công việc có công việc con không
    $check_subtasks = query("SELECT COUNT(*) as count FROM tasks WHERE parent_id = $task_id");
    $subtask_count = fetch_array($check_subtasks)['count'];
    
    if ($subtask_count > 0) {
        // Nếu có công việc con, cập nhật các công việc con (đặt parent_id về NULL)
        query("UPDATE tasks SET parent_id = NULL, updated_at = NOW() WHERE parent_id = $task_id");
    }
    
    // Xóa các ghi chú liên quan
    query("DELETE FROM task_comments WHERE task_id = $task_id");
    
    // Xóa công việc
    $delete_result = query("DELETE FROM tasks WHERE id = $task_id");
    
    if ($delete_result) {
        set_flash_message('Xóa công việc thành công');
        
        // Chuyển về trang công việc của dự án nếu đang ở trong một dự án cụ thể
        if (isset($_GET['project_id']) && !empty($_GET['project_id'])) {
            redirect('modules/tasks/index.php?project_id=' . (int)$_GET['project_id']);
        } else {
            redirect('modules/tasks/index.php');
        }
    } else {
        set_flash_message('Có lỗi xảy ra khi xóa công việc', 'danger');
        redirect('modules/tasks/view.php?id=' . $task_id);
    }
    exit;
}

// Tiêu đề trang
$page_title = "Xác nhận xóa công việc";

// Include header
include_once '../../templates/header.php';

// Kiểm tra công việc con
$subtasks_sql = "SELECT COUNT(*) as count FROM tasks WHERE parent_id = $task_id";
$subtasks_result = query($subtasks_sql);
$subtask_count = fetch_array($subtasks_result)['count'];
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Xác nhận xóa công việc</h1>
        <a href="view.php?id=<?php echo $task_id; ?>" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50"></i> Quay lại
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Xác nhận xóa</h6>
        </div>
        <div class="card-body">
            <div class="alert alert-danger">
                <h5 class="alert-heading">Cảnh báo!</h5>
                <p>Bạn đang chuẩn bị xóa công việc <strong><?php echo $task['title']; ?></strong>.</p>
                <hr>
                <?php if ($subtask_count > 0): ?>
                    <p class="mb-0">
                        <i class="fas fa-exclamation-triangle"></i> 
                        Công việc này hiện có <strong><?php echo $subtask_count; ?> công việc con</strong>.
                        Nếu xóa, các công việc con sẽ trở thành công việc độc lập.
                    </p>
                <?php endif; ?>
                <p class="mb-0 mt-2">Hành động này không thể khôi phục. Bạn có chắc chắn muốn xóa?</p>
            </div>
            
            <div class="table-responsive">
                <table class="table table-bordered">
                    <tr>
                        <th width="200">ID</th>
                        <td><?php echo $task['id']; ?></td>
                    </tr>
                    <tr>
                        <th>Tên công việc</th>
                        <td><?php echo $task['title']; ?></td>
                    </tr>
                    <tr>
                        <th>Dự án</th>
                        <td><?php echo $task['project_name']; ?></td>
                    </tr>
                    <tr>
                        <th>Trạng thái</th>
                        <td>
                            <?php 
                            $status_list = [
                                1 => ['name' => 'Chưa bắt đầu', 'color' => 'primary'],
                                2 => ['name' => 'Đang thực hiện', 'color' => 'warning'],
                                3 => ['name' => 'Hoàn thành', 'color' => 'success'],
                                4 => ['name' => 'Tạm dừng', 'color' => 'danger'],
                                5 => ['name' => 'Đã hủy', 'color' => 'secondary']
                            ];
                            ?>
                            <span class="badge badge-<?php echo $status_list[$task['status_id']]['color']; ?>">
                                <?php echo $status_list[$task['status_id']]['name']; ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Tiến độ</th>
                        <td><?php echo $task['progress']; ?>%</td>
                    </tr>
                    <tr>
                        <th>Hạn hoàn thành</th>
                        <td><?php echo format_date($task['due_date']); ?></td>
                    </tr>
                </table>
            </div>
            
            <div class="mt-4 text-center">
                <a href="view.php?id=<?php echo $task_id; ?>" class="btn btn-secondary btn-lg">
                    <i class="fas fa-times"></i> Hủy
                </a>
                <a href="delete.php?id=<?php echo $task_id; ?>&confirm=1<?php echo isset($_GET['project_id']) ? '&project_id='.(int)$_GET['project_id'] : ''; ?>" 
                   class="btn btn-danger btn-lg ml-2">
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