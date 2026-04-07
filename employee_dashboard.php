<?php
session_start();
include 'db.php';

$conn->query("CREATE TABLE IF NOT EXISTS employee_login_logs (id INT AUTO_INCREMENT PRIMARY KEY, employee_id INT NOT NULL, login_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, logout_time DATETIME NULL, INDEX idx_employee_id (employee_id), CONSTRAINT fk_employee_login_logs_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE)");

if (isset($_GET['logout']) && $_GET['logout'] === '1') {
	$employeeIdForLogout = (int) ($_SESSION['employee_id'] ?? 0);
	$loginLogId = (int) ($_SESSION['employee_login_log_id'] ?? 0);

	if ($loginLogId > 0) {
		$logoutStmt = $conn->prepare("UPDATE employee_login_logs SET logout_time = NOW() WHERE id = ? AND employee_id = ? AND logout_time IS NULL");
		$logoutStmt->bind_param("ii", $loginLogId, $employeeIdForLogout);
		$logoutStmt->execute();
	} elseif ($employeeIdForLogout > 0) {
		$fallbackLogoutStmt = $conn->prepare("UPDATE employee_login_logs SET logout_time = NOW() WHERE employee_id = ? AND logout_time IS NULL ORDER BY id DESC LIMIT 1");
		$fallbackLogoutStmt->bind_param("i", $employeeIdForLogout);
		$fallbackLogoutStmt->execute();
	}

	$_SESSION = [];
	session_destroy();
	header("Location: employee_login.html");
	exit();
}

$employeeId = (int) ($_SESSION['employee_id'] ?? 0);
if ($employeeId <= 0) {
	header("Location: employee_login.html");
	exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = $_POST['action'] ?? '';

	if ($action === 'update_profile') {
		$name = trim($_POST['name'] ?? '');
		$age = (int) ($_POST['age'] ?? 0);
		$gender = trim($_POST['gender'] ?? '');
		$birthday = trim($_POST['birthday'] ?? '');
		$address = trim($_POST['address'] ?? '');

		$allowedGenders = ['Male', 'Female', 'Other'];

		if ($name === '' || $age <= 0 || $birthday === '' || $address === '' || !in_array($gender, $allowedGenders, true)) {
			header("Location: employee_dashboard.php?status=invalid");
			exit();
		}

		$updateStmt = $conn->prepare("UPDATE employees SET name = ?, age = ?, gender = ?, birthday = ?, address = ? WHERE id = ?");
		$updateStmt->bind_param("sisssi", $name, $age, $gender, $birthday, $address, $employeeId);

		if ($updateStmt->execute()) {
			header("Location: employee_dashboard.php?status=updated");
			exit();
		}

		header("Location: employee_dashboard.php?status=failed");
		exit();
	}
}

$message = '';
$messageType = 'info';
$status = $_GET['status'] ?? '';
if ($status === 'updated') {
	$message = 'Profile updated successfully.';
	$messageType = 'success';
} elseif ($status === 'invalid') {
	$message = 'Please fill all editable fields with valid values.';
	$messageType = 'error';
} elseif ($status === 'failed') {
	$message = 'Unable to update profile right now.';
	$messageType = 'error';
}

$employeeStmt = $conn->prepare("SELECT id, name, age, gender, role, birthday, address, email FROM employees WHERE id = ? LIMIT 1");
$employeeStmt->bind_param("i", $employeeId);
$employeeStmt->execute();
$employeeResult = $employeeStmt->get_result();

if (!$employeeResult || $employeeResult->num_rows === 0) {
	$_SESSION = [];
	session_destroy();
	header("Location: employee_login.html");
	exit();
}

$employee = $employeeResult->fetch_assoc();

$weeklyLabels = [];
$weeklyHours = [];
$weeklyMap = [];
for ($i = 6; $i >= 0; $i--) {
	$dateKey = date('Y-m-d', strtotime("-$i day"));
	$weeklyLabels[] = date('D', strtotime($dateKey));
	$weeklyMap[$dateKey] = 0;
}

