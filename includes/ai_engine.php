<?php
/**
 * AI engine for Stockwise
 * 1) Built-in analytics engine (pure PHP, no internet, no limit)
 * 2) Google Gemini (through its OpenAI-compatible endpoint) for the chat
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// ---------- DATA + ANALYSIS ----------
function ai_uid(): int { return (int)($_SESSION['user']['user_id'] ?? 0); }

function ai_product_stats(?int $uid = null): array {
    $uid = $uid ?? ai_uid();
    $sql = "SELECT p.*, c.category_name,
              COALESCE(SUM(si.quantity),0) AS sold_total,
              COALESCE(SUM(CASE WHEN s.sale_date >= DATE_SUB(NOW(), INTERVAL " . (int)AI_WINDOW_DAYS . " DAY) THEN si.quantity END),0) AS sold_window,
              COALESCE(SUM(CASE WHEN DATE(s.sale_date)=CURDATE() THEN si.quantity END),0) AS sold_today,
              COALESCE(SUM(CASE WHEN DATE(s.sale_date)=CURDATE() THEN si.subtotal END),0) AS revenue_today
            FROM products p
            JOIN categories c ON c.category_id = p.category_id
            LEFT JOIN sale_items si ON si.product_id = p.product_id
            LEFT JOIN sales s ON s.sale_id = si.sale_id
            WHERE p.is_active = 1
            GROUP BY p.product_id, c.category_name
            ORDER BY p.product_name";
    $st = db()->prepare($sql);
    $st->execute();
    $rows = $st->fetchAll();
    foreach ($rows as &$r) {
        $r['avg_daily']  = round($r['sold_window'] / AI_WINDOW_DAYS, 1);
        $r['status']     = stock_status($r);
        $r['days_left']  = $r['avg_daily'] > 0 ? (int)round($r['stock_quantity'] / $r['avg_daily']) : null;
        $need = (int)ceil($r['avg_daily'] * AI_COVER_DAYS) + (int)$r['low_stock_threshold'] - (int)$r['stock_quantity'];
        $r['suggested']  = ($r['status'] === 'High' || $need <= 0) ? 0 : $need;
        $r['is_fast']    = $r['avg_daily'] >= 1.0;
        $r['is_slow']    = $r['avg_daily'] < 0.3;
        $r['urgent']     = $r['status'] === 'Low' || ($r['days_left'] !== null && $r['days_left'] <= AI_URGENT_DAYS);
    }
    return $rows;
}

function ai_recommendations(array $stats): array {
    $out = [];
    foreach ($stats as $p) {
        if ($p['urgent']) {
            $when = $p['days_left'] !== null ? "so it may run out in {$p['days_left']} days" : 'and it has no recent sales';
            $out[] = ['type' => 'restock', 'label' => 'Restock now', 'rank' => $p['days_left'] ?? 99, 'priority' => 'high', 'product_id' => $p['product_id'],
                'title' => 'Restock ' . $p['product_name'],
                'msg' => "Only {$p['stock_quantity']} left and it sells about {$p['avg_daily']} per day, $when. Order about {$p['suggested']} units.",
                'avg' => $p['avg_daily'], 'qty' => $p['suggested']];
        }
    }
    usort($out, fn($a, $b) => $a['rank'] <=> $b['rank']);
    foreach ($stats as $p) {
        if ($p['status'] === 'High') {
            $out[] = ['type' => 'overstock', 'label' => 'Overstock', 'priority' => 'medium', 'product_id' => $p['product_id'],
                'title' => 'Hold off ordering ' . $p['product_name'],
                'msg' => "{$p['stock_quantity']} units in stock but only {$p['sold_total']} sold. Avoid buying more until stock goes down.",
                'avg' => $p['avg_daily'], 'qty' => 0];
        }
    }
    foreach ($stats as $p) {
        if ($p['is_fast']) {
            $out[] = ['type' => 'fast_moving', 'label' => 'Fast-moving', 'priority' => 'low', 'product_id' => $p['product_id'],
                'title' => $p['product_name'] . ' is a top mover',
                'msg' => "{$p['sold_total']} units sold. Always keep this in stock and prioritize it when ordering.",
                'avg' => $p['avg_daily'], 'qty' => 0];
        }
    }
    foreach ($stats as $p) {
        if ($p['is_slow']) {
            $out[] = ['type' => 'slow_moving', 'label' => 'Slow-moving', 'priority' => 'low', 'product_id' => $p['product_id'],
                'title' => $p['product_name'] . ' sells slowly',
                'msg' => "Only about {$p['avg_daily']} sold per day. Consider a promo or bundle to move the stock.",
                'avg' => $p['avg_daily'], 'qty' => 0];
        }
    }
    return $out;
}

/** Save the latest recommendations to the ai_recommendations table (refreshes each time). */
function ai_save_recommendations(array $recs, ?int $uid = null): void {
    $uid = $uid ?? ai_uid();
    $pdo = db();
    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM ai_recommendations')->execute();
    $st = $pdo->prepare('INSERT INTO ai_recommendations (product_id, recommendation_type, priority, message, avg_daily_sales, suggested_order_qty) VALUES (?,?,?,?,?,?)');
    foreach ($recs as $r) $st->execute([$r['product_id'], $r['type'], $r['priority'], mb_substr($r['msg'], 0, 500), $r['avg'], $r['qty']]);
    $pdo->commit();
}

