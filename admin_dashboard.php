<?php
include 'db.php';

$conn->query("CREATE TABLE IF NOT EXISTS employee_login_logs (id INT AUTO_INCREMENT PRIMARY KEY, employee_id INT NOT NULL, login_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, logout_time DATETIME NULL, INDEX idx_employee_id (employee_id), CONSTRAINT fk_employee_login_logs_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE)");
$conn->query("ALTER TABLE employees ADD COLUMN approval_status ENUM('pending', 'approved') NOT NULL DEFAULT 'pending'");

$message = '';
$messageType = 'info';
$editEmployee = null;

function buildDashboardUrl($status, $searchTerm = '') {
	$params = ['status' => $status];

	if ($searchTerm !== '') {
		$params['search'] = $searchTerm;
	}

	return 'admin_dashboard.php?' . http_build_query($params) . '#employee-directory';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = $_POST['action'] ?? '';
	$returnSearch = trim($_POST['return_search'] ?? '');

	if ($action === 'delete') {
		$employeeId = (int) ($_POST['employee_id'] ?? 0);

		if ($employeeId > 0) {
			$deleteStmt = $conn->prepare("DELETE FROM employees WHERE id = ?");
			$deleteStmt->bind_param("i", $employeeId);
			$deleteStmt->execute();

			if ($deleteStmt->affected_rows > 0) {
				header("Location: " . buildDashboardUrl('deleted', $returnSearch));
				exit();
			}

			header("Location: " . buildDashboardUrl('delete_failed', $returnSearch));
			exit();
		}

		header("Location: " . buildDashboardUrl('invalid', $returnSearch));
		exit();
	}

	if ($action === 'update') {
		$employeeId = (int) ($_POST['employee_id'] ?? 0);
		$name = trim($_POST['name'] ?? '');
		$age = (int) ($_POST['age'] ?? 0);
		$gender = trim($_POST['gender'] ?? '');
		$role = trim($_POST['role'] ?? '');
		$birthday = trim($_POST['birthday'] ?? '');
		$address = trim($_POST['address'] ?? '');
		$email = trim($_POST['email'] ?? '');

		$allowedGenders = ['Male', 'Female', 'Other'];

		if ($employeeId <= 0 || $name === '' || $age <= 0 || !in_array($gender, $allowedGenders, true) || $role === '' || $birthday === '' || $address === '' || $email === '') {
			header("Location: " . buildDashboardUrl('invalid', $returnSearch));
			exit();
		}

		$updateStmt = $conn->prepare("UPDATE employees SET name = ?, age = ?, gender = ?, role = ?, birthday = ?, address = ?, email = ? WHERE id = ?");
		$updateStmt->bind_param("sisssssi", $name, $age, $gender, $role, $birthday, $address, $email, $employeeId);
		$updated = $updateStmt->execute();

		if ($updated) {
			header("Location: " . buildDashboardUrl('updated', $returnSearch));
			exit();
		}

		header("Location: " . buildDashboardUrl('update_failed', $returnSearch));
		exit();
	}

	if ($action === 'approve') {
		$employeeId = (int) ($_POST['employee_id'] ?? 0);

		if ($employeeId > 0) {
			$approveStmt = $conn->prepare("UPDATE employees SET approval_status = 'approved' WHERE id = ?");
			$approveStmt->bind_param("i", $employeeId);
			$approved = $approveStmt->execute();

			if ($approved) {
				header("Location: " . buildDashboardUrl('approved', $returnSearch));
				exit();
			}

			header("Location: " . buildDashboardUrl('approve_failed', $returnSearch));
			exit();
		}

		header("Location: " . buildDashboardUrl('invalid', $returnSearch));
		exit();
	}
}

