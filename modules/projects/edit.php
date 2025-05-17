<?php
/**
 * Chỉnh sửa thông tin dự án
 */

// Include config
require_once '../../config/config.php';

// Kiểm tra đăng nhập và quyền hạn
if (!is_logged_in()) {
    redirect('index.php');
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

$project = fetch_array($project_result);

// Kiểm tra quyền chỉnh sửa dự án
if (!has_permission('admin') && !has_permission('project_manager') && $project['manager_id'] != $_SESSION['user_id']) {
    set_flash_message('Bạn không có quyền chỉnh sửa dự án này', 'danger');
    redirect('modules/projects/view.php?id=' . $project_id);
}

// Lấy danh sách phòng ban tham gia dự án
$assigned_dept_sql = "SELECT department_id FROM project_departments WHERE project_id = $project_id";
$assigned_dept_result = query($assigned_dept_sql);
$assigned_departments = [];

while ($dept = fetch_array($assigned_dept_result)) {
    $assigned_departments[] = $dept['department_id'];
}

// Xử lý form chỉnh sửa
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lấy dữ liệu từ form
    $name = escape_string($_POST['name']);
    $description = escape_string($_POST['description']);
    $start_date = escape_string($_POST['start_date']);
    $end_date = escape_string($_POST['end_date']);
    $status_id = (int)$_POST['status_id'];
    $priority = (int)$_POST['priority'];
    $manager_id = (int)$_POST['manager_id'];
    $departments = isset($_POST['departments']) ? $_POST['departments'] : [];
    
    // Kiểm tra dữ liệu
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Vui lòng nhập tên dự án';
    }
    
    if (empty($start_date)) {
        $errors[] = 'Vui lòng chọn ngày bắt đầu';
    }
    
    if (empty($end_date)) {
        $errors[] = 'Vui lòng chọn ngày kết thúc';
    } elseif ($end_date < $start_date) {
        $errors[] = 'Ngày kết thúc phải sau ngày bắt đầu';
    }
    
    if ($manager_id <= 0) {
        $errors[] = 'Vui lòng chọn người quản lý dự án';
    }
    
    if (empty($departments)) {
        $errors[] = 'Vui lòng chọn ít nhất một phòng ban tham gia dự án';
    }
    
    // Nếu không có lỗi, cập nhật dự án
    if (empty($errors)) {
        // Cập nhật thông tin dự án
        $sql = "UPDATE projects SET 
                name = '$name', 
                description = '$description', 
                start_date = '$start_date', 
                end_date = '$end_date', 
                status_id = $status_id, 
                priority = $priority, 
                manager_id = $manager_id,
                updated_at = NOW()
                WHERE id = $project_id";
        $result = query($sql);
        
        if ($result) {
            // Xóa tất cả phòng ban cũ và thêm phòng ban mới
            query("DELETE FROM project_departments WHERE project_id = $project_id");
            
            // Thêm phòng ban mới cho dự án
            foreach ($departments as $department_id) {
                $department_id = (int)$department_id;
                $sql = "INSERT INTO project_departments (project_id, department_id, assigned_at) 
                        VALUES ($project_id, $department_id, NOW())";
                query($sql);
            }
            
            // Nếu dự án đã hoàn thành (status_id = 3), cập nhật tất cả công việc chưa hoàn thành thành đã hoàn thành
            if ($status_id == 3 && $project['status_id'] != 3) {
                $complete_tasks_sql = "UPDATE tasks SET status_id = 3, progress = 100, completed_date = CURDATE() 
                                     WHERE project_id = $project_id AND status_id != 3 AND status_id != 5";
                query($complete_tasks_sql);
            }
            
            // Ghi log và thông báo
            set_flash_message('Cập nhật dự án thành công');
            
            // Chuyển hướng đến trang chi tiết dự án
            redirect('modules/projects/view.php?id=' . $project_id);
        } else {
            $errors[] = 'Có lỗi xảy ra khi cập nhật dự án';
        }
    }
}

// Lấy danh sách người dùng có thể quản lý dự án (admin, project_manager)
$manager_sql = "SELECT id, name, email FROM users WHERE role IN ('admin', 'project_manager') ORDER BY name";
$manager_result = query($manager_sql);

