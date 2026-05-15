<?php
session_start();
require 'config.php';

// 1. Check if user is logged in
if (!isset($_SESSION['email'])) {
    http_response_code(403);
    exit("Login required");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_email = $_SESSION['email'];
    $book_id    = intval($_POST['book_id'] ?? 0);
    $status     = $_POST['status'] ?? '';

    
    $allowed_statuses = ['want_to_read', 'reading', 'completed'];
    
    if ($book_id > 0 && in_array($status, $allowed_statuses)) {
        
        
        $stmt = $conn->prepare("
            INSERT INTO user_books (user_email, book_id, status, added_at) 
            VALUES (?, ?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE status = VALUES(status)
        ");
        
        $stmt->bind_param("sis", $user_email, $book_id, $status);
        
        if ($stmt->execute()) {
            
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode(['status' => 'success']);
                exit;
            } else {
                
                header("Location: book_detail.php?id=" . $book_id);
                exit;
            }
        }
        $stmt->close();
    }
}


header("Location: user_page.php");
exit();