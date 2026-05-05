<?php

header("Content-Type: application/json");

require_once './config/Database.php';

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents("php://input"), true);

// ================= RESOURCES =================

// GET ALL
function getAllResources($db){
    $stmt = $db->prepare("SELECT id,title,description,link FROM resources");
    $stmt->execute();

    sendResponse([
        "success" => true,
        "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

// GET BY ID
function getResourceById($db, $id){
    if(!is_numeric($id)){
        sendResponse(["success"=>false],400);
    }

    $stmt = $db->prepare("SELECT id,title,description,link FROM resources WHERE id=?");
    $stmt->execute([$id]);

    $resource = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$resource){
        sendResponse(["success"=>false],404);
    }

    sendResponse([
        "success"=>true,
        "data"=>$resource
    ]);
}

// CREATE
function createResource($db, $data){

    if(empty($data['title']) || empty($data['description']) || empty($data['link'])){
        sendResponse(["success"=>false],400);
    }

    $stmt = $db->prepare("INSERT INTO resources(title,description,link) VALUES(?,?,?)");

    $stmt->execute([
        $data['title'],
        $data['description'],
        $data['link']
    ]);

    sendResponse(["success"=>true],201);
}

// DELETE
function deleteResource($db, $id){

    if(!is_numeric($id)){
        sendResponse(["success"=>false],400);
    }

    $stmt = $db->prepare("SELECT id FROM resources WHERE id=?");
    $stmt->execute([$id]);

    if(!$stmt->fetch()){
        sendResponse(["success"=>false],404);
    }

    $stmt = $db->prepare("DELETE FROM resources WHERE id=?");
    $stmt->execute([$id]);

    sendResponse(["success"=>true]);
}

// ================= COMMENTS =================

// GET COMMENTS BY RESOURCE
function getComments($db, $resource_id){

    if(!is_numeric($resource_id)){
        sendResponse(["success"=>false],400);
    }

    $stmt = $db->prepare("SELECT id,resource_id,author,text FROM comments_resource WHERE resource_id=?");
    $stmt->execute([$resource_id]);

    sendResponse([
        "success"=>true,
        "data"=>$stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

// CREATE COMMENT
function createComment($db, $data){

    if(empty($data['resource_id']) || empty($data['author']) || empty($data['text'])){
        sendResponse(["success"=>false],400);
    }

    // check resource exists
    $stmt = $db->prepare("SELECT id FROM resources WHERE id=?");
    $stmt->execute([$data['resource_id']]);

    if(!$stmt->fetch()){
        sendResponse(["success"=>false],404);
    }

    $stmt = $db->prepare("INSERT INTO comments_resource(resource_id,author,text) VALUES(?,?,?)");
    $stmt->execute([
        $data['resource_id'],
        $data['author'],
        $data['text']
    ]);

    sendResponse(["success"=>true],201);
}

// DELETE COMMENT
function deleteComment($db, $id){

    if(!is_numeric($id)){
        sendResponse(["success"=>false],400);
    }

    $stmt = $db->prepare("SELECT id FROM comments_resource WHERE id=?");
    $stmt->execute([$id]);

    if(!$stmt->fetch()){
        sendResponse(["success"=>false],404);
    }

    $stmt = $db->prepare("DELETE FROM comments_resource WHERE id=?");
    $stmt->execute([$id]);

    sendResponse(["success"=>true]);
}

// ================= ROUTER =================

try{

    // ROUTES BASED ON QUERY PARAMS
    if(isset($_GET['comments'])){
        if($method === "GET"){
            getComments($db, $_GET['comments']);
        }
        elseif($method === "POST"){
            createComment($db, $data);
        }
        else{
            sendResponse(["success"=>false],405);
        }
    }

    elseif(isset($_GET['comment_id'])){
        if($method === "DELETE"){
            deleteComment($db, $_GET['comment_id']);
        } else {
            sendResponse(["success"=>false],405);
        }
    }

    else{
        if($method === "GET"){
            if(isset($_GET['id'])){
                getResourceById($db, $_GET['id']);
            } else {
                getAllResources($db);
            }
        }

        elseif($method === "POST"){
            createResource($db, $data);
        }

        elseif($method === "DELETE"){
            if(isset($_GET['id'])){
                deleteResource($db, $_GET['id']);
            } else {
                sendResponse(["success"=>false],400);
            }
        }

        else{
            sendResponse(["success"=>false],405);
        }
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
