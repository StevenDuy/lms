<?php
require_once '../config/config.php';
requireRole('student');

$user = getCurrentUser();

// Get all grades for enrolled courses
$stmt = $pdo->prepare("
    SELECT 
        c.id as course_id,
        c.title as course_title,
        u.full_name as lecturer_name,
        tr.id as result_id,
        tr.score,
        tr.total_points,
        tr.percentage,
        tr.completed_at,
        tr.feedback,
        m.title as material_title,
        m.material_type
    FROM test_results tr
    JOIN materials m ON tr.material_id = m.id
    JOIN courses c ON m.course_id = c.id
    JOIN users u ON c.lecturer_id = u.id
    JOIN enrollments e ON c.id = e.course_id
    WHERE tr.student_id = ? AND e.student_id = ?
    ORDER BY c.title ASC, tr.completed_at DESC
");
$stmt->execute([$user['id'], $user['id']]);
$all_grades = $stmt->fetchAll();

// Group grades by course
$courses_grades = [];
foreach ($all_grades as $grade) {
    $course_id = $grade['course_id'];
    if (!isset($courses_grades[$course_id])) {
        $courses_grades[$course_id] = [
            'course_title' => $grade['course_title'],
            'lecturer_name' => $grade['lecturer_name'],
            'grades' => [],
            'average' => 0,
            'total_points' => 0,
            'earned_points' => 0
        ];
    }

    $courses_grades[$course_id]['grades'][] = $grade;
    $courses_grades[$course_id]['total_points'] += $grade['total_points'];
    $courses_grades[$course_id]['earned_points'] += $grade['score'];
}

// Calculate averages
foreach ($courses_grades as &$course_data) {
    if ($course_data['total_points'] > 0) {
        $course_data['average'] = round(($course_data['earned_points'] / $course_data['total_points']) * 100, 2);
    }
}

// Calculate overall statistics
$total_earned = array_sum(array_column($courses_grades, 'earned_points'));
$total_possible = array_sum(array_column($courses_grades, 'total_points'));
$overall_average = $total_possible > 0 ? round(($total_earned / $total_possible) * 100, 2) : 0;

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
    <title>Grades - Student Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .grade-card {
            transition: transform 0.3s, box-shadow 0.3s;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .grade-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .grade-badge {
            font-size: 1.2rem;
            padding: 0.5rem 1rem;
        }
        .grade-A { background: linear-gradient(135deg, #28a745, #20c997); }
        .grade-B { background: linear-gradient(135deg, #007bff, #6f42c1); }
        .grade-C { background: linear-gradient(135deg, #ffc107, #fd7e14); }
        .grade-D { background: linear-gradient(135deg, #fd7e14, #dc3545); }
        .grade-F { background: linear-gradient(135deg, #dc3545, #6f42c1); }
        .overall-stats {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
        }
        .material-assignment { color: #ffc107; }
        .material-exam { color: #dc3545; }
        .material-lesson { color: #28a745; }
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
            <h2><i class="fas fa-chart-bar me-2 text-primary"></i>My Grades</h2>
            <span class="badge bg-info"><?php echo count($courses_grades); ?> courses</span>
        </div>

        <?php if (empty($courses_grades)): ?>
            <div class="text-center py-5">
                <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                <h4 class="text-muted">No Grades Available</h4>
                <p class="text-muted">Complete assignments and exams to see your grades here.</p>
                <a href="my_courses.php" class="btn btn-primary">
                    <i class="fas fa-book me-2"></i>Go to My Courses
                </a>
            </div>
        <?php else: ?>
            <!-- Overall Statistics -->
            <div class="overall-stats mb-4">
                <div class="row text-center">
                    <div class="col-md-3">
                        <i class="fas fa-trophy fa-2x mb-2"></i>
                        <h3><?php echo $overall_average; ?>%</h3>
                        <p class="mb-0">Overall Average</p>
                    </div>
                    <div class="col-md-3">
                        <i class="fas fa-graduation-cap fa-2x mb-2"></i>
                        <h3><?php echo getGradeLetter($overall_average); ?></h3>
                        <p class="mb-0">Overall Grade</p>
                    </div>
                    <div class="col-md-3">
                        <i class="fas fa-book fa-2x mb-2"></i>
                        <h3><?php echo count($courses_grades); ?></h3>
                        <p class="mb-0">Courses</p>
                    </div>
                    <div class="col-md-3">
                        <i class="fas fa-tasks fa-2x mb-2"></i>
                        <h3><?php echo count($all_grades); ?></h3>
                        <p class="mb-0">Assessments</p>
                    </div>
                </div>
            </div>

            <!-- Courses Grades -->
            <div class="row g-4">
                <?php foreach ($courses_grades as $course_id => $course_data): ?>
                    <div class="col-12">
                        <div class="card grade-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="mb-1"><?php echo htmlspecialchars($course_data['course_title']); ?></h5>
                                    <small class="text-muted">Instructor: <?php echo htmlspecialchars($course_data['lecturer_name']); ?></small>
                                </div>
                                <div class="text-end">
                                    <?php 
                                    $grade_letter = getGradeLetter($course_data['average']);
                                    $grade_class = 'grade-' . substr($grade_letter, 0, 1);
                                    ?>
                                    <span class="badge grade-badge text-white <?php echo $grade_class; ?>">
                                        <?php echo $grade_letter; ?> (<?php echo $course_data['average']; ?>%)
                                    </span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="table-responsive">
                                            <table class="table table-sm">
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
                                                    <?php foreach ($course_data['grades'] as $grade): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($grade['material_title']); ?></td>
                                                            <td>
                                                                <span class="material-<?php echo $grade['material_type']; ?>">
                                                                    <?php if ($grade['material_type'] === 'assignment'): ?>
                                                                        <i class="fas fa-tasks me-1"></i>Assignment
                                                                    <?php elseif ($grade['material_type'] === 'exam'): ?>
                                                                        <i class="fas fa-clipboard-check me-1"></i>Exam
                                                                    <?php else: ?>
                                                                        <i class="fas fa-book me-1"></i>Lesson
                                                                    <?php endif; ?>
                                                                </span>
                                                            </td>
                                                            <td><?php echo $grade['score']; ?>/<?php echo $grade['total_points']; ?></td>
                                                            <td>
                                                                <span class="badge bg-<?php echo $grade['percentage'] >= 60 ? 'success' : 'danger'; ?>">
                                                                    <?php echo $grade['percentage']; ?>%
                                                                </span>
                                                            </td>
                                                            <td><?php echo formatDate($grade['completed_at']); ?></td>
                                                            <td>
                                                                <a href="test_result.php?id=<?php echo $grade['result_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                                    View Details
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="bg-light p-3 rounded">
                                            <h6 class="mb-3">Course Summary</h6>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Total Points:</span>
                                                <strong><?php echo $course_data['earned_points']; ?>/<?php echo $course_data['total_points']; ?></strong>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Average:</span>
                                                <strong><?php echo $course_data['average']; ?>%</strong>
                                            </div>
                                            <div class="d-flex justify-content-between mb-3">
                                                <span>Grade:</span>
                                                <strong><?php echo getGradeLetter($course_data['average']); ?></strong>
                                            </div>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-<?php echo $course_data['average'] >= 60 ? 'success' : 'danger'; ?>" 
                                                     style="width: <?php echo $course_data['average']; ?>%"></div>
                                            </div>
                                        </div>

                                        <?php if (!empty(array_filter($course_data['grades'], function($g) { return !empty($g['feedback']); }))): ?>
                                            <div class="mt-3">
                                                <h6>Instructor Feedback</h6>
                                                <?php foreach ($course_data['grades'] as $grade): ?>
                                                    <?php if (!empty($grade['feedback'])): ?>
                                                        <div class="alert alert-info alert-sm">
                                                            <strong><?php echo htmlspecialchars($grade['material_title']); ?>:</strong><br>
                                                            <?php echo nl2br(htmlspecialchars($grade['feedback'])); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>