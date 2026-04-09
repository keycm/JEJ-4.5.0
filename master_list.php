<?php
// master_list.php
include 'config.php';

// Security Check
if(!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['SUPER ADMIN', 'ADMIN', 'MANAGER'])){
    header("Location: login.php");
    exit();
}

$alert_msg = "";
$alert_type = "";

// --- 1. FETCH DISTINCT LOCATIONS & MAP THEM ---
$locations = [];
$map_urls = [];
$locSql = "SELECT DISTINCT location FROM lots WHERE location IS NOT NULL AND location != '' ORDER BY location";
$locRes = $conn->query($locSql);

if ($locRes && $locRes->num_rows > 0) {
    while ($row = $locRes->fetch_assoc()) {
        $loc = trim($row['location']);
        $locations[] = $loc;
        
        // Create a safe, standardized filename based on the location name
        $safe_loc = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $loc));
        $map_urls[$loc] = "assets/map.png"; // Default fallback map
        
        // Check which map extension exists for this specific location
        $exts = ['png', 'jpg', 'jpeg'];
        foreach($exts as $ext) {
            if(file_exists("uploads/map_{$safe_loc}.{$ext}")) {
                $map_urls[$loc] = "uploads/map_{$safe_loc}.{$ext}";
                break;
            }
        }
    }
}

// --- 2. HANDLE LOCATION-SPECIFIC MAP UPLOAD ---
if(isset($_POST['upload_map']) && isset($_FILES['map_image']) && !empty($_POST['map_location']) && $_FILES['map_image']['error'] == 0){
    $loc = $_POST['map_location'];
    $safe_loc = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $loc));
    
    $target_dir = "uploads/";
    if(!is_dir($target_dir)) mkdir($target_dir);
    
    $ext = pathinfo($_FILES['map_image']['name'], PATHINFO_EXTENSION);
    $mapPath = $target_dir . "map_{$safe_loc}." . $ext;
    
    // Delete existing map variations for this specific location to prevent conflicts
    @unlink($target_dir . "map_{$safe_loc}.png");
    @unlink($target_dir . "map_{$safe_loc}.jpg");
    @unlink($target_dir . "map_{$safe_loc}.jpeg");

    if(move_uploaded_file($_FILES['map_image']['tmp_name'], $mapPath)){
        $alert_msg = "New Scheme Map for <strong>{$loc}</strong> uploaded successfully!";
        $alert_type = "success";
        // Refresh the map url array for the newly uploaded file
        $map_urls[$loc] = $mapPath;
    } else {
        $alert_msg = "Failed to upload the map image.";
        $alert_type = "error";
    }
}

// --- 3. FETCH ALL LOTS FOR THE TABLE ---
$lots = [];
$statusCounts = [
    'AVAILABLE' => 0,
    'SOLD' => 0,
    'RESERVED' => 0
];

