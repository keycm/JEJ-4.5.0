<?php
// lot_details.php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'config.php';

// Ensure user is logged in
if(!isset($_SESSION['user_id'])){ header("Location: index.php"); exit(); }
if(!isset($_GET['id'])){ header("Location: index.php"); exit(); }

$id = (int)$_GET['id'];

// Fetch Lot Details
$stmt = $conn->prepare("SELECT * FROM lots WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$lot = $stmt->get_result()->fetch_assoc();

if(!$lot) die("Property not found.");

// Fetch Gallery Images
$gallery_stmt = $conn->prepare("SELECT * FROM lot_gallery WHERE lot_id = ?");
$gallery_stmt->bind_param("i", $id);
$gallery_stmt->execute();
$gallery_res = $gallery_stmt->get_result();

// Build array of all images for the JS Gallery
$js_images = [];
// Add Main Image first
$main_img = $lot['lot_image'] ? 'uploads/'.$lot['lot_image'] : 'assets/default_lot.jpg';
$js_images[] = $main_img;

// Add Gallery Images
$gallery_html = ""; 
while($img = $gallery_res->fetch_assoc()){
    $path = 'uploads/'.$img['image_path'];
    $js_images[] = $path;
    $gallery_html .= '<div class="thumb-box" onclick="openLightbox(\''.$path.'\')"><img src="'.$path.'" class="thumb-img"></div>';
}

// --- FETCH DATA FOR SCHEME MAP ---
// Determine which map image to show
$current_map = "assets/map.png"; 
if(file_exists("uploads/master_scheme_map.png")) $current_map = "uploads/master_scheme_map.png";
elseif(file_exists("uploads/master_scheme_map.jpg")) $current_map = "uploads/master_scheme_map.jpg";
elseif(file_exists("uploads/master_scheme_map.jpeg")) $current_map = "uploads/master_scheme_map.jpeg";

// Fetch all lots to render the subdivision context
$all_lots = [];
$res_lots = $conn->query("SELECT id, block_no, lot_no, status, coordinates FROM lots");
if($res_lots && $res_lots->num_rows > 0){
    while($r = $res_lots->fetch_assoc()){
        $all_lots[] = $r;
    }
}

// Fetch unread notifications for the navbar
$unread_count = 0;
$notif_stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
if ($notif_stmt) {
    $notif_stmt->bind_param("i", $_SESSION['user_id']);
    $notif_stmt->execute();
    $notif_stmt->bind_result($unread_count);
    $notif_stmt->fetch();
    $notif_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Property Details | JEJ Surveying Services</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <style>
        :root {
            /* PREMIUM NATURE THEME */
            --primary: #1e4b36; 
            --primary-light: #2d6a4f;
            --accent: #d4af37; 
            --bg-color: #f8fafc;
            --text-dark: #0f172a;
            --text-gray: #64748b;
            --white: #ffffff;
            --gray-border: #e2e8f0;
            
            --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 10px 25px -5px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 20px 40px -10px rgba(0,0,0,0.12);
        }

        body { background-color: var(--bg-color); font-family: 'Plus Jakarta Sans', sans-serif; color: var(--text-dark); margin: 0; padding: 0; overflow-x: hidden;}

        /* --- ANIMATIONS --- */
        @keyframes fadeInUp { 0% { opacity: 0; transform: translateY(40px); } 100% { opacity: 1; transform: translateY(0); } }
        @keyframes fadeIn { 0% { opacity: 0; } 100% { opacity: 1; } }
        @keyframes dropIn { from { opacity: 0; transform: scale(0.9) translateY(-10px); } to { opacity: 1; transform: scale(1) translateY(0); } }

        .animate-on-scroll { opacity: 0; transform: translateY(30px); transition: opacity 0.8s cubic-bezier(0.16, 1, 0.3, 1), transform 0.8s cubic-bezier(0.16, 1, 0.3, 1); }
        .animate-on-scroll.visible { opacity: 1; transform: translateY(0); }

        /* --- NAVIGATION --- */
        .nav { position: fixed; top: 0; left: 0; right: 0; display: flex; justify-content: space-between; align-items: center; padding: 15px 5%; background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border-bottom: 1px solid rgba(255,255,255,0.3); box-shadow: var(--shadow-sm); z-index: 1000; transition: all 0.3s ease; }
        .brand-wrapper { display: flex; align-items: center; gap: 12px; text-decoration: none; color: var(--primary); transition: transform 0.3s ease; }
        .brand-wrapper:hover { transform: scale(1.02); }
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
        .profile-name { display: block; font-weight: 700; font-size: 0.9rem; color: var(--text-dark); font-family: inherit;}
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

        /* General Page Layout */
        .main-content { padding: 100px 5% 50px 5%; max-width: 1400px; margin: 0 auto; } /* Added top padding for fixed nav */
        .breadcrumb { margin: 10px 0 25px; font-size: 0.9rem; color: var(--text-gray); display: flex; align-items: center; gap: 10px; font-weight: 600;}
        .breadcrumb a { color: var(--primary); text-decoration: none; transition: 0.2s;}
        .breadcrumb a:hover { color: var(--primary-light); }

        /* --- MEDIA & ACTION GRID (Top Section) --- */
        .media-action-grid { display: grid; grid-template-columns: 1.2fr 1fr; gap: 40px; align-items: start; margin-bottom: 40px;}

        /* Image Gallery */
        .main-img-box { position: relative; border-radius: 20px; overflow: hidden; height: 450px; box-shadow: var(--shadow-md); background: #f8fafc; cursor: pointer; border: 1px solid var(--gray-border);}
        .main-img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s cubic-bezier(0.16, 1, 0.3, 1); }
        .main-img-box:hover .main-img { transform: scale(1.05); }
        
        .badge { position: absolute; padding: 8px 18px; border-radius: 10px; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; box-shadow: 0 4px 15px rgba(0,0,0,0.2); letter-spacing: 0.5px;}
        .badge.AVAILABLE { background: rgba(16, 185, 129, 0.95); color: white; backdrop-filter: blur(4px); }
        .badge.RESERVED { background: rgba(245, 158, 11, 0.95); color: white; backdrop-filter: blur(4px); }
        .badge.SOLD { background: rgba(239, 68, 68, 0.95); color: white; backdrop-filter: blur(4px); }

        .gallery-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; margin-top: 15px; margin-bottom: 25px; }
        .thumb-box { height: 80px; border-radius: 12px; overflow: hidden; cursor: pointer; border: 2px solid transparent; opacity: 0.7; transition: 0.3s; box-shadow: var(--shadow-sm);}
        .thumb-box:hover { border-color: var(--primary); opacity: 1; transform: translateY(-4px); box-shadow: var(--shadow-md);}
        .thumb-img { width: 100%; height: 100%; object-fit: cover; }

        /* --- PROPERTY DETAILS CARD --- */
        .specs-card { background: white; border-radius: 20px; padding: 35px; box-shadow: var(--shadow-md); border: 1px solid var(--gray-border); }
        .specs-card .prop-type { font-size: 0.75rem; font-weight: 800; color: var(--primary); text-transform: uppercase; letter-spacing: 1px; background: #ecfdf5; padding: 6px 12px; border-radius: 8px; display: inline-block; margin-bottom: 15px; border: 1px solid #d1fae5;}
        .specs-card h2 { font-size: 2.2rem; font-weight: 800; color: var(--text-dark); margin: 0 0 10px; letter-spacing: -0.5px; line-height: 1.2;}
        .specs-card .location { color: var(--text-gray); font-size: 1rem; font-weight: 600; margin-bottom: 25px; display: flex; align-items: center; gap: 8px; }
        
        .price-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; background: #f8fafc; padding: 25px; border-radius: 16px; border: 1px solid #e2e8f0; margin-bottom: 30px; }
        .price-grid div small { display: block; font-size: 0.8rem; color: var(--text-gray); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px;}
        .price-grid div strong { font-size: 1.2rem; color: var(--text-dark); font-weight: 800; }
        .price-grid .total-row { grid-column: 1 / -1; border-top: 1px dashed #cbd5e1; padding-top: 15px; margin-top: 5px; }
        .price-grid .total-row strong { font-size: 1.8rem; color: var(--primary); font-weight: 900; letter-spacing: -0.5px;}

        .specs-card h4 { font-size: 1.2rem; font-weight: 800; color: var(--text-dark); margin: 0 0 10px; }
        .specs-card p { font-size: 1rem; color: var(--text-gray); line-height: 1.7; margin: 0; font-weight: 500;}

        /* Reservation Form */
        .form-card { background: white; border-radius: 20px; padding: 0; box-shadow: var(--shadow-md); border: 1px solid var(--gray-border); display: flex; flex-direction: column; overflow: hidden; height: fit-content; }
        .form-header { padding: 30px 35px 20px; background: white; border-bottom: 1px solid #f1f5f9;}
        .form-body { padding: 25px 35px 35px; }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-dark); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;}
        .form-control { width: 100%; padding: 14px 18px; border-radius: 12px; border: 2px solid #e2e8f0; background: #f8fafc; font-size: 0.95rem; font-family: inherit; font-weight: 500; transition: 0.3s; box-sizing: border-box; outline: none; color: var(--text-dark);}
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(30, 75, 54, 0.1); background: white;}
        .form-control::placeholder { color: #94a3b8; }
        
        .btn-submit { width: 100%; background: var(--primary); color: white; border: none; padding: 18px; border-radius: 12px; font-weight: 800; font-size: 1.1rem; cursor: pointer; margin-top: 10px; transition: 0.3s; box-shadow: 0 4px 15px rgba(30, 75, 54, 0.2); letter-spacing: 0.5px;}
        .btn-submit:hover { background: var(--primary-light); transform: translateY(-2px); box-shadow: 0 8px 25px rgba(30, 75, 54, 0.3);}

        /* Alerts inside form */
        .alert-warning { background: #fffbeb; border: 1px solid #fef08a; padding: 18px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 12px; }

        /* --- MAPS GRID (Scheme + Geo Map Side by Side on Bottom) --- */
        .maps-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 40px; margin-top: 30px; }
        
        .map-wrapper { display: flex; flex-direction: column; width: 100%; border-radius: 20px; border: 1px solid var(--gray-border); background: #ffffff; overflow: hidden; box-shadow: var(--shadow-md); position: relative; transition: all 0.3s ease; height: 600px; }
        
        /* FULLSCREEN MODIFIER for Scheme Map */
        .map-wrapper.fullscreen { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: 9999; border-radius: 0; margin: 0; border: none; }

        .map-header { padding: 20px 25px; background: white; border-bottom: 1px solid var(--gray-border); font-size: 1.1rem; font-weight: 800; color: var(--text-dark); display: flex; align-items: center; justify-content: space-between; flex-shrink: 0;}
        
        /* Scheme Map specifics */
        .svg-container { flex: 1; width: 100%; position: relative; background: #ffffff; overflow: hidden; cursor: grab; }
        .svg-container:active { cursor: grabbing; }
        #schemeMap { width: 100%; height: 100%; transform-origin: center center; display: block; transition: transform 0.05s linear;}
        
        .map-controls-overlay { position: absolute; right: 20px; top: 50%; transform: translateY(-50%); display: flex; flex-direction: column; gap: 5px; z-index: 100; background: white; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: var(--shadow-md); }
        .zoom-btn { background: transparent; color: var(--text-gray); border: none; width: 45px; height: 45px; font-size: 1.1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s; border-bottom: 1px solid #f1f5f9; }
        .zoom-btn:last-child { border-bottom: none; }
        .zoom-btn:hover { background: #f8fafc; color: var(--primary); }

        /* Geo Map specifics */
        #map-display { flex: 1; width: 100%; z-index: 1; background: #e2e8f0; height: 100%;}

        /* --- HIGH VISIBILITY MAP LOT HIGHLIGHTING --- */
        .lot { transition: all 0.3s ease; }
        .lot.available { fill: rgba(34, 197, 94, 0.7); stroke: #15803d; stroke-width: 2; } 
        .lot.reserved { fill: rgba(234, 179, 8, 0.7); stroke: #a16207; stroke-width: 2; } 
        .lot.sold { fill: rgba(239, 68, 68, 0.85); stroke: #991b1b; stroke-width: 2; } 
        
        .lot-dimmed { opacity: 0.35; pointer-events: none; } 
        .lot-focused { 
            stroke: #00e5ff !important; 
            stroke-width: 7 !important; 
            fill: rgba(0, 229, 255, 0.5) !important; 
            animation: pulseLot 1.5s infinite; 
            z-index: 100; 
            opacity: 1 !important; 
        }
        @keyframes pulseLot {
            0% { filter: drop-shadow(0 0 5px #00e5ff) brightness(1); }
            50% { filter: drop-shadow(0 0 25px #00e5ff) brightness(1.4); }
            100% { filter: drop-shadow(0 0 5px #00e5ff) brightness(1); }
        }

        /* Lightbox */
        .lightbox { display: none; position: fixed; z-index: 2000; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.95); backdrop-filter: blur(8px); justify-content: center; align-items: center; flex-direction: column; }
        .lightbox img { max-width: 90%; max-height: 85vh; border-radius: 12px; box-shadow: 0 15px 50px rgba(0,0,0,0.5); user-select: none; }
        .lb-controls { position: absolute; top: 50%; width: 100%; display: flex; justify-content: space-between; padding: 0 40px; transform: translateY(-50%); pointer-events: none; }
        .lb-btn { pointer-events: auto; background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); width: 55px; height: 55px; border-radius: 50%; font-size: 1.2rem; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.3s; backdrop-filter: blur(5px); }
        .lb-btn:hover { background: var(--primary); border-color: var(--primary); transform: scale(1.1); }
        .close-btn { position: absolute; top: 30px; right: 40px; color: white; font-size: 2rem; cursor: pointer; background: rgba(0,0,0,0.3); width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: 0.3s;}
        .close-btn:hover { background: #ef4444; transform: rotate(90deg);}

        .footer { background: var(--text-dark); color: white; text-align: center; padding: 40px 5%; margin-top: 60px; font-size: 0.9rem; opacity: 0.9; }

        @media (max-width: 1000px) { 
            .media-action-grid { grid-template-columns: 1fr; }
            .maps-grid { grid-template-columns: 1fr; } 
            .nav-links.desktop-only { display: none; }
            .brand-text-container { display: none; }
        }
    </style>
</head>
<body>

    <div id="lightbox" class="lightbox">
        <div class="close-btn" onclick="closeLightbox()">&times;</div>
        <div class="lb-controls">
            <button class="lb-btn" onclick="changeSlide(-1)"><i class="fa-solid fa-chevron-left"></i></button>
            <button class="lb-btn" onclick="changeSlide(1)"><i class="fa-solid fa-chevron-right"></i></button>
        </div>
        <div style="overflow: hidden; display: flex; justify-content: center; align-items: center; width: 100%; height: 85vh;">
            <img id="lightbox-img" src="" style="transition: transform 0.2s ease;">
        </div>
        <div style="display: flex; gap: 15px; margin-top: 20px; align-items: center;">
            <button class="lb-btn" onclick="zoomImage(-0.2)" style="width: 45px; height: 45px; font-size: 1rem;" title="Zoom Out"><i class="fa-solid fa-magnifying-glass-minus"></i></button>
            <div style="color: white; font-weight: 700; font-size: 1rem; background: rgba(0,0,0,0.6); padding: 8px 20px; border-radius: 30px;">
                <span id="lb-counter">1</span> / <?= count($js_images) ?>
            </div>
            <button class="lb-btn" onclick="zoomImage(0.2)" style="width: 45px; height: 45px; font-size: 1rem;" title="Zoom In"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
        </div>
    </div>

    <nav class="nav">
        <div class="brand-wrapper">
            <a href="index.php" style="display: flex; align-items: center; gap: 12px; text-decoration: none;">
                <img src="assets/logo.png" alt="JEJ Surveying Logo" class="brand-logo-img">
                <div class="brand-text-container">
                    <span class="nav-brand">JEJ Surveying</span>
                    <span class="nav-brand-sub">Services & Real Estate</span>
                </div>
            </a>
        </div>
        
        <div class="nav-links desktop-only">
            <a href="index.php" class="active">Properties</a>
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

    <main class="main-content">

        <div class="breadcrumb animate-on-scroll">
            <a href="index.php"><i class="fa-solid fa-house" style="margin-right: 4px;"></i> Home</a>
            <i class="fa-solid fa-chevron-right" style="font-size: 10px;"></i>
            <span><?= htmlspecialchars($lot['location']) ?></span>
            <i class="fa-solid fa-chevron-right" style="font-size: 10px;"></i>
            <strong style="color: var(--text-dark);">Block <?= htmlspecialchars($lot['block_no']) ?> Lot <?= htmlspecialchars($lot['lot_no']) ?></strong>
        </div>

        <div class="media-action-grid animate-on-scroll">
            
            <div class="gallery-section">
                <div class="main-img-box" onclick="openLightbox('<?= $main_img ?>')">
                    <img src="<?= $main_img ?>" class="main-img">
                    <span class="badge <?= $lot['status'] ?>" style="top:20px; left:20px; right:auto;"><?= $lot['status'] ?></span>
                    <div style="position: absolute; bottom: 20px; right: 20px; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(4px); color: white; padding: 10px 18px; border-radius: 10px; font-size: 0.85rem; font-weight: 700; pointer-events: none; border: 1px solid rgba(255,255,255,0.2);">
                        <i class="fa-solid fa-expand" style="margin-right: 5px;"></i> View Full Screen
                    </div>
                </div>

                <div class="gallery-grid">
                    <div class="thumb-box" onclick="openLightbox('<?= $main_img ?>')">
                        <img src="<?= $main_img ?>" class="thumb-img">
                    </div>
                    <?= $gallery_html ?>
                </div>

                <div class="specs-card">
                    <span class="prop-type"><?= $lot['property_type'] ?: 'Residential Lot' ?></span>
                    
                    <h2>Block <?= htmlspecialchars($lot['block_no']) ?>, Lot <?= htmlspecialchars($lot['lot_no']) ?></h2>
                    
                    <div class="location">
                        <i class="fa-solid fa-location-dot" style="color: #ef4444;"></i> <?= htmlspecialchars($lot['location']) ?>
                    </div>

                    <div class="price-grid">
                        <div>
                            <small>Lot Area</small>
                            <strong><?= number_format($lot['area']) ?> m²</strong>
                        </div>
                        <div>
                            <small>Price / SQM</small>
                            <strong>₱<?= number_format($lot['price_per_sqm']) ?></strong>
                        </div>
                        <div class="total-row">
                            <small>Total Contract Price</small>
                            <strong>₱<?= number_format($lot['total_price']) ?></strong>
                        </div>
                    </div>

                    <h4>Property Overview</h4>
                    <p><?= nl2br(htmlspecialchars($lot['property_overview'] ?? 'No additional description available for this property at the moment.')) ?></p>
                </div>
            </div>

            <div class="form-section">
                <div class="form-card">
                    <?php if($lot['status'] == 'AVAILABLE'): ?>
                        <div class="form-header">
                            <h3 style="font-size: 1.5rem; font-weight: 800; margin: 0 0 5px; color: var(--text-dark);">Reserve Property</h3>
                            <p style="color: var(--text-gray); font-size: 0.95rem; margin: 0; font-weight: 500;">Fill out the details below to secure this lot.</p>
                        </div>

                        <div class="form-body">
                            <form action="actions.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="reserve">
                                <input type="hidden" name="lot_id" value="<?= $lot['id'] ?>">
                                
                                <div class="form-group">
                                    <label>Full Name <span style="color:#ef4444">*</span></label>
                                    <input type="text" name="fullname" class="form-control" placeholder="E.g., Juan Dela Cruz" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Email Address <span style="color:#ef4444">*</span></label>
                                    <input type="email" name="email" class="form-control" required placeholder="E.g., juan@example.com">
                                </div>

                                <div style="display:flex; gap:15px;">
                                    <div class="form-group" style="flex:1;">
                                        <label>Mobile No. <span style="color:#ef4444">*</span></label>
                                        <input type="text" name="contact_number" class="form-control" placeholder="09XX XXX XXXX" required>
                                    </div>
                                    <div class="form-group" style="flex:1;">
                                        <label>Agent Name <span style="color:#ef4444">*</span></label>
                                        <input type="text" name="agent_name" class="form-control" placeholder="Who assisted you?" required>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Complete Home Address <span style="color:#ef4444">*</span></label>
                                    <input type="text" name="address" class="form-control" placeholder="House No, Street, Brgy, City" required>
                                </div>
                                
                                <div style="border-top: 1px dashed #cbd5e1; margin: 30px 0;"></div>
                                
                                <div style="margin-bottom: 20px;">
                                    <strong style="font-size: 1rem; color: var(--text-dark); font-weight: 800;">Required Documents</strong>
                                    <p style="font-size: 0.85rem; color: var(--text-gray); margin: 4px 0 0; font-weight: 500;">Please upload clear photos (JPG, PNG).</p>
                                </div>

                                <div class="form-group">
                                    <label>1. Valid Government ID</label>
                                    <input type="file" name="valid_id" class="form-control" style="padding: 10px 15px; background: white;" accept="image/*" required>
                                </div>
                                <div class="form-group">
                                    <label>2. Selfie holding the ID</label>
                                    <input type="file" name="selfie_id" class="form-control" style="padding: 10px 15px; background: white;" accept="image/*" required>
                                </div>
                                <div class="form-group">
                                    <label>3. Proof of Reservation Payment</label>
                                    <input type="file" name="proof" class="form-control" style="padding: 10px 15px; background: white;" accept="image/*" required>
                                    <small style="display:block; margin-top:8px; color: var(--primary); font-size:0.75rem; font-weight:700;"><i class="fa-solid fa-circle-info"></i> Minimum required downpayment is ₱5,000.</small>
                                </div>

                                <div class="alert-warning">
                                    <i class="fa-solid fa-circle-exclamation" style="color: #d97706; font-size: 1.2rem; margin-top: 2px;"></i>
                                    <div>
                                        <strong style="color: #92400e; font-size: 0.95rem; display: block; margin-bottom: 2px;">Payment Required</strong>
                                        <span style="color: #b45309; font-size: 0.85rem; font-weight: 600; line-height: 1.4; display: block;">A minimum downpayment of <b>₱5,000</b> is strictly required to process and secure this reservation.</span>
                                    </div>
                                </div>

                                <button type="submit" class="btn-submit"><i class="fa-solid fa-paper-plane" style="margin-right: 8px;"></i> Submit Reservation Request</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div style="text-align:center; padding: 80px 40px; background: #fef2f2; height: 100%; display: flex; flex-direction: column; justify-content: center; align-items: center; min-height: 500px;">
                            <div style="width: 80px; height: 80px; background: #fee2e2; color: #dc2626; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; margin-bottom: 25px; box-shadow: 0 4px 10px rgba(220, 38, 38, 0.2);">
                                <i class="fa-solid fa-lock"></i>
                            </div>
                            <h3 style="margin: 0 0 12px; color: #991b1b; font-size: 1.8rem; font-weight: 800; letter-spacing: -0.5px;">Property Unavailable</h3>
                            <p style="color: #b91c1c; font-size: 1rem; line-height: 1.6; margin: 0; font-weight: 500;">This lot has already been marked as <strong><?= htmlspecialchars($lot['status']) ?></strong> and cannot be reserved online at this time.</p>
                            <a href="index.php" style="margin-top: 30px; padding: 16px 30px; background: white; color: #dc2626; border: 2px solid #fecdd3; border-radius: 12px; text-decoration: none; font-weight: 800; font-size: 1rem; transition: 0.3s; box-shadow: 0 4px 6px rgba(220, 38, 38, 0.1);">Browse Other Lots <i class="fa-solid fa-arrow-right" style="margin-left: 5px;"></i></a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="maps-grid animate-on-scroll">
            
            <div class="map-wrapper" id="schemeWrapper">
                <div class="map-header">
                    <span><i class="fa-solid fa-map" style="color: var(--primary); margin-right: 8px;"></i> Subdivision Plan</span>
                    <div style="display: flex; gap: 10px;">
                        <span style="width:16px; height:16px; border-radius:4px; background:rgba(34, 197, 94, 0.8); border: 2px solid #15803d;" title="Available"></span>
                        <span style="width:16px; height:16px; border-radius:4px; background:rgba(234, 179, 8, 0.8); border: 2px solid #a16207;" title="Reserved"></span>
                        <span style="width:16px; height:16px; border-radius:4px; background:rgba(239, 68, 68, 0.9); border: 2px solid #991b1b;" title="Sold"></span>
                    </div>
                </div>
                
                <div class="svg-container" id="svgContainer">
                    <div class="map-controls-overlay">
                        <button class="zoom-btn" onclick="toggleMapFullscreen()" title="Toggle Fullscreen"><i class="fa-solid fa-expand" id="fsIcon"></i></button>
                        <button class="zoom-btn" onclick="zoomMap(0.2)" title="Zoom In"><i class="fa-solid fa-plus"></i></button>
                        <button class="zoom-btn" onclick="zoomMap(-0.2)" title="Zoom Out"><i class="fa-solid fa-minus"></i></button>
                        <button class="zoom-btn" onclick="resetMap()" title="Reset View"><i class="fa-solid fa-rotate-left"></i></button>
                    </div>
                    
                    <svg id="schemeMap" viewBox="0 0 1464 1052" preserveAspectRatio="xMidYMid meet">
                        <image href="<?= $current_map ?>?v=<?= time() ?>" x="0" y="0" width="1464" height="1052"></image>
                        <?php foreach ($all_lots as $l): 
                            $points = htmlspecialchars($l['coordinates'] ?? '');
                            if(empty($points)) continue;
                            
                            $isCurrent = ($l['id'] == $lot['id']);
                            $statusClass = strtolower($l['status']);
                            $polyClass = "lot " . $statusClass . ($isCurrent ? " lot-focused" : " lot-dimmed");
                        ?>
                        <polygon class="<?= $polyClass ?>" points="<?= $points ?>">
                            <title>Block <?= htmlspecialchars($l['block_no']) ?> - Lot <?= htmlspecialchars($l['lot_no']) ?></title>
                        </polygon>
                        <?php endforeach; ?>
                    </svg>
                </div>
                
                <div style="padding: 15px 25px; background: white; font-size: 0.85rem; color: var(--text-gray); border-top: 1px solid var(--gray-border); flex-shrink: 0; text-align: center; display: flex; align-items: center; justify-content: center; gap: 15px;">
                    <span style="color: #00e5ff; text-shadow: 0 0 4px rgba(0,229,255,0.6); font-weight: 800;"><i class="fa-solid fa-location-crosshairs"></i> Current Lot Highlighted in Cyan</span>
                    <span style="color: #cbd5e1;">|</span>
                    <span style="font-weight: 700;">Map Status Key: <strong style="color:#dc2626;">Sold (Red)</strong>, <strong style="color:#d97706;">Reserved (Yellow)</strong>, <strong style="color:#059669;">Available (Green)</strong></span>
                </div>
            </div>

            <?php if(!empty($lot['latitude'])): ?>
            <div class="map-wrapper geo-wrapper">
                <div class="map-header">
                    <span><i class="fa-solid fa-earth-asia" style="color: var(--primary); margin-right: 8px;"></i> Geographic Location</span>
                </div>
                <div id="map-display"></div>
            </div>
            <?php else: ?>
            <div class="map-wrapper geo-wrapper" style="justify-content: center; align-items: center; background: #f8fafc; border: 2px dashed #cbd5e1;">
                <i class="fa-solid fa-location-dot" style="font-size: 3rem; color: #cbd5e1; margin-bottom: 15px;"></i>
                <span style="color: var(--text-gray); font-size: 0.95rem; font-weight: 600;">No exact geographic pin available.</span>
            </div>
            <?php endif; ?>

        </div>
    </main>

    <footer class="footer">
        <div style="margin-bottom: 25px;">
            <img src="image_e94543.png" alt="JEJ Logo" style="height: 60px; width: auto;">
        </div>
        <p style="margin: 0; font-size: 1.1rem; font-weight: 800; color: white;">JEJ Surveying Services</p>
        <p style="color: #94a3b8; font-size: 0.95rem; margin: 8px 0 0;">Professional surveying and subdivision blueprint solutions.</p>
        <div style="margin-top: 30px; font-size: 0.85rem; color: #64748b; font-weight: 500;">
            &copy; <?= date('Y') ?> All Rights Reserved.
        </div>
    </footer>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // --- LEAFLET MAP LOGIC ---
        <?php if(!empty($lot['latitude'])): ?>
        var map = L.map('map-display').setView([<?= $lot['latitude'] ?>, <?= $lot['longitude'] ?>], 16);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);
        
        var markerIcon = L.divIcon({
            className: 'custom-div-icon',
            html: "<div style='background-color:#ef4444; width:18px; height:18px; border-radius:50%; border:3px solid white; box-shadow:0 0 15px rgba(0,0,0,0.5);'></div>",
            iconSize: [24, 24],
            iconAnchor: [12, 12]
        });
        L.marker([<?= $lot['latitude'] ?>, <?= $lot['longitude'] ?>], {icon: markerIcon}).addTo(map)
         .bindPopup("<b>Block <?= $lot['block_no'] ?> Lot <?= $lot['lot_no'] ?></b><br>JEJ Surveying");
        
        setTimeout(() => { map.invalidateSize(); }, 500);
        <?php endif; ?>

        // --- SCHEME MAP LOGIC (ZOOM & PAN & FULLSCREEN) ---
        let mapScale = 1;
        let mapPanX = 0;
        let mapPanY = 0;
        let isPanning = false;
        let startDrag = { x: 0, y: 0 };

        const svgContainer = document.getElementById('svgContainer');
        const schemeMap = document.getElementById('schemeMap');
        const schemeWrapper = document.getElementById('schemeWrapper');
        const fsIcon = document.getElementById('fsIcon');

        window.addEventListener('load', function() {
            const focusedLot = document.querySelector('.lot-focused');
            if (svgContainer && focusedLot) {
                const bbox = focusedLot.getBBox();
                const scaleX = svgContainer.clientWidth / 1464; 
                const scrollTargetX = (bbox.x * scaleX) - (svgContainer.clientWidth / 2);
                if(scrollTargetX > 0) { svgContainer.scrollLeft = scrollTargetX; }
            }
        });

        function setMapTransform() {
            schemeMap.style.transform = `translate(${mapPanX}px, ${mapPanY}px) scale(${mapScale})`;
        }

        function zoomMap(delta) {
            mapScale += delta;
            if(mapScale < 0.5) mapScale = 0.5;
            if(mapScale > 5) mapScale = 5;
            setMapTransform();
        }

        function resetMap() {
            mapScale = 1; mapPanX = 0; mapPanY = 0;
            setMapTransform();
        }

        function toggleMapFullscreen() {
            schemeWrapper.classList.toggle('fullscreen');
            if (schemeWrapper.classList.contains('fullscreen')) {
                fsIcon.classList.remove('fa-expand');
                fsIcon.classList.add('fa-compress');
                document.body.style.overflow = 'hidden';
            } else {
                fsIcon.classList.remove('fa-compress');
                fsIcon.classList.add('fa-expand');
                document.body.style.overflow = 'auto';
            }
            resetMap();
        }

        svgContainer.addEventListener('wheel', function(e) {
            e.preventDefault();
            const delta = e.deltaY > 0 ? -0.1 : 0.1;
            zoomMap(delta);
        });

        svgContainer.addEventListener('mousedown', function(e) {
            e.preventDefault();
            isPanning = true;
            startDrag = { x: e.clientX - mapPanX, y: e.clientY - mapPanY };
            svgContainer.style.cursor = 'grabbing';
        });

        window.addEventListener('mouseup', function() {
            isPanning = false;
            svgContainer.style.cursor = 'grab';
        });

        window.addEventListener('mousemove', function(e) {
            if (!isPanning) return;
            e.preventDefault();
            mapPanX = (e.clientX - startDrag.x);
            mapPanY = (e.clientY - startDrag.y);
            setMapTransform();
        });


        // --- LIGHTBOX LOGIC ---
        const allImages = <?php echo json_encode($js_images); ?>;
        let currentIdx = 0;
        let currentLbZoom = 1;

        function zoomImage(step) {
            currentLbZoom += step;
            if (currentLbZoom < 0.5) currentLbZoom = 0.5; 
            if (currentLbZoom > 4) currentLbZoom = 4;     
            document.getElementById('lightbox-img').style.transform = `scale(${currentLbZoom})`;
        }

        function resetLbZoom() {
            currentLbZoom = 1;
            document.getElementById('lightbox-img').style.transform = `scale(${currentLbZoom})`;
        }

        function openLightbox(src) {
            const index = allImages.indexOf(src);
            if(index !== -1) {
                currentIdx = index;
                resetLbZoom();
                updateLightboxImage();
                document.getElementById('lightbox').style.display = 'flex';
                document.body.style.overflow = 'hidden'; 
            }
        }

        function closeLightbox() {
            document.getElementById('lightbox').style.display = 'none';
            document.body.style.overflow = 'auto'; 
            resetLbZoom();
        }

        function changeSlide(step) {
            currentIdx += step;
            if (currentIdx >= allImages.length) currentIdx = 0;
            if (currentIdx < 0) currentIdx = allImages.length - 1;
            resetLbZoom();
            updateLightboxImage();
        }

        function updateLightboxImage() {
            document.getElementById('lightbox-img').src = allImages[currentIdx];
            document.getElementById('lb-counter').innerText = currentIdx + 1;
        }

        document.addEventListener('keydown', function(e) {
            if(document.getElementById('lightbox').style.display === 'flex') {
                if(e.key === 'ArrowLeft') changeSlide(-1);
                if(e.key === 'ArrowRight') changeSlide(1);
                if(e.key === 'Escape') closeLightbox();
            }
            if(e.key === 'Escape' && schemeWrapper.classList.contains('fullscreen')) {
                toggleMapFullscreen();
            }
        });

        // Dropdown Menu Logic
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