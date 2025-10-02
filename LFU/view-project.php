<?php
require_once 'config.php';
requireLogin();

$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$project_id) {
    header('Location: dashboard.php');
    exit;
}

// Get project details with members and statistics
$stmt = $pdo->prepare("
    SELECT p.*, 
           GROUP_CONCAT(u.username SEPARATOR ', ') as members,
           COUNT(DISTINCT v.id) as vote_count,
           AVG(v.rating) as avg_rating
    FROM projects p
    LEFT JOIN project_members pm ON p.id = pm.project_id
    LEFT JOIN users u ON pm.user_id = u.id
    LEFT JOIN votes v ON p.id = v.project_id
    WHERE p.id = ?
    GROUP BY p.id
");
$stmt->execute([$project_id]);
$project = $stmt->fetch();

if (!$project) {
    header('Location: dashboard.php');
    exit;
}

// Get project images
$stmt = $pdo->prepare("SELECT image_path FROM project_images WHERE project_id = ? ORDER BY uploaded_at");
$stmt->execute([$project_id]);
$images = $stmt->fetchAll();

// Get voting breakdown
$stmt = $pdo->prepare("
    SELECT rating, COUNT(*) as count 
    FROM votes 
    WHERE project_id = ? 
    GROUP BY rating 
    ORDER BY rating DESC
");
$stmt->execute([$project_id]);
$vote_breakdown = $stmt->fetchAll();

// Check if current user is a member
$stmt = $pdo->prepare("SELECT id FROM project_members WHERE user_id = ? AND project_id = ?");
$stmt->execute([$_SESSION['user_id'], $project_id]);
$is_member = $stmt->rowCount() > 0;

// Check if current user has voted
$stmt = $pdo->prepare("SELECT rating FROM votes WHERE voter_id = ? AND project_id = ?");
$stmt->execute([$_SESSION['user_id'], $project_id]);
$user_vote = $stmt->fetch();

// Get voting status
$stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_name = 'voting_enabled'");
$stmt->execute();
$voting_enabled = $stmt->fetchColumn() == '1';
?>
<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($project['title']) ?> - سیستەمی بەڕێوەبردنی پڕۆژە</title>
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
                    بینینی پڕۆژە - <?= $_SESSION['username'] ?>
                </span>
                <a href="dashboard.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-arrow-right"></i> گەڕانەوە
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <!-- Project Details -->
            <div class="col-lg-8">
                <div class="card project-card">
                    <div class="card-header bg-white">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h2 class="mb-2"><?= htmlspecialchars($project['title']) ?></h2>
                                <p class="text-muted mb-0">
                                    <i class="fas fa-users me-1"></i>
                                    <strong>ئەندامان:</strong> <?= $project['members'] ?: 'هیچ ئەندامێک' ?>
                                    <?php if ($is_member): ?>
                                        <span class="badge bg-success ms-2">تۆ ئەندامی ئەم پڕۆژەیەیت</span>
                                    <?php endif; ?>
                                </p>
                                <small class="text-muted">
                                    <i class="fas fa-calendar me-1"></i>
                                    دروستکراوە لە: <?= date('Y/m/d H:i', strtotime($project['created_at'])) ?>
                                </small>
                            </div>
                            <?php if ($voting_enabled && $project['is_active_for_voting'] && !$is_member && !$user_vote): ?>
                                <a href="vote.php?id=<?= $project['id'] ?>" class="btn btn-success">
                                    <i class="fas fa-vote-yea"></i> دەنگدان
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <!-- Project Images -->
                        <?php if (!empty($images)): ?>
                            <div class="mb-4">
                                <?php if (count($images) == 1): ?>
                                    <img src="<?= UPLOAD_PATH . $images[0]['image_path'] ?>" 
                                         class="img-fluid project-image w-100" alt="وێنەی پڕۆژە"
                                         data-bs-toggle="modal" data-bs-target="#imageModal"
                                         data-bs-src="<?= UPLOAD_PATH . $images[0]['image_path'] ?>">
                                <?php else: ?>
                                    <div id="projectCarousel" class="carousel slide mb-3" data-bs-ride="carousel">
                                        <div class="carousel-inner">
                                            <?php foreach ($images as $index => $image): ?>
                                                <div class="carousel-item <?= $index == 0 ? 'active' : '' ?>">
                                                    <img src="<?= UPLOAD_PATH . $image['image_path'] ?>" 
                                                         class="d-block w-100 project-image" alt="وێنەی پڕۆژە"
                                                         data-bs-toggle="modal" data-bs-target="#imageModal"
                                                         data-bs-src="<?= UPLOAD_PATH . $image['image_path'] ?>">
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
                                    
                                    <!-- Thumbnail Gallery -->
                                    <div class="row image-gallery">
                                        <?php foreach ($images as $image): ?>
                                            <div class="col-md-3 mb-2">
                                                <img src="<?= UPLOAD_PATH . $image['image_path'] ?>" 
                                                     class="img-fluid" alt="وێنەی پڕۆژە"
                                                     data-bs-toggle="modal" data-bs-target="#imageModal"
                                                     data-bs-src="<?= UPLOAD_PATH . $image['image_path'] ?>">
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Project Description -->
                        <h5>وەسفی پڕۆژە:</h5>
                        <div class="bg-light p-3 rounded">
                            <p class="mb-0 text-justify"><?= nl2br(htmlspecialchars($project['description'])) ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Statistics and Voting -->
            <div class="col-lg-4">
                <!-- Rating Statistics -->
                <?php if ($project['vote_count'] > 0): ?>
                    <div class="card stats-card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-bar me-2"></i>
                                ئامارەکانی نرخاندن
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-3">
                                <h2 class="text-primary"><?= number_format($project['avg_rating'], 1) ?></h2>
                                <div class="rating-stars mb-2">
                                    <?php 
                                    $rating = round($project['avg_rating']);
                                    for ($i = 1; $i <= 5; $i++): 
                                    ?>
                                        <i class="fas fa-star<?= $i <= $rating ? '' : ' text-muted' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <p class="text-muted mb-0"><?= $project['vote_count'] ?> دەنگ</p>
                            </div>
                            
                            <!-- Rating Breakdown -->
                            <div class="mt-3">
                                <?php 
                                $rating_counts = array_fill(1, 5, 0);
                                foreach ($vote_breakdown as $vote) {
                                    $rating_counts[$vote['rating']] = $vote['count'];
                                }
                                ?>
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="me-2"><?= $i ?></span>
                                        <i class="fas fa-star text-warning me-2"></i>
                                        <div class="progress flex-grow-1 me-2">
                                            <div class="progress-bar bg-warning" 
                                                 style="width: <?= $project['vote_count'] > 0 ? ($rating_counts[$i] / $project['vote_count']) * 100 : 0 ?>%">
                                            </div>
                                        </div>
                                        <small class="text-muted"><?= $rating_counts[$i] ?></small>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- User's Vote Status -->
                <div class="card stats-card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="fas fa-vote-yea me-2"></i>
                            حاڵەتی دەنگدان
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!$voting_enabled): ?>
                            <div class="alert alert-warning mb-0">
                                <i class="fas fa-pause-circle me-2"></i>
                                دەنگدان ئێستا ئەکتیڤ نیە
                            </div>
                        <?php elseif (!$project['is_active_for_voting']): ?>
                            <div class="alert alert-info mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                ئەم پڕۆژەیە بۆ دەنگدان ئەکتیڤ نەکراوە
                            </div>
                        <?php elseif ($is_member): ?>
                            <div class="alert alert-secondary mb-0">
                                <i class="fas fa-user-check me-2"></i>
                                وەک ئەندامی پڕۆژە، ناتوانیت دەنگ بدەیت
                            </div>
                        <?php elseif ($user_vote): ?>
                            <div class="alert alert-success mb-0">
                                <i class="fas fa-check-circle me-2"></i>
                                تۆ دەنگت داوە!<br>
                                <strong>نرخاندنەکەت: <?= $user_vote['rating'] ?>/5</strong>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-primary mb-3">
                                <i class="fas fa-vote-yea me-2"></i>
                                دەتوانیت دەنگ بەم پڕۆژەیە بدەیت
                            </div>
                            <a href="vote.php?id=<?= $project['id'] ?>" class="btn btn-primary w-100">
                                <i class="fas fa-vote-yea me-2"></i>
                                دەنگدان
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Project Info -->
                <div class="card stats-card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            زانیاریەکانی پڕۆژە
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6">
                                <h4 class="text-primary"><?= count($images) ?></h4>
                                <small class="text-muted">وێنە</small>
                            </div>
                            <div class="col-6">
                                <h4 class="text-success"><?= $project['vote_count'] ?></h4>
                                <small class="text-muted">دەنگ</small>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="text-center">
                            <small class="text-muted">
                                <i class="fas fa-calendar me-1"></i>
                                دروستکراوە لە:<br>
                                <?= date('Y/m/d H:i', strtotime($project['created_at'])) ?>
                            </small>
                        </div>
                        
                        <?php if ($project['updated_at'] != $project['created_at']): ?>
                            <div class="text-center mt-2">
                                <small class="text-muted">
                                    <i class="fas fa-edit me-1"></i>
                                    دواین نوێکردنەوە:<br>
                                    <?= date('Y/m/d H:i', strtotime($project['updated_at'])) ?>
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Back Button -->
                <div class="text-center">
                    <a href="dashboard.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-right me-2"></i>
                        گەڕانەوە بۆ پڕۆژەکان
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">وێنەی پڕۆژە</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" src="" class="img-fluid" alt="وێنەی پڕۆژە">
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle image modal
        document.getElementById('imageModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const src = button.getAttribute('data-bs-src');
            const modalImage = document.getElementById('modalImage');
            modalImage.src = src;
        });
    </script>
</body>
</html>