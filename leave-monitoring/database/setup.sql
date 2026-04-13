-- SDO Leave Monitoring Database Setup
-- Run this script in phpMyAdmin or MySQL CLI

CREATE DATABASE IF NOT EXISTS sdo_leave_monitoring_db;
USE sdo_leave_monitoring_db;

-- Employees table
CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    surname VARCHAR(100) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    middle_initial VARCHAR(5) DEFAULT NULL,
    date_of_birth DATE NOT NULL,
    place_of_birth VARCHAR(200) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Leaves table
CREATE TABLE IF NOT EXISTS leaves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    period_from DATE NOT NULL,
    period_to DATE NOT NULL,
    reason VARCHAR(255) NOT NULL,
    station VARCHAR(200) NOT NULL,
    pay_status ENUM('With Pay', 'Without Pay', 'N/A') NOT NULL DEFAULT 'N/A',
    total_days INT NOT NULL,
    remarks TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
