# Attendly — Student School Attendance

A PHP/MySQL school attendance portal with role-based admin and student pages.

## Run with XAMPP

1. Copy the complete project folder into XAMPP's `htdocs` directory. For example, use `C:\xampp\htdocs\StudentAttendance` and rename the folder to `StudentAttendance` if desired.
2. Open the XAMPP Control Panel and start **Apache** and **MySQL**.
3. Open `http://localhost/phpmyadmin`, choose **Import**, select this project's `database.sql`, and click **Import**. The script creates `student_attendance_db` and its tables.
4. If your MySQL root account has a password, update `$dbPassword` in `config/database.php`.
5. Visit `http://localhost/StudentAttendance/` (use the actual folder name if different).

Demo administrator login:

- Username: `admin`
- Password: `password`

Create student accounts from **Students → Add a student** while signed in as the administrator. Public registration is intentionally disabled so students cannot grant themselves accounts or roles.

## Notes

- Login passwords are stored with PHP's `password_hash()` and checked with `password_verify()`.
- Mutating forms use session-bound CSRF tokens, database prepared statements, and role checks.
- The database has a unique key on `(student_id, attendance_date)` so repeated or concurrent check-ins cannot create duplicates.
- Students can only view and record their own attendance. Admins can update or delete records.
- Attendance dates and times use the PHP/MySQL server timezone. Set it in the XAMPP `php.ini` if needed.
- This demo is intended for local development. Change the demo administrator password before exposing the site beyond your machine; HTTPS and production-grade configuration are also required for deployment.