// ---------- BUILT-IN CHAT ENGINE ----------
function ai_find_products(string $q, array $stats): array {
    $q = mb_strtolower($q);
    $best = 0; $found = [];
    foreach ($stats as $p) {
        $name = mb_strtolower($p['product_name']);
        $score = mb_strpos($q, $name) !== false ? 100 : 0;
        foreach (preg_split('/[^\p{L}\p{N}\.]+/u', $name, -1, PREG_SPLIT_NO_EMPTY) as $tok) {
            if (mb_strlen($tok) >= 4 && mb_strpos($q, $tok) !== false) $score++;
        }
        if ($score > $best) { $best = $score; $found = [$p]; }
        elseif ($score === $best && $score > 0) $found[] = $p;
    }
    return $found;
}

function ai_has(string $q, array $words): bool {
    foreach ($words as $w) if (mb_strpos($q, $w) !== false) return true;
    return false;
}

function ai_answer_builtin(string $question, array $stats): string {
    $q = mb_strtolower(trim($question));
    if ($q === '') return 'Ask me about stock, restocking, sales, or a specific product.';

    $found = ai_find_products($q, $stats);
    if ($found) {
        $lines = [];
        foreach (array_slice($found, 0, 4) as $p) {
            $l = "{$p['product_name']}: {$p['stock_quantity']} units left ({$p['status']} stock), {$p['sold_total']} sold in total, about {$p['avg_daily']} per day.";
            if ($p['days_left'] !== null) $l .= " Estimated {$p['days_left']} days of stock left.";
            if ($p['suggested'] > 0) $l .= " Suggested order: {$p['suggested']} units.";
            elseif ($p['status'] === 'High') $l .= ' Overstocked, avoid ordering more for now.';
            $lines[] = $l;
        }
        return implode("\n", $lines);
    }

    if (ai_has($q, ['today', 'ngayon', 'ngayong'])) {
        $rev = array_sum(array_column($stats, 'revenue_today'));
        $units = array_sum(array_column($stats, 'sold_today'));
        $top = $stats; usort($top, fn($a, $b) => $b['sold_today'] <=> $a['sold_today']);
        $msg = "Today's sales: " . peso($rev) . " from $units units sold.";
        if ($units > 0) $msg .= " Best seller today: {$top[0]['product_name']} ({$top[0]['sold_today']} sold).";
        return $msg;
    }
    if (ai_has($q, ['restock', 'reorder', 'order', 'low', 'kulang', 'ubos', 'mauubos', 'bilhin', 'bumili'])) {
        $list = array_filter($stats, fn($p) => $p['urgent']);
        usort($list, fn($a, $b) => ($a['days_left'] ?? 99) <=> ($b['days_left'] ?? 99));
        if (!$list) return 'Everything is well-stocked right now. Nothing needs restocking.';
        $lines = array_map(fn($p) => "• {$p['product_name']}: {$p['stock_quantity']} left (about " . ($p['days_left'] ?? '?') . " days) → order about {$p['suggested']} units", $list);
        return "You should restock " . count($list) . " product(s), most urgent first:\n" . implode("\n", $lines);
    }
    if (ai_has($q, ['overstock', 'too much', 'sobra', 'high stock', 'marami', 'excess'])) {
        $list = array_filter($stats, fn($p) => $p['status'] === 'High');
        if (!$list) return 'No products are overstocked right now.';
        $lines = array_map(fn($p) => "• {$p['product_name']}: {$p['stock_quantity']} in stock, only {$p['sold_total']} sold. Hold off ordering.", $list);
        return "Overstocked products:\n" . implode("\n", $lines);
    }
    if (ai_has($q, ['slow', 'matumal', 'hindi mabenta', 'di mabenta'])) {
        $list = array_filter($stats, fn($p) => $p['is_slow']);
        if (!$list) return 'No slow-moving products right now.';
        $lines = array_map(fn($p) => "• {$p['product_name']}: about {$p['avg_daily']} per day, {$p['stock_quantity']} in stock", $list);
        return "Slow-moving products (consider a promo):\n" . implode("\n", $lines);
    }
    if (ai_has($q, ['top', 'best', 'seller', 'mabenta', 'pinakamabenta', 'popular', 'fast'])) {
        $top = $stats; usort($top, fn($a, $b) => $b['sold_total'] <=> $a['sold_total']);
        $lines = [];
        foreach (array_slice($top, 0, 5) as $i => $p) $lines[] = ($i + 1) . ". {$p['product_name']} — {$p['sold_total']} sold";
        return "Top sellers:\n" . implode("\n", $lines);
    }
    if (ai_has($q, ['total', 'revenue', 'sales', 'benta', 'kita', 'summary', 'overview'])) {
        $units = array_sum(array_column($stats, 'stock_quantity'));
        $low = count(array_filter($stats, fn($p) => $p['status'] === 'Low'));
        $rv = db()->prepare('SELECT COALESCE(SUM(si.subtotal),0) FROM sale_items si JOIN products p ON p.product_id = si.product_id');
        $rv->execute();
        $rev = $rv->fetchColumn();
        return "Store summary: " . count($stats) . " products, $units units in stock, $low low-stock item(s). Total recorded revenue: " . peso($rev) . ".";
    }
    return "I can help with: what to restock, overstocked or slow products, top sellers, today's sales, or a specific product (e.g. \"How many Coca-Cola are left?\").";
}

