<?php
/**
 * Assistant endpoint. Accepts a question and returns a grounded answer.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

Auth::boot();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST a question.']);
    exit;
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$question = trim((string) ($payload['question'] ?? $_POST['question'] ?? ''));

if ($question === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Ask me something.']);
    exit;
}

if (mb_strlen($question) > 500) {
    $question = mb_substr($question, 0, 500);
}

$history = [];

if (isset($payload['history']) && is_array($payload['history'])) {
    foreach (array_slice($payload['history'], -8) as $turn) {
        if (isset($turn['role'], $turn['content']) && is_string($turn['content'])) {
            $history[] = ['role' => (string) $turn['role'], 'content' => mb_substr($turn['content'], 0, 2000)];
        }
    }
}

$result = Assistant::reply($question, $history, BranchDAO::current());

// Keep a transcript so the thread survives a page reload.
$sessionKey = session_id() ?: 'anon';
$customer = Auth::customer();

$conversation = DB::one(
    'SELECT * FROM ai_conversations WHERE session_key = ? ORDER BY updated_at DESC LIMIT 1',
    [$sessionKey]
);

if ($conversation === null) {
    $conversationId = AccountDAO::uuid();
    DB::run(
        'INSERT INTO ai_conversations (id, customer_id, session_key, title) VALUES (?, ?, ?, ?)',
        [$conversationId, $customer['id'] ?? null, $sessionKey, mb_substr($question, 0, 200)]
    );
} else {
    $conversationId = (string) $conversation['id'];
}

DB::run(
    'INSERT INTO ai_messages (conversation_id, role, content) VALUES (?, ?, ?)',
    [$conversationId, 'user', $question]
);
DB::run(
    'INSERT INTO ai_messages (conversation_id, role, content) VALUES (?, ?, ?)',
    [$conversationId, 'assistant', $result['answer']]
);
DB::run('UPDATE ai_conversations SET updated_at = NOW() WHERE id = ?', [$conversationId]);

echo json_encode([
    'ok' => true,
    'answer' => $result['answer'],
    'engine' => $result['engine'],
    'powered_by_model' => $result['engine'] === 'openai',
]);
