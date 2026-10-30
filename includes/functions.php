<?php
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function peso($n): string { return '₱' . number_format((float)$n, 2); }
function receipt_no($id): string { return 'RCT-' . (1000 + (int)$id); }
function redirect(string $url): void { header('Location: ' . $url); exit; }

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void {
    $t = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!hash_equals($_SESSION['csrf'] ?? '', $t)) { http_response_code(419); die('Invalid session token. Go back and refresh the page.'); }
}

function flash(string $msg, string $type = 'success'): void { $_SESSION['flash'] = [$msg, $type]; }
function flash_show(): string {
    if (empty($_SESSION['flash'])) return '';
    [$m, $t] = $_SESSION['flash']; unset($_SESSION['flash']);
    return '<div class="flash flash-' . e($t) . '">' . e($m) . '</div>';
}

function stock_status(array $p): string {
    if ($p['stock_quantity'] <= $p['low_stock_threshold']) return 'Low';
    if ($p['stock_quantity'] >= $p['high_stock_level'])    return 'High';
    return 'Sufficient';
}
function status_badge(string $s): string {
    $c = ['Low' => 'bad', 'High' => 'warn', 'Sufficient' => 'good'][$s] ?? 'good';
    return '<span class="badge badge-' . $c . '">' . e($s) . '</span>';
}

/** Inline SVG icon (symbols live in includes/icons.php). */
function icon(string $name): string {
    return '<svg class="i i-' . e($name) . '" viewBox="0 0 24 24" aria-hidden="true"><use href="#i-' . e($name) . '"/></svg>';
}

/** One figure of the ledger strip: label, big number, short note, optional tone (bad|warn|good|info). */
function fig(string $label, $value, string $note = '', string $tone = ''): string {
    return '<div class="fig' . ($tone !== '' ? ' tone-' . e($tone) : '') . '"><span class="fig-label">' . e($label)
         . '</span><strong class="num">' . e($value) . '</strong><span class="fig-note">' . e($note) . '</span></div>';
}

/** Hue (0-359) for a category, so each category always gets the same color. */
function cat_hue($id): int { return ((int)$id * 47 + 120) % 360; }

/** Small colored square with the product's first letter. */
function pavatar(string $name, $catId): string {
    return '<span class="pav" style="--h:' . cat_hue($catId) . '">' . e(mb_strtoupper(mb_substr($name, 0, 1))) . '</span>';
}

/** Friendly empty-shelf drawing for empty states. */
function empty_art(): string {
    return '<svg class="empty-art" viewBox="0 0 120 90" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
         . '<path d="M8 78h104"/><rect x="18" y="50" width="26" height="28" rx="4"/><rect x="52" y="34" width="24" height="44" rx="4"/><rect x="84" y="58" width="22" height="20" rx="4"/>'
         . '<path d="M26 50v-8h10v8M58 34v-8h12v8M92 58v-6h6v6"/></svg>';
}
