<?php
// profile.php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'config.php';

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success_msg = "";
$error_msg = "";

// --- NOTIFICATION CHECK LOGIC ---
$unread_count = 0;
$notif_stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
if ($notif_stmt) {
    $notif_stmt->bind_param("i", $user_id);
    $notif_stmt->execute();
    $notif_stmt->bind_result($unread_count);
    $notif_stmt->fetch();
    $notif_stmt->close();
}

// Fetch current user details
$stmt = $conn->prepare("SELECT fullname, email, role FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fullname = htmlspecialchars($_POST['fullname']);
    $email = htmlspecialchars($_POST['email']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($fullname) || empty($email)) {
        $error_msg = "Your Fullname and Email are required to keep your profile updated.";
    } else {
        // Handle password update if fields are filled
        if (!empty($new_password)) {
            if ($new_password !== $confirm_password) {
                $error_msg = "The passwords you entered do not match. Please try again.";
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = $conn->prepare("UPDATE users SET fullname = ?, email = ?, password = ? WHERE id = ?");
                $update_stmt->bind_param("sssi", $fullname, $email, $hashed_password, $user_id);
            }
        } else {
            // Update without password
            $update_stmt = $conn->prepare("UPDATE users SET fullname = ?, email = ? WHERE id = ?");
            $update_stmt->bind_param("ssi", $fullname, $email, $user_id);
        }

        if (empty($error_msg)) {
            if ($update_stmt->execute()) {
                $success_msg = "Awesome! Your profile has been successfully updated.";
                // Update session variables
                $_SESSION['fullname'] = $fullname;
                $user['fullname'] = $fullname;
                $user['email'] = $email;
            } else {
                $error_msg = "We encountered a small database issue. Please try again later.";
            }
            $update_stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | JEJ Surveying Services</title>
    
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
        @keyframes fadeInUp { 0% { opacity: 0; transform: translateY(40px); } 100% { opacity: 1; transform: translateY(0); } }
        @keyframes fadeIn { 0% { opacity: 0; } 100% { opacity: 1; } }
        @keyframes dropIn { from { opacity: 0; transform: scale(0.9) translateY(-10px); } to { opacity: 1; transform: scale(1) translateY(0); } }

        .animate-on-scroll {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.8s cubic-bezier(0.16, 1, 0.3, 1), transform 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .animate-on-scroll.visible { opacity: 1; transform: translateY(0); }

        .hero-title { animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards; opacity: 0; }
        .hero-subtitle { animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) 0.2s forwards; opacity: 0; }

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
        .profile-dropdown-item i { width: 20px; text-align: center; color: var(--text-gray); }
        .profile-dropdown-item:hover { background: #f8fafc; color: var(--primary); padding-left: 25px; }
        .profile-dropdown-item:hover i { color: var(--primary); }
        .profile-dropdown-item.logout-btn { color: #ef4444; border-top: 1px solid #f1f5f9; }
        .profile-dropdown-item.logout-btn:hover { background: #fef2f2; color: #dc2626; }

        /* Hero Section (Profile specific) */
        .hero {
            margin-top: 76px; 
            height: 40vh; min-height: 350px;
            background: linear-gradient(to right, rgba(15, 23, 42, 0.85), rgba(30, 75, 54, 0.8)), url('assets/login2.JPG') center/cover no-repeat;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            color: white; text-align: center; padding: 0 20px 60px 20px;
        }
        .hero h1 { font-size: 3.5rem; font-weight: 800; margin-bottom: 10px; letter-spacing: -1px; text-shadow: 0 2px 15px rgba(0,0,0,0.4); }
        .hero p { font-size: 1.15rem; font-weight: 400; opacity: 0.9; max-width: 600px; text-shadow: 0 1px 5px rgba(0,0,0,0.3); }

        /* Profile Layout */
        .container { max-width: 1200px; margin: 0 auto 80px auto; padding: 0 5%; }
        
        .profile-wrapper {
            max-width: 900px;
            margin: -80px auto 0 auto;
            background: white;
            border-radius: 24px;
            box-shadow: var(--shadow-lg);
            position: relative;
            z-index: 10;
            padding: 40px;
        }

        .profile-avatar-container { text-align: center; margin-top: -90px; margin-bottom: 20px; }
        
        .profile-avatar-large {
            width: 110px; height: 110px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white; font-size: 2.5rem; font-weight: 800;
            border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
            box-shadow: 0 8px 25px rgba(30, 75, 54, 0.3);
            border: 5px solid white;
        }

        .user-title { text-align: center; margin-bottom: 40px; }
        .user-title h2 { margin: 0; font-size: 2rem; font-weight: 800; color: var(--text-dark); letter-spacing: -0.5px; }
        
        .role-badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: #f0fdf4; color: var(--primary);
            padding: 6px 16px; border-radius: 30px;
            font-size: 0.85rem; font-weight: 800; text-transform: uppercase;
            margin-top: 10px; border: 1px solid #bbf7d0; letter-spacing: 0.5px;
        }

        /* Form UI Updates */
        .form-section-title {
            font-size: 1.3rem; font-weight: 800; color: var(--text-dark);
            margin-bottom: 20px; display: flex; align-items: center; gap: 10px;
            border-bottom: 2px solid #f1f5f9; padding-bottom: 10px;
        }
        .form-section-title i { color: var(--primary); }

        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 25px; }
        .form-group { width: 100%; }
        
        .form-label { display: block; margin-bottom: 8px; font-weight: 700; color: var(--text-dark); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .form-control {
            width: 100%; padding: 15px 20px; border: 2px solid #e2e8f0;
            border-radius: 12px; outline: none; background: #f8fafc;
            font-size: 1rem; color: var(--text-dark); font-family: inherit; font-weight: 500;
            transition: all 0.3s;
        }
        .form-control::placeholder { color: #94a3b8; }
        .form-control:focus { border-color: var(--primary); background: white; box-shadow: 0 0 0 4px rgba(30,75,54,0.1); }

        .section-divider { height: 1px; background: transparent; margin: 40px 0; }

        .btn-submit {
            background: var(--primary); color: white; border: none;
            padding: 16px 40px; border-radius: 12px;
            font-size: 1.05rem; font-weight: 800; font-family: inherit;
            cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; justify-content: center; gap: 10px; box-shadow: 0 4px 15px rgba(30,75,54,0.2);
        }
        .btn-submit:hover { background: var(--primary-light); transform: translateY(-2px); box-shadow: 0 8px 25px rgba(30, 75, 54, 0.3); }

        /* Alerts */
        .alert { padding: 18px 25px; border-radius: 16px; margin-bottom: 30px; font-weight: 600; display: flex; align-items: center; gap: 15px; font-size: 0.95rem; box-shadow: var(--shadow-sm); }
        .alert-success { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .alert-error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

        .footer { background: var(--text-dark); color: white; text-align: center; padding: 30px; margin-top: auto; font-size: 0.9rem; opacity: 0.9; }

        @media (max-width: 768px) {
            .nav-links.desktop-only { display: none; }
            .brand-text-container { display: none; }
            .form-row { grid-template-columns: 1fr; gap: 20px; }
            .profile-wrapper { padding: 30px 20px; margin-top: -60px; border-radius: 20px; }
            .hero h1 { font-size: 2.5rem; }
            .btn-submit { width: 100%; }
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
                    <a href="profile.php" class="profile-dropdown-item" style="color: var(--primary);"><i class="fa-regular fa-user" style="color: var(--primary);"></i> My Profile</a>
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

    <header class="hero">
        <h1 class="hero-title">Your Profile Account</h1>
        <p class="hero-subtitle">Manage your personal details and security preferences securely.</p>
    </header>

    <div class="container">
        <div class="profile-wrapper animate-on-scroll">
            
            <div class="profile-avatar-container">
                <div class="profile-avatar-large">
                    <?= strtoupper(substr($user['fullname'], 0, 1)) ?>
                </div>
            </div>

            <div class="user-title">
                <h2><?= htmlspecialchars($user['fullname']) ?></h2>
                <div class="role-badge">
                    <i class="fa-solid fa-shield-halved"></i> <?= htmlspecialchars($user['role']) ?>
                </div>
            </div>

            <div class="profile-body">
                <?php if($success_msg): ?>
                    <div class="alert alert-success">
                        <i class="fa-solid fa-circle-check" style="font-size: 1.4rem;"></i> 
                        <?= $success_msg ?>
                    </div>
                <?php endif; ?>
                <?php if($error_msg): ?>
                    <div class="alert alert-error">
                        <i class="fa-solid fa-circle-exclamation" style="font-size: 1.4rem;"></i> 
                        <?= $error_msg ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="profile.php">
                    
                    <h3 class="form-section-title"><i class="fa-regular fa-id-badge"></i> Personal Information</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="fullname" class="form-control" value="<?= htmlspecialchars($user['fullname']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="section-divider"></div>

                    <h3 class="form-section-title"><i class="fa-solid fa-lock"></i> Security Details</h3>
                    <p style="font-size: 0.95rem; color: var(--text-gray); margin-top: -10px; margin-bottom: 25px; font-weight: 500;">
                        Leave the fields below blank if you prefer to keep your current password.
                    </p>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-control" placeholder="Create a new password">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" placeholder="Type it again to confirm">
                        </div>
                    </div>

                    <div style="text-align: right; margin-top: 25px;">
                        <button type="submit" class="btn-submit">
                            Save Changes <i class="fa-solid fa-floppy-disk"></i>
                        </button>
                    </div>
                </form>
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
                document.addEventListener('click', function(e) {
                    if (!profileBtn.contains(e.target) && !profileDropdown.contains(e.target)) {
                        profileDropdown.classList.remove('active');
                    }
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