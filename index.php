<?php
// Helper function: baca secret dari file mount (production-ready)
function get_db_cred($key, $secret_path = '/etc/secrets/db/') {
    $file = $secret_path . $key;
    if (file_exists($file)) {
        return trim(file_get_contents($file));
    }
    return '';
}

// Ambil credentials
$host = get_db_cred('DB_HOST');
$port = get_db_cred('DB_PORT') ?: '3306';
$user = get_db_cred('DB_USER');
$pass = get_db_cred('DB_PASS');
$db   = get_db_cred('DB_NAME');

// Validasi credentials
if (!$host || !$user || !$pass || !$db) {
    http_response_code(500);
    die("ERROR: Database credentials missing. Check mounted secrets.");
}

// Koneksi database
try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch(PDOException $e) {
    http_response_code(500);
    error_log("DB Connection Error: " . $e->getMessage());
    die("Database connection failed. Please contact administrator.");
}

// === HANDLE FORM SUBMIT (POST) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO visitors (ip_address, user_agent) VALUES (:ip, :ua)");
        $stmt->execute([
            ':ip' => $_SERVER['REMOTE_ADDR'],
            ':ua' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
        // Redirect setelah insert sukses (Post/Redirect/Get pattern)
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } catch(PDOException $e) {
        $error_msg = "Failed to save data: " . $e->getMessage();
        error_log($error_msg);
    }
}

// === FETCH VISITORS DATA ===
$visitors = [];
try {
    $stmt = $pdo->query("SELECT * FROM visitors ORDER BY created_at DESC LIMIT 20");
    $visitors = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Query Error: " . $e->getMessage());
    // $visitors tetap array kosong, aman untuk foreach
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>PHP + VM DB (Production Ready)</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; max-width: 900px; margin: 40px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; margin-bottom: 10px; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 20px; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 20px; border-left: 4px solid #dc3545; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #4CAF50; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        input, button { padding: 10px; margin: 5px 0; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #4CAF50; color: white; cursor: pointer; font-weight: bold; }
        button:hover { background: #45a049; }
        .info { background: #e7f3ff; padding: 15px; border-left: 4px solid #2196F3; margin: 20px 0; border-radius: 0 4px 4px 0; }
        .pod-info { background: #fff3cd; padding: 12px; border-radius: 4px; margin-top: 30px; font-family: monospace; font-size: 0.9em; }
        .empty-state { text-align: center; color: #666; padding: 20px; font-style: italic; }
    </style>
</head>
<body>
    <div class="container">
        <h1> Production-Ready POC</h1>
        
        <div class="info">
            <strong> Architecture:</strong><br>
             Credentials from Kubernetes Secret (mounted as files)<br>
             Database: MariaDB on KubeVirt VM<br>
             Auto-scaling: HPA enabled (1-10 pods)<br>
             Zero hardcoded secrets in code
        </div>

        <!-- Form Input -->
        <form method="POST">
            <input type="text" name="name" placeholder="Enter your name" required style="width: 70%;">
            <button type="submit">Save to Database</button>
        </form>

        <!-- Feedback Messages -->
        <?php if (isset($error_msg)): ?>
            <div class="error"> <?= htmlspecialchars($error_msg) ?></div>
        <?php endif; ?>

        <h3 style="margin-top: 30px;">Recent Visitors (Last 20)</h3>
        
        <!-- Data Table -->
        <table>
            <thead>
                <tr><th>ID</th><th>IP Address</th><th>User Agent</th><th>Timestamp</th></tr>
            </thead>
            <tbody>
                <?php if (!empty($visitors)): ?>
                    <?php foreach($visitors as $v): ?>
                    <tr>
                        <td><?= htmlspecialchars($v['id']) ?></td>
                        <td><?= htmlspecialchars($v['ip_address']) ?></td>
                        <td><?= htmlspecialchars(substr($v['user_agent'] ?? '', 0, 50)) ?>...</td>
                        <td><?= htmlspecialchars($v['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="empty-state">No visitors yet. Be the first! </td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Pod Info -->
        <div class="pod-info">
            <strong> Pod Hostname:</strong> <?= gethostname() ?><br>
            <strong> DB Host:</strong> <?= htmlspecialchars($host) ?>:<?= htmlspecialchars($port) ?><br>
            <strong> Server Time:</strong> <?= date('Y-m-d H:i:s') ?>
        </div>
    </div>
</body>
</html>
