<?php
// ============================================
// PHP-FPM VISITOR TRACKER - HARDCODE FALLBACK VERSION
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ============================================
// HACK: BACA ENV VARS DARI /proc/self/environ
// (Karena getenv() kadang nggak work di S2I PHP-FPM)
// ============================================
function getEnvVar($key, $default = null) {
    // 1. Coba getenv() biasa dulu
    $val = getenv($key);
    if ($val !== false && $val !== '') return $val;
    
    // 2. Fallback: baca dari /proc/self/environ (Linux specific)
    if (file_exists('/proc/self/environ')) {
        $env = file_get_contents('/proc/self/environ');
        $pairs = explode("\0", $env);
        foreach ($pairs as $pair) {
            if (strpos($pair, "$key=") === 0) {
                return substr($pair, strlen("$key="));
            }
        }
    }
    
    // 3. Return default kalau nggak ketemu
    return $default;
}

// Ambil config pakai function hack di atas
$host = getEnvVar('DB_HOST', 'pxc-cluster-haproxy.uad.svc.cluster.local'); // Default ke host lu
$port = getEnvVar('DB_PORT', '3306');
$user = getEnvVar('DB_USER', 'root');
$pass = getEnvVar('DB_PASS', 'RootP@ss123!'); //  Pastikan ini sama dengan secret lu!
$db   = getEnvVar('DB_NAME', 'poc_db');

$hostname = gethostname();
$error = null;
$visitors = [];
$total = 0;
$lastInsertId = '-';

// Debug output (BIAR KELIATAN DI HTML)
$debug_info = [
    'host' => $host,
    'port' => $port,
    'user' => $user,
    'db' => $db,
    'pass_set' => !empty($pass) ? 'YES' : 'NO'
];

// ============================================
// DB CONNECTION
// ============================================
if (empty($host) || empty($db)) {
    $error = "Config Error: Host='$host', DB='$db'";
} else {
    // Force TCP connection
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        
        // Auto-log visitor
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        $stmt = $pdo->prepare("INSERT INTO visitors (ip_address, user_agent, request_uri, request_method) VALUES (?, ?, ?, ?)");
        $stmt->execute([$ip, substr($ua, 0, 255), $uri, $method]);
        $lastInsertId = $pdo->lastInsertId();
        
        // Fetch data
        $visitors = $pdo->query("SELECT * FROM visitors ORDER BY created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($visitors)) $visitors = [];
        
        $total = $pdo->query("SELECT COUNT(*) as total FROM visitors")->fetchColumn();
        
    } catch (PDOException $e) {
        $error = "DB Error (" . $e->getCode() . "): " . $e->getMessage();
        error_log($error);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>PHP-FPM Tracker</title>
  <style>
    body { font-family: monospace; background: #1a202c; color: #e2e8f0; padding: 2rem; }
    .container { max-width: 1200px; margin: 0 auto; }
    .error { background: #c53030; color: white; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
    .debug { background: #2d3748; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem; }
    .debug code { color: #68d391; }
    table { width: 100%; border-collapse: collapse; background: #2d3748; }
    th, td { padding: 0.75rem; border-bottom: 1px solid #4a5568; text-align: left; }
    th { color: #a0aec0; font-weight: 600; }
  </style>
</head>
<body>
  <div class="container">
    <h1> PHP-FPM Visitor Tracker</h1>
    
    <!-- DEBUG INFO (PENTING!) -->
    <div class="debug">
      <strong> Config Debug:</strong><br>
      <code>DB_HOST: <?= htmlspecialchars($debug_info['host']) ?></code><br>
      <code>DB_PORT: <?= htmlspecialchars($debug_info['port']) ?></code><br>
      <code>DB_USER: <?= htmlspecialchars($debug_info['user']) ?></code><br>
      <code>DB_NAME: <?= htmlspecialchars($debug_info['db']) ?></code><br>
      <code>DB_PASS: <?= $debug_info['pass_set'] ?></code><br>
      <code>Hostname: <?= htmlspecialchars($hostname) ?></code>
    </div>

    <!-- ERROR BOX -->
    <?php if (!empty($error)): ?>
      <div class="error"> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- STATS -->
    <p><strong>Total Visitors:</strong> <?= number_format($total) ?></p>
    <p><strong>Last Insert ID:</strong> <?= $lastInsertId ?></p>

    <!-- TABLE -->
    <table>
      <thead><tr><th>ID</th><th>IP</th><th>Method</th><th>Path</th><th>Time</th></tr></thead>
      <tbody>
        <?php if (!empty($visitors)): ?>
          <?php foreach ($visitors as $v): ?>
          <tr>
            <td><?= $v['id'] ?></td>
            <td><?= htmlspecialchars($v['ip_address']) ?></td>
            <td><?= $v['request_method'] ?></td>
            <td><?= htmlspecialchars($v['request_uri']) ?></td>
            <td><?= date('H:i:s', strtotime($v['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="5" style="text-align:center">No data <?= $error ? '(Error: '.htmlspecialchars($error).')' : '' ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</body>
</html>
