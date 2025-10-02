<?php
require_once 'config.php';

// Check if there are any admin users already
$stmt = $pdo->prepare("SELECT COUNT(*) as admin_count FROM users WHERE role = 'admin'");
$stmt->execute();
$admin_exists = $stmt->fetch()['admin_count'] > 0;

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($username) || empty($email) || empty($password)) {
        $error = 'تکایە هەموو خانەکان پڕ بکەرەوە';
    } elseif ($password !== $confirm_password) {
        $error = 'وشە نهێنیەکان یەکناگرنەوە';
    } elseif (strlen($password) < 6) {
        $error = 'وشە نهێنیەکە دەبێت لانیکەم ٦ پیت بێت';
    } else {
        // Check if username or email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->fetch()) {
            $error = 'ناوی بەکارهێنەر یان ئیمەیڵ پێشتر بەکارهاتووە';
        } else {
            // Create admin user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'admin')");
            
            try {
                $stmt->execute([$username, $email, $hashed_password]);
                $success = 'ئەدمین بە سەرکەوتوویی دروست کرا! ئێستا دەتوانی login بکەیت.';
            } catch (PDOException $e) {
                $error = 'خەتا لە دروستکردنی ئەدمین: ' . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>دروستکردنی ئەدمین</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
            margin: 20px;
        }

        h2 {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
            font-size: 28px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .error {
            background: #ffebee;
            color: #c62828;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #ffcdd2;
        }

        .success {
            background: #e8f5e8;
            color: #2e7d32;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #c8e6c9;
        }

        .warning {
            background: #fff3e0;
            color: #f57f17;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #ffcc02;
            text-align: center;
        }

        .links {
            text-align: center;
            margin-top: 20px;
        }

        .links a {
            color: #667eea;
            text-decoration: none;
            font-weight: bold;
        }

        .links a:hover {
            text-decoration: underline;
        }

        .admin-exists {
            background: #e3f2fd;
            color: #1976d2;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #90caf9;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>دروستکردنی ئەدمین</h2>
        
        <?php if ($admin_exists): ?>
            <div class="admin_exists">
                <strong>ئاگاداری:</strong> ئەدمین لە سیستەمدا هەیە. ئەگەر ناتوانیت login بکەیت، دەتوانیت ئەدمینێکی نوێ دروست بکەیت یان لەگەڵ database کار بکەیت.
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php else: ?>
            <form method="POST">
                <div class="form-group">
                    <label for="username">ناوی بەکارهێنەر:</label>
                    <input type="text" id="username" name="username" required 
                           value="<?php echo isset($_POST['username']) ? sanitizeInput($_POST['username']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="email">ئیمەیڵ:</label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo isset($_POST['email']) ? sanitizeInput($_POST['email']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">وشە نهێنی:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">دووبارەکردنەوەی وشە نهێنی:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <button type="submit" class="btn">دروستکردنی ئەدمین</button>
            </form>
        <?php endif; ?>
        
        <div class="links">
            <a href="login.php">چوونە ژوورەوە</a>
            <?php if (file_exists('index.php')): ?>
                | <a href="index.php">گەڕانەوە بۆ ماڵەوە</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>