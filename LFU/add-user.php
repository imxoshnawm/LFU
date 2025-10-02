<?php
require_once 'config.php';
requireAdmin();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $role = sanitizeInput($_POST['role']);
    
    if (!empty($username) && !empty($email) && !empty($password)) {
        // Check if username or email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->rowCount() > 0) {
            $error = 'ناوی بەکارهێنەر یان ئیمێڵ پێشتر بەکارهێنراوە';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            try {
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$username, $email, $hashed_password, $role]);
                
                $success = 'بەکارهێنەر بە سەرکەوتوویی زیاد کرا!';
                
                // Clear form
                $_POST = array();
                
            } catch (Exception $e) {
                $error = 'خەتا لە زیادکردنی بەکارهێنەردا: ' . $e->getMessage();
            }
        }
    } else {
        $error = 'تکایە هەموو خانەکان پڕ بکەرەوە';
    }
}

// Get all users for display
$stmt = $pdo->prepare("SELECT * FROM users ORDER BY created_at DESC");
$stmt->execute();
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>زیادکردنی بەکارهێنەر - سیستەمی بەڕێوەبردنی پڕۆژە</title>
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
                    زیادکردنی بەکارهێنەر - <?= $_SESSION['username'] ?>
                </span>
                <a href="admin-panel.php" class="btn btn-outline-light btn-sm me-2">
                    <i class="fas fa-cog"></i> پانێڵی ئەدمین
                </a>
                <a href="dashboard.php" class="btn btn-outline-light btn-sm me-2">
                    <i class="fas fa-home"></i> داشبۆرد
                </a>
                <a href="logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> دەرچوون
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <!-- Add User Form -->
            <div class="col-lg-6">
                <div class="card form-card">
                    <div class="card-header bg-white">
                        <h4 class="mb-0">
                            <i class="fas fa-user-plus me-2"></i>
                            زیادکردنی بەکارهێنەری نوێ
                        </h4>
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
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="username" class="form-label">ناوی بەکارهێنەر *</label>
                                <input type="text" class="form-control" id="username" name="username" required 
                                       value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
                                       placeholder="ناوی بەکارهێنەر">
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">ئیمێڵ *</label>
                                <input type="email" class="form-control" id="email" name="email" required 
                                       value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                                       placeholder="ئیمێڵی بەکارهێنەر">
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">پاسۆرد *</label>
                                <input type="password" class="form-control" id="password" name="password" required 
                                       placeholder="پاسۆردی بەکارهێنەر">
                                <small class="text-muted">پێویستە لانیکەم 6 پیت بێت</small>
                            </div>
                            
                            <div class="mb-4">
                                <label for="role" class="form-label">جۆری بەکارهێنەر *</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="student" <?= (isset($_POST['role']) && $_POST['role'] == 'student') ? 'selected' : '' ?>>
                                        قوتابی
                                    </option>
                                    <option value="admin" <?= (isset($_POST['role']) && $_POST['role'] == 'admin') ? 'selected' : '' ?>>
                                        ئەدمین
                                    </option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-user-plus me-2"></i>
                                زیادکردنی بەکارهێنەر
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Users List -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header bg-white">
                        <h4 class="mb-0">
                            <i class="fas fa-users me-2"></i>
                            لیستی بەکارهێنەران (<?= count($users) ?>)
                        </h4>
                    </div>
                    
                    <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                        <?php if (empty($users)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">هیچ بەکارهێنەرێک نییە</h5>
                            </div>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <div class="card user-card">
                                    <div class="card-body py-2">
                                        <div class="row align-items-center">
                                            <div class="col-md-8">
                                                <h6 class="mb-1">
                                                    <i class="fas fa-user me-1"></i>
                                                    <?= htmlspecialchars($user['username']) ?>
                                                    <?php if ($user['role'] == 'admin'): ?>
                                                        <span class="badge bg-danger ms-2">ئەدمین</span>
                                                    <?php endif; ?>
                                                </h6>
                                                <small class="text-muted">
                                                    <i class="fas fa-envelope me-1"></i>
                                                    <?= htmlspecialchars($user['email']) ?>
                                                </small>
                                            </div>
                                            <div class="col-md-4 text-end">
                                                <small class="text-muted">
                                                    <?= date('Y/m/d', strtotime($user['created_at'])) ?>
                                                </small>
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Generate random password
        function generatePassword() {
            const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            let password = '';
            for (let i = 0; i < 8; i++) {
                password += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            document.getElementById('password').value = password;
        }
        
        // Add generate password button
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const generateBtn = document.createElement('button');
            generateBtn.type = 'button';
            generateBtn.className = 'btn btn-outline-secondary btn-sm mt-1';
            generateBtn.innerHTML = '<i class="fas fa-random me-1"></i>دروستکردنی پاسۆرد';
            generateBtn.onclick = generatePassword;
            
            passwordInput.parentNode.appendChild(generateBtn);
        });
    </script>
</body>
</html>