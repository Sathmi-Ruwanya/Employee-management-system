<?php
session_start();
include 'db.php';

$conn->query("CREATE TABLE IF NOT EXISTS employee_login_logs (id INT AUTO_INCREMENT PRIMARY KEY, employee_id INT NOT NULL, login_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, logout_time DATETIME NULL, INDEX idx_employee_id (employee_id), CONSTRAINT fk_employee_login_logs_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE)");
$conn->query("ALTER TABLE employees ADD COLUMN approval_status ENUM('pending', 'approved') NOT NULL DEFAULT 'pending'");

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    echo "<script>alert('Email and password are required'); window.location.href='employee_login.html';</script>";
    exit();
}

$stmt = $conn->prepare("SELECT id, email, password, approval_status FROM employees WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $employee = $result->fetch_assoc();

    $isPasswordValid = false;
    if (password_verify($password, $employee['password'])) {
        $isPasswordValid = true;
    } elseif ($password === $employee['password']) {
        $isPasswordValid = true;
    }

    if ($isPasswordValid) {
        if (($employee['approval_status'] ?? 'pending') !== 'approved') {
            echo "<script>alert('Your account is pending. Please wait until admin approves you.'); window.location.href='employee_pending.html';</script>";
            exit();
        }

        $closeOpenStmt = $conn->prepare("UPDATE employee_login_logs SET logout_time = NOW() WHERE employee_id = ? AND logout_time IS NULL");
        $closeOpenStmt->bind_param("i", $employee['id']);
        $closeOpenStmt->execute();

        $loginLogStmt = $conn->prepare("INSERT INTO employee_login_logs (employee_id, login_time) VALUES (?, NOW())");
        $loginLogStmt->bind_param("i", $employee['id']);
        $loginLogStmt->execute();

        $_SESSION['employee_id'] = (int) $employee['id'];
        $_SESSION['employee_email'] = $employee['email'];
        $_SESSION['employee_login_log_id'] = (int) $conn->insert_id;

        header("Location: employee_dashboard.php");
        exit();
    }

    echo "<script>alert('Invalid Email or Password'); window.location.href='employee_login.html';</script>";
    exit();
} else {
    echo "<script>alert('Invalid Email or Password'); window.location.href='employee_login.html';</script>";
    exit();
}
?>

