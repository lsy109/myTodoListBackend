<?php
header('Content-Type: application/json'); // 设置响应内容类型为 JSON
header('Access-Control-Allow-Origin: *'); // 允许跨域请求
header('Access-Control-Allow-Methods: GET, POST, OPTIONS'); // 允许的请求方法
header('Access-Control-Allow-Headers: Content-Type'); // 允许的请求头

// 简单的路由机制
$request_method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI']; // 去掉末尾斜杠

// 使用 Connection URL
$connection_url = 'mysql://root:OoqjSOInUNPRiGeWrnJMZprBotusOUHs@junction.proxy.rlwy.net:23097/railway';
$url_components = parse_url($connection_url);

// 数据库连接信息
$host = $url_components['host'];  // 数据库主机
$username = $url_components['user'];                  // 用户名
$password = $url_components['pass']; // 密码
$dbname = ltrim($url_components['path'], '/');                 // 数据库名
$port = $url_components['port'];                       // 端口

// 根目录 API
if ($request_uri === '/index.php' && $request_method === 'GET') {
    echo json_encode(['success' => '成功']);
    exit;
}

$dsn = "mysql:host=$host;dbname=$dbname;port=$port";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);

    if ($pdo === null) {
        http_response_code(500);
        echo json_encode(['error' => '数据库连接未定义']);
        exit;
    } else {
        $dbStatus = ['dbConnected' => true]; // 数据库连接成功
        
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}
// 测试连接 API
if ($request_uri === '/index.php/testConnection' && $request_method === 'GET') {
    echo json_encode(['dbStatus' => $dbStatus]);
    exit;
}



$response = []; // 初始化响应数组

// 登录验证 API
if ($request_uri === '/index.php/login' && $request_method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? "";
    $password = $input['password'] ?? "";
    
    // 确保 email 字段不为空
    if (empty($email) || empty($password)) {
        echo json_encode(['loginSuccess' => false, 'message' => '邮箱和密码不能为空']);
        exit;
    }

    // 查询数据库中的用户
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // 登录成功
        $response = array_merge($dbStatus, [
            'loginSuccess' => true,
            'username' => $user['username'],
            'email' => $user['email'],
            'id' => $user['id']
        ]);
    } else {
        // 登录失败
        $response = array_merge($dbStatus, ['loginSuccess' => false, 'message' => '无效的邮箱或密码']);
    }
} 

// 用户注册 API
if ($request_uri === '/index.php/register' && $request_method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $userName = trim($input['username'] ?? '');
    $password = trim($input['password'] ?? '');
    $email = trim($input['email'] ?? '');

    // 检查输入是否为空
    if (empty($userName) || empty($password) || empty($email)) {
        echo json_encode([
            'registerSuccess' => false,
            'message' => '用户名、密码和邮箱不能为空'
        ]);
        exit;
    }

    // 检查用户名或邮箱是否已存在
    $stmt = $pdo->prepare('SELECT username, email FROM users WHERE username = ? OR email = ?');
    $stmt->execute([$userName, $email]);
    $existingUser = $stmt->fetch();

    if ($existingUser) {
        $response = ['registerSuccess' => false];
        if ($existingUser['username'] === $userName) {
            $response['message'] = '用户名已存在';
            $response['username'] = false;
        }
        if ($existingUser['email'] === $email) {
            $response['message'] = '邮箱已存在';
            $response['email'] = false;
        }
        echo json_encode($response);
        exit;
    }

    // 获取最新用户的 ID，并计算新 ID
    $stmt = $pdo->query('SELECT MAX(id) AS max_id FROM users');
    $maxIdResult = $stmt->fetch();
    $newId = ($maxIdResult['max_id'] ?? 0) + 1;

    // 密码加密
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $createdAt = date('Y-m-d H:i:s');
    $updatedAt = $createdAt;

    // 插入新用户数据
    $insertStmt = $pdo->prepare(
        'INSERT INTO users (id, username, password, email, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)'
    );

    try {
        $insertStmt->execute([$newId, $userName, $hashedPassword, $email, $createdAt, $updatedAt]);
        $response = [
            'registerSuccess' => true,
            'message' => '用户注册成功'
        ];
    } catch (PDOException $e) {
        $response = [
            'registerSuccess' => false,
            'message' => '注册失败，请稍后再试'
        ];
    }

    echo json_encode($response);
    exit;
}

echo json_encode($response);
// 关闭连接
$pdo = null; // 关闭 PDO 连接
?> 