/**
 * WorkNest - Hệ thống quản lý công việc
 * Main JavaScript
 */

$(document).ready(function() {
    
    // Tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
    
    // Popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl)
    });
    
    // Xác nhận xóa
    $('.delete-confirm').on('click', function(e) {
        if (!confirm('Bạn có chắc chắn muốn xóa mục này?')) {
            e.preventDefault();
        }
    });
    
    // Animation cho các phần tử mới xuất hiện
    $('.fade-in').addClass('show');
    
    // Điều chỉnh thanh tiến độ tương ứng với giá trị
    $('.progress-bar').each(function() {
        var value = $(this).attr('aria-valuenow');
        $(this).css('width', value + '%');
    });
    
    // Bật tắt sidebar
    $('#sidebarToggle').on('click', function() {
        $('#sidebar').toggleClass('collapsed');
        $('#content').toggleClass('expanded');
    });
    
    // Auto-resize cho textarea
    $('textarea.auto-resize').each(function() {
        this.setAttribute('style', 'height:' + (this.scrollHeight) + 'px;overflow-y:hidden;');
    }).on('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });
    
    // Form validation
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
    
    // Hiệu ứng khi cuộn trang
    $(window).scroll(function() {
        if ($(this).scrollTop() > 100) {
            $('.back-to-top').fadeIn();
            $('.navbar').addClass('navbar-scrolled');
        } else {
            $('.back-to-top').fadeOut();
            $('.navbar').removeClass('navbar-scrolled');
        }
    });
    
    // Nút quay lại đầu trang
    $('.back-to-top').click(function() {
        $('html, body').animate({scrollTop: 0}, 800);
        return false;
    });
    
    // Datepicker
    if ($.fn.datepicker) {
        $('.datepicker').datepicker({
            format: 'dd/mm/yyyy',
            autoclose: true,
            todayHighlight: true,
            language: 'vi'
        });
    }
    
    // Hiển thị tên file khi chọn file
    $('.custom-file-input').on('change', function() {
        var fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').html(fileName);
    });
    
    // Tìm kiếm trong bảng
    $("#tableSearch").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        $("#dataTable tbody tr").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });
    
    // Chọn tất cả checkbox
    $('#selectAll').click(function(e) {
        var table = $(e.target).closest('table');
        $('td input:checkbox', table).prop('checked', this.checked);
    });
    
    // Xử lý khi dropdown thay đổi giá trị trạng thái công việc
    $('.task-status-dropdown').on('change', function() {
        var taskId = $(this).data('task-id');
        var statusId = $(this).val();
        
        // Gửi Ajax để cập nhật trạng thái
        $.ajax({
            url: BASE_URL + 'modules/tasks/update_status.php',
            type: 'POST',
            data: {
                task_id: taskId,
                status_id: statusId,
                csrf_token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                var data = JSON.parse(response);
                if (data.success) {
                    // Hiển thị thông báo thành công
                    showToast('Cập nhật trạng thái thành công', 'success');
                    
                    // Đổi màu badge
                    var badgeClass = 'badge bg-' + data.color;
                    $('#task-' + taskId + '-badge')
                        .attr('class', badgeClass)
                        .text(data.status_text);
                        
                    // Cập nhật tiến độ
                    if (data.progress !== undefined) {
                        $('#task-' + taskId + '-progress')
                            .attr('aria-valuenow', data.progress)
                            .css('width', data.progress + '%')
                            .text(data.progress + '%');
                    }
                } else {
                    // Hiển thị thông báo lỗi
                    showToast(data.message || 'Có lỗi xảy ra', 'danger');
                    
                    // Đặt lại giá trị cũ
                    $(this).val(data.old_status_id);
                }
            },
            error: function() {
                showToast('Đã xảy ra lỗi khi cập nhật trạng thái', 'danger');
            }
        });
    });
    
    // Hiển thị toast thông báo
    function showToast(message, type = 'info') {
        var toast = $('<div class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3000">');
        toast.append('<div class="toast-header bg-' + type + ' text-white">' +
                        '<strong class="me-auto">Thông báo</strong>' +
                        '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>' +
                     '</div>');
        toast.append('<div class="toast-body">' + message + '</div>');
        
        $('#toastContainer').append(toast);
        var bsToast = new bootstrap.Toast(toast[0]);
        bsToast.show();
        
        // Xóa toast sau khi ẩn
        toast.on('hidden.bs.toast', function() {
            $(this).remove();
        });
    }
    
    // Kiểm tra mật khẩu
    $('#newPassword, #confirmPassword').on('keyup', function() {
        var password = $('#newPassword').val();
        var confirm = $('#confirmPassword').val();
        
        if (password === confirm) {
            $('#passwordMatch').html('<span class="text-success">Mật khẩu khớp</span>');
        } else {
            $('#passwordMatch').html('<span class="text-danger">Mật khẩu không khớp</span>');
        }
    });
    
    // Hiện/ẩn mật khẩu
    $('.password-toggle').on('click', function() {
        var input = $($(this).data('target'));
        var icon = $(this).find('i');
        
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            input.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });
    
    // Tạo biểu đồ nếu có thư viện Chart.js
    if (typeof Chart !== 'undefined') {
        renderDashboardCharts();
    }
    
    function renderDashboardCharts() {
        // Biểu đồ thống kê công việc theo trạng thái
        if ($('#taskStatusChart').length) {
            var statusCtx = document.getElementById('taskStatusChart').getContext('2d');
            var statusChart = new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: taskStatusLabels,
                    datasets: [{
                        data: taskStatusData,
                        backgroundColor: taskStatusColors,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
        
        // Biểu đồ tiến độ dự án
        if ($('#projectProgressChart').length) {
            var progressCtx = document.getElementById('projectProgressChart').getContext('2d');
            var progressChart = new Chart(progressCtx, {
                type: 'bar',
                data: {
                    labels: projectLabels,
                    datasets: [{
                        label: 'Tiến độ (%)',
                        data: projectProgressData,
                        backgroundColor: 'rgba(13, 110, 253, 0.6)',
                        borderColor: 'rgba(13, 110, 253, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });
        }
    }
}); 