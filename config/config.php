<?php
// Load local credentials without overriding environment variables configured by Apache.
<<<<<<< HEAD
$envFile = is_file(__DIR__ . '/../.env') ? __DIR__ . '/../.env' : __DIR__ . '/../env';   // the file may be saved as .env or env
=======
$envFile = __DIR__ . '/../.env';
>>>>>>> 8d01c4576897e530dadbc1ece83b3a55659c1a50
if (is_file($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        throw new RuntimeException('Unable to read the .env file.');
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $matches)) {
            throw new RuntimeException('Invalid .env entry. Expected KEY=VALUE.');
        }

        $name = $matches[1];
        $value = trim($matches[2]);
        if (strlen($value) >= 2 && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
            $value = substr($value, 1, -1);
        }
        if (getenv($name) === false) putenv($name . '=' . $value);
    }
}

function config_env(string $name, string $default): string {
    $value = getenv($name);
    return $value === false ? $default : $value;
}

// ===== DATABASE =====
define('DB_HOST', config_env('DB_HOST', 'localhost'));
<<<<<<< HEAD
define('DB_NAME', config_env('DB_NAME', 'stockwise_db'));
define('DB_USER', config_env('DB_USER', 'root'));
define('DB_PASS', config_env('DB_PASS', ''));

define('STORE_NAME', 'Stockwise');
=======
define('DB_NAME', config_env('DB_NAME', 'youthco_db'));
define('DB_USER', config_env('DB_USER', 'root'));
define('DB_PASS', config_env('DB_PASS', ''));

define('STORE_NAME', 'YouthCo. Shop');
>>>>>>> 8d01c4576897e530dadbc1ece83b3a55659c1a50
date_default_timezone_set('Asia/Manila');

// ===== AI SETTINGS (Google Gemini) =====
// 'auto'    = ask Gemini; if it is busy / offline, the built-in PHP engine answers instead
// 'builtin' = never call Gemini (rule-based PHP engine only)
// 'llm'     = Gemini only (shows an error if it fails)
define('AI_PROVIDER', 'auto');
define('AI_PROFILE', 'gemini');

$AI_PROFILES = [
    'gemini' => [
        'url'   => 'https://generativelanguage.googleapis.com/v1beta/openai/',
<<<<<<< HEAD
        'model' => config_env('GEMINI_MODEL', 'gemini-3.8-flash'),
        'key'   => config_env('GEMINI_API_KEY', ''),
    ],
    // backup model: same key and endpoint, lighter and faster, used when the main model is busy or fails
    'gemini_lite' => [
        'url'   => 'https://generativelanguage.googleapis.com/v1beta/openai/',
        'model' => config_env('GEMINI_FALLBACK_MODEL', 'gemini-3.5-flash-lite'),
=======
        'model' => 'gemini-3.8-flash',
>>>>>>> 8d01c4576897e530dadbc1ece83b3a55659c1a50
        'key'   => config_env('GEMINI_API_KEY', ''),
    ],
];
define('AI_PROFILES_ALL', $AI_PROFILES);
<<<<<<< HEAD
define('AI_CHAIN', ['gemini', 'gemini_lite']);   // AIs to try, in order (then the built-in engine)
=======
define('AI_CHAIN', ['gemini']);                  // AIs to try, in order (then the built-in engine)
>>>>>>> 8d01c4576897e530dadbc1ece83b3a55659c1a50

define('AI_BASE_URL', $AI_PROFILES[AI_PROFILE]['url']);
define('AI_MODEL',    $AI_PROFILES[AI_PROFILE]['model']);
define('AI_API_KEY',  $AI_PROFILES[AI_PROFILE]['key']);
define('AI_TIMEOUT',  30);  
define('AI_CONNECT_TIMEOUT', 10);
define('AI_MAX_TOKENS', 1500);   // room for the answer (Gemini counts its internal thinking too)

// Analysis window used by the AI engine
define('AI_WINDOW_DAYS', 30);
define('AI_COVER_DAYS',  14);
define('AI_URGENT_DAYS', 7);
