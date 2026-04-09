<?php
// notifications.php
ob_start(); 

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'config.php';

// Ensure user is logged in. Redirect to index.php since login.php is removed.
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// 1. Mark all unread notifications as read since the user is now viewing them
$update_stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
$update_stmt->bind_param("i", $user_id);
$update_stmt->execute();
$update_stmt->close();

// 2. Fetch all notifications for this user
$query = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

// Unread count for the navbar will be 0 now because we just updated them
$unread_count = 0; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications | JEJ Surveying Services</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root { 
            --primary: #1e4b36; 
            --primary-light: #2d6a4f;
            --accent: #d4af37; 
            --bg-color: #f8fafc;
            --text-dark: #0f172a;
            --text-gray: #64748b;
            --white: #ffffff;
            --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 10px 25px -5px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 20px 40px -10px rgba(0,0,0,0.12);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background-color: var(--bg-color); 
            color: var(--text-dark); 
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* --- ANIMATIONS --- */
        @keyframes dropIn { from { opacity: 0; transform: scale(0.9) translateY(-10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        @keyframes fadeIn { 0% { opacity: 0; } 100% { opacity: 1; } }

        .animate-on-scroll {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.8s cubic-bezier(0.16, 1, 0.3, 1), transform 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .animate-on-scroll.visible { opacity: 1; transform: translateY(0); }

        /* Navbar - Glassmorphism */
        .nav {
            position: fixed; top: 0; left: 0; right: 0;
            display: flex; justify-content: space-between; align-items: center;
            padding: 15px 5%; background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255,255,255,0.3); box-shadow: var(--shadow-sm);
            z-index: 100; transition: all 0.3s ease;
        }

        .brand-wrapper a {
            display: flex; align-items: center; gap: 12px;
            text-decoration: none; color: var(--primary); transition: transform 0.3s ease;
        }
        .brand-wrapper a:hover { transform: scale(1.02); }
        .brand-logo-img { height: 48px; width: auto; object-fit: contain; }
        .brand-text-container { display: flex; flex-direction: column; justify-content: center; }
        .nav-brand { font-size: 1.3rem; font-weight: 800; letter-spacing: -0.5px; line-height: 1.1; color: var(--primary); }
        .nav-brand-sub { font-size: 0.65rem; color: var(--text-gray); font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; }
        
        .nav-links { display: flex; gap: 30px; }
        .nav-links a { text-decoration: none; color: var(--text-gray); font-weight: 600; font-size: 0.95rem; transition: color 0.2s; position: relative; }
        .nav-links a:hover, .nav-links a.active { color: var(--primary); }
        .nav-links a.active::after { content: ''; position: absolute; bottom: -5px; left: 0; width: 100%; height: 2px; background: var(--primary); border-radius: 2px; animation: fadeIn 0.3s ease; }

        /* User Menu & Dropdown */
        .user-menu { display: flex; align-items: center; gap: 15px; }
        .notification-bell { position: relative; color: var(--primary); font-size: 22px; text-decoration: none; transition: color 0.3s; }
        .notification-bell:hover { transform: scale(1.1); }
        .notification-dot { position: absolute; top: 0; right: -2px; width: 10px; height: 10px; background-color: #ef4444; border-radius: 50%; border: 2px solid white; }
        
        .profile-dropdown-container { position: relative; }
        .profile-trigger { display: flex; align-items: center; gap: 12px; background: transparent; border: 1px solid #e2e8f0; cursor: pointer; padding: 6px 12px; border-radius: 40px; transition: all 0.2s ease; }
        .profile-trigger:hover { background: #f8fafc; border-color: #cbd5e1; box-shadow: var(--shadow-sm); }
        .profile-info { text-align: right; }
        .profile-name { display: block; font-weight: 700; font-size: 0.9rem; color: var(--text-dark); font-family: 'Plus Jakarta Sans', sans-serif;}
        .profile-role { display: block; font-size: 0.7rem; color: var(--primary); font-weight: 800; letter-spacing: 0.5px; text-transform: uppercase; }
        .avatar-circle { background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; border-radius: 50%; width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1.1rem; box-shadow: 0 2px 8px rgba(30,75,54,0.3); }
        
        .profile-dropdown-menu { display: none; position: absolute; top: 120%; right: 0; background: white; min-width: 240px; border-radius: 16px; box-shadow: var(--shadow-lg); border: 1px solid #f1f5f9; overflow: hidden; z-index: 100; }
        .profile-dropdown-menu.active { display: block; animation: dropIn 0.2s cubic-bezier(0.16, 1, 0.3, 1) forwards; transform-origin: top right; }
        
        .profile-dropdown-item { display: flex; align-items: center; gap: 12px; padding: 15px 20px; text-decoration: none; color: var(--text-dark); font-size: 0.95rem; font-weight: 600; transition: all 0.2s; }
        .profile-dropdown-item:hover { background: #f8fafc; color: var(--primary); padding-left: 25px; }
        .profile-dropdown-item.logout-btn { color: #ef4444; border-top: 1px solid #f1f5f9; }
        .profile-dropdown-item.logout-btn:hover { background: #fef2f2; color: #dc2626; }

        /* Notifications Page Specific Styles */
        .page-container {
            max-width: 850px;
            margin: 120px auto 80px auto; /* Increased top margin for fixed navbar */
            padding: 0 5%;
        }

        .notifications-wrapper {
            background: white;
            border-radius: 24px;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            padding: 40px;
        }
        
        .notif-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #f1f5f9;
        }

        .notif-header h2 {
            margin: 0;
            font-size: 2rem;
            font-weight: 800;
            color: var(--text-dark);
            letter-spacing: -0.5px;
        }

        .notif-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .notif-item {
            display: flex;
            gap: 20px;
            padding: 25px;
            border-radius: 16px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .notif-item:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
            border-color: #cbd5e1;
        }

        .notif-icon {
            width: 50px;
            height: 50px;
            background: rgba(30, 75, 54, 0.08);
            color: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0;
        }

        .notif-content h4 {
            margin: 0 0 8px 0;
            color: var(--text-dark);
            font-size: 1.1rem;
            font-weight: 700;
        }

        .notif-content p {
            margin: 0;
            color: var(--text-gray);
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .notif-time {
            display: block;
            margin-top: 12px;
            font-size: 0.85rem;
            color: #94a3b8;
            font-weight: 500;
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: var(--text-gray);
        }
        
        .footer { background: var(--text-dark); color: white; text-align: center; padding: 30px; margin-top: auto; font-size: 0.9rem; opacity: 0.9; }

        @media (max-width: 768px) {
            .nav-links.desktop-only { display: none; }
            .brand-text-container { display: none; }
            .notifications-wrapper { padding: 25px; border-radius: 16px; }
            .notif-item { flex-direction: column; gap: 15px; padding: 20px; }
        }
    </style>
</head>
<body>

    <nav class="nav">
        <div class="brand-wrapper">
            <a href="index.php">
                <img src="assets/logo.png" alt="JEJ Surveying Logo" class="brand-logo-img">
                <div class="brand-text-container">
                    <span class="nav-brand">JEJ Surveying</span>
                    <span class="nav-brand-sub">Services & Real Estate</span>
                </div>
            </a>
        </div>
        
        <div class="nav-links desktop-only">
            <a href="index.php">Properties</a>
            <a href="contact.php">Contact Us</a>
        </div>

        <div class="user-menu">
            <a href="notifications.php" class="notification-bell" title="Notifications">
                <i class="fa-solid fa-bell"></i>
                <?php if($unread_count > 0): ?> <span class="notification-dot"></span> <?php endif; ?>
            </a>
            
            <div class="profile-dropdown-container">
                <button class="profile-trigger" id="profileBtn">
                    <div class="profile-info desktop-only">
                        <span class="profile-name"><?= htmlspecialchars($_SESSION['fullname']) ?></span>
                        <span class="profile-role"><?= htmlspecialchars($_SESSION['role']) ?></span>
                    </div>
                    <div class="avatar-circle">
                        <?= htmlspecialchars(strtoupper(substr($_SESSION['fullname'], 0, 1))) ?>
                    </div>
                </button>
                <div class="profile-dropdown-menu" id="profileDropdown">
                    <a href="profile.php" class="profile-dropdown-item"><i class="fa-regular fa-user"></i> My Profile</a>
                    <?php if(in_array($_SESSION['role'], ['SUPER ADMIN', 'ADMIN', 'MANAGER'])): ?>
                        <a href="admin.php" class="profile-dropdown-item"><i class="fa-solid fa-shield-halved"></i> Admin Dashboard</a>
                    <?php else: ?>
                        <a href="my_reservations.php" class="profile-dropdown-item"><i class="fa-solid fa-file-contract"></i> My Reservations</a>
                    <?php endif; ?>
                    <a href="logout.php" class="profile-dropdown-item logout-btn"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="page-container">
        <div class="notifications-wrapper animate-on-scroll">
            <div class="notif-header">
                <h2>Your Notifications</h2>
            </div>

            <div class="notif-list">
                <?php if($result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <div class="notif-item">
                            <div class="notif-icon">
                                <i class="fa-solid fa-circle-info"></i>
                            </div>
                            <div class="notif-content">
                                <h4><?= htmlspecialchars($row['title']) ?></h4>
                                <p><?= htmlspecialchars($row['message']) ?></p>
                                <span class="notif-time">
                                    <i class="fa-regular fa-clock" style="margin-right: 6px;"></i>
                                    <?= date("F j, Y, g:i a", strtotime($row['created_at'])) ?>
                                </span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fa-regular fa-bell-slash" style="font-size: 45px; margin-bottom: 20px; color: #cbd5e1;"></i>
                        <h3 style="color: var(--text-dark); margin-bottom: 8px; font-size: 1.5rem;">No notifications yet</h3>
                        <p>When you receive updates about your reservations or inquiries, they will appear here.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer class="footer">
        <p>&copy; <?= date('Y') ?> JEJ Surveying Services. All Rights Reserved. Built with trust and excellence.</p>
    </footer>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Profile Dropdown Logic
            const profileBtn = document.getElementById('profileBtn');
            const profileDropdown = document.getElementById('profileDropdown');
            if (profileBtn && profileDropdown) {
                profileBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    profileDropdown.classList.toggle('active');
                });
                document.addEventListener('click', function() {
                    profileDropdown.classList.remove('active');
                });
            }

            // IntersectionObserver for scroll animations
            const observerOptions = { threshold: 0.1, rootMargin: "0px 0px -50px 0px" };
            const observer = new IntersectionObserver(function(entries, observer) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);

            document.querySelectorAll('.animate-on-scroll').forEach(el => { observer.observe(el); });
        });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>