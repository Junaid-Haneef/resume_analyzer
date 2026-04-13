<?php
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// Validate ID parameter
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    header('Location: ' . SITEURL);
    exit;
}

try {
    $pdo    = getPDO();
    $resume = getResumeById($id, $pdo);
} catch (PDOException $e) {
    $resume = null;
}

if (!$resume) {
    header('Location: ' . SITEURL);
    exit;
}

$data        = json_decode($resume['analysis_json'], true);
$score       = (int) $resume['score'];
$scoreInfo   = getScoreLabel($score);
$skills      = $data['skills']     ?? ['matched' => [], 'missing' => []];
$sections    = $data['sections']   ?? ['present' => [], 'missing' => []];
$contact     = $data['contact']    ?? ['has_email' => false, 'has_phone' => false];
$length      = $data['length']     ?? ['word_count' => 0, 'status' => ''];
$keywords    = $data['keywords']   ?? ['total' => 0, 'score' => 0];
$suggestions = $data['suggestions'] ?? [];

// Past analyses
try {
    $recent = getRecentAnalyses(10, $pdo);
} catch (PDOException $e) {
    $recent = [];
}

$h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resume Analysis Result &mdash; Resume Analyzer</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?php echo $h(SITEURL); ?>assets/css/style.css">
</head>
<body class="ra-body">

<!-- Navbar -->
<nav class="navbar navbar-expand-lg ra-navbar">
    <div class="container">
        <a class="navbar-brand ra-brand" href="<?php echo $h(SITEURL); ?>">
            <i class="bi bi-file-earmark-person-fill"></i>
            Resume Analyzer
            <span class="ra-brand-badge">v1.0</span>
        </a>
        <a href="<?php echo $h(SITEURL); ?>" class="btn btn-outline-light btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Analyze Another
        </a>
    </div>
</nav>

