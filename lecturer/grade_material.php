<?php
require_once '../config/config.php';
requireRole('lecturer');

$user = getCurrentUser();
$material_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Verify material ownership
$stmt = $pdo->prepare("
    SELECT m.*, c.title as course_title, c.id as course_id
    FROM materials m
    JOIN courses c ON m.course_id = c.id
    WHERE m.id = ? AND c.lecturer_id = ? AND m.material_type = 'pdf'
");
$stmt->execute([$material_id, $user['id']]);
$material = $stmt->fetch();

if (!$material) {
    redirect('dashboard.php');
}

// Get all submissions for this material
$stmt = $pdo->prepare("
    SELECT s.*, u.full_name as student_name, u.email
    FROM student_material_submissions s
    JOIN users u ON s.student_id = u.id
    WHERE s.material_id = ?
    ORDER BY s.submitted_at DESC
");
$stmt->execute([$material_id]);
$submissions = $stmt->fetchAll();

$message = '';
$error = '';

// Handle grading submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grade_submission'])) {
    $submission_id = (int)$_POST['submission_id'];
    $grade = (float)$_POST['grade'];
    $feedback = trim($_POST['feedback']);

    // Validate grade
    if ($grade < 0 || $grade > 100) {
        $error = 'Grade must be between 0 and 100';
    } else {
        // Update submission with grade and feedback
        $stmt = $pdo->prepare("
            UPDATE student_material_submissions 
            SET grade = ?, feedback = ?, graded_at = CURRENT_TIMESTAMP 
            WHERE id = ? AND material_id = ?
        ");
        if ($stmt->execute([$grade, $feedback, $submission_id, $material_id])) {
            $message = 'Grade has been saved successfully.';
        } else {
            $error = 'Error saving grade. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Submissions - <?php echo htmlspecialchars($material['title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .grade-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 2rem 0;
        }
        .submission-card {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
            transition: transform 0.3s;
        }
        .submission-card:hover {
            transform: translateY(-3px);
        }
        .btn-grade {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            border: none;
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 500;
            transition: all 0.3s;
        }
        .btn-grade:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 10px rgba(0, 0, 0, 0.2);
            color: white;
        }
        .file-link {
            color: #007bff;
            text-decoration: none;
        }
        .file-link:hover {
            color: #0056b3;
            text-decoration: underline;
        }
        .grade-badge {
            font-size: 1.2rem;
            font-weight: bold;
        }
        .grade-pending {
            background-color: #ffc107;
            color: #212529;
        }
        .grade-graded {
            background-color: #28a745;
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

    <!-- Grade Header -->
    <section class="grade-header">
        <div class="container">
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php" class="text-white-50">My Courses</a></li>
                    <li class="breadcrumb-item"><a href="course_details.php?id=<?php echo $material['course_id']; ?>" class="text-white-50"><?php echo htmlspecialchars($material['course_title']); ?></a></li>
                    <li class="breadcrumb-item active text-white">Grade Submissions</li>
                </ol>
            </nav>

            <h1 class="display-5 fw-bold mb-2">
                <i class="fas fa-file-invoice me-3"></i>Grade Submissions
            </h1>
            <p class="lead mb-0">Review and grade student submissions for <?php echo htmlspecialchars($material['title']); ?></p>
        </div>
    </section>

    <div class="container mt-4 mb-5">
        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($submissions)): ?>
            <div class="text-center py-5">
                <i class="fas fa-file-invoice fa-3x text-muted mb-3"></i>
                <h4 class="text-muted">No Submissions Yet</h4>
                <p class="text-muted">Students will be able to submit their assignments here once they upload their PDF files.</p>
            </div>
        <?php else: ?>
            <?php foreach ($submissions as $submission): ?>
                <div class="submission-card card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h5 class="card-title mb-1">
                                    <i class="fas fa-user-graduate me-2"></i><?php echo htmlspecialchars($submission['student_name']); ?>
                                </h5>
                                <p class="card-text text-muted mb-2"><?php echo htmlspecialchars($submission['email']); ?></p>

                                <div class="mb-2">
                                    <strong>Submitted:</strong> <?php echo date('M d, Y H:i', strtotime($submission['submitted_at'])); ?>
                                </div>

                                <div class="mb-3">
                                    <strong>File:</strong> 
                                    <a href="<?php echo htmlspecialchars($submission['file_path']); ?>" class="file-link" download>
                                        <i class="fas fa-file-pdf me-1"></i><?php echo htmlspecialchars($submission['file_name']); ?>
                                    </a>
                                    <span class="text-muted ms-2">(<?php echo round($submission['file_size'] / 1024, 2); ?> KB)</span>
                                </div>

                                <?php if ($submission['feedback']): ?>
                                    <div class="alert alert-info">
                                        <h6 class="alert-heading"><i class="fas fa-comment me-2"></i>Previous Feedback</h6>
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($submission['feedback'])); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-4 text-end">
                                <?php if ($submission['grade'] === null): ?>
                                    <span class="badge grade-badge grade-pending">Pending</span>
                                <?php else: ?>
                                    <span class="badge grade-badge grade-graded"><?php echo $submission['grade']; ?>%</span>
                                    <small class="text-muted d-block mt-1">Graded on <?php echo date('M d, Y', strtotime($submission['graded_at'])); ?></small>
                                <?php endif; ?>

                                <button type="button" class="btn btn-grade mt-3" data-bs-toggle="modal" data-bs-target="#gradeModal<?php echo $submission['id']; ?>">
                                    <i class="fas fa-edit me-1"></i>Grade
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Grade Modal -->
                <div class="modal fade" id="gradeModal<?php echo $submission['id']; ?>" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="fas fa-file-invoice me-2"></i>Grade Submission - <?php echo htmlspecialchars($submission['student_name']); ?>
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <form method="POST">
                                    <input type="hidden" name="submission_id" value="<?php echo $submission['id']; ?>">

                                    <div class="mb-3">
                                        <label for="grade<?php echo $submission['id']; ?>" class="form-label">Grade (0-100) <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-percent"></i></span>
                                            <input type="number" class="form-control" id="grade<?php echo $submission['id']; ?>" name="grade" 
                                                   min="0" max="100" step="0.1" value="<?php echo $submission['grade'] ?? ''; ?>" required>
                                        </div>
                                        <div class="form-text">Enter a grade between 0 and 100.</div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="feedback<?php echo $submission['id']; ?>" class="form-label">Feedback</label>
                                        <textarea class="form-control" id="feedback<?php echo $submission['id']; ?>" name="feedback" rows="4"
                                                  placeholder="Provide feedback on the student's submission..."><?php echo htmlspecialchars($submission['feedback'] ?? ''); ?></textarea>
                                        <div class="form-text">Optional feedback for the student.</div>
                                    </div>

                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" name="grade_submission" class="btn btn-success">
                                            <i class="fas fa-save me-1"></i>Save Grade
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
