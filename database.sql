-- Tạo cơ sở dữ liệu
CREATE DATABASE IF NOT EXISTS work_nest CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE work_nest;

-- Bảng người dùng
CREATE TABLE IF NOT EXISTS users (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'project_manager', 'department_manager', 'staff') NOT NULL DEFAULT 'staff',
    department_id INT(11) NULL,
    phone VARCHAR(20) NULL,
    avatar VARCHAR(255) NULL DEFAULT 'assets/images/avatar-default.png',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    last_login DATETIME NULL,
    INDEX (department_id),
    INDEX (role)
) ENGINE=InnoDB;

-- Bảng phòng ban
CREATE TABLE IF NOT EXISTS departments (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    manager_id INT(11) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX (manager_id)
) ENGINE=InnoDB;

-- Bảng dự án
CREATE TABLE IF NOT EXISTS projects (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status_id TINYINT(1) NOT NULL DEFAULT 1,
    priority TINYINT(1) NOT NULL DEFAULT 2,
    manager_id INT(11) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX (manager_id),
    INDEX (status_id)
) ENGINE=InnoDB;

-- Bảng phân công dự án cho phòng ban
CREATE TABLE IF NOT EXISTS project_departments (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    project_id INT(11) NOT NULL,
    department_id INT(11) NOT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    note TEXT NULL,
    INDEX (project_id),
    INDEX (department_id),
    UNIQUE (project_id, department_id)
) ENGINE=InnoDB;

-- Bảng công việc
CREATE TABLE IF NOT EXISTS tasks (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    project_id INT(11) NOT NULL,
    department_id INT(11) NULL,
    assigned_to INT(11) NULL,
    assigned_by INT(11) NOT NULL,
    status_id TINYINT(1) NOT NULL DEFAULT 1,
    priority TINYINT(1) NOT NULL DEFAULT 2,
    start_date DATE NOT NULL,
    due_date DATE NOT NULL,
    completed_date DATE NULL,
    progress TINYINT(3) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    parent_id INT(11) NULL DEFAULT NULL,
    INDEX (project_id),
    INDEX (department_id),
    INDEX (assigned_to),
    INDEX (assigned_by),
    INDEX (status_id),
    INDEX (parent_id)
) ENGINE=InnoDB;

-- Bảng file đính kèm
CREATE TABLE IF NOT EXISTS attachments (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    task_id INT(11) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(100) NULL,
    file_size INT(11) NULL,
    uploaded_by INT(11) NOT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (task_id),
    INDEX (uploaded_by)
) ENGINE=InnoDB;

-- Bảng bình luận
CREATE TABLE IF NOT EXISTS comments (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    task_id INT(11) NOT NULL,
    user_id INT(11) NOT NULL,
    content TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX (task_id),
    INDEX (user_id)
) ENGINE=InnoDB;

-- Bảng thông báo
CREATE TABLE IF NOT EXISTS notifications (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    message VARCHAR(255) NOT NULL,
    link VARCHAR(255) NULL DEFAULT '#',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    INDEX (user_id),
    INDEX (is_read)
) ENGINE=InnoDB;

-- Bảng đánh giá công việc
CREATE TABLE IF NOT EXISTS task_evaluations (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    task_id INT(11) NOT NULL,
    rated_by INT(11) NOT NULL,
    score TINYINT(3) NOT NULL,
    comment TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (task_id),
    INDEX (rated_by),
    UNIQUE (task_id, rated_by)
) ENGINE=InnoDB;

-- Bảng báo cáo
CREATE TABLE IF NOT EXISTS reports (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    report_type ENUM('daily', 'weekly', 'monthly', 'project') NOT NULL,
    project_id INT(11) NULL,
    department_id INT(11) NULL,
    user_id INT(11) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX (project_id),
    INDEX (department_id),
    INDEX (user_id),
    INDEX (report_type)
) ENGINE=InnoDB;

-- Khóa ngoại
ALTER TABLE users
    ADD CONSTRAINT fk_users_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL;

ALTER TABLE departments
    ADD CONSTRAINT fk_departments_manager FOREIGN KEY (manager_id) REFERENCES users (id) ON DELETE SET NULL;

ALTER TABLE projects
    ADD CONSTRAINT fk_projects_manager FOREIGN KEY (manager_id) REFERENCES users (id) ON DELETE RESTRICT;

ALTER TABLE project_departments
    ADD CONSTRAINT fk_pd_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_pd_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE CASCADE;

