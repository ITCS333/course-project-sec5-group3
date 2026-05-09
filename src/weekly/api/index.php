<?php
/**
 * Weekly Course Breakdown API
 *
 * RESTful API for CRUD operations on weekly course content and discussion
 * comments. Uses PDO to interact with the MySQL database defined in
 * schema.sql.
 *
 * Database Tables (ground truth: schema.sql):
 *
 * Table: weeks
 *   id          INT UNSIGNED  PRIMARY KEY AUTO_INCREMENT
 *   title       VARCHAR(200)  NOT NULL
 *   start_date  DATE          NOT NULL
 *   description TEXT
 *   links       TEXT          — JSON-encoded array of URL strings
 *   created_at  TIMESTAMP
 *   updated_at  TIMESTAMP
 *
 * Table: comments_week
 *   id          INT UNSIGNED  PRIMARY KEY AUTO_INCREMENT
 *   week_id     INT UNSIGNED  NOT NULL   — FK → weeks.id (ON DELETE CASCADE)
 *   author      VARCHAR(100)  NOT NULL
 *   text        TEXT          NOT NULL
 *   created_at  TIMESTAMP
 *
 * HTTP Methods Supported:
 *   GET    — Retrieve week(s) or comments
 *   POST   — Create a new week or comment
 *   PUT    — Update an existing week
 *   DELETE — Delete a week (cascade removes its comments) or a single comment
 *
 * URL scheme (all requests go to index.php):
 *
 *   Weeks:
 *     GET    ./api/index.php                  — list all weeks
 *     GET    ./api/index.php?id={id}           — get one week by integer id
 *     POST   ./api/index.php                  — create a new week
 *     PUT    ./api/index.php                  — update a week (id in JSON body)
 *     DELETE ./api/index.php?id={id}           — delete a week
 *
 *   Comments (action parameter selects the comments sub-resource):
 *     GET    ./api/index.php?action=comments&week_id={id}
 *                                             — list comments for a week
 *     POST   ./api/index.php?action=comment   — create a comment
 *     DELETE ./api/index.php?action=delete_comment&comment_id={id}
 *                                             — delete a single comment
 *
 * Query parameters for GET all weeks:
 *   search — filter rows where title LIKE or description LIKE the term
 *   sort   — column to sort by; allowed: title, start_date (default: start_date)
 *   order  — sort direction; allowed: asc, desc (default: asc)
 *
 * Response format: JSON
 *   Success: { "success": true,  "data": ... }
 *   Error:   { "success": false, "message": "..." }
 */

// ============================================================================
// HEADERS AND INITIALIZATION
// ============================================================================

// Set headers for JSON response and CORS.
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include the shared database connection file.
require_once __DIR__ . '/../../common/db.php';

// Get the PDO database connection.
$db = getDBConnection();

// Read the HTTP request method.
$method = $_SERVER['REQUEST_METHOD'];

// Read and decode the request body for POST and PUT requests.
$rawData = file_get_contents('php://input');
$data    = json_decode($rawData, true) ?? [];

// Read query parameters.
$action    = $_GET['action']     ?? null;  // 'comments', 'comment', 'delete_comment'
$id        = $_GET['id']         ?? null;  // integer week id
$weekId    = $_GET['week_id']    ?? null;  // integer week id for comments queries
$commentId = $_GET['comment_id'] ?? null;  // integer comment id


// ============================================================================
// WEEKS FUNCTIONS
// ============================================================================

/**
 * Get all weeks (with optional search and sort).
 * Method: GET (no ?id or ?action parameter).
 */
function getAllWeeks(PDO $db): void
{
    $query = "SELECT id, title, start_date, description, links, created_at FROM weeks";
    $params = [];
    
    $search = $_GET['search'] ?? '';
    if (!empty($search)) {
        $query .= " WHERE title LIKE :search OR description LIKE :search";
        $params[':search'] = '%' . $search . '%';
    }

    $sort = $_GET['sort'] ?? 'start_date';
    $allowedSort = ['title', 'start_date'];
    if (!in_array($sort, $allowedSort)) {
        $sort = 'start_date';
    }

    $order = strtolower($_GET['order'] ?? 'asc');
    $allowedOrder = ['asc', 'desc'];
    if (!in_array($order, $allowedOrder)) {
        $order = 'asc';
    }

    $query .= " ORDER BY {$sort} {$order}";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $weeks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($weeks as &$week) {
        $week['links'] = json_decode($week['links'], true) ?? [];
    }

    sendResponse(['success' => true, 'data' => $weeks]);
}