$weeklyStmt = $conn->prepare("SELECT DATE(login_time) AS work_date, ROUND(SUM(TIMESTAMPDIFF(MINUTE, login_time, COALESCE(logout_time, NOW()))) / 60, 2) AS hours FROM employee_login_logs WHERE employee_id = ? AND login_time >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(login_time)");
$weeklyStmt->bind_param("i", $employeeId);
$weeklyStmt->execute();
$weeklyResult = $weeklyStmt->get_result();

if ($weeklyResult) {
	while ($row = $weeklyResult->fetch_assoc()) {
		if (isset($weeklyMap[$row['work_date']])) {
			$weeklyMap[$row['work_date']] = (float) $row['hours'];
		}
	}
}

foreach ($weeklyMap as $hours) {
	$weeklyHours[] = $hours;
}

$monthlyLabels = [];
$monthlyHours = [];
$monthlyMap = [];
for ($i = 5; $i >= 0; $i--) {
	$monthKey = date('Y-m', strtotime(date('Y-m-01') . " -$i month"));
	$monthlyLabels[] = date('M Y', strtotime($monthKey . '-01'));
	$monthlyMap[$monthKey] = 0;
}

$monthlyStmt = $conn->prepare("SELECT DATE_FORMAT(login_time, '%Y-%m') AS month_key, ROUND(SUM(TIMESTAMPDIFF(MINUTE, login_time, COALESCE(logout_time, NOW()))) / 60, 2) AS hours FROM employee_login_logs WHERE employee_id = ? AND login_time >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 5 MONTH) GROUP BY DATE_FORMAT(login_time, '%Y-%m')");
$monthlyStmt->bind_param("i", $employeeId);
$monthlyStmt->execute();
$monthlyResult = $monthlyStmt->get_result();

if ($monthlyResult) {
	while ($row = $monthlyResult->fetch_assoc()) {
		if (isset($monthlyMap[$row['month_key']])) {
			$monthlyMap[$row['month_key']] = (float) $row['hours'];
		}
	}
}

