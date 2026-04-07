<?php
include 'db.php';

$email = $_POST['email'];
$password = $_POST['password'];

$sql = "SELECT * FROM admins WHERE email='$email' AND password='$password'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
   header("Location: http://localhost/EMP/admin_dashboard.php");
} else {
    echo "<script>alert('Invalid Email or Password'); window.location.href='admin_login.html';</script>";
}
?>