<?php
require_once '../config/config.php';
requireRole('student');

$user = getCurrentUser();
$result_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get test result details
$stmt = $pdo->prepare("
    SELECT tr.*, m.title as material_title, m.material_type, c.title as course_title, c.id as course_id
    FROM test_results tr
    JOIN materials m ON tr.material_id = m.id
    JOIN courses c ON m.course_id = c.id
    WHERE tr.id = ? AND tr.student_id = ?
");
$stmt->execute([$result_id, $user['id']]);
$result = $stmt->fetch();

if (!$result) {
    redirect('my_courses.php');
}

// Get detailed answers
$stmt = $pdo->prepare("
    SELECT sa.*, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_answer, q.points
    FROM student_answers sa
    JOIN questions q ON sa.question_id = q.id
    WHERE sa.student_id = ? AND q.material_id = ?
    AND sa.attempt_date >= (
        SELECT completed_at FROM test_results WHERE id = ?
    ) - INTERVAL 1 MINUTE
    ORDER BY q.id ASC
");
$stmt->execute([$user['id'], $result['material_id'], $result_id]);
$answers = $stmt->fetchAll();

// Calculate grade
$grade = '';
if ($result['percentage'] >= 90) $grade = 'A+';
elseif ($result['percentage'] >= 85) $grade = 'A';
elseif ($result['percentage'] >= 80) $grade = 'B+';
elseif ($result['percentage'] >= 75) $grade = 'B';
elseif ($result['percentage'] >= 70) $grade = 'C+';
elseif ($result['percentage'] >= 65) $grade = 'C';
elseif ($result['percentage'] >= 60) $grade = 'D';
else $grade = 'F';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($result['material_title']); ?> - Results</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .result-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 3rem 0;
        }
        .result-header.failed {
            background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
        }
        .score-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
            margin: 0 auto;
        }
        .answer-card {
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        .correct-answer {
            border-left: 4px solid #28a745;
            background-color: #d4edda;
        }
        .incorrect-answer {
            border-left: 4px solid #dc3545;
            background-color: #f8d7da;
        }
        .option {
            padding: 0.5rem;
            margin: 0.25rem 0;
            border-radius: 4px;
        }
        .option.correct {
            background-color: #d4edda;
            color: #155724;
        }
        .option.incorrect {
            background-color: #f8d7da;
            color: #721c24;
        }
        .option.selected {
            font-weight: bold;
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
            <div class="navbar-nav me-auto">
                <a class="nav-link" href="course_learning.php?id=<?php echo $result['course_id']; ?>">
                    <i class="fas fa-arrow-left me-1"></i>Back to Course
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

    <!-- Result Header -->
    <section class="result-header <?php echo $result['percentage'] < 60 ? 'failed' : ''; ?>">
        <div class="container text-center">
            <div class="score-circle">
                <?php echo $result['percentage']; ?>%
            </div>
            <h1 class="display-5 fw-bold mt-3 mb-2">
                <?php echo $result['percentage'] >= 60 ? 'Congratulations!' : 'Keep Trying!'; ?>
            </h1>
            <h3><?php echo htmlspecialchars($result['material_title']); ?></h3>
            <p class="lead">Course: <?php echo htmlspecialchars($result['course_title']); ?></p>
        </div>
    </section>

    <!-- Result Details -->
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8">
                <!-- Score Breakdown -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Score Breakdown</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <h4 class="text-primary"><?php echo $result['score']; ?>/<?php echo $result['total_points']; ?></h4>
                                <small class="text-muted">Points Earned</small>
                            </div>
                            <div class="col-md-3">
                                <h4 class="text-info"><?php echo $result['percentage']; ?>%</h4>
                                <small class="text-muted">Percentage</small>
                            </div>
                            <div class="col-md-3">
                                <h4 class="text-success"><?php echo $grade; ?></h4>
                                <small class="text-muted">Grade</small>
                            </div>
                            <div class="col-md-3">
                                <h4 class="text-warning"><?php echo count($answers); ?></h4>
                                <small class="text-muted">Questions</small>
                            </div>
                        </div>

                        <div class="mt-3">
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar <?php echo $result['percentage'] >= 60 ? 'bg-success' : 'bg-danger'; ?>" 
                                     style="width: <?php echo $result['percentage']; ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="card mb-4">
                    <div class="card-body text-center">
                        <h5>What's Next?</h5>
                        <div class="mt-3">
                            <a href="course_learning.php?id=<?php echo $result['course_id']; ?>" class="btn btn-primary me-2">
                                <i class="fas fa-arrow-left me-2"></i>Back to Course
                            </a>

                            <?php if ($result['material_type'] === 'assignment'): ?>
                                <a href="take_test.php?id=<?php echo $result['material_id']; ?>" class="btn btn-warning">
                                    <i class="fas fa-redo me-2"></i>Retake Assignment
                                </a>
                            <?php endif; ?>

                            <a href="grades.php" class="btn btn-outline-primary">
                                <i class="fas fa-chart-bar me-2"></i>View All Grades
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Detailed Answers -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list-alt me-2"></i>Detailed Review</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($answers)): ?>
                            <p class="text-muted">No detailed answers available.</p>
                        <?php else: ?>
                            <?php foreach ($answers as $index => $answer): ?>
                                <div class="answer-card card <?php echo $answer['is_correct'] ? 'correct-answer' : 'incorrect-answer'; ?>">
                                    <div class="card-body">
                                        <div class="d-flex align-items-start mb-3">
                                            <div class="me-3">
                                                <?php if ($answer['is_correct']): ?>
                                                    <i class="fas fa-check-circle fa-2x text-success"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-times-circle fa-2x text-danger"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-2">Question <?php echo $index + 1; ?> (<?php echo $answer['points']; ?> points)</h6>
                                                <p class="mb-3"><?php echo htmlspecialchars($answer['question_text']); ?></p>

                                                <div class="options">
                                                    <div class="option <?php echo $answer['correct_answer'] === 'A' ? 'correct' : ($answer['selected_answer'] === 'A' ? 'incorrect' : ''); ?> <?php echo $answer['selected_answer'] === 'A' ? 'selected' : ''; ?>">
                                                        <strong>A)</strong> <?php echo htmlspecialchars($answer['option_a']); ?>
                                                        <?php if ($answer['correct_answer'] === 'A'): ?>
                                                            <i class="fas fa-check text-success ms-2"></i>
                                                        <?php elseif ($answer['selected_answer'] === 'A'): ?>
                                                            <i class="fas fa-times text-danger ms-2"></i>
                                                        <?php endif; ?>
                                                    </div>

                                                    <div class="option <?php echo $answer['correct_answer'] === 'B' ? 'correct' : ($answer['selected_answer'] === 'B' ? 'incorrect' : ''); ?> <?php echo $answer['selected_answer'] === 'B' ? 'selected' : ''; ?>">
                                                        <strong>B)</strong> <?php echo htmlspecialchars($answer['option_b']); ?>
                                                        <?php if ($answer['correct_answer'] === 'B'): ?>
                                                            <i class="fas fa-check text-success ms-2"></i>
                                                        <?php elseif ($answer['selected_answer'] === 'B'): ?>
                                                            <i class="fas fa-times text-danger ms-2"></i>
                                                        <?php endif; ?>
                                                    </div>

                                                    <div class="option <?php echo $answer['correct_answer'] === 'C' ? 'correct' : ($answer['selected_answer'] === 'C' ? 'incorrect' : ''); ?> <?php echo $answer['selected_answer'] === 'C' ? 'selected' : ''; ?>">
                                                        <strong>C)</strong> <?php echo htmlspecialchars($answer['option_c']); ?>
                                                        <?php if ($answer['correct_answer'] === 'C'): ?>
                                                            <i class="fas fa-check text-success ms-2"></i>
                                                        <?php elseif ($answer['selected_answer'] === 'C'): ?>
                                                            <i class="fas fa-times text-danger ms-2"></i>
                                                        <?php endif; ?>
                                                    </div>

                                                    <div class="option <?php echo $answer['correct_answer'] === 'D' ? 'correct' : ($answer['selected_answer'] === 'D' ? 'incorrect' : ''); ?> <?php echo $answer['selected_answer'] === 'D' ? 'selected' : ''; ?>">
                                                        <strong>D)</strong> <?php echo htmlspecialchars($answer['option_d']); ?>
                                                        <?php if ($answer['correct_answer'] === 'D'): ?>
                                                            <i class="fas fa-check text-success ms-2"></i>
                                                        <?php elseif ($answer['selected_answer'] === 'D'): ?>
                                                            <i class="fas fa-times text-danger ms-2"></i>
                                                        <?php endif; ?>
                                                    </div>
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

            <!-- Sidebar -->
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Test Information</h6>
                    </div>
                    <div class="card-body">
                        <p><strong>Type:</strong> <?php echo ucfirst($result['material_type']); ?></p>
                        <p><strong>Completed:</strong> <?php echo formatDate($result['completed_at']); ?></p>
                        <p><strong>Attempt:</strong> #<?php echo $result['attempt_count']; ?></p>
                        <p><strong>Status:</strong> 
                            <span class="badge <?php echo $result['percentage'] >= 60 ? 'bg-success' : 'bg-danger'; ?>">
                                <?php echo $result['percentage'] >= 60 ? 'Passed' : 'Failed'; ?>
                            </span>
                        </p>
                    </div>
                </div>

                <?php if ($result['feedback']): ?>
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-comment me-2"></i>Instructor Feedback</h6>
                    </div>
                    <div class="card-body">
                        <p><?php echo nl2br(htmlspecialchars($result['feedback'])); ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>