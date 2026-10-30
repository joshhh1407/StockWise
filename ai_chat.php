<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/ai_engine.php';
header('Content-Type: application/json; charset=utf-8');
if (!current_user()) { http_response_code(401); echo json_encode(['answer' => 'Please log in again.', 'engine' => 'error']); exit; }
csrf_check();
set_time_limit(120);      // allow slow LLM answers
session_write_close();    // don't block other pages while the LLM thinks

$body = json_decode(file_get_contents('php://input'), true);
$q = trim((string)($body['q'] ?? ''));
if ($q === '') { echo json_encode(['answer' => 'Type a question first.', 'engine' => 'Built-in AI']); exit; }
echo json_encode(ai_answer(mb_substr($q, 0, 500)), JSON_UNESCAPED_UNICODE);
