<?php
header('Content-Type: application/json; charset=utf-8');

function respond($statusCode, $payload) {
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['status' => 'error', 'message' => 'Method not allowed']);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    respond(400, ['status' => 'error', 'message' => 'Invalid JSON payload']);
}

$staffId = strtoupper(trim((string)($data['staffId'] ?? '')));
if (!preg_match('/^[A-Z0-9]{6}$/', $staffId)) {
    respond(400, ['status' => 'error', 'message' => '人事號需為 6 碼英文字母或數字。']);
}

$score = filter_var($data['score'] ?? null, FILTER_VALIDATE_INT);
$totalQuestions = filter_var($data['totalQuestions'] ?? null, FILTER_VALIDATE_INT);
if ($score === false || $totalQuestions === false || $totalQuestions <= 0 || $score < 0 || $score > $totalQuestions) {
    respond(400, ['status' => 'error', 'message' => 'Invalid score data']);
}

$percentage = round($score / $totalQuestions * 100, 1);
if ($percentage < 70) {
    respond(403, ['status' => 'error', 'message' => '尚未通過，無法登錄學分。']);
}

$path = __DIR__ . '/records.csv';
$fp = fopen($path, 'c+');
if (!$fp) {
    respond(500, ['status' => 'error', 'message' => 'Unable to open records file']);
}

if (!flock($fp, LOCK_EX)) {
    fclose($fp);
    respond(500, ['status' => 'error', 'message' => 'Unable to lock records file']);
}

$hasRows = false;
$duplicate = false;
rewind($fp);
while (($row = fgetcsv($fp)) !== false) {
    if (!$row) {
        continue;
    }
    $hasRows = true;
    if (($row[0] ?? '') === 'staffId') {
        continue;
    }
    if (strtoupper(trim((string)($row[0] ?? ''))) === $staffId) {
        $duplicate = true;
        break;
    }
}

if ($duplicate) {
    flock($fp, LOCK_UN);
    fclose($fp);
    respond(200, ['status' => 'duplicate', 'message' => '此人事號已完成通過紀錄，本次不重複登錄學分。']);
}

fseek($fp, 0, SEEK_END);
$fileSize = ftell($fp);
if (!$hasRows || $fileSize === 0) {
    fputcsv($fp, ['staffId', 'score', 'totalQuestions', 'percentage', 'completionTime']);
} else {
    fseek($fp, -1, SEEK_END);
    if (fgetc($fp) !== "\n") {
        fwrite($fp, PHP_EOL);
    }
    fseek($fp, 0, SEEK_END);
}

fputcsv($fp, [$staffId, $score, $totalQuestions, $percentage, date('c')]);
fflush($fp);
flock($fp, LOCK_UN);
fclose($fp);

respond(200, ['status' => 'saved', 'message' => '通過紀錄已完成。']);
?>
