<?php
// filepath: /workspaces/QMeduLHS/save_record.php
$data = json_decode(file_get_contents('php://input'), true);
if ($data) {
    $line = implode(',', [
        $data['staffId'],
        $data['score'],
        $data['totalQuestions'],
        $data['percentage'],
        $data['completionTime']
    ]) . "\n";
    file_put_contents('records.csv', $line, FILE_APPEND | LOCK_EX);
    echo 'OK';
} else {
    http_response_code(400);
    echo 'Invalid data';
}
?>