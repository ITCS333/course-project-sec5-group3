<?php

header("Content-Type: application/json");

require_once './config/Database.php';

$db = (new Database())->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents("php://input"), true);

// ========= HELPER =========
function sendResponse($data,$code=200){
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// ========= RESOURCES =========

// GET ALL + SEARCH
function getAllResources($db,$search=null){
    if($search){
        $stmt = $db->prepare("SELECT * FROM resources WHERE title LIKE ? OR description LIKE ?");
        $stmt->execute(["%$search%","%$search%"]);
    }else{
        $stmt = $db->prepare("SELECT * FROM resources");
        $stmt->execute();
    }

    sendResponse([
        "success"=>true,
        "data"=>$stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

// GET BY ID
function getResource($db,$id){
    if(!is_numeric($id)) sendResponse(["success"=>false],400);

    $stmt = $db->prepare("SELECT * FROM resources WHERE id=?");
    $stmt->execute([$id]);

    $res = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$res) sendResponse(["success"=>false],404);

    sendResponse(["success"=>true,"data"=>$res]);
}

// CREATE
function createResource($db,$data){

    if(empty($data['title'])) sendResponse(["success"=>false],400);
    if(empty($data['link'])) sendResponse(["success"=>false],400);

    if(!filter_var($data['link'], FILTER_VALIDATE_URL)){
        sendResponse(["success"=>false],400);
    }

    $stmt = $db->prepare("INSERT INTO resources(title,description,link) VALUES(?,?,?)");
    $stmt->execute([
        $data['title'],
        $data['description'] ?? "",
        $data['link']
    ]);

    sendResponse([
        "success"=>true,
        "id"=>$db->lastInsertId()
    ],201);
}

// UPDATE
function updateResource($db,$data){

    if(empty($data['id'])) sendResponse(["success"=>false],400);

    $stmt = $db->prepare("SELECT id FROM resources WHERE id=?");
    $stmt->execute([$data['id']]);

    if(!$stmt->fetch()) sendResponse(["success"=>false],404);

    if(isset($data['link']) && !filter_var($data['link'], FILTER_VALIDATE_URL)){
        sendResponse(["success"=>false],400);
    }

    $stmt = $db->prepare("UPDATE resources SET title=?, description=?, link=? WHERE id=?");
    $stmt->execute([
        $data['title'],
        $data['description'],
        $data['link'],
        $data['id']
    ]);

    sendResponse(["success"=>true]);
}

// DELETE
function deleteResource($db,$id){

    if(!is_numeric($id)) sendResponse(["success"=>false],400);

    $stmt = $db->prepare("SELECT id FROM resources WHERE id=?");
    $stmt->execute([$id]);

    if(!$stmt->fetch()) sendResponse(["success"=>false],404);

    $stmt = $db->prepare("DELETE FROM resources WHERE id=?");
    $stmt->execute([$id]);

    sendResponse(["success"=>true]);
}

// ========= COMMENTS =========

// GET COMMENTS
function getComments($db,$resource_id){

    if(!is_numeric($resource_id)) sendResponse(["success"=>false],400);

    $stmt = $db->prepare("SELECT * FROM comments_resource WHERE resource_id=?");
    $stmt->execute([$resource_id]);

    sendResponse([
        "success"=>true,
        "data"=>$stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

// CREATE COMMENT
function createComment($db,$data){

    if(empty($data['resource_id']) || empty($data['text'])){
        sendResponse(["success"=>false],400);
    }

    $stmt = $db->prepare("SELECT id FROM resources WHERE id=?");
    $stmt->execute([$data['resource_id']]);

    if(!$stmt->fetch()){
        sendResponse(["success"=>false],404);
    }

    $stmt = $db->prepare("INSERT INTO comments_resource(resource_id,author,text) VALUES(?,?,?)");
    $stmt->execute([
        $data['resource_id'],
        $data['author'] ?? "anonymous",
        $data['text']
    ]);

    sendResponse(["success"=>true],201);
}

// DELETE COMMENT
function deleteComment($db,$id){

    if(!is_numeric($id)) sendResponse(["success"=>false],400);

    $stmt = $db->prepare("SELECT id FROM comments_resource WHERE id=?");
    $stmt->execute([$id]);

    if(!$stmt->fetch()) sendResponse(["success"=>false],404);

    $stmt = $db->prepare("DELETE FROM comments_resource WHERE id=?");
    $stmt->execute([$id]);

    sendResponse(["success"=>true]);
}

// ========= ROUTER (SMART) =========

try{

    // ===== GET =====
    if($method === "GET"){

        $id = $_GET['id'] ?? $_GET['resource_id'] ?? $_GET['resourceId'] ?? null;
        $search = $_GET['search'] ?? $_GET['q'] ?? null;

        if(isset($_GET['comments']) || isset($_GET['resource_id']) && !$id){
            $rid = $_GET['comments'] ?? $_GET['resource_id'] ?? null;
            getComments($db,$rid);
        }
        elseif($id){
            getResource($db,$id);
        }
        else{
            getAllResources($db,$search);
        }
    }

    // ===== POST =====
    elseif($method === "POST"){

        if(isset($_GET['comment']) || isset($_GET['comments'])){
            createComment($db,$data);
        }else{
            createResource($db,$data);
        }
    }

    // ===== PUT =====
    elseif($method === "PUT"){
        updateResource($db,$data);
    }

    // ===== DELETE =====
    elseif($method === "DELETE"){

        $id = $_GET['id'] ?? $_GET['resource_id'] ?? $_GET['resourceId'] ?? null;
        $comment_id = $_GET['comment_id'] ?? $_GET['commentId'] ?? null;

        if($comment_id){
            deleteComment($db,$comment_id);
        }
        elseif($id){
            deleteResource($db,$id);
        }
        else{
            sendResponse(["success"=>false],400);
        }
    }

    else{
        sendResponse(["success"=>false],405);
    }

}catch(Exception $e){
    sendResponse(["success"=>false],500);
}
