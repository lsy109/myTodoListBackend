<?php
header('Content-Type: application/json'); // 设置响应内容类型为 JSON
header('Access-Control-Allow-Origin: *'); // 允许跨域请求
header('Access-Control-Allow-Methods: GET, POST, OPTIONS'); // 允许的请求方法
header('Access-Control-Allow-Headers: Content-Type'); // 允许的请求头

// 简单的路由机制
$request_method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI']; // 去掉末尾斜杠

// 数据库连接信息
$host = getenv('DB_HOST');
$username = getenv('DB_USERNAME');
$password = getenv('DB_PASSWORD');
$dbname = getenv('DB_NAME');
$port = getenv('DB_PORT');                   // 端口


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
if ($request_uri === '/myLoginBackEnd.php/testConnection' && $request_method === 'GET') {
    echo json_encode(['dbStatus' => $dbStatus]);
    exit;
}

// 根目录 API
if ($request_uri === '/myLoginBackEnd.php' && $request_method === 'GET') {
    echo json_encode(['success' => '成功']);
    exit;
}

$response = []; // 初始化响应数组

// 登录验证 API
if ($request_uri === '/myLoginBackEnd.php/login' && $request_method === 'POST') {
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

// Todo添加API
if ($request_uri === '/myLoginBackEnd.php/addtodo' && $request_method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    error_log('Received input: ' . print_r($input, true));  // 打印收到的输入数据
    $id = $input['id'] ?? null;
    $user_id = $input['user_id'] ?? null;
    $description = $input['description'] ?? '';
    $status = $input['status'] ?? 0;
    $created_at = $input['created_at'] ?? date('Y-m-d H:i:s'); // 使用当前时间戳
    $priority = $input['priority'] ?? 'green'; // 默认值为 'green'

    // 检查必要字段是否存在
    if (empty($id) || empty($user_id) || empty($description)) {
        echo json_encode([
            'addSuccess' => false,
            'message' => '缺少必要字段'
        ]);
        exit;
    }

    // 插入到 `todos` 表
    $stmt = $pdo->prepare(
        'INSERT INTO todos (id, user_id, description, status, created_at, priority) VALUES (?, ?, ?, ?, ?, ?)'
    );

    try {
        $stmt->execute([$id, $user_id, $description, $status, $created_at, $priority]);
        $response = [
            'addSuccess' => true,
            'message' => 'Todo项添加成功'
        ];
    } catch (PDOException $e) {
        $response = [
            'addSuccess' => false,
            'message' => '添加失败，请稍后再试'
        ];
    }

    echo json_encode($response);
    exit;
}

echo json_encode($response);
// 关闭连接
$pdo = null; // 关闭 PDO 连接
?>
