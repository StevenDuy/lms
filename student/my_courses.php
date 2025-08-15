<?php
require_once '../config/config.php';
requireRole('student');

$user = getCurrentUser();

// Get enrolled courses with progress
$stmt = $pdo->prepare("
    SELECT c.*, u.full_name as lecturer_name, e.enrollment_date, e.status as enrollment_status,
           (SELECT COUNT(*) FROM materials WHERE course_id = c.id) as total_materials,
           (SELECT COUNT(DISTINCT tr.material_id) FROM test_results tr 
            JOIN materials m ON tr.material_id = m.id 
            WHERE m.course_id = c.id AND tr.student_id = ?) as completed_materials
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    JOIN users u ON c.lecturer_id = u.id
    WHERE e.student_id = ?
    ORDER BY e.enrollment_date DESC
");
$stmt->execute([$user['id'], $user['id']]);
$enrolled_courses = $stmt->fetchAll();

// Calculate progress for each course
foreach ($enrolled_courses as &$course) {
    if ($course['total_materials'] > 0) {
        $course['progress'] = round(($course['completed_materials'] / $course['total_materials']) * 100, 2);
    } else {
        $course['progress'] = 0;
    }

    // Update status based on progress
    if ($course['progress'] >= 100) {
        $course['enrollment_status'] = 'completed';
        // Update in database
        $updateStmt = $pdo->prepare("UPDATE enrollments SET status = 'completed', progress = ? WHERE student_id = ? AND course_id = ?");
        $updateStmt->execute([$course['progress'], $user['id'], $course['id']]);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses - Student Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .course-card {
            transition: transform 0.3s, box-shadow 0.3s;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }
        .progress-bar {
            height: 8px;
        }
        .status-badge {
            font-size: 0.8rem;
        }
        .completed-course {
            border-left: 4px solid #28a745;
        }
        .enrolled-course {
            border-left: 4px solid #007bff;
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
                        <a class="nav-link active" href="my_courses.php">
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

    <div class="container mt-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-user-graduate me-2 text-primary"></i>My Courses</h2>
            <div>
                <span class="badge bg-info me-2"><?php echo count($enrolled_courses); ?> enrolled</span>
                <span class="badge bg-success"><?php echo count(array_filter($enrolled_courses, function($c) { return $c['enrollment_status'] === 'completed'; })); ?> completed</span>
            </div>
        </div>

        <!-- Courses Grid -->
        <?php if (empty($enrolled_courses)): ?>
            <div class="text-center py-5">
                <i class="fas fa-graduation-cap fa-3x text-muted mb-3"></i>
                <h4 class="text-muted">No Enrolled Courses</h4>
                <p class="text-muted">You haven't enrolled in any courses yet.</p>
                <a href="dashboard.php" class="btn btn-primary">
                    <i class="fas fa-search me-2"></i>Browse Available Courses
                </a>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($enrolled_courses as $course): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card course-card h-100 <?php echo $course['enrollment_status'] === 'completed' ? 'completed-course' : 'enrolled-course'; ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h5 class="card-title text-primary"><?php echo htmlspecialchars($course['title']); ?></h5>
                                    <span class="badge <?php echo $course['enrollment_status'] === 'completed' ? 'bg-success' : 'bg-primary'; ?> status-badge">
                                        <?php echo $course['enrollment_status'] === 'completed' ? 'Completed' : 'In Progress'; ?>
                                    </span>
                                </div>

                                <p class="card-text text-muted"><?php echo htmlspecialchars(substr($course['description'], 0, 80)); ?>...</p>

                                <!-- Progress Bar -->
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <small class="text-muted">Progress</small>
                                        <small class="fw-bold"><?php echo $course['progress']; ?>%</small>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar <?php echo $course['enrollment_status'] === 'completed' ? 'bg-success' : 'bg-primary'; ?>" 
                                             style="width: <?php echo $course['progress']; ?>%"></div>
                                    </div>
                                </div>

                                <!-- Course Stats -->
                                <div class="row text-center mb-3">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Completed</small>
                                        <strong><?php echo $course['completed_materials']; ?>/<?php echo $course['total_materials']; ?></strong>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Enrolled</small>
                                        <strong><?php echo formatDate($course['enrollment_date']); ?></strong>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">by <?php echo htmlspecialchars($course['lecturer_name']); ?></small>
                                    <a href="course_learning.php?id=<?php echo $course['id']; ?>" class="btn btn-primary btn-sm">
                                        <?php echo $course['enrollment_status'] === 'completed' ? 'Review' : 'Continue'; ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Summary Cards -->
            <div class="row mt-5">
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-book fa-2x text-primary mb-3"></i>
                            <h4><?php echo count($enrolled_courses); ?></h4>
                            <p class="text-muted mb-0">Total Courses</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-clock fa-2x text-warning mb-3"></i>
                            <h4><?php echo count(array_filter($enrolled_courses, function($c) { return $c['enrollment_status'] === 'enrolled'; })); ?></h4>
                            <p class="text-muted mb-0">In Progress</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-trophy fa-2x text-success mb-3"></i>
                            <h4><?php echo count(array_filter($enrolled_courses, function($c) { return $c['enrollment_status'] === 'completed'; })); ?></h4>
                            <p class="text-muted mb-0">Completed</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>