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
    WHERE m.id = ? AND e.student_id = ? AND (m.material_type = 'lesson' OR m.material_type = 'pdf' OR m.material_type = 'link')
");
$stmt->execute([$material_id, $user['id']]);
$material = $stmt->fetch();

if (!$material) {
    redirect('my_courses.php');
}

// Mark as viewed by creating a test result (for lessons, we create a dummy result)
$stmt = $pdo->prepare("SELECT id FROM test_results WHERE student_id = ? AND material_id = ?");
$stmt->execute([$user['id'], $material_id]);
$viewed = $stmt->fetch();

if (!$viewed) {
    $stmt = $pdo->prepare("INSERT INTO test_results (student_id, material_id, score, total_points, percentage) VALUES (?, ?, 100, 100, 100)");
    $stmt->execute([$user['id'], $material_id]);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($material['title']); ?> - Lesson</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .lesson-content {
            font-size: 1.1rem;
            line-height: 1.8;
        }
        .lesson-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
        }
        .back-btn {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
        }
        .back-btn:hover {
            background: rgba(255,255,255,0.3);
            color: white;
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

    <!-- Lesson Header -->
    <section class="lesson-header">
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
                        <i class="fas fa-book me-3"></i><?php echo htmlspecialchars($material['title']); ?>
                    </h1>
                    <p class="mb-0">Course: <?php echo htmlspecialchars($material['course_title']); ?></p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="alert alert-success bg-white bg-opacity-25 border-0 text-white">
                        <i class="fas fa-check-circle me-2"></i>Lesson Completed
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Lesson Content -->
    <div class="container mt-4 mb-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-body p-5">
                        <div class="lesson-content">
                            <?php if ($material['material_type'] === 'lesson'): ?>
                                <?php echo nl2br(htmlspecialchars($material['content'])); ?>
                            <?php elseif ($material['material_type'] === 'pdf'): ?>
                                <div class="mb-4">
                                    <h5 class="mb-3"><?php echo htmlspecialchars($material['title']); ?></h5>
                                    <p><?php echo nl2br(htmlspecialchars($material['content'])); ?></p>
                                </div>
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="fas fa-file-pdf me-2"></i>Document Preview</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (!empty($material['file_path'])): ?>
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h6 class="mb-0"><?php echo htmlspecialchars(pathinfo($material['file_path'], PATHINFO_BASENAME)); ?></h6>
                                                <div>
                                                    <a href="<?php echo htmlspecialchars($material['file_path']); ?>" class="btn btn-primary btn-sm" download>
                                                        <i class="fas fa-download me-1"></i>Download PDF
                                                    </a>
                                                    <button type="button" class="btn btn-outline-primary btn-sm ms-2" onclick="window.open('<?php echo htmlspecialchars($material['file_path']); ?>', '_blank')">
                                                        <i class="fas fa-external-link-alt me-1"></i>View in New Tab
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="pdf-viewer" style="height: 600px;">
                                                <div class="alert alert-info">
                                                    <i class="fas fa-info-circle me-2"></i>PDF preview may take a moment to load. If it doesn't display, use the "View in New Tab" button.
                                                </div>
                                                <iframe src="<?php echo htmlspecialchars($material['file_path']); ?>" width="100%" height="100%" frameborder="0"></iframe>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-warning">
                                                <i class="fas fa-exclamation-triangle me-2"></i>PDF document not available.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php elseif ($material['material_type'] === 'link'): ?>
                                <div class="mb-4">
                                    <h5 class="mb-3"><?php echo htmlspecialchars($material['title']); ?></h5>
                                    <p><?php echo nl2br(htmlspecialchars($material['content'])); ?></p>
                                </div>
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="fas fa-external-link-alt me-2"></i>External Resource</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (!empty($material['external_link'])): ?>
                                            <div class="mb-3">
                                                <h6 class="mb-2">External Resource Link:</h6>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" readonly value="<?php echo htmlspecialchars($material['external_link']); ?>" id="linkInput<?php echo $material['id']; ?>">
                                                    <button type="button" class="btn btn-outline-secondary" onclick="copyToClipboard('linkInput<?php echo $material['id']; ?>')">
                                                        <i class="fas fa-copy"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <a href="<?php echo htmlspecialchars($material['external_link']); ?>" target="_blank" class="btn btn-primary">
                                                        <i class="fas fa-external-link-alt me-1"></i>Open Link
                                                    </a>
                                                </div>
                                                <div class="alert alert-info d-inline-block align-middle mb-0" style="max-width: 300px;">
                                                    <i class="fas fa-info-circle me-2"></i>This link will open in a new tab.
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-warning">
                                                <i class="fas fa-exclamation-triangle me-2"></i>External link not available.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Navigation -->
                <div class="mt-4 d-flex justify-content-between">
                    <a href="course_learning.php?id=<?php echo $material['course_id']; ?>" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Course
                    </a>

                    <?php
                    // Get next material
                    $stmt = $pdo->prepare("
                        SELECT id, title, material_type 
                        FROM materials 
                        WHERE course_id = ? AND order_index > ? 
                        ORDER BY order_index ASC 
                        LIMIT 1
                    ");
                    $stmt->execute([$material['course_id'], $material['order_index']]);
                    $next_material = $stmt->fetch();
                    ?>

                    <?php if ($next_material): ?>
                        <?php if ($next_material['material_type'] === 'lesson'): ?>
                            <a href="view_material.php?id=<?php echo $next_material['id']; ?>" class="btn btn-primary">
                                Next: <?php echo htmlspecialchars($next_material['title']); ?>
                                <i class="fas fa-arrow-right ms-2"></i>
                            </a>
                        <?php else: ?>
                            <a href="take_test.php?id=<?php echo $next_material['id']; ?>" class="btn btn-primary">
                                Next: <?php echo htmlspecialchars($next_material['title']); ?>
                                <i class="fas fa-arrow-right ms-2"></i>
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-muted">
                            <i class="fas fa-check me-2"></i>Course Complete
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function copyToClipboard(elementId) {
            var copyText = document.getElementById(elementId);
            copyText.select();
            copyText.setSelectionRange(0, 99999); // For mobile devices
            navigator.clipboard.writeText(copyText.value).then(function() {
                // Show feedback
                var button = event.target.closest('button');
                var originalHTML = button.innerHTML;
                button.innerHTML = '<i class="fas fa-check me-1"></i>Copied!';
                button.classList.add('btn-success');
                button.classList.remove('btn-outline-secondary');
                
                // Reset after 2 seconds
                setTimeout(function() {
                    button.innerHTML = originalHTML;
                    button.classList.remove('btn-success');
                    button.classList.add('btn-outline-secondary');
                }, 2000);
            }).catch(function(err) {
                console.error('Failed to copy text: ', err);
            });
        }
    </script>
</body>
</html>