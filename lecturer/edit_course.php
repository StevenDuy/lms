<?php
require_once '../config/config.php';
requireRole('lecturer');

$user = getCurrentUser();
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get course details and verify ownership
$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND lecturer_id = ?");
$stmt->execute([$course_id, $user['id']]);
$course = $stmt->fetch();

if (!$course) {
    redirect('dashboard.php');
}

// Check if course has students (to prevent deletion)
$stmt = $pdo->prepare("SELECT COUNT(*) as student_count FROM enrollments WHERE course_id = ?");
$stmt->execute([$course_id]);
$has_students = $stmt->fetch()['student_count'] > 0;

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_course'])) {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $status = $_POST['status'];

        // Validation
        if (empty($title) || empty($description)) {
            $error = 'Please fill in all required fields';
        } elseif (strlen($title) < 3) {
            $error = 'Course title must be at least 3 characters long';
        } elseif (strlen($description) < 10) {
            $error = 'Course description must be at least 10 characters long';
        } else {
            // Update course
            $stmt = $pdo->prepare("UPDATE courses SET title = ?, description = ?, status = ? WHERE id = ? AND lecturer_id = ?");

            if ($stmt->execute([$title, $description, $status, $course_id, $user['id']])) {
                $message = 'Course updated successfully!';
                // Refresh course data
                $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND lecturer_id = ?");
                $stmt->execute([$course_id, $user['id']]);
                $course = $stmt->fetch();
            } else {
                $error = 'Failed to update course. Please try again.';
            }
        }
    } elseif (isset($_POST['delete_course']) && !$has_students) {
        // Delete course and related data
        $pdo->beginTransaction();
        try {
            // Delete in correct order to avoid foreign key constraints
            $pdo->prepare("DELETE FROM student_answers WHERE question_id IN (SELECT q.id FROM questions q JOIN materials m ON q.material_id = m.id WHERE m.course_id = ?)")->execute([$course_id]);
            $pdo->prepare("DELETE FROM test_results WHERE material_id IN (SELECT id FROM materials WHERE course_id = ?)")->execute([$course_id]);
            $pdo->prepare("DELETE FROM questions WHERE material_id IN (SELECT id FROM materials WHERE course_id = ?)")->execute([$course_id]);
            $pdo->prepare("DELETE FROM materials WHERE course_id = ?")->execute([$course_id]);
            $pdo->prepare("DELETE FROM enrollments WHERE course_id = ?")->execute([$course_id]);
            $pdo->prepare("DELETE FROM courses WHERE id = ? AND lecturer_id = ?")->execute([$course_id, $user['id']]);

            $pdo->commit();
            redirect('dashboard.php?deleted=1');
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Failed to delete course. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Course - <?php echo htmlspecialchars($course['title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .edit-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 2rem 0;
        }
        .form-card {
            border: none;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        .btn-update {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-update:hover {
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
                <a class="nav-link" href="course_details.php?id=<?php echo $course['id']; ?>">
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

    <!-- Edit Header -->
    <section class="edit-header">
        <div class="container">
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php" class="text-white-50">My Courses</a></li>
                    <li class="breadcrumb-item"><a href="course_details.php?id=<?php echo $course['id']; ?>" class="text-white-50"><?php echo htmlspecialchars($course['title']); ?></a></li>
                    <li class="breadcrumb-item active text-white">Edit Course</li>
                </ol>
            </nav>

            <h1 class="display-5 fw-bold mb-2">
                <i class="fas fa-edit me-3"></i>Edit Course
            </h1>
            <p class="lead mb-0">Modify course information and settings</p>
        </div>
    </section>

    <div class="container mt-4 mb-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card form-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-book-open me-2"></i>Course Information</h5>
                    </div>
                    <div class="card-body">
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

                        <form method="POST">
                            <div class="mb-3">
                                <label for="title" class="form-label">Course Title <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-heading"></i></span>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           placeholder="Enter course title" required maxlength="200"
                                           value="<?php echo htmlspecialchars($course['title']); ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Course Description <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-align-left"></i></span>
                                    <textarea class="form-control" id="description" name="description" rows="5" 
                                              placeholder="Describe what students will learn..." required><?php echo htmlspecialchars($course['description']); ?></textarea>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="status" class="form-label">Course Status</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-toggle-on"></i></span>
                                    <select class="form-select" id="status" name="status">
                                        <option value="active" <?php echo $course['status'] === 'active' ? 'selected' : ''; ?>>Active (Visible to students)</option>
                                        <option value="inactive" <?php echo $course['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive (Hidden from students)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="course_details.php?id=<?php echo $course['id']; ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                                <button type="submit" name="update_course" class="btn btn-update">
                                    <i class="fas fa-save me-2"></i>Update Course
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Danger Zone -->
                <div class="card mt-4 border-danger">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Danger Zone</h5>
                    </div>
                    <div class="card-body">
                        <h6>Delete Course</h6>
                        <?php if ($has_students): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Cannot delete this course:</strong> Students are currently enrolled. 
                                You can only edit the course or set it to inactive.
                            </div>
                        <?php else: ?>
                            <p class="text-muted">
                                Once you delete a course, there is no going back. Please be certain.
                            </p>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this course? This action cannot be undone.');">
                                <button type="submit" name="delete_course" class="btn btn-danger">
                                    <i class="fas fa-trash me-2"></i>Delete Course
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Course Information -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Course Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Created:</strong> <?php echo formatDate($course['created_at']); ?></p>
                                <p><strong>Last Updated:</strong> <?php echo formatDate($course['updated_at']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Course ID:</strong> #<?php echo $course['id']; ?></p>
                                <p><strong>Status:</strong> 
                                    <span class="badge <?php echo $course['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo ucfirst($course['status']); ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>