<main class="container py-5">

    <!-- ── Score Hero ─────────────────────────────────────────── -->
    <div class="row justify-content-center mb-5">
        <div class="col-lg-10">
            <div class="card ra-card ra-card-accent shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <div class="row align-items-center g-4">

                        <!-- Score circle -->
                        <div class="col-md-4 text-center">
                            <div class="ra-score-ring" style="--score-color: <?php echo $h($scoreInfo['color']); ?>">
                                <div class="ra-score-inner">
                                    <span class="ra-score-num"><?php echo $score; ?></span>
                                    <span class="ra-score-denom">/100</span>
                                    <span class="ra-score-label badge bg-<?php echo $h($scoreInfo['class']); ?>"><?php echo $h($scoreInfo['label']); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Score meta -->
                        <div class="col-md-8">
                            <h4 class="fw-bold mb-1">Analysis Complete</h4>
                            <p class="text-muted mb-3">
                                <?php echo $h(date('M j, Y g:i A', strtotime($resume['created_at']))); ?>
                                &bull;
                                <span class="text-muted"><?php echo (int) $length['word_count']; ?> words</span>
                            </p>
                            <!-- Stat pills -->
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <span class="badge ra-pill bg-success-subtle text-success-emphasis border border-success-subtle">
                                    <i class="bi bi-check-circle-fill me-1"></i><?php echo count($skills['matched']); ?> skills matched
                                </span>
                                <span class="badge ra-pill bg-danger-subtle text-danger-emphasis border border-danger-subtle">
                                    <i class="bi bi-x-circle-fill me-1"></i><?php echo count($skills['missing']); ?> skills missing
                                </span>
                                <span class="badge ra-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                                    <i class="bi bi-exclamation-circle-fill me-1"></i><?php echo count($sections['missing']); ?> sections missing
                                </span>
                                <span class="badge ra-pill bg-info-subtle text-info-emphasis border border-info-subtle">
                                    <i class="bi bi-keyboard-fill me-1"></i><?php echo (int) $keywords['total']; ?> action verbs
                                </span>
                            </div>
                            <!-- Skill match progress bar -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="small text-muted fw-semibold">Skill match</span>
                                    <span class="small fw-bold"><?php echo number_format((float)($skills['percentage'] ?? 0), 1); ?>%</span>
                                </div>
                                <div class="ra-match-bar-wrap">
                                    <div class="ra-match-bar" style="width:<?php echo min(100, (float)($skills['percentage'] ?? 0)); ?>%"></div>
                                </div>
                            </div>
                            <!-- Contact badges -->
                            <div class="d-flex gap-2">
                                <span class="badge <?php echo $contact['has_email'] ? 'bg-success' : 'bg-secondary'; ?>">
                                    <i class="bi bi-envelope-fill me-1"></i>
                                    Email <?php echo $contact['has_email'] ? 'found' : 'not found'; ?>
                                </span>
                                <span class="badge <?php echo $contact['has_phone'] ? 'bg-success' : 'bg-secondary'; ?>">
                                    <i class="bi bi-telephone-fill me-1"></i>
                                    Phone <?php echo $contact['has_phone'] ? 'found' : 'not found'; ?>
                                </span>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row justify-content-center g-4">
        <div class="col-lg-10">


            <div class="row g-4">

                <!-- Skills Found -->
                <div class="col-md-6">
                    <div class="card ra-card ra-card-accent-success shadow-sm h-100">
                        <div class="card-body p-4">
                            <h5 class="card-title mb-3">
                                <i class="bi bi-check2-circle text-success me-2"></i>Skills Matched
                                <span class="badge bg-success ms-1"><?php echo count($skills['matched']); ?></span>
                            </h5>
                            <?php if (!empty($skills['matched'])): ?>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($skills['matched'] as $skill): ?>
                                    <span class="badge ra-skill-badge bg-success-subtle text-success-emphasis border border-success-subtle">
                                        <i class="bi bi-check me-1"></i><?php echo $h($skill); ?>
                                    </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted small">No required skills detected.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Missing Skills -->
                <div class="col-md-6">
                    <div class="card ra-card ra-card-accent-danger shadow-sm h-100">
                        <div class="card-body p-4">
                            <h5 class="card-title mb-3">
                                <i class="bi bi-x-circle text-danger me-2"></i>Missing Skills
                                <span class="badge bg-danger ms-1"><?php echo count($skills['missing']); ?></span>
                            </h5>
                            <?php if (!empty($skills['missing'])): ?>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($skills['missing'] as $skill): ?>
                                    <span class="badge ra-skill-badge bg-danger-subtle text-danger-emphasis border border-danger-subtle">
                                        <i class="bi bi-x me-1"></i><?php echo $h($skill); ?>
                                    </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-success small"><i class="bi bi-check-circle-fill me-1"></i>All required skills found!</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Sections Present -->
                <div class="col-md-6">
                    <div class="card ra-card ra-card-accent-info shadow-sm h-100">
                        <div class="card-body p-4">
                            <h5 class="card-title mb-3"><i class="bi bi-layout-text-window-reverse me-2 text-primary"></i>Resume Sections</h5>
                            <p class="small text-muted mb-2">Present:</p>
                            <?php if (!empty($sections['present'])): ?>
                                <div class="d-flex flex-wrap gap-2 mb-3">
                                    <?php foreach ($sections['present'] as $sec): ?>
                                    <span class="badge ra-skill-badge bg-success-subtle text-success-emphasis border border-success-subtle">
                                        <i class="bi bi-check me-1"></i><?php echo $h(ucfirst($sec)); ?>
                                    </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted small">None detected.</p>
                            <?php endif; ?>
                            <p class="small text-muted mb-2">Missing:</p>
                            <?php if (!empty($sections['missing'])): ?>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($sections['missing'] as $sec): ?>
                                    <span class="badge ra-skill-badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                                        <i class="bi bi-exclamation me-1"></i><?php echo $h(ucfirst($sec)); ?>
                                    </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-success small"><i class="bi bi-check-circle-fill me-1"></i>All sections present!</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Keyword Analysis -->
                <div class="col-md-6">
                    <div class="card ra-card ra-card-accent-warning shadow-sm h-100">
                        <div class="card-body p-4">
                            <h5 class="card-title mb-3"><i class="bi bi-fonts me-2 text-info"></i>Action Verbs Found</h5>
                            <?php if (!empty($keywords['hits'])): ?>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($keywords['hits'] as $word => $count): ?>
                                    <span class="badge ra-skill-badge bg-info-subtle text-info-emphasis border border-info-subtle">
                                        <?php echo $h($word); ?> <span class="fw-bold">&times;<?php echo (int) $count; ?></span>
                                    </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted small">No action verbs detected. Try adding words like <em>developed, built, implemented</em>.</p>
                            <?php endif; ?>
                            <p class="text-muted small mt-3 mb-0">Score: <strong><?php echo (int) $keywords['score']; ?>/100</strong></p>
                        </div>
                    </div>
                </div>

            </div><!-- /row g-4 -->

            <!-- ── Suggestions ─────────────────────────────────────── -->
            <div class="card ra-card ra-card-accent-purple shadow-sm mt-4">
                <div class="card-body p-4">
                    <h5 class="card-title mb-3">
                        <i class="bi bi-lightbulb-fill text-warning me-2"></i>Improvement Suggestions
                    </h5>
                    <div>
                        <?php foreach ($suggestions as $i => $suggestion): ?>
                        <div class="ra-suggestion-item">
                            <span class="ra-suggestion-num"><?php echo $i + 1; ?></span>
                            <span class="small"><?php echo $suggestion; ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- ── Past Analyses ───────────────────────────────────── -->
            <?php if (!empty($recent)): ?>
            <div class="card ra-card shadow-sm mt-4">
                <div class="card-body p-4">
                    <h5 class="card-title mb-3"><i class="bi bi-clock-history me-2 text-secondary"></i>Recent Analyses</h5>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 ra-table">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Role</th>
                                    <th>Score</th>
                                    <th>Date</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent as $row): ?>
                                <tr <?php echo $row['id'] === $id ? 'class="table-primary"' : ''; ?>>
                                    <td class="text-muted small"><?php echo (int) $row['id']; ?></td>
                                <td><?php echo $row['role_name'] ? $h($row['role_name']) : '<span class="text-muted fst-italic">Custom</span>'; ?></td>
                                    <td>
                                        <?php
                                        $s    = (int) $row['score'];
                                        $info = getScoreLabel($s);
                                        ?>
                                        <span class="badge bg-<?php echo $h($info['class']); ?>"><?php echo $s; ?>/100</span>
                                    </td>
                                    <td class="text-muted small"><?php echo $h(date('M j, Y g:i A', strtotime($row['created_at']))); ?></td>
                                    <td>
                                        <a href="result.php?id=<?php echo (int) $row['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div><!-- /row -->

</main>

<footer class="ra-footer">
    <p class="mb-1 fw-semibold" style="color:rgba(255,255,255,0.75)">&copy; <?php echo date('Y'); ?> Resume Analyzer &mdash; College Project</p>
    <p class="mb-0" style="font-size:0.75rem;">Built with PHP, MySQL &amp; Bootstrap 5</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