// Lấy danh sách phòng ban
$department_sql = "SELECT id, name FROM departments ORDER BY name";
$department_result = query($department_sql);

// Danh sách trạng thái dự án
$status_list = [
    1 => 'Chuẩn bị',
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
$page_title = "Chỉnh sửa dự án: " . $project['name'];

// Include header
include_once '../../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Chỉnh sửa dự án</h1>
        <a href="view.php?id=<?php echo $project_id; ?>" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
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

    <!-- Form chỉnh sửa dự án -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Thông tin dự án</h6>
        </div>
        <div class="card-body">
            <form action="" method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="name">Tên dự án <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required 
                                   value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : htmlspecialchars($project['name']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Mô tả dự án</label>
                            <textarea class="form-control" id="description" name="description" rows="5"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : htmlspecialchars($project['description']); ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="start_date">Ngày bắt đầu <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" required 
                                           value="<?php echo isset($_POST['start_date']) ? $_POST['start_date'] : $project['start_date']; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="end_date">Ngày kết thúc <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" required 
                                           value="<?php echo isset($_POST['end_date']) ? $_POST['end_date'] : $project['end_date']; ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="manager_id">Người quản lý dự án <span class="text-danger">*</span></label>
                            <select class="form-control" id="manager_id" name="manager_id" required>
                                <option value="">-- Chọn người quản lý --</option>
                                <?php while ($manager = fetch_array($manager_result)): ?>
                                    <option value="<?php echo $manager['id']; ?>" 
                                            <?php echo (isset($_POST['manager_id']) ? $_POST['manager_id'] : $project['manager_id']) == $manager['id'] ? 'selected' : ''; ?>>
                                        <?php echo $manager['name']; ?> (<?php echo $manager['email']; ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="status_id">Trạng thái</label>
                                    <select class="form-control" id="status_id" name="status_id">
                                        <?php foreach ($status_list as $id => $name): ?>
                                            <option value="<?php echo $id; ?>" 
                                                    <?php echo (isset($_POST['status_id']) ? $_POST['status_id'] : $project['status_id']) == $id ? 'selected' : ''; ?>>
                                                <?php echo $name; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if ($project['status_id'] != 3): ?>
                                    <div class="small text-info mt-1">
                                        <i class="fas fa-info-circle"></i> Lưu ý: Nếu chọn trạng thái "Hoàn thành", tất cả công việc chưa hoàn thành sẽ được tự động đánh dấu là hoàn thành.
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="priority">Mức độ ưu tiên</label>
                                    <select class="form-control" id="priority" name="priority">
                                        <?php foreach ($priority_list as $id => $name): ?>
                                            <option value="<?php echo $id; ?>" 
                                                    <?php echo (isset($_POST['priority']) ? $_POST['priority'] : $project['priority']) == $id ? 'selected' : ''; ?>>
                                                <?php echo $name; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group" id="departments">
                            <label>Phòng ban tham gia <span class="text-danger">*</span></label>
                            <div class="card">
                                <div class="card-body" style="max-height: 200px; overflow-y: auto;">
                                    <?php
                                    $selected_departments = isset($_POST['departments']) ? $_POST['departments'] : $assigned_departments;
                                    
                                    // Reset con trỏ đến đầu result set
                                    mysqli_data_seek($department_result, 0);
                                    
                                    while ($department = fetch_array($department_result)):
                                    ?>
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" 
                                                   id="dept-<?php echo $department['id']; ?>" 
                                                   name="departments[]" 
                                                   value="<?php echo $department['id']; ?>" 
                                                   <?php echo in_array($department['id'], $selected_departments) ? 'checked' : ''; ?>>
                                            <label class="custom-control-label" for="dept-<?php echo $department['id']; ?>">
                                                <?php echo $department['name']; ?>
                                            </label>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <hr>
                
                <div class="row">
                    <div class="col-md-12 text-right">
                        <a href="view.php?id=<?php echo $project_id; ?>" class="btn btn-secondary">Hủy</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Lưu thay đổi
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../../templates/footer.php';
?> 