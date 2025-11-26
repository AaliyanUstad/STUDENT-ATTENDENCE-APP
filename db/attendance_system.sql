-- Student Attendance Management System Database
-- Complete Updated Version with Role-Based Access Control and Warning System

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS attendance_warnings;
DROP TABLE IF EXISTS student_teacher_relationships;
DROP TABLE IF EXISTS holidays;
DROP TABLE IF EXISTS class_schedule;
DROP TABLE IF EXISTS attendance_goals;
DROP TABLE IF EXISTS attendance_records;
DROP TABLE IF EXISTS subjects;
DROP TABLE IF EXISTS institutes;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. users table: Stores student and teacher info
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('student', 'teacher', 'admin') DEFAULT 'student',
    profile_picture VARCHAR(255) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 2. institutes table: Stores different institutes
CREATE TABLE institutes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL, -- Teacher who created the institute
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    address TEXT NULL,
    contact_email VARCHAR(150) NULL,
    contact_phone VARCHAR(20) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
);

-- 3. subjects table: Stores subjects with difficulty levels
CREATE TABLE subjects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    institute_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    difficulty ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
    color_code VARCHAR(7) DEFAULT '#3498db',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (institute_id) REFERENCES institutes(id) ON DELETE CASCADE,
    INDEX idx_institute_id (institute_id),
    INDEX idx_difficulty (difficulty)
);

-- 4. student_teacher_relationships table: Links students to teachers and institutes
CREATE TABLE student_teacher_relationships (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT NOT NULL,
    student_id INT NOT NULL,
    institute_id INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (institute_id) REFERENCES institutes(id) ON DELETE CASCADE,
    UNIQUE KEY unique_relationship (teacher_id, student_id, institute_id),
    INDEX idx_teacher_id (teacher_id),
    INDEX idx_student_id (student_id),
    INDEX idx_institute_id (institute_id)
);

-- 5. attendance_records table: Main attendance log
CREATE TABLE attendance_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL, -- Student ID
    institute_id INT NOT NULL,
    subject_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    selfie_image_path VARCHAR(255) NOT NULL,
    status ENUM('present', 'absent') DEFAULT 'present',
    notes TEXT NULL,
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (institute_id) REFERENCES institutes(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attendance (user_id, institute_id, subject_id, attendance_date),
    INDEX idx_user_date (user_id, attendance_date),
    INDEX idx_institute_subject (institute_id, subject_id)
);

-- 6. attendance_goals table: User-defined attendance targets
CREATE TABLE attendance_goals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    institute_id INT NOT NULL,
    target_percentage DECIMAL(5,2) DEFAULT 75.00,
    warning_threshold DECIMAL(5,2) DEFAULT 70.00,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (institute_id) REFERENCES institutes(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_institute (user_id, institute_id),
    INDEX idx_user_id (user_id),
    CONSTRAINT chk_target_percentage CHECK (target_percentage >= 0 AND target_percentage <= 100),
    CONSTRAINT chk_warning_threshold CHECK (warning_threshold >= 0 AND warning_threshold <= 100)
);

-- 7. attendance_warnings table: Stores automatic attendance warnings
CREATE TABLE attendance_warnings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    institute_id INT NOT NULL,
    subject_id INT NULL,
    warning_type ENUM('warning', 'critical') DEFAULT 'warning',
    message TEXT NOT NULL,
    attendance_percentage DECIMAL(5,2) NOT NULL,
    threshold_percentage DECIMAL(5,2) NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (institute_id) REFERENCES institutes(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    INDEX idx_user_unread (user_id, is_read),
    INDEX idx_created_at (created_at)
);

