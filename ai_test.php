<?php
// TEST ONLY - delete this file after checking the AI connection.
set_time_limit(120);
require_once __DIR__ . '/config/config.php';
$P = AI_PROFILES_ALL[AI_PROFILE];
$start = microtime(true);
$ch = curl_init(rtrim($P['url'], '/') . '/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode(['model' => $P['model'], 'stream' => false,
        'messages' => [['role' => 'user', 'content' => 'Say hi in Taglish']]]),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $P['key']],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => AI_CONNECT_TIMEOUT,
    CURLOPT_TIMEOUT => AI_TIMEOUT,
    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
]);
$res = curl_exec($ch);
echo 'Model: ' . $P['model'];
echo '<br>HTTP code: ' . curl_getinfo($ch, CURLINFO_HTTP_CODE) . ' (200 = working, 503 = Google is busy, try again later)';
echo '<br>cURL error: ' . curl_error($ch);
echo '<br>Time: ' . round(microtime(true) - $start, 1) . ' seconds';
$j = json_decode((string)$res, true);
echo '<h3>' . htmlspecialchars($j['choices'][0]['message']['content'] ?? '(no answer)') . '</h3>';
echo '<pre>' . htmlspecialchars((string)$res) . '</pre>';
