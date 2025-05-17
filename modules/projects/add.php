<?php
/**
 * Thêm dự án mới
 */

// Include config
require_once '../../config/config.php';

// Kiểm tra đăng nhập và quyền hạn
if (!is_logged_in() || (!has_permission('admin') && !has_permission('project_manager'))) {
    set_flash_message('Bạn không có quyền truy cập trang này', 'danger');
    redirect('dashboard.php');
}

// Xử lý form thêm dự án
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
    
    // Nếu không có lỗi, thêm dự án mới
    if (empty($errors)) {
        // Thêm dự án
        $sql = "INSERT INTO projects (name, description, start_date, end_date, status_id, priority, manager_id, created_at) 
                VALUES ('$name', '$description', '$start_date', '$end_date', $status_id, $priority, $manager_id, NOW())";
        $result = query($sql);
        
        if ($result) {
            $project_id = last_id();
            
            // Thêm phòng ban cho dự án
            foreach ($departments as $department_id) {
                $department_id = (int)$department_id;
                $sql = "INSERT INTO project_departments (project_id, department_id, assigned_at) 
                        VALUES ($project_id, $department_id, NOW())";
                query($sql);
            }
            
            // Ghi log và thông báo
            set_flash_message('Thêm dự án thành công');
            
            // Chuyển hướng đến trang chi tiết dự án
            redirect('modules/projects/view.php?id=' . $project_id);
        } else {
            $errors[] = 'Có lỗi xảy ra khi thêm dự án';
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
$page_title = "Thêm dự án mới";

// Include header
include_once '../../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Thêm dự án mới</h1>
        <a href="index.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
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

    <!-- Form thêm dự án -->
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
                                   value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Mô tả dự án</label>
                            <textarea class="form-control" id="description" name="description" rows="5"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
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
                                    <label for="end_date">Ngày kết thúc <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" required 
                                           value="<?php echo isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d', strtotime('+30 days')); ?>">
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
                                    <option value="<?php echo $manager['id']; ?>" <?php echo (isset($_POST['manager_id']) && $_POST['manager_id'] == $manager['id']) ? 'selected' : ''; ?>>
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
                                            <option value="<?php echo $id; ?>" <?php echo (isset($_POST['status_id']) ? $_POST['status_id'] : 1) == $id ? 'selected' : ''; ?>>
                                                <?php echo $name; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="priority">Mức độ ưu tiên</label>
                                    <select class="form-control" id="priority" name="priority">
                                        <?php foreach ($priority_list as $id => $name): ?>
                                            <option value="<?php echo $id; ?>" <?php echo (isset($_POST['priority']) ? $_POST['priority'] : 2) == $id ? 'selected' : ''; ?>>
                                                <?php echo $name; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Phòng ban tham gia <span class="text-danger">*</span></label>
                            <div class="card">
                                <div class="card-body" style="max-height: 200px; overflow-y: auto;">
                                    <?php
                                    $selected_departments = isset($_POST['departments']) ? $_POST['departments'] : [];
                                    
                                    while ($department = fetch_array($department_result)):
                                    ?>
                                        <div class="form-check mb-2">
                                            <input type="checkbox" class="form-check-input" 
                                                   id="dept-<?php echo $department['id']; ?>" 
                                                   name="departments[]" 
                                                   value="<?php echo $department['id']; ?>" 
                                                   <?php echo in_array($department['id'], $selected_departments) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="dept-<?php echo $department['id']; ?>">
                                                <?php echo $department['name']; ?>
                                            </label>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                            <div class="mt-2">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="selectAllDepts">Chọn tất cả</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAllDepts">Bỏ chọn tất cả</button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <hr>
                
                <div class="row">
                    <div class="col-md-12 text-right">
                        <a href="index.php" class="btn btn-secondary">Hủy</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Tạo dự án
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Select/Deselect all departments
    document.getElementById('selectAllDepts').addEventListener('click', function() {
        const checkboxes = document.querySelectorAll('input[name="departments[]"]');
        checkboxes.forEach(checkbox => checkbox.checked = true);
    });
    
    document.getElementById('deselectAllDepts').addEventListener('click', function() {
        const checkboxes = document.querySelectorAll('input[name="departments[]"]');
        checkboxes.forEach(checkbox => checkbox.checked = false);
    });
});
</script> 