<?php
/**
 * Xem chi tiết công việc
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
            p.name as project_name, p.manager_id as project_manager_id,
            d.name as department_name, d.manager_id as department_manager_id,
            u1.name as assigned_name, u1.email as assigned_email, u1.avatar as assigned_avatar,
            u2.name as assigned_by_name, u2.email as assigned_by_email, u2.avatar as assigned_by_avatar
            FROM tasks t
            LEFT JOIN projects p ON t.project_id = p.id
            LEFT JOIN departments d ON t.department_id = d.id
            LEFT JOIN users u1 ON t.assigned_to = u1.id
            LEFT JOIN users u2 ON t.assigned_by = u2.id
            WHERE t.id = $task_id";
$task_result = query($task_sql);

if (num_rows($task_result) === 0) {
    set_flash_message('Không tìm thấy công việc', 'danger');
    redirect('modules/tasks/index.php');
}

// Lấy thông tin công việc
$task = fetch_array($task_result);

// Kiểm tra quyền xem chi tiết công việc
$user_id = $_SESSION['user_id'];
$can_view = has_permission('admin') || has_permission('project_manager');

if (!$can_view) {
    // Người tạo hoặc người được giao việc có quyền xem
    if ($task['assigned_by'] == $user_id || $task['assigned_to'] == $user_id) {
        $can_view = true;
    }
    
    // Quản lý dự án có quyền xem
    if ($task['project_manager_id'] == $user_id) {
        $can_view = true;
    }
    
    // Quản lý phòng ban của người được giao việc có quyền xem
    if (has_permission('department_manager') && $task['department_manager_id'] == $user_id) {
        $can_view = true;
    }
    
    if (!$can_view) {
        set_flash_message('Bạn không có quyền xem chi tiết công việc này', 'danger');
        redirect('modules/tasks/index.php');
    }
}

// Kiểm tra quyền cập nhật tiến độ
$can_update_progress = has_permission('admin') || has_permission('project_manager') || 
                       $task['assigned_by'] == $user_id || $task['assigned_to'] == $user_id ||
                       (has_permission('department_manager') && $task['department_manager_id'] == $user_id);

// Kiểm tra quyền sửa công việc
$can_edit = has_permission('admin') || has_permission('project_manager') || 
            $task['assigned_by'] == $user_id || 
            (has_permission('department_manager') && $task['department_manager_id'] == $user_id);

// Kiểm tra quyền xóa công việc
$can_delete = has_permission('admin') || has_permission('project_manager') || 
              $task['assigned_by'] == $user_id;

// Cập nhật tiến độ công việc
if (isset($_POST['update_progress']) && $can_update_progress) {
    $progress = (int)$_POST['progress'];
    $status_id = (int)$_POST['status_id'];
    
    // Kiểm tra dữ liệu
    $errors = [];
    
    if ($progress < 0 || $progress > 100) {
        $errors[] = 'Tiến độ phải từ 0 đến 100%';
    }
    
    // Nếu không có lỗi, cập nhật tiến độ
    if (empty($errors)) {
        // Xác định ngày hoàn thành (nếu công việc đã hoàn thành)
        $completed_date = ($status_id == 3) ? ", completed_date = NOW()" : '';
        
        // Cập nhật tiến độ
        $sql = "UPDATE tasks SET 
                progress = $progress, 
                status_id = $status_id
                $completed_date
                WHERE id = $task_id";
        query($sql);
        
        // Tạo thông báo cho người giao việc (nếu người cập nhật là người được giao việc)
        if ($task['assigned_to'] == $user_id && $task['assigned_by'] != $user_id) {
            $notification_message = "Công việc \"" . $task['title'] . "\" đã được cập nhật tiến độ: $progress%";
            $notification_link = "modules/tasks/view.php?id=$task_id";
            
            $notification_sql = "INSERT INTO notifications (user_id, message, link, created_at)
                               VALUES ({$task['assigned_by']}, '$notification_message', '$notification_link', NOW())";
            query($notification_sql);
        }
        
        // Nếu công việc đã hoàn thành, tạo thông báo cho người giao việc
        if ($status_id == 3 && $task['assigned_by'] != $user_id) {
            $notification_message = "Công việc \"" . $task['title'] . "\" đã hoàn thành";
            $notification_link = "modules/tasks/view.php?id=$task_id";
            
            $notification_sql = "INSERT INTO notifications (user_id, message, link, created_at)
                               VALUES ({$task['assigned_by']}, '$notification_message', '$notification_link', NOW())";
            query($notification_sql);
        }
        
        set_flash_message('Cập nhật tiến độ thành công');
        redirect('modules/tasks/view.php?id=' . $task_id);
    }
}

// Lấy lịch sử bình luận
$history_sql = "SELECT c.*, u.name as user_name, u.avatar as user_avatar
               FROM comments c
               JOIN users u ON c.user_id = u.id
               WHERE c.task_id = $task_id
               ORDER BY c.created_at DESC";
$history_result = query($history_sql);

// Lấy danh sách công việc con (nếu có)
$subtasks_sql = "SELECT t.*, 
                u.name as assigned_name, u.avatar as assigned_avatar
                FROM tasks t
                LEFT JOIN users u ON t.assigned_to = u.id
                WHERE t.parent_id = $task_id
                ORDER BY t.status_id ASC, t.priority DESC, t.due_date ASC";
$subtasks_result = query($subtasks_sql);

// Danh sách trạng thái công việc
$status_list = [
    1 => ['name' => 'Chưa bắt đầu', 'color' => 'primary'],
    2 => ['name' => 'Đang thực hiện', 'color' => 'warning'],
    3 => ['name' => 'Hoàn thành', 'color' => 'success'],
    4 => ['name' => 'Tạm dừng', 'color' => 'danger'],
    5 => ['name' => 'Đã hủy', 'color' => 'secondary']
];

// Danh sách ưu tiên
$priority_list = [
    1 => ['name' => 'Thấp', 'color' => 'secondary'],
    2 => ['name' => 'Trung bình', 'color' => 'info'],
    3 => ['name' => 'Cao', 'color' => 'warning'],
    4 => ['name' => 'Khẩn cấp', 'color' => 'danger']
];

// Thiết lập các biến theo trạng thái công việc
$status_color = $status_list[$task['status_id']]['color'];
$priority_color = $priority_list[$task['priority']]['color'];

// Xác định class cho thời hạn
$now = strtotime(date('Y-m-d'));
$due_date = strtotime($task['due_date']);
$date_class = '';

if ($task['status_id'] != 3 && $task['status_id'] != 5) { // Nếu không phải đã hoàn thành hoặc đã hủy
    if ($due_date < $now) {
        $date_class = 'text-danger font-weight-bold'; // Quá hạn
    } elseif ($due_date < strtotime('+3 days', $now)) {
        $date_class = 'text-warning font-weight-bold'; // Sắp đến hạn (3 ngày)
    }
}

// Tiêu đề trang
$page_title = "Chi tiết công việc: " . $task['title'];

// Thêm bình luận mới
if (isset($_POST['add_comment'])) {
    $comment_content = escape_string($_POST['comment_content']);
    
    if (empty($comment_content)) {
        $errors[] = "Nội dung bình luận không được để trống.";
    } else {
        // Thêm bình luận
        $insert_comment_sql = "INSERT INTO comments (task_id, user_id, content, created_at) 
                             VALUES ($task_id, $user_id, '$comment_content', NOW())";
        $insert_result = query($insert_comment_sql);
        
        if ($insert_result) {
            set_flash_message("Đã thêm bình luận thành công.", "success");
            redirect('modules/tasks/view.php?id=' . $task_id);
        } else {
            $errors[] = "Có lỗi xảy ra khi thêm bình luận.";
        }
    }
}

// Xử lý nộp báo cáo công việc
if (isset($_POST['submit_report']) && $task['assigned_to'] == $user_id) {
    $report_title = escape_string($_POST['report_title']);
    $report_content = escape_string($_POST['report_content']);
    $file_path = null;
    
    // Kiểm tra dữ liệu
    $errors = [];
    
    if (empty($report_title)) {
        $errors[] = "Tiêu đề báo cáo không được để trống.";
    }
    
    if (empty($report_content)) {
        $errors[] = "Nội dung báo cáo không được để trống.";
    }
    
    // Xử lý tệp đính kèm nếu có
    if (!empty($_FILES['report_file']['name'])) {
        $upload_dir = 'uploads/reports/';
        $file_name = time() . '_' . $_FILES['report_file']['name'];
        $upload_path = '../../' . $upload_dir . $file_name;
        
        // Kiểm tra kích thước tệp (tối đa 10MB)
        if ($_FILES['report_file']['size'] > 10 * 1024 * 1024) {
            $errors[] = "Kích thước tệp vượt quá 10MB.";
        }
        
        // Kiểm tra định dạng tệp
        $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar', 'jpg', 'jpeg', 'png'];
        $file_ext = strtolower(pathinfo($_FILES['report_file']['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_ext, $allowed_extensions)) {
            $errors[] = "Định dạng tệp không được hỗ trợ.";
        }
        
        // Tạo thư mục uploads nếu chưa tồn tại
        if (!file_exists('../../' . $upload_dir)) {
            mkdir('../../' . $upload_dir, 0777, true);
        }
        
        // Upload tệp
        if (empty($errors)) {
            if (move_uploaded_file($_FILES['report_file']['tmp_name'], $upload_path)) {
                $file_path = $upload_dir . $file_name;
            } else {
                $errors[] = "Có lỗi xảy ra khi tải tệp lên.";
            }
        }
    }
    
    // Nếu không có lỗi, lưu báo cáo
    if (empty($errors)) {
        $file_path_sql = $file_path ? "'$file_path'" : "NULL";
        
        // Thêm báo cáo mới
        $sql = "INSERT INTO reports (title, content, report_type, task_id, project_id, user_id, file_path, created_at) 
                VALUES ('$report_title', '$report_content', 'task', $task_id, {$task['project_id']}, $user_id, $file_path_sql, NOW())";
        $result = query($sql);
        
        if ($result) {
            // Tạo thông báo cho người giao việc
            if ($task['assigned_by'] != $user_id) {
                $notification_message = "Nhân viên " . $_SESSION['user_name'] . " đã nộp báo cáo cho công việc \"" . $task['title'] . "\"";
                $notification_link = "modules/tasks/view.php?id=$task_id";
                
                $notification_sql = "INSERT INTO notifications (user_id, message, link, created_at)
                                   VALUES ({$task['assigned_by']}, '$notification_message', '$notification_link', NOW())";
                query($notification_sql);
            }
            
            set_flash_message("Nộp báo cáo thành công.", "success");
            redirect('modules/tasks/view.php?id=' . $task_id);
        } else {
            $errors[] = "Có lỗi xảy ra khi nộp báo cáo.";
        }
    }
}

// Include header
include_once '../../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            Chi tiết công việc
            <span class="badge badge-<?php echo $status_color; ?> ml-2">
                <?php echo $status_list[$task['status_id']]['name']; ?>
            </span>
        </h1>
        <div>
            <?php if ($can_edit): ?>
            <a href="edit.php?id=<?php echo $task_id; ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-edit fa-sm text-white-50"></i> Chỉnh sửa
            </a>
            <?php endif; ?>
            
            <?php if ($can_delete): ?>
            <a href="delete.php?id=<?php echo $task_id; ?>" class="btn btn-danger btn-sm" 
               onclick="return confirm('Bạn có chắc chắn muốn xóa công việc này?');">
                <i class="fas fa-trash fa-sm text-white-50"></i> Xóa
            </a>
            <?php endif; ?>
            
            <a href="index.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left fa-sm text-white-50"></i> Quay lại
            </a>
        </div>
    </div>

    <!-- Hiển thị thông báo -->
    <?php 
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

    <!-- Thông tin công việc và tiến độ -->
    <div class="row">
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Thông tin công việc</h6>
                </div>
                <div class="card-body">
                    <h4 class="font-weight-bold"><?php echo $task['title']; ?></h4>
                    
                    <?php if ($task['parent_id']): ?>
                        <?php
                        // Lấy thông tin công việc cha
                        $parent_task_sql = "SELECT id, title FROM tasks WHERE id = {$task['parent_id']}";
                        $parent_task_result = query($parent_task_sql);
                        $parent_task = fetch_array($parent_task_result);
                        ?>
                        <div class="mb-3">
                            <span class="text-muted">Công việc con của:</span>
                            <a href="view.php?id=<?php echo $parent_task['id']; ?>" class="ml-1">
                                <?php echo $parent_task['title']; ?>
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <p>
                                <strong>Dự án:</strong> 
                                <a href="../projects/view.php?id=<?php echo $task['project_id']; ?>"><?php echo $task['project_name']; ?></a>
                            </p>
                            
                            <p>
                                <strong>Phòng ban:</strong> 
                                <?php if ($task['department_id']): ?>
                                    <a href="../departments/view.php?id=<?php echo $task['department_id']; ?>"><?php echo $task['department_name']; ?></a>
                                <?php else: ?>
                                    <span class="text-muted">Chưa phân công</span>
                                <?php endif; ?>
                            </p>
                            
                            <p>
                                <strong>Ưu tiên:</strong> 
                                <span class="badge badge-<?php echo $priority_color; ?>">
                                    <?php echo $priority_list[$task['priority']]['name']; ?>
                                </span>
                            </p>
                        </div>
                        
                        <div class="col-md-6">
                            <p>
                                <strong>Ngày bắt đầu:</strong> <?php echo format_date($task['start_date']); ?>
                            </p>
                            
                            <p>
                                <strong>Hạn hoàn thành:</strong> 
                                <span class="<?php echo $date_class; ?>">
                                    <?php echo format_date($task['due_date']); ?>
                                    <?php if ($date_class == 'text-danger font-weight-bold'): ?>
                                        <span class="badge badge-danger ml-1">Quá hạn</span>
                                    <?php endif; ?>
                                </span>
                            </p>
                            
                            <p>
                                <strong>Ngày hoàn thành:</strong> 
                                <?php if ($task['completed_date']): ?>
                                    <?php echo format_date($task['completed_date']); ?>
                                <?php else: ?>
                                    <span class="text-muted">Chưa hoàn thành</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Người giao việc:</strong></p>
                            <div class="d-flex align-items-center">
                                <img src="<?php echo BASE_URL . $task['assigned_by_avatar']; ?>" class="rounded-circle mr-2" width="40" height="40">
                                <div>
                                    <?php echo $task['assigned_by_name']; ?><br>
                                    <small class="text-muted"><?php echo $task['assigned_by_email']; ?></small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Người thực hiện:</strong></p>
                            <?php if ($task['assigned_to']): ?>
                                <div class="d-flex align-items-center">
                                    <img src="<?php echo BASE_URL . $task['assigned_avatar']; ?>" class="rounded-circle mr-2" width="40" height="40">
                                    <div>
                                        <?php echo $task['assigned_name']; ?><br>
                                        <small class="text-muted"><?php echo $task['assigned_email']; ?></small>
                                    </div>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">Chưa phân công</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <h6 class="font-weight-bold">Tiến độ công việc</h6>
                        <div class="progress mb-2" style="height: 25px;">
                            <div class="progress-bar bg-<?php echo $status_color; ?>" role="progressbar" 
                                 style="width: <?php echo $task['progress']; ?>%" 
                                 aria-valuenow="<?php echo $task['progress']; ?>" aria-valuemin="0" aria-valuemax="100">
                                <?php echo $task['progress']; ?>%
                            </div>
                        </div>
                        <div class="text-right">
                            <small class="text-muted">Cập nhật lần cuối: <?php echo format_datetime($task['updated_at']); ?></small>
                        </div>
                    </div>
                    
                    <div>
                        <h6 class="font-weight-bold">Mô tả công việc</h6>
                        <div class="p-3 bg-light rounded">
                            <?php echo nl2br($task['description']) ?: '<span class="text-muted">Không có mô tả</span>'; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Danh sách công việc con -->
            <?php if (num_rows($subtasks_result) > 0 || $can_edit): ?>
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Danh sách công việc con</h6>
                    
                    <?php if ($can_edit): ?>
                    <a href="add.php?parent_id=<?php echo $task_id; ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus fa-sm"></i> Thêm công việc con
                    </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (num_rows($subtasks_result) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th width="40%">Tên công việc</th>
                                        <th>Người thực hiện</th>
                                        <th>Hạn hoàn thành</th>
                                        <th>Trạng thái</th>
                                        <th>Tiến độ</th>
                                        <th>Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($subtask = fetch_array($subtasks_result)): ?>
                                        <?php 
                                        // Xác định màu cho trạng thái
                                        $subtask_status_color = $status_list[$subtask['status_id']]['color'];
                                        
                                        // Xác định class cho thời hạn
                                        $subtask_due_date = strtotime($subtask['due_date']);
                                        $subtask_date_class = '';
                                        
                                        if ($subtask['status_id'] != 3 && $subtask['status_id'] != 5) {
                                            if ($subtask_due_date < $now) {
                                                $subtask_date_class = 'text-danger font-weight-bold';
                                            } elseif ($subtask_due_date < strtotime('+3 days', $now)) {
                                                $subtask_date_class = 'text-warning font-weight-bold';
                                            }
                                        }
                                        ?>
                                        <tr>
                                            <td><?php echo $subtask['title']; ?></td>
                                            <td>
                                                <?php if ($subtask['assigned_to']): ?>
                                                    <div class="d-flex align-items-center justify-content-evenly">
                                                        <img class="rounded-circle mr-2" width="30" height="30" 
                                                             src="<?php echo BASE_URL . $subtask['assigned_avatar']; ?>" alt="">
                                                        <?php echo $subtask['assigned_name']; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">Chưa phân công</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="<?php echo $subtask_date_class; ?>">
                                                <?php echo format_date($subtask['due_date']); ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $subtask_status_color; ?>">
                                                    <?php echo $status_list[$subtask['status_id']]['name']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="progress">
                                                    <div class="progress-bar bg-<?php echo $subtask_status_color; ?>" role="progressbar" 
                                                         style="width: <?php echo $subtask['progress']; ?>%" 
                                                         aria-valuenow="<?php echo $subtask['progress']; ?>" aria-valuemin="0" aria-valuemax="100">
                                                        <?php echo $subtask['progress']; ?>%
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <a href="view.php?id=<?php echo $subtask['id']; ?>" class="btn btn-info btn-sm">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-muted">
                            Chưa có công việc con nào.
                            <?php if ($can_edit): ?>
                                <a href="add.php?parent_id=<?php echo $task_id; ?>">Thêm công việc con</a>.
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="col-xl-4 col-lg-5">
            <!-- Cập nhật tiến độ -->
            <?php if ($can_update_progress && $task['status_id'] != 3 && $task['status_id'] != 5): ?>
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Cập nhật tiến độ</h6>
                </div>
                <div class="card-body">
                    <form action="" method="POST">
                        <div class="form-group">
                            <label for="progress">Tiến độ hiện tại (%)</label>
                            <input type="number" class="form-control" id="progress" name="progress" min="0" max="100" 
                                   value="<?php echo $task['progress']; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="status_id">Trạng thái công việc</label>
                            <select class="form-control" id="status_id" name="status_id">
                                <?php foreach ($status_list as $id => $status): ?>
                                    <option value="<?php echo $id; ?>" 
                                            <?php echo $task['status_id'] == $id ? 'selected' : ''; ?>>
                                        <?php echo $status['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <button type="submit" name="update_progress" class="btn btn-primary btn-block">
                            <i class="fas fa-save"></i> Cập nhật tiến độ
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Form thêm bình luận -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Thêm bình luận</h6>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <div class="form-group">
                            <textarea class="form-control" name="comment_content" rows="3" placeholder="Nhập bình luận của bạn..."></textarea>
                        </div>
                        <button type="submit" name="add_comment" class="btn btn-primary">Gửi bình luận</button>
                    </form>
                </div>
            </div>
            
            <!-- Báo cáo công việc -->
            <?php if ($task['assigned_to'] == $user_id): ?>
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-success">Nộp kết quả báo cáo công việc</h6>
                </div>
                <div class="card-body">
                    <form method="post" action="" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="report_title">Tiêu đề báo cáo</label>
                            <input type="text" class="form-control" id="report_title" name="report_title" placeholder="Nhập tiêu đề báo cáo...">
                        </div>
                        <div class="form-group">
                            <label for="report_content">Nội dung báo cáo</label>
                            <textarea class="form-control" id="report_content" name="report_content" rows="4" placeholder="Nội dung báo cáo chi tiết..."></textarea>
                        </div>
                        <div class="form-group">
                            <label for="report_file">Đính kèm tệp (nếu có)</label>
                            <input type="file" class="form-control-file w-100" id="report_file" name="report_file">
                            <small class="form-text text-muted">Định dạng hỗ trợ: PDF, Word, Excel, Zip (tối đa 10MB)</small>
                        </div>
                        <button type="submit" name="submit_report" class="btn btn-success">Nộp báo cáo</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- Lịch sử báo cáo -->
            <?php if ($can_view): ?>
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-success">Lịch sử báo cáo kết quả công việc</h6>
                </div>
                <div class="card-body">
                    <?php 
                    // Lấy lịch sử báo cáo cho công việc này
                    $reports_sql = "SELECT r.*, u.name as user_name, u.avatar as user_avatar 
                                   FROM reports r
                                   JOIN users u ON r.user_id = u.id
                                   WHERE r.task_id = $task_id
                                   ORDER BY r.created_at DESC";
                    $reports_result = query($reports_sql);
                    
                    if (num_rows($reports_result) > 0):
                    ?>
                        <div class="timeline timeline-report">
                            <?php while ($report = fetch_array($reports_result)): ?>
                                <div class="timeline-item">
                                    <div class="timeline-item-marker">
                                        <div class="timeline-item-marker-text"><?php echo format_datetime($report['created_at']); ?></div>
                                        <div class="timeline-item-marker-indicator bg-success"></div>
                                    </div>
                                    <div class="timeline-item-content">
                                        <div class="d-flex align-items-center mb-2">
                                            <img src="<?php echo BASE_URL . $report['user_avatar']; ?>" class="rounded-circle mr-2" width="30" height="30">
                                            <strong><?php echo $report['user_name']; ?></strong>
                                            <span class="badge badge-success ml-2">Báo cáo chính thức</span>
                                        </div>
                                        
                                        <h6 class="font-weight-bold"><?php echo $report['title']; ?></h6>
                                        
                                        <div class="card bg-light mb-2">
                                            <div class="card-body py-2 px-3">
                                                <?php echo nl2br(htmlspecialchars($report['content'])); ?>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($report['file_path'])): ?>
                                        <div>
                                            <a href="<?php echo BASE_URL . $report['file_path']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-download"></i> Tải tệp đính kèm
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            Chưa có báo cáo nào cho công việc này.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Bình luận -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Bình luận</h6>
                </div>
                <div class="card-body">
                    <?php if (num_rows($history_result) > 0): ?>
                        <div class="timeline timeline-comment">
                            <?php while ($comment = fetch_array($history_result)): ?>
                                <div class="timeline-item">
                                    <div class="timeline-item-marker">
                                        <div class="timeline-item-marker-text"><?php echo format_datetime($comment['created_at']); ?></div>
                                        <div class="timeline-item-marker-indicator bg-primary"></div>
                                    </div>
                                    <div class="timeline-item-content">
                                        <div class="d-flex align-items-center justify-content-evenly mb-2">
                                            <img src="<?php echo BASE_URL . $comment['user_avatar']; ?>" class="rounded-circle mr-2" width="30" height="30">
                                            <strong><?php echo $comment['user_name']; ?></strong>
                                        </div>
                                        
                                        <?php if (!empty($comment['content'])): ?>
                                            <div class="card bg-light mb-2">
                                                <div class="card-body py-2 px-3">
                                                    <?php echo nl2br(htmlspecialchars($comment['content'])); ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            Chưa có bình luận nào cho công việc này.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Timeline style */
