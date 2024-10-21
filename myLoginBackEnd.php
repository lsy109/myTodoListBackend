<?php
header('Content-Type: application/json'); // 设置响应内容类型为 JSON
header('Access-Control-Allow-Origin: *'); // 允许跨域请求
header('Access-Control-Allow-Methods: GET, POST, OPTIONS'); // 允许的请求方法
header('Access-Control-Allow-Headers: Content-Type'); // 允许的请求头

// 简单的路由机制
$request_method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI']; // 去掉末尾斜杠

// 数据库连接参数
$host = 'localhost'; // 主机名
$user = 'root';      // 用户名
$password = '12345678';      // 密码
$database = 'user'; // 数据库名

$dsn = "mysql:host=$host;dbname=$database;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $password, $options);
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

// 根目录api
if ($request_uri === '/myLoginBackEnd.php' && $request_method === 'GET') {
    echo json_encode(['success' => '成功']);
    exit;
}

$response = []; // 初始化响应数组

// 登录验证api
if ($request_uri === '/myLoginBackEnd.php/login' && $request_method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? "";
    $password = $input['password'] ?? "";
    $stmt = $pdo->prepare('SELECT * FROM user_info WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $response = array_merge($dbStatus, [
            'loginSuccess' => true,
            'username' => $user['username'],
            'email' => $user['email'],
            'id' => $user['id']
        ]);
    } else {
        $response = array_merge($dbStatus, ['loginSuccess' => false]);
    }
} 

if ($request_uri === '/myLoginBackEnd.php/register' && $request_method === 'POST') {
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
    $stmt = $pdo->prepare('SELECT username, email FROM user_info WHERE username = ? OR email = ?');
    $stmt->execute([$userName, $email]);
    $existingUser = $stmt->fetch();

    $response = ['registerSuccess' => false];
    if ($existingUser) {
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

    // 获取最新一笔用户的 ID，并计算新 ID
    $stmt = $pdo->query('SELECT MAX(id) AS max_id FROM user_info');
    $maxIdResult = $stmt->fetch();
    $newId = ($maxIdResult['max_id'] ?? 0) + 1;

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $createdAt = date('Y-m-d H:i:s'); // 创建时间

    $insertStmt = $pdo->prepare(
        'INSERT INTO user_info (id, username, password, email, created_at) VALUES (?, ?, ?, ?, ?)'
    );

    try {
        $insertStmt->execute([$newId, $userName, $hashedPassword, $email, $createdAt]);
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