// ---------- Google Gemini ----------
function ai_context_text(array $stats): string {
    $lines = ["Store: " . STORE_NAME . ". Date: " . date('Y-m-d') . ". Currency: Philippine peso (₱)."];
    foreach ($stats as $p) {
        $days = $p['days_left'] === null ? 'no recent sales' : $p['days_left'] . ' days';
        $lines[] = "- {$p['product_name']} ({$p['category_name']}): price ₱{$p['unit_price']}, stock {$p['stock_quantity']}, status {$p['status']}, total sold {$p['sold_total']}, average sold per day {$p['avg_daily']}, stock lasts {$days}, suggested order {$p['suggested']} units, sold today {$p['sold_today']}.";
    }
    return implode("\n", $lines);
}

/** Pre-computed facts (calculated by PHP from the database) so the LLM does not have to do math. */
function ai_facts_text(array $stats, string $question): string {
    $f = [];
    $restock = array_values(array_filter($stats, fn($p) => $p['urgent']));
    usort($restock, fn($a, $b) => ($a['days_left'] ?? 99) <=> ($b['days_left'] ?? 99));
    $f[] = 'Products that need restocking (most urgent first): ' . ($restock
        ? implode('; ', array_map(fn($p) => "{$p['product_name']} (stock {$p['stock_quantity']}, lasts " . ($p['days_left'] ?? '?') . " days, order {$p['suggested']} units)", $restock))
        : 'none');

    $over = array_values(array_filter($stats, fn($p) => $p['status'] === 'High'));
    $f[] = 'Overstocked products: ' . ($over
        ? implode('; ', array_map(fn($p) => "{$p['product_name']} (stock {$p['stock_quantity']}, only {$p['sold_total']} sold)", $over)) : 'none');

    $top = $stats; usort($top, fn($a, $b) => $b['sold_total'] <=> $a['sold_total']);
    $f[] = 'Top sellers (highest total sold first): ' . implode('; ', array_map(fn($p) => "{$p['product_name']} ({$p['sold_total']} sold)", array_slice($top, 0, 5)));

    $slow = array_values(array_filter($stats, fn($p) => $p['is_slow']));
    $f[] = 'Slow-moving products: ' . ($slow ? implode('; ', array_map(fn($p) => "{$p['product_name']} ({$p['avg_daily']} per day)", $slow)) : 'none');

    $f[] = "Today's sales: " . peso(array_sum(array_column($stats, 'revenue_today'))) . ' from ' . array_sum(array_column($stats, 'sold_today')) . ' units.';
    $f[] = 'Total products: ' . count($stats) . '. Total units in stock: ' . array_sum(array_column($stats, 'stock_quantity')) . '.';

    if (ai_find_products($question, $stats)) {
        $f[] = 'About the product the user mentioned: ' . str_replace("\n", ' ', ai_answer_builtin($question, $stats));
    }
    return '- ' . implode("\n- ", $f);
}

