<?php
// Ambil dari environment variables (dari Secret)
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$db   = getenv('DB_NAME');

if (!$host || !$user || !$pass || !$db) {
    die("ERROR: Database credentials not configured. Check environment variables.");
}

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch(PDOException $e) {
    error_log("DB Connection Error: " . $e->getMessage());
    die("Database connection failed. Please check logs.");
}

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
    $stmt = $pdo->prepare("INSERT INTO visitors (ip_address, user_agent) VALUES (?, ?)");
    $stmt->execute([$_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
    header("Location: /");
    exit;
}

// Fetch data
$stmt = $pdo->query("SELECT * FROM visitors ORDER BY visit_time DESC LIMIT 20");
$visitors = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>PHP + VM DB (Production Ready)</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; max-width: 900px; margin: 40px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #4CAF50; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        input, button { padding: 10px; margin: 5px 0; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #4CAF50; color: white; cursor: pointer; }
        button:hover { background: #45a049; }
        .info { background: #e7f3ff; padding: 10px; border-left: 4px solid #2196F3; margin: 20px 0; }
        .pod-info { background: #fff3cd; padding: 8px; border-radius: 4px; margin-top: 20px; font-family: monospace; }
    </style>
</head>
<body>
    <div class="container">
        <h1> Production-Ready POC</h1>
        <div class="info">
             Credentials from Kubernetes Secret<br>
             Environment-based configuration<br>
             HPA enabled for auto-scaling
        </div>
        
        <form method="POST">
            <input type="text" name="name" placeholder="Enter your name" required style="width: 70%;">
            <button type="submit">Save to Database</button>
        </form>

        <h3>Recent Visitors (Last 20)</h3>
        <table>
            <thead>
                <tr><th>ID</th><th>IP Address</th><th>User Agent</th><th>Timestamp</th></tr>
            </thead>
            <tbody>
                <?php foreach($visitors as $v): ?>
                <tr>
                    <td><?= htmlspecialchars($v['id']) ?></td>
                    <td><?= htmlspecialchars($v['ip_address']) ?></td>
                    <td><?= htmlspecialchars(substr($v['user_agent'], 0, 60)) ?>...</td>
                    <td><?= $v['visit_time'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="pod-info">
            <strong>Pod Hostname:</strong> <?= gethostname() ?><br>
            <strong>DB Host:</strong> <?= $host ?> (from Secret)<br>
            <strong>Server Time:</strong> <?= date('Y-m-d H:i:s') ?>
        </div>
    </div>
</body>
</html>
