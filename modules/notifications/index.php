<?php
/**
 * Trang quản lý thông báo
 */

// Include file cấu hình
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Kiểm tra đăng nhập
if (!is_logged_in()) {
    redirect(BASE_URL . 'index.php');
}

// Lấy ID user hiện tại
$user_id = $_SESSION['user_id'];

// Xử lý đánh dấu tất cả đã đọc
if (isset($_GET['mark_all_read'])) {
    mark_all_notifications_as_read($user_id);
    set_flash_message('Đã đánh dấu tất cả thông báo là đã đọc');
    redirect(BASE_URL . 'modules/notifications/');
}

// Phân trang
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; // Số thông báo mỗi trang
$offset = ($page - 1) * $limit;

// Lấy tổng số thông báo
$sql = "SELECT COUNT(*) as total FROM notifications WHERE user_id = $user_id";
$result = query($sql);
$data = fetch_array($result);
$total_notifications = $data['total'];

// Tính tổng số trang
$total_pages = ceil($total_notifications / $limit);

// Đảm bảo trang hiện tại không vượt quá tổng số trang
if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
    $offset = ($page - 1) * $limit;
}

// Lấy danh sách thông báo
$notifications = get_notifications($user_id, $limit, $offset);

// Tiêu đề trang
$page_title = "Quản lý thông báo";

// Include header
include_once '../../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Quản lý thông báo</h1>
        
        <div>
            <?php if (count_unread_notifications($user_id) > 0): ?>
            <a href="?mark_all_read=1" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm me-2">
                <i class="fas fa-check fa-sm text-white-50"></i> Đánh dấu tất cả đã đọc
            </a>
            <?php endif; ?>
            
            <?php if ($total_notifications > 0): ?>
            <a href="delete_all.php" class="d-none d-sm-inline-block btn btn-sm btn-danger shadow-sm" 
               onclick="return confirm('Bạn có chắc chắn muốn xóa tất cả thông báo?')">
                <i class="fas fa-trash fa-sm text-white-50"></i> Xóa tất cả
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <?php
    // Hiển thị thông báo flash nếu có
    $flash_message = get_flash_message();
    if (!empty($flash_message)):
    ?>
    <div class="alert alert-<?php echo $flash_message['type']; ?> alert-dismissible fade show" role="alert">
        <?php echo $flash_message['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Danh sách thông báo</h6>
            <span class="badge bg-primary"><?php echo $total_notifications; ?> thông báo</span>
        </div>
        <div class="card-body">
            <?php if (empty($notifications)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                    <p class="text-muted">Bạn chưa có thông báo nào</p>
                </div>
            <?php else: ?>
                <div class="list-group notification-list">
                    <?php foreach ($notifications as $notification): ?>
                        <div class="list-group-item list-group-item-action <?php echo $notification['is_read'] ? '' : 'unread bg-light'; ?>">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 <?php echo $notification['is_read'] ? 'text-muted' : 'text-primary'; ?>">
                                        <?php echo $notification['is_read'] ? '' : '<i class="fas fa-circle text-primary me-1 small"></i>'; ?>
                                        <?php echo $notification['message']; ?>
                                    </h6>
                                    <small class="text-muted"><?php echo format_datetime($notification['created_at']); ?></small>
                                </div>
                                <div class="btn-group">
                                    <?php if (!$notification['is_read']): ?>
                                        <a href="mark_read.php?id=<?php echo $notification['id']; ?>&redirect=<?php echo urlencode(BASE_URL . 'modules/notifications/'); ?>" 
                                           class="btn btn-sm btn-outline-primary" title="Đánh dấu đã đọc">
                                            <i class="fas fa-check"></i>
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($notification['link'] && $notification['link'] != '#'): ?>
                                        <a href="mark_read.php?id=<?php echo $notification['id']; ?>&redirect=<?php echo urlencode($notification['link']); ?>" 
                                           class="btn btn-sm btn-outline-secondary" title="Đi đến nội dung">
                                            <i class="fas fa-external-link-alt"></i>
                                        </a>
                                    <?php endif; ?>

                                    <a href="delete.php?id=<?php echo $notification['id']; ?>" 
                                       class="btn btn-sm btn-outline-danger" 
                                       onclick="return confirm('Bạn có chắc chắn muốn xóa thông báo này?')"
                                       title="Xóa thông báo">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($total_pages > 1): ?>
                    <!-- Phân trang -->
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .notification-list .unread {
        border-left: 3px solid var(--primary-color);
    }
</style>

<?php
// Include footer
include_once '../../templates/footer.php';
?> 