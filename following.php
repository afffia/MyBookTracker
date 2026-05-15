<?php
session_start();
require 'config.php';

if (!isset($_SESSION['email'])) { header("Location: homepage.php"); exit(); }

$user_email = $_SESSION['email'];
$name       = $_SESSION['name'];

// get everyone this user follows
$stmt = $conn->prepare("
    SELECT u.name, u.email,
           COUNT(ub.id) as book_count
    FROM follows f
    JOIN users u ON f.following_email = u.email
    LEFT JOIN user_books ub ON ub.user_email = u.email
    WHERE f.follower_email = ?
    GROUP BY u.email
    ORDER BY u.name ASC
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
$following = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>Following</title>
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

<div style="max-width:700px; margin:40px auto; padding:0 20px;">
    <a href="user_page.php" style="color:#8c374f; text-decoration:none;">← Back to Dashboard</a>
    <h2 style="margin:20px 0 8px; color:#44222f;">Following</h2>
    <p style="color:#888; margin-bottom:24px;"><?= count($following) ?> user<?= count($following) !== 1 ? 's' : '' ?></p>

    <?php if (empty($following)): ?>
        <p style="color:#888;">You're not following anyone yet. Find readers by searching books and viewing who reviewed them.</p>
    <?php else: ?>
    <div style="display:flex; flex-direction:column; gap:12px;">
        <?php foreach ($following as $f): ?>
        <div style="display:flex; align-items:center; justify-content:space-between; background:rgba(255,255,255,0.5); border:1px solid rgba(140,55,79,0.2); border-radius:12px; padding:14px 18px;">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="width:42px; height:42px; background:rgba(140,55,79,0.15); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:18px; font-weight:600; color:#8c374f;">
                    <?= strtoupper($f['name'][0]) ?>
                </div>
                <div>
                    <p style="font-weight:600; color:#44222f; margin:0;"><?= htmlspecialchars($f['name']) ?></p>
                    <p style="font-size:0.8rem; color:#888; margin:2px 0 0;"><?= $f['book_count'] ?> books</p>
                </div>
            </div>
            <div style="display:flex; gap:8px;">
                <a href="profile.php?email=<?= urlencode($f['email']) ?>" 
                   style="font-size:0.85rem; color:#8c374f; text-decoration:none; border:1px solid rgba(140,55,79,0.3); padding:5px 14px; border-radius:20px;">
                    View List
                </a>
                <form action="follow.php" method="POST" style="margin:0;">
                    <input type="hidden" name="target_email" value="<?= htmlspecialchars($f['email']) ?>">
                    <input type="hidden" name="action" value="unfollow">
                    <button type="submit" style="font-size:0.85rem; background:transparent; border:1px solid rgba(140,55,79,0.3); padding:5px 14px; border-radius:20px; cursor:pointer; color:#888;">
                        Unfollow
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script src="script.js"></script>
<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</body>
</html>