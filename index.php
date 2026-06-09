<?php
// ============================================
// PHP-FPM VISITOR TRACKER - FIXED VERSION
// ============================================

// Enable error reporting untuk debug
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Initialize variables dengan default values
$host = getenv('DB_HOST');
$port = getenv('DB_PORT') ?: '3306';
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$db   = getenv('DB_NAME');
$hostname = gethostname();
$error = null;
$visitors = [];
$total = 0;
$lastInsertId = '-';

// Debug: Check if env vars are set (remove after fix)
// Uncomment baris di bawah ini kalau mau debug:
/*
echo "<pre>";
var_dump([
  'DB_HOST' => $host,
  'DB_PORT' => $port,
  'DB_USER' => $user,
  'DB_NAME' => $db,
  'DB_PASS_SET' => !empty($pass)
]);
echo "</pre>";
exit;
*/

// Validate required env vars
if (empty($host)) {
  $error = "DB_HOST environment variable is not set. Please check deployment config.";
} elseif (empty($db)) {
  $error = "DB_NAME environment variable is not set. Please check deployment config.";
} else {
  // Force TCP connection dengan explicit host & port
  $dsn = "mysql:host=" . $host . ";port=" . $port . ";dbname=" . $db . ";charset=utf8mb4";
  
  try {
    // Create PDO connection dengan options yang tepat
    $pdo = new PDO($dsn, $user, $pass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
      PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
    
    // 1. Auto-Log Visitor
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    
    $stmt = $pdo->prepare("
      INSERT INTO visitors (ip_address, user_agent, request_uri, request_method) 
      VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$ip, substr($ua, 0, 255), $uri, $method]);
    $lastInsertId = $pdo->lastInsertId();
    
    // 2. Fetch Visitors (dengan safety check)
    $stmt = $pdo->query("SELECT * FROM visitors ORDER BY created_at DESC LIMIT 50");
    $visitors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Safety: pastikan $visitors adalah array
    if (!is_array($visitors)) {
      $visitors = [];
    }
    
    // 3. Count Total Visitors
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM visitors");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $total = $row['total'] ?? 0;
    
  } catch (PDOException $e) {
    $error = "DB Connection Error: " . $e->getMessage();
    // Log error ke stderr untuk debugging
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

    <!-- Error Box (jika ada error) -->
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
                   Unable to load visitors due to database error
                <?php else: ?>
                   No visitors yet. Be the first!
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
