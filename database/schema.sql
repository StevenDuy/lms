-- LMS Database Schema
CREATE DATABASE IF NOT EXISTS lms_db;
USE lms_db;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('student', 'lecturer', 'admin') NOT NULL,
    avatar VARCHAR(255) DEFAULT 'default-avatar.png',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Courses table
CREATE TABLE courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    lecturer_id INT NOT NULL,
    thumbnail VARCHAR(255) DEFAULT 'default-course.jpg',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Course enrollments
CREATE TABLE enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    progress DECIMAL(5,2) DEFAULT 0.00,
    status ENUM('enrolled', 'completed') DEFAULT 'enrolled',
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_enrollment (student_id, course_id)
);

-- Course materials (lessons)
CREATE TABLE materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    material_type ENUM('lesson', 'assignment', 'exam', 'pdf', 'link') NOT NULL,
    file_path VARCHAR(255) DEFAULT NULL,
    file_size INT DEFAULT NULL,
    file_type VARCHAR(50) DEFAULT NULL,
    external_link VARCHAR(500) DEFAULT NULL,
    order_index INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- Questions for assignments and exams
CREATE TABLE questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    material_id INT NOT NULL,
    question_text TEXT NOT NULL,
    option_a VARCHAR(500) NOT NULL,
    option_b VARCHAR(500) NOT NULL,
    option_c VARCHAR(500) NOT NULL,
    option_d VARCHAR(500) NOT NULL,
    correct_answer ENUM('A', 'B', 'C', 'D') NOT NULL,
    points INT DEFAULT 1,
    FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE
);

-- Student answers and grades
CREATE TABLE student_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_answer ENUM('A', 'B', 'C', 'D') NOT NULL,
    is_correct BOOLEAN NOT NULL,
    attempt_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);

-- Test/Exam results
CREATE TABLE test_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    material_id INT NOT NULL,
    score DECIMAL(5,2) NOT NULL,
    total_points INT NOT NULL,
    percentage DECIMAL(5,2) NOT NULL,
    attempt_count INT DEFAULT 1,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    feedback TEXT,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE
);

-- Student material submissions (for PDF materials)
CREATE TABLE student_material_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    material_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    grade DECIMAL(5,2) DEFAULT NULL,
    feedback TEXT DEFAULT NULL,
    graded_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE,
    UNIQUE KEY unique_submission (student_id, material_id)
);

-- Insert default admin user
INSERT INTO users (username, email, password, full_name, role) VALUES 
('admin', 'admin@lms.com', MD5('admin123'), 'System Administrator', 'admin');

-- Insert sample lecturer
INSERT INTO users (username, email, password, full_name, role) VALUES 
('lecturer1', 'lecturer@lms.com', MD5('lecturer123'), 'John Lecturer', 'lecturer');

-- Insert sample student
INSERT INTO users (username, email, password, full_name, role) VALUES 
('student1', 'student@lms.com', MD5('student123'), 'Jane Student', 'student');

-- Insert sample course
INSERT INTO courses (title, description, lecturer_id) VALUES 
('Web Development Basics', 'Learn the fundamentals of web development including HTML, CSS, and JavaScript', 2);

-- Insert sample materials
INSERT INTO materials (course_id, title, content, material_type, order_index) VALUES 
(1, 'Introduction to HTML', 'Learn the basics of HTML markup language...', 'lesson', 1),
(1, 'CSS Fundamentals', 'Understanding CSS for styling web pages...', 'lesson', 2),
(1, 'HTML Quiz', 'Test your knowledge of HTML', 'assignment', 3),
(1, 'Final Exam', 'Comprehensive exam covering all topics', 'exam', 4);

