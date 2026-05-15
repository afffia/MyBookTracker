<?php
session_start();
require 'config.php';


if (!isset($_SESSION['email'])) {
    header("Location: tracker_homepage.php");
    exit();
}

if (isset($_POST['save_goal'])) {
    $new_goal = $_POST['new_goal'];
    $email = $_SESSION['email'];

    
    $new_goal = intval($new_goal);

   
    $query = "UPDATE users SET reading_goal = $new_goal WHERE email = '$email'";
    
    if ($conn->query($query)) {
        $_SESSION['alerts'][] = ['type' => 'success', 'message' => 'Reading goal updated!'];
    } else {
        $_SESSION['alerts'][] = ['type' => 'error', 'message' => 'Failed to update goal.'];
    }

    
    header("Location: user_page.php");
    exit();
}


header("Location: user_page.php");
exit();
?>