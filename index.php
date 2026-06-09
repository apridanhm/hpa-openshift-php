<?php
// ============================================
// PHP-FPM VISITOR TRACKER - FINAL VERSION
// Working Backend + Beautiful UI
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 0);

// ============================================
// HACK: BACA ENV VARS DARI /proc/self/environ
// ============================================
function getEnvVar($key, $default = null) {
    $val = getenv($key);
    if ($val !== false && $val !== '') return $val;
    
    if (file_exists('/proc/self/environ')) {
        $env = file_get_contents('/proc/self/environ');
        $pairs = explode("\0", $env);
        foreach ($pairs as $pair) {
            if (strpos($pair, "$key=") === 0) {
                return substr($pair, strlen("$key="));
            }
        }
    }
    
    return $default;
}

// Ambil config
$host = getEnvVar('DB_HOST', 'pxc-cluster-haproxy.uad.svc.cluster.local');
$port = getEnvVar('DB_PORT', '3306');
$user = getEnvVar('DB_USER', 'root');
$pass = getEnvVar('DB_PASS', 'RootP@ss123!');
$db   = getEnvVar('DB_NAME', 'poc_db');

$hostname = gethostname();
$error = null;
$visitors = [];
$total = 0;
$lastInsertId = '-';

// DB Connection
if (empty($host) || empty($db)) {
    $error = "Config Error";
} else {
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
        $error = "DB Error: " . $e->getMessage();
        error_log($error);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PHP-FPM Visitor Tracker</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { 
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      padding: 2rem;
    }
    .container { max-width: 1200px; margin: 0 auto; }
    .header {
      background: linear-gradient(135deg, #2d3748 0%, #1a202c 100%);
      color: white;
      padding: 2rem;
      border-radius: 12px;
      margin-bottom: 2rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    }
    .header h1 { font-size: 2.5rem; margin-bottom: 0.5rem; }
    .header p { opacity: 0.8; font-size: 1rem; }
    .badge {
      background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
      color: white;
      padding: 0.5rem 1rem;
      border-radius: 9999px;
      font-size: 0.875rem;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }
    .badge::before {
      content: ''; width: 8px; height: 8px; background: white; border-radius: 50%;
      animation: pulse 2s infinite;
    }
    @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
    .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
    .card { background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    .card h3 { color: #718096; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem; }
    .card .value { font-size: 2rem; font-weight: bold; color: #2d3748; word-break: break-all; }
    .card .value.orange { color: #ed8936; }
    .error-box {
      background: #fed7d7;
      color: #c53030;
      padding: 1.5rem;
      border-radius: 12px;
      margin-bottom: 2rem;
      border-left: 4px solid #c53030;
    }
    .table-container { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    table { width: 100%; border-collapse: collapse; }
    thead { background: #f7fafc; border-bottom: 2px solid #e2e8f0; }
    th { padding: 1rem; text-align: left; font-weight: 600; color: #4a5568; font-size: 0.875rem; }
    td { padding: 1rem; border-bottom: 1px solid #e2e8f0; color: #2d3748; font-size: 0.875rem; }
    tr:hover { background: #f7fafc; }
    .method { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 4px; font-weight: 600; font-size: 0.75rem; }
    .method.get { background: #c6f6d5; color: #22543d; }
    .timestamp { color: #718096; font-family: monospace; }
    .empty-state { text-align: center; padding: 3rem; color: #718096; }
    @media (max-width: 768px) {
      body { padding: 1rem; }
      .header { flex-direction: column; text-align: center; gap: 1rem; }
      .cards { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
  <div class="container">
    <!-- Header -->
    <div class="header">
      <div>
        <h1> PHP-FPM Visitor Tracker</h1>
        <p>PHP-FPM + Percona XtraDB Cluster</p>
      </div>
      <div class="badge">AUTO-LOGGING ACTIVE</div>
    </div>
    
    <!-- Info Cards -->
    <div class="cards">
      <div class="card">
        <h3>Total Visitors</h3>
        <div class="value orange"><?= number_format($total) ?></div>
      </div>
      <div class="card">
        <h3>Current Pod</h3>
        <div class="value" style="font-size:1rem"><?= htmlspecialchars($hostname) ?></div>
      </div>
      <div class="card">
        <h3>Database</h3>
        <div class="value" style="font-size:1rem">pxc-cluster-haproxy.uad.svc.cluster.local</div>
      </div>
      <div class="card">
        <h3>Last Insert ID</h3>
        <div class="value"><?= htmlspecialchars($lastInsertId) ?></div>
      </div>
    </div>

    <!-- Error Box -->
    <?php if (!empty($error)): ?>
      <div class="error-box">
        <strong> Error:</strong> <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <!-- Visitors Table -->
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>IP Address</th>
            <th>Method</th>
            <th>Path</th>
            <th>User Agent</th>
            <th>Timestamp</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($visitors) && is_array($visitors)): ?>
            <?php foreach ($visitors as $v): ?>
            <tr>
              <td><?= htmlspecialchars($v['id']) ?></td>
              <td><?= htmlspecialchars($v['ip_address']) ?></td>
              <td><span class="method get"><?= htmlspecialchars($v['request_method']) ?></span></td>
              <td><?= htmlspecialchars($v['request_uri']) ?></td>
              <td style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($v['user_agent']) ?>">
                <?= htmlspecialchars($v['user_agent']) ?>
              </td>
              <td class="timestamp"><?= date('Y-m-d H:i:s', strtotime($v['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="6" class="empty-state">
                <?php if (!empty($error)): ?>
                   Unable to load visitors
                <?php else: ?>
                   No visitors yet
                <?php endif; ?>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
