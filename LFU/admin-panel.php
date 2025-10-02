<?php
require_once 'config.php';
requireAdmin();

$success = '';
$error = '';

// Handle voting status toggle
if (isset($_POST['toggle_voting'])) {
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_name = 'voting_enabled'");
    $stmt->execute();
    $current_status = $stmt->fetchColumn();
    
    $new_status = $current_status == '1' ? '0' : '1';
    
    $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_name = 'voting_enabled'");
    $stmt->execute([$new_status]);
    
    $success = $new_status == '1' ? 'دەنگدان ئەکتیڤ کرا!' : 'دەنگدان ناەکتیڤ کرا!';
}

// Handle project activation for voting
if (isset($_POST['toggle_project_voting'])) {
    $project_id = $_POST['project_id'];
    $stmt = $pdo->prepare("UPDATE projects SET is_active_for_voting = NOT is_active_for_voting WHERE id = ?");
    $stmt->execute([$project_id]);
    $success = 'حاڵەتی دەنگدانی پڕۆژە گۆڕدرا!';
}

// Get voting status
$stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_name = 'voting_enabled'");
$stmt->execute();
$voting_enabled = $stmt->fetchColumn() == '1';

// Get all projects with voting status
$stmt = $pdo->prepare("
    SELECT p.*, 
           GROUP_CONCAT(u.username SEPARATOR ', ') as members,
           COUNT(DISTINCT v.id) as vote_count,
           AVG(v.rating) as avg_rating
    FROM projects p
    LEFT JOIN project_members pm ON p.id = pm.project_id
    LEFT JOIN users u ON pm.user_id = u.id
    LEFT JOIN votes v ON p.id = v.project_id
    GROUP BY p.id
    ORDER BY p.created_at DESC
");
$stmt->execute();
$projects = $stmt->fetchAll();

// Get ranking
$stmt = $pdo->prepare("
    SELECT p.*, 
           GROUP_CONCAT(u.username SEPARATOR ', ') as members,
           COUNT(DISTINCT v.id) as vote_count,
           AVG(v.rating) as avg_rating
    FROM projects p
    LEFT JOIN project_members pm ON p.id = pm.project_id
    LEFT JOIN users u ON pm.user_id = u.id
    LEFT JOIN votes v ON p.id = v.project_id
    GROUP BY p.id
    HAVING avg_rating IS NOT NULL
    ORDER BY avg_rating DESC, vote_count DESC
");
$stmt->execute();
$rankings = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'");
$total_students = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM projects");
$total_projects = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM votes");
$total_votes = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پانێڵی ئەدمین - سیستەمی بەڕێوەبردنی پڕۆژە</title>
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
                    پانێڵی ئەدمین - <?= $_SESSION['username'] ?>
                </span>
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
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?= $success ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card bg-primary text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-users stats-icon"></i>
                        <h3 class="mt-2"><?= $total_students ?></h3>
                        <p class="mb-0">قوتابی</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card bg-success text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-project-diagram stats-icon"></i>
                        <h3 class="mt-2"><?= $total_projects ?></h3>
                        <p class="mb-0">پڕۆژە</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card bg-warning text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-vote-yea stats-icon"></i>
                        <h3 class="mt-2"><?= $total_votes ?></h3>
                        <p class="mb-0">دەنگ</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card bg-info text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-<?= $voting_enabled ? 'toggle-on' : 'toggle-off' ?> stats-icon"></i>
                        <h5 class="mt-2"><?= $voting_enabled ? 'ئەکتیڤە' : 'ناەکتیڤە' ?></h5>
                        <p class="mb-0">دەنگدان</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Voting Control -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-cog me-2"></i>
                            کۆنترۆڵی دەنگدان
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="d-inline">
                            <button type="submit" name="toggle_voting" class="btn btn-<?= $voting_enabled ? 'danger' : 'success' ?> btn-lg">
                                <i class="fas fa-<?= $voting_enabled ? 'stop' : 'play' ?> me-2"></i>
                                <?= $voting_enabled ? 'ڕاگرتنی دەنگدان' : 'دەستپێکردنی دەنگدان' ?>
                            </button>
                        </form>
                        <p class="mt-2 mb-0 text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            کاتێک دەنگدان ئەکتیڤ بکەیت، قوتابیان دەتوانن دەنگ بە پڕۆژەکان بدەن
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Projects Management -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            بەڕێوەبردنی پڕۆژەکان
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($projects)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">هیچ پڕۆژەیەک نییە</h5>
                            </div>
                        <?php else: ?>
                            <?php foreach ($projects as $project): ?>
                                <div class="card project-card">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-md-6">
                                                <h6 class="mb-1"><?= htmlspecialchars($project['title']) ?></h6>
                                                <small class="text-muted">
                                                    <strong>ئەندامان:</strong> <?= $project['members'] ?: 'هیچ ئەندامێک' ?>
                                                </small><br>
                                                <?php if ($project['avg_rating']): ?>
                                                    <small class="text-muted">
                                                        <strong>نرخاندن:</strong> <?= number_format($project['avg_rating'], 1) ?>/5 
                                                        (<?= $project['vote_count'] ?> دەنگ)
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-6 text-end">
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="project_id" value="<?= $project['id'] ?>">
                                                    <button type="submit" name="toggle_project_voting" 
                                                            class="btn btn-<?= $project['is_active_for_voting'] ? 'success' : 'outline-secondary' ?> btn-sm">
                                                        <i class="fas fa-<?= $project['is_active_for_voting'] ? 'check' : 'times' ?> me-1"></i>
                                                        <?= $project['is_active_for_voting'] ? 'ئەکتیڤە' : 'ناەکتیڤە' ?>
                                                    </button>
                                                </form>
                                                <a href="view-project.php?id=<?= $project['id'] ?>" class="btn btn-outline-primary btn-sm ms-2">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Rankings -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-trophy me-2"></i>
                            ڕیزبەندی کۆتایی
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($rankings)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-trophy fa-2x text-muted mb-2"></i>
                                <p class="text-muted mb-0">هێشتا هیچ دەنگێک نەدراوە</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($rankings as $index => $project): ?>
                                <div class="d-flex align-items-center mb-3 p-2 <?= $index < 3 ? 'bg-light rounded' : '' ?>">
                                    <div class="me-3">
                                        <?php if ($index == 0): ?>
                                            <span class="badge bg-warning ranking-badge">🏆</span>
                                        <?php elseif ($index == 1): ?>
                                            <span class="badge bg-secondary ranking-badge">🥈</span>
                                        <?php elseif ($index == 2): ?>
                                            <span class="badge bg-info ranking-badge">🥉</span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark ranking-badge"><?= $index + 1 ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-0"><?= htmlspecialchars($project['title']) ?></h6>
                                        <small class="text-muted">
                                            <?= number_format($project['avg_rating'], 1) ?>/5 - <?= $project['vote_count'] ?> دەنگ
                                        </small>
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
</body>
</html>