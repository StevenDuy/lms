<?php
require_once '../config/config.php';
requireRole('student');

$user = getCurrentUser();
$material_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get material details and check if it's a PDF material
$stmt = $pdo->prepare("
    SELECT m.*, c.title as course_title, c.id as course_id
    FROM materials m
    JOIN courses c ON m.course_id = c.id
    JOIN enrollments e ON c.id = e.course_id
    WHERE m.id = ? AND e.student_id = ? AND m.material_type = 'pdf'
");
$stmt->execute([$material_id, $user['id']]);
$material = $stmt->fetch();

if (!$material) {
    redirect('dashboard.php');
}

// Check if student already submitted
$stmt = $pdo->prepare("SELECT * FROM student_material_submissions WHERE student_id = ? AND material_id = ?");
$stmt->execute([$user['id'], $material_id]);
$submission = $stmt->fetch();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] == 0) {
        $allowed = array('pdf');
        $filename = $_FILES['pdf_file']['name'];
        $filetmp = $_FILES['pdf_file']['tmp_name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $error = 'Only PDF files are allowed';
        } else {
            $new_filename = uniqid('submission_') . '.' . $ext;
            $upload_dir = '../uploads/submissions/';

            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            if (move_uploaded_file($filetmp, $upload_dir . $new_filename)) {
                $file_path = $upload_dir . $new_filename;
                $file_size = filesize($filetmp);
                $file_name = $filename;

                if ($submission) {
                    // Update existing submission
                    $stmt = $pdo->prepare("UPDATE student_material_submissions 
                                           SET file_path = ?, file_name = ?, file_size = ?, submitted_at = CURRENT_TIMESTAMP 
                                           WHERE id = ?");
                    $stmt->execute([$file_path, $file_name, $file_size, $submission['id']]);
                    $message = 'Your submission has been updated successfully.';
                } else {
                    // Create new submission
                    $stmt = $pdo->prepare("INSERT INTO student_material_submissions 
                                           (student_id, material_id, file_path, file_name, file_size) 
                                           VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$user['id'], $material_id, $file_path, $file_name, $file_size]);
                    $message = 'Your submission has been uploaded successfully.';
                }
            } else {
                $error = 'Error uploading file. Please try again.';
            }
        }
    } else {
        $error = 'Please select a PDF file to upload.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Assignment - <?php echo htmlspecialchars($material['title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .submit-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
        }
        .form-card {
            border: none;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
            color: white;
        }
        .submission-info {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
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

    <!-- Submit Header -->
    <section class="submit-header">
        <div class="container">
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="my_courses.php" class="text-white-50">My Courses</a></li>
                    <li class="breadcrumb-item"><a href="course_learning.php?id=<?php echo $material['course_id']; ?>" class="text-white-50"><?php echo htmlspecialchars($material['course_title']); ?></a></li>
                    <li class="breadcrumb-item active text-white">Submit Assignment</li>
                </ol>
            </nav>

            <h1 class="display-5 fw-bold mb-2">
                <i class="fas fa-file-upload me-3"></i>Submit Your Work
            </h1>
            <p class="lead mb-0">Upload your PDF assignment for <?php echo htmlspecialchars($material['title']); ?></p>
        </div>
    </section>

    <div class="container mt-4 mb-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card form-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-file-pdf me-2"></i>Submit Your PDF Assignment</h5>
                    </div>
                    <div class="card-body">
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

                        <?php if ($submission): ?>
                            <div class="submission-info">
                                <h6 class="mb-3"><i class="fas fa-info-circle me-2"></i>Previous Submission</h6>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="mb-1"><strong>File:</strong> <?php echo htmlspecialchars($submission['file_name']); ?></p>
                                        <p class="mb-0"><strong>Submitted:</strong> <?php echo date('M d, Y H:i', strtotime($submission['submitted_at'])); ?></p>
                                    </div>
                                    <?php if ($submission['grade'] === null): ?>
                                        <span class="badge bg-warning">Pending Review</span>
                                    <?php else: ?>
                                        <span class="badge bg-primary">Score: <?php echo $submission['grade']; ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($submission['feedback'])): ?>
                                    <div class="mt-3">
                                        <h6>Feedback:</h6>
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($submission['feedback'])); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-4">
                                <label for="pdf_file" class="form-label">PDF File <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-file-pdf"></i></span>
                                    <input type="file" class="form-control" id="pdf_file" name="pdf_file" accept=".pdf" required>
                                </div>
                                <div class="form-text">Upload your completed assignment as a PDF file.</div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="course_learning.php?id=<?php echo $material['course_id']; ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-submit">
                                    <i class="fas fa-upload me-2"><?php echo $submission ? 'Update Submission' : 'Submit Assignment'; ?></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
