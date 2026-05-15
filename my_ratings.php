<?php
session_start();
require 'config.php';
if (!isset($_SESSION['name'])) { header("Location: user_page.php"); exit(); }

$user_email = $_SESSION['email'];
$name = $_SESSION['name'];

$stmt = $conn->prepare("
    SELECT b.id, b.book_title, b.author, b.cover_url,
           ub.rating, ub.status 
    FROM user_books ub 
    JOIN books b ON ub.book_id = b.id 
    WHERE ub.user_email = ? AND ub.rating IS NOT NULL 
    ORDER BY ub.rating DESC, b.book_title ASC
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
    <title>My Ratings</title>
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
    <a href="user_page.php" style="color:#8c374f; text-decoration:none;">Back</a>
    <h2 style="margin:20px 0 4px;">My Ratings</h2>
    <p style="color:#888; margin-bottom:24px;">
         <?= count($books) ?><?= count($books) !== 1 ? 's' : '' ?>
    </p>

    <?php if (empty($books)): ?>
        <p style="color:#888;">You haven't rated any books yet.</p>
    <?php else: ?>
        <?php 
        $current_rating = -1;
        foreach ($books as $book): 
            if ($book['rating'] !== $current_rating):
                if ($current_rating !== -1) echo '</div>';
                $current_rating = $book['rating'];
        ?>
            <h4 style="color:#8c374f; margin:24px 0 10px;">
                <?= str_repeat('★', $book['rating']) ?><?= str_repeat('☆', 5 - $book['rating']) ?>
            </h4>
            <div style="display:flex; flex-direction:column; gap:10px;">
        <?php endif; ?>

            <div style="background:rgba(255,255,255,0.5); border-radius:10px; padding:14px 18px; border:1px solid rgba(140,55,79,0.15); display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <p style="font-weight:600; color:#44222f; margin:0;"><?= htmlspecialchars($book['book_title']) ?></p>
                    <p style="font-size:0.85rem; color:#888; margin:2px 0 0;"><?= htmlspecialchars($book['author']) ?></p>
                </div>
                <span style="font-size:0.75rem; color:#fff; background:#8c374f; padding:3px 10px; border-radius:20px;">
                    <?= htmlspecialchars($book['status']) ?>
                </span>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script src="script.js"></script>
</body>
</html>