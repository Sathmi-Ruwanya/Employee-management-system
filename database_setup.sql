-- Create the database
CREATE DATABASE IF NOT EXISTS emp;

-- Use the database
USE emp;

-- Create admins table
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

-- Create employees table
CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    age INT NOT NULL,
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    role VARCHAR(255) NOT NULL,
    birthday DATE NOT NULL,
    address TEXT NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    approval_status ENUM('pending', 'approved') NOT NULL DEFAULT 'pending'
);

-- Create employee login logs table
CREATE TABLE IF NOT EXISTS employee_login_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    login_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    logout_time DATETIME NULL,
    INDEX idx_employee_id (employee_id),
    CONSTRAINT fk_employee_login_logs_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

-- Insert sample admin data (password: admin123)
INSERT INTO admins (email, password) VALUES
('admin@example.com', 'admin123');

-- Insert sample employee data (password: pass123)
INSERT INTO employees (name, age, gender, role, birthday, address, email, password, approval_status) VALUES
('John1 Doe', 30, 'Male', 'Worker', '1994-01-15', '123 Main St, City, Country', 'john1@example.com', 'pass123', 'approved'),
('Jane1 Smith', 28, 'Female', 'Supervisor', '1996-05-20', '456 Elm St, City, Country', 'jane1@example.com', 'pass123', 'approved'),
('Alex1 Johnson', 25, 'Other', 'Worker', '1999-09-10', '789 Oak St, City, Country', 'alex1@example.com', 'pass123', 'approved');

-- Insert dummy login history data (past sessions)
INSERT INTO employee_login_logs (employee_id, login_time, logout_time) VALUES
(1, '2026-03-25 08:55:00', '2026-03-25 17:10:00'),
(1, '2026-03-26 09:02:00', '2026-03-26 17:05:00'),
(1, '2026-03-27 08:48:00', '2026-03-27 16:56:00'),
(2, '2026-03-25 09:12:00', '2026-03-25 18:00:00'),
(2, '2026-03-26 09:00:00', '2026-03-26 17:42:00'),
(2, '2026-03-28 09:20:00', '2026-03-28 16:50:00'),
(3, '2026-03-24 10:01:00', '2026-03-24 18:11:00'),
(3, '2026-03-26 09:35:00', '2026-03-26 17:15:00'),
(3, '2026-03-29 09:10:00', NULL),
(1, '2026-02-03 09:00:00', '2026-02-03 17:20:00'),
(1, '2026-02-14 08:50:00', '2026-02-14 16:45:00'),
(2, '2026-02-05 09:10:00', '2026-02-05 18:00:00'),
(2, '2026-02-17 09:05:00', '2026-02-17 17:35:00'),
(3, '2026-02-08 10:00:00', '2026-02-08 18:10:00'),
(3, '2026-02-21 09:40:00', '2026-02-21 17:25:00'),
(1, '2026-01-06 08:58:00', '2026-01-06 17:05:00'),
(1, '2026-01-20 09:03:00', '2026-01-20 16:52:00'),
(2, '2026-01-09 09:15:00', '2026-01-09 17:55:00'),
(2, '2026-01-23 09:08:00', '2026-01-23 17:40:00'),
(3, '2026-01-11 09:45:00', '2026-01-11 18:05:00'),
(3, '2026-01-25 09:30:00', '2026-01-25 17:18:00'),
(1, '2025-12-04 09:01:00', '2025-12-04 17:12:00'),
(1, '2025-12-18 08:55:00', '2025-12-18 16:58:00'),
(2, '2025-12-06 09:12:00', '2025-12-06 18:02:00'),
(2, '2025-12-19 09:07:00', '2025-12-19 17:48:00'),
(3, '2025-12-10 09:50:00', '2025-12-10 18:08:00'),
(3, '2025-12-22 09:34:00', '2025-12-22 17:22:00'),
(1, '2025-11-05 08:57:00', '2025-11-05 17:00:00'),
(1, '2025-11-21 09:04:00', '2025-11-21 16:50:00'),
(2, '2025-11-07 09:11:00', '2025-11-07 17:58:00'),
(2, '2025-11-24 09:06:00', '2025-11-24 17:36:00'),
(3, '2025-11-12 09:42:00', '2025-11-12 18:01:00'),
(3, '2025-11-27 09:28:00', '2025-11-27 17:16:00');

-- Extra sample attendance for John (employee_id = 1)
INSERT INTO employee_login_logs (employee_id, login_time, logout_time) VALUES
(1, '2026-04-01 08:58:00', '2026-04-01 17:14:00'),
(1, '2026-04-02 09:05:00', '2026-04-02 17:20:00'),
(1, '2026-04-03 08:50:00', '2026-04-03 16:55:00'),
(1, '2026-04-04 09:12:00', '2026-04-04 17:36:00'),
(1, '2026-04-05 09:00:00', '2026-04-05 16:48:00'),
(1, '2026-04-06 09:08:00', '2026-04-06 17:30:00'),
(1, '2026-04-07 08:53:00', NULL);