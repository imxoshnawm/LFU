<?php
require_once 'config.php';
requireLogin();

$error = '';
$success = '';

// Check if voting is enabled
$stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_name = 'voting_enabled'");
$stmt->execute();
$voting_enabled = $stmt->fetchColumn() == '1';

if (!$voting_enabled) {
    header('Location: dashboard.php');
    exit;
}

$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$project_id) {
    header('Location: dashboard.php');
    exit;
}

// Get project details
$stmt = $pdo->prepare("
    SELECT p.*, 
           GROUP_CONCAT(u.username SEPARATOR ', ') as members,
           p.is_active_for_voting
    FROM projects p
    LEFT JOIN project_members pm ON p.id = pm.project_id
    LEFT JOIN users u ON pm.user_id = u.id
    WHERE p.id = ?
    GROUP BY p.id
");
$stmt->execute([$project_id]);
$project = $stmt->fetch();

if (!$project || !$project['is_active_for_voting']) {
    $error = 'ئەم پڕۆژەیە بۆ دەنگدان ئەکتیڤ نیە';
}

// Check if user already voted for this project
$stmt = $pdo->prepare("SELECT rating FROM votes WHERE voter_id = ? AND project_id = ?");
$stmt->execute([$_SESSION['user_id'], $project_id]);
$existing_vote = $stmt->fetch();

// Check if user is a member of this project
$stmt = $pdo->prepare("SELECT id FROM project_members WHERE user_id = ? AND project_id = ?");
$stmt->execute([$_SESSION['user_id'], $project_id]);
$is_member = $stmt->rowCount() > 0;

if ($is_member) {
    $error = 'ناتوانیت دەنگ بە پڕۆژەی خۆت بدەیت';
}

// Handle vote submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$error && !$existing_vote && !$is_member) {
    $rating = (int)$_POST['rating'];
    
    if ($rating >= 1 && $rating <= 5) {
        try {
            $stmt = $pdo->prepare("INSERT INTO votes (voter_id, project_id, rating) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $project_id, $rating]);
            
            $success = 'دەنگەکەت بە سەرکەوتوویی تۆمار کرا!';
            
            // Refresh to show new vote
            header("Refresh: 2; url=dashboard.php");
            
        } catch (Exception $e) {
            $error = 'خەتا لە تۆمارکردنی دەنگەکەدا: ' . $e->getMessage();
        }
    } else {
        $error = 'تکایە نرخاندنێکی گونجاو هەڵبژێرە (1-5)';
    }
}

// Get project images
$stmt = $pdo->prepare("SELECT image_path FROM project_images WHERE project_id = ?");
$stmt->execute([$project_id]);
$images = $stmt->fetchAll();

// Get current votes for this project
$stmt = $pdo->prepare("
    SELECT COUNT(*) as vote_count, AVG(rating) as avg_rating 
    FROM votes 
    WHERE project_id = ?
");
$stmt->execute([$project_id]);
$vote_stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>دەنگدان - <?= htmlspecialchars($project['title'] ?? 'پڕۆژە') ?></title>
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
                    دەنگدان - <?= $_SESSION['username'] ?>
                </span>
                <a href="dashboard.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-arrow-right"></i> گەڕانەوە
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
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

        <?php if ($project && !$error): ?>
            <div class="row">
                <!-- Project Details -->
                <div class="col-lg-8">
                    <div class="card project-card">
                        <div class="card-header bg-white">
                            <h3 class="mb-0"><?= htmlspecialchars($project['title']) ?></h3>
                            <p class="text-muted mb-0">
                                <i class="fas fa-users me-1"></i>
                                <strong>ئەندامان:</strong> <?= $project['members'] ?: 'هیچ ئەندامێک' ?>
                            </p>
                        </div>
                        
                        <div class="card-body">
                            <!-- Project Images -->
                            <?php if (!empty($images)): ?>
                                <div class="mb-4">
                                    <?php if (count($images) == 1): ?>
                                        <img src="<?= UPLOAD_PATH . $images[0]['image_path'] ?>" 
                                             class="img-fluid project-image w-100" alt="وێنەی پڕۆژە">
                                    <?php else: ?>
                                        <div id="projectCarousel" class="carousel slide" data-bs-ride="carousel">
                                            <div class="carousel-inner image-carousel">
                                                <?php foreach ($images as $index => $image): ?>
                                                    <div class="carousel-item <?= $index == 0 ? 'active' : '' ?>">
                                                        <img src="<?= UPLOAD_PATH . $image['image_path'] ?>" 
                                                             class="d-block w-100" alt="وێنەی پڕۆژە">
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <button class="carousel-control-prev" type="button" data-bs-target="#projectCarousel" data-bs-slide="prev">
                                                <span class="carousel-control-prev-icon"></span>
                                            </button>
                                            <button class="carousel-control-next" type="button" data-bs-target="#projectCarousel" data-bs-slide="next">
                                                <span class="carousel-control-next-icon"></span>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Project Description -->
                            <h5>وەسفی پڕۆژە:</h5>
                            <p class="text-justify"><?= nl2br(htmlspecialchars($project['description'])) ?></p>
                            
                            <!-- Current Rating -->
                            <?php if ($vote_stats['vote_count'] > 0): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-chart-bar me-2"></i>
                                    <strong>نرخاندنی ئێستا:</strong> 
                                    <?= number_format($vote_stats['avg_rating'], 1) ?>/5 
                                    (<?= $vote_stats['vote_count'] ?> دەنگ)
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Voting Section -->
                <div class="col-lg-4">
                    <div class="card vote-card">
                        <div class="card-body text-center">
                            <h4 class="mb-4">
                                <i class="fas fa-vote-yea me-2"></i>
                                دەنگدان
                            </h4>
                            
                            <?php if ($existing_vote): ?>
                                <div class="alert alert-light">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    تۆ پێشتر دەنگت داوە!
                                    <br>
                                    <strong>نرخاندنەکەت: <?= $existing_vote['rating'] ?>/5</strong>
                                </div>
                            <?php elseif ($is_member): ?>
                                <div class="alert alert-light">
                                    <i class="fas fa-info-circle text-info me-2"></i>
                                    ناتوانیت دەنگ بە پڕۆژەی خۆت بدەیت
                                </div>
                            <?php else: ?>
                                <form method="POST" id="voteForm">
                                    <p class="mb-4">تکایە نرخاندنەکەت دیاری بکە:</p>
                                    
                                    <div class="rating-container mb-4">
                                        <input type="hidden" name="rating" id="ratingInput" value="">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star rating-stars" data-rating="<?= $i ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    
                                    <div id="ratingText" class="mb-3 text-light"></div>
                                    
                                    <button type="submit" class="btn btn-light btn-lg w-100" id="submitBtn" disabled>
                                        <i class="fas fa-paper-plane me-2"></i>
                                        ناردنی دەنگ
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <div class="mt-4">
                                <a href="dashboard.php" class="btn btn-outline-light">
                                    <i class="fas fa-arrow-right me-2"></i>
                                    گەڕانەوە بۆ پڕۆژەکان
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Rating system
        const stars = document.querySelectorAll('.rating-stars');
        const ratingInput = document.getElementById('ratingInput');
        const ratingText = document.getElementById('ratingText');
        const submitBtn = document.getElementById('submitBtn');
        
        const ratingTexts = {
            1: 'زۆر خراپ',
            2: 'خراپ',
            3: 'مامناوەند',
            4: 'باش',
            5: 'زۆر باش'
        };
        
        stars.forEach((star, index) => {
            star.addEventListener('mouseover', () => {
                highlightStars(index + 1);
            });
            
            star.addEventListener('mouseout', () => {
                const currentRating = parseInt(ratingInput.value) || 0;
                highlightStars(currentRating);
            });
            
            star.addEventListener('click', () => {
                const rating = index + 1;
                ratingInput.value = rating;
                ratingText.textContent = ratingTexts[rating];
                submitBtn.disabled = false;
                highlightStars(rating);
            });
        });
        
        function highlightStars(rating) {
            stars.forEach((star, index) => {
                if (index < rating) {
                    star.classList.add('selected');
                    star.classList.remove('hover');
                } else {
                    star.classList.remove('selected', 'hover');
                }
            });
        }
    </script>
</body>
</html>