-- 8. class_schedule table: Optional but useful for class timings
CREATE TABLE class_schedule (
    id INT PRIMARY KEY AUTO_INCREMENT,
    institute_id INT NOT NULL,
    subject_id INT NOT NULL,
    day_of_week ENUM('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'),
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (institute_id) REFERENCES institutes(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    INDEX idx_institute_day (institute_id, day_of_week)
);

-- 9. holidays table: Optional for managing holidays
CREATE TABLE holidays (
    id INT PRIMARY KEY AUTO_INCREMENT,
    institute_id INT NOT NULL,
    holiday_date DATE NOT NULL,
    description VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (institute_id) REFERENCES institutes(id) ON DELETE CASCADE,
    UNIQUE KEY unique_institute_holiday (institute_id, holiday_date)
);

-- Insert Sample Data

-- Sample Users (passwords are hashed versions of 'password123')
INSERT INTO users (name, email, password_hash, role) VALUES
('John Student', 'john@student.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student'),
('Sarah Teacher', 'sarah@teacher.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher'),
('Mike Learner', 'mike@student.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student'),
('Mr. Sharma', 'teacher@school.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher'),
('Rahul Student', 'student@school.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student'),
('Dr. Gupta', 'dr.gupta@college.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher'),
('Emma Wilson', 'emma@student.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student'),
('Prof. Kumar', 'prof.kumar@university.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher');

-- Sample Institutes (created by teachers)
INSERT INTO institutes (user_id, name, description, contact_email, contact_phone) VALUES
(2, 'City High School', 'Main school campus for high school education', 'info@cityhigh.edu', '+1234567890'),
(2, 'Math Excellence Center', 'Advanced mathematics tuition center', 'contact@mathexcellence.com', '+0987654321'),
(4, 'Science Academy', 'Physics and Chemistry coaching institute', 'admin@scienceacademy.org', '+1122334455'),
(6, 'Delhi Public School', 'CBSE affiliated school', 'principal@dps.edu', '+4455667788'),
(8, 'University College', 'Undergraduate degree programs', 'admissions@university.edu', '+5566778899');

-- Sample Subjects
INSERT INTO subjects (institute_id, name, difficulty, color_code) VALUES
(1, 'Mathematics', 'medium', '#e74c3c'),
(1, 'Physics', 'hard', '#3498db'),
(1, 'English Literature', 'easy', '#2ecc71'),
(1, 'Chemistry', 'medium', '#9b59b6'),
(2, 'Advanced Calculus', 'hard', '#e67e22'),
(2, 'Linear Algebra', 'medium', '#1abc9c'),
(3, 'Organic Chemistry', 'hard', '#34495e'),
(3, 'Quantum Physics', 'hard', '#27ae60'),
(3, 'Biology', 'medium', '#8e44ad'),
(4, 'Computer Science', 'medium', '#c0392b'),
(4, 'Data Structures', 'hard', '#16a085'),
(4, 'Web Development', 'easy', '#2980b9'),
(5, 'Business Management', 'easy', '#f39c12'),
(5, 'Economics', 'medium', '#8e44ad'),
(5, 'Statistics', 'medium', '#27ae60');

-- Sample Student-Teacher Relationships (Enrollments)
INSERT INTO student_teacher_relationships (teacher_id, student_id, institute_id) VALUES
-- Sarah Teacher's students in City High School
(2, 1, 1), -- John Student in City High School
(2, 3, 1), -- Mike Learner in City High School
(2, 7, 1), -- Emma Wilson in City High School
-- Sarah Teacher's students in Math Excellence Center
(2, 1, 2), -- John Student in Math Excellence Center
(2, 5, 2), -- Rahul Student in Math Excellence Center
-- Mr. Sharma's students in Science Academy
(4, 1, 3), -- John Student in Science Academy
(4, 5, 3), -- Rahul Student in Science Academy
(4, 7, 3), -- Emma Wilson in Science Academy
-- Dr. Gupta's students in Delhi Public School
(6, 3, 4), -- Mike Learner in Delhi Public School
(6, 5, 4), -- Rahul Student in Delhi Public School
(6, 7, 4), -- Emma Wilson in Delhi Public School
-- Prof. Kumar's students in University College
(8, 1, 5), -- John Student in University College
(8, 3, 5), -- Mike Learner in University College
(8, 5, 5); -- Rahul Student in University College

-- Sample Attendance Goals
INSERT INTO attendance_goals (user_id, institute_id, target_percentage, warning_threshold) VALUES
(1, 1, 80.00, 75.00),
(1, 2, 85.00, 80.00),
(1, 3, 90.00, 85.00),
(1, 5, 75.00, 70.00),
(3, 1, 75.00, 70.00),
(3, 4, 80.00, 75.00),
(3, 5, 70.00, 65.00),
(5, 2, 95.00, 90.00),
(5, 3, 90.00, 85.00),
(5, 4, 85.00, 80.00),
(7, 1, 88.00, 83.00),
(7, 3, 92.00, 87.00),
(7, 4, 78.00, 73.00);

-- Sample Attendance Records (last 30 days with varied attendance to trigger warnings)
INSERT INTO attendance_records (user_id, institute_id, subject_id, attendance_date, selfie_image_path, status, notes) VALUES
-- John Student - Good attendance (80%+)
(1, 1, 1, CURDATE(), 'uploads/selfies/selfie1.jpg', 'present', 'Great class today!'),
(1, 1, 1, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'uploads/selfies/selfie2.jpg', 'present', 'Regular class'),
(1, 1, 1, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'uploads/selfies/selfie3.jpg', 'present', NULL),
(1, 1, 1, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'uploads/selfies/selfie4.jpg', 'present', 'Test preparation'),
(1, 1, 1, DATE_SUB(CURDATE(), INTERVAL 4 DAY), 'uploads/selfies/selfie5.jpg', 'absent', 'Sick leave'),
(1, 1, 2, CURDATE(), 'uploads/selfies/selfie6.jpg', 'present', 'Physics lab'),
(1, 1, 2, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'uploads/selfies/selfie7.jpg', 'present', NULL),
(1, 1, 2, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'uploads/selfies/selfie8.jpg', 'present', 'Experiment day'),

-- Mike Learner - Low attendance (will trigger warnings)
(3, 1, 1, CURDATE(), 'uploads/selfies/selfie9.jpg', 'present', NULL),
(3, 1, 1, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'uploads/selfies/selfie10.jpg', 'present', 'Made up class'),
(3, 1, 1, DATE_SUB(CURDATE(), INTERVAL 8 DAY), 'uploads/selfies/selfie11.jpg', 'absent', 'Family function'),
(3, 1, 1, DATE_SUB(CURDATE(), INTERVAL 10 DAY), 'uploads/selfies/selfie12.jpg', 'present', NULL),
(3, 1, 1, DATE_SUB(CURDATE(), INTERVAL 12 DAY), 'uploads/selfies/selfie13.jpg', 'absent', 'Medical appointment'),
(3, 1, 3, CURDATE(), 'uploads/selfies/selfie14.jpg', 'present', 'Literature discussion'),
(3, 1, 3, DATE_SUB(CURDATE(), INTERVAL 6 DAY), 'uploads/selfies/selfie15.jpg', 'absent', 'Transport issues'),

-- Rahul Student - Mixed attendance
(5, 3, 7, CURDATE(), 'uploads/selfies/selfie16.jpg', 'present', 'Organic chemistry lab'),
(5, 3, 7, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'uploads/selfies/selfie17.jpg', 'present', NULL),
(5, 3, 7, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'uploads/selfies/selfie18.jpg', 'absent', 'Had to leave early'),
(5, 3, 7, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'uploads/selfies/selfie19.jpg', 'present', 'Group study'),
(5, 3, 8, CURDATE(), 'uploads/selfies/selfie20.jpg', 'present', 'Quantum mechanics'),

-- Emma Wilson - Good attendance
(7, 1, 1, CURDATE(), 'uploads/selfies/selfie21.jpg', 'present', NULL),
(7, 1, 1, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'uploads/selfies/selfie22.jpg', 'present', 'Math test'),
(7, 1, 1, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'uploads/selfies/selfie23.jpg', 'present', 'Problem solving'),
(7, 1, 2, CURDATE(), 'uploads/selfies/selfie24.jpg', 'present', 'Physics practical');

-- Sample Attendance Warnings (automatically generated by the system)
INSERT INTO attendance_warnings (user_id, institute_id, subject_id, warning_type, message, attendance_percentage, threshold_percentage, is_read, created_at) VALUES
-- Mike Learner's warnings (low attendance)
(3, 1, 1, 'critical', 'Attendance is critically low! (40%)', 40.00, 70.00, 0, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(3, 1, 3, 'warning', 'Attendance is below target (50%)', 50.00, 75.00, 0, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(3, 4, NULL, 'warning', 'Attendance is below target (65%)', 65.00, 75.00, 1, DATE_SUB(NOW(), INTERVAL 3 DAY)),

-- Rahul Student's warnings
(5, 3, 7, 'warning', 'Attendance is below target (75%)', 75.00, 90.00, 0, DATE_SUB(NOW(), INTERVAL 1 DAY)),

-- Emma Wilson's read warnings (she improved)
(7, 4, NULL, 'warning', 'Attendance is below target (70%)', 70.00, 78.00, 1, DATE_SUB(NOW(), INTERVAL 5 DAY));

-- Sample Class Schedule
INSERT INTO class_schedule (institute_id, subject_id, day_of_week, start_time, end_time) VALUES
-- City High School Schedule
(1, 1, 'monday', '09:00:00', '10:00:00'),
(1, 1, 'wednesday', '09:00:00', '10:00:00'),
(1, 1, 'friday', '09:00:00', '10:00:00'),
(1, 2, 'tuesday', '10:00:00', '11:00:00'),
(1, 2, 'thursday', '10:00:00', '11:00:00'),
(1, 3, 'monday', '11:00:00', '12:00:00'),
(1, 3, 'wednesday', '11:00:00', '12:00:00'),
(1, 4, 'tuesday', '14:00:00', '15:00:00'),

-- Math Excellence Center
(2, 5, 'monday', '15:00:00', '17:00:00'),
(2, 5, 'thursday', '15:00:00', '17:00:00'),
(2, 6, 'tuesday', '16:00:00', '18:00:00'),

-- Science Academy
(3, 7, 'monday', '14:00:00', '16:00:00'),
(3, 7, 'thursday', '14:00:00', '16:00:00'),
(3, 8, 'tuesday', '15:00:00', '17:00:00'),
(3, 9, 'wednesday', '14:00:00', '16:00:00'),

-- Delhi Public School
(4, 10, 'monday', '10:00:00', '12:00:00'),
(4, 11, 'tuesday', '10:00:00', '12:00:00'),
(4, 12, 'wednesday', '10:00:00', '12:00:00'),

-- University College
(5, 13, 'monday', '13:00:00', '15:00:00'),
(5, 14, 'tuesday', '13:00:00', '15:00:00'),
(5, 15, 'wednesday', '13:00:00', '15:00:00');

-- Sample Holidays
INSERT INTO holidays (institute_id, holiday_date, description) VALUES
(1, '2024-12-25', 'Christmas Day'),
(1, '2024-01-01', 'New Year Day'),
(1, '2024-03-25', 'Holi Festival'),
(2, '2024-12-25', 'Christmas Break'),
(3, '2024-10-02', 'Gandhi Jayanti'),
(4, '2024-01-26', 'Republic Day'),
(5, '2024-08-15', 'Independence Day');

-- Create Useful Views

-- View for attendance summary
CREATE VIEW attendance_summary AS
SELECT 
    u.id as user_id,
    u.name as student_name,
    i.id as institute_id,
    i.name as institute_name,
    s.id as subject_id,
    s.name as subject_name,
    COUNT(ar.id) as total_classes,
    SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as attended_classes,
    CASE 
        WHEN COUNT(ar.id) > 0 THEN 
            ROUND((SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) / COUNT(ar.id)) * 100, 2)
        ELSE 0 
    END as attendance_percentage,
    ag.target_percentage,
    ag.warning_threshold,
    CASE 
        WHEN COUNT(ar.id) > 0 AND 
             (SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) / COUNT(ar.id)) * 100 < ag.warning_threshold 
        THEN 'LOW'
        ELSE 'OK'
    END as attendance_status
FROM users u
CROSS JOIN institutes i
CROSS JOIN subjects s
LEFT JOIN attendance_records ar ON u.id = ar.user_id AND i.id = ar.institute_id AND s.id = ar.subject_id
LEFT JOIN attendance_goals ag ON u.id = ag.user_id AND i.id = ag.institute_id
WHERE s.institute_id = i.id
GROUP BY u.id, i.id, s.id, ag.target_percentage, ag.warning_threshold;

-- View for user institute summary
CREATE VIEW user_institute_summary AS
SELECT 
    u.id as user_id,
    u.name as user_name,
    i.id as institute_id,
    i.name as institute_name,
    COUNT(DISTINCT s.id) as total_subjects,
    COUNT(ar.id) as total_attendance_records,
    SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as present_count,
    CASE 
        WHEN COUNT(ar.id) > 0 THEN 
            ROUND((SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) / COUNT(ar.id)) * 100, 2)
        ELSE 0 
    END as overall_attendance_percentage,
    ag.target_percentage,
    ag.warning_threshold
