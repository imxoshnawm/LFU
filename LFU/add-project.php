<?php
require_once 'config.php';
requireLogin();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = sanitizeInput($_POST['title']);
    $description = sanitizeInput($_POST['description']);
    $team_members = isset($_POST['team_members']) ? array_filter($_POST['team_members']) : [];
    
    if (!empty($title) && !empty($description)) {
        try {
            $pdo->beginTransaction();
            
            // Insert project
            $stmt = $pdo->prepare("INSERT INTO projects (title, description) VALUES (?, ?)");
            $stmt->execute([$title, $description]);
            $project_id = $pdo->lastInsertId();
            
            // Add current user as project member
            $stmt = $pdo->prepare("INSERT INTO project_members (project_id, user_id) VALUES (?, ?)");
            $stmt->execute([$project_id, $_SESSION['user_id']]);
            
            // Add team members
            foreach ($team_members as $member_id) {
                if (!empty($member_id) && $member_id != $_SESSION['user_id']) {
                    $stmt->execute([$project_id, $member_id]);
                }
            }
            
            // Handle image uploads
            if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
                $stmt = $pdo->prepare("INSERT INTO project_images (project_id, image_path) VALUES (?, ?)");
                
                for ($i = 0; $i < count($_FILES['images']['name']); $i++) {
                    if ($_FILES['images']['error'][$i] == UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $_FILES['images']['name'][$i],
                            'type' => $_FILES['images']['type'][$i],
                            'tmp_name' => $_FILES['images']['tmp_name'][$i],
                            'error' => $_FILES['images']['error'][$i],
                            'size' => $_FILES['images']['size'][$i]
                        ];
                        
                        $filename = uploadImage($file);
                        if ($filename) {
                            $stmt->execute([$project_id, $filename]);
                        }
                    }
                }
            }
            
            $pdo->commit();
            $success = 'پڕۆژەکە بە سەرکەوتوویی زیاد کرا!';
            
            // Redirect after 2 seconds
            header("Refresh: 2; url=dashboard.php");
            
        } catch (Exception $e) {
            $pdo->rollback();
            $error = 'خەتا لە زیادکردنی پڕۆژەدا: ' . $e->getMessage();
        }
    } else {
        $error = 'تکایە ناونیشان و وەسف پڕ بکەرەوە';
    }
}

// Get all users except current user for team selection
$stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE id != ? AND role = 'student' ORDER BY username");
$stmt->execute([$_SESSION['user_id']]);
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>زیادکردنی پڕۆژەی نوێ - سیستەمی بەڕێوەبردنی پڕۆژە</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-project-diagram me-2"></i>
                سیستەمی پڕۆژەکان
            </a>
            
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    بەخێرهاتیت، <?= $_SESSION['username'] ?>
                </span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> دەرچوون
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card form-card">
                    <div class="card-header bg-white">
                        <h3 class="mb-0">
                            <i class="fas fa-plus-circle me-2"></i>
                            زیادکردنی پڕۆژەی نوێ
                        </h3>
                    </div>
                    
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                <?= $success ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?= $error ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="title" class="form-label">ناونیشانی پڕۆژە *</label>
                                <input type="text" class="form-control" id="title" name="title" required 
                                       placeholder="ناونیشانی پڕۆژەکە بنووسە">
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">وەسفی پڕۆژە *</label>
                                <textarea class="form-control" id="description" name="description" rows="6" required 
                                          placeholder="وەسفی تەواو و درێژی پڕۆژەکە بنووسە..."></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">ئەندامانی تیم (دەتوانیت تاکە یان لەگەڵ کەسێکی تر کار بکەیت)</label>
                                <div class="row">
                                    <div class="col-md-6">
                                        <select class="form-select" name="team_members[]">
                                            <option value="">هیچ ئەندامێک دیاری نەکە</option>
                                            <?php foreach ($users as $user): ?>
                                                <option value="<?= $user['id'] ?>">
                                                    <?= htmlspecialchars($user['username']) ?> (<?= htmlspecialchars($user['email']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <small class="text-muted">تۆ بە خۆکاری وەک ئەندامی پڕۆژە زیاد دەکرێیت</small>
                            </div>
                            
                            <div class="mb-4">
                                <label for="images" class="form-label">وێنەکانی پڕۆژە</label>
                                <input type="file" class="form-control" id="images" name="images[]" 
                                       accept="image/*" multiple onchange="previewImages()">
                                <small class="text-muted">دەتوانیت چەند وێنەیەک هەڵبژێریت (حەداکثر 5MB هەر وێنەیەک)</small>
                                <div id="imagePreview" class="mt-3"></div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="dashboard.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-right me-2"></i>
                                    گەڕانەوە
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>
                                    زیادکردنی پڕۆژە
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
        function previewImages() {
            const preview = document.getElementById('imagePreview');
            const files = document.getElementById('images').files;
            
            preview.innerHTML = '';
            
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'image-preview';
                    preview.appendChild(img);
                }
                
                reader.readAsDataURL(file);
            }
        }
    </script>
</body>
</html>