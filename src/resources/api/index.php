<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once './config/Database.php';

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

$rawData = file_get_contents("php://input");
$data = json_decode($rawData, true);

$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;
$resource_id = $_GET['resource_id'] ?? null;
$comment_id = $_GET['comment_id'] ?? null;

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

function getAllResources($db) {
    $stmt = $db->prepare("SELECT id, title, description, link, created_at FROM resources ORDER BY created_at DESC");
    $stmt->execute();
    $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);
    sendResponse(["success" => true, "data" => $resources]);
}

function getResourceById($db, $id) {
    $stmt = $db->prepare("SELECT id, title, description, link, created_at FROM resources WHERE id = ?");
    $stmt->execute([$id]);
    $resource = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($resource) {
        sendResponse(["success" => true, "data" => $resource]);
    } else {
        sendResponse(["success" => false, "message" => "Resource not found"], 404);
    }
}

function createResource($db, $data) {
    if (empty($data['title']) || empty($data['link'])) {
        sendResponse(["success" => false, "message" => "Missing fields"], 400);
    }

    $stmt = $db->prepare("INSERT INTO resources (title, description, link) VALUES (?, ?, ?)");
    $stmt->execute([
        trim($data['title']),
        trim($data['description'] ?? ''),
        trim($data['link'])
    ]);

    sendResponse(["success" => true, "id" => $db->lastInsertId()], 201);
}

function updateResource($db, $data) {
    if (empty($data['id'])) {
        sendResponse(["success" => false], 400);
    }

    $stmt = $db->prepare("UPDATE resources SET title=?, description=?, link=? WHERE id=?");
    $stmt->execute([
        $data['title'],
        $data['description'],
        $data['link'],
        $data['id']
    ]);

    sendResponse(["success" => true]);
}

function deleteResource($db, $id) {
    $stmt = $db->prepare("DELETE FROM resources WHERE id=?");
    $stmt->execute([$id]);
    sendResponse(["success" => true]);
}

function getCommentsByResourceId($db, $resource_id) {
    $stmt = $db->prepare("SELECT id, resource_id, author, text, created_at FROM comments_resource WHERE resource_id=? ORDER BY created_at ASC");
    $stmt->execute([$resource_id]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    sendResponse(["success" => true, "data" => $comments]);
}

function createComment($db, $data) {
    if (empty($data['resource_id']) || empty($data['author']) || empty($data['text'])) {
        sendResponse(["success" => false], 400);
    }

    $stmt = $db->prepare("INSERT INTO comments_resource (resource_id, author, text) VALUES (?, ?, ?)");
    $stmt->execute([
        $data['resource_id'],
        $data['author'],
        $data['text']
    ]);

    sendResponse(["success" => true, "data" => [
        "id" => $db->lastInsertId(),
        "resource_id" => $data['resource_id'],
        "author" => $data['author'],
        "text" => $data['text']
    ]], 201);
}

function deleteComment($db, $comment_id) {
    $stmt = $db->prepare("DELETE FROM comments_resource WHERE id=?");
    $stmt->execute([$comment_id]);
    sendResponse(["success" => true]);
}

try {

    if ($method === 'GET') {

        if ($action === 'comments') {
            getCommentsByResourceId($db, $resource_id);
        } elseif ($id) {
            getResourceById($db, $id);
        } else {
            getAllResources($db);
        }

    } elseif ($method === 'POST') {

        if ($action === 'comment') {
            createComment($db, $data);
        } else {
            createResource($db, $data);
        }

    } elseif ($method === 'PUT') {

        updateResource($db, $data);

    } elseif ($method === 'DELETE') {

        if ($action === 'delete_comment') {
            deleteComment($db, $comment_id);
        } else {
            deleteResource($db, $id);
        }

    } else {
        sendResponse(["success" => false], 405);
    }

} catch (Exception $e) {
    sendResponse(["success" => false], 500);
}

?>