-- Insert sample questions
INSERT INTO questions (material_id, question_text, option_a, option_b, option_c, option_d, correct_answer, points) VALUES 
(3, 'What does HTML stand for?', 'Hyper Text Markup Language', 'High Tech Modern Language', 'Home Tool Markup Language', 'Hyperlink and Text Markup Language', 'A', 2),
(3, 'Which HTML tag is used for the largest heading?', '<h6>', '<h1>', '<heading>', '<header>', 'B', 2),
(4, 'Which CSS property is used to change text color?', 'font-color', 'text-color', 'color', 'foreground-color', 'C', 3),
(4, 'What is the correct HTML element for inserting a line break?', '<break>', '<br>', '<lb>', '<newline>', 'B', 3);

-- ==============================================
-- TÀI LIỆU HƯỚNG DẪN SỬ DỤNG TÍNH NĂNG MỚI
-- ==============================================

/*
Tính năng Quản lý Tài liệu (Material) mới

Giới thiệu:
Tính năng quản lý tài liệu mới đã được thêm vào hệ thống LMS, cho phép giảng viên tải lên tài liệu PDF và liên kết đến các tài nguyên bên ngoài. Sinh viên có thể xem tài liệu trực tuyến, tải về và nộp bài tập PDF.

Các tính năng mới:

1. Tài liệu PDF
- Giảng viên có thể tải lên tài liệu PDF
- Sinh viên có thể xem trực tuyến tài liệu PDF
- Sinh viên có thể tải tài liệu PDF về máy
- Giảng viên có thể tạo bài tập và yêu cầu sinh viên nộp bài PDF
- Giảng viên có thể xem và chấm điểm các bài nộp của sinh viên

2. Liên kết ngoài (External Links)
- Giảng viên có thể thêm liên kết đến các tài nguyên bên ngoài
- Sinh viên có thể mở liên kết trong tab mới
- Liên kết có thể trỏ đến bất kỳ trang web nào

Sử dụng tính năng:

1. Thêm tài liệu PDF hoặc Link
- Đăng nhập với tư cách giảng viên
- Vào chi tiết khóa học
- Nhấn nút "Add Material"
- Chọn loại tài liệu: PDF Document hoặc External Link
- Điền thông tin và tải lên file hoặc nhập liên kết
- Nhấn "Add Material" để lưu

2. Xem tài liệu
- Đăng nhập với tư cách sinh viên
- Vào khóa học tương ứng
- Nhấn nút "View" để xem tài liệu
- Với tài liệu PDF, bạn có thể:
  * Xem trực tuyến
  * Tải về máy
  * Nộp bài (nếu là bài tập)

3. Nộp bài PDF
- Vào chi tiết khóa học
- Tìm tài liệu PDF cần nộp
- Nhấn nút "Submit"
- Chọn file PDF từ máy của bạn
- Nhấn "Submit Assignment" để nộp bài

4. Chấm điểm bài nộp
- Đăng nhập với tư cách giảng viên
- Vào chi tiết khóa học
- Tìm tài liệu PDF cần chấm điểm
- Nhấn nút "Grade" để xem danh sách bài nộp
- Nhấn nút "Grade" để chấm điểm và đưa phản hồi
- Nhấn "Save Grade" để lưu kết quả

Lưu ý:
- Tài liệu PDF sẽ được lưu trong thư mục uploads/materials/
- Bài nộp của sinh viên sẽ được lưu trong thư mục uploads/submissions/
- Đảm bảo các thư mục này có quyền ghi cho web server

Các file đã được cập nhật/ tạo mới:
- database/schema.sql: File này - đã được cập nhật cấu trúc bảng materials và thêm bảng student_material_submissions
- lecturer/add_material.php: Thêm chức năng tải lên PDF và link
- lecturer/course_details.php: Hiển thị và quản lý tài liệu PDF và link
- lecturer/grade_material.php: Xem và chấm điểm bài nộp sinh viên
- student/view_material.php: Hiển thị tài liệu PDF và link
- student/course_learning.php: Hiển thị và truy cập tài liệu PDF và link
- student/submit_material.php: Nộp bài PDF
*/
