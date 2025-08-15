<?php
require_once '../config/config.php';
requireRole('lecturer');

$user = getCurrentUser();
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get course details and verify ownership
$stmt = $pdo->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as student_count,
           (SELECT COUNT(*) FROM materials WHERE course_id = c.id) as material_count
    FROM courses c 
    WHERE c.id = ? AND c.lecturer_id = ?
");
$stmt->execute([$course_id, $user['id']]);
$course = $stmt->fetch();

if (!$course) {
    redirect('dashboard.php');
}

// Get enrolled students
$stmt = $pdo->prepare("
    SELECT u.id, u.full_name, u.email, e.enrollment_date, e.status, e.progress
    FROM enrollments e
    JOIN users u ON e.student_id = u.id
    WHERE e.course_id = ?
    ORDER BY e.enrollment_date DESC
");
$stmt->execute([$course_id]);
$students = $stmt->fetchAll();

// Get course materials
$stmt = $pdo->prepare("
    SELECT m.*, 
           (SELECT COUNT(*) FROM test_results tr WHERE tr.material_id = m.id) as completion_count
    FROM materials m 
    WHERE m.course_id = ? 
    ORDER BY m.order_index ASC
");
$stmt->execute([$course_id]);
$materials = $stmt->fetchAll();

// Handle material deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_material'])) {
    $material_id = (int)$_POST['material_id'];

    // Delete related data first
    $pdo->prepare("DELETE FROM student_answers WHERE question_id IN (SELECT id FROM questions WHERE material_id = ?)")->execute([$material_id]);
    $pdo->prepare("DELETE FROM test_results WHERE material_id = ?")->execute([$material_id]);
    $pdo->prepare("DELETE FROM questions WHERE material_id = ?")->execute([$material_id]);
    $pdo->prepare("DELETE FROM materials WHERE id = ? AND course_id = ?")->execute([$material_id, $course_id]);

    redirect('course_details.php?id=' . $course_id);
}

// Calculate course statistics
$lesson_count = count(array_filter($materials, function($m) { return $m['material_type'] === 'lesson'; }));
$assignment_count = count(array_filter($materials, function($m) { return $m['material_type'] === 'assignment'; }));
$exam_count = count(array_filter($materials, function($m) { return $m['material_type'] === 'exam'; }));
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
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 2rem 0;
        }
        .stat-card {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
        }
        .material-card {
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
        }
        .material-lesson { border-left: 4px solid #007bff; }
        .material-assignment { border-left: 4px solid #ffc107; }
        .material-exam { border-left: 4px solid #dc3545; }
        .student-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-chalkboard-teacher me-2"></i>LMS - Lecturer
            </a>
            <div class="navbar-nav me-auto">
                <a class="nav-link" href="dashboard.php">
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
        <div class="container">
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php" class="text-white-50">My Courses</a></li>
                    <li class="breadcrumb-item active text-white"><?php echo htmlspecialchars($course['title']); ?></li>
                </ol>
            </nav>

            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="display-5 fw-bold mb-2"><?php echo htmlspecialchars($course['title']); ?></h1>
                    <p class="lead mb-3"><?php echo htmlspecialchars($course['description']); ?></p>
                    <span class="badge <?php echo $course['status'] === 'active' ? 'bg-light text-dark' : 'bg-warning text-dark'; ?> me-2">
                        <?php echo ucfirst($course['status']); ?>
                    </span>
                    <small class="text-white-50">Created <?php echo formatDate($course['created_at']); ?></small>
                </div>
                <div class="col-md-4 text-end">
                    <a href="edit_course.php?id=<?php echo $course['id']; ?>" class="btn btn-light btn-lg me-2">
                        <i class="fas fa-edit me-2"></i>Edit Course
                    </a>
                    <a href="add_material.php?course_id=<?php echo $course['id']; ?>" class="btn btn-outline-light btn-lg">
                        <i class="fas fa-plus me-2"></i>Add Material
                    </a>
                </div>
            </div>
        </div>
    </section>

    <div class="container mt-4">
        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card stat-card text-center p-3">
                    <div class="card-body">
                        <i class="fas fa-users fa-3x text-primary mb-3"></i>
                        <h3 class="fw-bold"><?php echo $course['student_count']; ?></h3>
                        <p class="text-muted mb-0">Enrolled Students</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card text-center p-3">
                    <div class="card-body">
                        <i class="fas fa-book fa-3x text-info mb-3"></i>
                        <h3 class="fw-bold"><?php echo $lesson_count; ?></h3>
                        <p class="text-muted mb-0">Lessons</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card text-center p-3">
                    <div class="card-body">
                        <i class="fas fa-tasks fa-3x text-warning mb-3"></i>
                        <h3 class="fw-bold"><?php echo $assignment_count; ?></h3>
                        <p class="text-muted mb-0">Assignments</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card text-center p-3">
                    <div class="card-body">
                        <i class="fas fa-clipboard-check fa-3x text-danger mb-3"></i>
                        <h3 class="fw-bold"><?php echo $exam_count; ?></h3>
                        <p class="text-muted mb-0">Exams</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Course Materials -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Course Materials</h5>
                        <a href="add_material.php?course_id=<?php echo $course['id']; ?>" class="btn btn-success btn-sm">
                            <i class="fas fa-plus me-1"></i>Add Material
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($materials)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-book-open fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No Materials Added</h5>
                                <p class="text-muted">Start building your course by adding lessons, assignments, and exams.</p>
                                <a href="add_material.php?course_id=<?php echo $course['id']; ?>" class="btn btn-success">
                                    <i class="fas fa-plus me-2"></i>Add First Material
                                </a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($materials as $material): ?>
                                <div class="material-card material-<?php echo $material['material_type']; ?> card mb-3">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h6 class="card-title mb-1">
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
                                                <p class="card-text text-muted mb-2"><?php echo htmlspecialchars(substr($material['content'], 0, 100)); ?>...</p>
                                                <div class="d-flex align-items-center">
                                                    <span class="badge bg-secondary me-2"><?php echo ucfirst($material['material_type']); ?></span>
                                                    
                                                    <?php if ($material['material_type'] === 'pdf'): ?>
                                                        <?php 
                                                        $stmt = $pdo->prepare("SELECT COUNT(*) as submission_count FROM student_material_submissions WHERE material_id = ?");
                                                        $stmt->execute([$material['id']]);
                                                        $submission_count = $stmt->fetch()['submission_count'];
                                                        ?>
                                                        <small class="text-muted"><?php echo $submission_count; ?> submissions</small>
                                                    <?php else: ?>
                                                        <small class="text-muted"><?php echo $material['completion_count']; ?> completions</small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="ms-3">
                                                <div class="btn-group">
                                                    <?php if ($material['material_type'] === 'pdf'): ?>
                                                        <a href="grade_material.php?id=<?php echo $material['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                            <i class="fas fa-clipboard-check"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="edit_material.php?id=<?php echo $material['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="confirmDelete(<?php echo $material['id']; ?>, '<?php echo htmlspecialchars($material['title']); ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Enrolled Students -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-users me-2"></i>Enrolled Students</h5>
                    </div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        <?php if (empty($students)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-user-graduate fa-2x text-muted mb-2"></i>
                                <p class="text-muted mb-0">No students enrolled yet</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($students as $student): ?>
                                <div class="d-flex align-items-center mb-3 p-2 bg-light rounded">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($student['full_name']); ?></h6>
                                        <small class="text-muted"><?php echo htmlspecialchars($student['email']); ?></small>
                                        <div class="mt-1">
                                            <span class="badge <?php echo $student['status'] === 'completed' ? 'bg-success' : 'bg-primary'; ?>">
                                                <?php echo ucfirst($student['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted d-block">Enrolled</small>
                                        <small class="text-muted"><?php echo formatDate($student['enrollment_date']); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete "<span id="materialTitle"></span>"?</p>
                    <p class="text-danger"><strong>Warning:</strong> This action cannot be undone and will delete all associated questions and student results.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="material_id" id="deleteMaterialId">
                        <button type="submit" name="delete_material" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(materialId, materialTitle) {
            document.getElementById('deleteMaterialId').value = materialId;
            document.getElementById('materialTitle').textContent = materialTitle;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
</body>
</html>