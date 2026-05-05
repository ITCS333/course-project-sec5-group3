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
    if(!is_numeric($id)) sendResponse(["success"=>false,"message"=>"Invalid id"],400);

    $stmt = $db->prepare("SELECT id,name,email FROM users WHERE id=?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$user) sendResponse(["success"=>false,"message"=>"User not found"],404);

    sendResponse(["success"=>true,"data"=>$user]);
}

function createUser($db,$data){

    if(empty($data['name']) || empty($data['email']) || empty($data['password'])){
        sendResponse(["success"=>false,"message"=>"Missing fields"],400);
    }

    if(strlen($data['password']) < 6){
        sendResponse(["success"=>false,"message"=>"Password too short"],400);
    }

    try{
        $stmt = $db->prepare("INSERT INTO users(name,email,password) VALUES(?,?,?)");
        $stmt->execute([
            sanitizeInput($data['name']),
            sanitizeInput($data['email']),
            password_hash($data['password'], PASSWORD_DEFAULT)
        ]);
    }catch(Exception $e){
        sendResponse(["success"=>false,"message"=>"Duplicate email"],409);
    }

    sendResponse(["success"=>true],201);
}

function updateUser($db,$data){

    if(empty($data['id'])) sendResponse(["success"=>false,"message"=>"Missing id"],400);

    $stmt = $db->prepare("SELECT id FROM users WHERE id=?");
    $stmt->execute([$data['id']]);

    if(!$stmt->fetch()) sendResponse(["success"=>false,"message"=>"User not found"],404);

    $stmt = $db->prepare("UPDATE users SET name=? WHERE id=?");
    $stmt->execute([
        sanitizeInput($data['name']),
        $data['id']
    ]);

    sendResponse(["success"=>true]);
}

function deleteUser($db,$id){

    if(!is_numeric($id)) sendResponse(["success"=>false,"message"=>"Invalid id"],400);

    $stmt = $db->prepare("SELECT id FROM users WHERE id=?");
    $stmt->execute([$id]);

    if(!$stmt->fetch()) sendResponse(["success"=>false,"message"=>"User not found"],404);

    $stmt = $db->prepare("DELETE FROM users WHERE id=?");
    $stmt->execute([$id]);

    sendResponse(["success"=>true]);
}


