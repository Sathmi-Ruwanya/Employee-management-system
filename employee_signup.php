<?php
include 'db.php';

$conn->query("ALTER TABLE employees ADD COLUMN approval_status ENUM('pending', 'approved') NOT NULL DEFAULT 'pending'");

$name = $_POST['name'];
$age = $_POST['age'];
$gender = $_POST['gender'];
$role = $_POST['role'];
$birthday = $_POST['birthday'];
$address = $_POST['address'];
$email = $_POST['email'];
$password = $_POST['password'];
$confirm_password = $_POST['confirm_password'];

if ($password !== $confirm_password) {
    echo "<script>alert('Passwords do not match'); window.location.href='employee_signup.html';</script>";
    exit();
}

// Check if email already exists
$sql_check = "SELECT * FROM employees WHERE email='$email'";
$result_check = $conn->query($sql_check);

if ($result_check->num_rows > 0) {
    echo "<script>alert('Email already exists'); window.location.href='employee_signup.html';</script>";
    exit();
}

// Hash the password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Insert into database
$sql = "INSERT INTO employees (name, age, gender, role, birthday, address, email, password, approval_status) VALUES ('$name', '$age', '$gender', '$role', '$birthday', '$address', '$email', '$hashed_password', 'pending')";

if ($conn->query($sql) === TRUE) {
    echo "<script>window.location.href='employee_pending.html';</script>";
} else {
    echo "<script>alert('Error: " . $sql . "<br>" . $conn->error . "'); window.location.href='employee_signup.html';</script>";
}

$conn->close();
?>