/** Settings of one AI profile from config.php (url, model, key + timeouts). */
function ai_profile(string $name): ?array {
    $all = AI_PROFILES_ALL;
    if (!isset($all[$name])) return null;
    $p = $all[$name]; $p['name'] = $name;
    $p['timeout'] = AI_TIMEOUT;
    $p['connect'] = AI_CONNECT_TIMEOUT;
    return $p;
}

function ai_call_llm(string $question, array $stats, array $prof): ?string {
    if (!function_exists('curl_init')) { $GLOBALS['AI_LAST_ERROR'] = 'PHP cURL extension is off'; return null; }
    $system = "You are the assistant of a small Philippine convenience store's inventory system.\n"
            . "RULES:\n"
            . "1. Use ONLY the DATA and VERIFIED FACTS below. Copy numbers exactly. Never invent products, numbers, prices or dates.\n"
            . "2. If the answer is not in the data, say you don't have that information.\n"
            . "3. Reply in the same language as the user's question (English, Filipino or Taglish). Keep it short: 2-5 sentences or a short list.\n"
            . "4. Only recommend restocking products listed under 'Products that need restocking'. Only call a product overstocked if it is listed under 'Overstocked products'.\n\n"
            . "VERIFIED FACTS (calculated from the database):\n" . ai_facts_text($stats, $question) . "\n\n"
            . "DATA (all products):\n" . ai_context_text($stats);
    $payload = json_encode([
        'model' => $prof['model'], 'temperature' => 0.1, 'stream' => false, 'max_tokens' => AI_MAX_TOKENS,
        'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $question]],
    ]);
    $headers = ['Content-Type: application/json'];
    if (($prof['key'] ?? '') !== '') $headers[] = 'Authorization: Bearer ' . $prof['key'];
    // Gemini sometimes answers 503 ("high demand") or 429 for a moment, so try up to 3 times.
    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $ch = curl_init(rtrim($prof['url'], '/') . '/chat/completions');
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => $prof['connect'], CURLOPT_TIMEOUT => $prof['timeout'],
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($code === 200 || !in_array($code, [429, 500, 502, 503, 504], true) || $attempt === 3) break;
        sleep($attempt);   // wait 1s, then 2s
    }
    if ($res === false || $code !== 200) {
        $j = json_decode((string)$res, true);
        $msg = $j['error']['message'] ?? ($j[0]['error']['message'] ?? substr((string)$res, 0, 200));
        $GLOBALS['AI_LAST_ERROR'] = $res === false ? ('connection failed: ' . $err) : ('HTTP ' . $code . ' - ' . substr($msg, 0, 160));
        return null;
    }
    $json = json_decode($res, true);
    $text = (string)($json['choices'][0]['message']['content'] ?? '');
    $text = trim($text);
    if ($text === '') { $GLOBALS['AI_LAST_ERROR'] = 'empty answer'; return null; }
    return $text;
}

/** Main entry: tries each AI in AI_CHAIN, then the built-in engine. Returns ['answer','engine','note'] */
function ai_answer(string $question): array {
    $stats = ai_product_stats();
    $errors = [];
    if (AI_PROVIDER !== 'builtin') {
        foreach (AI_CHAIN as $name) {
            $prof = ai_profile($name);
            if (!$prof) continue;
            $GLOBALS['AI_LAST_ERROR'] = null;
            $text = ai_call_llm($question, $stats, $prof);
            if ($text !== null) {
                return ['answer' => $text, 'engine' => $prof['model'] . ' (LLM)',
                        'note' => $errors ? 'Backup AI used. ' . implode(' | ', $errors) : ''];
            }
            $errors[] = $name . ': ' . ($GLOBALS['AI_LAST_ERROR'] ?? 'failed');
        }
        if (AI_PROVIDER === 'llm') return ['answer' => 'No AI is reachable. ' . implode(' | ', $errors), 'engine' => 'error', 'note' => ''];
    }
    return ['answer' => ai_answer_builtin($question, $stats), 'engine' => 'Built-in AI',
            'note' => $errors ? 'LLM not used. ' . implode(' | ', $errors) : ''];
}
