<?php
/**
 * skills_api.php — CRUD endpoint for skills_master.
 * Accepts POST only, returns JSON.
 */

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$action = trim($_POST['action'] ?? '');

try {
    $pdo = getPDO();
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
    exit;
}

switch ($action) {

    // ── Add skill ────────────────────────────────────────────────
    case 'add':
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            echo json_encode(['success' => false, 'error' => 'Skill name cannot be empty.']);
            exit;
        }
        if (mb_strlen($name, 'UTF-8') > 60) {
            echo json_encode(['success' => false, 'error' => 'Skill name must be 60 characters or fewer.']);
            exit;
        }
        try {
            $skill = addSkillToDB($name, $pdo);
            echo json_encode(['success' => true, 'skill' => $skill]);
        } catch (RuntimeException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    // ── Rename skill ─────────────────────────────────────────────
    case 'rename':
        $id   = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
        $name = trim($_POST['name'] ?? '');
        if (!$id || $id < 1) {
            echo json_encode(['success' => false, 'error' => 'Invalid skill ID.']);
            exit;
        }
        if ($name === '') {
            echo json_encode(['success' => false, 'error' => 'Skill name cannot be empty.']);
            exit;
        }
        if (mb_strlen($name, 'UTF-8') > 60) {
            echo json_encode(['success' => false, 'error' => 'Skill name must be 60 characters or fewer.']);
            exit;
        }
        try {
            renameSkill((int) $id, $name, $pdo);
            echo json_encode(['success' => true]);
        } catch (RuntimeException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    // ── Delete skill ─────────────────────────────────────────────
    case 'delete':
        $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
        if (!$id || $id < 1) {
            echo json_encode(['success' => false, 'error' => 'Invalid skill ID.']);
            exit;
        }
        deleteSkill((int) $id, $pdo);
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Unknown action.']);
}
