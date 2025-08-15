<?php
require_once '../config/config.php';
requireRole('student');

$user = getCurrentUser();
$material_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get material details and check enrollment
$stmt = $pdo->prepare("
    SELECT m.*, c.title as course_title, c.id as course_id
    FROM materials m 
    JOIN courses c ON m.course_id = c.id
    JOIN enrollments e ON c.id = e.course_id
    WHERE m.id = ? AND e.student_id = ? AND m.material_type IN ('assignment', 'exam')
");
$stmt->execute([$material_id, $user['id']]);
$material = $stmt->fetch();

if (!$material) {
    redirect('my_courses.php');
}

// Check if already completed (for exams, only one attempt allowed)
$stmt = $pdo->prepare("SELECT * FROM test_results WHERE student_id = ? AND material_id = ?");
$stmt->execute([$user['id'], $material_id]);
$existing_result = $stmt->fetch();

if ($existing_result && $material['material_type'] === 'exam') {
    redirect('test_result.php?id=' . $existing_result['id']);
}

// Get questions
$stmt = $pdo->prepare("SELECT * FROM questions WHERE material_id = ? ORDER BY id ASC");
$stmt->execute([$material_id]);
$questions = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_test'])) {
    $total_questions = count($questions);
    $correct_answers = 0;
    $total_points = 0;
    $earned_points = 0;

    // Calculate total possible points
    foreach ($questions as $question) {
        $total_points += $question['points'];
    }

    // Process answers
    foreach ($questions as $question) {
        $answer = isset($_POST['question_' . $question['id']]) ? $_POST['question_' . $question['id']] : '';
        $is_correct = ($answer === $question['correct_answer']);

        if ($is_correct) {
            $correct_answers++;
            $earned_points += $question['points'];
        }

        // Save student answer
        $stmt = $pdo->prepare("INSERT INTO student_answers (student_id, question_id, selected_answer, is_correct) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user['id'], $question['id'], $answer, $is_correct]);
    }

    // Calculate percentage
    $percentage = $total_points > 0 ? round(($earned_points / $total_points) * 100, 2) : 0;

    // Update attempt count if retaking assignment
    $attempt_count = 1;
    if ($existing_result && $material['material_type'] === 'assignment') {
        $attempt_count = $existing_result['attempt_count'] + 1;
        // Delete previous result for assignment retake
        $stmt = $pdo->prepare("DELETE FROM test_results WHERE id = ?");
        $stmt->execute([$existing_result['id']]);
    }

    // Save test result
    $stmt = $pdo->prepare("INSERT INTO test_results (student_id, material_id, score, total_points, percentage, attempt_count) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user['id'], $material_id, $earned_points, $total_points, $percentage, $attempt_count]);
    $result_id = $pdo->lastInsertId();

    redirect('test_result.php?id=' . $result_id);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($material['title']); ?> - <?php echo ucfirst($material['material_type']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .test-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
        }
        .question-card {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .option-label {
            cursor: pointer;
            padding: 1rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            transition: all 0.3s;
        }
        .option-label:hover {
            background-color: #f8f9fa;
            border-color: #667eea;
        }
        .option-label.selected {
            background-color: #e7f1ff;
            border-color: #667eea;
        }
        .question-number {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
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
                <a class="nav-link" href="course_learning.php?id=<?php echo $material['course_id']; ?>">
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

    <!-- Test Header -->
    <section class="test-header">
        <div class="container">
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="my_courses.php" class="text-white-50">My Courses</a></li>
                    <li class="breadcrumb-item"><a href="course_learning.php?id=<?php echo $material['course_id']; ?>" class="text-white-50"><?php echo htmlspecialchars($material['course_title']); ?></a></li>
                    <li class="breadcrumb-item active text-white"><?php echo htmlspecialchars($material['title']); ?></li>
                </ol>
            </nav>

            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="display-6 fw-bold mb-2">
                        <?php if ($material['material_type'] === 'assignment'): ?>
                            <i class="fas fa-tasks me-3"></i>
                        <?php else: ?>
                            <i class="fas fa-clipboard-check me-3"></i>
                        <?php endif; ?>
                        <?php echo htmlspecialchars($material['title']); ?>
                    </h1>
                    <p class="lead mb-0">Course: <?php echo htmlspecialchars($material['course_title']); ?></p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="bg-white bg-opacity-25 p-3 rounded">
                        <h5 class="mb-1"><?php echo count($questions); ?> Questions</h5>
                        <small>
                            <?php if ($material['material_type'] === 'assignment'): ?>
                                <i class="fas fa-redo me-1"></i>Retakes allowed
                            <?php else: ?>
                                <i class="fas fa-exclamation-triangle me-1"></i>One attempt only
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Test Instructions -->
    <div class="container mt-4">
        <?php if ($existing_result && $material['material_type'] === 'assignment'): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Retaking Assignment:</strong> This is attempt #<?php echo $existing_result['attempt_count'] + 1; ?>. 
                Your previous score was <?php echo $existing_result['percentage']; ?>%.
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-body">
                <h5><i class="fas fa-info-circle me-2"></i>Instructions</h5>
                <ul class="mb-0">
                    <li>Read each question carefully before selecting your answer</li>
                    <li>Select one option (A, B, C, or D) for each question</li>
                    <li>You can change your answers before submitting</li>
                    <?php if ($material['material_type'] === 'assignment'): ?>
                        <li><strong>Note:</strong> You can retake this assignment multiple times</li>
                    <?php else: ?>
                        <li><strong>Important:</strong> You have only ONE attempt for this exam</li>
                    <?php endif; ?>
                    <li>Click "Submit <?php echo ucfirst($material['material_type']); ?>" when you're finished</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Questions Form -->
    <div class="container mb-5">
        <?php if (empty($questions)): ?>
            <div class="text-center py-5">
                <i class="fas fa-question-circle fa-3x text-muted mb-3"></i>
                <h4 class="text-muted">No Questions Available</h4>
                <p class="text-muted">This <?php echo $material['material_type']; ?> doesn't have any questions yet.</p>
                <a href="course_learning.php?id=<?php echo $material['course_id']; ?>" class="btn btn-primary">
                    Back to Course
                </a>
            </div>
        <?php else: ?>
            <form method="POST" id="testForm">
                <?php foreach ($questions as $index => $question): ?>
                    <div class="question-card card">
                        <div class="card-body">
                            <div class="d-flex align-items-start mb-3">
                                <div class="question-number me-3">
                                    <?php echo $index + 1; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="mb-2"><?php echo htmlspecialchars($question['question_text']); ?></h5>
                                    <small class="text-muted"><?php echo $question['points']; ?> point(s)</small>
                                </div>
                            </div>

                            <div class="options ms-5">
                                <label class="option-label d-block" onclick="selectOption(this, '<?php echo $question['id']; ?>', 'A')">
                                    <input type="radio" name="question_<?php echo $question['id']; ?>" value="A" class="d-none" required>
                                    <strong>A)</strong> <?php echo htmlspecialchars($question['option_a']); ?>
                                </label>
                                <label class="option-label d-block" onclick="selectOption(this, '<?php echo $question['id']; ?>', 'B')">
                                    <input type="radio" name="question_<?php echo $question['id']; ?>" value="B" class="d-none" required>
                                    <strong>B)</strong> <?php echo htmlspecialchars($question['option_b']); ?>
                                </label>
                                <label class="option-label d-block" onclick="selectOption(this, '<?php echo $question['id']; ?>', 'C')">
                                    <input type="radio" name="question_<?php echo $question['id']; ?>" value="C" class="d-none" required>
                                    <strong>C)</strong> <?php echo htmlspecialchars($question['option_c']); ?>
                                </label>
                                <label class="option-label d-block" onclick="selectOption(this, '<?php echo $question['id']; ?>', 'D')">
                                    <input type="radio" name="question_<?php echo $question['id']; ?>" value="D" class="d-none" required>
                                    <strong>D)</strong> <?php echo htmlspecialchars($question['option_d']); ?>
                                </label>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="text-center mt-4">
                    <div class="card">
                        <div class="card-body">
                            <h5>Ready to Submit?</h5>
                            <p class="text-muted">Make sure you have answered all questions before submitting.</p>
                            <div class="d-flex justify-content-center gap-3">
                                <a href="course_learning.php?id=<?php echo $material['course_id']; ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                                <button type="submit" name="submit_test" class="btn btn-primary btn-lg" onclick="return confirmSubmit()">
                                    <i class="fas fa-paper-plane me-2"></i>Submit <?php echo ucfirst($material['material_type']); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectOption(element, questionId, option) {
            // Remove selected class from all options for this question
            const questionCard = element.closest('.question-card');
            questionCard.querySelectorAll('.option-label').forEach(label => {
                label.classList.remove('selected');
            });

            // Add selected class to clicked option
            element.classList.add('selected');

            // Set the radio button value
            const radio = element.querySelector('input[type="radio"]');
            radio.checked = true;
        }

        function confirmSubmit() {
            const form = document.getElementById('testForm');
            const questions = form.querySelectorAll('.question-card');
            let answeredCount = 0;

            questions.forEach(question => {
                const radioButtons = question.querySelectorAll('input[type="radio"]');
                const isAnswered = Array.from(radioButtons).some(radio => radio.checked);
                if (isAnswered) answeredCount++;
            });

            if (answeredCount < questions.length) {
                alert('Please answer all questions before submitting.');
                return false;
            }

            const materialType = '<?php echo $material['material_type']; ?>';
            const message = materialType === 'exam' 
                ? 'Are you sure you want to submit this exam? You cannot retake it after submission.'
                : 'Are you sure you want to submit this assignment?';

            return confirm(message);
        }
    </script>
</body>
</html>