ALTER TABLE tasks
    ADD CONSTRAINT fk_tasks_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_tasks_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_tasks_assigned_to FOREIGN KEY (assigned_to) REFERENCES users (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_tasks_assigned_by FOREIGN KEY (assigned_by) REFERENCES users (id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_tasks_parent FOREIGN KEY (parent_id) REFERENCES tasks (id) ON DELETE SET NULL;

ALTER TABLE attachments
    ADD CONSTRAINT fk_attachments_task FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_attachments_user FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE RESTRICT;

ALTER TABLE comments
    ADD CONSTRAINT fk_comments_task FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_comments_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE;

ALTER TABLE notifications
    ADD CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE;

ALTER TABLE task_evaluations
    ADD CONSTRAINT fk_evaluations_task FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_evaluations_user FOREIGN KEY (rated_by) REFERENCES users (id) ON DELETE CASCADE;

ALTER TABLE reports
    ADD CONSTRAINT fk_reports_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_reports_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_reports_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE;

-- Dữ liệu mẫu: Admin
INSERT INTO users (name, email, password, role) VALUES 
('Admin', 'admin@worknest.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Dữ liệu mẫu: Phòng ban
INSERT INTO departments (name, description) VALUES
('Ban Giám đốc', 'Phòng ban quản lý cấp cao'),
('Phòng Kế hoạch', 'Phòng ban phụ trách kế hoạch và dự án'),
('Phòng Kỹ thuật', 'Phòng ban phụ trách kỹ thuật và phát triển'),
('Phòng Marketing', 'Phòng ban phụ trách marketing và quảng cáo');

-- Dữ liệu mẫu: Người dùng
INSERT INTO users (name, email, password, role, department_id) VALUES
('Nguyễn Quản lý', 'manager@worknest.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'project_manager', 1),
('Trần Trưởng phòng', 'department@worknest.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'department_manager', 2),
('Lê Nhân viên', 'staff@worknest.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', 3);

-- Cập nhật trưởng phòng cho các phòng ban
UPDATE departments SET manager_id = 2 WHERE id = 2;
UPDATE departments SET manager_id = 3 WHERE id = 3;

-- Dữ liệu mẫu: Dự án
INSERT INTO projects (name, description, start_date, end_date, status_id, priority, manager_id) VALUES
('Xây dựng Website công ty', 'Dự án xây dựng website mới cho công ty', '2023-01-01', '2023-03-31', 2, 2, 2),
('Phát triển ứng dụng di động', 'Dự án phát triển ứng dụng di động cho khách hàng', '2023-02-01', '2023-05-31', 2, 3, 2),
('Chiến dịch Marketing Q2', 'Chiến dịch marketing quý 2 năm 2023', '2023-04-01', '2023-06-30', 1, 2, 2);

-- Dữ liệu mẫu: Phân công dự án cho phòng ban
INSERT INTO project_departments (project_id, department_id) VALUES
(1, 3), -- Dự án 1 phân cho phòng Kỹ thuật
(2, 3), -- Dự án 2 phân cho phòng Kỹ thuật
(3, 4); -- Dự án 3 phân cho phòng Marketing

-- Dữ liệu mẫu: Công việc
INSERT INTO tasks (title, description, project_id, department_id, assigned_to, assigned_by, status_id, priority, start_date, due_date) VALUES
('Thiết kế giao diện website', 'Thiết kế giao diện website theo yêu cầu của khách hàng', 1, 3, 3, 2, 3, 2, '2023-01-05', '2023-01-20'),
('Lập trình front-end', 'Lập trình front-end cho website', 1, 3, 3, 2, 2, 2, '2023-01-21', '2023-02-15'),
('Lập trình back-end', 'Lập trình back-end và kết nối CSDL', 1, 3, 3, 2, 1, 2, '2023-02-16', '2023-03-10'),
('Thiết kế UI/UX cho ứng dụng', 'Thiết kế giao diện người dùng cho ứng dụng di động', 2, 3, 3, 2, 2, 3, '2023-02-05', '2023-02-28'),
('Lên kế hoạch chiến dịch', 'Lập kế hoạch chi tiết cho chiến dịch marketing Q2', 3, 4, 3, 2, 1, 2, '2023-04-01', '2023-04-15');

-- Dữ liệu mẫu: Bình luận
INSERT INTO comments (task_id, user_id, content) VALUES
(1, 2, 'Giao diện đẹp, cần điều chỉnh một số chi tiết nhỏ'),
(1, 3, 'Đã cập nhật theo góp ý'),
(2, 2, 'Đang triển khai tốt, cần đảm bảo responsive trên mobile');

-- Dữ liệu mẫu: Thông báo
INSERT INTO notifications (user_id, message, link) VALUES
(2, 'Công việc "Thiết kế giao diện website" đã hoàn thành', 'modules/tasks/view.php?id=1'),
(3, 'Bạn được giao công việc mới "Lập trình back-end"', 'modules/tasks/view.php?id=3'),
(3, 'Quản lý đã bình luận về công việc của bạn', 'modules/tasks/view.php?id=1');

-- Lưu ý: Mật khẩu mẫu là "password"