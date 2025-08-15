<?php
require_once '../config/config.php';
requireRole('lecturer');

$user = getCurrentUser();

// Get lecturer's courses with student count
$stmt = $pdo->prepare("
    SELECT c.id, c.title,
           (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as student_count
    FROM courses c 
    WHERE c.lecturer_id = ?
    ORDER BY c.title ASC
");
$stmt->execute([$user['id']]);
$courses = $stmt->fetchAll();

// Handle course selection
$selected_course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$course_details = null;
$course_grades = [];

if ($selected_course_id) {
    // Verify course ownership
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND lecturer_id = ?");
    $stmt->execute([$selected_course_id, $user['id']]);
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

// Handle feedback update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_feedback'])) {
    $result_id = (int)$_POST['result_id'];
    $feedback = trim($_POST['feedback']);

    $stmt = $pdo->prepare("UPDATE test_results SET feedback = ? WHERE id = ?");
    if ($stmt->execute([$feedback, $result_id])) {
        $message = 'Feedback updated successfully!';
        // Refresh the page to show updated data
        header("Location: grades.php?course_id=" . $selected_course_id);
        exit;
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
    <title>Grades - Lecturer Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
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
            border: 2px solid #28a745;
            background-color: #f8fff8;
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
        .feedback-form {
            display: none;
        }
        .feedback-form.show {
            display: block;
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
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-book me-1"></i>My Courses
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
            <h2><i class="fas fa-chart-bar me-2 text-success"></i>Student Grades</h2>
            <span class="badge bg-info"><?php echo count($courses); ?> courses</span>
        </div>

        <?php if (empty($courses)): ?>
            <div class="text-center py-5">
                <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                <h4 class="text-muted">No Courses Available</h4>
                <p class="text-muted">Create courses to view student grades and performance.</p>
                <a href="create_course.php" class="btn btn-success">
                    <i class="fas fa-plus me-2"></i>Create Your First Course
                </a>
            </div>
        <?php else: ?>
            <!-- Course Selection -->
            <div class="mb-4">
                <h5>Select a Course to View Grades:</h5>
                <div class="row g-3">
                    <?php foreach ($courses as $course): ?>
                        <div class="col-md-4">
                            <div class="card course-card <?php echo $course['id'] == $selected_course_id ? 'selected' : ''; ?>" 
                                 onclick="window.location.href='grades.php?course_id=<?php echo $course['id']; ?>'">
                                <div class="card-body text-center">
                                    <h6 class="card-title"><?php echo htmlspecialchars($course['title']); ?></h6>
                                    <p class="text-muted mb-0">
                                        <i class="fas fa-users me-1"></i><?php echo $course['student_count']; ?> students
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($course_details && !empty($course_grades)): ?>
                <!-- Course Grades -->
                <div class="card grade-table">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-graduation-cap me-2"></i><?php echo htmlspecialchars($course_details['title']); ?> - Student Grades
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-success">
                                    <tr>
                                        <th>Student</th>
                                        <th>Email</th>
                                        <th>Average Grade</th>
                                        <th>Letter Grade</th>
                                        <th>Assessments</th>
                                        <th>Actions</th>
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
                                                <span class="text-muted"><?php echo count($student_data['grades']); ?> completed</span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-success" onclick="toggleDetails(<?php echo $student_id; ?>)">
                                                    <i class="fas fa-eye"></i> View Details
                                                </button>
                                            </td>
                                        </tr>
                                        <!-- Detailed Grades Row -->
                                        <tr id="details-<?php echo $student_id; ?>" style="display: none;">
                                            <td colspan="6" class="p-0">
                                                <div class="bg-light p-3">
                                                    <h6>Assessment Details:</h6>
                                                    <?php if (empty($student_data['grades'])): ?>
                                                        <p class="text-muted">No assessments completed yet.</p>
                                                    <?php else: ?>
                                                        <div class="table-responsive">
                                                            <table class="table table-sm">
                                                                <thead>
                                                                    <tr>
                                                                        <th>Assessment</th>
                                                                        <th>Type</th>
                                                                        <th>Score</th>
                                                                        <th>Percentage</th>
                                                                        <th>Completed</th>
                                                                        <th>Feedback</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php foreach ($student_data['grades'] as $grade): ?>
                                                                        <tr>
                                                                            <td><?php echo htmlspecialchars($grade['material_title']); ?></td>
                                                                            <td>
                                                                                <span class="badge <?php echo $grade['material_type'] === 'exam' ? 'bg-danger' : 'bg-warning'; ?>">
                                                                                    <?php echo ucfirst($grade['material_type']); ?>
                                                                                </span>
                                                                            </td>
                                                                            <td><?php echo $grade['score']; ?>/<?php echo $grade['total_points']; ?></td>
                                                                            <td><?php echo $grade['percentage']; ?>%</td>
                                                                            <td><?php echo formatDate($grade['completed_at']); ?></td>
                                                                            <td>
                                                                                <div class="feedback-display-<?php echo $grade['result_id']; ?>">
                                                                                    <?php if ($grade['feedback']): ?>
                                                                                        <span class="text-success"><?php echo htmlspecialchars($grade['feedback']); ?></span>
                                                                                    <?php else: ?>
                                                                                        <span class="text-muted">No feedback</span>
                                                                                    <?php endif; ?>
                                                                                    <button class="btn btn-sm btn-outline-primary ms-2" onclick="showFeedbackForm(<?php echo $grade['result_id']; ?>)">
                                                                                        <i class="fas fa-edit"></i>
                                                                                    </button>
                                                                                </div>

                                                                                <form method="POST" class="feedback-form feedback-form-<?php echo $grade['result_id']; ?> mt-2">
                                                                                    <input type="hidden" name="result_id" value="<?php echo $grade['result_id']; ?>">
                                                                                    <div class="input-group input-group-sm">
                                                                                        <input type="text" class="form-control" name="feedback" 
                                                                                               placeholder="Enter feedback..." value="<?php echo htmlspecialchars($grade['feedback']); ?>">
                                                                                        <button type="submit" name="update_feedback" class="btn btn-success">
                                                                                            <i class="fas fa-save"></i>
                                                                                        </button>
                                                                                        <button type="button" class="btn btn-secondary" onclick="hideFeedbackForm(<?php echo $grade['result_id']; ?>)">
                                                                                            <i class="fas fa-times"></i>
                                                                                        </button>
                                                                                    </div>
                                                                                </form>
                                                                            </td>
                                                                        </tr>
                                                                    <?php endforeach; ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php elseif ($course_details): ?>
                <div class="text-center py-5">
                    <i class="fas fa-user-graduate fa-3x text-muted mb-3"></i>
                    <h4 class="text-muted">No Students Enrolled</h4>
                    <p class="text-muted">This course doesn't have any enrolled students yet.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleDetails(studentId) {
            const detailsRow = document.getElementById('details-' + studentId);
            if (detailsRow.style.display === 'none') {
                detailsRow.style.display = 'table-row';
            } else {
                detailsRow.style.display = 'none';
            }
        }

        function showFeedbackForm(resultId) {
            document.querySelector('.feedback-display-' + resultId).style.display = 'none';
            document.querySelector('.feedback-form-' + resultId).classList.add('show');
        }

        function hideFeedbackForm(resultId) {
            document.querySelector('.feedback-display-' + resultId).style.display = 'block';
            document.querySelector('.feedback-form-' + resultId).classList.remove('show');
        }
    </script>
</body>
</html>