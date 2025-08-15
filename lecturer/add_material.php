<?php
require_once '../config/config.php';
requireRole('lecturer');

$user = getCurrentUser();
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

// Verify course ownership
$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND lecturer_id = ?");
$stmt->execute([$course_id, $user['id']]);
$course = $stmt->fetch();

if (!$course) {
    redirect('dashboard.php');
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $material_type = $_POST['material_type'];

    // Get next order index
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(order_index), 0) + 1 as next_order FROM materials WHERE course_id = ?");
    $stmt->execute([$course_id]);
    $order_index = $stmt->fetch()['next_order'];

    $file_path = NULL;
    $file_size = NULL;
    $file_type = NULL;
    $external_link = NULL;

    // Handle file upload for PDF materials
    if ($material_type === 'pdf') {
        if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] == 0) {
            $allowed = array('pdf');
            $filename = $_FILES['pdf_file']['name'];
            $filetmp = $_FILES['pdf_file']['tmp_name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed)) {
                $error = 'Only PDF files are allowed';
            } else {
                $new_filename = uniqid('material_') . '.' . $ext;
                $upload_dir = '../uploads/materials/';

                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                if (move_uploaded_file($filetmp, $upload_dir . $new_filename)) {
                    $file_path = $upload_dir . $new_filename;
                    $file_size = $_FILES['pdf_file']['size'];
                    $file_type = $ext;
                } else {
                    $error = 'Error uploading file. Please try again.';
                }
            }
        } else {
            $error = 'Please select a PDF file to upload';
        }
    }

    // Handle external link for link materials
    if ($material_type === 'link') {
        if (!empty($_POST['external_link'])) {
            $external_link = trim($_POST['external_link']);
            if (!filter_var($external_link, FILTER_VALIDATE_URL)) {
                $error = 'Please enter a valid URL';
            }
        } else {
            $error = 'Please enter an external link';
        }
    }

    // Validation
    if (empty($title) || empty($content)) {
        $error = 'Please fill in all required fields';
    } elseif (strlen($title) < 3) {
        $error = 'Material title must be at least 3 characters long';
    } elseif (strlen($content) < 10) {
        $error = 'Material content must be at least 10 characters long';
    } elseif (empty($error)) {
        // Insert material
        $stmt = $pdo->prepare("INSERT INTO materials (course_id, title, content, material_type, file_path, file_size, file_type, external_link, order_index) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if ($stmt->execute([$course_id, $title, $content, $material_type, $file_path, $file_size, $file_type, $external_link, $order_index])) {
            $material_id = $pdo->lastInsertId();

            // If it's assignment or exam, redirect to add questions
            if ($material_type === 'assignment' || $material_type === 'exam') {
                redirect('add_questions.php?material_id=' . $material_id);
            } else {
                redirect('course_details.php?id=' . $course_id);
            }
        } else {
            $error = 'Failed to add material. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Material - <?php echo htmlspecialchars($course['title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .add-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 2rem 0;
        }
        .form-card {
            border: none;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        .type-card {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            height: 100%;
        }
        .type-card:hover {
            border-color: #28a745;
            background-color: #f8f9fa;
        }
        .type-card.active {
            border-color: #28a745;
            background-color: #e8f5e8;
        }
        .btn-add {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-add:hover {
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

    <!-- Add Header -->
    <section class="add-header">
        <div class="container">
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php" class="text-white-50">My Courses</a></li>
                    <li class="breadcrumb-item"><a href="course_details.php?id=<?php echo $course['id']; ?>" class="text-white-50"><?php echo htmlspecialchars($course['title']); ?></a></li>
                    <li class="breadcrumb-item active text-white">Add Material</li>
                </ol>
            </nav>

            <h1 class="display-5 fw-bold mb-2">
                <i class="fas fa-plus-circle me-3"></i>Add Course Material
            </h1>
            <p class="lead mb-0">Create new learning content for your students</p>
        </div>
    </section>

    <div class="container mt-4 mb-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card form-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-book-open me-2"></i>Material Information</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data" id="materialForm">
                            <!-- Material Type Selection -->
                            <div class="mb-4">
                                <label class="form-label">Material Type <span class="text-danger">*</span></label>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <div class="type-card" onclick="selectType('lesson')">
                                            <i class="fas fa-book fa-3x text-primary mb-3"></i>
                                            <h5>Lesson</h5>
                                            <p class="text-muted mb-0">Educational content and reading materials</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="type-card" onclick="selectType('assignment')">
                                            <i class="fas fa-tasks fa-3x text-warning mb-3"></i>
                                            <h5>Assignment</h5>
                                            <p class="text-muted mb-0">Practice questions (retakes allowed)</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="type-card" onclick="selectType('exam')">
                                            <i class="fas fa-clipboard-check fa-3x text-danger mb-3"></i>
                                            <h5>Exam</h5>
                                            <p class="text-muted mb-0">Assessment test (one attempt only)</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="type-card" onclick="selectType('pdf')">
                                            <i class="fas fa-file-pdf fa-3x text-danger mb-3"></i>
                                            <h5>PDF Document</h5>
                                            <p class="text-muted mb-0">Upload PDF files for students</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="type-card" onclick="selectType('link')">
                                            <i class="fas fa-link fa-3x text-info mb-3"></i>
                                            <h5>External Link</h5>
                                            <p class="text-muted mb-0">Link to external resources</p>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" name="material_type" id="selectedType" value="lesson" required>
                            </div>

                            <div class="mb-3">
                                <label for="title" class="form-label">Material Title <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-heading"></i></span>
                                    <input type="text" class="form-control" id="title" name="title"
                                           placeholder="Enter material title" required maxlength="200"
                                           value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                                </div>
                                <div class="form-text">Choose a clear and descriptive title for this material.</div>
                            </div>

                            <div class="mb-4">
                                <label for="content" class="form-label">Content <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-align-left"></i></span>
                                    <textarea class="form-control" id="content" name="content" rows="8"
                                              placeholder="Enter the material content..." required><?php echo isset($_POST['content']) ? htmlspecialchars($_POST['content']) : ''; ?></textarea>
                                </div>
                                <div class="form-text" id="contentHelp">
                                    For lessons: Write the educational content.<br>
                                    For assignments/exams: Write instructions or description.<br>
                                    For PDFs: Write description of the document.<br>
                                    For Links: Write description of the external resource.
                                </div>
                            </div>

                            <!-- File Upload for PDF Materials -->
                            <div id="pdfUploadSection" class="mb-4" style="display: none;">
                                <label for="pdf_file" class="form-label">PDF File <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-file-pdf"></i></span>
                                    <input type="file" class="form-control" id="pdf_file" name="pdf_file" accept=".pdf">
                                </div>
                                <div class="form-text">Upload a PDF file for students to view or download.</div>
                            </div>

                            <!-- External Link for Link Materials -->
                            <div id="linkSection" class="mb-4" style="display: none;">
                                <label for="external_link" class="form-label">External Link <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-link"></i></span>
                                    <input type="text" class="form-control" id="external_link" name="external_link"
                                           placeholder="https://example.com" value="<?php echo isset($_POST['external_link']) ? htmlspecialchars($_POST['external_link']) : ''; ?>">
                                </div>
                                <div class="form-text">Enter a valid URL to an external resource.</div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="course_details.php?id=<?php echo $course['id']; ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-add">
                                    <i class="fas fa-plus me-2"></i>Add Material
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectType(type) {
            // Remove active class from all cards
            document.querySelectorAll('.type-card').forEach(card => {
                card.classList.remove('active');
            });

            // Add active class to selected card
            if (event && event.currentTarget) {
                event.currentTarget.classList.add('active');
            }

            // Update hidden input
            document.getElementById('selectedType').value = type;

            // Get elements
            const helpText = document.getElementById('contentHelp');
            const pdfSection = document.getElementById('pdfUploadSection');
            const linkSection = document.getElementById('linkSection');
            const pdfFile = document.getElementById('pdf_file');
            const linkInput = document.getElementById('external_link');

            // Hide all special sections first and remove required attributes
            if (pdfSection) {
                pdfSection.style.display = 'none';
                if (pdfFile) pdfFile.required = false;
            }
            if (linkSection) {
                linkSection.style.display = 'none';
                if (linkInput) linkInput.required = false;
            }

            // Update help text and show relevant sections based on type
            if (type === 'lesson') {
                if (helpText) helpText.innerHTML = 'Write the educational content that students will read and learn from.';
            } else if (type === 'assignment' || type === 'exam') {
                if (helpText) helpText.innerHTML = 'Write instructions or description. You will add questions in the next step.';
            } else if (type === 'pdf') {
                if (helpText) helpText.innerHTML = 'Write description of the PDF document.';
                if (pdfSection) {
                    pdfSection.style.display = 'block';
                    if (pdfFile) pdfFile.required = true;
                }
            } else if (type === 'link') {
                if (helpText) helpText.innerHTML = 'Write description of the external resource.';
                if (linkSection) {
                    linkSection.style.display = 'block';
                    if (linkInput) linkInput.required = true;
                }
            }
        }

        // Set initial state
        document.addEventListener('DOMContentLoaded', function() {
            // Set first card as active
            const firstCard = document.querySelector('.type-card');
            if (firstCard) {
                firstCard.classList.add('active');
            }

            // Initialize form
            selectType('lesson');
        });
    </script>
</body>
</html>