/**
 * Get a single week by its integer primary key.
 * Method: GET with ?id={id}.
 */
function getWeekById(PDO $db, $id): void
{
    if (!$id || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid or missing ID'], 400);
    }

    $stmt = $db->prepare("SELECT id, title, start_date, description, links, created_at FROM weeks WHERE id = ?");
    $stmt->execute([$id]);
    $week = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($week) {
        $week['links'] = json_decode($week['links'], true) ?? [];
        sendResponse(['success' => true, 'data' => $week]);
    } else {
        sendResponse(['success' => false, 'message' => 'Week not found'], 404);
    }
}


/**
 * Create a new week.
 * Method: POST (no ?action parameter).
 */
function createWeek(PDO $db, array $data): void
{
    if (empty($data['title']) || empty($data['start_date'])) {
        sendResponse(['success' => false, 'message' => 'Title and start_date are required'], 400);
    }

    $title = trim($data['title']);
    $startDate = trim($data['start_date']);
    $description = isset($data['description']) ? trim($data['description']) : '';

    if (!validateDate($startDate)) {
        sendResponse(['success' => false, 'message' => 'Invalid start_date format, expected YYYY-MM-DD'], 400);
    }

    $links = isset($data['links']) && is_array($data['links']) ? json_encode($data['links']) : json_encode([]);

    $stmt = $db->prepare("INSERT INTO weeks (title, start_date, description, links) VALUES (?, ?, ?, ?)");
    $stmt->execute([$title, $startDate, $description, $links]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Week created successfully', 'id' => $db->lastInsertId()], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to create week'], 500);
    }
}


/**
 * Update an existing week.
 * Method: PUT.
 */
function updateWeek(PDO $db, array $data): void
{
    if (empty($data['id']) || !is_numeric($data['id'])) {
        sendResponse(['success' => false, 'message' => 'Valid ID is required'], 400);
    }
    
    $id = $data['id'];

    $stmt = $db->prepare("SELECT id FROM weeks WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Week not found'], 404);
    }

    $updates = [];
    $params = [];

    if (isset($data['title'])) {
        $updates[] = "title = ?";
        $params[] = trim($data['title']);
    }
    if (isset($data['start_date'])) {
        if (!validateDate($data['start_date'])) {
            sendResponse(['success' => false, 'message' => 'Invalid start_date format'], 400);
        }
        $updates[] = "start_date = ?";
        $params[] = trim($data['start_date']);
    }
    if (isset($data['description'])) {
        $updates[] = "description = ?";
        $params[] = trim($data['description']);
    }
    if (isset($data['links'])) {
        $updates[] = "links = ?";
        $params[] = is_array($data['links']) ? json_encode($data['links']) : json_encode([]);
    }

    if (empty($updates)) {
        sendResponse(['success' => false, 'message' => 'No updatable fields provided'], 400);
    }

    $params[] = $id;

    $query = "UPDATE weeks SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $db->prepare($query);

    if ($stmt->execute($params)) {
        sendResponse(['success' => true, 'message' => 'Week updated successfully'], 200);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to update week'], 500);
    }
}


/**
 * Delete a week by integer id.
 * Method: DELETE with ?id={id}.
 */
function deleteWeek(PDO $db, $id): void
{
    if (!$id || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Valid ID is required'], 400);
    }

    $stmt = $db->prepare("SELECT id FROM weeks WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Week not found'], 404);
    }

    $stmt = $db->prepare("DELETE FROM weeks WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Week deleted successfully'], 200);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to delete week'], 500);
    }
}


// ============================================================================
// COMMENTS FUNCTIONS
// ============================================================================

/**
 * Get all comments for a specific week.
 * Method: GET with ?action=comments&week_id={id}.
 */
