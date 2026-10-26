<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

function current_user(): ?array { return $_SESSION['user'] ?? null; }
function require_login(): void { if (!current_user()) redirect('login.php'); }
function is_admin(): bool { return (current_user()['role'] ?? '') === 'Administrator'; }
function require_admin(): void { if (!is_admin()) { flash('Administrator access only.', 'error'); redirect('index.php'); } }

/** The logged-in account's id. Every product, sale and report is filtered by this. */
function uid(): int { return (int)(current_user()['user_id'] ?? 0); }
