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

$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;
$resource_id = $_GET['resource_id'] ?? null;
$comment_id = $_GET['comment_id'] ?? null;
$user_id = $_GET['user_id'] ?? null;


// ================= USERS =================
function getAllUsers($db){
    $stmt = $db->prepare("SELECT id,name,email FROM users");
    $stmt->execute();
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
    $stmt->execute([
        sanitizeInput($data['name']),
        $data['id']
    ]);

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


// ================= RESOURCES =================
function getAllResources($db) {
    $stmt = $db->prepare("SELECT id,title,description,link,created_at FROM resources ORDER BY created_at DESC");
    $stmt->execute();
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

function createResource($db,$data){
    if(empty($data['title']) || empty($data['link'])){
        sendResponse(["success"=>false],400);
    }

    $stmt=$db->prepare("INSERT INTO resources(title,description,link) VALUES(?,?,?)");
    $stmt->execute([
        sanitizeInput($data['title']),
        sanitizeInput($data['description'] ?? ''),
        $data['link']
    ]);

    sendResponse(["success"=>true],201);
}

function updateResource($db,$data){
    if(empty($data['id'])) sendResponse(["success"=>false],400);

    $stmt=$db->prepare("UPDATE resources SET title=?,description=?,link=? WHERE id=?");
    $stmt->execute([
        $data['title'],
        $data['description'],
        $data['link'],
        $data['id']
    ]);

    sendResponse(["success"=>true]);
}

function deleteResource($db,$id){
    if(!is_numeric($id)) sendResponse(["success"=>false],400);

    $stmt=$db->prepare("DELETE FROM resources WHERE id=?");
    $stmt->execute([$id]);

    sendResponse(["success"=>true]);
}


// ================= COMMENTS =================
function getCommentsByResourceId($db,$rid){
    $stmt=$db->prepare("SELECT * FROM comments_resource WHERE resource_id=?");
    $stmt->execute([$rid]);
    sendResponse(["success"=>true,"data"=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function createComment($db,$data){
    $stmt=$db->prepare("INSERT INTO comments_resource(resource_id,author,text) VALUES(?,?,?)");
    $stmt->execute([$data['resource_id'],$data['author'],$data['text']]);

    sendResponse(["success"=>true],201);
}

function deleteComment($db,$cid){
    $stmt=$db->prepare("DELETE FROM comments_resource WHERE id=?");
    $stmt->execute([$cid]);

    sendResponse(["success"=>true]);
}


// ================= ROUTER =================
try {

if($method==="GET"){

    if($user_id){
        getUserById($db,$user_id);
    }
    elseif(isset($_GET['users'])){
        getAllUsers($db);
    }
    elseif($action==="comments"){
        getCommentsByResourceId($db,$resource_id);
    }
    elseif($id){
        getResourceById($db,$id);
    }
    else{
        getAllResources($db);
    }
}

elseif($method==="POST"){

    if(isset($data['name']) && isset($data['email'])){
        createUser($db,$data);
    }
    elseif($action==="comment"){
        createComment($db,$data);
    }
    else{
        createResource($db,$data);
    }
}

elseif($method==="PUT"){

    if(isset($data['name'])){
        updateUser($db,$data);
    } else {
        updateResource($db,$data);
    }
}

elseif($method==="DELETE"){

    if($user_id){
        deleteUser($db,$user_id);
    }
    elseif($action==="delete_comment"){
        deleteComment($db,$comment_id);
    }
    else{
        deleteResource($db,$id);
    }
}

else{
    sendResponse(["success"=>false],405);
}

}catch(Exception $e){
    error_log($e->getMessage());
    sendResponse(["success"=>false],500);
}


// ================= HELPERS =================
function sendResponse($data,$code=200){
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function sanitizeInput($d){
    return htmlspecialchars(strip_tags(trim($d)),ENT_QUOTES,'UTF-8');
}

?>
