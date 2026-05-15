<?php
session_start();
require 'config.php';

if (!isset($_SESSION['email'])) { header("Location: homepage.php"); exit(); }

$follower_email = $_SESSION['email'];
$target_email   = $_POST['target_email'] ?? '';
$action         = $_POST['action'] ?? '';

// can't follow yourself
if (empty($target_email) || $target_email === $follower_email) {
    header("Location: homepage.php"); exit();
}

if ($action === 'follow') {
    $stmt = $conn->prepare("
        INSERT IGNORE INTO follows (follower_email, following_email) 
        VALUES (?, ?)
    ");
    $stmt->bind_param("ss", $follower_email, $target_email);
    $stmt->execute();
    $stmt->close();
} elseif ($action === 'unfollow') {
    $stmt = $conn->prepare("
        DELETE FROM follows WHERE follower_email = ? AND following_email = ?
    ");
    $stmt->bind_param("ss", $follower_email, $target_email);
    $stmt->execute();
    $stmt->close();
}

header("Location: profile.php?email=" . urlencode($target_email));
exit();