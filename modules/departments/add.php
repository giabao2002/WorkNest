<?php
/**
 * Thêm phòng ban mới
 */

// Include config
require_once '../../config/config.php';

// Kiểm tra đăng nhập và quyền hạn
if (!is_logged_in() || (!has_permission('admin') && !has_permission('project_manager'))) {
    set_flash_message('Bạn không có quyền truy cập trang này', 'danger');
    redirect('dashboard.php');
}

// Xử lý form thêm phòng ban
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = escape_string($_POST['name']);
    $description = escape_string($_POST['description']);
    $manager_id = !empty($_POST['manager_id']) ? (int)$_POST['manager_id'] : 'NULL';
    
    // Kiểm tra tên phòng ban
    if (empty($name)) {
        $error = 'Vui lòng nhập tên phòng ban';
    } else {
        // Kiểm tra phòng ban đã tồn tại chưa
        $check_query = query("SELECT id FROM departments WHERE name = '$name'");
        if (num_rows($check_query) > 0) {
            $error = 'Tên phòng ban đã tồn tại';
        } else {
            // Thêm phòng ban mới
            $sql = "INSERT INTO departments (name, description, manager_id) VALUES ('$name', '$description', $manager_id)";
            $result = query($sql);
            
            if ($result) {
                set_flash_message('Thêm phòng ban thành công');
                redirect('modules/departments/index.php');
            } else {
                $error = 'Có lỗi xảy ra, vui lòng thử lại';
            }
        }
    }
}

// Lấy danh sách người dùng có thể làm trưởng phòng
$user_query = query("SELECT id, name, email FROM users WHERE role IN ('project_manager', 'department_manager') ORDER BY name");

// Tiêu đề trang
$page_title = "Thêm phòng ban mới";

// Include header
include_once '../../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Thêm phòng ban mới</h1>
        <a href="index.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
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
                           value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="description">Mô tả</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="manager_id">Trưởng phòng</label>
                    <select class="form-control" id="manager_id" name="manager_id">
                        <option value="">-- Chọn trưởng phòng --</option>
                        <?php while ($user = fetch_array($user_query)): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo (isset($_POST['manager_id']) && $_POST['manager_id'] == $user['id']) ? 'selected' : ''; ?>>
                                <?php echo $user['name']; ?> (<?php echo $user['email']; ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">Thêm phòng ban</button>
                <a href="index.php" class="btn btn-secondary">Hủy</a>
            </form>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../../templates/footer.php';
?> 