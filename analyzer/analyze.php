<?php
/**
 * analyze.php — Upload handler and analysis orchestrator.
 * Accepts POST multipart/form-data, returns JSON.
 */

header('Content-Type: application/json');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/parser.php';

// -------------------------------------------------------
// 1. Validate file upload
// -------------------------------------------------------
if (!isset($_FILES['resume']) || $_FILES['resume']['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form size limit.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension blocked the upload.',
    ];
    $code  = $_FILES['resume']['error'] ?? UPLOAD_ERR_NO_FILE;
    $msg   = $uploadErrors[$code] ?? 'Unknown upload error.';
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

$file = $_FILES['resume'];

// File size check (≤ 2 MB)
if ($file['size'] > MAX_FILE_SIZE) {
    echo json_encode(['success' => false, 'error' => 'File exceeds the 2 MB size limit.']);
    exit;
}

// Extension extraction and validation
$originalName = basename($file['name']);
$ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

if (in_array($ext, BLOCKED_EXTENSIONS, true)) {
    echo json_encode(['success' => false, 'error' => 'File type not permitted.']);
    exit;
}

if (!in_array($ext, ALLOWED_EXTENSIONS, true)) {
    echo json_encode(['success' => false, 'error' => 'Only PDF, DOCX, and TXT files are accepted.']);
    exit;
}

// MIME type check via finfo (independent of file name)
$finfo    = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, ALLOWED_MIME_TYPES, true)) {
    echo json_encode(['success' => false, 'error' => 'File content does not match the declared extension.']);
    exit;
}

// -------------------------------------------------------
// 2. Validate selected skills
// -------------------------------------------------------
$rawSkillIds = $_POST['skill_ids'] ?? [];
if (!is_array($rawSkillIds) || count($rawSkillIds) === 0) {
    echo json_encode(['success' => false, 'error' => 'Please select at least one skill to analyse.']);
    exit;
}

$skillIds = array_values(array_unique(array_filter(array_map('intval', $rawSkillIds), fn($id) => $id > 0)));
if (count($skillIds) === 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid skill selection.']);
    exit;
}

// Fetch skill names from DB (validates IDs exist)
try {
    $pdo          = getPDO();
    $placeholders = implode(',', array_fill(0, count($skillIds), '?'));
    $stmt         = $pdo->prepare("SELECT skill_name FROM skills_master WHERE id IN ({$placeholders})");
    $stmt->execute($skillIds);
    $requiredSkillNames = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error. Please try again.']);
    exit;
}

if (count($requiredSkillNames) === 0) {
    echo json_encode(['success' => false, 'error' => 'No valid skills found. Please try again.']);
    exit;
}

// -------------------------------------------------------
// 3. Save uploaded file with a server-generated name
// -------------------------------------------------------
$safeFilename = uniqid('resume_', true) . '.' . $ext;
$destPath     = UPLOAD_DIR . $safeFilename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    echo json_encode(['success' => false, 'error' => 'Failed to save the uploaded file.']);
    exit;
}

// -------------------------------------------------------
// 4. Extract text
// -------------------------------------------------------
try {
    $rawText = parseResume($destPath, $ext);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Could not extract text from the file: ' . $e->getMessage()]);
    exit;
}

if (empty(trim($rawText))) {
    echo json_encode(['success' => false, 'error' => 'No readable text found in the uploaded file. Please try a different file.']);
    exit;
}

// -------------------------------------------------------
// 5. Analyse
// -------------------------------------------------------
$processedText = preprocessText($rawText);

$skillData    = matchSkills($processedText, $requiredSkillNames);
$sectionData  = detectSections($processedText);
$keywordData  = analyzeKeywords($processedText);
$lengthData   = checkLength($processedText);
$contactData  = detectContact($rawText); // raw text for regex accuracy

$score = calculateScore(
    $skillData['percentage'],
    $sectionData['score'],
    $keywordData['score'],
    $lengthData['score'],
    $contactData['score']
);

$suggestions = generateSuggestions(
    $skillData['missing'],
    $sectionData['missing'],
    $keywordData['score'],
    $contactData,
    $lengthData
);

// -------------------------------------------------------
// 6. Build analysis payload
// -------------------------------------------------------
$analysis = [
    'score'       => $score,
    'skills'      => $skillData,
    'sections'    => $sectionData,
    'keywords'    => $keywordData,
    'length'      => $lengthData,
    'contact'     => $contactData,
    'suggestions' => $suggestions,
];

// -------------------------------------------------------
// 7. Persist to DB
// -------------------------------------------------------
try {
    $stmt = $pdo->prepare(
        'INSERT INTO resumes (file_path, extracted_text, job_role_id, score, analysis_json)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $safeFilename,
        $rawText,
        null,
        $score,
        json_encode($analysis),
    ]);
    $resumeId = (int) $pdo->lastInsertId();
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Failed to save analysis results.']);
    exit;
}

// -------------------------------------------------------
// 8. Return JSON response
// -------------------------------------------------------
echo json_encode([
    'success'  => true,
    'redirect' => SITEURL . 'result.php?id=' . $resumeId,
]);
exit;
