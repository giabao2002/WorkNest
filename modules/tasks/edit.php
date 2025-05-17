<?php
/**
 * Chỉnh sửa thông tin công việc
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
            d.name as department_name, d.manager_id as department_manager_id
            FROM tasks t
            LEFT JOIN projects p ON t.project_id = p.id
            LEFT JOIN departments d ON t.department_id = d.id
            WHERE t.id = $task_id";
$task_result = query($task_sql);

if (num_rows($task_result) === 0) {
    set_flash_message('Không tìm thấy công việc', 'danger');
    redirect('modules/tasks/index.php');
}

// Lấy thông tin công việc
$task = fetch_array($task_result);

// Kiểm tra quyền chỉnh sửa công việc
$user_id = $_SESSION['user_id'];
$can_edit = has_permission('admin') || has_permission('project_manager');

if (!$can_edit) {
    // Người tạo có quyền sửa
    if ($task['assigned_by'] == $user_id) {
        $can_edit = true;
    }
    
    // Quản lý dự án có quyền sửa
    if ($task['project_manager_id'] == $user_id) {
        $can_edit = true;
    }
    
    // Quản lý phòng ban của người được giao việc có quyền sửa
    if (has_permission('department_manager') && $task['department_manager_id'] == $user_id) {
        $can_edit = true;
    }
}

if (!$can_edit) {
    set_flash_message('Bạn không có quyền chỉnh sửa công việc này', 'danger');
    redirect('modules/tasks/view.php?id=' . $task_id);
}

// Xử lý form chỉnh sửa
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lấy dữ liệu từ form
    $title = escape_string($_POST['title']);
    $description = escape_string($_POST['description']);
    $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : 'NULL';
    $assigned_to = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : 'NULL';
    $status_id = (int)$_POST['status_id'];
    $priority = (int)$_POST['priority'];
    $start_date = escape_string($_POST['start_date']);
    $due_date = escape_string($_POST['due_date']);
    $progress = (int)$_POST['progress'];
    
    // Kiểm tra dữ liệu
    $errors = [];
    
    if (empty($title)) {
        $errors[] = 'Vui lòng nhập tên công việc';
    }
    
    if (empty($start_date)) {
        $errors[] = 'Vui lòng chọn ngày bắt đầu';
    }
    
    if (empty($due_date)) {
        $errors[] = 'Vui lòng chọn ngày hoàn thành';
    } elseif ($due_date < $start_date) {
        $errors[] = 'Ngày hoàn thành phải sau ngày bắt đầu';
    }
    
    if ($progress < 0 || $progress > 100) {
        $errors[] = 'Tiến độ phải từ 0 đến 100%';
    }
    
    // Nếu không có lỗi, cập nhật công việc
    if (empty($errors)) {
        // Xác định ngày hoàn thành (nếu công việc đã hoàn thành)
        $completed_date = '';
        if ($status_id == 3) {
            if ($task['status_id'] != 3) { // Nếu trước đó công việc chưa hoàn thành
                $completed_date = ", completed_date = NOW()";
            }
        } else {
            $completed_date = ", completed_date = NULL";
        }
        
        // Cập nhật thông tin công việc
        $sql = "UPDATE tasks SET 
                title = '$title', 
                description = '$description', 
                department_id = $department_id, 
                assigned_to = $assigned_to, 
                status_id = $status_id, 
                priority = $priority, 
                start_date = '$start_date', 
                due_date = '$due_date', 
                progress = $progress,
                updated_at = NOW()
                $completed_date
                WHERE id = $task_id";
        $result = query($sql);
        
        if ($result) {
            // Ghi nhật ký cập nhật
            if (isset($_POST['add_comment']) && !empty($_POST['comment'])) {
                $comment = escape_string($_POST['comment']);
                
                $comment_sql = "INSERT INTO task_comments (task_id, user_id, comment, progress, status_id, created_at)
                              VALUES ($task_id, $user_id, '$comment', $progress, $status_id, NOW())";
                query($comment_sql);
            }
            
            // Tạo thông báo cho người được giao việc (nếu có thay đổi)
            if ($assigned_to !== 'NULL' && $assigned_to != $task['assigned_to']) {
                $notification_message = "Bạn được giao công việc: $title";
                $notification_link = "modules/tasks/view.php?id=$task_id";
                
                $notification_sql = "INSERT INTO notifications (user_id, message, link, created_at)
                                   VALUES ($assigned_to, '$notification_message', '$notification_link', NOW())";
                query($notification_sql);
            }
            
            set_flash_message('Cập nhật công việc thành công');
            redirect('modules/tasks/view.php?id=' . $task_id);
        } else {
            $errors[] = 'Có lỗi xảy ra khi cập nhật công việc';
        }
    }
}

// Lấy danh sách phòng ban
$departments_sql = "SELECT d.id, d.name FROM departments d";

// Nếu là quản lý phòng ban, chỉ lấy phòng ban mình quản lý
if (has_permission('department_manager') && !has_permission('admin') && !has_permission('project_manager')) {
    $departments_sql .= " WHERE d.manager_id = $user_id";
}

// Nếu đã chọn dự án, chỉ lấy các phòng ban tham gia dự án
if ($task['project_id']) {
    if (strpos($departments_sql, 'WHERE') !== false) {
        $departments_sql .= " AND d.id IN (SELECT department_id FROM project_departments WHERE project_id = {$task['project_id']})";
    } else {
        $departments_sql .= " WHERE d.id IN (SELECT department_id FROM project_departments WHERE project_id = {$task['project_id']})";
    }
}

$departments_sql .= " ORDER BY name";
$departments_result = query($departments_sql);

// Lấy danh sách người dùng (nếu đã chọn phòng ban)
$users = [];
$dept_id = isset($_POST['department_id']) ? (int)$_POST['department_id'] : $task['department_id'];

if ($dept_id) {
    $users_query = query("SELECT id, name, email FROM users WHERE department_id = $dept_id ORDER BY name");
    while ($user = fetch_array($users_query)) {
        $users[$user['id']] = $user['name'] . ' (' . $user['email'] . ')';
    }
}

// Danh sách trạng thái công việc
$status_list = [
    1 => 'Chưa bắt đầu',
    2 => 'Đang thực hiện',
    3 => 'Hoàn thành',
    4 => 'Tạm dừng',
    5 => 'Đã hủy'
];

// Danh sách ưu tiên
$priority_list = [
    1 => 'Thấp',
    2 => 'Trung bình',
    3 => 'Cao',
    4 => 'Khẩn cấp'
];

// Tiêu đề trang
$page_title = "Chỉnh sửa công việc: " . $task['title'];

// Include header
include_once '../../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Chỉnh sửa công việc</h1>
        <a href="view.php?id=<?php echo $task_id; ?>" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50"></i> Quay lại
        </a>
    </div>

    <!-- Hiển thị lỗi nếu có -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Thông tin công việc</h6>
        </div>
        <div class="card-body">
            <form action="" method="POST">
                <!-- Dự án (chỉ hiển thị, không sửa được) -->
                <div class="form-group">
                    <label>Dự án</label>
                    <input type="text" class="form-control" value="<?php echo $task['project_name']; ?>" readonly>
                    <small class="form-text text-muted">Không thể thay đổi dự án của công việc. Nếu muốn chuyển công việc sang dự án khác, vui lòng tạo công việc mới.</small>
                </div>
                
                <?php if ($task['parent_id']): ?>
                    <?php
                    // Lấy thông tin công việc cha
                    $parent_task_sql = "SELECT id, title FROM tasks WHERE id = {$task['parent_id']}";
                    $parent_task_result = query($parent_task_sql);
                    $parent_task = fetch_array($parent_task_result);
                    ?>
                    <div class="form-group">
                        <label>Công việc cha</label>
                        <input type="text" class="form-control" value="<?php echo $parent_task['title']; ?>" readonly>
                        <small class="form-text text-muted">Không thể thay đổi công việc cha.</small>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-8">
                        <div class="form-group">
                            <label for="title">Tên công việc <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" required 
                                   value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : htmlspecialchars($task['title']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Mô tả công việc</label>
                            <textarea class="form-control" id="description" name="description" rows="5"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : htmlspecialchars($task['description']); ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="department_id">Phòng ban phụ trách</label>
                                    <select class="form-control" id="department_id" name="department_id" onchange="this.form.submit()">
                                        <option value="">-- Chọn phòng ban --</option>
                                        <?php mysqli_data_seek($departments_result, 0); ?>
                                        <?php while ($d = fetch_array($departments_result)): ?>
                                            <option value="<?php echo $d['id']; ?>" 
                                                    <?php echo (isset($_POST['department_id']) ? $_POST['department_id'] : $task['department_id']) == $d['id'] ? 'selected' : ''; ?>>
                                                <?php echo $d['name']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="assigned_to">Người thực hiện</label>
                                    <select class="form-control" id="assigned_to" name="assigned_to">
                                        <option value="">-- Chọn người thực hiện --</option>
                                        <?php foreach ($users as $id => $name): ?>
                                            <option value="<?php echo $id; ?>" 
                                                    <?php echo (isset($_POST['assigned_to']) ? $_POST['assigned_to'] : $task['assigned_to']) == $id ? 'selected' : ''; ?>>
                                                <?php echo $name; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (empty($users)): ?>
                                        <small class="form-text text-muted">Chọn phòng ban để hiển thị danh sách nhân viên</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="start_date">Ngày bắt đầu <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" required 
                                           value="<?php echo isset($_POST['start_date']) ? $_POST['start_date'] : $task['start_date']; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="due_date">Ngày hoàn thành <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="due_date" name="due_date" required 
                                           value="<?php echo isset($_POST['due_date']) ? $_POST['due_date'] : $task['due_date']; ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Thông tin bổ sung</h6>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="status_id">Trạng thái</label>
                                    <select class="form-control" id="status_id" name="status_id">
                                        <?php foreach ($status_list as $id => $name): ?>
                                            <option value="<?php echo $id; ?>" 
                                                    <?php echo (isset($_POST['status_id']) ? $_POST['status_id'] : $task['status_id']) == $id ? 'selected' : ''; ?>>
                                                <?php echo $name; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="priority">Mức độ ưu tiên</label>
                                    <select class="form-control" id="priority" name="priority">
                                        <?php foreach ($priority_list as $id => $name): ?>
                                            <option value="<?php echo $id; ?>" 
                                                    <?php echo (isset($_POST['priority']) ? $_POST['priority'] : $task['priority']) == $id ? 'selected' : ''; ?>>
                                                <?php echo $name; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="progress">Tiến độ (%)</label>
                                    <input type="number" class="form-control" id="progress" name="progress" min="0" max="100" 
                                           value="<?php echo isset($_POST['progress']) ? (int)$_POST['progress'] : $task['progress']; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="add_comment" name="add_comment" value="1" checked>
                                        <label class="custom-control-label" for="add_comment">Ghi nhật ký cập nhật</label>
                                    </div>
                                </div>
                                
                                <div class="form-group" id="comment-group">
                                    <label for="comment">Ghi chú cập nhật</label>
                                    <textarea class="form-control" id="comment" name="comment" rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Nút submit -->
                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Lưu thay đổi
                    </button>
                    <a href="view.php?id=<?php echo $task_id; ?>" class="btn btn-secondary ml-2">
                        <i class="fas fa-times"></i> Hủy
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Cập nhật progress tự động khi chọn trạng thái
document.addEventListener('DOMContentLoaded', function() {
    var statusSelect = document.getElementById('status_id');
    var progressInput = document.getElementById('progress');
    var addCommentCheckbox = document.getElementById('add_comment');
    var commentGroup = document.getElementById('comment-group');
    
    statusSelect.addEventListener('change', function() {
        if (statusSelect.value == '3') { // Hoàn thành
            progressInput.value = 100;
        } else if (statusSelect.value == '1') { // Chưa bắt đầu
            progressInput.value = 0;
        }
    });
    
    // Hiển thị/ẩn ô ghi chú
    addCommentCheckbox.addEventListener('change', function() {
        if (addCommentCheckbox.checked) {
            commentGroup.style.display = 'block';
        } else {
            commentGroup.style.display = 'none';
        }
    });
});
</script>

<?php
// Include footer
include_once '../../templates/footer.php';
?> 