FROM users u
JOIN institutes i 
LEFT JOIN subjects s ON i.id = s.institute_id
LEFT JOIN attendance_records ar ON u.id = ar.user_id AND i.id = ar.institute_id AND s.id = ar.subject_id
LEFT JOIN attendance_goals ag ON u.id = ag.user_id AND i.id = ag.institute_id
GROUP BY u.id, i.id, ag.target_percentage, ag.warning_threshold;

-- View for teacher student relationships
CREATE VIEW teacher_student_view AS
SELECT 
    str.id as relationship_id,
    t.id as teacher_id,
    t.name as teacher_name,
    t.email as teacher_email,
    s.id as student_id,
    s.name as student_name,
    s.email as student_email,
    i.id as institute_id,
    i.name as institute_name,
    str.is_active,
    str.created_at
FROM student_teacher_relationships str
JOIN users t ON str.teacher_id = t.id
JOIN users s ON str.student_id = s.id
JOIN institutes i ON str.institute_id = i.id;

-- View for active warnings
CREATE VIEW active_warnings_view AS
SELECT 
    aw.*,
    u.name as student_name,
    u.email as student_email,
    i.name as institute_name,
    s.name as subject_name
FROM attendance_warnings aw
JOIN users u ON aw.user_id = u.id
JOIN institutes i ON aw.institute_id = i.id
LEFT JOIN subjects s ON aw.subject_id = s.id
WHERE aw.is_read = 0;

