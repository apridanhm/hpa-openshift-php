<?php
// public/index.php  PHP-FPM VERSION (Copy dari BakpiaRun)
header('Content-Type: text/html; charset=utf-8');

// Load DB creds dari env vars (OpenShift style)
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$db   = getenv('DB_NAME') ?: 'poc_db';

// Connect to Percona
try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch(PDOException $e) {
    http_response_code(500);
    die("DB Error: " . $e->getMessage());
}

// Auto-log visitor
$client_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
try {
    $stmt = $pdo->prepare("INSERT INTO visitors (ip_address, user_agent, request_uri, request_method) VALUES (?, ?, ?, ?)");
    $stmt->execute([$client_ip, substr($user_agent,0,255), $_SERVER['REQUEST_URI'] ?? '/', $_SERVER['REQUEST_METHOD'] ?? 'GET']);
} catch(PDOException $e) {
    error_log("Log failed: " . $e->getMessage());
}

// Fetch & render (sama persis dengan BakpiaRun)
$visitors = $pdo->query("SELECT * FROM visitors ORDER BY created_at DESC LIMIT 50")->fetchAll();
$total = $pdo->query("SELECT COUNT(*) FROM visitors")->fetchColumn();
?>
<!DOCTYPE html><html><head><title>PHP-FPM Benchmark</title></head><body>
<h1> PHP-FPM Visitor Tracker</h1>
<p>Total: <?= $total ?></p>
<p>Pod: <?= gethostname() ?></p>
<!-- Tambahin tabel visitor sama seperti BakpiaRun biar fair -->
</body></html>
