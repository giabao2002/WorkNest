<?php
/**
 * Cấu hình kết nối cơ sở dữ liệu MySQL
 */

// Thông tin kết nối
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'work_nest');

// Tạo kết nối
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Kiểm tra kết nối
if (!$conn) {
    die("Kết nối thất bại: " . mysqli_connect_error());
}

// Đặt charset là utf8mb4
mysqli_set_charset($conn, "utf8mb4");

// Hàm escape string để tránh lỗi SQL injection
function escape_string($string) {
    global $conn;
    return mysqli_real_escape_string($conn, $string);
}

// Hàm thực hiện câu truy vấn
function query($sql) {
    global $conn;
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        // In ra lỗi để dễ dàng debug
        echo "<p>Lỗi SQL: " . mysqli_error($conn) . "</p>";
        echo "<p>Câu truy vấn: " . $sql . "</p>";
    }
    return $result;
}

// Hàm lấy dữ liệu dạng mảng liên kết
function fetch_array($result) {
    return mysqli_fetch_assoc($result);
}

// Hàm lấy số hàng
function num_rows($result) {
    return mysqli_num_rows($result);
}

// Hàm lấy ID được tạo gần nhất
function last_id() {
    global $conn;
    return mysqli_insert_id($conn);
}

// Hàm đóng kết nối
function close_connection() {
    global $conn;
    mysqli_close($conn);
} 