function getCommentsByWeek(PDO $db, $weekId): void
{
    if (!$weekId || !is_numeric($weekId)) {
        sendResponse(['success' => false, 'message' => 'Valid week_id is required'], 400);
    }

    $stmt = $db->prepare("SELECT id, week_id, author, text, created_at FROM comments_week WHERE week_id = ? ORDER BY created_at ASC");
    $stmt->execute([$weekId]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse(['success' => true, 'data' => $comments]);
}


/**
 * Create a new comment.
 * Method: POST with ?action=comment.
 */
function createComment(PDO $db, array $data): void
{
    $weekId = isset($data['week_id']) ? trim($data['week_id']) : null;
    $author = isset($data['author']) ? trim($data['author']) : null;
    $text = isset($data['text']) ? trim($data['text']) : null;

    if (empty($weekId) || empty($author) || empty($text)) {
        sendResponse(['success' => false, 'message' => 'week_id, author, and text are required'], 400);
    }

    if (!is_numeric($weekId)) {
        sendResponse(['success' => false, 'message' => 'week_id must be numeric'], 400);
    }

    $stmt = $db->prepare("SELECT id FROM weeks WHERE id = ?");
    $stmt->execute([$weekId]);
    if (!$stmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Week not found'], 404);
    }

    $stmt = $db->prepare("INSERT INTO comments_week (week_id, author, text) VALUES (?, ?, ?)");
    $stmt->execute([$weekId, $author, $text]);

    if ($stmt->rowCount() > 0) {
        $newId = $db->lastInsertId();
        
        $stmt = $db->prepare("SELECT id, week_id, author, text, created_at FROM comments_week WHERE id = ?");
        $stmt->execute([$newId]);
        $comment = $stmt->fetch(PDO::FETCH_ASSOC);

        sendResponse(['success' => true, 'message' => 'Comment created successfully', 'id' => $newId, 'data' => $comment], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to create comment'], 500);
    }
}


/**
 * Delete a single comment.
 * Method: DELETE with ?action=delete_comment&comment_id={id}.
 */
function deleteComment(PDO $db, $commentId): void
{
    if (!$commentId || !is_numeric($commentId)) {
        sendResponse(['success' => false, 'message' => 'Valid comment_id is required'], 400);
    }

    $stmt = $db->prepare("SELECT id FROM comments_week WHERE id = ?");
    $stmt->execute([$commentId]);
    if (!$stmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Comment not found'], 404);
    }

    $stmt = $db->prepare("DELETE FROM comments_week WHERE id = ?");
    $stmt->execute([$commentId]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Comment deleted successfully'], 200);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to delete comment'], 500);
    }
}


// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================

try {
    if ($method === 'GET') {
        if ($action === 'comments') {
            getCommentsByWeek($db, $weekId);
        } elseif ($id !== null) {
            getWeekById($db, $id);
        } else {
            getAllWeeks($db);
        }
    } elseif ($method === 'POST') {
        if ($action === 'comment') {
            createComment($db, $data);
        } else {
            createWeek($db, $data);
        }
    } elseif ($method === 'PUT') {
        updateWeek($db, $data);
    } elseif ($method === 'DELETE') {
        if ($action === 'delete_comment') {
            deleteComment($db, $commentId);
        } else {
            deleteWeek($db, $id);
        }
    } else {
        sendResponse(['success' => false, 'message' => 'Method Not Allowed'], 405);
    }

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    sendResponse(['success' => false, 'message' => 'Internal Server Error'], 500);

} catch (Exception $e) {
    error_log("General Error: " . $e->getMessage());
    sendResponse(['success' => false, 'message' => 'Internal Server Error'], 500);
}


// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Send a JSON response and stop execution.
 *
 * @param array $data        Must include a 'success' key.
 * @param int   $statusCode  HTTP status code (default 200).
 */
function sendResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}


/**
 * Validate a date string against the "YYYY-MM-DD" format.
 *
 * @param  string $date
 * @return bool  True if valid, false otherwise.
 */
function validateDate(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}


/**
 * Sanitize a string input.
 *
 * @param  string $data
 * @return string  Trimmed, tag-stripped, HTML-encoded string.
 */
function sanitizeInput(string $data): string
{
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}
