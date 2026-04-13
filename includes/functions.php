<?php
require_once __DIR__ . '/db.php';

/**
 * Preprocess resume text for analysis.
 * Lowercases, strips non-essential symbols, normalises spaces.
 */
function preprocessText(string $text): string {
    $text = mb_strtolower($text, 'UTF-8');
    // Keep alphanumerics, whitespace, @ . + ( ) - for emails / phone / skills like Node.js C++
    $text = preg_replace('/[^a-z0-9\s@.\+\(\)\-#\/]/u', ' ', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

/**
 * Match a given list of skill names against resume text.
 * Returns matched skills, missing skills, and match percentage.
 *
 * @param string   $text           Preprocessed resume text.
 * @param string[] $requiredSkills Skill names to check.
 */
function matchSkills(string $text, array $requiredSkills): array {
    $matched = [];
    $missing = [];

    foreach ($requiredSkills as $skill) {
        $pattern = '/\b' . preg_quote(strtolower($skill), '/') . '\b/i';
        if (preg_match($pattern, $text)) {
            $matched[] = $skill;
        } else {
            $missing[] = $skill;
        }
    }

    $total      = count($requiredSkills);
    $percentage = $total > 0 ? round((count($matched) / $total) * 100, 1) : 0;

    return [
        'matched'    => $matched,
        'missing'    => $missing,
        'total'      => $total,
        'percentage' => $percentage,
    ];
}

/**
 * Detect which standard resume sections are present or missing.
 */
function detectSections(string $text): array {
    $sections = ['education', 'experience', 'projects', 'skills', 'contact'];
    // Accept common synonyms
    $synonymMap = [
        'education'  => ['education', 'academic', 'qualifications'],
        'experience' => ['experience', 'employment', 'work history', 'professional background'],
        'projects'   => ['projects', 'portfolio', 'personal projects'],
        'skills'     => ['skills', 'technical skills', 'competencies', 'expertise'],
        'contact'    => ['contact', 'personal information', 'personal details'],
    ];

    $present = [];
    $missing = [];

    foreach ($sections as $section) {
        $found = false;
        foreach ($synonymMap[$section] as $term) {
            if (str_contains($text, $term)) {
                $found = true;
                break;
            }
        }
        if ($found) {
            $present[] = $section;
        } else {
            $missing[] = $section;
        }
    }

    $total = count($sections);
    $score = round((count($present) / $total) * 100);

    return [
        'present' => $present,
        'missing' => $missing,
        'score'   => $score,
    ];
}

/**
 * Count action verb usage to gauge resume quality.
 * Score is capped at 100 (10 points per keyword hit).
 */
function analyzeKeywords(string $text): array {
    $actionWords = [
        'developed', 'built', 'implemented', 'designed', 'managed',
        'created', 'led', 'optimized', 'delivered', 'deployed',
        'architected', 'automated', 'improved', 'integrated', 'maintained',
    ];

    $hits   = [];
    $total  = 0;

    foreach ($actionWords as $word) {
        $count = substr_count($text, $word);
        if ($count > 0) {
            $hits[$word] = $count;
            $total += $count;
        }
    }

    $score = min($total * 10, 100);

    return [
        'hits'  => $hits,
        'total' => $total,
        'score' => $score,
    ];
}

/**
 * Score based on resume word count.
 */
function checkLength(string $text): array {
    $wordCount = str_word_count($text);

    if ($wordCount < 300) {
        $score  = 30;
        $status = 'too_short';
    } elseif ($wordCount <= 1000) {
        $score  = 100;
        $status = 'optimal';
    } elseif ($wordCount <= 1500) {
        $score  = 80;
        $status = 'slightly_long';
    } else {
        $score  = 60;
        $status = 'too_long';
    }

    return [
        'word_count' => $wordCount,
        'score'      => $score,
        'status'     => $status,
    ];
}

/**
 * Detect contact information (email + phone) in resume text.
 * Uses the raw (not fully preprocessed) text to preserve symbols.
 */
function detectContact(string $rawText): array {
    $hasEmail = (bool) preg_match(
        '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/',
        $rawText
    );
    $hasPhone = (bool) preg_match(
        '/(\+?[\d][\d\s\-().]{7,}[\d])/',
        $rawText
    );

    $score = ($hasEmail ? 50 : 0) + ($hasPhone ? 50 : 0);

    return [
        'has_email' => $hasEmail,
        'has_phone' => $hasPhone,
        'score'     => $score,
    ];
}

/**
 * Calculate overall weighted score.
 *
 * Skill Match   → 40%
 * Sections      → 20%
 * Keywords      → 15%
 * Length        → 10%
 * Contact Info  → 15%
 */
function calculateScore(
    float $skillPct,
    int   $sectionScore,
    int   $keywordScore,
    int   $lengthScore,
    int   $contactScore
): int {
    $score = ($skillPct   * 0.40)
           + ($sectionScore  * 0.20)
           + ($keywordScore  * 0.15)
           + ($lengthScore   * 0.10)
           + ($contactScore  * 0.15);

    return (int) round($score);
}

/**
 * Generate actionable suggestions based on analysis results.
 */
function generateSuggestions(
    array $missingSkills,
    array $missingSections,
    int   $keywordScore,
    array $contact,
    array $lengthData
): array {
    $suggestions = [];

    foreach ($missingSkills as $skill) {
        $suggestions[] = "Consider adding <strong>{$skill}</strong> to your resume or learning it for this role.";
    }

    foreach ($missingSections as $section) {
        $label = ucfirst($section);
        $suggestions[] = "Add a <strong>{$label}</strong> section to your resume.";
    }

    if ($keywordScore < 50) {
        $suggestions[] = 'Use more action verbs (e.g., <em>developed, implemented, designed, optimized</em>) to strengthen impact.';
    }

    if (!$contact['has_email']) {
        $suggestions[] = 'Include a professional email address in your contact information.';
    }

    if (!$contact['has_phone']) {
        $suggestions[] = 'Include a phone number in your contact information.';
    }

    if ($lengthData['status'] === 'too_short') {
        $suggestions[] = 'Your resume is too short (' . $lengthData['word_count'] . ' words). Expand your experience and project descriptions.';
    } elseif ($lengthData['status'] === 'too_long') {
        $suggestions[] = 'Your resume is quite long (' . $lengthData['word_count'] . ' words). Consider trimming to 1–2 pages for better readability.';
    }

    if (empty($suggestions)) {
        $suggestions[] = 'Great job! Your resume looks comprehensive for this role.';
    }

    return $suggestions;
}

/**
 * Get score label and Bootstrap color class.
 */
function getScoreLabel(int $score): array {
    if ($score >= 70) {
        return ['label' => 'Strong', 'class' => 'success', 'color' => '#198754'];
    } elseif ($score >= 40) {
        return ['label' => 'Average', 'class' => 'warning', 'color' => '#ffc107'];
    } else {
        return ['label' => 'Weak', 'class' => 'danger', 'color' => '#dc3545'];
    }
}

/**
 * Fetch all job roles from the DB.
 */
function getJobRoles(PDO $pdo): array {
    return $pdo->query('SELECT id, role_name FROM job_roles ORDER BY id')->fetchAll();
}

/**
 * Fetch all skills from the DB.
 */
function getAllSkills(PDO $pdo): array {
    return $pdo->query('SELECT id, skill_name FROM skills_master ORDER BY skill_name')->fetchAll();
}

/**
 * Fetch a resume record by ID.
 */
function getResumeById(int $id, PDO $pdo): ?array {
    $stmt = $pdo->prepare(
        'SELECT r.*, jr.role_name
         FROM resumes r
         LEFT JOIN job_roles jr ON r.job_role_id = jr.id
         WHERE r.id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Fetch the last N resume analyses for the history table.
 */
function getRecentAnalyses(int $limit, PDO $pdo): array {
    $stmt = $pdo->prepare(
        'SELECT r.id, jr.role_name, r.score, r.created_at
         FROM resumes r
         LEFT JOIN job_roles jr ON r.job_role_id = jr.id
         ORDER BY r.created_at DESC
         LIMIT ?'
    );
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

/**
 * Add a new skill. Returns the new row or throws on duplicate/error.
 */
function addSkillToDB(string $name, PDO $pdo): array {
    $check = $pdo->prepare('SELECT id FROM skills_master WHERE LOWER(skill_name) = LOWER(?)');
    $check->execute([$name]);
    if ($check->fetch()) {
        throw new RuntimeException('A skill with that name already exists.');
    }
    $stmt = $pdo->prepare('INSERT INTO skills_master (skill_name) VALUES (?)');
    $stmt->execute([$name]);
    return ['id' => (int) $pdo->lastInsertId(), 'skill_name' => $name];
}

/**
 * Rename an existing skill by ID.
 */
function renameSkill(int $id, string $name, PDO $pdo): bool {
    $check = $pdo->prepare(
        'SELECT id FROM skills_master WHERE LOWER(skill_name) = LOWER(?) AND id <> ?'
    );
    $check->execute([$name, $id]);
    if ($check->fetch()) {
        throw new RuntimeException('A skill with that name already exists.');
    }
    $stmt = $pdo->prepare('UPDATE skills_master SET skill_name = ? WHERE id = ?');
    $stmt->execute([$name, $id]);
    return $stmt->rowCount() > 0;
}

/**
 * Delete a skill by ID.
 */
function deleteSkill(int $id, PDO $pdo): bool {
    $stmt = $pdo->prepare('DELETE FROM skills_master WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->rowCount() > 0;
}