// ================= RESOURCES =================
function getAllResources($db) {

    $query = "SELECT id,title,description,link,created_at FROM resources";
    $params = [];

    if (!empty($_GET['search'])) {
        $query .= " WHERE title LIKE :search OR description LIKE :search";
        $params[':search'] = '%' . $_GET['search'] . '%';
    }

    $allowedSort = ['title','created_at'];
    $sort = $_GET['sort'] ?? 'created_at';
    if (!in_array($sort,$allowedSort)) $sort = 'created_at';

    $order = strtolower($_GET['order'] ?? 'desc');
    if (!in_array($order,['asc','desc'])) $order = 'desc';

    $query .= " ORDER BY $sort $order";

    $stmt = $db->prepare($query);

    foreach ($params as $key=>$val) {
        $stmt->bindValue($key,$val);
    }

    $stmt->execute();

    sendResponse(["success"=>true,"data"=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function getResourceById($db,$id){
    if(!is_numeric($id)) sendResponse(["success"=>false,"message"=>"Invalid id"],400);

    $stmt=$db->prepare("SELECT id,title,description,link,created_at FROM resources WHERE id=?");
    $stmt->execute([$id]);
    $res=$stmt->fetch(PDO::FETCH_ASSOC);

    if(!$res) sendResponse(["success"=>false,"message"=>"Resource not found"],404);

    sendResponse(["success"=>true,"data"=>$res]);
}

function createResource($db,$data){
    if(empty($data['title']) || empty($data['link'])){
        sendResponse(["success"=>false,"message"=>"Missing fields"],400);
    }

    if(!filter_var($data['link'], FILTER_VALIDATE_URL)){
        sendResponse(["success"=>false,"message"=>"Invalid URL"],400);
    }

    $desc = $data['description'] ?? '';

    $stmt=$db->prepare("INSERT INTO resources(title,description,link) VALUES(?,?,?)");

    $stmt->execute([
        sanitizeInput($data['title']),
        sanitizeInput($desc),
        $data['link']
    ]);

    sendResponse(["success"=>true,"id"=>$db->lastInsertId()],201);
}

function updateResource($db,$data){

    if(empty($data['id'])){
        sendResponse(["success"=>false,"message"=>"Missing id"],400);
    }

    $stmt = $db->prepare("SELECT id FROM resources WHERE id=?");
    $stmt->execute([$data['id']]);
    if(!$stmt->fetch()){
        sendResponse(["success"=>false,"message"=>"Not found"],404);
    }

    $fields = [];
    $values = [];

    if(isset($data['title'])){
        $fields[] = "title=?";
        $values[] = sanitizeInput($data['title']);
    }

    if(isset($data['description'])){
        $fields[] = "description=?";
        $values[] = sanitizeInput($data['description']);
    }

    if(isset($data['link'])){
        if(!filter_var($data['link'], FILTER_VALIDATE_URL)){
            sendResponse(["success"=>false,"message"=>"Invalid URL"],400);
        }
        $fields[] = "link=?";
        $values[] = $data['link'];
    }

    if(empty($fields)){
        sendResponse(["success"=>false,"message"=>"No fields"],400);
    }

    $values[] = $data['id'];

    $sql = "UPDATE resources SET ".implode(",",$fields)." WHERE id=?";

    $stmt = $db->prepare($sql);
    $stmt->execute($values);

    sendResponse(["success"=>true,"message"=>"Resource updated successfully."]);
}

function deleteResource($db,$id){

    if(!is_numeric($id)){
        sendResponse(["success"=>false,"message"=>"Invalid id"],400);
    }

    $stmt = $db->prepare("SELECT id FROM resources WHERE id=?");
    $stmt->execute([$id]);

    if(!$stmt->fetch()){
        sendResponse(["success"=>false,"message"=>"Not found"],404);
    }

    $stmt = $db->prepare("DELETE FROM resources WHERE id=?");
    $stmt->execute([$id]);

    sendResponse(["success"=>true,"message"=>"Deleted"]);
}


// ================= COMMENTS =================
function getCommentsByResourceId($db,$rid){
    if(!is_numeric($rid)){
        sendResponse(["success"=>false,"message"=>"Invalid id"],400);
    }

    $stmt=$db->prepare("SELECT id,resource_id,author,text,created_at FROM comments_resource WHERE resource_id=? ORDER BY created_at ASC");
    $stmt->execute([$rid]);

    sendResponse(["success"=>true,"data"=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function createComment($db,$data){

    if(empty($data['resource_id']) || empty($data['author']) || empty($data['text'])){
        sendResponse(["success"=>false,"message"=>"Missing fields"],400);
    }

    if(!is_numeric($data['resource_id'])){
        sendResponse(["success"=>false,"message"=>"Invalid id"],400);
    }

    $stmt = $db->prepare("SELECT id FROM resources WHERE id=?");
    $stmt->execute([$data['resource_id']]);

    if(!$stmt->fetch()){
        sendResponse(["success"=>false,"message"=>"Resource not found"],404);
    }

    $stmt=$db->prepare("INSERT INTO comments_resource(resource_id,author,text) VALUES(?,?,?)");

    $stmt->execute([
        $data['resource_id'],
        sanitizeInput($data['author']),
        sanitizeInput($data['text'])
    ]);

    sendResponse(["success"=>true,"id"=>$db->lastInsertId()],201);
}

function deleteComment($db,$cid){

    if(!is_numeric($cid)){
        sendResponse(["success"=>false,"message"=>"Invalid id"],400);
    }

    $stmt = $db->prepare("SELECT id FROM comments_resource WHERE id=?");
    $stmt->execute([$cid]);

    if(!$stmt->fetch()){
        sendResponse(["success"=>false,"message"=>"Comment not found"],404);
    }

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
    sendResponse(["success"=>false,"message"=>"Method not allowed"],405);
}

}catch(Exception $e){
    error_log($e->getMessage());
    sendResponse(["success"=>false,"message"=>"Server error"],500);
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