$status = $_GET['status'] ?? '';
if ($status === 'updated') {
	$message = 'Employee details updated successfully.';
	$messageType = 'success';
} elseif ($status === 'deleted') {
	$message = 'Employee removed successfully.';
	$messageType = 'success';
} elseif ($status === 'update_failed') {
	$message = 'Unable to update employee details.';
	$messageType = 'error';
} elseif ($status === 'delete_failed') {
	$message = 'Unable to remove this employee.';
	$messageType = 'error';
} elseif ($status === 'invalid') {
	$message = 'Please provide valid employee information.';
	$messageType = 'error';
	} elseif ($status === 'approved') {
	$message = 'Employee approved successfully.';
	$messageType = 'success';
	} elseif ($status === 'approve_failed') {
	$message = 'Unable to approve employee right now.';
	$messageType = 'error';
}

$editId = (int) ($_GET['edit'] ?? 0);
if ($editId > 0) {
	$editStmt = $conn->prepare("SELECT id, name, age, gender, role, birthday, address, email, approval_status FROM employees WHERE id = ? LIMIT 1");
	$editStmt->bind_param("i", $editId);
	$editStmt->execute();
	$editResult = $editStmt->get_result();

	if ($editResult && $editResult->num_rows > 0) {
		$editEmployee = $editResult->fetch_assoc();
	} else {
		$message = 'Employee record not found.';
		$messageType = 'error';
	}
}

$stats = [
	'totalEmployees' => 0,
	'totalAdmins' => 0,
	'newThisMonth' => 0
];

$searchTerm = trim($_GET['search'] ?? '');
$attendanceFilterDate = trim($_GET['attendance_date'] ?? date('Y-m-d'));
$attendanceFilterGender = trim($_GET['attendance_gender'] ?? 'All');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $attendanceFilterDate)) {
	$attendanceFilterDate = date('Y-m-d');
}

$allowedAttendanceGenders = ['All', 'Male', 'Female', 'Other'];
if (!in_array($attendanceFilterGender, $allowedAttendanceGenders, true)) {
	$attendanceFilterGender = 'All';
}

$employees = [];
$roleSummary = [];
$genderSummary = [];

$employeesCountResult = $conn->query("SELECT COUNT(*) AS total FROM employees");
if ($employeesCountResult && $employeesCountResult->num_rows > 0) {
	$stats['totalEmployees'] = (int) $employeesCountResult->fetch_assoc()['total'];
}

$adminsCountResult = $conn->query("SELECT COUNT(*) AS total FROM admins");
if ($adminsCountResult && $adminsCountResult->num_rows > 0) {
	$stats['totalAdmins'] = (int) $adminsCountResult->fetch_assoc()['total'];
}

$newEmployeesResult = $conn->query("SELECT COUNT(*) AS total FROM employees WHERE MONTH(birthday) = MONTH(CURDATE())");
if ($newEmployeesResult && $newEmployeesResult->num_rows > 0) {
	$stats['newThisMonth'] = (int) $newEmployeesResult->fetch_assoc()['total'];
}

$employeesResult = null;
if ($searchTerm !== '') {
	$searchLike = '%' . $searchTerm . '%';
	$employeesStmt = $conn->prepare("SELECT id, name, age, gender, role, birthday, address, email, approval_status FROM employees WHERE name LIKE ? OR email LIKE ? OR role LIKE ? OR gender LIKE ? OR address LIKE ? OR approval_status LIKE ? ORDER BY id DESC");
	$employeesStmt->bind_param("ssssss", $searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike);
	$employeesStmt->execute();
	$employeesResult = $employeesStmt->get_result();
} else {
	$employeesResult = $conn->query("SELECT id, name, age, gender, role, birthday, address, email, approval_status FROM employees ORDER BY id DESC");
}

if ($employeesResult) {
	while ($row = $employeesResult->fetch_assoc()) {
		$employees[] = $row;
		$role = $row['role'];
		$gender = $row['gender'];

		if (!isset($roleSummary[$role])) {
			$roleSummary[$role] = 0;
		}
		$roleSummary[$role]++;

		if (!isset($genderSummary[$gender])) {
			$genderSummary[$gender] = 0;
		}
		$genderSummary[$gender]++;
	}
}

$topRole = 'N/A';
if (!empty($roleSummary)) {
	arsort($roleSummary);
	$topRole = array_key_first($roleSummary);
}

