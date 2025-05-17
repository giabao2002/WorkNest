<?php
/**
 * Thêm công việc mới
 */

// Include config
require_once '../../config/config.php';

// Kiểm tra đăng nhập và quyền hạn
if (!is_logged_in()) {
    redirect('index.php');
}

// Kiểm tra quyền thêm công việc (chỉ admin, quản lý dự án, và quản lý phòng ban có quyền)
if (!has_permission('admin') && !has_permission('project_manager') && !has_permission('department_manager')) {
    set_flash_message('Bạn không có quyền thêm công việc mới', 'danger');
    redirect('modules/tasks/');
}

// Lấy tham số dự án từ URL (nếu có)
$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
$project = null;

if ($project_id > 0) {
    // Lấy thông tin dự án
    $project_query = query("SELECT * FROM projects WHERE id = $project_id");
    if (num_rows($project_query) > 0) {
        $project = fetch_array($project_query);
        
        // Nếu là quản lý phòng ban, kiểm tra xem phòng ban có tham gia dự án không
        if (has_permission('department_manager')) {
            $user_id = $_SESSION['user_id'];
            $dept_query = query("SELECT d.id FROM departments d WHERE d.manager_id = $user_id");
            
            if (num_rows($dept_query) > 0) {
                $dept = fetch_array($dept_query);
                $dept_id = $dept['id'];
                
                // Kiểm tra phòng ban có tham gia dự án không
                $check_query = query("SELECT id FROM project_departments WHERE project_id = $project_id AND department_id = $dept_id");
                
                if (num_rows($check_query) === 0) {
                    set_flash_message('Phòng ban của bạn không tham gia dự án này', 'danger');
                    redirect('modules/tasks/');
                }
            } else {
                set_flash_message('Bạn không quản lý phòng ban nào', 'danger');
                redirect('modules/tasks/');
            }
        }
    } else {
        set_flash_message('Không tìm thấy dự án', 'danger');
        redirect('modules/tasks/');
    }
}

// Lấy tham số công việc cha (nếu là công việc con)
$parent_id = isset($_GET['parent_id']) ? (int)$_GET['parent_id'] : 0;
$parent_task = null;

if ($parent_id > 0) {
    // Lấy thông tin công việc cha
    $parent_query = query("SELECT * FROM tasks WHERE id = $parent_id");
    if (num_rows($parent_query) > 0) {
        $parent_task = fetch_array($parent_query);
        $project_id = $parent_task['project_id'];
        
        // Lấy thông tin dự án từ công việc cha
        $project_query = query("SELECT * FROM projects WHERE id = $project_id");
        if (num_rows($project_query) > 0) {
            $project = fetch_array($project_query);
        }
    } else {
        set_flash_message('Không tìm thấy công việc cha', 'danger');
        redirect('modules/tasks/');
    }
}