foreach ($monthlyMap as $hours) {
	$monthlyHours[] = $hours;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Employee Dashboard - Employee Management System</title>
	<link rel="stylesheet" href="css/style.css">
</head>
<body>
	<main class="dashboard-page">
		<section class="dashboard-panel">
			<header class="dashboard-header">
				<div>
					<p class="eyebrow">Employee Panel</p>
					<h1>Welcome, <?php echo htmlspecialchars($employee['name']); ?></h1>
					
				</div>
				<div style="display: flex; gap: 0.75rem;">
					<a class="button button-secondary" href="index.html">Home</a>
					<a class="button" href="employee_dashboard.php?logout=1">Logout</a>
				</div>
			</header>

			<section class="dashboard-grid">
				<article class="feature-card">
					<p class="eyebrow">Email</p>
					<h2><?php echo htmlspecialchars($employee['email']); ?></h2>
					<p>Email is read-only and cannot be changed here.</p>
				</article>
				<article class="feature-card">
					<p class="eyebrow">Role</p>
					<h2><?php echo htmlspecialchars($employee['role']); ?></h2>
					<p>Role is managed by admin and not editable.</p>
				</article>
				<article class="feature-card">
					<p class="eyebrow">Employee ID</p>
					<h2>#<?php echo (int) $employee['id']; ?></h2>
					<p>Your unique employee record identifier.</p>
				</article>
			</section>

			<section class="employee-list" style="margin-bottom: 1.5rem;">
				<div class="list-header">
					<div>
						<h2>Working Hours</h2>
						<p>Your weekly and monthly working hours based on login sessions.</p>
					</div>
				</div>

				<div class="dashboard-grid" style="margin-bottom: 0; grid-template-columns: repeat(2, minmax(0, 1fr));">
					<article class="feature-card">
						<h3 style="margin-bottom: 1rem;">Last 7 Days</h3>
						<canvas id="weeklyHoursChart" height="240"></canvas>
					</article>
					<article class="feature-card">
						<h3 style="margin-bottom: 1rem;">Last 6 Months</h3>
						<canvas id="monthlyHoursChart" height="240"></canvas>
					</article>
				</div>
			</section>

			<section class="employee-list">
				<div class="list-header">
					<div>
						<h2>Edit Profile</h2>
						<p>Update allowed fields below.</p>
					</div>
				</div>

				<?php if ($message !== ''): ?>
					<p style="margin-bottom: 1rem; color: <?php echo $messageType === 'success' ? '#86efac' : '#fca5a5'; ?>; font-weight: 600;">
						<?php echo htmlspecialchars($message); ?>
					</p>
				<?php endif; ?>

				<form class="login-form" action="employee_dashboard.php" method="post">
					<input type="hidden" name="action" value="update_profile">

					<div class="form-group">
						<label for="name">Name</label>
						<input id="name" name="name" type="text" required value="<?php echo htmlspecialchars($employee['name']); ?>">
					</div>

					<div class="form-group">
						<label for="age">Age</label>
						<input id="age" name="age" type="number" min="1" required value="<?php echo (int) $employee['age']; ?>">
					</div>

					<div class="form-group">
						<label for="birthday">Birthday</label>
						<input id="birthday" name="birthday" type="date" required value="<?php echo htmlspecialchars($employee['birthday']); ?>">
					</div>

					<div class="form-group">
						<label for="address">Address</label>
						<input id="address" name="address" type="text" required value="<?php echo htmlspecialchars($employee['address']); ?>">
					</div>

					<div class="form-group">
						<label for="gender">Gender</label>
						<select id="gender" name="gender" required>
							<option value="Male" <?php echo $employee['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
							<option value="Female" <?php echo $employee['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
							<option value="Other" <?php echo $employee['gender'] === 'Other' ? 'selected' : ''; ?>>Other</option>
						</select>
					</div>

					<button type="submit">Save Profile</button>
				</form>
			</section>
		</section>
	</main>
	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
	<script>
		const weeklyLabels = <?php echo json_encode($weeklyLabels); ?>;
		const weeklyHours = <?php echo json_encode($weeklyHours); ?>;
		const monthlyLabels = <?php echo json_encode($monthlyLabels); ?>;
		const monthlyHours = <?php echo json_encode($monthlyHours); ?>;

		new Chart(document.getElementById('weeklyHoursChart'), {
			type: 'bar',
			data: {
				labels: weeklyLabels,
				datasets: [{
					label: 'Hours',
					data: weeklyHours,
					backgroundColor: 'rgba(96, 165, 250, 0.75)',
					borderColor: '#60a5fa',
					borderWidth: 1,
					borderRadius: 8
				}]
			},
			options: {
				plugins: {
					legend: { labels: { color: '#e2e8f0' } }
				},
				scales: {
					x: { ticks: { color: '#cbd5e1' }, grid: { color: 'rgba(148, 163, 184, 0.12)' } },
					y: { beginAtZero: true, ticks: { color: '#cbd5e1' }, grid: { color: 'rgba(148, 163, 184, 0.12)' } }
				}
			}
		});

		new Chart(document.getElementById('monthlyHoursChart'), {
			type: 'bar',
			data: {
				labels: monthlyLabels,
				datasets: [{
					label: 'Hours',
					data: monthlyHours,
					backgroundColor: 'rgba(52, 211, 153, 0.75)',
					borderColor: '#34d399',
					borderWidth: 1,
					borderRadius: 8
				}]
			},
			options: {
				plugins: {
					legend: { labels: { color: '#e2e8f0' } }
				},
				scales: {
					x: { ticks: { color: '#cbd5e1' }, grid: { color: 'rgba(148, 163, 184, 0.12)' } },
					y: { beginAtZero: true, ticks: { color: '#cbd5e1' }, grid: { color: 'rgba(148, 163, 184, 0.12)' } }
				}
			}
		});
	</script>
</body>
</html>
