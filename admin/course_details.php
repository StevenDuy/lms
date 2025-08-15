<?php
require_once '../config/config.php';
requireRole('admin');

$user = getCurrentUser();
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get course details
$stmt = $pdo->prepare("
    SELECT c.*, u.full_name as lecturer_name, u.email as lecturer_email,
           (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as student_count,
           (SELECT COUNT(*) FROM materials WHERE course_id = c.id) as material_count,
           (SELECT COUNT(*) FROM materials WHERE course_id = c.id AND material_type = 'lesson') as lesson_count,
           (SELECT COUNT(*) FROM materials WHERE course_id = c.id AND material_type = 'assignment') as assignment_count,
           (SELECT COUNT(*) FROM materials WHERE course_id = c.id AND material_type = 'exam') as exam_count
    FROM courses c
    JOIN users u ON c.lecturer_id = u.id
    WHERE c.id = ?
");
$stmt->execute([$course_id]);
$course = $stmt->fetch();

if (!$course) {
    redirect('dashboard.php');
}

// Get enrolled students
$stmt = $pdo->prepare("
    SELECT u.*, e.enrollment_date, e.status as enrollment_status,
           (SELECT COUNT(DISTINCT tr.material_id) FROM test_results tr 
            JOIN materials m ON tr.material_id = m.id 
            WHERE m.course_id = ? AND tr.student_id = u.id) as completed_materials
    FROM enrollments e
    JOIN users u ON e.student_id = u.id
    WHERE e.course_id = ?
    ORDER BY e.enrollment_date DESC
");
$stmt->execute([$course_id, $course_id]);
$students = $stmt->fetchAll();

// Get course materials
$stmt = $pdo->prepare("SELECT * FROM materials WHERE course_id = ? ORDER BY order_index ASC");
$stmt->execute([$course_id]);
$materials = $stmt->fetchAll();
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
        .student-card {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .material-item {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }
        .material-lesson { border-left: 4px solid #007bff; }
        .material-assignment { border-left: 4px solid #ffc107; }
        .material-exam { border-left: 4px solid #dc3545; }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-user-shield me-2"></i>LMS - Admin
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-book me-1"></i>All Courses
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">
                            <i class="fas fa-users me-1"></i>Users
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
                    <li class="breadcrumb-item"><a href="dashboard.php" class="text-white-50">All Courses</a></li>
                    <li class="breadcrumb-item active text-white"><?php echo htmlspecialchars($course['title']); ?></li>
                </ol>
            </nav>

            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="display-6 fw-bold mb-3"><?php echo htmlspecialchars($course['title']); ?></h1>
                    <p class="lead mb-3"><?php echo htmlspecialchars($course['description']); ?></p>
                    <div class="d-flex flex-wrap gap-3">
                        <span class="badge bg-light text-dark">
                            <i class="fas fa-user me-1"></i>Lecturer: <?php echo htmlspecialchars($course['lecturer_name']); ?>
                        </span>
                        <span class="badge bg-light text-dark">
                            <i class="fas fa-calendar me-1"></i>Created: <?php echo formatDate($course['created_at']); ?>
                        </span>
                        <span class="badge bg-<?php echo $course['status'] === 'active' ? 'success' : 'danger'; ?>">
                            <?php echo ucfirst($course['status']); ?>
                        </span>
                    </div>
                </div>
                <div class="col-lg-4 text-end">
                    <a href="edit_course.php?id=<?php echo $course['id']; ?>" class="btn btn-light btn-lg me-2">
                        <i class="fas fa-edit me-2"></i>Edit Course
                    </a>
                </div>
            </div>
        </div>
    </section>

    <div class="container mt-5">
        <!-- Course Statistics -->
        <div class="row g-4 mb-5">
            <div class="col-md-3">
                <div class="card stat-card text-center p-4">
                    <div class="card-body">
                        <i class="fas fa-users fa-3x text-primary mb-3"></i>
                        <h3 class="fw-bold"><?php echo $course['student_count']; ?></h3>
                        <p class="text-muted mb-0">Enrolled Students</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card text-center p-4">
                    <div class="card-body">
                        <i class="fas fa-book fa-3x text-success mb-3"></i>
                        <h3 class="fw-bold"><?php echo $course['lesson_count']; ?></h3>
                        <p class="text-muted mb-0">Lessons</p>
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

        <!-- Course Content Tabs -->
        <ul class="nav nav-tabs" id="courseTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="students-tab" data-bs-toggle="tab" data-bs-target="#students" type="button" role="tab">
                    <i class="fas fa-users me-2"></i>Enrolled Students (<?php echo count($students); ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="materials-tab" data-bs-toggle="tab" data-bs-target="#materials" type="button" role="tab">
                    <i class="fas fa-book me-2"></i>Course Materials (<?php echo count($materials); ?>)
                </button>
            </li>
        </ul>

        <div class="tab-content" id="courseTabContent">
            <!-- Students Tab -->
            <div class="tab-pane fade show active" id="students" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <?php if (empty($students)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-user-graduate fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No Students Enrolled</h5>
                                <p class="text-muted">This course has no enrolled students yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Student</th>
                                            <th>Email</th>
                                            <th>Enrollment Date</th>
                                            <th>Progress</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $student): ?>
                                            <?php 
                                            $progress = $course['material_count'] > 0 ? 
                                                round(($student['completed_materials'] / $course['material_count']) * 100, 2) : 0;
                                            ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-user-circle fa-2x text-muted me-3"></i>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($student['full_name']); ?></strong><br>
                                                            <small class="text-muted">@<?php echo htmlspecialchars($student['username']); ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                                <td><?php echo formatDate($student['enrollment_date']); ?></td>
                                                <td>
                                                    <div class="progress" style="height: 6px;">
                                                        <div class="progress-bar" style="width: <?php echo $progress; ?>%"></div>
                                                    </div>
                                                    <small><?php echo $progress; ?>%</small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $student['enrollment_status'] === 'completed' ? 'success' : 'primary'; ?>">
                                                        <?php echo ucfirst($student['enrollment_status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="grades.php?course=<?php echo $course_id; ?>&student=<?php echo $student['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-chart-bar me-1"></i>View Grades
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Materials Tab -->
            <div class="tab-pane fade" id="materials" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <?php if (empty($materials)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-book-open fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No Materials Available</h5>
                                <p class="text-muted">This course has no materials yet.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($materials as $material): ?>
                                <div class="material-item material-<?php echo $material['material_type']; ?> p-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">
                                                <?php if ($material['material_type'] === 'lesson'): ?>
                                                    <i class="fas fa-book text-primary me-2"></i>
                                                <?php elseif ($material['material_type'] === 'assignment'): ?>
                                                    <i class="fas fa-tasks text-warning me-2"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-clipboard-check text-danger me-2"></i>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($material['title']); ?>
                                            </h6>
                                            <p class="text-muted mb-0"><?php echo htmlspecialchars(substr($material['content'], 0, 100)); ?>...</p>
                                            <small class="text-muted text-capitalize">
                                                <?php echo $material['material_type']; ?> #<?php echo $material['order_index']; ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>