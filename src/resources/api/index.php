<?php

header("Content-Type: application/json");

require_once './config/Database.php';
$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents("php://input"), true);

$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;
$search = $_GET['search'] ?? null;

// ================= USERS =================

function getAllUsers($db, $search){
    if($search){
        $stmt = $db->prepare("SELECT id,name,email FROM users WHERE name LIKE ?");
        $stmt->execute(["%$search%"]);
    } else {
        $stmt = $db->prepare("SELECT id,name,email FROM users");
        $stmt->execute();
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
            $data['name'],
            $data['email'],
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
    $stmt->execute([$data['name'],$data['id']]);

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

// ⭐ CHANGE PASSWORD (مهم جدًا للتست)
function changePassword($db,$data){

    if(empty($data['id']) || empty($data['current_password']) || empty($data['new_password'])){
        sendResponse(["success"=>false],400);
    }

    if(strlen($data['new_password']) < 6){
        sendResponse(["success"=>false],400);
    }

    $stmt = $db->prepare("SELECT password FROM users WHERE id=?");
    $stmt->execute([$data['id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$user) sendResponse(["success"=>false],404);

    if(!password_verify($data['current_password'],$user['password'])){
        sendResponse(["success"=>false],401);
    }

    $stmt = $db->prepare("UPDATE users SET password=? WHERE id=?");
    $stmt->execute([
        password_hash($data['new_password'], PASSWORD_DEFAULT),
        $data['id']
    ]);

    sendResponse(["success"=>true]);
}

// ================= ROUTER =================

try {

if($method==="GET"){

    if($id){
        getUserById($db,$id);
    } else {
        getAllUsers($db,$search);
    }
}

elseif($method==="POST"){

    if($action==="change_password"){
        changePassword($db,$data);
    } else {
        createUser($db,$data);
    }
}

elseif($method==="PUT"){
    updateUser($db,$data);
}

elseif($method==="DELETE"){
    if($id){
        deleteUser($db,$id);
    } else {
        sendResponse(["success"=>false],400);
    }
}

else{
    sendResponse(["success"=>false],405);
}

}catch(Exception $e){
    sendResponse(["success"=>false],500);
}

// ================= HELPER =================

function sendResponse($data,$code=200){
    http_response_code($code);
    echo json_encode($data);
    exit;
}

?>
