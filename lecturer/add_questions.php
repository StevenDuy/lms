<?php
require_once '../config/config.php';
requireRole('lecturer');

$user = getCurrentUser();
$material_id = isset($_GET['material_id']) ? (int)$_GET['material_id'] : 0;

// Get material details and verify ownership
$stmt = $pdo->prepare("
    SELECT m.*, c.title as course_title, c.id as course_id
    FROM materials m 
    JOIN courses c ON m.course_id = c.id
    WHERE m.id = ? AND c.lecturer_id = ? AND m.material_type IN ('assignment', 'exam')
");
$stmt->execute([$material_id, $user['id']]);
$material = $stmt->fetch();

if (!$material) {
    redirect('dashboard.php');
}

// Get existing questions
$stmt = $pdo->prepare("SELECT * FROM questions WHERE material_id = ? ORDER BY id ASC");
$stmt->execute([$material_id]);
$questions = $stmt->fetchAll();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_question'])) {
        $question_text = trim($_POST['question_text']);
        $option_a = trim($_POST['option_a']);
        $option_b = trim($_POST['option_b']);
        $option_c = trim($_POST['option_c']);
        $option_d = trim($_POST['option_d']);
        $correct_answer = $_POST['correct_answer'];
        $points = (int)$_POST['points'];

        // Validation
        if (empty($question_text) || empty($option_a) || empty($option_b) || empty($option_c) || empty($option_d)) {
            $error = 'Please fill in all fields';
        } elseif ($points < 1 || $points > 10) {
            $error = 'Points must be between 1 and 10';
        } else {
            // Insert question
            $stmt = $pdo->prepare("INSERT INTO questions (material_id, question_text, option_a, option_b, option_c, option_d, correct_answer, points) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

            if ($stmt->execute([$material_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_answer, $points])) {
                $message = 'Question added successfully!';
                // Refresh questions
                $stmt = $pdo->prepare("SELECT * FROM questions WHERE material_id = ? ORDER BY id ASC");
                $stmt->execute([$material_id]);
                $questions = $stmt->fetchAll();
            } else {
                $error = 'Failed to add question. Please try again.';
            }
        }
    } elseif (isset($_POST['delete_question'])) {
        $question_id = (int)$_POST['question_id'];

        // Delete question and related answers
        $pdo->prepare("DELETE FROM student_answers WHERE question_id = ?")->execute([$question_id]);
        $pdo->prepare("DELETE FROM questions WHERE id = ? AND material_id = ?")->execute([$question_id, $material_id]);

        $message = 'Question deleted successfully!';
        // Refresh questions
        $stmt = $pdo->prepare("SELECT * FROM questions WHERE material_id = ? ORDER BY id ASC");
        $stmt->execute([$material_id]);
        $questions = $stmt->fetchAll();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Questions - <?php echo htmlspecialchars($material['title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .questions-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 2rem 0;
        }
        .form-card {
            border: none;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        .question-card {
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        .option-input {
            background-color: #f8f9fa;
            border-left: 4px solid #007bff;
        }
        .option-a { border-left-color: #007bff; }
        .option-b { border-left-color: #28a745; }
        .option-c { border-left-color: #ffc107; }
        .option-d { border-left-color: #dc3545; }
        .correct-answer {
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        .btn-add-question {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-add-question:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
            color: white;
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
                <a class="nav-link" href="course_details.php?id=<?php echo $material['course_id']; ?>">
                    <i class="fas fa-arrow-left me-1"></i>Back to Course Details
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

    <!-- Questions Header -->
    <section class="questions-header">
        <div class="container">
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php" class="text-white-50">My Courses</a></li>
                    <li class="breadcrumb-item"><a href="course_details.php?id=<?php echo $material['course_id']; ?>" class="text-white-50"><?php echo htmlspecialchars($material['course_title']); ?></a></li>
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
                    <p class="lead mb-0">Add and manage questions for this <?php echo $material['material_type']; ?></p>
                </div>
                <div class="col-md-4 text-end">
                    <span class="badge bg-light text-dark fs-6">
                        <?php echo count($questions); ?> Questions Added
                    </span>
                </div>
            </div>
        </div>
    </section>

    <div class="container mt-4 mb-5">
        <?php if ($message): ?>
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Add Question Form -->
            <div class="col-lg-6">
                <div class="card form-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-plus me-2"></i>Add New Question</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label for="question_text" class="form-label">Question <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="question_text" name="question_text" rows="3" 
                                          placeholder="Enter your question here..." required></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Answer Options <span class="text-danger">*</span></label>

                                <div class="mb-2">
                                    <div class="input-group">
                                        <span class="input-group-text option-a"><strong>A</strong></span>
                                        <input type="text" class="form-control" name="option_a" placeholder="Option A" required>
                                    </div>
                                </div>

                                <div class="mb-2">
                                    <div class="input-group">
                                        <span class="input-group-text option-b"><strong>B</strong></span>
                                        <input type="text" class="form-control" name="option_b" placeholder="Option B" required>
                                    </div>
                                </div>

                                <div class="mb-2">
                                    <div class="input-group">
                                        <span class="input-group-text option-c"><strong>C</strong></span>
                                        <input type="text" class="form-control" name="option_c" placeholder="Option C" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="input-group">
                                        <span class="input-group-text option-d"><strong>D</strong></span>
                                        <input type="text" class="form-control" name="option_d" placeholder="Option D" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="correct_answer" class="form-label">Correct Answer <span class="text-danger">*</span></label>
                                    <select class="form-select" id="correct_answer" name="correct_answer" required>
                                        <option value="">Select correct answer</option>
                                        <option value="A">A</option>
                                        <option value="B">B</option>
                                        <option value="C">C</option>
                                        <option value="D">D</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="points" class="form-label">Points <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="points" name="points" min="1" max="10" value="1" required>
                                </div>
                            </div>

                            <button type="submit" name="add_question" class="btn btn-add-question w-100">
                                <i class="fas fa-plus me-2"></i>Add Question
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Existing Questions -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Added Questions</h5>
                        <?php if (!empty($questions)): ?>
                            <a href="course_details.php?id=<?php echo $material['course_id']; ?>" class="btn btn-success btn-sm">
                                <i class="fas fa-check me-1"></i>Finish
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                        <?php if (empty($questions)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-question-circle fa-3x text-muted mb-3"></i>
                                <h6 class="text-muted">No Questions Added</h6>
                                <p class="text-muted mb-0">Start adding questions using the form on the left.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($questions as $index => $question): ?>
                                <div class="question-card card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="card-title">Question <?php echo $index + 1; ?></h6>
                                            <div>
                                                <span class="badge bg-primary"><?php echo $question['points']; ?> pts</span>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this question?');">
                                                    <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                                                    <button type="submit" name="delete_question" class="btn btn-sm btn-outline-danger ms-2">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>

                                        <p class="card-text mb-3"><?php echo htmlspecialchars($question['question_text']); ?></p>

                                        <div class="options">
                                            <div class="mb-1 p-2 rounded <?php echo $question['correct_answer'] === 'A' ? 'correct-answer' : 'bg-light'; ?>">
                                                <strong>A)</strong> <?php echo htmlspecialchars($question['option_a']); ?>
                                                <?php if ($question['correct_answer'] === 'A'): ?>
                                                    <i class="fas fa-check text-success ms-2"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="mb-1 p-2 rounded <?php echo $question['correct_answer'] === 'B' ? 'correct-answer' : 'bg-light'; ?>">
                                                <strong>B)</strong> <?php echo htmlspecialchars($question['option_b']); ?>
                                                <?php if ($question['correct_answer'] === 'B'): ?>
                                                    <i class="fas fa-check text-success ms-2"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="mb-1 p-2 rounded <?php echo $question['correct_answer'] === 'C' ? 'correct-answer' : 'bg-light'; ?>">
                                                <strong>C)</strong> <?php echo htmlspecialchars($question['option_c']); ?>
                                                <?php if ($question['correct_answer'] === 'C'): ?>
                                                    <i class="fas fa-check text-success ms-2"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="mb-1 p-2 rounded <?php echo $question['correct_answer'] === 'D' ? 'correct-answer' : 'bg-light'; ?>">
                                                <strong>D)</strong> <?php echo htmlspecialchars($question['option_d']); ?>
                                                <?php if ($question['correct_answer'] === 'D'): ?>
                                                    <i class="fas fa-check text-success ms-2"></i>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="text-center mt-4">
            <?php if (!empty($questions)): ?>
                <a href="course_details.php?id=<?php echo $material['course_id']; ?>" class="btn btn-success btn-lg me-3">
                    <i class="fas fa-check me-2"></i>Finish Adding Questions
                </a>
            <?php endif; ?>
            <a href="course_details.php?id=<?php echo $material['course_id']; ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Course
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>