$topGender = 'N/A';
if (!empty($genderSummary)) {
	arsort($genderSummary);
	$topGender = array_key_first($genderSummary);
}

$roleLabels = json_encode(array_keys($roleSummary));
$roleValues = json_encode(array_values($roleSummary));
$genderLabels = json_encode(array_keys($genderSummary));
$genderValues = json_encode(array_values($genderSummary));

$attendanceDailyLabels = [];
$attendanceDailyValues = [];
$dailyMap = [];
for ($i = 6; $i >= 0; $i--) {
	$dateKey = date('Y-m-d', strtotime("-$i day"));
	$attendanceDailyLabels[] = date('d M', strtotime($dateKey));
	$dailyMap[$dateKey] = 0;
}

$attendanceDailyQuery = "SELECT DATE(login_time) AS work_date, ROUND(SUM(TIMESTAMPDIFF(MINUTE, login_time, COALESCE(logout_time, NOW()))) / 60, 2) AS hours FROM employee_login_logs WHERE login_time >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(login_time)";
$attendanceDailyResult = $conn->query($attendanceDailyQuery);
if ($attendanceDailyResult) {
	while ($row = $attendanceDailyResult->fetch_assoc()) {
		if (isset($dailyMap[$row['work_date']])) {
			$dailyMap[$row['work_date']] = (float) $row['hours'];
		}
	}
}
foreach ($dailyMap as $hours) {
	$attendanceDailyValues[] = $hours;
}

$attendanceMonthlyLabels = [];
$attendanceMonthlyValues = [];
$monthlyMap = [];
for ($i = 5; $i >= 0; $i--) {
	$monthKey = date('Y-m', strtotime(date('Y-m-01') . " -$i month"));
	$attendanceMonthlyLabels[] = date('M Y', strtotime($monthKey . '-01'));
	$monthlyMap[$monthKey] = 0;
}

$attendanceMonthlyQuery = "SELECT DATE_FORMAT(login_time, '%Y-%m') AS month_key, ROUND(SUM(TIMESTAMPDIFF(MINUTE, login_time, COALESCE(logout_time, NOW()))) / 60, 2) AS hours FROM employee_login_logs WHERE login_time >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 5 MONTH) GROUP BY DATE_FORMAT(login_time, '%Y-%m')";
$attendanceMonthlyResult = $conn->query($attendanceMonthlyQuery);
if ($attendanceMonthlyResult) {
	while ($row = $attendanceMonthlyResult->fetch_assoc()) {
		if (isset($monthlyMap[$row['month_key']])) {
			$monthlyMap[$row['month_key']] = (float) $row['hours'];
		}
	}
}
foreach ($monthlyMap as $hours) {
	$attendanceMonthlyValues[] = $hours;
}

$attendanceGenderLabels = ['Male', 'Female', 'Other'];
$genderHoursMap = [
	'Male' => 0,
	'Female' => 0,
	'Other' => 0
];
$attendanceGenderQuery = "SELECT e.gender, ROUND(SUM(TIMESTAMPDIFF(MINUTE, l.login_time, COALESCE(l.logout_time, NOW()))) / 60, 2) AS hours FROM employee_login_logs l INNER JOIN employees e ON e.id = l.employee_id GROUP BY e.gender";
$attendanceGenderResult = $conn->query($attendanceGenderQuery);
if ($attendanceGenderResult) {
	while ($row = $attendanceGenderResult->fetch_assoc()) {
		$genderName = $row['gender'];
		if (isset($genderHoursMap[$genderName])) {
			$genderHoursMap[$genderName] = (float) $row['hours'];
		}
	}
}
$attendanceGenderValues = [
	$genderHoursMap['Male'],
	$genderHoursMap['Female'],
	$genderHoursMap['Other']
];

$attendanceFilteredLabels = [];
$attendanceFilteredValues = [];

