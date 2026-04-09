<?php
// my_reservations.php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'config.php';

// 1. Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$alert_msg = "";
$alert_type = "";

// 2. Handle QR Payment Receipt Upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_qr_payment'])) {
    $res_id = intval($_POST['res_id']);
    
    if (isset($_FILES['dp_receipt']) && $_FILES['dp_receipt']['error'] == 0) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) mkdir($target_dir);
        
        $filename = time() . "_DP_" . basename($_FILES['dp_receipt']['name']);
        
        if (move_uploaded_file($_FILES['dp_receipt']['tmp_name'], $target_dir . $filename)) {
            // Update the reservation to show the payment is being verified
            $stmt = $conn->prepare("UPDATE reservations SET dp_proof = ?, dp_status = 'VERIFYING' WHERE id = ? AND user_id = ?");
            if ($stmt) {
                $stmt->bind_param("sii", $filename, $res_id, $user_id);
                if ($stmt->execute()) {
                    $alert_msg = "Payment receipt uploaded successfully! Our team will verify it shortly.";
                    $alert_type = "success";
                } else {
                    $alert_msg = "Database Error: Failed to update payment status.";
                    $alert_type = "error";
                }
                $stmt->close();
            }
        } else {
            $alert_msg = "Failed to upload the file. Please try again.";
            $alert_type = "error";
        }
    } else {
        $alert_msg = "Please select a valid image file for the receipt.";
        $alert_type = "error";
    }
}

// 3. Check for Unread Notifications
$unread_count = 0;
$notif_stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
if ($notif_stmt) {
    $notif_stmt->bind_param("i", $user_id);
    $notif_stmt->execute();
    $notif_stmt->bind_result($unread_count);
    $notif_stmt->fetch();
    $notif_stmt->close();
}

