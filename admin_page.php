<?php
session_start();
require 'config.php';

if (!isset($_SESSION['email'])) { header("Location: homepage.php"); exit(); }

$stmt = $conn->prepare("SELECT role FROM users WHERE email = ?");
$stmt->bind_param("s", $_SESSION['email']);
$stmt->execute();
$user_role = $stmt->get_result()->fetch_assoc()['role'];
$stmt->close();

if (!in_array($user_role, ['admin', 'moderator'])) {
    header("Location: tracker_homepage.php"); exit();
}

$is_admin     = $user_role === 'admin';
$is_moderator = $user_role === 'moderator';
$name         = $_SESSION['name'];
$user_email   = $_SESSION['email'];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_book') {
        $book_id = intval($_POST['book_id']);
        $stmt = $conn->prepare("DELETE FROM books WHERE id = ?");
        $stmt->bind_param("i", $book_id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['alerts'][] = ['type' => 'success', 'message' => 'Book deleted.'];
    }

    if ($action === 'delete_review') {
        $ub_id = intval($_POST['ub_id']);
        $stmt = $conn->prepare("UPDATE user_books SET review = NULL, rating = NULL WHERE id = ?");
        $stmt->bind_param("i", $ub_id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['alerts'][] = ['type' => 'success', 'message' => 'Review removed.'];
    }

    if ($action === 'delete_user' && $is_admin) {
        $target_email = $_POST['target_email'];
        $stmt = $conn->prepare("DELETE FROM users WHERE email = ? AND role != 'admin'");
        $stmt->bind_param("s", $target_email);
        $stmt->execute();
        $stmt->close();
        $_SESSION['alerts'][] = ['type' => 'success', 'message' => 'User deleted.'];
    }

    if ($action === 'toggle_admin' && $is_admin) {
        $target_email = $_POST['target_email'];
        $stmt = $conn->prepare("SELECT role FROM users WHERE email = ?");
        $stmt->bind_param("s", $target_email);
        $stmt->execute();
        $target_role = $stmt->get_result()->fetch_assoc()['role'];
        $stmt->close();

        $new_role = $target_role === 'admin' ? 'user' : 'admin';
        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE email = ? AND email != ?");
        $stmt->bind_param("sss", $new_role, $target_email, $user_email);
        $stmt->execute();
        $stmt->close();
        $_SESSION['alerts'][] = ['type' => 'success', 'message' => 'Role updated.'];
    }
    if ($action === 'upload_book' && ($is_admin || $is_moderator)) {
    $title       = trim($_POST['title'] ?? '');
    $desc        = trim($_POST['description'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);

    if (!empty($title) && !empty($_FILES['cover']['name']) && !empty($_FILES['pdf']['name'])) {
        $cover = time() . '_' . basename($_FILES['cover']['name']);
        $pdf   = time() . '_' . basename($_FILES['pdf']['name']);

        move_uploaded_file($_FILES['cover']['tmp_name'], "uploads/covers/" . $cover);
        move_uploaded_file($_FILES['pdf']['tmp_name'],   "uploads/pdfs/"   . $pdf);

        $stmt = $conn->prepare("INSERT INTO upload (title, description, cover_image, pdf_file, category_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $title, $desc, $cover, $pdf, $category_id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['alerts'][] = ['type' => 'success', 'message' => "\"$title\" uploaded successfully."];
    }
    header("Location: admin_page.php?tab=upload");
    exit();
}

if ($action === 'add_category' && ($is_admin || $is_moderator)) {
    $cat_name = trim($_POST['cat_name'] ?? '');
    if (!empty($cat_name)) {
        $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
        $stmt->bind_param("s", $cat_name);
        $stmt->execute();
        $new_id = $conn->insert_id;
        $stmt->close();
        // if called via AJAX return JSON
        if (!empty($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['id' => $new_id, 'name' => $cat_name]);
            exit();
        }
    }
    header("Location: admin_page.php?tab=upload");
    exit();
}

    header("Location: admin_page.php?tab=" . ($_POST['current_tab'] ?? 'overview'));
    exit();
}

$active_tab = $_GET['tab'] ?? 'overview';


$stats = [];
$stats['users']    = $conn->query("SELECT COUNT(*) as t FROM users")->fetch_assoc()['t'];
$stats['books']    = $conn->query("SELECT COUNT(*) as t FROM books")->fetch_assoc()['t'];
$stats['saves']    = $conn->query("SELECT COUNT(*) as t FROM user_books")->fetch_assoc()['t'];
$stats['reviews']  = $conn->query("SELECT COUNT(*) as t FROM user_books WHERE review IS NOT NULL AND review != ''")->fetch_assoc()['t'];
$stats['follows']  = $conn->query("SELECT COUNT(*) as t FROM follows")->fetch_assoc()['t'];
$stats['completed'] = $conn->query("SELECT COUNT(*) as t FROM user_books WHERE status = 'completed'")->fetch_assoc()['t'];


$users = $conn->query("
    SELECT u.name, u.email, u.role,
           COUNT(DISTINCT ub.id) as book_count,
           COUNT(DISTINCT f.id)  as follower_count
    FROM users u
    LEFT JOIN user_books ub ON ub.user_email = u.email
    LEFT JOIN follows f     ON f.following_email = u.email
    GROUP BY u.email
    ORDER BY u.name ASC
")->fetch_all(MYSQLI_ASSOC);


$books = $conn->query("
    SELECT b.id, b.book_title, b.author, b.category, b.created_at, b.added_by_email,
           COUNT(DISTINCT ub.id)    as save_count,
           ROUND(AVG(ub.rating), 1) as avg_rating
    FROM books b
    LEFT JOIN user_books ub ON ub.book_id = b.id
    GROUP BY b.id
    ORDER BY b.created_at DESC
")->fetch_all(MYSQLI_ASSOC);


$reviews = $conn->query("
    SELECT ub.id, ub.review, ub.rating, ub.user_email,
           u.name, b.book_title, b.id as book_id
    FROM user_books ub
    JOIN users u ON ub.user_email = u.email
    JOIN books b ON ub.book_id    = b.id
    WHERE ub.review IS NOT NULL AND ub.review != ''
    ORDER BY ub.added_at DESC
")->fetch_all(MYSQLI_ASSOC);


$activity = $conn->query("
    SELECT u.name, b.book_title, ub.status, ub.added_at
    FROM user_books ub
    JOIN users u ON ub.user_email = u.email
    JOIN books b ON ub.book_id    = b.id
    ORDER BY ub.added_at DESC
    LIMIT 20
")->fetch_all(MYSQLI_ASSOC);
$chart_data = $conn->query("
    SELECT DATE_FORMAT(created_at, '%b %Y') as month,
           DATE_FORMAT(created_at, '%Y-%m') as sort_key,
           COUNT(*) as total
    FROM books
    GROUP BY sort_key, month  -- Add 'month' here
    ORDER BY sort_key ASC
    LIMIT 12
")->fetch_all(MYSQLI_ASSOC);
$chart_labels = json_encode(array_column($chart_data, 'month'));
$chart_values = json_encode(array_column($chart_data, 'total'));

$categories = $conn->query("SELECT * FROM categories ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="admin.css">
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
    <title>Admin Panel — MyBookTracker</title>
</head>
<body>
<header>
        <h1 class="logo"> <a href="user_page.php">MyBookTracker</a> </h1>
           
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

<main class="admin-body">

    <h1 class="admin-page-title">Admin Panel</h1>
    <p class="admin-breadcrumb"><a href="user_page.php">Home</a> › Admin Panel</p>

   
    <section class="admin-stats">
        <div class="admin-stat-card">
            <div class="admin-stat-card__icon"></div>
            <div class="admin-stat-card__info">
                <div class="admin-stat-card__number"><?= $stats['users'] ?></div>
                <div class="admin-stat-card__label">Total Users</div>
            </div>
        </div>
        <div class="admin-stat-card">
            <div class="admin-stat-card__icon"></div>
            <div class="admin-stat-card__info">
                <div class="admin-stat-card__number"><?= $stats['books'] ?></div>
                <div class="admin-stat-card__label">Books in Catalogue</div>
            </div>
        </div>
        <div class="admin-stat-card">
            <div class="admin-stat-card__icon"></div>
            <div class="admin-stat-card__info">
                <div class="admin-stat-card__number"><?= $stats['saves'] ?></div>
                <div class="admin-stat-card__label">Books Saved</div>
            </div>
        </div>
        
        <div class="admin-stat-card">
            <div class="admin-stat-card__icon"></div>
            <div class="admin-stat-card__info">
                <div class="admin-stat-card__number"><?= $stats['reviews'] ?></div>
                <div class="admin-stat-card__label">Reviews Written</div>
            </div>
        </div>
        
    </section>

    <div class="admin-main-grid">

        <div class="admin-panel">
            <div class="admin-panel__header">
                <h2 class="admin-panel__title">Books Added Over Time</h2>
            </div>
            <canvas id="booksChart" height="120"></canvas>
        </div>

        <div class="admin-panel">
            <div class="admin-panel__header">
                <h2 class="admin-panel__title">Recent Activity</h2>
            </div>
            <div class="admin-activity-feed">
                <?php foreach (array_slice($activity, 0, 15) as $a): ?>
                <div class="admin-activity-item">
                    <div class="admin-activity-item__dot"></div>
                    <div class="admin-activity-item__text">
                        <strong><?= htmlspecialchars($a['name']) ?></strong> added
                        <em><?= htmlspecialchars($a['book_title']) ?></em>
                        <span class="admin-badge admin-badge--<?= $a['status'] ?>"><?= $a['status'] ?></span>
                    </div>
                    <div class="admin-activity-item__time"><?= date('M j', strtotime($a['added_at'])) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <div class="admin-panel">

        <div class="admin-tab-panel-header">
            <div class="admin-tab-bar">
                <button class="admin-tab-btn active" onclick="switchTab('users', this)">Users (<?= $stats['users'] ?>)</button>
                <button class="admin-tab-btn" onclick="switchTab('upload', this)">Upload Book</button>
                <button class="admin-tab-btn"         onclick="switchTab('books', this)">Books (<?= $stats['books'] ?>)</button>
                <button class="admin-tab-btn"         onclick="switchTab('reviews', this)">Reviews (<?= $stats['reviews'] ?>)</button>
                <button class="admin-tab-btn"         onclick="switchTab('activity', this)">All Activity</button>
            </div>
            <input type="text" class="admin-search" placeholder="Filter..." oninput="filterActive(this)">
        </div>

        <div id="tab-users" class="admin-tab-content active">
            <table class="admin-table" id="tbl-users">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Books</th>
                        <th>Followers</th>
                        <?php if ($is_admin): ?><th>Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <div class="admin-table__user-cell">
                            <div class="admin-avatar"><?= strtoupper($u['name'][0]) ?></div>
                            <a href="profile.php?email=<?= urlencode($u['email']) ?>" class="admin-table__link">
                                <?= htmlspecialchars($u['name']) ?>
                            </a>
                        </div>
                    </td>
                    <td class="admin-table__cell--muted"><?= htmlspecialchars($u['email']) ?></td>
                    <td>
                        <span class="admin-badge admin-badge--<?= $u['role'] ?? 'user' ?>">
                            <?= htmlspecialchars($u['role'] ?? 'user') ?>
                        </span>
                    </td>
                    <td><?= $u['book_count'] ?></td>
                    <td><?= $u['follower_count'] ?></td>
                    <?php if ($is_admin): ?>
                    <td>
                        <?php if ($u['email'] !== $user_email): ?>
                        <div class="admin-table__actions">
                            <form method="POST" class="admin-inline-form">
                                <input type="hidden" name="action"       value="toggle_admin">
                                <input type="hidden" name="target_email" value="<?= htmlspecialchars($u['email']) ?>">
                                <input type="hidden" name="current_tab"  value="users">
                                <button type="submit" class="admin-btn-role">
                                    <?= $u['role'] === 'admin' ? 'Demote' : 'Make Admin' ?>
                                </button>
                            </form>
                            <form method="POST" class="admin-inline-form" onsubmit="return confirm('Delete this user and all their data?')">
                                <input type="hidden" name="action"       value="delete_user">
                                <input type="hidden" name="target_email" value="<?= htmlspecialchars($u['email']) ?>">
                                <input type="hidden" name="current_tab"  value="users">
                                <button type="submit" class="admin-btn-delete">Delete</button>
                            </form>
                        </div>
                        <?php else: ?>
                        <span class="admin-table__self-label">You</span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

       
        <div id="tab-books" class="admin-tab-content">
            <table class="admin-table" id="tbl-books">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Author</th>
                        <th>Category</th>
                        <th>Saves</th>
                        <th>Avg Rating</th>
                        <th>Added</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($books as $b): ?>
                <tr>
                    <td>
                        <a href="book_detail.php?id=<?= $b['id'] ?>" class="admin-table__link">
                            <?= htmlspecialchars($b['book_title']) ?>
                        </a>
                    </td>
                    <td class="admin-table__cell--muted"><?= htmlspecialchars($b['author']) ?></td>
                    <td>
                        <span class="admin-badge admin-badge--user">
                            <?= htmlspecialchars($b['category'] ?? '—') ?>
                        </span>
                    </td>
                    <td><?= $b['save_count'] ?></td>
                    <td class="admin-table__cell--rating"><?= $b['avg_rating'] ? $b['avg_rating'] . ' ★' : '—' ?></td>
                    <td class="admin-table__cell--light"><?= date('M j Y', strtotime($b['created_at'])) ?></td>
                    <td>
                        <form method="POST" class="admin-inline-form" onsubmit="return confirm('Delete this book and all associated data?')">
                            <input type="hidden" name="action"      value="delete_book">
                            <input type="hidden" name="book_id"     value="<?= $b['id'] ?>">
                            <input type="hidden" name="current_tab" value="books">
                            <button type="submit" class="admin-btn-delete">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- UPLOAD -->
<div id="tab-upload" class="admin-tab-content">
    <div style="max-width:600px;">
        <h3 style="color:#44222f; margin-bottom:20px;">Upload a Book</h3>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload_book">
            <input type="hidden" name="current_tab" value="upload">

            <div style="margin-bottom:14px;">
                <label style="font-size:0.85rem; color:#888; display:block; margin-bottom:4px;">Title</label>
                <input type="text" name="title" required
                    style="width:100%; padding:9px 14px; border:1px solid #e0e0e0; border-radius:8px; font-family:inherit; font-size:0.9rem; background:#fafafa;">
            </div>

            <div style="margin-bottom:14px;">
                <label style="font-size:0.85rem; color:#888; display:block; margin-bottom:4px;">Description</label>
                <textarea name="description" rows="3"
                    style="width:100%; padding:9px 14px; border:1px solid #e0e0e0; border-radius:8px; font-family:inherit; font-size:0.9rem; background:#fafafa; resize:vertical;"></textarea>
            </div>

            <div style="margin-bottom:14px;">
                <label style="font-size:0.85rem; color:#888; display:block; margin-bottom:4px;">Category</label>
                <div style="display:flex; gap:8px; align-items:center;">
                    <select name="category_id" id="upload_category_select" required
                        style="flex:1; padding:9px 14px; border:1px solid #e0e0e0; border-radius:8px; font-family:inherit; font-size:0.9rem; background:#fafafa;">
                        <option value="" hidden>Select category</option>
                        <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" onclick="openCatModal()"
                        style="padding:9px 16px; border:1px solid rgba(140,55,79,0.3); border-radius:8px; background:transparent; color:#8c374f; cursor:pointer; font-size:1.1rem; font-weight:600;">+</button>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:20px;">
                <div>
                    <label style="font-size:0.85rem; color:#888; display:block; margin-bottom:4px;">Cover Image</label>
                    <input type="file" name="cover" accept="image/*" required
                        style="width:100%; padding:7px; border:1px solid #e0e0e0; border-radius:8px; background:#fafafa; font-size:0.85rem;">
                </div>
                <div>
                    <label style="font-size:0.85rem; color:#888; display:block; margin-bottom:4px;">PDF File</label>
                    <input type="file" name="pdf" accept=".pdf" required
                        style="width:100%; padding:7px; border:1px solid #e0e0e0; border-radius:8px; background:#fafafa; font-size:0.85rem;">
                </div>
            </div>

            <button type="submit"
                style="padding:10px 28px; background:#8c374f; color:#fff; border:none; border-radius:8px; cursor:pointer; font-family:inherit; font-size:0.9rem; font-weight:500;">
                Upload Book
            </button>
        </form>

        <!-- Add category modal -->
        <div id="catModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:999; align-items:center; justify-content:center;">
            <div style="background:#fff; width:380px; padding:24px; border-radius:12px; position:relative;">
                <span onclick="closeCatModal()"
                    style="position:absolute; top:12px; right:16px; font-size:22px; cursor:pointer; color:#888;">&times;</span>
                <h3 style="color:#44222f; margin-bottom:16px;">Add Category</h3>
                <input type="text" id="new_cat_name" placeholder="Category name"
                    style="width:100%; padding:9px 14px; border:1px solid #e0e0e0; border-radius:8px; font-family:inherit; margin-bottom:12px;">
                <button onclick="submitCategory()"
                    style="width:100%; padding:9px; background:#8c374f; color:#fff; border:none; border-radius:8px; cursor:pointer; font-family:inherit;">
                    Save Category
                </button>
            </div>
        </div>
    </div>
</div>
        
        <div id="tab-reviews" class="admin-tab-content">
            <table class="admin-table" id="tbl-reviews">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Book</th>
                        <th>Rating</th>
                        <th>Review</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($reviews as $r): ?>
                <tr>
                    <td>
                        <div class="admin-table__user-cell">
                            <div class="admin-avatar"><?= strtoupper($r['name'][0]) ?></div>
                            <a href="profile.php?email=<?= urlencode($r['user_email']) ?>" class="admin-table__link">
                                <?= htmlspecialchars($r['name']) ?>
                            </a>
                        </div>
                    </td>
                    <td>
                        <a href="book_detail.php?id=<?= $r['book_id'] ?>" class="admin-table__link">
                            <?= htmlspecialchars($r['book_title']) ?>
                        </a>
                    </td>
                    <td class="admin-table__cell--rating">
                        <?= $r['rating'] ? str_repeat('★', $r['rating']) : '—' ?>
                    </td>
                    <td class="admin-table__cell--review">
                        <?= htmlspecialchars(mb_substr($r['review'], 0, 80)) ?><?= strlen($r['review']) > 80 ? '…' : '' ?>
                    </td>
                    <td>
                        <form method="POST" class="admin-inline-form" onsubmit="return confirm('Remove this review?')">
                            <input type="hidden" name="action"      value="delete_review">
                            <input type="hidden" name="ub_id"       value="<?= $r['id'] ?>">
                            <input type="hidden" name="current_tab" value="reviews">
                            <button type="submit" class="admin-btn-delete">Remove</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

       
        <div id="tab-activity" class="admin-tab-content">
            <?php
            $all_activity = $conn->query("
                SELECT u.name, b.book_title, ub.status, ub.rating, ub.added_at
                FROM user_books ub
                JOIN users u ON ub.user_email = u.email
                JOIN books b ON ub.book_id    = b.id
                ORDER BY ub.added_at DESC
                LIMIT 100
            ")->fetch_all(MYSQLI_ASSOC);
            ?>
            <table class="admin-table" id="tbl-activity">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Book</th>
                        <th>Status</th>
                        <th>Rating</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($all_activity as $a): ?>
                <tr>
                    <td>
                        <div class="admin-table__user-cell">
                            <div class="admin-avatar"><?= strtoupper($a['name'][0]) ?></div>
                            <?= htmlspecialchars($a['name']) ?>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($a['book_title']) ?></td>
                    <td>
                        <span class="admin-badge admin-badge--<?= $a['status'] ?>">
                            <?= $a['status'] ?>
                        </span>
                    </td>
                    <td class="admin-table__cell--rating">
                        <?= $a['rating'] ? str_repeat('★', $a['rating']) : '—' ?>
                    </td>
                    <td class="admin-table__cell--light">
                        <?= date('M j, g:ia', strtotime($a['added_at'])) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div><!-- end tabbed panel -->

</main>

<script src="script.js"></script>
<script>
function switchTab(name, btn) {
    document.querySelectorAll('.admin-tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.admin-tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
    document.querySelector('.admin-search').value = '';
}

function filterActive(input) {
    const filter = input.value.toLowerCase();
    const activeTab = document.querySelector('.admin-tab-content.active');
    if (!activeTab) return;
    activeTab.querySelectorAll('tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
    });
}

const ctx = document.getElementById('booksChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= $chart_labels ?>,
        datasets: [{
            label: 'Books Added',
            data: <?= $chart_values ?>,
            borderColor: '#8c374f',
            backgroundColor: 'rgba(140,55,79,0.08)',
            borderWidth: 2,
            pointBackgroundColor: '#8c374f',
            pointRadius: 4,
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1, color: '#aaa', font: { size: 11 } }, grid: { color: '#f0f0f0' } },
            x: { ticks: { color: '#aaa', font: { size: 11 } }, grid: { display: false } }
        }
    }
});
function openCatModal() {
    document.getElementById('catModal').style.display = 'flex';
}
function closeCatModal() {
    document.getElementById('catModal').style.display = 'none';
    document.getElementById('new_cat_name').value = '';
}
function submitCategory() {
    const catName = document.getElementById('new_cat_name').value.trim();
    if (!catName) { alert('Enter a category name'); return; }

    const fd = new FormData();
    fd.append('action', 'add_category');
    fd.append('cat_name', catName);
    fd.append('ajax', '1');

    fetch('admin_page.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            const sel = document.getElementById('upload_category_select');
            const opt = document.createElement('option');
            opt.value = data.id;
            opt.text  = data.name;
            opt.selected = true;
            sel.appendChild(opt);
            closeCatModal();
        })
        .catch(() => alert('Error adding category'));
}
window.onclick = e => {
    if (e.target.id === 'catModal') closeCatModal();
};
</script>
<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</body>
</html>