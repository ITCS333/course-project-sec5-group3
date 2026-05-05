<?php

// ================= HEADERS =================
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ================= DB =================
require_once './config/Database.php';
$database = new Database();
$db = $database->getConnection();

// ================= INPUT =================
$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents("php://input"), true);

// ================= HELPERS =================
function sendResponse($data,$code=200){
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function sanitizeInput($d){
    return htmlspecialchars(strip_tags(trim($d)),ENT_QUOTES,'UTF-8');
}

// ================= USERS =================
function getAllUsers($db,$search=null){
    if($search){
        $stmt = $db->prepare("SELECT id,name,email FROM users WHERE name LIKE ?");
        $stmt->execute(['%'.$search.'%']);
    } else {
        $stmt = $db->query("SELECT id,name,email FROM users");
    }
    sendResponse(["success"=>true,"data"=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function getUserById($db,$id){
    if(!is_numeric($id)) sendResponse(["success"=>false],400);

    $stmt = $db->prepare("SELECT id,name,email FROM users WHERE id=?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$user) sendResponse(["success"=>false],404);

    sendResponse(["success"=>true,"data"=>$user]);
}

function createUser($db,$data){
    if(empty($data['name']) || empty($data['email']) || empty($data['password'])){
        sendResponse(["success"=>false],400);
    }

    if(strlen($data['password']) < 6){
        sendResponse(["success"=>false],400);
    }

    try{
        $stmt = $db->prepare("INSERT INTO users(name,email,password) VALUES(?,?,?)");
        $stmt->execute([
            sanitizeInput($data['name']),
            sanitizeInput($data['email']),
            password_hash($data['password'], PASSWORD_DEFAULT)
        ]);
    }catch(Exception $e){
        sendResponse(["success"=>false],409);
    }

    sendResponse(["success"=>true],201);
}

function updateUser($db,$data){
    if(empty($data['id'])) sendResponse(["success"=>false],400);

    $stmt = $db->prepare("SELECT id FROM users WHERE id=?");
    $stmt->execute([$data['id']]);
    if(!$stmt->fetch()) sendResponse(["success"=>false],404);

    $stmt = $db->prepare("UPDATE users SET name=? WHERE id=?");
    $stmt->execute([sanitizeInput($data['name']), $data['id']]);

    sendResponse(["success"=>true]);
}

function deleteUser($db,$id){
    if(!is_numeric($id)) sendResponse(["success"=>false],400);

    $stmt = $db->prepare("SELECT id FROM users WHERE id=?");
    $stmt->execute([$id]);
    if(!$stmt->fetch()) sendResponse(["success"=>false],404);

    $stmt = $db->prepare("DELETE FROM users WHERE id=?");
    $stmt->execute([$id]);

    sendResponse(["success"=>true]);
}

function changePassword($db,$data){
    if(empty($data['user_id']) || empty($data['current_password']) || empty($data['new_password'])){
        sendResponse(["success"=>false],400);
    }

    if(strlen($data['new_password']) < 6){
        sendResponse(["success"=>false],400);
    }

    $stmt = $db->prepare("SELECT password FROM users WHERE id=?");
    $stmt->execute([$data['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$user) sendResponse(["success"=>false],404);

    if(!password_verify($data['current_password'],$user['password'])){
        sendResponse(["success"=>false],401);
    }

    $stmt = $db->prepare("UPDATE users SET password=? WHERE id=?");
    $stmt->execute([
        password_hash($data['new_password'], PASSWORD_DEFAULT),
        $data['user_id']
    ]);

    sendResponse(["success"=>true]);
}

// ================= RESOURCES =================
function getAllResources($db){
    $stmt = $db->query("SELECT id,title,description,link,created_at FROM resources");
    sendResponse(["success"=>true,"data"=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function getResourceById($db,$id){
    if(!is_numeric($id)) sendResponse(["success"=>false],400);

    $stmt=$db->prepare("SELECT id,title,description,link,created_at FROM resources WHERE id=?");
    $stmt->execute([$id]);
    $res=$stmt->fetch(PDO::FETCH_ASSOC);

    if(!$res) sendResponse(["success"=>false],404);

    sendResponse(["success"=>true,"data"=>$res]);
}

// ================= WEEKS =================
function getAllWeeks($db){
    $stmt = $db->query("SELECT * FROM weeks");
    sendResponse(["success"=>true,"data"=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

// ================= ROUTER =================
try {

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', trim($path,'/'));

$entity = $segments[count($segments)-2] ?? null;
$id = $segments[count($segments)-1] ?? null;

// USERS
if($entity === "users"){

    if($method === "GET"){
        if(is_numeric($id)){
            getUserById($db,$id);
        } else {
            getAllUsers($db,$_GET['search'] ?? null);
        }
    }

    elseif($method === "POST"){
        if(isset($data['current_password'])){
            changePassword($db,$data);
        } else {
            createUser($db,$data);
        }
    }

    elseif($method === "PUT"){
        updateUser($db,$data);
    }

    elseif($method === "DELETE"){
        deleteUser($db,$id);
    }

    else sendResponse(["success"=>false],405);
}

// RESOURCES
elseif($entity === "resources"){

    if($method === "GET"){
        if(is_numeric($id)){
            getResourceById($db,$id);
        } else {
            getAllResources($db);
        }
    }

    else sendResponse(["success"=>false],405);
}

// WEEKS
elseif($entity === "weeks"){

    if($method === "GET"){
        getAllWeeks($db);
    }

    else sendResponse(["success"=>false],405);
}

else{
    sendResponse(["success"=>false],404);
}

}catch(Exception $e){
    sendResponse(["success"=>false],500);
}
?>
