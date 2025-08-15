<?php
require_once '../config/config.php';
requireRole('admin');

$user = getCurrentUser();

// Get all courses for selection
$stmt = $pdo->prepare("
    SELECT c.id, c.title, u.full_name as lecturer_name,
           (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as student_count
    FROM courses c 
    JOIN users u ON c.lecturer_id = u.id 
    ORDER BY c.title ASC
");
$stmt->execute();
$courses = $stmt->fetchAll();

// Handle course selection
$selected_course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$course_details = null;
$course_grades = [];

if ($selected_course_id) {
    // Get course details
    $stmt = $pdo->prepare("
        SELECT c.*, u.full_name as lecturer_name
        FROM courses c 
        JOIN users u ON c.lecturer_id = u.id 
        WHERE c.id = ?
    ");
    $stmt->execute([$selected_course_id]);
    $course_details = $stmt->fetch();

    if ($course_details) {
        // Get student grades for this course
        $stmt = $pdo->prepare("
            SELECT 
                u.id as student_id,
                u.full_name as student_name,
                u.email,
                tr.id as result_id,
                tr.score,
                tr.total_points,
                tr.percentage,
                tr.completed_at,
                tr.feedback,
                m.title as material_title,
                m.material_type
            FROM enrollments e
            JOIN users u ON e.student_id = u.id
            LEFT JOIN test_results tr ON tr.student_id = u.id
            LEFT JOIN materials m ON tr.material_id = m.id AND m.course_id = ?
            WHERE e.course_id = ?
            ORDER BY u.full_name ASC, tr.completed_at DESC
        ");
        $stmt->execute([$selected_course_id, $selected_course_id]);
        $results = $stmt->fetchAll();

        // Group by student
        $course_grades = [];
        foreach ($results as $result) {
            $student_id = $result['student_id'];
            if (!isset($course_grades[$student_id])) {
                $course_grades[$student_id] = [
                    'student_name' => $result['student_name'],
                    'email' => $result['email'],
                    'grades' => [],
                    'average' => 0,
                    'total_points' => 0,
                    'earned_points' => 0
                ];
            }

            if ($result['result_id']) {
                $course_grades[$student_id]['grades'][] = $result;
                $course_grades[$student_id]['total_points'] += $result['total_points'];
                $course_grades[$student_id]['earned_points'] += $result['score'];
            }
        }

        // Calculate averages
        foreach ($course_grades as &$student_data) {
            if ($student_data['total_points'] > 0) {
                $student_data['average'] = round(($student_data['earned_points'] / $student_data['total_points']) * 100, 2);
            }
        }
    }
}

// Handle grade editing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_grade'])) {
    $result_id = (int)$_POST['result_id'];
    $new_percentage = (float)$_POST['new_percentage'];
    $feedback = trim($_POST['feedback']);

    if ($new_percentage >= 0 && $new_percentage <= 100) {
        // Get current result details
        $stmt = $pdo->prepare("SELECT total_points FROM test_results WHERE id = ?");
        $stmt->execute([$result_id]);
        $result = $stmt->fetch();

        if ($result) {
            $new_score = round(($new_percentage / 100) * $result['total_points'], 2);

            $stmt = $pdo->prepare("UPDATE test_results SET score = ?, percentage = ?, feedback = ? WHERE id = ?");
            if ($stmt->execute([$new_score, $new_percentage, $feedback, $result_id])) {
                $message = 'Grade updated successfully!';
                // Refresh the page
                header("Location: grades.php?course_id=" . $selected_course_id);
                exit;
            }
        }
    }
}

function getGradeLetter($percentage) {
    if ($percentage >= 90) return 'A+';
    elseif ($percentage >= 85) return 'A';
    elseif ($percentage >= 80) return 'B+';
    elseif ($percentage >= 75) return 'B';
    elseif ($percentage >= 70) return 'C+';
    elseif ($percentage >= 65) return 'C';
    elseif ($percentage >= 60) return 'D';
    else return 'F';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grades Management - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .course-card {
            transition: transform 0.3s, box-shadow 0.3s;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            cursor: pointer;
        }
        .course-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .course-card.selected {
            border: 2px solid #667eea;
            background-color: #f8f9ff;
        }
        .grade-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .grade-A { color: #28a745; font-weight: bold; }
        .grade-B { color: #007bff; font-weight: bold; }
        .grade-C { color: #ffc107; font-weight: bold; }
        .grade-D { color: #fd7e14; font-weight: bold; }
        .grade-F { color: #dc3545; font-weight: bold; }
        .editable-grade {
            background-color: #fff3cd;
            border: 1px dashed #ffc107;
            padding: 2px 6px;
            border-radius: 4px;
            cursor: pointer;
        }
        .editable-grade:hover {
            background-color: #ffeaa7;
        }
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
                        <a class="nav-link active" href="grades.php">
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
            <h2><i class="fas fa-chart-bar me-2 text-primary"></i>Grades Management</h2>
            <span class="badge bg-info"><?php echo count($courses); ?> courses</span>
        </div>

        <?php if (empty($courses)): ?>
            <div class="text-center py-5">
                <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                <h4 class="text-muted">No Courses Available</h4>
                <p class="text-muted">No courses have been created in the system yet.</p>
            </div>
        <?php else: ?>
            <!-- Course Selection -->
            <div class="mb-4">
                <h5>Select a Course to View Student Grades:</h5>
                <div class="row g-3">
                    <?php foreach ($courses as $course): ?>
                        <div class="col-md-4">
                            <div class="card course-card <?php echo $course['id'] == $selected_course_id ? 'selected' : ''; ?>" 
                                 onclick="window.location.href='grades.php?course_id=<?php echo $course['id']; ?>'">
                                <div class="card-body">
                                    <h6 class="card-title"><?php echo htmlspecialchars($course['title']); ?></h6>
                                    <p class="text-muted mb-1">
                                        <i class="fas fa-user-tie me-1"></i><?php echo htmlspecialchars($course['lecturer_name']); ?>
                                    </p>
                                    <p class="text-muted mb-0">
                                        <i class="fas fa-users me-1"></i><?php echo $course['student_count']; ?> students
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($course_details): ?>
                <!-- Course Overview -->
                <div class="stats-card mb-4">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h4><?php echo htmlspecialchars($course_details['title']); ?></h4>
                            <p class="mb-0">
                                <i class="fas fa-user-tie me-2"></i>Instructor: <?php echo htmlspecialchars($course_details['lecturer_name']); ?>
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <h3><?php echo count($course_grades); ?></h3>
                            <p class="mb-0">Enrolled Students</p>
                        </div>
                    </div>
                </div>

                <?php if (!empty($course_grades)): ?>
                    <!-- Student Grades Table -->
                    <div class="grade-table">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-graduation-cap me-2"></i>Student Grades
                                <small class="text-muted ms-2">(Click on grades to edit)</small>
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Student</th>
                                            <th>Email</th>
                                            <th>Average Grade</th>
                                            <th>Letter Grade</th>
                                            <th>Assessment Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($course_grades as $student_id => $student_data): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($student_data['student_name']); ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($student_data['email']); ?></td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo $student_data['average']; ?>%</span>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $grade_letter = getGradeLetter($student_data['average']);
                                                    $grade_class = 'grade-' . substr($grade_letter, 0, 1);
                                                    ?>
                                                    <span class="<?php echo $grade_class; ?>"><?php echo $grade_letter; ?></span>
                                                </td>
                                                <td>
                                                    <?php if (!empty($student_data['grades'])): ?>
                                                        <button class="btn btn-sm btn-outline-primary" type="button" 
                                                                data-bs-toggle="collapse" 
                                                                data-bs-target="#grades-<?php echo $student_id; ?>">
                                                            <i class="fas fa-eye me-1"></i>View Details (<?php echo count($student_data['grades']); ?>)
                                                        </button>

                                                        <div class="collapse mt-2" id="grades-<?php echo $student_id; ?>">
                                                            <div class="table-responsive">
                                                                <table class="table table-sm table-bordered">
                                                                    <thead>
                                                                        <tr>
                                                                            <th>Assessment</th>
                                                                            <th>Type</th>
                                                                            <th>Score</th>
                                                                            <th>Percentage</th>
                                                                            <th>Date</th>
                                                                            <th>Action</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php foreach ($student_data['grades'] as $grade): ?>
                                                                            <tr>
                                                                                <td><?php echo htmlspecialchars($grade['material_title']); ?></td>
                                                                                <td>
                                                                                    <span class="badge bg-secondary"><?php echo ucfirst($grade['material_type']); ?></span>
                                                                                </td>
                                                                                <td><?php echo $grade['score']; ?>/<?php echo $grade['total_points']; ?></td>
                                                                                <td>
                                                                                    <span class="editable-grade" 
                                                                                          onclick="editGrade(<?php echo $grade['result_id']; ?>, <?php echo $grade['percentage']; ?>, '<?php echo htmlspecialchars($grade['feedback'] ?? ''); ?>')">
                                                                                        <?php echo $grade['percentage']; ?>%
                                                                                        <i class="fas fa-edit ms-1"></i>
                                                                                    </span>
                                                                                </td>
                                                                                <td><?php echo date('M d, Y', strtotime($grade['completed_at'])); ?></td>
                                                                                <td>
                                                                                    <?php if ($grade['feedback']): ?>
                                                                                        <i class="fas fa-comment text-info" title="Has feedback"></i>
                                                                                    <?php else: ?>
                                                                                        <i class="fas fa-comment-slash text-muted" title="No feedback"></i>
                                                                                    <?php endif; ?>
                                                                                </td>
                                                                            </tr>
                                                                        <?php endforeach; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">No assessments completed</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">No Students Enrolled</h4>
                        <p class="text-muted">This course doesn't have any enrolled students yet.</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Edit Grade Modal -->
    <div class="modal fade" id="editGradeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Grade</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="result_id" id="editResultId">

                        <div class="mb-3">
                            <label for="editPercentage" class="form-label">Percentage (0-100)</label>
                            <input type="number" class="form-control" id="editPercentage" name="new_percentage" 
                                   min="0" max="100" step="0.01" required>
                        </div>

                        <div class="mb-3">
                            <label for="editFeedback" class="form-label">Feedback</label>
                            <textarea class="form-control" id="editFeedback" name="feedback" rows="3" 
                                      placeholder="Optional feedback for student..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_grade" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editGrade(resultId, currentPercentage, currentFeedback) {
            document.getElementById('editResultId').value = resultId;
            document.getElementById('editPercentage').value = currentPercentage;
            document.getElementById('editFeedback').value = currentFeedback;

            new bootstrap.Modal(document.getElementById('editGradeModal')).show();
        }
    </script>
</body>
</html>