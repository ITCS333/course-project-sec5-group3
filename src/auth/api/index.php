<?php
/**
 * Authentication Handler for Login Form
 */

// --- Session Management ---
session_start();

// --- Set Response Headers ---
header('Content-Type: application/json');

// --- Check Request Method ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// --- Get POST Data ---
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// --- Validate Input Existence ---
if (!isset($data['email']) || !isset($data['password'])) {
    echo json_encode(['success' => false, 'message' => 'Missing email or password']);
    exit;
}

$email = trim($data['email']);
$password = $data['password'];

// --- Server-Side Validation ---
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
    exit;
}

// --- Database Connection ---
require_once 'db.php'; 
try {
    $pdo = getDBConnection();

    // --- Prepare and Execute Query ---
    $sql = "SELECT id, name, email, password, is_admin FROM users WHERE email = :email";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // --- Verify User and Password ---
    if ($user && password_verify($password, $user['password'])) {
        
        // --- Handle Successful Authentication ---
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['is_admin'] = $user['is_admin'];
        $_SESSION['logged_in'] = true;

        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'is_admin' => $user['is_admin']
            ]
        ]);
        exit;

    } else {
        // --- Handle Failed Authentication ---
        echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
        exit;
    }

} catch (PDOException $e) {
    // --- Error Handling ---
    error_log($e->getMessage()); 
    echo json_encode(['success' => false, 'message' => 'An internal server error occurred']);
    exit;
}
?>
