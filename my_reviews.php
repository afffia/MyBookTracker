<?php
session_start();
require 'config.php';
if (!isset($_SESSION['name'])) { header("Location: user_page.php"); exit(); }

$user_email = $_SESSION['email'];
$name = $_SESSION['name'];

$stmt = $conn->prepare("
    SELECT b.id, b.book_title, b.author, b.cover_url, 
           ub.review, ub.rating 
    FROM user_books ub 
    JOIN books b ON ub.book_id = b.id 
    WHERE ub.user_email = ? AND ub.review IS NOT NULL AND ub.review != ''
    ORDER BY b.book_title ASC
");
$stmt = $conn->prepare("SELECT role FROM users WHERE email = ?");
$stmt->bind_param("s", $_SESSION['email']);
$stmt->execute();
$user_role = $stmt->get_result()->fetch_assoc()['role'];
$stmt->close();

if (!in_array($user_role, ['admin', 'moderator'])) {
    header("Location: admin_page.php"); exit();
}

$is_admin     = $user_role === 'admin';
$is_moderator = $user_role === 'moderator';
$stmt->bind_param("s", $user_email);
$stmt->execute();
$books = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>My Reviews</title>
</head>
<body>
<header>
        <h1 class="logo"> <a href="user_page.html">MyBookTracker</a> </h1>
           
        <div class="user">
            <?php if (!empty($name)): ?>
        <div class="profile-box">
                    <div class="avatar-circle"><?= strtoupper($name[0]) ?></div> 
                   <div class="drop-down">
    <?php if (in_array($user_role, ['admin', 'moderator'])): ?>
    <a href="admin_page.php">Admin Panel</a>
    <?php endif; ?>
    <a href="profile.php?email=<?= urlencode($user_email) ?>">My Profile</a>
    <a href="following.php">Following</a>
    <a href="user_page.php">Dashboard</a>
    <a href="logout.php">Logout</a>
</div>
                </div>
                <?php else: ?>
                 <button class="btnloginpopup" name="login_btn">Login</button>
                 <?php endif; ?>
             </div></header>

<div style="max-width:800px; margin:40px auto; padding:0 20px;">
    <a href="user_page.php" style="color:#8c374f; text-decoration:none;">Dashboard</a>
    <h2 style="margin:20px 0 8px;">My Reviews</h2>
    <p style="color:#888; margin-bottom:24px;"><?= count($books) ?> review<?= count($books) !== 1 ? 's' : '' ?></p>

    <?php if (empty($books)): ?>
        <p style="color:#888;">You haven't written any reviews yet.</p>
    <?php else: ?>
        <?php foreach ($books as $book): ?>
        <div style="background:rgba(255,255,255,0.5); border-radius:12px; padding:20px; margin-bottom:16px; border:1px solid rgba(140,55,79,0.2);">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:8px;">
                <div>
                    <h3 style="color:#44222f; margin:0;"><?= htmlspecialchars($book['book_title']) ?></h3>
                    <p style="color:#888; font-size:0.9rem; margin:2px 0 0;"><?= htmlspecialchars($book['author']) ?></p>
                </div>
                <?php if ($book['rating']): ?>
                <div style="font-size:1.1rem; color:#c0392b;">
                    <?= str_repeat('★', $book['rating']) ?><?= str_repeat('☆', 5 - $book['rating']) ?>
                </div>
                <?php endif; ?>
            </div>
            <p style="margin-top:14px; color:#333; line-height:1.6;"><?= htmlspecialchars($book['review']) ?></p>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script src="script.js"></script>
</body>
</html>