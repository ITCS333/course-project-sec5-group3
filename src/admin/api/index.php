<?php
// --- إعدادات الـ Headers ---
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// --- التعامل مع Preflight OPTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --- الاتصال بقاعدة البيانات ---
require_once 'db_connection.php'; // تأكد أن ملف الاتصال يحتوي على دالة getDBConnection()
$db = getDBConnection();

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents('php://input'), true);
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$action = $_GET['action'] ?? null;

// --- الدوال الأساسية ---

function getUsers($db) {
    $sql = "SELECT id, name, email, is_admin, created_at FROM users";
    $params = [];
    
    // الفلترة
    if (!empty($_GET['search'])) {
        $sql .= " WHERE name LIKE :search OR email LIKE :search";
        $params['search'] = '%' . $_GET['search'] . '%';
    }

    // الترتيب
    $allowed = ['name', 'email', 'is_admin'];
    $sort = in_array($_GET['sort'] ?? '', $allowed) ? $_GET['sort'] : 'id';
    $order = (isset($_GET['order']) && strtolower($_GET['order']) === 'desc') ? 'DESC' : 'ASC';
    $sql .= " ORDER BY $sort $order";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    sendResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function getUserById($db, $id) {
    $stmt = $db->prepare("SELECT id, name, email, is_admin, created_at FROM users WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $user ? sendResponse($user) : sendResponse("User not found", 404);
}

function createUser($db, $data) {
    if (empty($data['name']) || empty($data['email']) || empty($data['password'])) 
        sendResponse("Missing fields", 400);

    $email = $data['email'];
    if (!validateEmail($email) || strlen($data['password']) < 8) 
        sendResponse("Invalid input", 400);

    $stmt = $db->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->execute(['email' => $email]);
    if ($stmt->fetch()) sendResponse("Email exists", 409);

    $hash = password_hash($data['password'], PASSWORD_DEFAULT);
    $isAdmin = isset($data['is_admin']) ? (int)$data['is_admin'] : 0;

    $stmt = $db->prepare("INSERT INTO users (name, email, password, is_admin) VALUES (:name, :email, :password, :is_admin)");
    $stmt->execute([
        'name' => sanitizeInput($data['name']),
        'email' => $email,
        'password' => $hash,
        'is_admin' => $isAdmin
    ]);
    sendResponse(['id' => $db->lastInsertId()], 201);
}

function updateUser($db, $data) {
    if (empty($data['id'])) sendResponse("ID required", 400);

    $stmt = $db->prepare("SELECT id FROM users WHERE id = :id");
    $stmt->execute(['id' => $data['id']]);
    if (!$stmt->fetch()) sendResponse("User not found", 404);

    $fields = [];
    $params = ['id' => $data['id']];
    foreach (['name', 'email', 'is_admin'] as $key) {
        if (isset($data[$key])) {
            $fields[] = "$key = :$key";
            $params[$key] = sanitizeInput($data[$key]);
        }
    }

    $stmt = $db->prepare("UPDATE users SET " . implode(", ", $fields) . " WHERE id = :id");
    $stmt->execute($params);
    sendResponse("User updated");
}

function deleteUser($db, $id) {
    if (!$id) sendResponse("Invalid ID", 400);
    $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
    $stmt->execute(['id' => $id]);
    sendResponse("User deleted");
}

function changePassword($db, $data) {
    $stmt = $db->prepare("SELECT password FROM users WHERE id = :id");
    $stmt->execute(['id' => $data['id']]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($data['current_password'], $user['password']))
        sendResponse("Unauthorized", 401);

    $hash = password_hash($data['new_password'], PASSWORD_DEFAULT);
    $stmt = $db->prepare("UPDATE users SET password = :password WHERE id = :id");
    $stmt->execute(['password' => $hash, 'id' => $data['id']]);
    sendResponse("Password updated");
}

// --- الموجه الرئيسي ---
try {
    if ($method === 'GET') { $id ? getUserById($db, $id) : getUsers($db); }
    elseif ($method === 'POST') { $action === 'change_password' ? changePassword($db, $data) : createUser($db, $data); }
    elseif ($method === 'PUT') { updateUser($db, $data); }
    elseif ($method === 'DELETE') { deleteUser($db, $id); }
    else { sendResponse("Method Not Allowed", 405); }
} catch (Exception $e) { sendResponse("Internal Server Error", 500); }

// --- دوال المساعدة ---
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($statusCode < 400 ? ['success' => true, 'data' => $data] : ['success' => false, 'message' => $data]);
    exit();
}

function validateEmail($email) { return (bool) filter_var($email, FILTER_VALIDATE_EMAIL); }

function sanitizeInput($data) { return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8'); }
?>
