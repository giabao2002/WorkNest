<?php
/**
 * Chỉnh sửa thông tin phòng ban
 */

// Include config
require_once '../../config/config.php';

// Kiểm tra đăng nhập và quyền hạn
if (!is_logged_in() || (!has_permission('admin') && !has_permission('project_manager'))) {
    set_flash_message('Bạn không có quyền truy cập trang này', 'danger');
    redirect('dashboard.php');
}

// Kiểm tra ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    set_flash_message('ID phòng ban không hợp lệ', 'danger');
    redirect('modules/departments/index.php');
}

$department_id = (int)$_GET['id'];

// Lấy thông tin phòng ban
$department_query = query("SELECT * FROM departments WHERE id = $department_id");
if (num_rows($department_query) === 0) {
    set_flash_message('Không tìm thấy phòng ban', 'danger');
    redirect('modules/departments/index.php');
}

$department = fetch_array($department_query);

// Xử lý form chỉnh sửa
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = escape_string($_POST['name']);
    $description = escape_string($_POST['description']);
    $manager_id = !empty($_POST['manager_id']) ? (int)$_POST['manager_id'] : 'NULL';
    
    // Kiểm tra tên phòng ban
    if (empty($name)) {
        $error = 'Vui lòng nhập tên phòng ban';
    } else {
        // Kiểm tra tên phòng ban đã tồn tại chưa (ngoại trừ phòng ban hiện tại)
        $check_query = query("SELECT id FROM departments WHERE name = '$name' AND id != $department_id");
        if (num_rows($check_query) > 0) {
            $error = 'Tên phòng ban đã tồn tại';
        } else {
            // Cập nhật thông tin phòng ban
            $sql = "UPDATE departments SET 
                    name = '$name', 
                    description = '$description', 
                    manager_id = $manager_id,
                    updated_at = NOW()
                    WHERE id = $department_id";
            $result = query($sql);
            
            if ($result) {
                // Nếu có thay đổi trưởng phòng, cập nhật vai trò người dùng
                if ($manager_id !== 'NULL' && $department['manager_id'] != $manager_id) {
                    // Cập nhật vai trò người dùng nếu chưa phải là trưởng phòng hoặc quản lý dự án
                    query("UPDATE users SET role = 'department_manager' 
                           WHERE id = $manager_id AND role = 'staff'");
                }
                
                set_flash_message('Cập nhật phòng ban thành công');
                redirect('modules/departments/view.php?id=' . $department_id);
            } else {
                $error = 'Có lỗi xảy ra, vui lòng thử lại';
            }
        }
    }
}

// Lấy danh sách người dùng có thể làm trưởng phòng
$user_query = query("SELECT id, name, email, role FROM users WHERE role IN ('project_manager', 'department_manager', 'staff') ORDER BY name");

// Tiêu đề trang
$page_title = "Chỉnh sửa phòng ban: " . $department['name'];

// Include header
include_once '../../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Chỉnh sửa phòng ban</h1>
        <a href="view.php?id=<?php echo $department_id; ?>" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50"></i> Quay lại
        </a>
    </div>

    <!-- Content Row -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Thông tin phòng ban</h6>
        </div>
        <div class="card-body">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>
            
            <form action="" method="POST">
                <div class="form-group">
                    <label for="name">Tên phòng ban <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name" name="name" required 
                           value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : htmlspecialchars($department['name']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="description">Mô tả</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : htmlspecialchars($department['description']); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="manager_id">Trưởng phòng</label>
                    <select class="form-control" id="manager_id" name="manager_id">
                        <option value="">-- Chọn trưởng phòng --</option>
                        <?php while ($user = fetch_array($user_query)): ?>
                            <option value="<?php echo $user['id']; ?>" 
                                <?php 
                                $selected_manager = isset($_POST['manager_id']) ? $_POST['manager_id'] : $department['manager_id'];
                                echo ($selected_manager == $user['id']) ? 'selected' : ''; 
                                ?>>
                                <?php echo $user['name']; ?> (<?php echo $user['email']; ?>) - 
                                <?php 
                                $role_names = [
                                    'admin' => 'Quản trị viên',
                                    'project_manager' => 'Quản lý dự án',
                                    'department_manager' => 'Trưởng phòng',
                                    'staff' => 'Nhân viên'
                                ];
                                echo $role_names[$user['role']];
                                ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
                <a href="view.php?id=<?php echo $department_id; ?>" class="btn btn-secondary">Hủy</a>
            </form>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../../templates/footer.php';
?> 