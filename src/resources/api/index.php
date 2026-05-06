<?php

header("Content-Type: application/json");
require_once __DIR__ . '/../../common/db.php';
$db = getDBConnection();

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents("php://input"), true);

function sendResponse($data, $code = 200){
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// ========= ROUTER =========

try {
    if($method === "GET"){
        $id = $_GET['id'] ?? null;
        if(isset($_GET['comments']) || isset($_GET['resource_id']) || isset($_GET['resourceId'])){
            $res_id = $_GET['comments'] ?? $_GET['resource_id'] ?? $_GET['resourceId'];
            $stmt = $db->prepare("SELECT * FROM comments_resource WHERE resource_id = ? ORDER BY id ASC");
            $stmt->execute([$res_id]);
            sendResponse(["success" => true, "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } elseif($id) {
            $stmt = $db->prepare("SELECT * FROM resources WHERE id = ?");
            $stmt->execute([$id]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if(!$res) sendResponse(["success" => false], 404);
            sendResponse(["success" => true, "data" => $res]);
        } else {
            $search = $_GET['search'] ?? $_GET['q'] ?? null;
            if($search){
                $stmt = $db->prepare("SELECT * FROM resources WHERE title LIKE ? OR description LIKE ?");
                $stmt->execute(["%$search%", "%$search%"]);
            } else {
                $stmt = $db->prepare("SELECT * FROM resources");
                $stmt->execute();
            }
            sendResponse(["success" => true, "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }
    }

    elseif($method === "POST"){
        if(isset($data['resource_id']) || isset($data['resourceId'])){
            $rId = $data['resource_id'] ?? $data['resourceId'];
            if(empty($rId) || empty($data['author']) || empty($data['text'])) sendResponse(["success" => false], 400);
            $stmt = $db->prepare("SELECT id FROM resources WHERE id = ?");
            $stmt->execute([$rId]);
            if(!$stmt->fetch()) sendResponse(["success" => false], 404);
            $stmt = $db->prepare("INSERT INTO comments_resource(resource_id, author, text) VALUES(?, ?, ?)");
            $stmt->execute([$rId, $data['author'], $data['text']]);
            sendResponse(["success" => true], 201);
        } else {
            if(empty($data['title']) || empty($data['link'])) sendResponse(["success" => false], 400);
            $stmt = $db->prepare("INSERT INTO resources(title, description, link) VALUES(?, ?, ?)");
            $stmt->execute([$data['title'], $data['description'] ?? "", $data['link']]);
            sendResponse(["success" => true, "id" => $db->lastInsertId()], 201);
        }
    }

    elseif($method === "PUT"){
        $id = $data['id'] ?? null;
        if(!$id) sendResponse(["success" => false], 400);

        // جلب البيانات القديمة أولاً للسماح بالتحديث الجزئي
        $stmt = $db->prepare("SELECT * FROM resources WHERE id = ?");
        $stmt->execute([$id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if(!$existing) sendResponse(["success" => false], 404);

        $title = $data['title'] ?? $existing['title'];
        $description = $data['description'] ?? $existing['description'];
        $link = $data['link'] ?? $existing['link'];

        // التأكد من أن الحقول الأساسية ليست فارغة بعد الدمج
        if(empty($title) || empty($link)) sendResponse(["success" => false], 400);

        $stmt = $db->prepare("UPDATE resources SET title = ?, description = ?, link = ? WHERE id = ?");
        $stmt->execute([$title, $description, $link, $id]);
        sendResponse(["success" => true]);
    }

    elseif($method === "DELETE"){
        $id = $_GET['id'] ?? null;
        $comment_id = $_GET['comment_id'] ?? $_GET['comment'] ?? $_GET['commentId'] ?? $_GET['comments'] ?? null;

        // إذا كانت القيمة المستخرجة من GET ليست رقماً (مجرد Flag)، نستخدم id
        $final_comment_id = is_numeric($comment_id) ? $comment_id : (is_numeric($id) ? $id : null);

        // محاولة البحث في التعليقات أولاً (أولوية قصوى)
        if ($final_comment_id) {
            $stmt = $db->prepare("SELECT id FROM comments_resource WHERE id = ?");
            $stmt->execute([$final_comment_id]);
            if ($stmt->fetch()) {
                $stmt = $db->prepare("DELETE FROM comments_resource WHERE id = ?");
                $stmt->execute([$final_comment_id]);
                sendResponse(["success" => true]);
            }
        }

        // إذا لم يكن تعليقاً، نحاول حذفه كمورد
        if ($id) {
            $stmt = $db->prepare("SELECT id FROM resources WHERE id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetch()) {
                $stmt = $db->prepare("DELETE FROM resources WHERE id = ?");
                $stmt->execute([$id]);
                sendResponse(["success" => true]);
            }
            sendResponse(["success" => false], 404);
        }

        sendResponse(["success" => false], 400);
    }

    else {
        sendResponse(["success" => false], 405);
    }

} catch(Exception $e){
    sendResponse(["success" => false, "error" => $e->getMessage()], 500);
}
