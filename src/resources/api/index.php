<?php

header("Content-Type: application/json");

require_once __DIR__ . '/../../common/db.php';

$db = getDBConnection();

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents("php://input"), true);

// ========= HELPER =========

function sendResponse($data, $code = 200){
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// ========= RESOURCES =========

// GET ALL + SEARCH
function getAllResources($db, $search = null){

    if($search){

        $stmt = $db->prepare("
            SELECT * FROM resources
            WHERE title LIKE ?
            OR description LIKE ?
        ");

        $stmt->execute([
            "%$search%",
            "%$search%"
        ]);

    } else {

        $stmt = $db->prepare("
            SELECT * FROM resources
        ");

        $stmt->execute();
    }

    sendResponse([
        "success" => true,
        "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

// GET RESOURCE BY ID
function getResource($db, $id){

    if(!is_numeric($id)){
        sendResponse(["success" => false], 400);
    }

    $stmt = $db->prepare("
        SELECT * FROM resources
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    $resource = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$resource){
        sendResponse(["success" => false], 404);
    }

    sendResponse([
        "success" => true,
        "data" => $resource
    ]);
}

// CREATE RESOURCE
function createResource($db, $data){

    if(empty($data['title'])){
        sendResponse(["success" => false], 400);
    }

    if(empty($data['link'])){
        sendResponse(["success" => false], 400);
    }

    if(!filter_var($data['link'], FILTER_VALIDATE_URL)){
        sendResponse(["success" => false], 400);
    }

    $stmt = $db->prepare("
        INSERT INTO resources(title, description, link)
        VALUES(?, ?, ?)
    ");

    $stmt->execute([
        $data['title'],
        $data['description'] ?? "",
        $data['link']
    ]);

    sendResponse([
        "success" => true,
        "id" => $db->lastInsertId()
    ], 201);
}

// UPDATE RESOURCE
function updateResource($db, $data){

    if(empty($data['id'])){
        sendResponse(["success" => false], 400);
    }

    $stmt = $db->prepare("
        SELECT * FROM resources
        WHERE id = ?
    ");

    $stmt->execute([$data['id']]);

    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$existing){
        sendResponse(["success" => false], 404);
    }

    $title = $data['title'] ?? $existing['title'];
    $description = $data['description'] ?? $existing['description'];
    $link = $data['link'] ?? $existing['link'];

    if(!filter_var($link, FILTER_VALIDATE_URL)){
        sendResponse(["success" => false], 400);
    }

    $stmt = $db->prepare("
        UPDATE resources
        SET title = ?, description = ?, link = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $title,
        $description,
        $link,
        $data['id']
    ]);

    sendResponse([
        "success" => true
    ]);
}

// DELETE RESOURCE
function deleteResource($db, $id){

    if(!is_numeric($id)){
        sendResponse(["success" => false], 400);
    }

    $stmt = $db->prepare("
        SELECT id FROM resources
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    if(!$stmt->fetch()){
        sendResponse(["success" => false], 404);
    }

    $stmt = $db->prepare("
        DELETE FROM resources
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    sendResponse([
        "success" => true
    ]);
}

// ========= COMMENTS =========

// GET COMMENTS
function getComments($db, $resource_id){

    if(!is_numeric($resource_id)){
        sendResponse(["success" => false], 400);
    }

    $stmt = $db->prepare("
        SELECT *
        FROM comments_resource
        WHERE resource_id = ?
        ORDER BY id ASC
    ");

    $stmt->execute([$resource_id]);

    sendResponse([
        "success" => true,
        "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

// CREATE COMMENT
function createComment($db, $data){

    $resourceId =
        $data['resource_id']
        ?? $data['resourceId']
        ?? null;

    $author = $data['author'] ?? null;
    $text = $data['text'] ?? null;

    if(
        empty($resourceId) ||
        empty($author) ||
        empty($text)
    ){
        sendResponse(["success" => false], 400);
    }

    $stmt = $db->prepare("
        SELECT id FROM resources
        WHERE id = ?
    ");

    $stmt->execute([$resourceId]);

    if(!$stmt->fetch()){
        sendResponse(["success" => false], 404);
    }

    $stmt = $db->prepare("
        INSERT INTO comments_resource(resource_id, author, text)
        VALUES(?, ?, ?)
    ");

    $stmt->execute([
        $resourceId,
        $author,
        $text
    ]);

    sendResponse([
        "success" => true
    ], 201);
}

// DELETE COMMENT
function deleteComment($db, $id){

    if(!is_numeric($id)){
        sendResponse(["success" => false], 404);
    }

    $stmt = $db->prepare("
        SELECT id FROM comments_resource
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    if(!$stmt->fetch()){
        sendResponse(["success" => false], 404);
    }

    $stmt = $db->prepare("
        DELETE FROM comments_resource
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    sendResponse([
        "success" => true
    ]);
}

// ========= ROUTER =========

try {

    // ===== GET =====
    if($method === "GET"){

        $id = $_GET['id'] ?? null;

        $search =
            $_GET['search']
            ?? $_GET['q']
            ?? null;

        // COMMENTS
        if(
            isset($_GET['comments']) ||
            isset($_GET['resource_id']) ||
            isset($_GET['resourceId'])
        ){

            $resource_id =
                $_GET['comments']
                ?? $_GET['resource_id']
                ?? $_GET['resourceId'];

            getComments($db, $resource_id);
        }

        // SINGLE RESOURCE
        elseif($id){

            getResource($db, $id);
        }

        // ALL RESOURCES
        else {

            getAllResources($db, $search);
        }
    }

    // ===== POST =====
    elseif($method === "POST"){

        if(
            isset($data['resource_id']) ||
            isset($data['resourceId'])
        ){

            createComment($db, $data);

        } else {

            createResource($db, $data);
        }
    }

    // ===== PUT =====
    elseif($method === "PUT"){

        updateResource($db, $data);
    }

    // ===== DELETE =====
    elseif($method === "DELETE"){

        $id = $_GET['id'] ?? null;

        // DELETE COMMENT
        if(
            isset($_GET['comment']) ||
            isset($_GET['comment_id']) ||
            isset($_GET['commentId'])
        ){

            $comment_id =
                $_GET['comment_id']
                ?? $_GET['commentId']
                ?? $id;

            deleteComment($db, $comment_id);
        }

        // DELETE RESOURCE
        elseif($id){

            deleteResource($db, $id);
        }

        else{

            sendResponse([
                "success" => false
            ], 400);
        }
    }

    // ===== METHOD NOT ALLOWED =====
    else {

        sendResponse([
            "success" => false
        ], 405);
    }

} catch(Exception $e){

    sendResponse([
        "success" => false,
        "error" => $e->getMessage()
    ], 500);
}
