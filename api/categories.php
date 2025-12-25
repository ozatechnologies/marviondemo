<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$host = 'sql213.infinityfree.com';
$user = 'if0_40731041';
$pass = 'ZwN0WsN1t7y';
$db = 'if0_40731041_mexim';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$conn->set_charset("utf8");

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? intval($_GET['id']) : null;

switch ($method) {
    case 'GET':
        if ($id) {
            getSingleCategory($conn, $id);
        } else {
            getAllCategories($conn);
        }
        break;
    
    case 'POST':
        addCategory($conn);
        break;
    
    case 'PUT':
        updateCategory($conn, $id);
        break;
    
    case 'DELETE':
        deleteCategory($conn, $id);
        break;
    
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        break;
}

$conn->close();

function getAllCategories($conn) {
    $query = "SELECT * FROM categories ORDER BY priority ASC, created_at DESC";
    $result = $conn->query($query);
    
    if (!$result) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $conn->error]);
        return;
    }
    
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    
    echo json_encode($categories);
}

function getSingleCategory($conn, $id) {
    $query = "SELECT * FROM categories WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Category not found']);
        return;
    }
    
    echo json_encode($result->fetch_assoc());
    $stmt->close();
}

function addCategory($conn) {
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!isset($data['name'], $data['image'], $data['slug'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    $name = $conn->real_escape_string($data['name']);
    $slug = $conn->real_escape_string($data['slug']);
    $image = $conn->real_escape_string($data['image']);
    $description = $conn->real_escape_string($data['description'] ?? '');
    $subtitle = $conn->real_escape_string($data['subtitle'] ?? '');
    $priority = isset($data['priority']) ? intval($data['priority']) : getNextPriority($conn);
    
    $check = $conn->query("SELECT id FROM categories WHERE priority = $priority");
    if ($check->num_rows > 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Priority already in use']);
        return;
    }
    
    $query = "INSERT INTO categories (name, slug, image, description, subtitle, priority) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $conn->error]);
        return;
    }
    
    $stmt->bind_param("sssssi", $name, $slug, $image, $description, $subtitle, $priority);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'id' => $stmt->insert_id, 'message' => 'Category added']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $stmt->error]);
    }
    
    $stmt->close();
}

function updateCategory($conn, $id) {
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID required']);
        return;
    }
    
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!isset($data['name'], $data['image'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    $name = $conn->real_escape_string($data['name']);
    $image = $conn->real_escape_string($data['image']);
    $description = $conn->real_escape_string($data['description'] ?? '');
    $subtitle = $conn->real_escape_string($data['subtitle'] ?? '');
    $priority = isset($data['priority']) ? intval($data['priority']) : 0;
    
    if ($priority > 0) {
        $check = $conn->query("SELECT id FROM categories WHERE priority = $priority AND id != $id");
        if ($check->num_rows > 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Priority already in use']);
            return;
        }
    }
    
    if ($priority > 0) {
        $query = "UPDATE categories SET name = ?, image = ?, description = ?, subtitle = ?, priority = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssii", $name, $image, $description, $subtitle, $priority, $id);
    } else {
        $query = "UPDATE categories SET name = ?, image = ?, description = ?, subtitle = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssi", $name, $image, $description, $subtitle, $id);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Category updated']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $stmt->error]);
    }
    
    $stmt->close();
}

function deleteCategory($conn, $id) {
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID required']);
        return;
    }
    
    $query = "DELETE FROM categories WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Category deleted']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $stmt->error]);
    }
    
    $stmt->close();
}

function getNextPriority($conn) {
    $result = $conn->query("SELECT MAX(priority) as max_priority FROM categories");
    $row = $result->fetch_assoc();
    return ($row['max_priority'] ?? 0) + 1;
}
?>