.timeline {
    position: relative;
    padding-left: 1rem;
    margin: 0;
}
.timeline-item {
    position: relative;
    padding-left: 2rem;
    padding-bottom: 2rem;
}
.timeline-item:last-child {
    padding-bottom: 0;
}
.timeline-item-marker {
    position: absolute;
    left: -0.25rem;
    width: 2rem;
}
.timeline-item-marker-text {
    font-size: 0.8rem;
    color: #6c757d;
    margin-bottom: 0.25rem;
}
.timeline-item-marker-indicator {
    position: absolute;
    left: -1rem;
    width: 1rem;
    height: 1rem;
    border-radius: 100%;
}
.timeline-item::before {
    content: '';
    display: block;
    position: absolute;
    left: -0.25rem;
    top: 2rem;
    bottom: 0;
    width: 1px;
    background-color: #e3e6ec;
}
.timeline-item:last-child::before {
    display: none;
}
.timeline-item-content {
    padding-bottom: 0.5rem;
}

/* Reports style - to visually differentiate from comments */
.timeline-report .timeline-item-marker-indicator {
    background-color: #28a745 !important;
}
.timeline-report .timeline-item::before {
    background-color: #28a745;
}
.timeline-report .card {
    border-left: 4px solid #28a745;
}
.timeline-report .timeline-item-content h6 {
    color: #28a745;
}
.timeline-comment .timeline-item-marker-indicator {
    background-color: #4e73df !important;
}
.timeline-comment .timeline-item::before {
    background-color: #4e73df;
}
</style>

<script>
// Cập nhật progress tự động khi chọn trạng thái
document.addEventListener('DOMContentLoaded', function() {
    var statusSelect = document.getElementById('status_id');
    var progressInput = document.getElementById('progress');
    
    if (statusSelect && progressInput) {
        statusSelect.addEventListener('change', function() {
            if (statusSelect.value == '3') { // Hoàn thành
                progressInput.value = 100;
            } else if (statusSelect.value == '1') { // Chưa bắt đầu
                progressInput.value = 0;
            }
        });
    }
});
</script>

<?php
// Include footer
include_once '../../templates/footer.php';
?> 