<?php
require_once '../config/config.php';
requireRole('student');

$user = getCurrentUser();
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get course details
$stmt = $pdo->prepare("
    SELECT c.*, u.full_name as lecturer_name,
           (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as student_count,
           (SELECT COUNT(*) FROM materials WHERE course_id = c.id) as lesson_count,
           (SELECT COUNT(*) FROM materials WHERE course_id = c.id AND material_type = 'assignment') as assignment_count,
           (SELECT COUNT(*) FROM materials WHERE course_id = c.id AND material_type = 'exam') as exam_count
    FROM courses c 
    JOIN users u ON c.lecturer_id = u.id 
    WHERE c.id = ? AND c.status = 'active'
");
$stmt->execute([$course_id]);
$course = $stmt->fetch();

if (!$course) {
    redirect('dashboard.php');
}

// Check if already enrolled
$stmt = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = ? AND course_id = ?");
$stmt->execute([$user['id'], $course_id]);
$is_enrolled = $stmt->fetch();

// Handle enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll']) && !$is_enrolled) {
    $stmt = $pdo->prepare("INSERT INTO enrollments (student_id, course_id) VALUES (?, ?)");
    if ($stmt->execute([$user['id'], $course_id])) {
        redirect('my_courses.php');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($course['title']); ?> - Course Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .course-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 0;
        }
        .stat-card {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .enroll-btn {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .enroll-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
            color: white;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-graduation-cap me-2"></i>LMS - Student
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-book me-1"></i>Courses
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my_courses.php">
                            <i class="fas fa-user-graduate me-1"></i>My Courses
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="grades.php">
                            <i class="fas fa-chart-bar me-1"></i>Grades
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($user['full_name']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Course Header -->
    <section class="course-header">
        <div class="container">
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php" class="text-white-50">Courses</a></li>
                    <li class="breadcrumb-item active text-white"><?php echo htmlspecialchars($course['title']); ?></li>
                </ol>
            </nav>

            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="display-5 fw-bold mb-3"><?php echo htmlspecialchars($course['title']); ?></h1>
                    <p class="lead mb-4"><?php echo htmlspecialchars($course['description']); ?></p>
                    <div class="d-flex align-items-center mb-3">
                        <i class="fas fa-user me-2"></i>
                        <span>Instructor: <strong><?php echo htmlspecialchars($course['lecturer_name']); ?></strong></span>
                    </div>
                    <div class="d-flex align-items-center">
                        <i class="fas fa-calendar me-2"></i>
                        <span>Created: <?php echo formatDate($course['created_at']); ?></span>
                    </div>
                </div>
                <div class="col-lg-4 text-center">
                    <?php if ($is_enrolled): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>You are enrolled in this course
                        </div>
                        <a href="my_courses.php" class="btn btn-light btn-lg">
                            <i class="fas fa-arrow-right me-2"></i>Go to My Courses
                        </a>
                    <?php else: ?>
                        <form method="POST">
                            <button type="submit" name="enroll" class="btn enroll-btn btn-lg">
                                <i class="fas fa-plus me-2"></i>Enroll in Course
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Course Statistics -->
    <div class="container mt-5">
        <div class="row g-4">
            <div class="col-md-3">
                <div class="card stat-card text-center p-4">
                    <div class="card-body">
                        <i class="fas fa-users fa-3x text-primary mb-3"></i>
                        <h3 class="fw-bold"><?php echo $course['student_count']; ?></h3>
                        <p class="text-muted mb-0">Students Enrolled</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card text-center p-4">
                    <div class="card-body">
                        <i class="fas fa-book fa-3x text-success mb-3"></i>
                        <h3 class="fw-bold"><?php echo $course['lesson_count']; ?></h3>
                        <p class="text-muted mb-0">Total Lessons</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card text-center p-4">
                    <div class="card-body">
                        <i class="fas fa-tasks fa-3x text-warning mb-3"></i>
                        <h3 class="fw-bold"><?php echo $course['assignment_count']; ?></h3>
                        <p class="text-muted mb-0">Assignments</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card text-center p-4">
                    <div class="card-body">
                        <i class="fas fa-clipboard-check fa-3x text-danger mb-3"></i>
                        <h3 class="fw-bold"><?php echo $course['exam_count']; ?></h3>
                        <p class="text-muted mb-0">Exams</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Course Information -->
    <div class="container mt-5 mb-5">
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Course Information</h5>
                    </div>
                    <div class="card-body">
                        <h6>Description</h6>
                        <p><?php echo nl2br(htmlspecialchars($course['description'])); ?></p>

                        <h6 class="mt-4">What You'll Learn</h6>
                        <ul>
                            <li>Comprehensive understanding of the subject matter</li>
                            <li>Practical skills through assignments and projects</li>
                            <li>Assessment through quizzes and exams</li>
                            <li>Expert guidance from qualified instructors</li>
                        </ul>

                        <h6 class="mt-4">Course Structure</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success me-2"></i><?php echo $course['lesson_count']; ?> Interactive Lessons</li>
                                    <li><i class="fas fa-check text-success me-2"></i><?php echo $course['assignment_count']; ?> Practice Assignments</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success me-2"></i><?php echo $course['exam_count']; ?> Assessment Exams</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Progress Tracking</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-user-tie me-2"></i>Instructor</h5>
                    </div>
                    <div class="card-body text-center">
                        <i class="fas fa-user-circle fa-3x text-muted mb-3"></i>
                        <h6><?php echo htmlspecialchars($course['lecturer_name']); ?></h6>
                        <p class="text-muted">Course Instructor</p>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Course Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Created:</span>
                            <span><?php echo formatDate($course['created_at']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Last Updated:</span>
                            <span><?php echo formatDate($course['updated_at']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Status:</span>
                            <span class="badge bg-success">Active</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>