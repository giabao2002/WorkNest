    </div><!-- Container End -->

    <!-- Footer -->
    <footer class="footer mt-5 py-3 bg-dark text-white">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6">
                    <p class="small mt-2">Hệ thống quản lý công việc toàn diện cho doanh nghiệp</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="small">&copy; <?php echo date('Y'); ?> WorkNest. Đã đăng ký bản quyền.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script src="<?php echo BASE_URL; ?>assets/js/main.js"></script>
    
    <!-- Script phát hiện hoạt động người dùng -->
    <script>
        $(document).ready(function() {
            let idleTime = 0;
            
            // Tăng idleTime mỗi phút
            const idleInterval = setInterval(timerIncrement, 60000);
            
            // Đặt lại idleTime khi có sự kiện người dùng
            $(this).mousemove(function(e) {
                idleTime = 0;
            });
            $(this).keypress(function(e) {
                idleTime = 0;
            });
            
            // Tự động đăng xuất sau 30 phút không hoạt động
            function timerIncrement() {
                idleTime++;
                if (idleTime > 30) {
                    window.location.href = "<?php echo BASE_URL; ?>logout.php?timeout=1";
                }
            }
            
            // Hiển thị thông báo toast
            $('.toast').toast('show');
            
            // Xóa thông báo flash sau 5 giây
            setTimeout(function() {
                $('.alert.alert-dismissible').alert('close');
            }, 5000);
        });
    </script>
</body>
</html> 