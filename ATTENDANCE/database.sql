CREATE DATABASE IF NOT EXISTS student_attendance_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE student_attendance_db;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(160) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin', 'student') NOT NULL DEFAULT 'student',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS students (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL UNIQUE,
  student_number VARCHAR(40) NOT NULL UNIQUE,
  course VARCHAR(120) NOT NULL,
  year_level VARCHAR(30) NOT NULL,
  section VARCHAR(60) NOT NULL,
  CONSTRAINT fk_students_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS attendance (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  attendance_date DATE NOT NULL,
  time_in TIME NULL,
  status ENUM('Present', 'Absent') NOT NULL DEFAULT 'Present',
  date_created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_student_attendance_date (student_id, attendance_date),
  CONSTRAINT fk_attendance_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Demo admin account: username admin, password password. Change it after first login.
INSERT INTO users (full_name, username, email, password, role)
VALUES ('System Administrator', 'admin', 'admin@example.com', '$2y$12$jS7ThvhnlOFlq/URsROQlemsUAoIG.VMxEaU8mg5.8sRvE87BZMzC', 'admin')
ON DUPLICATE KEY UPDATE username = VALUES(username);