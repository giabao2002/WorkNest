<?php
/**
 * API lấy danh sách nhân viên theo phòng ban
 */

// Include config
require_once '../../config/config.php';

// Kiểm tra đăng nhập
if (!is_logged_in()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Kiểm tra tham số phòng ban
if (!isset($_GET['department_id']) || empty($_GET['department_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing department_id parameter']);
    exit;
}

$department_id = (int)$_GET['department_id'];

// Lấy danh sách nhân viên theo phòng ban
$users_sql = "SELECT id, name, email FROM users WHERE department_id = $department_id ORDER BY name";
$users_result = query($users_sql);

$users = [];
if (num_rows($users_result) > 0) {
    while ($user = fetch_array($users_result)) {
        $users[] = [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email']
        ];
    }
}

// Trả về kết quả dạng JSON
header('Content-Type: application/json');
echo json_encode($users);
exit;
?> 