// 4. Fetch User Reservations
$query = "SELECT r.*, l.block_no, l.lot_no, l.property_type, l.total_price, l.location 
          FROM reservations r 
          JOIN lots l ON r.lot_id = l.id 
          WHERE r.user_id = ? 
          ORDER BY r.reservation_date DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$reservations = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reservations | JEJ Surveying Services</title>
    
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
        @keyframes fadeInUp {
            0% { opacity: 0; transform: translateY(40px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeIn {
            0% { opacity: 0; }
            100% { opacity: 1; }
        }
        @keyframes dropIn { 
            from { opacity: 0; transform: scale(0.9) translateY(-10px); } 
            to { opacity: 1; transform: scale(1) translateY(0); } 
        }
        
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
        .profile-name { display: block; font-weight: 700; font-size: 0.9rem; color: var(--text-dark); }
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

        /* Hero Section */
        .hero {
            margin-top: 76px; 
            height: 45vh; min-height: 380px;
            background: linear-gradient(to right, rgba(15, 23, 42, 0.85), rgba(30, 75, 54, 0.8)), url('assets/login2.JPG') center/cover no-repeat;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            color: white; text-align: center; padding: 0 20px;
        }
        .hero h1 { font-size: 3.5rem; font-weight: 800; margin-bottom: 15px; letter-spacing: -1px; text-shadow: 0 2px 15px rgba(0,0,0,0.4); }
        .hero p { font-size: 1.15rem; font-weight: 400; opacity: 0.9; max-width: 600px; text-shadow: 0 1px 5px rgba(0,0,0,0.3); }

        /* Main Container & Alerts */
        .res-container { max-width: 1280px; margin: -50px auto 80px auto; padding: 0 5%; position: relative; z-index: 10; }
        
        .alert-box { padding: 18px 25px; border-radius: 16px; margin-bottom: 35px; font-size: 0.95rem; font-weight: 600; display: flex; align-items: center; gap: 15px; box-shadow: var(--shadow-md); background: white; }
        .alert-error { border-left: 6px solid #ef4444; color: #b91c1c; }
        .alert-success { border-left: 6px solid #10b981; color: var(--primary); }

        /* Grid & Cards */
        .res-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap: 35px; }

        .res-card { background: white; border-radius: 24px; border: 1px solid #f1f5f9; box-shadow: var(--shadow-sm); overflow: hidden; display: flex; flex-direction: column; transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
        .res-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-lg); border-color: transparent; }
        
        .res-card-header { padding: 30px 25px 20px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: flex-start; }
        .res-title { margin: 0 0 8px 0; font-size: 1.35rem; font-weight: 800; color: var(--text-dark); letter-spacing: -0.5px; }
        .res-subtitle { margin: 0; color: var(--text-gray); font-size: 0.9rem; font-weight: 600; display: flex; align-items: center; gap: 6px; }
        
        .res-badge { padding: 8px 14px; border-radius: 8px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
        .badge-PENDING { background: #fef9c3; color: #a16207; }
        .badge-APPROVED { background: #d1fae5; color: #065f46; }
        .badge-REJECTED { background: #fee2e2; color: #b91c1c; }

        .res-card-body { padding: 25px; flex-grow: 1; background: #f8fafc; }
        
        /* Timeline */
        .timeline { list-style: none; padding: 0; margin: 0 0 30px 0; position: relative; }
        .timeline::before { content: ''; position: absolute; left: 15px; top: 8px; bottom: 8px; width: 2px; background: #e2e8f0; }
        .timeline-item { position: relative; padding-left: 45px; margin-bottom: 25px; }
        .timeline-item:last-child { margin-bottom: 0; }
        .timeline-item::before { content: ''; position: absolute; left: 0; top: 2px; width: 32px; height: 32px; border-radius: 50%; background: white; border: 2px solid #cbd5e1; z-index: 2; box-sizing: border-box; transition: all 0.3s;}
        .timeline-item.active::before { border-color: var(--primary); background: var(--primary); box-shadow: 0 0 0 6px rgba(30, 75, 54, 0.1); }
        .timeline-item.active::after { content: '\f00c'; font-family: 'Font Awesome 6 Free'; font-weight: 900; position: absolute; left: 9px; top: 8px; color: white; font-size: 14px; z-index: 3;}
        
        .tl-title { display: block; font-size: 1rem; font-weight: 700; color: var(--text-dark); margin-bottom: 4px; }
        .tl-desc { display: block; font-size: 0.85rem; color: var(--text-gray); line-height: 1.5; font-weight: 500; }

        /* Pricing Box */
        .price-box { background: white; border: 1px solid #e2e8f0; padding: 20px; border-radius: 16px; margin-bottom: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
        .price-row { display: flex; justify-content: space-between; font-size: 0.95rem; margin-bottom: 10px; color: var(--text-gray); font-weight: 500; }

        .res-card-footer { padding: 25px; border-top: 1px solid #f1f5f9; background: white; }
        
        /* Buttons */
        .btn-pay { 
            display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; 
            padding: 16px; border-radius: 12px; background: var(--primary); color: white; 
            font-weight: 800; text-decoration: none; border: none; cursor: pointer; 
            transition: all 0.3s; font-size: 1rem; box-shadow: 0 4px 15px rgba(30,75,54,0.2);
        }
        .btn-pay:hover { background: var(--primary-light); transform: translateY(-2px); box-shadow: 0 8px 25px rgba(30,75,54,0.3); }
        
        .status-msg { display: flex; align-items: center; justify-content: center; gap: 15px; width: 100%; padding: 16px; border-radius: 12px; font-weight: 700; font-size: 0.95rem; }
        .status-verifying { background: #f0f9ff; color: #0369a1; border: 1px solid #bae6fd; }

        /* Modal UI */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s ease; padding: 20px; box-sizing: border-box; }
        .modal-overlay.active { display: flex; opacity: 1; }
        
        .modal-content { background: white; border-radius: 24px; width: 100%; max-width: 550px; position: relative; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.4); transform: translateY(30px) scale(0.95); transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); overflow: hidden; padding: 40px; }
        .modal-overlay.active .modal-content { transform: translateY(0) scale(1); }

        .modal-close { position: absolute; top: 20px; right: 25px; background: #f1f5f9; border: none; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 16px; cursor: pointer; color: var(--text-gray); transition: all 0.2s ease; z-index: 1001; }
        .modal-close:hover { color: #ef4444; background: #fee2e2; transform: rotate(90deg); }

        .qr-box { background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 20px; padding: 35px 20px; text-align: center; margin-bottom: 25px; transition: 0.3s; }
        .qr-box:hover { border-color: var(--primary); background: white; box-shadow: var(--shadow-md); }
        .qr-box i { font-size: 55px; color: var(--primary); margin-bottom: 15px; }
        .qr-box h4 { margin: 0 0 5px 0; color: var(--text-dark); font-size: 1.2rem; font-weight: 800; }
        .qr-box p { margin: 0; color: var(--text-gray); font-size: 0.95rem; font-weight: 500; }

        .file-upload-wrapper { position: relative; width: 100%; height: 65px; margin-bottom: 20px; }
        .file-upload-input { position: absolute; left: 0; top: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; z-index: 2; }
        .file-upload-display { position: absolute; left: 0; top: 0; width: 100%; height: 100%; background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 12px; display: flex; align-items: center; padding: 0 20px; color: var(--text-gray); font-size: 1rem; font-weight: 600; transition: 0.3s; z-index: 1; box-sizing: border-box; gap: 12px;}
        .file-upload-wrapper:hover .file-upload-display { border-color: var(--primary); color: var(--primary); background: white; }

        .footer { background: var(--text-dark); color: white; text-align: center; padding: 30px; margin-top: auto; font-size: 0.9rem; opacity: 0.9; }

        @media (max-width: 768px) {
            .nav-links.desktop-only { display: none; }
            .brand-text-container { display: none; }
            .hero h1 { font-size: 2.5rem; }
            .res-card-header { padding: 25px 20px 15px; }
            .res-card-body { padding: 20px; }
            .res-card-footer { padding: 20px; }
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
                        <a href="my_reservations.php" class="profile-dropdown-item" style="color: var(--primary);"><i class="fa-solid fa-file-contract" style="color: var(--primary);"></i> My Reservations</a>
                    <?php endif; ?>
                    <a href="logout.php" class="profile-dropdown-item logout-btn"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <header class="hero">
        <h1 class="hero-title">My Property Portfolio</h1>
        <p class="hero-subtitle">Track your reservations, view payment terms, and upload receipts seamlessly.</p>
    </header>

    <div class="res-container">
        
        <?php if (!empty($alert_msg)): ?>
            <div class="alert-box alert-<?= $alert_type ?> animate-on-scroll">
                <i class="fa-solid <?= $alert_type == 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>" style="font-size: 1.4rem;"></i> 
                <?= htmlspecialchars($alert_msg) ?>
            </div>
        <?php endif; ?>

        <div class="res-grid">
            <?php if($reservations && $reservations->num_rows > 0): ?>
                <?php while($row = $reservations->fetch_assoc()): 
                    $total = $row['total_price'];
                    
                    // Fetch required_dp, otherwise fallback to 20%
                    $dp_amount = (!empty($row['required_dp']) && $row['required_dp'] > 0) ? $row['required_dp'] : ($total * 0.20); 
                    $dp_status = isset($row['dp_status']) ? $row['dp_status'] : 'UNPAID'; 

                    // --- NEW: FETCH TRACKED PAYMENTS FROM TRANSACTIONS ---
                    $res_id = $row['id'];
                    $desc_like_dp = "%Down Payment%Res#{$res_id}%";
                    $dp_query = $conn->prepare("SELECT SUM(amount) as total_paid FROM transactions WHERE type='INCOME' AND description LIKE ?");
                    $dp_query->bind_param("s", $desc_like_dp);
                    $dp_query->execute();
                    $dp_paid_amount = $dp_query->get_result()->fetch_assoc()['total_paid'] ?? 0;

                    $dp_remaining = $dp_amount - $dp_paid_amount;
                    if($dp_remaining < 0) $dp_remaining = 0;
                    // -----------------------------------------------------
                ?>
                <div class="res-card animate-on-scroll">
                    <div class="res-card-header">
                        <div>
                            <h3 class="res-title">Block <?= htmlspecialchars($row['block_no']) ?>, Lot <?= htmlspecialchars($row['lot_no']) ?></h3>
                            <p class="res-subtitle"><i class="fa-solid fa-map-pin" style="color: var(--primary);"></i> <?= htmlspecialchars($row['location']) ?></p>
                        </div>
                        <span class="res-badge badge-<?= htmlspecialchars($row['status']) ?>"><?= htmlspecialchars($row['status']) ?></span>
                    </div>
                    
                    <div class="res-card-body">
                        <ul class="timeline">
                            <li class="timeline-item active">
                                <span class="tl-title">Reservation Placed</span>
                                <span class="tl-desc">Date: <?= date('M d, Y', strtotime($row['reservation_date'])) ?></span>
                            </li>
                            
                            <li class="timeline-item <?= ($row['status'] == 'APPROVED') ? 'active' : '' ?>">
                                <span class="tl-title">Management Approval</span>
                                <span class="tl-desc">
                                    <?= ($row['status'] == 'PENDING') ? 'Documents are currently under review.' : '' ?>
                                    <?= ($row['status'] == 'APPROVED') ? 'Your reservation has been securely approved.' : '' ?>
                                    <?= ($row['status'] == 'REJECTED') ? 'This reservation was declined.' : '' ?>
                                </span>
                            </li>
                        </ul>

                        <div class="price-box">
                            <div class="price-row"><span>Property Value (TCP)</span> <span>₱<?= number_format($total) ?></span></div>
                            <div class="price-row"><span>Target Down Payment</span> <span>₱<?= number_format($dp_amount) ?></span></div>
                            <div class="price-row" style="color: var(--primary);"><span>Total DP Paid</span> <span>₱<?= number_format($dp_paid_amount) ?></span></div>
                            
                            <div class="price-row" style="font-weight: 800; color: <?= ($dp_remaining > 0) ? '#b91c1c' : 'var(--primary)' ?>; border-top: 1px solid #f1f5f9; padding-top: 12px; margin-top: 12px;">
                                <span>Remaining DP Balance</span> 
                                <span>₱<?= number_format($dp_remaining) ?></span>
                            </div>
                        </div>
                        </div>
                    
                    <div class="res-card-footer">
                        <?php if($row['status'] == 'PENDING' || $row['status'] == 'APPROVED'): ?>
                            
                            <?php if($dp_remaining > 0 && $dp_status != 'VERIFYING'): ?>
                                <button class="btn-pay" onclick="openPaymentModal(<?= $row['id'] ?>, <?= $dp_remaining ?>)">
                                    <i class="fa-solid fa-qrcode"></i> Pay Remaining DP Online
                                </button>
                            <?php elseif($dp_status == 'VERIFYING'): ?>
                                <div class="status-msg status-verifying">
                                    <i class="fa-solid fa-arrows-rotate fa-spin"></i> Verifying Payment...
                                </div>
                            <?php elseif($dp_remaining <= 0 || $dp_status == 'PAID'): ?>
                                <div class="status-msg" style="background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; text-align: left;">
                                    <i class="fa-solid fa-shield-check" style="font-size: 2rem;"></i> 
                                    <div>
                                        <div style="font-size: 1.1rem; font-weight: 800; margin-bottom: 2px;">Congratulations!</div>
                                        <div style="font-size: 0.85rem; font-weight: 600;">Your downpayment is fully paid.</div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                        <?php elseif($row['status'] == 'REJECTED'): ?>
                            <div class="status-msg" style="background:#fee2e2; color:#b91c1c; border:1px solid #fecaca;">
                                <i class="fa-solid fa-circle-xmark"></i> Reservation Canceled
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 80px 20px; background: white; border-radius: 24px; border: 1px solid #e2e8f0; box-shadow: var(--shadow-sm);" class="animate-on-scroll">
                    <i class="fa-regular fa-folder-open" style="font-size: 60px; color: #cbd5e1; margin-bottom: 25px;"></i>
                    <h3 style="color: var(--text-dark); font-weight: 800; font-size: 1.8rem; margin-bottom: 10px; letter-spacing: -0.5px;">No Reservations Yet</h3>
                    <p style="color: var(--text-gray); font-size: 1.1rem; margin-bottom: 30px;">Ready to find your dream sustainable lot?</p>
                    <a href="index.php" class="btn-pay" style="width: auto; display: inline-flex; padding: 16px 40px; border-radius: 40px;">Explore Properties <i class="fa-solid fa-arrow-right"></i></a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal-overlay" id="paymentModal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('paymentModal')"><i class="fa-solid fa-xmark"></i></button>
            
            <h2 style="font-weight: 800; font-size: 1.8rem; color: var(--text-dark); margin: 0 0 10px 0; letter-spacing: -0.5px;">Online Payment</h2>
            <p style="color: var(--text-gray); font-size: 1rem; margin-bottom: 25px; line-height: 1.6;">Scan the QR code using GCash, Maya, or your preferred banking app to transfer the down payment.</p>

            <div class="qr-box">
                <i class="fa-solid fa-qrcode"></i>
                <h4>JEJ Surveying Official</h4>
                <p>GCash / Maya / Instapay</p>
                <div style="font-size: 1.8rem; font-weight: 900; color: var(--primary); margin-top: 15px; letter-spacing: -1px;" id="modalAmountDisplay">₱0.00</div>
            </div>

            <form action="my_reservations.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="res_id" id="modalResId" value="">
                
                <label style="display: block; font-weight: 700; color: var(--text-dark); margin-bottom: 12px; font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.5px;">Upload Transfer Receipt</label>
                
                <div class="file-upload-wrapper">
                    <input type="file" name="dp_receipt" id="dp_receipt" class="file-upload-input" accept="image/*" required onchange="updateFileName(this)">
                    <div class="file-upload-display" id="fileDisplay">
                        <i class="fa-solid fa-cloud-arrow-up" style="font-size: 1.2rem;"></i> Choose receipt image...
                    </div>
                </div>

                <button type="submit" name="upload_qr_payment" class="btn-pay" style="margin-top: 15px; border-radius: 12px;">
                    Submit for Verification <i class="fa-solid fa-check-circle"></i>
                </button>
            </form>
        </div>
    </div>

    <footer class="footer">
        <p>&copy; <?= date('Y') ?> JEJ Surveying Services. All Rights Reserved. Built with trust and excellence.</p>
    </footer>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
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

        function openPaymentModal(resId, dpAmount) {
            document.getElementById('modalResId').value = resId;
            document.getElementById('modalAmountDisplay').innerText = '₱' + new Intl.NumberFormat('en-PH').format(dpAmount);
            document.getElementById('paymentModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
            document.body.style.overflow = 'auto';
            document.getElementById('dp_receipt').value = '';
            document.getElementById('fileDisplay').innerHTML = '<i class="fa-solid fa-cloud-arrow-up" style="font-size: 1.2rem;"></i> Choose receipt image...';
            document.getElementById('fileDisplay').style.borderColor = '#e2e8f0';
        }

        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                closeModal(event.target.id);
            }
        });

        function updateFileName(input) {
            const display = document.getElementById('fileDisplay');
            if (input.files && input.files[0]) {
                display.innerHTML = '<i class="fa-solid fa-image" style="color: var(--primary); font-size: 1.2rem;"></i> <span style="color: var(--text-dark);">' + input.files[0].name + '</span>';
                display.style.borderColor = 'var(--primary)';
                display.style.background = 'white';
            } else {
                display.innerHTML = '<i class="fa-solid fa-cloud-arrow-up" style="font-size: 1.2rem;"></i> Choose receipt image...';
                display.style.borderColor = '#e2e8f0';
                display.style.background = '#f8fafc';
            }
        }
    </script>
</body>
</html>
<?php ob_end_flush(); ?>