$sql = "SELECT * FROM lots ORDER BY CAST(block_no AS UNSIGNED), CAST(lot_no AS UNSIGNED)";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $lots[] = $row;
        $status = strtoupper($row['status']);
        if (isset($statusCounts[$status])) {
            $statusCounts[$status]++;
        }
    }
}
$totalLots = count($lots);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master List & Map | JEJ Admin</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            /* NATURE GREEN THEME */
            --primary: #2e7d32; 
            --primary-light: #e8f5e9; 
            --dark: #1b5e20; 
            --gray-light: #f1f8e9; 
            --gray-border: #c8e6c9; 
            --text-muted: #607d8b; 
            
            --shadow-sm: 0 1px 2px 0 rgba(46, 125, 50, 0.08);
            --shadow-md: 0 4px 6px -1px rgba(46, 125, 50, 0.1), 0 2px 4px -1px rgba(46, 125, 50, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(46, 125, 50, 0.15), 0 4px 6px -2px rgba(46, 125, 50, 0.05);
        }

        body { background-color: #fafcf9; display: flex; min-height: 100vh; overflow-x: hidden; font-family: 'Inter', sans-serif; color: #37474f; margin: 0; }

        /* Sidebar Styling */
        .sidebar { width: 260px; background: #ffffff; border-right: 1px solid var(--gray-border); display: flex; flex-direction: column; position: fixed; height: 100vh; z-index: 100; box-shadow: var(--shadow-sm); }
        .brand-box { padding: 25px; border-bottom: 1px solid var(--gray-border); display: flex; align-items: center; gap: 12px; }
        .sidebar-menu { padding: 20px 15px; flex: 1; overflow-y: auto; }
        .menu-link { display: flex; align-items: center; gap: 12px; padding: 12px 18px; color: #455a64; text-decoration: none; font-weight: 500; font-size: 14px; border-radius: 10px; margin-bottom: 6px; transition: all 0.2s ease; }
        .menu-link:hover { background: var(--gray-light); color: var(--primary); }
        .menu-link.active { background: var(--primary-light); color: var(--primary); font-weight: 600; border-left: 4px solid var(--primary); }
        .menu-link i { width: 20px; text-align: center; font-size: 16px; opacity: 0.8; }
        
        /* Main Panel & Header */
        .main-panel { margin-left: 260px; flex: 1; padding: 0; width: calc(100% - 260px); display: flex; flex-direction: column; }
        
        .top-header { display: flex; justify-content: space-between; align-items: center; background: #ffffff; padding: 20px 40px; border-bottom: 1px solid var(--gray-border); box-shadow: var(--shadow-sm); z-index: 50; }
        .header-title h1 { font-size: 22px; font-weight: 800; color: var(--dark); margin: 0 0 4px 0; letter-spacing: -0.5px;}
        .header-title p { color: var(--text-muted); font-size: 13px; margin: 0; }

        /* Profile Dropdown */
        .profile-dropdown { position: relative; cursor: pointer; }
        .profile-trigger { display: flex; align-items: center; gap: 12px; padding: 6px 12px; border-radius: 10px; transition: background 0.2s; border: 1px solid transparent; }
        .profile-trigger:hover { background: var(--gray-light); border-color: var(--gray-border); }
        .profile-avatar { width: 40px; height: 40px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 16px; box-shadow: 0 2px 4px rgba(46, 125, 50, 0.2);}
        .profile-info strong { display: block; font-size: 13px; color: var(--dark); line-height: 1.2; }
        .profile-info small { font-size: 11px; color: var(--text-muted); font-weight: 500; }
        
        .dropdown-menu { display: none; position: absolute; right: 0; top: 110%; background: white; border-radius: 12px; box-shadow: var(--shadow-lg); border: 1px solid var(--gray-border); min-width: 200px; z-index: 1000; overflow: hidden; transform-origin: top right; animation: dropAnim 0.2s ease-out forwards; }
        @keyframes dropAnim { 0% { opacity: 0; transform: scale(0.95) translateY(-10px); } 100% { opacity: 1; transform: scale(1) translateY(0); } }
        .profile-dropdown:hover .dropdown-menu { display: block; }
        .dropdown-header { padding: 15px; border-bottom: 1px solid var(--gray-border); background: var(--gray-light); }
        .dropdown-item { padding: 12px 16px; display: flex; align-items: center; gap: 12px; color: #455a64; text-decoration: none; font-size: 13px; font-weight: 500; transition: background 0.2s; border-left: 3px solid transparent;}
        .dropdown-item:hover { background: var(--primary-light); color: var(--primary); border-left-color: var(--primary); }
        .dropdown-item.text-danger { color: #d84315; }
        .dropdown-item.text-danger:hover { background: #fbe9e7; color: #bf360c; border-left-color: #d84315; }

        .content-area { padding: 35px 40px; flex: 1; }

        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .stat-card { background: white; padding: 20px; border-radius: 16px; border: 1px solid var(--gray-border); box-shadow: var(--shadow-sm); display: flex; flex-direction: column; transition: transform 0.2s;}
        .stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
        .stat-card span { font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 5px; letter-spacing: 0.5px;}
        .stat-card strong { font-size: 28px; font-weight: 800; color: var(--dark); }
        
        .sc-total { border-top: 4px solid #3b82f6; } 
        .sc-avail { border-top: 4px solid #10b981; } 
        .sc-res   { border-top: 4px solid #f59e0b; } 
        .sc-sold  { border-top: 4px solid #ef4444; } 

        /* Directory / Table Styling */
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 16px 24px; font-size: 12px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; background: var(--gray-light); border-bottom: 1px solid var(--gray-border); letter-spacing: 0.5px;}
        td { padding: 16px 24px; border-bottom: 1px solid var(--gray-border); color: #37474f; font-size: 14px; vertical-align: middle; }
        tr:hover td { background: #fdfdfd; }
        
        /* Map UI Styling */
        .map-container { background: white; border-radius: 16px; border: 1px solid var(--gray-border); box-shadow: var(--shadow-sm); padding: 20px; margin-bottom: 30px; }
        .map-toolbar { display: flex; gap: 15px; margin-bottom: 15px; align-items: center; flex-wrap: wrap; justify-content: space-between; background: #f8fafc; padding: 15px; border-radius: 12px; border: 1px solid #e2e8f0; }
        .map-toolbar-left { display: flex; gap: 10px; align-items: center; flex: 1; flex-wrap: wrap; }
        .map-toolbar input[type="text"], .map-toolbar select, .map-toolbar button { padding: 10px 15px; border-radius: 8px; border: 1px solid #cbd5e1; font-family: inherit; font-size: 13px; outline: none; transition: 0.2s;}
        .map-toolbar input[type="text"]:focus, .map-toolbar select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.15); }
        .map-toolbar input[type="text"] { min-width: 220px; }
        .map-toolbar button.btn-reset { background: white; color: var(--text-muted); border: 1px solid var(--gray-border); font-weight: 600; cursor: pointer; }
        .map-toolbar button.btn-reset:hover { background: #f1f5f9; color: #1e293b; }
        
        /* Dynamic Upload Form Styling */
        .map-upload-form { display:flex; gap:10px; align-items:center; background: white; padding: 8px 15px; border-radius: 8px; border: 1px dashed #cbd5e1; }
        .upload-labels { display: flex; flex-direction: column; margin-right: 10px; }
        .upload-labels .lbl-title { font-size: 10px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .upload-labels .lbl-loc { font-size: 13px; font-weight: 700; color: var(--primary); }
        .map-upload-form input[type="file"] { font-size: 12px; max-width: 190px; padding: 0; border: none; }
        .map-upload-form button { background: #334155; color: white; border: none; padding: 8px 15px; font-size: 12px; border-radius: 6px; cursor: pointer; font-weight: 600; transition: 0.2s; }
        .map-upload-form button:hover:not(:disabled) { background: #0f172a; }
        .map-upload-form button:disabled { opacity: 0.5; cursor: not-allowed; }

        .legend { display: flex; gap: 15px; font-size: 12px; font-weight: 600; color: #455a64; margin-bottom: 15px;}
        .legend span { display: flex; align-items: center; gap: 5px; }
        .legend i { width: 14px; height: 14px; border-radius: 3px; border: 1px solid rgba(0,0,0,0.1); }

        .map-wrapper { width: 100%; overflow: auto; border-radius: 12px; border: 1px solid var(--gray-border); background: #f8fafc; position: relative; min-height: 400px; }
        #schemeMap { width: 100%; min-width: 1000px; display: block; }
        
        /* The Overlay when "All Branches" is selected */
        #mapOverlay { position: absolute; inset: 0; background: rgba(248, 250, 252, 0.95); backdrop-filter: blur(4px); display: flex; flex-direction: column; justify-content: center; align-items: center; z-index: 50; }
        #mapOverlay i { font-size: 48px; color: #cbd5e1; margin-bottom: 15px; }
        #mapOverlay h3 { margin: 0 0 5px 0; color: #334155; font-size: 20px; font-weight: 800; }
        #mapOverlay p { margin: 0; color: #64748b; font-size: 14px; font-weight: 500; }

        /* Interactive Polygon Styling */
        .lot { stroke: #ffffff; stroke-width: 1.5; cursor: pointer; transition: all 0.3s ease; }
        .lot:hover { stroke: #1e293b; stroke-width: 3; filter: brightness(1.1); }
        .lot.available { fill: rgba(16, 185, 129, 0.6); } 
        .lot.reserved { fill: rgba(245, 158, 11, 0.6); }   
        .lot.sold { fill: rgba(239, 68, 68, 0.85); stroke: #991b1b; } 
        .lot.hidden-by-filter { opacity: 0; pointer-events: none; }
        
        /* Pinpoint Locate Highlight Styling */
        .lot.lot-dimmed { opacity: 0.15 !important; pointer-events: none; }
        .lot.lot-focused { stroke: #3b82f6 !important; stroke-width: 6 !important; animation: pulseLot 1.5s infinite; z-index: 100; }
        @keyframes pulseLot { 0% { filter: drop-shadow(0 0 2px #3b82f6) brightness(1); } 50% { filter: drop-shadow(0 0 15px #3b82f6) brightness(1.5); } 100% { filter: drop-shadow(0 0 2px #3b82f6) brightness(1); } }
        
        /* Badges & Buttons */
        .status-badge { padding: 6px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; letter-spacing: 0.3px; display: inline-block;}
        .btn-action { padding: 8px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; cursor: pointer; border: none; transition: 0.2s;}
        .btn-locate { background: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd;} 
        .btn-locate:hover { background: #bae6fd; color: #0369a1; }
        .btn-edit { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1;} 
        .btn-edit:hover { background: #e2e8f0; color: #334155; }
        .btn-full-edit { background: #ffffff; color: #64748b; border: 1px solid #cbd5e1; }
        .btn-full-edit:hover { background: #f8fafc; color: #475569; border-color: #94a3b8; }
        
        /* Modal & Form Styling */
        .modal { display: none; position: fixed; z-index: 9999; inset: 0; background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(3px); padding: 30px; overflow-y: auto; }
        .modal-content { max-width: 550px; margin: 5vh auto; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        .modal-header { padding: 20px 25px; border-bottom: 1px solid var(--gray-border); display: flex; justify-content: space-between; align-items: center; background: var(--gray-light); }
        .modal-header h2 { margin: 0; font-size: 18px; font-weight: 700; color: var(--dark); }
        .close-btn { background: none; border: none; font-size: 20px; color: #90a4ae; cursor: pointer; transition: 0.2s;}
        .close-btn:hover { color: #ef4444; transform: scale(1.1);}
        #modalBody { padding: 25px; }
        
        .alert-box { padding: 16px 20px; border-radius: 10px; margin-bottom: 25px; font-weight: 500; font-size: 14px; box-shadow: var(--shadow-sm); }
        .alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .alert-error { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="brand-box">
            <img src="assets/logo.png" style="height: 38px; width: auto; border-radius: 8px;">
            <div style="line-height: 1.1;">
                <span style="font-size: 16px; font-weight: 800; color: var(--primary); display: block;">JEJ Surveying</span>
                <span style="font-size: 11px; color: var(--text-muted); font-weight: 500;">Management Portal</span>
            </div>
        </div>
        
        <div class="sidebar-menu">
            <small style="padding: 0 15px; color: #90a4ae; font-weight: 700; font-size: 11px; display: block; margin-bottom: 12px; letter-spacing: 0.5px;">MAIN MENU</small>
            <a href="admin.php?view=dashboard" class="menu-link"><i class="fa-solid fa-chart-pie"></i> Dashboard</a>
            <a href="reservation.php" class="menu-link"><i class="fa-solid fa-file-signature"></i> Reservations</a>
            <a href="master_list.php" class="menu-link active"><i class="fa-solid fa-map-location-dot"></i> Master List / Map</a>
            <a href="admin.php?view=inventory" class="menu-link"><i class="fa-solid fa-plus-circle"></i> Add Property</a>
            <a href="financial.php" class="menu-link"><i class="fa-solid fa-coins"></i> Financials</a>
            <a href="payment_tracking.php" class="menu-link"><i class="fa-solid fa-file-invoice-dollar"></i> Payment Tracking</a>
            
            <small style="padding: 0 15px; color: #90a4ae; font-weight: 700; font-size: 11px; display: block; margin-top: 25px; margin-bottom: 12px; letter-spacing: 0.5px;">MANAGEMENT</small>
            <a href="inquiries.php" class="menu-link"><i class="fa-solid fa-envelope-open-text"></i> Inquiries</a>
            <a href="accounts.php" class="menu-link"><i class="fa-solid fa-users-gear"></i> Accounts</a>
            <a href="delete_history.php" class="menu-link"><i class="fa-solid fa-trash-can"></i> Delete History</a>
            
            <small style="padding: 0 15px; color: #90a4ae; font-weight: 700; font-size: 11px; display: block; margin-top: 25px; margin-bottom: 12px; letter-spacing: 0.5px;">SYSTEM</small>
            <a href="index.php" class="menu-link" target="_blank"><i class="fa-solid fa-globe"></i> View Website</a>
        </div>
    </div>

    <div class="main-panel">
        
        <div class="top-header">
            <div class="header-title">
                <h1>Master List & Scheme Map</h1>
                <p>Interactive subdivision map and complete property inventory.</p>
            </div>
            
            <div class="profile-dropdown">
                <div class="profile-trigger">
                    <div class="profile-avatar">A</div>
                    <div class="profile-info">
                        <strong>Administrator</strong>
                        <small>System Admin <i class="fa-solid fa-chevron-down" style="font-size: 9px; margin-left: 3px;"></i></small>
                    </div>
                </div>
                
                <div class="dropdown-menu">
                    <div class="dropdown-header">
                        <strong style="display: block; font-size: 13px; color: var(--dark);">JEJ Admin System</strong>
                        <span style="font-size: 11px; color: var(--text-muted);">Logged in successfully</span>
                    </div>
                    <a href="audit_logs.php" class="dropdown-item"><i class="fa-solid fa-clock-rotate-left" style="width:16px;"></i> System Audit Logs</a>
                    <a href="settings.php" class="dropdown-item"><i class="fa-solid fa-gear" style="width:16px;"></i> Account Settings</a>
                    <div style="height: 1px; background: var(--gray-border); margin: 5px 0;"></div>
                    <a href="logout.php" class="dropdown-item text-danger"><i class="fa-solid fa-arrow-right-from-bracket" style="width:16px;"></i> Secure Logout</a>
                </div>
            </div>
        </div>

        <div class="content-area">

            <?php if($alert_msg): ?>
                <div class="alert-box <?= $alert_type == 'success' ? 'alert-success' : 'alert-error' ?>">
                    <i class="fa-solid <?= $alert_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>" style="margin-right: 8px;"></i>
                    <?= $alert_msg ?>
                </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card sc-total">
                    <span>Total Lots</span>
                    <strong><?= $totalLots ?></strong>
                </div>
                <div class="stat-card sc-avail">
                    <span>Available</span>
                    <strong><?= $statusCounts['AVAILABLE'] ?></strong>
                </div>
                <div class="stat-card sc-res">
                    <span>Reserved</span>
                    <strong><?= $statusCounts['RESERVED'] ?></strong>
                </div>
                <div class="stat-card sc-sold">
                    <span>Sold</span>
                    <strong><?= $statusCounts['SOLD'] ?></strong>
                </div>
            </div>

            <div class="directory-wrapper" style="margin-bottom: 30px;">
                <div style="background: white; border-radius: 12px; margin-bottom: 20px; padding: 20px 24px; border: 1px solid var(--gray-border); box-shadow: var(--shadow-sm);">
                    <span style="font-size: 16px; font-weight: 700; color: var(--dark);"><i class="fa-solid fa-list-ul" style="color: var(--primary); margin-right: 8px;"></i> Master List Directory (Click a Municipality to Expand)</span>
                </div>

                <?php 
                // Group lots by location
                $groupedLots = [];
                foreach($lots as $l) {
                    $lName = !empty($l['location']) ? $l['location'] : 'Unassigned';
                    if(!isset($groupedLots[$lName])) {
                        $groupedLots[$lName] = [];
                    }
                    $groupedLots[$lName][] = $l;
                }
                
                // Sort keys alphabetically
                ksort($groupedLots);

                foreach($groupedLots as $locName => $locLots): 
                    $availLots = array_filter($locLots, function($l) { return strtoupper($l['status']) === 'AVAILABLE'; });
                    $locId = md5($locName);
                ?>
                
                <div class="location-card" data-locname="<?= htmlspecialchars($locName) ?>" style="background: white; border-radius: 12px; margin-bottom: 15px; border: 1px solid var(--gray-border); box-shadow: var(--shadow-sm); overflow: hidden;">
                    
                    <div class="loc-header" onclick="toggleDirectory('<?= $locId ?>')" style="padding: 18px 24px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; background: #f8fafc; transition: background 0.2s;">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <div style="width: 40px; height: 40px; background: #e0f2fe; color: #0284c7; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px;">
                                <i class="fa-solid fa-map-location"></i>
                            </div>
                            <div>
                                <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: #0f172a;"><?= htmlspecialchars($locName) ?></h3>
                                <span style="font-size: 12px; color: #64748b; font-weight: 600;"><?= count($availLots) ?> Available out of <?= count($locLots) ?> Total Lots</span>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-down dir-icon" id="icon-<?= $locId ?>" style="color: #64748b; transition: transform 0.3s;"></i>
                    </div>
                    
                    <div class="loc-body" id="body-<?= $locId ?>" style="display: none; border-top: 1px solid var(--gray-border);">
                        
                        <div style="padding: 20px 24px; background: #fcfdfc; border-bottom: 1px solid var(--gray-border);">
                            <h4 style="margin: 0 0 12px 0; font-size: 13px; color: #2e7d32; text-transform: uppercase; font-weight: 800;"><i class="fa-solid fa-check-circle"></i> Available Lots in <?= htmlspecialchars($locName) ?></h4>
                            <?php if(count($availLots) > 0): ?>
                                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px;">
                                    <?php $counter=1; foreach($availLots as $al): ?>
                                        <div style="background: white; border: 1px solid #c8e6c9; padding: 10px 15px; border-radius: 8px; font-size: 13px; color: #37474f; display: flex; justify-content: space-between; align-items: center;">
                                            <span><strong><?= $counter++ ?>.)</strong> Lot <?= htmlspecialchars($al['lot_no']) ?> — <?= number_format($al['area'], 0) ?> sqm</span>
                                            <span style="width: 8px; height: 8px; background: #10b981; border-radius: 50%; display: inline-block; box-shadow: 0 0 4px #10b981;"></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p style="font-size: 13px; color: #94a3b8; margin: 0;">No available lots currently listed for this area.</p>
                            <?php endif; ?>
                        </div>

                        <div style="overflow-x: auto;">
                            <table style="width: 100%; min-width: 800px;">
                                <thead>
                                    <tr>
                                        <th>Image</th>
                                        <th>Property Type</th>
                                        <th>Block/Lot</th>
                                        <th>Area</th>
                                        <th>Price</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($locLots as $lot): ?>
                                    <tr class="lot-row" data-block="<?= htmlspecialchars(strtolower($lot['block_no'] ?? '')) ?>" data-lot="<?= htmlspecialchars(strtolower($lot['lot_no'] ?? '')) ?>" data-status="<?= htmlspecialchars(strtolower($lot['status'] ?? '')) ?>" data-location="<?= htmlspecialchars($lot['location'] ?? '') ?>">
                                        <td><img src="uploads/<?= $lot['lot_image']?:'default_lot.jpg' ?>" style="width: 45px; height: 45px; object-fit: cover; border-radius: 8px; border: 1px solid var(--gray-border);"></td>
                                        <td style="font-size: 12px; font-weight: 600; color: #64748b;"><?= htmlspecialchars($lot['property_type'] ?? 'Lot') ?></td>
                                        <td style="font-weight: 700; color: var(--primary);">B-<?= htmlspecialchars($lot['block_no']) ?> L-<?= htmlspecialchars($lot['lot_no']) ?></td>
                                        <td><?= htmlspecialchars($lot['area']) ?> sqm</td>
                                        <td style="font-weight: 600; color: #1e293b;">₱<?= number_format($lot['total_price']) ?></td>
                                        <td>
                                            <?php 
                                                $badges = [
                                                    'AVAILABLE' => ['bg'=>'#d1fae5', 'col'=>'#065f46'],
                                                    'RESERVED'  => ['bg'=>'#fef3c7', 'col'=>'#92400e'],
                                                    'SOLD'      => ['bg'=>'#fee2e2', 'col'=>'#991b1b']
                                                ];
                                                $b = $badges[strtoupper($lot['status'])] ?? ['bg'=>'#f1f5f9', 'col'=>'#475569'];
                                            ?>
                                            <span class="status-badge" style="background: <?= $b['bg'] ?>; color: <?= $b['col'] ?>;"><?= strtoupper($lot['status']) ?></span>
                                        </td>
                                        <td>
                                            <button type="button" class="btn-action btn-locate" onclick="locateLot(<?= $lot['id'] ?>, '<?= htmlspecialchars(addslashes($lot['location'])) ?>')"><i class="fa-solid fa-location-dot"></i> Locate</button>
                                            <button type="button" class="btn-action btn-edit" onclick="openLotDetails(<?= $lot['id'] ?>, '<?= htmlspecialchars(addslashes($lot['location'])) ?>')"><i class="fa-solid fa-pen"></i> Quick Edit</button>
                                            <a href="admin.php?view=inventory&edit_id=<?= $lot['id'] ?>" class="btn-action btn-full-edit"><i class="fa-solid fa-gear"></i> Full Edit</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="map-container" id="mapSection">
                <div class="map-toolbar">
                    <div class="map-toolbar-left">
                        <div style="display:flex; align-items:center; background: #e2e8f0; border-radius: 8px; padding: 4px; border: 1px solid #cbd5e1;">
                            <span style="font-size: 11px; font-weight: 800; color: #475569; padding: 0 10px; text-transform: uppercase;">1. Set Active Map:</span>
                            <select id="filterLocation" style="border: none; box-shadow: none; font-weight: 600; color: var(--dark); min-width: 180px;">
                                <option value="">-- View All Lots (Directory) --</option>
                                <?php foreach($locations as $loc): ?>
                                    <option value="<?= htmlspecialchars($loc) ?>"><?= htmlspecialchars($loc) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <i class="fa-solid fa-filter" style="color: #90a4ae; margin-left: 10px;"></i>
                        <input type="text" id="searchLot" placeholder="Search Block or Lot...">
                        <select id="filterStatus">
                            <option value="">All Statuses</option>
                            <option value="available">Available</option>
                            <option value="reserved">Reserved</option>
                            <option value="sold">Sold</option>
                        </select>
                        <button type="button" class="btn-reset" onclick="resetFilters()"><i class="fa-solid fa-rotate-right"></i></button>
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data" class="map-upload-form" id="mapUploadForm">
                        <div class="upload-labels">
                            <span class="lbl-title">2. Update Background Map</span>
                            <span class="lbl-loc" id="uploadLocationText">Select Location First</span>
                        </div>
                        <input type="hidden" name="map_location" id="uploadLocationInput" value="">
                        <input type="file" name="map_image" id="mapFileInput" accept="image/*" required>
                        <button type="submit" name="upload_map" id="uploadMapBtn" disabled><i class="fa-solid fa-upload"></i> Upload</button>
                    </form>
                </div>

                <div class="legend">
                    <span><i style="background: rgba(16, 185, 129, 0.8);"></i> Available</span>
                    <span><i style="background: rgba(245, 158, 11, 0.8);"></i> Reserved</span>
                    <span><i style="background: rgba(239, 68, 68, 0.9);"></i> Sold</span>
                </div>

                <div class="map-wrapper" id="svgWrapper">
                    
                    <div id="mapOverlay">
                        <i class="fa-solid fa-map"></i>
                        <h3>Map View Inactive</h3>
                        <p>Please select a specific <strong>Municipality / Branch</strong> from the dropdown above to load its interactive map.</p>
                    </div>

                    <svg id="schemeMap" viewBox="0 0 1464 1052" preserveAspectRatio="xMidYMid meet">
                        <image id="mainMapImage" href="assets/map.png" x="0" y="0" width="1464" height="1052"></image>

                        <?php foreach ($lots as $lot): ?>
                            <?php
                                $statusClass = strtolower($lot['status']);
                                $dataBlock = htmlspecialchars($lot['block_no']);
                                $dataLot = htmlspecialchars($lot['lot_no']);
                                $dataStatus = htmlspecialchars($lot['status']);
                                $dataLocation = htmlspecialchars($lot['location'] ?? '');
                                $dataId = (int)$lot['id'];
                                $points = isset($lot['coordinates']) ? htmlspecialchars($lot['coordinates']) : ''; 
                            ?>
                            <?php if(!empty($points)): ?>
                            <polygon
                                class="lot <?= $statusClass ?>"
                                points="<?= $points ?>"
                                data-id="<?= $dataId ?>"
                                data-block="<?= $dataBlock ?>"
                                data-lot="<?= $dataLot ?>"
                                data-status="<?= $dataStatus ?>"
                                data-location="<?= $dataLocation ?>"
                                onclick="openLotDetails(<?= $dataId ?>, '<?= addslashes($dataLocation) ?>')"
                            >
                                <title>Block <?= $dataBlock ?> - Lot <?= $dataLot ?> (<?= htmlspecialchars($lot['location'] ?? 'N/A') ?> - <?= $dataStatus ?>)</title>
                            </polygon>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </svg>
                </div>
            </div>
            
        </div>
    </div>

    <div id="lotModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Quick Edit Property</h2>
                <button class="close-btn" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div id="modalBody">
                <p>Loading...</p>
            </div>
        </div>
    </div>

    <script>
        // Data populated from PHP containing { 'Location Name': 'uploads/map_location.jpg' }
        const mapUrls = <?= json_encode($map_urls) ?>;

        const modal = document.getElementById('lotModal');
        const modalBody = document.getElementById('modalBody');

        // NEW: Toggle Accordion Directories
        function toggleDirectory(locId) {
            const body = document.getElementById('body-' + locId);
            const icon = document.getElementById('icon-' + locId);
            if (body.style.display === 'none' || body.style.display === '') {
                body.style.display = 'block';
                icon.style.transform = 'rotate(180deg)';
            } else {
                body.style.display = 'none';
                icon.style.transform = 'rotate(0deg)';
            }
        }

        // --- 1. SEARCH, FILTER & DYNAMIC MAP SWITCHING LOGIC ---
        function applyFilters() {
            const searchValue = document.getElementById('searchLot').value.trim().toLowerCase();
            const statusValue = document.getElementById('filterStatus').value.trim().toLowerCase();
            const locationSelect = document.getElementById('filterLocation');
            const locationValue = locationSelect.value; // Exact Case String (e.g. "San Miguel")
            
            // UI Elements
            const mapOverlay = document.getElementById('mapOverlay');
            const mainMapImage = document.getElementById('mainMapImage');
            const uploadBtn = document.getElementById('uploadMapBtn');
            const uploadFormText = document.getElementById('uploadLocationText');
            const uploadInput = document.getElementById('uploadLocationInput');

            // Handle the Map Graphic & Upload Form
            if (locationValue === '') {
                // "View All" Selected -> Show Overlay, lock upload form, hide all polygons
                mapOverlay.style.display = 'flex';
                uploadBtn.disabled = true;
                uploadFormText.innerText = "Select Location First";
                uploadFormText.style.color = "#94a3b8";
                uploadInput.value = "";
            } else {
                // Specific Location Selected -> Hide overlay, load correct image, unlock upload form
                mapOverlay.style.display = 'none';
                uploadBtn.disabled = false;
                uploadFormText.innerText = locationValue;
                uploadFormText.style.color = "var(--primary)";
                uploadInput.value = locationValue;

                // Set new map image (add timestamp to bypass browser cache if just uploaded)
                if(mapUrls[locationValue]) {
                    mainMapImage.setAttribute('href', mapUrls[locationValue] + '?v=' + new Date().getTime());
                } else {
                    mainMapImage.setAttribute('href', 'assets/map.png');
                }
            }

            // Sync the Directory Accordion to the Map Filter Dropdown
            document.querySelectorAll('.location-card').forEach(card => {
                const locName = card.dataset.locname;
                const body = card.querySelector('.loc-body');
                const icon = card.querySelector('.dir-icon');
                
                if(locationValue !== '') {
                    // Map selected: hide other cities, force expand the active city
                    if(locName === locationValue) {
                        card.style.display = 'block';
                        body.style.display = 'block';
                        icon.style.transform = 'rotate(180deg)';
                    } else {
                        card.style.display = 'none';
                    }
                } else {
                    // View all selected: show all city headers again
                    card.style.display = 'block';
                }
            });

            // Filter Map Polygons
            document.querySelectorAll('polygon.lot').forEach(lot => {
                const block = (lot.dataset.block || '').toLowerCase();
                const lotNo = (lot.dataset.lot || '').toLowerCase();
                const status = (lot.dataset.status || '').toLowerCase();
                const loc = (lot.dataset.location || '');

                const matchesSearch = searchValue === '' || block.includes(searchValue) || lotNo.includes(searchValue) || (`b-${block} l-${lotNo}`).includes(searchValue);
                const matchesStatus = statusValue === '' || status === statusValue;
                
                // IMPORTANT: Polygon must EXACTLY match the selected map location.
                const matchesLocation = (locationValue !== '' && loc === locationValue);

                if (matchesSearch && matchesStatus && matchesLocation) {
                    if(!lot.classList.contains('lot-dimmed')) lot.classList.remove('hidden-by-filter');
                } else {
                    lot.classList.add('hidden-by-filter');
                }
            });

            // Filter Table Rows
            document.querySelectorAll('.lot-row').forEach(row => {
                const block = row.dataset.block;
                const lotNo = row.dataset.lot;
                const status = row.dataset.status;
                const loc = row.dataset.location;

                const matchesSearch = searchValue === '' || block.includes(searchValue) || lotNo.includes(searchValue) || (`b-${block} l-${lotNo}`).includes(searchValue);
                const matchesStatus = statusValue === '' || status === statusValue;
                
                // Table shows everything naturally, only hides via search/status
                const matchesLocation = locationValue === '' || loc === locationValue;

                if (matchesSearch && matchesStatus && matchesLocation) row.style.display = '';
                else row.style.display = 'none';
            });
        }

        function resetFilters() {
            document.getElementById('searchLot').value = '';
            document.getElementById('filterStatus').value = '';
            // We do NOT reset the location dropdown here so the user doesn't lose their map context
            restoreMapVisibility(); 
        }

        // Event Listeners for Filters
        document.getElementById('searchLot').addEventListener('input', applyFilters);
        document.getElementById('filterStatus').addEventListener('change', applyFilters);
        document.getElementById('filterLocation').addEventListener('change', applyFilters);


        // --- 2. PINPOINT/LOCATE LOT ON MAP ---
        function locateLot(id, locationName) {
            // First, force the dropdown to the correct location so the right map loads
            const locSelect = document.getElementById('filterLocation');
            if(locSelect.value !== locationName) {
                locSelect.value = locationName;
                applyFilters();
            }

            document.getElementById('mapSection').scrollIntoView({ behavior: 'smooth', block: 'start' });

            document.querySelectorAll('polygon.lot').forEach(lot => {
                // Ensure we only focus polygons that belong to the active map
                if(parseInt(lot.dataset.id) === parseInt(id) && lot.dataset.location === locationName) {
                    lot.classList.remove('hidden-by-filter');
                    lot.classList.remove('lot-dimmed');
                    lot.classList.add('lot-focused');
                } else {
                    lot.classList.remove('lot-focused');
                    lot.classList.add('lot-dimmed');
                }
            });

            let resetBtn = document.getElementById('clearFocusBtn');
            if(!resetBtn) {
                resetBtn = document.createElement('button');
                resetBtn.id = 'clearFocusBtn';
                resetBtn.type = 'button';
                resetBtn.innerHTML = '<i class="fa-solid fa-eye-slash"></i> Clear Map Focus';
                resetBtn.style.cssText = "background: #ef4444; color: white; border: none; padding: 10px 15px; border-radius: 8px; font-weight: 600; cursor: pointer; margin-left: 10px; animation: pulseLot 1.5s infinite;";
                resetBtn.onclick = function() { restoreMapVisibility(); };
                document.querySelector('.map-toolbar-left').appendChild(resetBtn);
            }
        }


        // --- 3. OPEN MODAL & ISOLATE POLYGON ---
        function openLotDetails(id, locationName) {
            if (isDrawing) return; 
            locateLot(id, locationName);

            modal.style.display = 'block';
            modalBody.innerHTML = '<p style="text-align:center; color:#64748b; padding: 20px;"><i class="fa-solid fa-spinner fa-spin"></i> Loading data...</p>';

            fetch('get_lot.php?id=' + encodeURIComponent(id))
                .then(response => response.text())
                .then(html => { modalBody.innerHTML = html; })
                .catch(() => { modalBody.innerHTML = '<p style="color:#ef4444; text-align:center; padding: 20px;">Failed to load data.</p>'; });
        }

        function closeModal() { 
            modal.style.display = 'none'; 
            restoreMapVisibility();
        }

        function restoreMapVisibility() {
            document.querySelectorAll('polygon.lot').forEach(lot => {
                lot.classList.remove('lot-focused');
                lot.classList.remove('lot-dimmed');
            });
            applyFilters(); // Re-apply existing search parameters
            
            let resetBtn = document.getElementById('clearFocusBtn');
            if(resetBtn) resetBtn.remove();
        }

        window.onclick = function(event) { if (event.target === modal) closeModal(); };


        // --- 4. AJAX FORM SUBMISSION ---
        function saveLot(event) {
            event.preventDefault(); 
            const form = document.getElementById('lotForm');
            const formData = new FormData(form);
            
            let saveResult = document.getElementById('saveResult');
            if (!saveResult) {
                saveResult = document.createElement('div');
                saveResult.id = 'saveResult';
                saveResult.style.marginTop = '15px';
                saveResult.style.textAlign = 'center';
                form.appendChild(saveResult);
            }

            saveResult.innerHTML = '<p style="color:#3b82f6; font-size:14px; font-weight:600;"><i class="fa-solid fa-spinner fa-spin"></i> Saving changes...</p>';

            fetch('save_lot.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    saveResult.innerHTML = '<p style="color:#10b981; font-weight:600; font-size:14px;"><i class="fa-solid fa-check-circle"></i> ' + data.message + '</p>';
                    setTimeout(() => { location.reload(); }, 800);
                } else {
                    saveResult.innerHTML = '<p style="color:#ef4444; font-weight:600; font-size:14px;"><i class="fa-solid fa-circle-exclamation"></i> ' + data.message + '</p>';
                }
            })
            .catch(() => {
                saveResult.innerHTML = '<p style="color:#ef4444; font-weight:600; font-size:14px;"><i class="fa-solid fa-circle-exclamation"></i> Server communication error.</p>';
            });
        }


        // --- 5. INTERACTIVE MAP PINNING TOOL ---
        let isDrawing = false;
        let tempPoints = [];
        let tempPolygon = null;

        function startDrawing() {
            modal.style.display = 'none'; 
            isDrawing = true;
            tempPoints = [];
            
            let banner = document.getElementById('drawBanner');
            if(!banner) {
                banner = document.createElement('div');
                banner.id = 'drawBanner';
                banner.innerHTML = `
                    <div style="display:flex; align-items:center; gap:15px;">
                        <span><i class="fa-solid fa-pen-ruler"></i> <strong>Map Pin Mode:</strong> Click the corners of the lot on the map to draw its shape.</span>
                        <button onclick="finishDrawing()" style="background: #10b981; color: white; border: none; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size:13px; transition: 0.2s;">Done</button> 
                        <button onclick="cancelDrawing()" style="background: #ef4444; color: white; border: none; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size:13px; transition: 0.2s;">Cancel</button>
                    </div>
                `;
                banner.style.cssText = "position: fixed; top: 20px; left: 50%; transform: translateX(-50%); background: #1e293b; color: white; padding: 15px 25px; border-radius: 12px; z-index: 10000; box-shadow: 0 10px 25px rgba(0,0,0,0.3); font-size: 14px;";
                document.body.appendChild(banner);
            }
            banner.style.display = 'block';

            const svg = document.getElementById('schemeMap');
            tempPolygon = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
            tempPolygon.setAttribute('fill', 'rgba(245, 158, 11, 0.6)'); 
            tempPolygon.setAttribute('stroke', '#d97706');
            tempPolygon.setAttribute('stroke-width', '4');
            svg.appendChild(tempPolygon);
            
            document.getElementById('svgWrapper').scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        document.getElementById('schemeMap').addEventListener('click', function(e) {
            if(!isDrawing) return;
            const svg = document.getElementById('schemeMap');
            let pt = svg.createSVGPoint();
            pt.x = e.clientX;
            pt.y = e.clientY;
            let svgP = pt.matrixTransform(svg.getScreenCTM().inverse());
            let x = Math.round(svgP.x * 10) / 10;
            let y = Math.round(svgP.y * 10) / 10;
            
            tempPoints.push(`${x},${y}`);
            tempPolygon.setAttribute('points', tempPoints.join(' '));
        });

        function finishDrawing() {
            isDrawing = false;
            document.getElementById('drawBanner').style.display = 'none';
            modal.style.display = 'block'; 
            if(tempPoints.length > 2) {
                let pointsInput = document.getElementById('polygonPoints');
                if(pointsInput) pointsInput.value = tempPoints.join(' ');
            } else {
                alert("Please click at least 3 points on the map to create a valid shape.");
            }
            if(tempPolygon) tempPolygon.remove();
        }

        function cancelDrawing() {
            isDrawing = false;
            document.getElementById('drawBanner').style.display = 'none';
            modal.style.display = 'block';
            if(tempPolygon) tempPolygon.remove();
        }

        // --- 6. INITIALIZE STATE ON LOAD ---
        window.addEventListener('DOMContentLoaded', () => {
            const locSelect = document.getElementById('filterLocation');
            // Auto-select the first available location map so the map area isn't blank
            if(locSelect.options.length > 1) {
                locSelect.selectedIndex = 1; 
            }
            applyFilters();
        });
    </script>
</body>
</html>