// Xử lý form thêm công việc
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lấy dữ liệu từ form
    $title = escape_string($_POST['title']);
    $description = escape_string($_POST['description']);
    $project_id = (int)$_POST['project_id'];
    $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : 'NULL';
    $assigned_to = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : 'NULL';
    $status_id = (int)$_POST['status_id'];
    $priority = (int)$_POST['priority'];
    $start_date = escape_string($_POST['start_date']);
    $due_date = escape_string($_POST['due_date']);
    $progress = (int)$_POST['progress'];
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : 'NULL';
    
    // Kiểm tra dữ liệu
    $errors = [];
    
    if (empty($title)) {
        $errors[] = 'Vui lòng nhập tên công việc';
    }
    
    if ($project_id <= 0) {
        $errors[] = 'Vui lòng chọn dự án';
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
    
    // Nếu không có lỗi, thêm công việc mới
    if (empty($errors)) {
        // Lấy ID người giao việc (người tạo)
        $assigned_by = $_SESSION['user_id'];
        
        // Xác định ngày hoàn thành (nếu công việc đã hoàn thành)
        $completed_date = ($status_id == 3) ? "'" . date('Y-m-d') . "'" : 'NULL';
        
        // Thêm công việc mới
        $sql = "INSERT INTO tasks (
                title, description, project_id, department_id, assigned_to, assigned_by,
                status_id, priority, start_date, due_date, progress, parent_id, completed_date, created_at
                ) VALUES (
                '$title', '$description', $project_id, $department_id, $assigned_to, $assigned_by,
                $status_id, $priority, '$start_date', '$due_date', $progress, $parent_id, $completed_date, NOW()
                )";
        $result = query($sql);
        
        if ($result) {
            $task_id = last_id();
            
            // Tạo thông báo cho người được giao việc (nếu có)
            if ($assigned_to !== 'NULL') {
                $notification_message = "Bạn được giao công việc mới: $title";
                $notification_link = "modules/tasks/view.php?id=$task_id";
                
                $notification_sql = "INSERT INTO notifications (user_id, message, link, created_at)
                                   VALUES ($assigned_to, '$notification_message', '$notification_link', NOW())";
                query($notification_sql);
            }
            
            set_flash_message('Thêm công việc thành công');
            
            // Chuyển hướng đến trang chi tiết công việc
            redirect('modules/tasks/view.php?id=' . $task_id);
        } else {
            $errors[] = 'Có lỗi xảy ra khi thêm công việc';
        }
    }
}

// Lấy danh sách dự án
$projects_sql = "SELECT id, name FROM projects";

// Nếu là quản lý phòng ban, chỉ lấy dự án của phòng ban mình quản lý
if (has_permission('department_manager')) {
    $user_id = $_SESSION['user_id'];
    $dept_query = query("SELECT id FROM departments WHERE manager_id = $user_id");
    
    if (num_rows($dept_query) > 0) {
        $dept = fetch_array($dept_query);
        $dept_id = $dept['id'];
        
        $projects_sql = "SELECT p.id, p.name FROM projects p
                       JOIN project_departments pd ON p.id = pd.project_id
                       WHERE pd.department_id = $dept_id";
    }
}

$projects_sql .= " ORDER BY name";
$projects_result = query($projects_sql);

// Lấy danh sách phòng ban
$departments_sql = "SELECT id, name FROM departments";

// Nếu đã chọn dự án, chỉ lấy các phòng ban tham gia dự án
if ($project_id > 0) {
    $departments_sql = "SELECT d.id, d.name FROM departments d
                      JOIN project_departments pd ON d.id = pd.department_id
                      WHERE pd.project_id = $project_id";
}

// Nếu là quản lý phòng ban, chỉ lấy phòng ban mình quản lý
if (has_permission('department_manager')) {
    $user_id = $_SESSION['user_id'];
    $departments_sql = "SELECT id, name FROM departments WHERE manager_id = $user_id";
}

$departments_sql .= " ORDER BY name";
$departments_result = query($departments_sql);

