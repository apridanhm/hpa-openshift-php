<?php
// index.php  PHP-FPM VERSION (UI SAMA KAYAK BAKPIARUN)

// 1. Setup Database Connection
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$db   = getenv('DB_NAME') ?: 'poc_db';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // 2. Auto-Log Visitor
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $stmt = $pdo->prepare("INSERT INTO visitors (ip_address, user_agent, request_uri, request_method) VALUES (?, ?, ?, ?)");
    $stmt->execute([$ip, substr($ua, 0, 255), $_SERVER['REQUEST_URI'] ?? '/', $_SERVER['REQUEST_METHOD'] ?? 'GET']);
    $lastInsertId = $pdo->lastInsertId();

    // 3. Fetch Data
    $stmt = $pdo->query("SELECT * FROM visitors ORDER BY created_at DESC LIMIT 50");
    $visitors = $stmt->fetchAll();
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM visitors");
    $total = $stmt->fetch()['total'];

} catch (PDOException $e) {
    $error = "DB Error: " . $e->getMessage();
    $visitors = [];
    $total = 0;
    $lastInsertId = '-';
}

$hostname = gethostname();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PHP-FPM Visitor Tracker</title>
  <style>
    /* CSS SAMA PERSIS KAYAK BAKPIARUN & NODE.JS */
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
    .badge {
      background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%); /* Warna Orange khas PHP */
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
    .card .value { font-size: 2rem; font-weight: bold; color: #2d3748; }
    .card .value.orange { color: #ed8936; }
    .table-container { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    table { width: 100%; border-collapse: collapse; }
    thead { background: #f7fafc; border-bottom: 2px solid #e2e8f0; }
    th { padding: 1rem; text-align: left; font-weight: 600; color: #4a5568; font-size: 0.875rem; }
    td { padding: 1rem; border-bottom: 1px solid #e2e8f0; color: #2d3748; font-size: 0.875rem; }
    tr:hover { background: #f7fafc; }
    .method { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 4px; font-weight: 600; font-size: 0.75rem; }
    .method.get { background: #c6f6d5; color: #22543d; }
    .timestamp { color: #718096; font-family: monospace; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <div>
        <h1> PHP-FPM Visitor Tracker</h1>
        <p>Apache + PHP-FPM + OpenShift S2I</p>
      </div>
      <div class="badge">AUTO-LOGGING ACTIVE</div>
    </div>
    
    <div class="cards">
      <div class="card">
        <h3>Total Visitors</h3>
        <div class="value orange"><?= number_format($total) ?></div>
      </div>
      <div class="card">
        <h3>Current Pod</h3>
        <div class="value" style="font-size:1rem"><?= $hostname ?></div>
      </div>
      <div class="card">
        <h3>Database</h3>
        <div class="value" style="font-size:1rem">pxc-cluster-haproxy.uad.svc.cluster.local</div>
      </div>
      <div class="card">
        <h3>Last Insert ID</h3>
        <div class="value"><?= $lastInsertId ?></div>
      </div>
    </div>

    <?php if (!empty($error)): ?>
      <div class="card" style="background: #fed7d7; color: #c53030; margin-bottom: 2rem;">
        <strong>Error:</strong> <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>ID</th><th>IP</th><th>Method</th><th>Path</th><th>User Agent</th><th>Time</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($visitors as $v): ?>
          <tr>
            <td><?= $v['id'] ?></td>
            <td><?= $v['ip_address'] ?></td>
            <td><span class="method get"><?= $v['request_method'] ?></span></td>
            <td><?= $v['request_uri'] ?></td>
            <td style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= $v['user_agent'] ?></td>
            <td class="timestamp"><?= date('Y-m-d H:i:s', strtotime($v['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
