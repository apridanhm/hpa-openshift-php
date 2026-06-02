<?php
// ============================================================================
// PRODUCTION-READY POC - AUTO-LOG VISITOR (OPENSHIFT + KUBEVIRT VM DB)
// ============================================================================
// - Auto-insert on every page load (GET request)
// - Real client IP detection (X-Forwarded-For)
// - Zero hardcoded secrets (mounted Kubernetes Secret)
// - HPA-ready architecture
// ============================================================================

// 1. Load credentials from mounted secret files
function get_db_cred($key, $secret_path = '/etc/secrets/db/') {
    $file = $secret_path . $key;
    return file_exists($file) ? trim(file_get_contents($file)) : '';
}

$host = get_db_cred('DB_HOST');
$port = get_db_cred('DB_PORT') ?: '3306';
$user = get_db_cred('DB_USER');
$pass = get_db_cred('DB_PASS');
$db   = get_db_cred('DB_NAME');

if (!$host || !$user || !$pass || !$db) {
    http_response_code(500);
    die(" ERROR: Database credentials missing. Check mounted secrets at /etc/secrets/db/");
}

// 2. Connect to MariaDB
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
    error_log("DB Connection Failed: " . $e->getMessage());
    die(" Database connection failed. Please contact administrator.");
}

// 3. AUTO-LOG VISITOR ON EVERY ACCESS
$client_ip = '0.0.0.0';
if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    $client_ip = trim($ips[0]);
} elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
    $client_ip = $_SERVER['HTTP_X_REAL_IP'];
} elseif (!empty($_SERVER['REMOTE_ADDR'])) {
    $client_ip = $_SERVER['REMOTE_ADDR'];
}

if (!filter_var($client_ip, FILTER_VALIDATE_IP)) {
    $client_ip = '0.0.0.0';
}

$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown/Unknown';

try {
    // Insert immediately on page load
    $stmt = $pdo->prepare("INSERT INTO visitors (ip_address, user_agent) VALUES (:ip, :ua)");
    $stmt->execute([':ip' => $client_ip, ':ua' => $user_agent]);
} catch(PDOException $e) {
    // Log error but don't break the page
    error_log("Auto-log insert failed: " . $e->getMessage());
}

// 4. Fetch visitor data
$visitors = [];
$total_visitors = 0;

try {
    $stmt = $pdo->query("SELECT * FROM visitors ORDER BY created_at DESC LIMIT 50");
    $visitors = $stmt->fetchAll();
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM visitors");
    $total_visitors = $stmt->fetch()['total'];
} catch(PDOException $e) {
    error_log("Data fetch failed: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Visitor Tracker - OpenShift POC</title>
    <style>
        :root {
            --primary: #0f172a;
            --accent: #3b82f6;
            --bg: #f8fafc;
            --card: #ffffff;
            --border: #e2e8f0;
            --text: #1e293b;
            --muted: #64748b;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
            padding: 20px;
        }
        .container {
            max-width: 1100px;
            margin: 0 auto;
            background: var(--card);
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        header {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            color: white;
            padding: 30px;
            position: relative;
        }
        header h1 { font-size: 1.8rem; margin-bottom: 8px; }
        header p { color: #94a3b8; font-size: 0.95rem; }
        .live-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: #10b981;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .live-dot {
            width: 8px; height: 8px;
            background: white;
            border-radius: 50%;
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.4; } 100% { opacity: 1; } }
        
        .stats-bar {
            display: flex;
            gap: 20px;
            padding: 20px 30px;
            background: #f1f5f9;
            border-bottom: 1px solid var(--border);
        }
        .stat-item {
            flex: 1;
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid var(--border);
        }
        .stat-label { font-size: 0.8rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-value { font-size: 1.5rem; font-weight: 700; color: var(--primary); margin-top: 4px; }
        
        .content { padding: 30px; }
        .table-wrapper {
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: 8px;
        }
        table { width: 100%; border-collapse: collapse; min-width: 600px; }
        th, td { padding: 14px 16px; text-align: left; border-bottom: 1px solid var(--border); }
        th { background: #f8fafc; font-weight: 600; color: var(--muted); font-size: 0.85rem; text-transform: uppercase; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f8fafc; }
        .ip-cell { font-family: monospace; background: #e2e8f0; padding: 4px 8px; border-radius: 4px; font-size: 0.9rem; }
        .ua-cell { max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--muted); font-size: 0.9rem; }
        .time-cell { white-space: nowrap; color: var(--muted); font-size: 0.9rem; }
        
        .footer-info {
            padding: 20px 30px;
            background: #f8fafc;
            border-top: 1px solid var(--border);
            font-size: 0.85rem;
            color: var(--muted);
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        .footer-info span { font-family: monospace; }
        
        @media (max-width: 768px) {
            .stats-bar { flex-direction: column; gap: 10px; }
            .footer-info { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="live-badge"><div class="live-dot"></div> AUTO-LOGGING ACTIVE</div>
            <h1> Live Visitor Tracker</h1>
            <p>OpenShift Virtualization + KubeVirt VM Database + HPA Auto-Scaling</p>
        </header>

        <div class="stats-bar">
            <div class="stat-item">
                <div class="stat-label">Total Visitors</div>
                <div class="stat-value"><?= number_format($total_visitors) ?></div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Current Pod</div>
                <div class="stat-value" style="font-size: 1.1rem; font-family: monospace;"><?= htmlspecialchars(gethostname()) ?></div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Database Host</div>
                <div class="stat-value" style="font-size: 1.1rem;"><?= htmlspecialchars($host) ?>:<?= htmlspecialchars($port) ?></div>
            </div>
        </div>

        <div class="content">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 60px;">ID</th>
                            <th>IP Address</th>
                            <th>User Agent</th>
                            <th style="width: 180px;">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($visitors)): ?>
                            <?php foreach($visitors as $v): ?>
                            <tr>
                                <td style="color: var(--muted); font-weight: 600;"><?= htmlspecialchars($v['id']) ?></td>
                                <td><span class="ip-cell"><?= htmlspecialchars($v['ip_address']) ?></span></td>
                                <td class="ua-cell" title="<?= htmlspecialchars($v['user_agent']) ?>"><?= htmlspecialchars($v['user_agent']) ?></td>
                                <td class="time-cell"><?= htmlspecialchars($v['created_at']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" style="text-align: center; padding: 40px; color: var(--muted);">Waiting for first visitor...</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="footer-info">
            <div>Server Time: <span><?= date('Y-m-d H:i:s') ?></span></div>
            <div>PHP Version: <span><?= phpversion() ?></span></div>
            <div>Scaling: <span>HPA 1-10 Pods (50% CPU)</span></div>
        </div>
    </div>
</body>
</html>
