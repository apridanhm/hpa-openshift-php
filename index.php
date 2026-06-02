<?php
// ============================================================================
// PRODUCTION-READY PHP APP - OpenShift POC
// ============================================================================
// - Credentials from Kubernetes Secret (mounted as files)
// - Database: MariaDB on KubeVirt VM
// - Real client IP detection (X-Forwarded-For)
// - XSS protection
// - Error handling
// ============================================================================

// Helper function: baca secret dari file mount
function get_db_cred($key, $secret_path = '/etc/secrets/db/') {
    $file = $secret_path . $key;
    if (file_exists($file)) {
        return trim(file_get_contents($file));
    }
    return '';
}

// Ambil credentials dari mounted secret
$host = get_db_cred('DB_HOST');
$port = get_db_cred('DB_PORT') ?: '3306';
$user = get_db_cred('DB_USER');
$pass = get_db_cred('DB_PASS');
$db   = get_db_cred('DB_NAME');

// Validasi credentials
if (!$host || !$user || !$pass || !$db) {
    http_response_code(500);
    die("ERROR: Database credentials not configured. Check mounted secrets.");
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

// ============================================================================
// HANDLE FORM SUBMIT (POST)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
    try {
        // Ambil REAL client IP (bukan IP proxy/router)
        $client_ip = '';
        
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // X-Forwarded-For: "client, proxy1, proxy2"
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $client_ip = trim($ips[0]);
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $client_ip = $_SERVER['HTTP_X_REAL_IP'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $client_ip = $_SERVER['REMOTE_ADDR'];
        } else {
            $client_ip = '0.0.0.0';
        }
        
        // Validasi IP
        if (!filter_var($client_ip, FILTER_VALIDATE_IP)) {
            $client_ip = '0.0.0.0';
        }
        
        // Insert ke database
        $stmt = $pdo->prepare("INSERT INTO visitors (ip_address, user_agent) VALUES (:ip, :ua)");
        $stmt->execute([
            ':ip' => $client_ip,
            ':ua' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
        
        // Redirect setelah sukses (Post/Redirect/Get pattern)
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
        
    } catch(PDOException $e) {
        $error_msg = "Failed to save data: " . $e->getMessage();
        error_log("Insert Error: " . $error_msg);
    }
}

// ============================================================================
// FETCH VISITORS DATA
// ============================================================================
$visitors = [];
try {
    $stmt = $pdo->query("SELECT * FROM visitors ORDER BY created_at DESC LIMIT 20");
    $visitors = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Query Error: " . $e->getMessage());
    // $visitors tetap array kosong
}

// Hitung total visitors
$total_visitors = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM visitors");
    $total_visitors = $stmt->fetch()['total'];
} catch(PDOException $e) {
    // Ignore counting error
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Production-Ready POC - OpenShift</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            max-width: 1000px; 
            margin: 0 auto; 
            padding: 20px; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container { 
            background: white; 
            padding: 40px; 
            border-radius: 12px; 
            box-shadow: 0 10px 40px rgba(0,0,0,0.2); 
        }
        h1 { 
            color: #333; 
            margin-bottom: 10px;
            font-size: 2em;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 0.95em;
        }
        .info { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px; 
            border-radius: 8px; 
            margin: 20px 0;
        }
        .info strong {
            display: block;
            margin-bottom: 10px;
            font-size: 1.1em;
        }
        .info ul {
            margin: 0;
            padding-left: 20px;
        }
        .info li {
            margin: 5px 0;
        }
        .success { 
            background: #d4edda; 
            color: #155724; 
            padding: 15px; 
            border-radius: 6px; 
            margin: 20px 0; 
            border-left: 4px solid #28a745; 
        }
        .error { 
            background: #f8d7da; 
            color: #721c24; 
            padding: 15px; 
            border-radius: 6px; 
            margin: 20px 0; 
            border-left: 4px solid #dc3545; 
        }
        .form-group {
            margin: 30px 0;
            display: flex;
            gap: 10px;
        }
        input[type="text"] { 
            flex: 1;
            padding: 12px 16px; 
            border: 2px solid #ddd; 
            border-radius: 6px;
            font-size: 1em;
            transition: border-color 0.3s;
        }
        input[type="text"]:focus {
            outline: none;
            border-color: #667eea;
        }
        button { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; 
            padding: 12px 24px; 
            border: none;
            border-radius: 6px; 
            cursor: pointer; 
            font-weight: bold;
            font-size: 1em;
            transition: transform 0.2s;
        }
        button:hover { 
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        button:active {
            transform: translateY(0);
        }
        h3 {
            margin: 30px 0 15px 0;
            color: #333;
            font-size: 1.5em;
        }
        .stats {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            display: inline-block;
            font-weight: 600;
            color: #667eea;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        th, td { 
            border: 1px solid #ddd; 
            padding: 12px; 
            text-align: left; 
        }
        th { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
        }
        tr:nth-child(even) { 
            background: #f9f9f9; 
        }
        tr:hover {
            background: #f1f1f1;
        }
        .pod-info { 
            background: #fff3cd; 
            padding: 15px; 
            border-radius: 6px; 
            margin-top: 30px; 
            font-family: 'Courier New', monospace; 
            font-size: 0.9em;
            border-left: 4px solid #ffc107;
        }
        .pod-info strong {
            color: #856404;
        }
        .empty-state { 
            text-align: center; 
            color: #999; 
            padding: 40px; 
            font-style: italic;
            font-size: 1.1em;
        }
        .ip-badge {
            background: #e3f2fd;
            color: #1976d2;
            padding: 2px 8px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 0.9em;
        }
        @media (max-width: 768px) {
            .container { padding: 20px; }
            .form-group { flex-direction: column; }
            table { font-size: 0.9em; }
            th, td { padding: 8px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1> Production-Ready POC</h1>
        <p class="subtitle">OpenShift Virtualization + KubeVirt VM Database + HPA Auto-Scaling</p>
        
        <div class="info">
            <strong> Architecture:</strong>
            <ul>
                <li>Credentials from Kubernetes Secret (mounted as files)</li>
                <li>Database: MariaDB on KubeVirt VM (<?= htmlspecialchars($host) ?>:<?= htmlspecialchars($port) ?>)</li>
                <li>Auto-scaling: HPA enabled (1-10 pods)</li>
                <li>Zero hardcoded secrets in code</li>
                <li>Real client IP detection via X-Forwarded-For</li>
            </ul>
        </div>

        <!-- Feedback Messages -->
        <?php if (isset($success_msg)): ?>
            <div class="success"> <?= htmlspecialchars($success_msg) ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_msg)): ?>
            <div class="error"> <?= htmlspecialchars($error_msg) ?></div>
        <?php endif; ?>

        <!-- Form Input -->
        <div class="form-group">
            <form method="POST" style="flex: 1; display: flex; gap: 10px;">
                <input type="text" name="name" placeholder="Enter your name to visit..." required>
                <button type="submit">Save to Database</button>
            </form>
        </div>

        <h3> Recent Visitors (Last 20)</h3>
        
        <?php if ($total_visitors > 0): ?>
            <div class="stats">Total Visitors: <?= $total_visitors ?></div>
        <?php endif; ?>
        
        <!-- Data Table -->
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>IP Address</th>
                    <th>User Agent</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($visitors)): ?>
                    <?php foreach($visitors as $v): ?>
                    <tr>
                        <td><?= htmlspecialchars($v['id']) ?></td>
                        <td><span class="ip-badge"><?= htmlspecialchars($v['ip_address']) ?></span></td>
                        <td><?= htmlspecialchars(substr($v['user_agent'] ?? '', 0, 60)) ?><?= strlen($v['user_agent'] ?? '') > 60 ? '...' : '' ?></td>
                        <td><?= htmlspecialchars($v['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="empty-state">
                             No visitors yet. Be the first to submit the form above!
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Pod Info -->
        <div class="pod-info">
            <strong> Pod Hostname:</strong> <?= htmlspecialchars(gethostname()) ?><br>
            <strong> Database:</strong> <?= htmlspecialchars($host) ?>:<?= htmlspecialchars($port) ?> (from Secret)<br>
            <strong> Server Time:</strong> <?= date('Y-m-d H:i:s') ?><br>
            <strong> PHP Version:</strong> <?= phpversion() ?>
        </div>
    </div>
</body>
</html>
