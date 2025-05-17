<?php
/**
 * Danh sách phòng ban
 */

// Include config
require_once '../../config/config.php';

// Kiểm tra đăng nhập
if (!is_logged_in()) {
    redirect('index.php');
}

// Phân trang
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Tìm kiếm
$search = isset($_GET['search']) ? escape_string($_GET['search']) : '';
$search_condition = '';
if (!empty($search)) {
    $search_condition = " WHERE name LIKE '%$search%' OR description LIKE '%$search%'";
}

// Lấy tổng số phòng ban
$total_query = query("SELECT COUNT(*) as total FROM departments$search_condition");
$total_row = fetch_array($total_query);
$total = $total_row['total'];
$total_pages = ceil($total / $limit);

// Lấy danh sách phòng ban
$sql = "SELECT d.*, u.name as manager_name 
        FROM departments d 
        LEFT JOIN users u ON d.manager_id = u.id
        $search_condition
        ORDER BY d.id DESC 
        LIMIT $offset, $limit";
$result = query($sql);

// Tiêu đề trang
$page_title = "Quản lý phòng ban";

// Include header
include_once '../../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Quản lý phòng ban</h1>
        
        <?php if (has_permission('admin') || has_permission('project_manager')): ?>
        <a href="add.php" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
            <i class="fas fa-plus fa-sm text-white-50"></i> Thêm phòng ban
        </a>
        <?php endif; ?>
    </div>

    <!-- Content Row -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h6 class="m-0 font-weight-bold text-primary">Danh sách phòng ban</h6>
                </div>
                <div class="col-md-6">
                    <form action="" method="GET" class="float-right">
                        <div class="input-group">
                            <input type="text" name="search" class="form-control small" placeholder="Tìm kiếm..." value="<?php echo $search; ?>">
                            <div class="input-group-append">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search fa-sm"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php 
            // Hiển thị thông báo
            $flash_message = get_flash_message();
            if (!empty($flash_message)) {
                echo '<div class="alert alert-' . $flash_message['type'] . ' alert-dismissible fade show" role="alert">
                    ' . $flash_message['message'] . '
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>';
            }
            ?>
            
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tên phòng ban</th>
                            <th>Mô tả</th>
                            <th>Trưởng phòng</th>
                            <th>Ngày tạo</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (num_rows($result) > 0): ?>
                            <?php while ($row = fetch_array($result)): ?>
                                <tr>
                                    <td><?php echo $row['id']; ?></td>
                                    <td><?php echo $row['name']; ?></td>
                                    <td><?php echo $row['description']; ?></td>
                                    <td><?php echo $row['manager_name'] ?? 'Chưa phân công'; ?></td>
                                    <td><?php echo format_date($row['created_at']); ?></td>
                                    <td>
                                        <a href="view.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if (has_permission('admin') || has_permission('project_manager')): ?>
                                        <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="delete.php?id=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" 
                                           onclick="return confirm('Bạn có chắc muốn xóa phòng ban này?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">Không có phòng ban nào</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Phân trang -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mt-4">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo !empty($search) ? '&search='.$search : ''; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search='.$search : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo !empty($search) ? '&search='.$search : ''; ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../../templates/footer.php';
?> 