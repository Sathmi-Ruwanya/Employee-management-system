# Employee Management System (PHP + MySQL)

video - https://youtu.be/jyqLicf3zK4

This project is a simple Employee Management System with:
- Admin and Employee login
- Employee signup with admin approval flow
- Admin dashboard with employee management and attendance analytics
- Employee dashboard with profile update and working-hours charts

## 1. Requirements

Install these on the new PC:
- XAMPP (Apache + MySQL)
- Git
- Web browser (Chrome/Edge/Firefox)

## 2. Clone Project

Open terminal and run:

```bash
git clone <YOUR_GITHUB_REPO_URL>
```

Copy/move the project folder into XAMPP htdocs so final path becomes:

```text
c:\xampp\htdocs\EMP
```

## 3. Start Services

Open XAMPP Control Panel and start:
- Apache
- MySQL

## 4. Import Database

### Option A: Fresh setup (recommended)

Run this in terminal:

```powershell
cmd /c "c:\xampp\mysql\bin\mysql.exe -u root -e \"DROP DATABASE IF EXISTS emp; CREATE DATABASE emp;\""
cmd /c "c:\xampp\mysql\bin\mysql.exe -u root < c:\xampp\htdocs\EMP\database_setup.sql"
```

### Option B: Existing DB without dropping

If you do not want to reset data:

```powershell
cmd /c "c:\xampp\mysql\bin\mysql.exe -u root emp"
```

Then run only update queries manually from:
- schema changes in `database_setup.sql`
- any needed INSERT/UPDATE statements

## 5. Open Project in Browser

Main page:

```text
http://localhost/EMP/index.html
```

Admin login page:

```text
http://localhost/EMP/admin_login.html
```

Employee login page:

```text
http://localhost/EMP/employee_login.html
```

## 6. Default Sample Accounts

Admin:
- Email: `admin@example.com`
- Password: `admin123`

Employees (approved in sample data):
- john1@example.com / pass123
- jane1@example.com / pass123
- alex1@example.com / pass123

## 7. Important Notes

- New employee signups are created as `pending` approval.
- Pending employees cannot login until admin approves them in admin dashboard.
- Login/logout attendance is stored in `employee_login_logs`.
