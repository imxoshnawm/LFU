<?php
require_once 'config.php';
requireLogin();

// Get voting status
$stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_name = 'voting_enabled'");
$stmt->execute();
$voting_enabled = $stmt->fetchColumn() == '1';

// Get all projects with their members and vote counts
$stmt = $pdo->prepare("
    SELECT p.*, 
           GROUP_CONCAT(DISTINCT u.username SEPARATOR ', ') as members,
           COUNT(DISTINCT v.id) as vote_count,
           AVG(v.rating) as avg_rating,
           COUNT(DISTINCT pi.id) as image_count
    FROM projects p
    LEFT JOIN project_members pm ON p.id = pm.project_id
    LEFT JOIN users u ON pm.user_id = u.id
    LEFT JOIN votes v ON p.id = v.project_id
    LEFT JOIN project_images pi ON p.id = pi.project_id
    GROUP BY p.id
    ORDER BY avg_rating DESC, vote_count DESC
");
$stmt->execute();
$projects = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبۆرد - سیستەمی بەڕێوەبردنی پڕۆژە</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
        }
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .project-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 20px;
        }
        .project-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .project-image {
            height: 200px;
            object-fit: cover;
            border-radius: 10px;
        }
        .rating-stars {
            color: #ffc107;
        }
        .voting-status {
            position: fixed;
            top: 80px;
            left: 20px;
            z-index: 1000;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .badge-success {
            background: #28a745;
        }
        .badge-warning {
            background: #ffc107;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-project-diagram me-2"></i>
                سیستەمی پڕۆژەکان
            </a>
            
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    بەخێرهاتیت، <?= $_SESSION['username'] ?>
                    <?php if (isAdmin()): ?>
                        <span class="badge bg-light text-dark">ئەدمین</span>
                    <?php endif; ?>
                </span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> دەرچوون
                </a>
            </div>
        </div>
    </nav>

    <!-- Voting Status Alert -->
    <?php if (!isAdmin()): ?>
        <div class="voting-status">
            <div class="alert alert-<?= $voting_enabled ? 'success' : 'warning' ?> alert-dismissible fade show">
                <strong>
                    <i class="fas fa-vote-yea"></i>
                    <?= $voting_enabled ? 'دەنگدان ئەکتیڤە!' : 'دەنگدان ئەکتیڤ نیە' ?>
                </strong>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>

    <div class="container mt-4">
        <!-- Action Buttons -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h2>پڕۆژەکان</h2>
                    <div>
                        <?php if (isAdmin()): ?>
                            <a href="add-user.php" class="btn btn-success me-2">
                                <i class="fas fa-user-plus"></i> زیادکردنی بەکارهێنەر
                            </a>
                            <a href="admin-panel.php" class="btn btn-warning me-2">
                                <i class="fas fa-cog"></i> پانێڵی ئەدمین
                            </a>
                        <?php endif; ?>
                        <a href="add-project.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> زیادکردنی پڕۆژە
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Projects Grid -->
        <div class="row">
            <?php if (empty($projects)): ?>
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">هیچ پڕۆژەیەک نییە</h4>
                        <p class="text-muted">یەکەم پڕۆژە زیاد بکە</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($projects as $index => $project): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card project-card h-100">
                            <!-- Project Rank -->
                            <div class="position-absolute" style="top: 10px; left: 10px; z-index: 10;">
                                <?php if ($index == 0 && $project['avg_rating']): ?>
                                    <span class="badge bg-warning">🏆 یەکەم</span>
                                <?php elseif ($index == 1 && $project['avg_rating']): ?>
                                    <span class="badge bg-light text-dark">🥈 دووەم</span>
                                <?php elseif ($index == 2 && $project['avg_rating']): ?>
                                    <span class="badge bg-info">🥉 سێیەم</span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Project Image -->
                            <?php
                            $stmt = $pdo->prepare("SELECT image_path FROM project_images WHERE project_id = ? LIMIT 1");
                            $stmt->execute([$project['id']]);
                            $image = $stmt->fetchColumn();
                            ?>
                            
                            <?php if ($image): ?>
                                <img src="<?= UPLOAD_PATH . $image ?>" class="card-img-top project-image" alt="وێنەی پڕۆژە">
                            <?php else: ?>
                                <div class="card-img-top project-image bg-light d-flex align-items-center justify-content-center">
                                    <i class="fas fa-image fa-3x text-muted"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title"><?= htmlspecialchars($project['title']) ?></h5>
                                <p class="card-text flex-grow-1">
                                    <?= mb_substr(htmlspecialchars($project['description']), 0, 150) ?>...
                                </p>
                                
                                <div class="mb-2">
                                    <strong>ئەندامان:</strong> 
                                    <span class="text-muted"><?= $project['members'] ?: 'هیچ ئەندامێک' ?></span>
                                </div>
                                
                                <?php if ($project['image_count'] > 1): ?>
                                    <div class="mb-2">
                                        <small class="text-muted">
                                            <i class="fas fa-images"></i> <?= $project['image_count'] ?> وێنە
                                        </small>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Rating Display -->
                                <?php if ($project['avg_rating']): ?>
                                    <div class="mb-2">
                                        <div class="rating-stars">
                                            <?php 
                                            $rating = round($project['avg_rating']);
                                            for ($i = 1; $i <= 5; $i++): 
                                            ?>
                                                <i class="fas fa-star<?= $i <= $rating ? '' : ' text-muted' ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <small class="text-muted">
                                            (<?= number_format($project['avg_rating'], 1) ?>/5 - <?= $project['vote_count'] ?> دەنگ)
                                        </small>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mt-auto">
                                    <div class="d-flex gap-2">
                                        <a href="view-project.php?id=<?= $project['id'] ?>" class="btn btn-outline-primary btn-sm flex-grow-1">
                                            <i class="fas fa-eye"></i> بینین
                                        </a>
                                        <?php if ($voting_enabled && $project['is_active_for_voting']): ?>
                                            <a href="vote.php?id=<?= $project['id'] ?>" class="btn btn-success btn-sm">
                                                <i class="fas fa-vote-yea"></i> دەنگ
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>