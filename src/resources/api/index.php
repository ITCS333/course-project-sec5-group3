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

// ================= RESOURCE =================
function getAllResources($db) {
    $search = $_GET['search'] ?? null;
    $sort = in_array($_GET['sort'] ?? '', ['title','created_at']) ? $_GET['sort'] : 'created_at';
    $order = strtolower($_GET['order'] ?? '') === 'asc' ? 'ASC' : 'DESC';

    $sql = "SELECT id,title,description,link,created_at FROM resources";

    if ($search) {
        $sql .= " WHERE title LIKE :search OR description LIKE :search";
    }

    $sql .= " ORDER BY $sort $order";

    $stmt = $db->prepare($sql);

    if ($search) {
        $stmt->bindValue(':search', "%$search%");
    }

    $stmt->execute();
    $res = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse([
    "success"=>true,
    "data"=>array_values($res)
]);
}

function getResourceById($db,$id){
    if(!is_numeric($id)) sendResponse(["success"=>false,"message"=>"Invalid ID"],400);

    $stmt=$db->prepare("SELECT id,title,description,link,created_at FROM resources WHERE id=?");
    $stmt->execute([$id]);
    $res=$stmt->fetch(PDO::FETCH_ASSOC);

    if(!$res) sendResponse(["success"=>false,"message"=>"Resource not found"],404);

    sendResponse([
    "success"=>true,
    "data"=>$res ?? null
]);
}

function createResource($db,$data){
    $check=validateRequiredFields($data,["title","link"]);
    if(!$check['valid']) sendResponse(["success"=>false,"message"=>"Missing fields"],400);

    $title=sanitizeInput($data['title']);
    $desc=sanitizeInput($data['description'] ?? '');
    $link=$data['link'];

    if(!validateUrl($link)) sendResponse(["success"=>false,"message"=>"Invalid URL"],400);

    $stmt=$db->prepare("INSERT INTO resources(title,description,link) VALUES(?,?,?)");
    $stmt->execute([$title,$desc,$link]);

    sendResponse([
    "success"=>true,
    "data"=>[
        "id"=>$db->lastInsertId(),
        "title"=>$title,
        "description"=>$desc,
        "link"=>$link
    ]
],201);
}

function updateResource($db,$data){
    if(empty($data['id'])) sendResponse(["success"=>false],400);

    $stmt=$db->prepare("SELECT id FROM resources WHERE id=?");
    $stmt->execute([$data['id']]);
    if(!$stmt->fetch()) sendResponse(["success"=>false],404);

    $title=sanitizeInput($data['title'] ?? '');
    $desc=sanitizeInput($data['description'] ?? '');
    $link=$data['link'] ?? null;

    if($link && !validateUrl($link)) sendResponse(["success"=>false],400);

    $stmt=$db->prepare("UPDATE resources SET title=?,description=?,link=? WHERE id=?");
    $stmt->execute([$title,$desc,$link,$data['id']]);

    sendResponse([
    "success"=>true,
    "data"=>[
        "id"=>$data['id'],
        "title"=>$title,
        "description"=>$desc,
        "link"=>$link
    ]
]);
}

function deleteResource($db,$id){
    if(!is_numeric($id)) sendResponse(["success"=>false],400);

    $stmt=$db->prepare("SELECT id FROM resources WHERE id=?");
    $stmt->execute([$id]);
    if(!$stmt->fetch()) sendResponse(["success"=>false],404);

    $stmt=$db->prepare("DELETE FROM resources WHERE id=?");
    $stmt->execute([$id]);

    sendResponse(["success"=>true]);
}
function createUser($db,$data){

    if(empty($data['username']) || empty($data['email'])){
        sendResponse(["success"=>false],400);
    }

    $stmt = $db->prepare("INSERT INTO users (username,email) VALUES (?,?)");
    $stmt->execute([
        sanitizeInput($data['username']),
        sanitizeInput($data['email'])
    ]);

    sendResponse([
        "success"=>true,
        "data"=>[
            "username"=>$data['username'],
            "email"=>$data['email']
        ]
    ],201);
}
// ================= COMMENTS =================
function getCommentsByResourceId($db,$rid){
    if(!is_numeric($rid)) sendResponse(["success"=>false],400);

    $stmt=$db->prepare("SELECT id,resource_id,author,text,created_at FROM comments_resource WHERE resource_id=? ORDER BY created_at ASC");
    $stmt->execute([$rid]);

    sendResponse(["success"=>true,"data"=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function createComment($db,$data){
    $check=validateRequiredFields($data,["resource_id","author","text"]);
    if(!$check['valid']) sendResponse(["success"=>false],400);

    if(!is_numeric($data['resource_id'])) sendResponse(["success"=>false],400);

    $stmt=$db->prepare("SELECT id FROM resources WHERE id=?");
    $stmt->execute([$data['resource_id']]);
    if(!$stmt->fetch()) sendResponse(["success"=>false],404);

    $author=sanitizeInput($data['author']);
    $text=sanitizeInput($data['text']);

    $stmt=$db->prepare("INSERT INTO comments_resource(resource_id,author,text) VALUES(?,?,?)");
    $stmt->execute([$data['resource_id'],$author,$text]);

    sendResponse([
    "success"=>true,
    "data"=>[
        "id"=>$db->lastInsertId(),
        "resource_id"=>$data['resource_id'],
        "author"=>$author,
        "text"=>$text
    ]
],201);
}

function deleteComment($db,$cid){
    if(!is_numeric($cid)) sendResponse(["success"=>false],400);

    $stmt=$db->prepare("SELECT id FROM comments_resource WHERE id=?");
    $stmt->execute([$cid]);
    if(!$stmt->fetch()) sendResponse(["success"=>false],404);

    $stmt=$db->prepare("DELETE FROM comments_resource WHERE id=?");
    $stmt->execute([$cid]);

    sendResponse(["success"=>true]);
}

// ================= ROUTER =================
try {

if($method==="GET"){
    if($action==="comments") getCommentsByResourceId($db,$resource_id);
    elseif($id) getResourceById($db,$id);
    else getAllResources($db);
}

elseif($method==="POST"){

    //
    if(isset($data['username']) || isset($data['email'])){
        createUser($db,$data);
    }

    elseif($action==="comment"){
        createComment($db,$data);
    }

    else{
        createResource($db,$data);
    }
}
}

elseif($method==="PUT"){
    updateResource($db,$data);
}

elseif($method==="DELETE"){
    if($action==="delete_comment") deleteComment($db,$comment_id);
    else deleteResource($db,$id);
}

else{
    sendResponse(["success"=>false,"message"=>"Method Not Allowed"],405);
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

function validateUrl($url){
    return filter_var($url,FILTER_VALIDATE_URL);
}

function sanitizeInput($d){
    return htmlspecialchars(strip_tags(trim($d)),ENT_QUOTES,'UTF-8');
}

function validateRequiredFields($data,$fields){
    $missing=[];
    foreach($fields as $f){
        if(empty($data[$f])) $missing[]=$f;
    }
    return ["valid"=>empty($missing),"missing"=>$missing];
}

?>
