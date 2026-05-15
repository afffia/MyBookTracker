<?php
session_start();
require 'config.php';

$viewer_email = $_SESSION['email'] ?? null;
$viewer_name  = $_SESSION['name'] ?? null;


$profile_email = $_GET['email'] ?? '';
if (empty($profile_email)) { header("Location: homepage.php"); exit(); }


$stmt = $conn->prepare("SELECT name, email FROM users WHERE email = ?");
$stmt->bind_param("s", $profile_email);
$stmt->execute();
$profile_user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$profile_user) { header("Location: homepage.php"); exit(); }


$is_following = false;
if ($viewer_email && $viewer_email !== $profile_email) {
    $stmt = $conn->prepare("SELECT id FROM follows WHERE follower_email = ? AND following_email = ?");
    $stmt->bind_param("ss", $viewer_email, $profile_email);
    $stmt->execute();
    $is_following = $stmt->get_result()->num_rows > 0;
    $stmt->close();
}
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

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM follows WHERE following_email = ?");
$stmt->bind_param("s", $profile_email);
$stmt->execute();
$followers_count = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM follows WHERE follower_email = ?");
$stmt->bind_param("s", $profile_email);
$stmt->execute();
$following_count = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();


$stmt = $conn->prepare("
    SELECT b.id, b.book_title, b.author, b.cover_url, b.category,
           ub.status, ub.rating
    FROM user_books ub
    JOIN books b ON ub.book_id = b.id
    WHERE ub.user_email = ?
    ORDER BY created_at DESC
");
$stmt->bind_param("s", $profile_email);
$stmt->execute();
$their_books = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title><?= htmlspecialchars($profile_user['name']) ?>'s Profile</title>
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
    <a href="profile.php?email=<?= urlencode($profile_email) ?>">My Profile</a>
    <a href="following.php">Following</a>
    <a href="user_page.php">Dashboard</a>
    <a href="logout.php">Logout</a>
</div>
                </div>
                <?php else: ?>
                 <button class="btnloginpopup" name="login_btn">Login</button>
                 <?php endif; ?>
             </div></header>

<div style="max-width:900px; margin:40px auto; padding:0 20px;">

    <!-- Profile header -->
    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px; margin-bottom:32px;">
        <div style="display:flex; align-items:center; gap:16px;">
            <div style="width:64px; height:64px; background:rgba(140,55,79,0.2); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:28px; font-weight:600; color:#8c374f;">
                <?= strtoupper($profile_user['name'][0]) ?>
            </div>
            <div>
                <h2 style="color:#44222f; margin:0;"><?= htmlspecialchars($profile_user['name']) ?></h2>
                <p style="color:#888; font-size:0.85rem; margin:4px 0 0;">
                    <?= $followers_count ?> follower<?= $followers_count !== 1 ? 's' : '' ?> · 
                    <?= $following_count ?> following ·
                    <?= count($their_books) ?> books
                </p>
            </div>
        </div>

       
        <?php if ($viewer_email && $viewer_email !== $profile_email): ?>
        <form action="follow.php" method="POST">
            <input type="hidden" name="target_email" value="<?= htmlspecialchars($profile_email) ?>">
            <input type="hidden" name="action" value="<?= $is_following ? 'unfollow' : 'follow' ?>">
            <button type="submit" class="btn" style="width:auto; padding:8px 24px;">
                <?= $is_following ? 'Unfollow' : 'Follow' ?>
            </button>
        </form>
        <?php elseif ($viewer_email === $profile_email): ?>
        <a href="user_page.php" style="color:#8c374f; text-decoration:none; font-size:0.9rem;">Edit Dashboard</a>
        <?php endif; ?>
    </div>

    <?php
    $groups = ['reading' => [], 'want' => [], 'completed' => []];
    foreach ($their_books as $b) {
        $groups[$b['status'] ?? 'want'][] = $b;
    }
    $labels = ['reading' => 'Currently Reading', 'want_to_read' => 'Want to Read', 'completed' => 'completed'];
    foreach ($groups as $status => $blist):
        if (empty($blist)) continue;
    ?>
    <h3 style="color:#8c374f; margin:24px 0 12px;"><?= $labels[$status] ?> (<?= count($blist) ?>)</h3>
    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(140px,1fr)); gap:14px; margin-bottom:8px;">
        <?php foreach ($blist as $b): ?>
        <a href="book_detail.php?id=<?= $b['id'] ?>" style="text-decoration:none;">
            <div style="border-radius:10px; overflow:hidden; border:1px solid rgba(140,55,79,0.15); background:rgba(255,255,255,0.5); transition:transform .15s ease;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform=''">
                <?php if (!empty($b['cover_url'])): ?>
                    <img src="<?= htmlspecialchars($b['cover_url']) ?>" style="width:100%; height:180px; object-fit:cover;">
                <?php else: ?>
                    <div style="width:100%; height:180px; background:rgba(140,55,79,0.1); display:flex; align-items:center; justify-content:center; font-size:36px;">📖</div>
                <?php endif; ?>
                <div style="padding:8px;">
                    <p style="font-size:0.8rem; font-weight:600; color:#44222f; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($b['book_title']) ?></p>
                    <p style="font-size:0.75rem; color:#888;"><?= htmlspecialchars($b['author']) ?></p>
                    <?php if ($b['rating']): ?>
                    <p style="font-size:0.75rem; color:#c0392b; margin-top:4px;"><?= str_repeat('★', $b['rating']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>

    <?php if (empty($their_books)): ?>
    <p style="color:#888;">This user hasn't added any books yet.</p>
    <?php endif; ?>
</div>

<script src="script.js"></script>
<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</body>
</html>