-- Display table creation confirmation
SELECT 'Database created successfully!' as status;

-- Show table counts
SELECT 
    'users' as table_name, 
    COUNT(*) as record_count 
FROM users
UNION ALL
SELECT 'institutes', COUNT(*) FROM institutes
UNION ALL
SELECT 'subjects', COUNT(*) FROM subjects
UNION ALL
SELECT 'student_teacher_relationships', COUNT(*) FROM student_teacher_relationships
UNION ALL
SELECT 'attendance_records', COUNT(*) FROM attendance_records
UNION ALL
SELECT 'attendance_goals', COUNT(*) FROM attendance_goals
UNION ALL
SELECT 'attendance_warnings', COUNT(*) FROM attendance_warnings
UNION ALL
SELECT 'class_schedule', COUNT(*) FROM class_schedule
UNION ALL
SELECT 'holidays', COUNT(*) FROM holidays;

-- Show sample data summary
SELECT 
    'Sample Data Summary' as summary,
    CONCAT(COUNT(*), ' users') as details
FROM users
UNION ALL
SELECT 'Institutes', CONCAT(COUNT(*), ' institutes') FROM institutes
UNION ALL
SELECT 'Subjects', CONCAT(COUNT(*), ' subjects') FROM subjects
UNION ALL
SELECT 'Enrollments', CONCAT(COUNT(*), ' student enrollments') FROM student_teacher_relationships
UNION ALL
SELECT 'Attendance Records', CONCAT(COUNT(*), ' records') FROM attendance_records
UNION ALL
SELECT 'Active Warnings', CONCAT(COUNT(*), ' unread warnings') FROM attendance_warnings WHERE is_read = 0;