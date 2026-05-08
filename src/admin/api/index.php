<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if (!function_exists('getDBConnection')) {
    $dbFile = __DIR__ . '/db.php';
    if (file_exists($dbFile)) {
        require_once $dbFile;
    } else {
        require_once __DIR__ . '/db_connection.php';
    }
}

$db     = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];
$raw    = file_get_contents('php://input');
$data   = json_decode($raw, true) ?? [];
$id     = isset($_GET['id'])     ? (int)$_GET['id']    : null;
$action = $_GET['action']        ?? null;



function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    if ($statusCode < 400) {
        echo json_encode(['success' => true,  'data'    => $data]);
    } else {
        echo json_encode(['success' => false, 'message' => $data]);
    }
    exit();
}

function validateEmail($email) {
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}



function getUsers($db) {
    $sql    = "SELECT id, name, email, is_admin, created_at FROM users";
    $params = [];

    if (!empty($_GET['search'])) {
        $sql .= " WHERE name LIKE :search OR email LIKE :search";
        $params['search'] = '%' . $_GET['search'] . '%';
    }

    $allowed = ['name', 'email', 'is_admin'];
    $sort    = in_array($_GET['sort'] ?? '', $allowed) ? $_GET['sort'] : 'id';
    $order   = (isset($_GET['order']) && strtolower($_GET['order']) === 'desc') ? 'DESC' : 'ASC';
    $sql    .= " ORDER BY $sort $order";

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
    if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
        sendResponse("Missing required fields: name, email, and password are required", 400);
    }

    $name     = trim($data['name']);
    $email    = trim($data['email']);
    $password = trim($data['password']);

    if (!validateEmail($email)) {
        sendResponse("Invalid email address", 400);
    }

    if (strlen($password) < 8) {
        sendResponse("Password must be at least 8 characters", 400);
    }

    $stmt = $db->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->execute(['email' => $email]);
    if ($stmt->fetch()) {
        sendResponse("Email already exists", 409);
    }

    $hash    = password_hash($password, PASSWORD_DEFAULT);
    $isAdmin = isset($data['is_admin']) ? (int)$data['is_admin'] : 0;
    $isAdmin = in_array($isAdmin, [0, 1]) ? $isAdmin : 0;

    $stmt = $db->prepare(
        "INSERT INTO users (name, email, password, is_admin) VALUES (:name, :email, :password, :is_admin)"
    );
    $ok = $stmt->execute([
        'name'     => sanitizeInput($name),
        'email'    => $email,
        'password' => $hash,
        'is_admin' => $isAdmin,
    ]);

    if ($ok) {
        sendResponse(['id' => $db->lastInsertId()], 201);
    } else {
        sendResponse("Failed to create user", 500);
    }
}

function updateUser($db, $data) {
    if (empty($data['id'])) {
        sendResponse("ID is required", 400);
    }

    $userId = (int)$data['id'];

    $stmt = $db->prepare("SELECT id FROM users WHERE id = :id");
    $stmt->execute(['id' => $userId]);
    if (!$stmt->fetch()) {
        sendResponse("User not found", 404);
    }

    if (!empty($data['email'])) {
        if (!validateEmail($data['email'])) {
            sendResponse("Invalid email address", 400);
        }
        $stmt = $db->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
        $stmt->execute(['email' => $data['email'], 'id' => $userId]);
        if ($stmt->fetch()) {
            sendResponse("Email already exists", 409);
        }
    }

    $fields = [];
    $params = ['id' => $userId];
    foreach (['name', 'email', 'is_admin'] as $key) {
        if (isset($data[$key])) {
            $fields[]     = "$key = :$key";
            $params[$key] = ($key === 'is_admin') ? (int)$data[$key] : sanitizeInput($data[$key]);
        }
    }

    if (empty($fields)) {
        sendResponse("User updated");
    }

    $stmt = $db->prepare("UPDATE users SET " . implode(", ", $fields) . " WHERE id = :id");
    $ok   = $stmt->execute($params);

    $ok ? sendResponse("User updated") : sendResponse("Failed to update user", 500);
}

function deleteUser($db, $id) {
    if (!$id) {
        sendResponse("Invalid ID", 400);
    }

    $stmt = $db->prepare("SELECT id FROM users WHERE id = :id");
    $stmt->execute(['id' => $id]);
    if (!$stmt->fetch()) {
        sendResponse("User not found", 404);
    }

    $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
    $ok   = $stmt->execute(['id' => $id]);

    $ok ? sendResponse("User deleted") : sendResponse("Failed to delete user", 500);
}

function changePassword($db, $data) {
    if (empty($data['id']) || empty($data['current_password']) || empty($data['new_password'])) {
        sendResponse("Missing required fields: id, current_password, and new_password are required", 400);
    }

    if (strlen($data['new_password']) < 8) {
        sendResponse("New password must be at least 8 characters", 400);
    }

    $stmt = $db->prepare("SELECT password FROM users WHERE id = :id");
    $stmt->execute(['id' => $data['id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        sendResponse("User not found", 404);
    }

    if (!password_verify($data['current_password'], $user['password'])) {
        sendResponse("Current password is incorrect", 401);
    }

    $hash = password_hash($data['new_password'], PASSWORD_DEFAULT);
    $stmt = $db->prepare("UPDATE users SET password = :password WHERE id = :id");
    $ok   = $stmt->execute(['password' => $hash, 'id' => $data['id']]);

    $ok ? sendResponse("Password updated") : sendResponse("Failed to update password", 500);
}



try {
    if ($method === 'GET') {
        $id ? getUserById($db, $id) : getUsers($db);

    } elseif ($method === 'POST') {
        $action === 'change_password' ? changePassword($db, $data) : createUser($db, $data);

    } elseif ($method === 'PUT') {
        updateUser($db, $data);

    } elseif ($method === 'DELETE') {
        deleteUser($db, $id);

    } else {
        sendResponse("Method Not Allowed", 405);
    }

} catch (PDOException $e) {
    error_log($e->getMessage());
    sendResponse("Database error", 500);

} catch (Exception $e) {
    sendResponse($e->getMessage(), 500);
}
?>
