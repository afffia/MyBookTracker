<?php
session_start();
require 'config.php';

$book_id = intval($_GET['id'] ?? 0);
$user_email = $_SESSION['email'] ?? null;
$name = $_SESSION['name'] ?? null;


if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_email) {
    $rating = intval($_POST['rating'] ?? 0);
    $review = trim($_POST['review'] ?? '');

    if ($rating >= 1 && $rating <= 5) {
        $stmt = $conn->prepare("
            INSERT INTO user_books (user_email, book_id, rating, review)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE rating = VALUES(rating), review = VALUES(review)
        ");
        $stmt->bind_param("siis", $user_email, $book_id, $rating, $review);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: book_detail.php?id=$book_id");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM books WHERE id = ?");
$stmt->bind_param("i", $book_id);
$stmt->execute();
$book = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$book) { header("Location: user_page.php"); exit(); }
$user_book = null;
if ($user_email) {
    $ub_stmt = $conn->prepare("SELECT * FROM user_books WHERE user_email = ? AND book_id = ?");
    $ub_stmt->bind_param("si", $user_email, $book_id);
    $ub_stmt->execute();
    $user_book = $ub_stmt->get_result()->fetch_assoc();
    $ub_stmt->close(); 
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

$avg_stmt = $conn->prepare("SELECT ROUND(AVG(rating),1) as avg, COUNT(rating) as total 
FROM user_books WHERE book_id = ?");
$avg_stmt->bind_param("i", $book_id);
$avg_stmt->execute();
$avg_data = $avg_stmt->get_result()->fetch_assoc();
$avg_stmt->close();


$review_stmt = $conn->prepare("SELECT u.email, u.name, ub.rating, ub.review FROM user_books ub JOIN users u ON ub.user_email = u.email WHERE ub.book_id = ? 
AND ub.review IS NOT NULL AND ub.review != ''");
$review_stmt->bind_param("i", $book_id);
$review_stmt->execute();
$reviews = $review_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$review_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($book['title']) ?></title>
    <link rel="stylesheet" href="style.css">
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

<div class="main">

    
    <aside class="sidebarbook">
         <?php if (!empty($book['cover_url'])): ?>
        <img src="<?= htmlspecialchars($book['cover_url']) ?>" class="cover">
    <?php else: ?>
        <div style="width:100%; height:280px; background:rgba(140,55,79,0.15); border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:48px;">cover</div>
    <?php endif; ?>
     <div style="margin-top:10px; text-align:center;">
        <span style="font-size:0.75rem; background:rgba(140,55,79,0.15); color:#8c374f; padding:4px 14px; border-radius:20px;">
            <?= htmlspecialchars($book['category'] ?? '—') ?>
        </span>
    </div>

        <?php if ($user_email): ?>
    <form action="update_status.php" method="POST">
        <input type="hidden" name="book_id" value="<?= $book_id ?>">
        <select name="status" style="width:100%; margin-top:12px; padding:8px; border-radius:10px; border:1px solid #ccc; background:transparent;">
            <option value="want"     <?= ($user_book['status'] ?? '') === 'want_to_read'     ? 'selected' : '' ?>>Want to Read</option>
            <option value="reading"  <?= ($user_book['status'] ?? '') === 'reading'  ? 'selected' : '' ?>>Ongoing</option>
            <option value="completed" <?= ($user_book['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
        </select>
        <button type="submit" class="btnbook primary" style="margin-top:6px;">Save to List</button>
    </form>
    <?php else: ?>
        <button class="btnbook primary btnloginpopup" style="margin-top:12px;">Save to List</button>
    <?php endif; ?>

        <div class="stats">
            <p>⭐ <?= $avg_data['avg'] ?? '—' ?> / 5 (<?= $avg_data['total'] ?> ratings)</p>
        </div>

        <div class="info">
            <p><strong>Author:</strong> <?= htmlspecialchars($book['author']) ?></p>
            <p><strong>Status:</strong> <?= htmlspecialchars($book['status'] ?? '—') ?></p>
            <?php if (!empty($book['genres'])): ?>
            <p><strong>Genres:</strong> <?= htmlspecialchars($book['genres']) ?></p>
            <?php endif; ?>
        </div>
    </aside>

    
    <section class="content">
        <h1><?= htmlspecialchars($book['book_title']) ?></h1>
        <p class="subtitle"><?= htmlspecialchars($book['author']) ?></p>

        <div class="tabs">
            <button class="bookactive" onclick="showTab('about', this)">About</button>
            <button class="bookactive" onclick="showTab('reviews', this)">Reviews</button>
            <button class="bookactive" onclick="showTab('read', this)">Read</button>
        </div>

       
        <div id="tab-about" class="card">
            <h2>Summary</h2>
            <p><?= nl2br(htmlspecialchars($book['description'] ?? 'No description available.')) ?></p>
        </div>

<div id="tab-read" class="card" style="display:none;">
   
    <?php if (!empty($book['source_value'])): ?>
        <a href="<?= htmlspecialchars($book['source_value']) ?>" target="_blank" class="btnbook primary">Read Online</a>
    <?php else: ?>
        <p style="color:#888;">No reading source available for this book.</p>
    <?php endif; ?>
</div>
       
        
        <div id="tab-reviews" style="display:none;">

            
            <?php if (empty($reviews)): ?>
            <div class="card"><p>No reviews yet — be the first!</p></div>
            <?php else: ?>
                <?php foreach ($reviews as $r): ?>
                <div class="card">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <a href="profile.php?email=<?= urlencode($r['email']) ?>" 
           style="text-decoration:none; color:#44222f; font-weight:600;">
            <?= htmlspecialchars($r['name']) ?>
        </a>
                        <span style="color:#c0392b;">
                            <?= str_repeat('★', $r['rating']) ?><?= str_repeat('☆', 5 - $r['rating']) ?>
                        </span>
                    </div>
                    <p><?= nl2br(htmlspecialchars($r['review'])) ?></p>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>


            <?php if ($user_email): ?>
            <div class="card">
                <h2><?= (!empty($user_book) && !empty($user_book['review'])) ? 'Edit Your Review' : 'Add a Review' ?></h2>
                <form action="book_detail.php?id=<?= $book_id ?>" method="POST">

                    <div class="star-selector" style="margin-bottom:16px;">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <input type="radio" name="rating" id="star<?= $i ?>" value="<?= $i ?>"
                            <?= ($user_book['rating'] ?? 0)== $i ? 'checked' : '' ?> required>
                        <label for="star<?= $i ?>">★</label>
                        <?php endfor; ?>
                    </div>

                    <textarea name="review" rows="4"
                        style="width:100%; padding:12px; border-radius:8px; border:1px solid rgba(140,55,79,0.3); background:transparent; resize:vertical; font-family:inherit; color:#44222f;"
                        placeholder="Write your review..."><?= htmlspecialchars($user_book['review'] ?? '') ?></textarea>

                    <button type="submit" class="btnbook primary" style="margin-top:10px;">Submit Review</button>
                </form>
            </div>
            <?php else: ?>
            <div class="card">
                <p>Please <a href="#" class="btnloginpopup" style="color:#8c374f;">login</a> to leave a review.</p>
            </div>
            <?php endif; ?>
        </div>

    </section>

   
    <div class="wrapper">
        <span class="icon-close"><ion-icon name="close"></ion-icon></span>
        <div class="form-box login">
            <h2>Login</h2>
            <form action="login_register.php" method="post">
                <div class="input-box">
                    <span class="icon"><ion-icon name="mail"></ion-icon></span>
                    <input type="email" name="email" required>
                    <label>Email</label>
                </div>
                <div class="input-box">
                    <span class="icon"><ion-icon name="lock-closed"></ion-icon></span>
                    <input type="password" name="password" required>
                    <label>Password</label>
                </div>
                <div class="remember-forgot">
                    <label><input type="checkbox"> Remember me</label>
                    <a href="#">Forgot password?</a>
                </div>
                <button type="submit" name="login" class="btn">Login</button>
                <div class="login-register">
                    <p>Don't have an account? <a href="#" class="signup-link">Signup</a></p>
                </div>
            </form>
        </div>
        <div class="form-box register">
            <h2>Sign Up</h2>
            <form action="login_register.php" method="post">
                <div class="input-box">
                    <span class="icon"><ion-icon name="person-circle-outline"></ion-icon></span>
                    <input type="text" name="name" required>
                    <label>Username</label>
                </div>
                <div class="input-box">
                    <span class="icon"><ion-icon name="mail"></ion-icon></span>
                    <input type="email" name="email" required>
                    <label>Email</label>
                </div>
                <div class="input-box">
                    <span class="icon"><ion-icon name="lock-closed"></ion-icon></span>
                    <input type="password" name="password" required>
                    <label>Password</label>
                </div>
                <div class="remember-forgot">
                    <label><input type="checkbox"> I agree to the terms & conditions</label>
                </div>
                <button type="submit" name="register" class="btn">Signup</button>
                <div class="login-register">
                    <p>Already have an account? <a href="#" class="login-link">Login</a></p>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.star-selector { display:flex; flex-direction:row-reverse; justify-content:flex-end; gap:4px; }
.star-selector input { display:none; }
.star-selector label { font-size:2rem; color:#ccc; cursor:pointer; transition:.1s; }
.star-selector input:checked ~ label,
.star-selector label:hover,
.star-selector label:hover ~ label { color:#c0392b; }
</style>

<script>
function showTab(tab, btn) {
    
    const tabs = ['tab-about', 'tab-reviews', 'tab-read'];
    
   
    tabs.forEach(id => {
        document.getElementById(id).style.display = 'none';
    });

    // Show the selected one
    document.getElementById('tab-' + tab).style.display = 'block';

    // Update button active states
    document.querySelectorAll('.tabs button').forEach(b => b.classList.remove('bookactive'));
    btn.classList.add('bookactive');
}
</script>
<script src="script.js"></script>
<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</body>
</html>