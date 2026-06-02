<?php
// Helper function: baca secret dari file mount
function get_db_cred($key, $secret_path = '/etc/secrets/db/') {
    $file = $secret_path . $key;
    if (file_exists($file)) {
        return trim(file_get_contents($file));
    }
    return ''; // Fallback kosong kalau file nggak ada
}

$host = get_db_cred('DB_HOST');
$port = get_db_cred('DB_PORT') ?: '3306'; // Default 3306 kalau nggak ada file
$user = get_db_cred('DB_USER');
$pass = get_db_cred('DB_PASS');
$db   = get_db_cred('DB_NAME');

// Debug (hapus nanti)
echo "<pre>DB_HOST: $host | DB_USER: $user | DB_NAME: $db</pre>";

if (!$host || !$user || !$pass || !$db) {
    die("ERROR: Database credentials missing in /etc/secrets/db/");
}

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo " Database Connected Successfully!";
} catch(PDOException $e) {
    die(" DB Error: " . $e->getMessage());
}
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
