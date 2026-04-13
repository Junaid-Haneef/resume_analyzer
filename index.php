<?php
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

try {
    $pdo       = getPDO();
    $allSkills = getAllSkills($pdo);
} catch (PDOException $e) {
    $allSkills = [];
    $dbError   = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resume Analyzer</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(SITEURL, ENT_QUOTES, 'UTF-8'); ?>assets/css/style.css">
</head>
<body class="ra-body">

<nav class="navbar navbar-expand-lg ra-navbar">
    <div class="container">
        <a class="navbar-brand ra-brand" href="<?php echo htmlspecialchars(SITEURL, ENT_QUOTES, 'UTF-8'); ?>">
            <i class="bi bi-file-earmark-person-fill"></i>
            Resume Analyzer
            <span class="ra-brand-badge">v1.0</span>
        </a>
    </div>
</nav>

<main class="container py-5">

    <!-- Hero -->
    <div class="text-center mb-5">
        <div class="ra-hero-eyebrow">
            <i class="bi bi-stars"></i> Smart Resume Analysis Tool
        </div>
        <h1 class="ra-hero-title">Analyze Your Resume</h1>
        <p class="ra-hero-sub">Upload your resume, pick the skills to check, and instantly see your match score, skill gaps, and actionable improvement tips.</p>
    </div>

    <?php if (!empty($dbError)): ?>
    <div class="alert alert-danger text-center">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        Could not connect to the database. Please ensure MySQL is running and the <code>resume_analyzer</code> database is imported.
    </div>
    <?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-lg-7 col-md-9">

            <!-- Upload Card -->
            <div class="card ra-card ra-card-accent shadow-sm">
                <div class="card-body p-4 p-md-5">

                    <h5 class="card-title mb-4"><i class="bi bi-upload me-2 text-primary"></i>Upload Your Resume</h5>

                    <!-- Error Alert -->
                    <div id="alertBox" class="alert alert-danger d-none" role="alert">
                        <i class="bi bi-exclamation-circle-fill me-2"></i>
                        <span id="alertMsg"></span>
                    </div>

                    <form id="resumeForm" enctype="multipart/form-data" novalidate>

                        <!-- File Input -->
                        <div class="mb-4">
                            <div class="ra-step-label"><span class="ra-step-num">1</span> Upload Resume</div>
                            <label for="resumeFile" class="form-label fw-semibold">Resume File</label>
                            <div class="ra-drop-zone" id="dropZone">
                                <i class="bi bi-cloud-arrow-up-fill ra-drop-icon"></i>
                                <p class="mb-1 fw-semibold">Drag &amp; drop your file here</p>
                                <p class="text-muted small mb-3">or click to browse</p>
                                <input class="form-control ra-file-input" type="file" id="resumeFile" name="resume"
                                       accept=".pdf,.docx,.txt" required>
                                <div id="fileLabel" class="mt-2 text-muted small d-none">
                                    <i class="bi bi-file-earmark-check-fill text-success me-1"></i>
                                    <span id="fileName"></span>
                                </div>
                            </div>
                            <div class="form-text">Accepted: PDF, DOCX, TXT &bull; Max size: 2 MB</div>
                        </div>

                        <!-- Skill Picker -->
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <div>
                                    <div class="ra-step-label"><span class="ra-step-num">2</span> Select Skills</div>
                                    <label class="form-label fw-semibold mb-0">Skills to Analyse</label>
                                </div>
                                <button type="button" class="btn btn-outline-secondary btn-sm mt-1"
                                        data-bs-toggle="modal" data-bs-target="#manageSkillsModal">
                                    <i class="bi bi-gear me-1"></i>Manage Skills
                                </button>
                            </div>
                            <p class="form-text mb-2">Click a skill to add it to your list. Click &times; on a tag to remove it.</p>

                            <!-- Available skills pool -->
                            <?php if (!empty($allSkills)): ?>
                            <div class="ra-skill-pool p-3 rounded border mb-3 d-flex flex-wrap gap-2" id="skillPool">
                                <?php foreach ($allSkills as $skill): ?>
                                <span class="badge ra-skill-option"
                                      data-id="<?php echo (int) $skill['id']; ?>"
                                      data-name="<?php echo htmlspecialchars($skill['skill_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                      role="button" tabindex="0">
                                    <i class="bi bi-plus me-1"></i><?php echo htmlspecialchars($skill['skill_name'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-warning py-2">No skills found &mdash; check DB connection.</div>
                            <?php endif; ?>

                            <!-- Selected tags -->
                            <div class="ra-selected-area-label mt-2">Selected Skills</div>
                            <div class="ra-selected-area d-flex flex-wrap gap-2 align-items-center" id="selectedSkillsContainer">
                                <span class="text-muted fst-italic small" id="noSkillsMsg">No skills selected yet.</span>
                            </div>

                            <!-- Hidden inputs populated by JS -->
                            <div id="skillInputs"></div>

                            <div id="skillPickerError" class="text-danger small mt-2 d-none">
                                <i class="bi bi-exclamation-circle me-1"></i>Please select at least one skill.
                            </div>
                        </div>

                        <!-- Submit -->
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg ra-submit-btn" id="submitBtn">
                                <span id="btnText"><i class="bi bi-search me-2"></i>Analyze Resume</span>
                                <span id="btnSpinner" class="d-none">
                                    <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                                    Analyzing…
                                </span>
                            </button>
                        </div>

                    </form>
                </div>
            </div>

            <!-- Feature info row -->
            <div class="row text-center mt-4 g-3">
                <div class="col-4">
                    <div class="ra-feature-box">
                        <div class="ra-feature-icon mx-auto" style="background:#eef0ff;">
                            <i class="bi bi-bar-chart-fill text-primary"></i>
                        </div>
                        <p class="small mt-1 mb-0 fw-semibold">Score / 100</p>
                        <p class="mb-0 text-muted" style="font-size:0.7rem">Weighted result</p>
                    </div>
                </div>
                <div class="col-4">
                    <div class="ra-feature-box">
                        <div class="ra-feature-icon mx-auto" style="background:#f0fdf4;">
                            <i class="bi bi-check2-circle text-success"></i>
                        </div>
                        <p class="small mt-1 mb-0 fw-semibold">Skill Gaps</p>
                        <p class="mb-0 text-muted" style="font-size:0.7rem">See what's missing</p>
                    </div>
                </div>
                <div class="col-4">
                    <div class="ra-feature-box">
                        <div class="ra-feature-icon mx-auto" style="background:#fefce8;">
                            <i class="bi bi-lightbulb-fill text-warning"></i>
                        </div>
                        <p class="small mt-1 mb-0 fw-semibold">Suggestions</p>
                        <p class="mb-0 text-muted" style="font-size:0.7rem">Actionable tips</p>
                    </div>
                </div>
            </div>

        </div>
    </div>

</main>

<!-- ── Manage Skills Modal ──────────────────────────────────────── -->
<div class="modal fade" id="manageSkillsModal" tabindex="-1" aria-labelledby="manageSkillsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="manageSkillsModalLabel">
                    <i class="bi bi-gear me-2 text-primary"></i>Manage Skills
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">

                <!-- Add new skill -->
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Add New Skill</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="newSkillInput"
                               placeholder="e.g. TypeScript" maxlength="60">
                        <button class="btn btn-primary" type="button" id="addSkillBtn">
                            <i class="bi bi-plus-lg me-1"></i>Add
                        </button>
                    </div>
                    <div id="addSkillError" class="text-danger small mt-1 d-none"></div>
                </div>

                <hr class="my-3">

                <p class="small text-muted mb-2 fw-semibold">Existing Skills</p>
                <div id="manageSkillsList">
                    <!-- Rows built by JS -->
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Done</button>
            </div>
        </div>
    </div>
</div>

<footer class="ra-footer">
    <p class="mb-1 fw-semibold" style="color:rgba(255,255,255,0.75)">&copy; <?php echo date('Y'); ?> Resume Analyzer &mdash; College Project</p>
    <p class="mb-0" style="font-size:0.75rem;">Built with PHP, MySQL &amp; Bootstrap 5</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo htmlspecialchars(SITEURL, ENT_QUOTES, 'UTF-8'); ?>assets/js/app.js"></script>
</body>
</html>
