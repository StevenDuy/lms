<?php
require_once '../config/config.php';
requireRole('student');

$user = getCurrentUser();
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Check enrollment
$stmt = $pdo->prepare("SELECT * FROM enrollments WHERE student_id = ? AND course_id = ?");
$stmt->execute([$user['id'], $course_id]);
$enrollment = $stmt->fetch();

if (!$enrollment) {
    redirect('dashboard.php');
}

// Get course details
$stmt = $pdo->prepare("
    SELECT c.*, u.full_name as lecturer_name
    FROM courses c 
    JOIN users u ON c.lecturer_id = u.id 
    WHERE c.id = ?
");
$stmt->execute([$course_id]);
$course = $stmt->fetch();

// Get course materials
$stmt = $pdo->prepare("
    SELECT m.*, 
           (SELECT COUNT(*) FROM test_results tr WHERE tr.material_id = m.id AND tr.student_id = ?) as completed
    FROM materials m 
    WHERE m.course_id = ? 
    ORDER BY m.order_index ASC
");
$stmt->execute([$user['id'], $course_id]);
$materials = $stmt->fetchAll();

// Calculate progress
$total_materials = count($materials);
$completed_materials = array_sum(array_column($materials, 'completed'));
$progress = $total_materials > 0 ? round(($completed_materials / $total_materials) * 100, 2) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($course['title']); ?> - Learning</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            background: #f8f9fa;
            min-height: calc(100vh - 56px);
            border-right: 1px solid #dee2e6;
        }
        .material-item {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin-bottom: 1rem;
            transition: all 0.3s;
        }
        .material-item:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .material-completed {
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        .material-lesson { border-left: 4px solid #007bff; }
        .material-assignment { border-left: 4px solid #ffc107; }
        .material-exam { border-left: 4px solid #dc3545; }
        .progress-bar { height: 8px; }
        .course-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem 0;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-graduation-cap me-2"></i>LMS - Student
            </a>
            <div class="navbar-nav me-auto">
                <a class="nav-link" href="my_courses.php">
                    <i class="fas fa-arrow-left me-1"></i>Back to My Courses
                </a>
            </div>
            <div class="navbar-nav">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($user['full_name']); ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Course Header -->
    <section class="course-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h4 class="mb-1"><?php echo htmlspecialchars($course['title']); ?></h4>
                    <p class="mb-0">Instructor: <?php echo htmlspecialchars($course['lecturer_name']); ?></p>
                </div>
                <div class="col-md-4">
                    <div class="text-end">
                        <small class="d-block">Course Progress</small>
                        <div class="progress bg-white bg-opacity-25" style="height: 8px;">
                            <div class="progress-bar bg-success" style="width: <?php echo $progress; ?>%"></div>
                        </div>
                        <small class="d-block mt-1"><?php echo $progress; ?>% Complete</small>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar - Course Materials -->
            <div class="col-md-4 col-lg-3 sidebar p-3">
                <h5 class="mb-3">
                    <i class="fas fa-list me-2"></i>Course Materials
                    <span class="badge bg-primary ms-2"><?php echo $completed_materials; ?>/<?php echo $total_materials; ?></span>
                </h5>

                <?php if (empty($materials)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-book-open fa-2x text-muted mb-2"></i>
                        <p class="text-muted">No materials available yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($materials as $material): ?>
                        <div class="material-item material-<?php echo $material['material_type']; ?> <?php echo $material['completed'] ? 'material-completed' : ''; ?> p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1">
                                        <?php if ($material['material_type'] === 'lesson'): ?>
                                            <i class="fas fa-book text-primary me-2"></i>
                                        <?php elseif ($material['material_type'] === 'assignment'): ?>
                                            <i class="fas fa-tasks text-warning me-2"></i>
                                        <?php elseif ($material['material_type'] === 'exam'): ?>
                                            <i class="fas fa-clipboard-check text-danger me-2"></i>
                                        <?php elseif ($material['material_type'] === 'pdf'): ?>
                                            <i class="fas fa-file-pdf text-danger me-2"></i>
                                        <?php else: ?>
                                            <i class="fas fa-link text-info me-2"></i>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($material['title']); ?>
                                    </h6>
                                    <small class="text-muted text-capitalize"><?php echo $material['material_type']; ?></small>
                                </div>
                                <div class="ms-2">
                                    <?php if ($material['completed']): ?>
                                        <i class="fas fa-check-circle text-success"></i>
                                    <?php elseif ($material['material_type'] === 'lesson' || $material['material_type'] === 'pdf' || $material['material_type'] === 'link'): ?>
                                        <?php if ($material['material_type'] === 'pdf'): ?>
                                            <?php 
                                            $stmt = $pdo->prepare("SELECT id FROM student_material_submissions WHERE student_id = ? AND material_id = ?");
                                            $stmt->execute([$user['id'], $material['id']]);
                                            $submission = $stmt->fetch();
                                            ?>
                                            <a href="view_material.php?id=<?php echo $material['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                            <?php if (!$submission): ?>
                                                <a href="submit_material.php?id=<?php echo $material['id']; ?>" class="btn btn-sm btn-success ms-1">Submit</a>
                                            <?php else: ?>
                                                <a href="submit_material.php?id=<?php echo $material['id']; ?>" class="btn btn-sm btn-outline-secondary ms-1">Edit</a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <a href="view_material.php?id=<?php echo $material['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <a href="take_test.php?id=<?php echo $material['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <?php echo $material['material_type'] === 'assignment' ? 'Take' : 'Start'; ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Main Content -->
            <div class="col-md-8 col-lg-9 p-4">
                <?php if (empty($materials)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-graduation-cap fa-3x text-muted mb-3"></i>
                        <h4>Welcome to <?php echo htmlspecialchars($course['title']); ?></h4>
                        <p class="text-muted">Course materials will appear here when available.</p>
                    </div>
                <?php else: ?>
                    <!-- Course Overview -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Course Overview</h5>
                        </div>
                        <div class="card-body">
                            <p><?php echo nl2br(htmlspecialchars($course['description'])); ?></p>

                            <div class="row mt-4">
                                <div class="col-md-3 text-center">
                                    <div class="bg-light p-3 rounded">
                                        <i class="fas fa-book fa-2x text-primary mb-2"></i>
                                        <h5><?php echo count(array_filter($materials, function($m) { return $m['material_type'] === 'lesson'; })); ?></h5>
                                        <small class="text-muted">Lessons</small>
                                    </div>
                                </div>
                                <div class="col-md-3 text-center">
                                    <div class="bg-light p-3 rounded">
                                        <i class="fas fa-tasks fa-2x text-warning mb-2"></i>
                                        <h5><?php echo count(array_filter($materials, function($m) { return $m['material_type'] === 'assignment'; })); ?></h5>
                                        <small class="text-muted">Assignments</small>
                                    </div>
                                </div>
                                <div class="col-md-3 text-center">
                                    <div class="bg-light p-3 rounded">
                                        <i class="fas fa-clipboard-check fa-2x text-danger mb-2"></i>
                                        <h5><?php echo count(array_filter($materials, function($m) { return $m['material_type'] === 'exam'; })); ?></h5>
                                        <small class="text-muted">Exams</small>
                                    </div>
                                </div>
                                <div class="col-md-3 text-center">
                                    <div class="bg-light p-3 rounded">
                                        <i class="fas fa-trophy fa-2x text-success mb-2"></i>
                                        <h5><?php echo $progress; ?>%</h5>
                                        <small class="text-muted">Complete</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Next Steps -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-play-circle me-2"></i>Next Steps</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($progress >= 100): ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-trophy me-2"></i>
                                    <strong>Congratulations!</strong> You have completed this course.
                                    <br>You can review materials or check your grades.
                                </div>
                                <a href="grades.php" class="btn btn-success">
                                    <i class="fas fa-chart-bar me-2"></i>View Your Grades
                                </a>
                            <?php else: ?>
                                <?php 
                                // Find next incomplete material
                                $next_material = null;
                                foreach ($materials as $material) {
                                    if (!$material['completed']) {
                                        $next_material = $material;
                                        break;
                                    }
                                }
                                ?>
                                <?php if ($next_material): ?>
                                    <h6>Continue with: <?php echo htmlspecialchars($next_material['title']); ?></h6>
                                    <p class="text-muted"><?php echo ucfirst($next_material['material_type']); ?></p>
                                    <?php if ($next_material['material_type'] === 'lesson' || $next_material['material_type'] === 'pdf' || $next_material['material_type'] === 'link'): ?>
                                        <?php if ($next_material['material_type'] === 'pdf'): ?>
                                            <a href="view_material.php?id=<?php echo $next_material['id']; ?>" class="btn btn-primary">
                                                <i class="fas fa-file-pdf me-2"></i>View PDF
                                            </a>
                                        <?php elseif ($next_material['material_type'] === 'link'): ?>
                                            <a href="view_material.php?id=<?php echo $next_material['id']; ?>" class="btn btn-primary">
                                                <i class="fas fa-link me-2"></i>Open Link
                                            </a>
                                        <?php else: ?>
                                            <a href="view_material.php?id=<?php echo $next_material['id']; ?>" class="btn btn-primary">
                                                <i class="fas fa-play me-2"></i>Start Lesson
                                            </a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <a href="take_test.php?id=<?php echo $next_material['id']; ?>" class="btn btn-primary">
                                            <i class="fas fa-pen me-2"></i><?php echo $next_material['material_type'] === 'assignment' ? 'Take Assignment' : 'Start Exam'; ?>
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>