$attendanceFilteredStmt = $conn->prepare("SELECT e.name, COALESCE(ROUND(SUM(TIMESTAMPDIFF(MINUTE, l.login_time, COALESCE(l.logout_time, NOW()))) / 60, 2), 0) AS hours FROM employees e LEFT JOIN employee_login_logs l ON l.employee_id = e.id AND DATE(l.login_time) = ? WHERE (? = 'All' OR e.gender = ?) GROUP BY e.id, e.name ORDER BY e.name");
$attendanceFilteredStmt->bind_param("sss", $attendanceFilterDate, $attendanceFilterGender, $attendanceFilterGender);
$attendanceFilteredStmt->execute();
$attendanceFilteredResult = $attendanceFilteredStmt->get_result();

if ($attendanceFilteredResult) {
	while ($row = $attendanceFilteredResult->fetch_assoc()) {
		$attendanceFilteredLabels[] = $row['name'];
		$attendanceFilteredValues[] = (float) $row['hours'];
	}
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Admin Dashboard - Employee Management System</title>
	<link rel="stylesheet" href="css/style.css">
	<style>
		.control-input {
			padding: 0.7rem 0.8rem;
			border-radius: 10px;
			border: 1px solid rgba(148, 163, 184, 0.28);
			background: rgba(80, 67, 67, 0.08);
			color: #25436a;
			width: 100%;
		}

		.toolbar-form {
			display: flex;
			gap: 0.5rem;
			align-items: center;
			width: min(100%, 460px);
		}

		.attendance-controls {
			display: grid;
			gap: 0.6rem;
			width: min(100%, 420px);
            color: #678fc8;
		}

		.attendance-filter-form {
			display: grid;
			grid-template-columns: 1fr 1fr auto;
			gap: 0.4rem;
			align-items: center;
		}

		.actions-cell {
			white-space: nowrap;
		}

		@media (max-width: 860px) {
			.toolbar-form {
				width: 100%;
			}

			.attendance-filter-form {
				grid-template-columns: 1fr;
			}
		}
	</style>
</head>
<body>
	<main class="dashboard-page">
		<section class="dashboard-panel">
			<header class="dashboard-header">
				<div>
					<p class="eyebrow">Administrator Panel</p>
					<h1>Admin Dashboard</h1>
					<p>Manage your workforce from one place with a live view of team information.</p>
				</div>
				<a class="button button-secondary" href="index.html">Back to Home</a>
			</header>

			<section class="dashboard-grid">
				<article class="feature-card">
					<p class="eyebrow">Employees</p>
					<h2><?php echo $stats['totalEmployees']; ?></h2>
					<p>Total registered employees in the system.</p>
				</article>
				<article class="feature-card">
					<p class="eyebrow">Quick Action</p>
					<h2>Add Employee</h2>
					<p>Create a new employee account from the admin panel.</p>
					<a class="button" href="employee_signup.html">Add Employee</a>
				</article>
				<article class="feature-card">
					<p class="eyebrow">This Month</p>
					<h2><?php echo $stats['newThisMonth']; ?></h2>
					<p>Employees with birthdays in the current month.</p>
				</article>
				<article class="feature-card">
					<p class="eyebrow">Top Role</p>
					<h2><?php echo htmlspecialchars($topRole); ?></h2>
					<p>Most common role among registered employees.</p>
				</article>
				<article class="feature-card">
					<p class="eyebrow">Top Gender</p>
					<h2><?php echo htmlspecialchars($topGender); ?></h2>
					<p>Most represented gender in the current records.</p>
				</article>
				<article class="feature-card">
					<p class="eyebrow">Dataset</p>
					<h2><?php echo count($employees); ?></h2>
					<p>Employee entries currently loaded on this page.</p>
				</article>
			</section>

			<section class="employee-list" style="margin-bottom: 1.5rem;">
				<div class="list-header">
					<div>
						<h2>Employee Insights</h2>
						<p>Role and gender distribution across all employees.</p>
					</div>
				</div>
				<div class="dashboard-grid" style="margin-bottom: 0;">
					<article class="feature-card">
						<h3 style="margin-bottom: 1rem;">Role Distribution</h3>
						<canvas id="rolePieChart" height="220"></canvas>
					</article>
					<article class="feature-card">
						<h3 style="margin-bottom: 1rem;">Gender Distribution</h3>
						<canvas id="genderPieChart" height="220"></canvas>
					</article>
				</div>
			</section>

			<section class="employee-list" style="margin-bottom: 1.5rem;">
				<div class="list-header">
					<div>
						<h2>Attendance Analytics</h2>
						<p>All employee attendance hours by daily, monthly, or gender-wise view.</p>
					</div>
					<div class="attendance-controls">
						<select id="attendanceView" class="control-input">
							<option value="daily">Daily (Last 7 Days)</option>
							<option value="monthly">Monthly (Last 6 Months)</option>
							<option value="gender">Gender Wise</option>
						</select>
						<form action="admin_dashboard.php" method="get" class="attendance-filter-form">
							<input type="date" name="attendance_date" value="<?php echo htmlspecialchars($attendanceFilterDate); ?>" class="control-input">
							<select name="attendance_gender" class="control-input">
								<option value="All" <?php echo $attendanceFilterGender === 'All' ? 'selected' : ''; ?>>All</option>
								<option value="Male" <?php echo $attendanceFilterGender === 'Male' ? 'selected' : ''; ?>>Male</option>
								<option value="Female" <?php echo $attendanceFilterGender === 'Female' ? 'selected' : ''; ?>>Female</option>
								<option value="Other" <?php echo $attendanceFilterGender === 'Other' ? 'selected' : ''; ?>>Other</option>
							</select>
							<?php if ($searchTerm !== ''): ?>
								<input type="hidden" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>">
							<?php endif; ?>
							<button type="submit" class="small-button" style="border: none; cursor: pointer;">Filter</button>
						</form>
					</div>
				</div>
				<div class="dashboard-grid" style="margin-bottom: 0; grid-template-columns: repeat(2, minmax(0, 1fr));">
					<article class="feature-card">
						<canvas id="attendanceBarChart" height="110"></canvas>
					</article>
					<article class="feature-card">
						<h3 style="margin-bottom: 0.5rem;">Selected Date by Gender</h3>
						<p style="margin-bottom: 1rem; color: #24456d;">Date: <?php echo htmlspecialchars($attendanceFilterDate); ?> | Gender: <?php echo htmlspecialchars($attendanceFilterGender); ?></p>
						<canvas id="attendanceFilteredChart" height="110"></canvas>
					</article>
				</div>
			</section>

			<section id="employee-directory" class="employee-list">
				<div class="list-header">
					<div>
						<h2>Employee Directory</h2>
						<p>Detailed records of all employees.</p>
					</div>
					<form action="admin_dashboard.php" method="get" class="toolbar-form">
						<input
							type="text"
							name="search"
							placeholder="Search name, email, role, gender, address"
							value="<?php echo htmlspecialchars($searchTerm); ?>"
							class="control-input"
						>
						<button type="submit" class="small-button" style="border: none; cursor: pointer;">Search</button>
						<?php if ($searchTerm !== ''): ?>
							<a class="small-button small-button--danger" href="admin_dashboard.php">Clear</a>
						<?php endif; ?>
					</form>
				</div>

				<?php if ($message !== ''): ?>
					<p style="margin-bottom: 1rem; color: <?php echo $messageType === 'success' ? '#86efac' : '#fca5a5'; ?>; font-weight: 600;">
						<?php echo htmlspecialchars($message); ?>
					</p>
				<?php endif; ?>

				<?php if ($editEmployee !== null): ?>
					<section id="edit-form" style="margin-bottom: 1.5rem; padding: 1rem; border: 1px solid rgba(148, 163, 184, 0.16); border-radius: 14px;">
						<h3 style="margin-bottom: 1rem;">Update Employee #<?php echo (int) $editEmployee['id']; ?></h3>
						<form class="login-form" action="admin_dashboard.php" method="post">
							<input type="hidden" name="action" value="update">
							<input type="hidden" name="employee_id" value="<?php echo (int) $editEmployee['id']; ?>">
							<input type="hidden" name="return_search" value="<?php echo htmlspecialchars($searchTerm); ?>">

							<div class="form-group">
								<label for="name">Name</label>
								<input id="name" name="name" type="text" required value="<?php echo htmlspecialchars($editEmployee['name']); ?>">
							</div>

							<div class="form-group">
								<label for="email">Email</label>
								<input id="email" name="email" type="email" required value="<?php echo htmlspecialchars($editEmployee['email']); ?>">
							</div>

							<div class="form-group">
								<label for="age">Age</label>
								<input id="age" name="age" type="number" min="1" required value="<?php echo (int) $editEmployee['age']; ?>">
							</div>

							<div class="form-group">
								<label for="gender">Gender</label>
								<select id="gender" name="gender" required>
									<option value="Male" <?php echo $editEmployee['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
									<option value="Female" <?php echo $editEmployee['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
									<option value="Other" <?php echo $editEmployee['gender'] === 'Other' ? 'selected' : ''; ?>>Other</option>
								</select>
							</div>

							<div class="form-group">
								<label for="role">Role</label>
								<input id="role" name="role" type="text" required value="<?php echo htmlspecialchars($editEmployee['role']); ?>">
							</div>

							<div class="form-group">
								<label for="birthday">Birthday</label>
								<input id="birthday" name="birthday" type="date" required value="<?php echo htmlspecialchars($editEmployee['birthday']); ?>">
							</div>

							<div class="form-group">
								<label for="address">Address</label>
								<input id="address" name="address" type="text" required value="<?php echo htmlspecialchars($editEmployee['address']); ?>">
							</div>

							<button type="submit">Save Changes</button>
							<a class="button button-secondary" href="admin_dashboard.php<?php echo $searchTerm !== '' ? '?search=' . urlencode($searchTerm) : ''; ?>#employee-directory" style="margin-top: 0.75rem;">Cancel</a>
						</form>
					</section>
				<?php endif; ?>

				<div class="table-wrap">
					<table>
						<thead>
							<tr>
								<th>ID</th>
								<th>Name</th>
								<th>Email</th>
								<th>Status</th>
								<th>Role</th>
								<th>Gender</th>
								<th>Age</th>
								<th>Birthday</th>
								<th>Address</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php if (count($employees) === 0): ?>
								<tr>
									<td colspan="10">
										<?php if ($searchTerm !== ''): ?>
											No employees found for "<?php echo htmlspecialchars($searchTerm); ?>".
										<?php else: ?>
											No employees found. Add records from the signup form.
										<?php endif; ?>
									</td>
								</tr>
							<?php else: ?>
								<?php foreach ($employees as $employee): ?>
									<tr>
										<td><?php echo (int) $employee['id']; ?></td>
										<td><?php echo htmlspecialchars($employee['name']); ?></td>
										<td><?php echo htmlspecialchars($employee['email']); ?></td>
										<td>
											<span style="padding: 0.25rem 0.55rem; border-radius: 999px; font-size: 0.8rem; font-weight: 600; background: <?php echo $employee['approval_status'] === 'approved' ? 'rgba(34, 197, 94, 0.2)' : 'rgba(245, 158, 11, 0.2)'; ?>; color: <?php echo $employee['approval_status'] === 'approved' ? '#86efac' : '#fcd34d'; ?>;">
												<?php echo htmlspecialchars(ucfirst($employee['approval_status'])); ?>
											</span>
										</td>
										<td><?php echo htmlspecialchars($employee['role']); ?></td>
										<td><?php echo htmlspecialchars($employee['gender']); ?></td>
										<td><?php echo (int) $employee['age']; ?></td>
										<td><?php echo htmlspecialchars($employee['birthday']); ?></td>
										<td><?php echo htmlspecialchars($employee['address']); ?></td>
										<td class="actions-cell">
											<?php if ($employee['approval_status'] !== 'approved'): ?>
												<form method="post" action="admin_dashboard.php" style="display: inline;">
													<input type="hidden" name="action" value="approve">
													<input type="hidden" name="employee_id" value="<?php echo (int) $employee['id']; ?>">
													<input type="hidden" name="return_search" value="<?php echo htmlspecialchars($searchTerm); ?>">
													<button type="submit" class="small-button" style="border: none; cursor: pointer;">Approve</button>
												</form>
											<?php endif; ?>
											<a class="small-button" href="admin_dashboard.php?edit=<?php echo (int) $employee['id']; ?><?php echo $searchTerm !== '' ? '&search=' . urlencode($searchTerm) : ''; ?>#edit-form">Update</a>
											<form method="post" action="admin_dashboard.php" style="display: inline;">
												<input type="hidden" name="action" value="delete">
												<input type="hidden" name="employee_id" value="<?php echo (int) $employee['id']; ?>">
												<input type="hidden" name="return_search" value="<?php echo htmlspecialchars($searchTerm); ?>">
												<button
													type="submit"
													class="small-button small-button--danger"
													style="border: none; cursor: pointer;"
													onclick="return confirm('Are you sure you want to remove this employee?');"
												>
													Remove
												</button>
											</form>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</section>
		</section>
	</main>
	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
	<script>
		const roleLabels = <?php echo $roleLabels ?: '[]'; ?>;
		const roleValues = <?php echo $roleValues ?: '[]'; ?>;
		const genderLabels = <?php echo $genderLabels ?: '[]'; ?>;
		const genderValues = <?php echo $genderValues ?: '[]'; ?>;
		const attendanceDailyLabels = <?php echo json_encode($attendanceDailyLabels); ?>;
		const attendanceDailyValues = <?php echo json_encode($attendanceDailyValues); ?>;
		const attendanceMonthlyLabels = <?php echo json_encode($attendanceMonthlyLabels); ?>;
		const attendanceMonthlyValues = <?php echo json_encode($attendanceMonthlyValues); ?>;
		const attendanceGenderLabels = <?php echo json_encode($attendanceGenderLabels); ?>;
		const attendanceGenderValues = <?php echo json_encode($attendanceGenderValues); ?>;
		const attendanceFilteredLabels = <?php echo json_encode($attendanceFilteredLabels); ?>;
		const attendanceFilteredValues = <?php echo json_encode($attendanceFilteredValues); ?>;

		const palette = ['#60a5fa', '#34d399', '#f59e0b', '#f87171', '#a78bfa', '#22d3ee', '#f472b6'];

		if (roleLabels.length > 0) {
			new Chart(document.getElementById('rolePieChart'), {
				type: 'pie',
				data: {
					labels: roleLabels,
					datasets: [{
						data: roleValues,
						backgroundColor: palette,
						borderColor: '#0f172a',
						borderWidth: 2
					}]
				},
				options: {
					plugins: {
						legend: {
							labels: {
								color: '#e2e8f0'
							}
						}
					}
				}
			});
		}

		if (genderLabels.length > 0) {
			new Chart(document.getElementById('genderPieChart'), {
				type: 'pie',
				data: {
					labels: genderLabels,
					datasets: [{
						data: genderValues,
						backgroundColor: palette,
						borderColor: '#0f172a',
						borderWidth: 2
					}]
				},
				options: {
					plugins: {
						legend: {
							labels: {
								color: '#e2e8f0'
							}
						}
					}
				}
			});
		}

		const attendanceSelect = document.getElementById('attendanceView');
		const attendanceChartContext = document.getElementById('attendanceBarChart');
		const axisTickColor = '#cbd5e1';
		const gridColor = 'rgba(148, 163, 184, 0.12)';

		const commonChartOptions = {
			maintainAspectRatio: false,
			plugins: {
				legend: {
					labels: {
						color: '#e2e8f0'
					}
				},
				tooltip: {
					callbacks: {
						label: function(context) {
							return context.dataset.label + ': ' + context.parsed.y + ' hrs';
						}
					}
				}
			},
			scales: {
				x: {
					ticks: { color: axisTickColor },
					grid: { color: gridColor }
				},
				y: {
					beginAtZero: true,
					title: {
						display: true,
						text: 'Hours',
						color: axisTickColor
					},
					ticks: { color: axisTickColor },
					grid: { color: gridColor }
				}
			}
		};

		const attendanceDatasetConfig = {
			daily: {
				type: 'line',
				labels: attendanceDailyLabels,
				values: attendanceDailyValues,
				color: 'rgba(96, 165, 250, 0.78)',
				border: '#60a5fa',
				title: 'Daily Hours'
			},
			monthly: {
				type: 'bar',
				labels: attendanceMonthlyLabels,
				values: attendanceMonthlyValues,
				color: 'rgba(52, 211, 153, 0.78)',
				border: '#34d399',
				title: 'Monthly Hours'
			},
			gender: {
				type: 'bar',
				labels: attendanceGenderLabels,
				values: attendanceGenderValues,
				color: 'rgba(245, 158, 11, 0.78)',
				border: '#f59e0b',
				title: 'Gender-wise Hours'
			}
		};

		const attendanceChart = new Chart(attendanceChartContext, {
			type: attendanceDatasetConfig.daily.type,
			data: {
				labels: attendanceDatasetConfig.daily.labels,
				datasets: [{
					label: attendanceDatasetConfig.daily.title,
					data: attendanceDatasetConfig.daily.values,
					backgroundColor: attendanceDatasetConfig.daily.color,
					borderColor: attendanceDatasetConfig.daily.border,
					borderWidth: 1,
					borderRadius: 8,
					pointBackgroundColor: attendanceDatasetConfig.daily.border,
					pointRadius: 4,
					tension: 0.3,
					fill: true
				}]
			},
			options: commonChartOptions
		});

		attendanceSelect.addEventListener('change', function () {
			const selected = attendanceDatasetConfig[this.value];
			attendanceChart.config.type = selected.type;
			attendanceChart.data.labels = selected.labels;
			attendanceChart.data.datasets[0].label = selected.title;
			attendanceChart.data.datasets[0].data = selected.values;
			attendanceChart.data.datasets[0].backgroundColor = selected.color;
			attendanceChart.data.datasets[0].borderColor = selected.border;
			attendanceChart.data.datasets[0].fill = selected.type === 'line';
			attendanceChart.data.datasets[0].tension = selected.type === 'line' ? 0.3 : 0;
			attendanceChart.data.datasets[0].pointRadius = selected.type === 'line' ? 4 : 0;
			attendanceChart.data.datasets[0].pointBackgroundColor = selected.border;
			attendanceChart.data.datasets[0].borderRadius = selected.type === 'bar' ? 8 : 0;
			attendanceChart.update();
		});

		const sortedFiltered = attendanceFilteredLabels.map(function(label, index) {
			return { label: label, value: attendanceFilteredValues[index] || 0 };
		}).sort(function(a, b) {
			return b.value - a.value;
		});

		const filteredLabelsSorted = sortedFiltered.map(function(item) { return item.label; });
		const filteredValuesSorted = sortedFiltered.map(function(item) { return item.value; });

		new Chart(document.getElementById('attendanceFilteredChart'), {
			type: 'bar',
			data: {
				labels: filteredLabelsSorted,
				datasets: [{
					label: 'Hours on selected date',
					data: filteredValuesSorted,
					backgroundColor: 'rgba(248, 113, 113, 0.78)',
					borderColor: '#f87171',
					borderWidth: 1,
					borderRadius: 8
				}]
			},
			options: {
				indexAxis: 'y',
				plugins: {
					legend: {
						labels: {
							color: '#e2e8f0'
						}
					},
					tooltip: {
						callbacks: {
							label: function(context) {
								return context.dataset.label + ': ' + context.parsed.x + ' hrs';
							}
						}
					}
				},
				scales: {
					x: {
						beginAtZero: true,
						title: { display: true, text: 'Hours', color: axisTickColor },
						ticks: { color: axisTickColor },
						grid: { color: gridColor }
					},
					y: {
						ticks: { color: axisTickColor },
						grid: { color: gridColor }
					}
				}
			}
		});
	</script>
</body>
</html>