// Lấy danh sách người dùng (nếu đã chọn phòng ban)
$users = [];
if (isset($_POST['department_id']) && !empty($_POST['department_id'])) {
    $dept_id = (int)$_POST['department_id'];
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
$page_title = $parent_id > 0 ? "Thêm công việc con" : "Thêm công việc mới";

// Include header
include_once '../../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <?php echo $parent_id > 0 ? "Thêm công việc con" : "Thêm công việc mới"; ?>
            <?php if ($project): ?>
                cho dự án: <?php echo $project['name']; ?>
            <?php endif; ?>
        </h1>
        <a href="<?php echo $parent_id > 0 ? 'view.php?id=' . $parent_id : 'index.php'; ?>" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
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
                <!-- Thông tin công việc cha -->
                <?php if ($parent_task): ?>
                    <div class="alert alert-info">
                        <h6 class="alert-heading">Công việc cha: <a href="view.php?id=<?php echo $parent_task['id']; ?>"><?php echo $parent_task['title']; ?></a></h6>
                        <p class="mb-0">Công việc mới sẽ được tạo như một công việc con của công việc trên.</p>
                        <input type="hidden" name="parent_id" value="<?php echo $parent_task['id']; ?>">
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-8">
                        <div class="form-group">
                            <label for="title">Tên công việc <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" required 
                                   value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Mô tả công việc</label>
                            <textarea class="form-control" id="description" name="description" rows="5"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="project_id">Dự án <span class="text-danger">*</span></label>
                                    <select class="form-control" id="project_id" name="project_id" required 
                                            <?php echo $project_id > 0 ? 'disabled' : ''; ?>>
                                        <option value="">-- Chọn dự án --</option>
                                        <?php while ($p = fetch_array($projects_result)): ?>
                                            <option value="<?php echo $p['id']; ?>" 
                                                    <?php echo (isset($_POST['project_id']) ? $_POST['project_id'] : $project_id) == $p['id'] ? 'selected' : ''; ?>>
                                                <?php echo $p['name']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <?php if ($project_id > 0): ?>
                                        <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="department_id">Phòng ban phụ trách</label>
                                    <select class="form-control" id="department_id" name="department_id" onchange="this.form.submit()">
                                        <option value="">-- Chọn phòng ban --</option>
                                        <?php mysqli_data_seek($departments_result, 0); ?>
                                        <?php while ($d = fetch_array($departments_result)): ?>
                                            <option value="<?php echo $d['id']; ?>" 
                                                    <?php echo (isset($_POST['department_id']) && $_POST['department_id'] == $d['id']) ? 'selected' : ''; ?>>
                                                <?php echo $d['name']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="start_date">Ngày bắt đầu <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" required 
                                           value="<?php echo isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d'); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="due_date">Ngày hoàn thành <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="due_date" name="due_date" required 
                                           value="<?php echo isset($_POST['due_date']) ? $_POST['due_date'] : date('Y-m-d', strtotime('+7 days')); ?>">
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
                                    <label for="assigned_to">Người thực hiện</label>
                                    <select class="form-control" id="assigned_to" name="assigned_to">
                                        <option value="">-- Chọn người thực hiện --</option>
                                        <?php foreach ($users as $id => $name): ?>
                                            <option value="<?php echo $id; ?>" 
                                                    <?php echo (isset($_POST['assigned_to']) && $_POST['assigned_to'] == $id) ? 'selected' : ''; ?>>
                                                <?php echo $name; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (empty($users)): ?>
                                        <small class="form-text text-muted">Chọn phòng ban để hiển thị danh sách nhân viên</small>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="form-group">
                                    <label for="status_id">Trạng thái</label>
                                    <select class="form-control" id="status_id" name="status_id">
                                        <?php foreach ($status_list as $id => $name): ?>
                                            <option value="<?php echo $id; ?>" 
                                                    <?php echo (isset($_POST['status_id']) ? $_POST['status_id'] : 1) == $id ? 'selected' : ''; ?>>
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
                                                    <?php echo (isset($_POST['priority']) ? $_POST['priority'] : 2) == $id ? 'selected' : ''; ?>>
                                                <?php echo $name; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="progress">Tiến độ (%)</label>
                                    <input type="number" class="form-control" id="progress" name="progress" min="0" max="100" 
                                           value="<?php echo isset($_POST['progress']) ? (int)$_POST['progress'] : 0; ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Nút submit -->
                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Lưu công việc
                    </button>
                    <a href="<?php echo $parent_id > 0 ? 'view.php?id=' . $parent_id : 'index.php'; ?>" class="btn btn-secondary ml-2">
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
    
    statusSelect.addEventListener('change', function() {
        if (statusSelect.value == '3') { // Hoàn thành
            progressInput.value = 100;
        } else if (statusSelect.value == '1') { // Chưa bắt đầu
            progressInput.value = 0;
        }
    });
});
</script>

<?php
// Include footer
include_once '../../templates/footer.php';
?> 