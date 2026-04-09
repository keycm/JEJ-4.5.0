<?php
// admin.php
include 'config.php';

// Security Check
if(!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['SUPER ADMIN', 'ADMIN', 'MANAGER'])){
    header("Location: login.php");
    exit();
}

$view = $_GET['view'] ?? 'dashboard';
$edit_mode = false;
$edit_data = [];
$alert_msg = "";
$alert_type = "";

// --- HANDLING ALERTS ---
if(isset($_GET['msg'])){
    $m = $_GET['msg'];
    if($m=='added') { $alert_msg = "Successfully added to database!"; $alert_type = "success"; }
    if($m=='updated') { $alert_msg = "Property details updated."; $alert_type = "success"; }
    if($m=='deleted') { $alert_msg = "Property deleted and moved to Archive."; $alert_type = "error"; }
    if($m=='bulk_added') {
        $count = $_GET['count'] ?? 'Multiple';
        $alert_msg = "$count properties bulk added successfully!"; 
        $alert_type = "success"; 
    }
}

// --- INVENTORY ACTIONS ---
if(isset($_POST['save_lot'])){
    $entry_mode = $_POST['entry_mode'] ?? 'single';
    
    // Smart Location Handler
    $location = $_POST['location'] ?? NULL;
    if($location == 'NEW_AREA'){
        $location = !empty(trim($_POST['new_location'] ?? '')) ? trim($_POST['new_location']) : NULL;
        // If adding a new area only, force single mode
        if(empty($_POST['block_no']) && empty($_POST['lot_no'])) {
            $entry_mode = 'single';
        }
    }

    $prop_type = $_POST['property_type'] ?? 'Lot';
    $block = $_POST['block_no'] ?? '';
    
    // Safely handle empty number inputs (defaults to 0 if empty)
    $area = !empty($_POST['area']) ? $_POST['area'] : 0;
    $price_sqm = !empty($_POST['price_sqm']) ? $_POST['price_sqm'] : 0; 
    $total = !empty($_POST['total_price']) ? $_POST['total_price'] : 0;
    
    $status = $_POST['status'] ?? 'AVAILABLE';
    
    // Process the pricing rules into the overview
    $lot_class = $_POST['lot_class'] ?? 'Inner Lot';
    $terms = $_POST['terms'] ?? '0';
    $term_text = ($terms == '0') ? "Spot Cash Payment" : "$terms Years Installment";
    $base_overview = $_POST['property_overview'] ?? '';
    
    $overview = "📌 [PRICING CONFIGURATION]\nClassification: $lot_class\nPayment Terms: $term_text\n\n" . $base_overview;
    
    $lat = !empty($_POST['latitude']) ? $_POST['latitude'] : NULL;
    $lng = !empty($_POST['longitude']) ? $_POST['longitude'] : NULL;
    
    $lot_image = $_POST['current_image'] ?? ''; 
    if(isset($_FILES['lot_image']) && $_FILES['lot_image']['error'] == 0){
        $target_dir = "uploads/";
        if(!is_dir($target_dir)) mkdir($target_dir);
        $filename = time() . "_" . basename($_FILES["lot_image"]["name"]);
        move_uploaded_file($_FILES["lot_image"]["tmp_name"], $target_dir . $filename);
        $lot_image = $filename;
    }

    if($entry_mode == 'bulk') {
        // --- BULK ENTRY LOGIC ---
        $start_lot = (int)$_POST['start_lot'];
        $end_lot = (int)$_POST['end_lot'];
        $added = 0;
        
        $stmt = $conn->prepare("INSERT INTO lots (location, property_type, block_no, lot_no, area, price_per_sqm, total_price, status, property_overview, lot_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        for($i = $start_lot; $i <= $end_lot; $i++) {
            $current_lot = (string)$i;
            $stmt->bind_param("ssssdddsss", $location, $prop_type, $block, $current_lot, $area, $price_sqm, $total, $status, $overview, $lot_image);
            if($stmt->execute()){
                $added++;
            }
        }
        
        logActivity($conn, $_SESSION['user_id'], "Bulk Added Properties", "Added $added lots to Block $block in $location");
        header("Location: admin.php?view=inventory&msg=bulk_added&count=$added");
        exit();

    } else {
        // --- SINGLE ENTRY / UPDATE LOGIC ---
        $lot_no = $_POST['lot_no'] ?? '';

        if(!empty($_POST['lot_id'])){
            $id = $_POST['lot_id'];
            $stmt = $conn->prepare("UPDATE lots SET location=?, property_type=?, block_no=?, lot_no=?, area=?, price_per_sqm=?, total_price=?, status=?, property_overview=?, latitude=?, longitude=?, lot_image=? WHERE id=?");
            $stmt->bind_param("ssssdddssddsi", $location, $prop_type, $block, $lot_no, $area, $price_sqm, $total, $status, $overview, $lat, $lng, $lot_image, $id);
            $stmt->execute();
            $target_lot_id = $id;
            $msg = "updated";
            
            logActivity($conn, $_SESSION['user_id'], "Updated Property", "Lot ID: $id | Block: $block, Lot: $lot_no | Status: $status");
        } else {
            $stmt = $conn->prepare("INSERT INTO lots (location, property_type, block_no, lot_no, area, price_per_sqm, total_price, status, property_overview, latitude, longitude, lot_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssdddssdds", $location, $prop_type, $block, $lot_no, $area, $price_sqm, $total, $status, $overview, $lat, $lng, $lot_image);
            $stmt->execute();
            $target_lot_id = $conn->insert_id;
            $msg = "added";

            logActivity($conn, $_SESSION['user_id'], "Added New Property/Area", "ID: $target_lot_id | Location: $location");
        }

        if(isset($_FILES['gallery'])){
            $count = count($_FILES['gallery']['name']);
            if(!is_dir("uploads/")) mkdir("uploads/");
            for($i=0; $i<$count; $i++){
                if($_FILES['gallery']['error'][$i] == 0){
                    $g_filename = time() . "_" . $i . "_" . basename($_FILES['gallery']['name'][$i]);
                    if(move_uploaded_file($_FILES['gallery']['tmp_name'][$i], "uploads/" . $g_filename)){
                        $conn->query("INSERT INTO lot_gallery (lot_id, image_path) VALUES ('$target_lot_id', '$g_filename')");
                    }
                }
            }
        }

        header("Location: admin.php?view=inventory&msg=$msg");
        exit();
    }
}

if(isset($_GET['delete_id'])){
    $id = $_GET['delete_id'];
    $lot_data = $conn->query("SELECT * FROM lots WHERE id='$id'")->fetch_assoc();
    if($lot_data) { logDeletion($conn, 'Property Inventory', $id, $lot_data, $_SESSION['user_id']); }
    logActivity($conn, $_SESSION['user_id'], "Deleted Property", "Removed Lot ID: $id from inventory");

    $conn->query("DELETE FROM reservations WHERE lot_id='$id'");
    $conn->query("DELETE FROM lot_gallery WHERE lot_id='$id'");
    $conn->query("DELETE FROM lots WHERE id='$id'");
    header("Location: admin.php?view=inventory&msg=deleted");
    exit();
}

if(isset($_GET['edit_id'])){
    $view = 'inventory'; 
    $edit_mode = true;
    $id = $_GET['edit_id'];
    $edit_data = $conn->query("SELECT * FROM lots WHERE id='$id'")->fetch_assoc();
}

// --- DATA FETCHING (PROPERTY INVENTORY OVERVIEW) ---
$stats_pending = $conn->query("SELECT COUNT(*) as count FROM reservations WHERE status='PENDING'")->fetch_assoc()['count'];
$stats_reserved = $conn->query("SELECT COUNT(*) as count FROM lots WHERE status='RESERVED'")->fetch_assoc()['count'];
$stats_sold    = $conn->query("SELECT COUNT(*) as count FROM lots WHERE status='SOLD'")->fetch_assoc()['count'];
$stats_avail   = $conn->query("SELECT COUNT(*) as count FROM lots WHERE status='AVAILABLE'")->fetch_assoc()['count'];

// FETCH ALL LOTS FOR INVENTORY DIRECTORY TAB
$lots = [];
if($view == 'inventory') {
    $sql = "SELECT * FROM lots ORDER BY location ASC, CAST(block_no AS UNSIGNED), CAST(lot_no AS UNSIGNED)";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $lots[] = $row;
        }
    }
}

// --- DATA FETCHING (MINI FINANCIAL & CALENDAR) ---
$income_months = []; $income_totals = [];
$expense_months = []; $expense_totals = [];
$calendar_events = [];

$resQuery = $conn->query("SELECT r.reservation_date, l.block_no, l.lot_no FROM reservations r JOIN lots l ON r.lot_id = l.id");
while($r = $resQuery->fetch_assoc()){
    $calendar_events[] = [
        'title' => 'Res: B'.$r['block_no'].' L'.$r['lot_no'],
        'start' => date('Y-m-d', strtotime($r['reservation_date'])),
        'color' => '#f57c00'
    ];
}

$recent_reservations = $conn->query("
    SELECT r.*, u.fullname, l.block_no, l.lot_no, l.total_price 
    FROM reservations r JOIN users u ON r.user_id = u.id JOIN lots l ON r.lot_id = l.id 
    ORDER BY r.reservation_date DESC LIMIT 10
");

$checkTable = $conn->query("SHOW TABLES LIKE 'transactions'");
if($checkTable && $checkTable->num_rows > 0) {
    $incData = $conn->query("SELECT DATE_FORMAT(transaction_date, '%b %Y') as month_label, DATE_FORMAT(transaction_date, '%Y-%m') as month_val, SUM(amount) as monthly_total FROM transactions WHERE type='INCOME' GROUP BY month_val, month_label ORDER BY month_val DESC LIMIT 6");
    while($row = $incData->fetch_assoc()){
        $income_months[] = $row['month_label'];
        $income_totals[] = $row['monthly_total'];
    }
    $income_months = array_reverse($income_months);
    $income_totals = array_reverse($income_totals);

    $expData = $conn->query("SELECT DATE_FORMAT(transaction_date, '%b %Y') as month_label, DATE_FORMAT(transaction_date, '%Y-%m') as month_val, SUM(amount) as monthly_total FROM transactions WHERE type='EXPENSE' GROUP BY month_val, month_label ORDER BY month_val DESC LIMIT 6");
    while($row = $expData->fetch_assoc()){
        $expense_months[] = $row['month_label'];
        $expense_totals[] = $row['monthly_total'];
    }
    $expense_months = array_reverse($expense_months);
    $expense_totals = array_reverse($expense_totals);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | JEJ Surveying</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-geosearch@3.11.0/dist/geosearch.css" />

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>

    <style>
        :root {
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

        /* Stats Grid - Nature Colors */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 24px; margin-bottom: 35px; }
        .stat-card { background: white; padding: 24px; border-radius: 16px; border: 1px solid var(--gray-border); box-shadow: var(--shadow-sm); position: relative; overflow: hidden; transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
        .stat-card h2 { font-size: 34px; font-weight: 800; color: var(--dark); margin: 8px 0 0; letter-spacing: -1px; }
        .stat-card small { font-size: 12px; font-weight: 600; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px; }
        .stat-icon { position: absolute; right: -15px; bottom: -15px; font-size: 90px; opacity: 0.08; transform: rotate(-10deg); transition: transform 0.3s; }
        .stat-card:hover .stat-icon { transform: rotate(0deg) scale(1.1); }

        .sc-autumn { border-top: 4px solid #d84315; } .sc-autumn .stat-icon { color: #d84315; }
        .sc-wood { border-top: 4px solid #8d6e63; } .sc-wood .stat-icon { color: #8d6e63; } 
        .sc-stone { border-top: 4px solid #546e7a; } .sc-stone .stat-icon { color: #546e7a; } 
        .sc-leaf { border-top: 4px solid #43a047; } .sc-leaf .stat-icon { color: #43a047; } 

        /* Dashboard Widgets */
        .dashboard-widgets { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 24px; margin-bottom: 35px; }
        @media (max-width: 1400px) { .dashboard-widgets { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 900px) { .dashboard-widgets { grid-template-columns: 1fr; } }
        
        .widget-card { background: white; padding: 24px; border-radius: 16px; border: 1px solid var(--gray-border); box-shadow: var(--shadow-sm); }
        .widget-title { font-size: 15px; font-weight: 700; color: var(--dark); margin-bottom: 20px; border-bottom: 1px solid var(--gray-border); padding-bottom: 12px; display: flex; justify-content: space-between; align-items: center;}
        
        /* Table Styling */
        .table-container { background: white; border-radius: 16px; border: 1px solid var(--gray-border); box-shadow: var(--shadow-sm); overflow: hidden; margin-bottom: 30px; }
        .table-header { padding: 20px 24px; border-bottom: 1px solid var(--gray-border); display: flex; justify-content: space-between; align-items: center; background: #fff; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 16px 24px; font-size: 12px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; background: var(--gray-light); border-bottom: 1px solid var(--gray-border); letter-spacing: 0.5px;}
        td { padding: 16px 24px; border-bottom: 1px solid var(--gray-border); color: #37474f; font-size: 14px; vertical-align: middle; }
        tr:hover td { background: #fdfdfd; }
        tr:last-child td { border-bottom: none; }

        /* Forms & Buttons */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; }
        .input-group { margin-bottom: 18px; }
        .input-group label { display: block; font-size: 13px; font-weight: 600; color: #455a64; margin-bottom: 8px; }
        .form-control { width: 100%; padding: 12px 16px; border: 1px solid #a5d6a7; border-radius: 8px; background: #fff; font-family: inherit; font-size: 14px; transition: all 0.2s; box-sizing: border-box; }
        .form-control:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.15); }
        select.form-control { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='none' stroke='%23455a64' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 14px center; padding-right: 35px; }
        
        .section-header { margin: 25px 0 15px 0; font-size: 16px; font-weight: 700; color: var(--dark); border-bottom: 2px solid var(--gray-light); padding-bottom: 8px; display: flex; align-items: center; gap: 8px; }
        .box-highlight { background: #e0f2fe; padding: 15px 20px; border-radius: 8px; border: 1px solid #bae6fd; grid-column: 1 / -1; display: flex; justify-content: space-between; align-items: center; }
        
        .btn-action { padding: 8px 14px; border-radius: 6px; font-size: 12px; font-weight: 600; text-decoration: none; display: inline-block; cursor: pointer; transition: all 0.2s;}
        .btn-edit { background: var(--primary-light); color: var(--primary); border: 1px solid rgba(46, 125, 50, 0.2); }
        .btn-edit:hover { background: #c8e6c9; }
        .btn-delete { background: #ffebee; color: #c62828; border: 1px solid #ffccbc; }
        .btn-delete:hover { background: #ffcdd2; }
        .btn-save { background: var(--primary); color: white; border: none; padding: 14px 28px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 14px; box-shadow: 0 4px 6px rgba(46, 125, 50, 0.2); transition: all 0.2s;}
        .btn-save:hover { background: var(--dark); box-shadow: 0 6px 8px rgba(27, 94, 32, 0.3); transform: translateY(-1px);}

        /* Miscellaneous */
        #map { height: 350px; width: 100%; border-radius: 12px; border: 1px solid #a5d6a7; z-index: 1; }
        .fc .fc-toolbar-title { font-size: 15px !important; color: var(--dark); font-weight: 700;}
        .fc .fc-button { padding: 4px 10px !important; font-size: 12px !important; background: var(--primary) !important; border: none !important; border-radius: 6px !important;}
        .fc .fc-day-today { background: var(--gray-light) !important; } 
        .fc-event { font-size: 11px !important; padding: 3px 5px !important; border: none !important; border-radius: 4px !important; font-weight: 600; cursor: pointer;}
        .status-badge { padding: 6px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; letter-spacing: 0.3px; display: inline-block;}
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
            <a href="admin.php?view=dashboard" class="menu-link <?= $view=='dashboard'?'active':'' ?>"><i class="fa-solid fa-chart-pie"></i> Dashboard</a>
            <a href="reservation.php" class="menu-link"><i class="fa-solid fa-file-signature"></i> Reservations</a>
            <a href="master_list.php" class="menu-link"><i class="fa-solid fa-map-location-dot"></i> Master List / Map</a>
            <a href="admin.php?view=inventory" class="menu-link <?= $view=='inventory'?'active':'' ?>"><i class="fa-solid fa-plus-circle"></i> Add Property</a>
            <a href="financial.php" class="menu-link"><i class="fa-solid fa-coins"></i> Financials</a>
            <a href="payment_tracking.php" class="menu-link"><i class="fa-solid fa-file-invoice-dollar"></i> Payment Tracking</a>
            
            <small style="padding: 0 15px; color: #90a4ae; font-weight: 700; font-size: 11px; display: block; margin-top: 25px; margin-bottom: 12px; letter-spacing: 0.5px;">MANAGEMENT</small>
            <a href="accounts.php" class="menu-link"><i class="fa-solid fa-users-gear"></i> Accounts</a>
            <a href="delete_history.php" class="menu-link"><i class="fa-solid fa-trash-can"></i> Delete History</a>
            
            <small style="padding: 0 15px; color: #90a4ae; font-weight: 700; font-size: 11px; display: block; margin-top: 25px; margin-bottom: 12px; letter-spacing: 0.5px;">SYSTEM</small>
            <a href="index.php" class="menu-link" target="_blank"><i class="fa-solid fa-globe"></i> View Website</a>
        </div>
    </div>

    <div class="main-panel">
        
        <div class="top-header">
            <div class="header-title">
                <h1><?= $view == 'dashboard' ? 'Overview Dashboard' : 'Property Inventory Management' ?></h1>
                <p><?= $view == 'dashboard' ? "Welcome back! Here's what's happening with your estate today." : "Configure land pricing, rapidly bulk add properties, and view inventory." ?></p>
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
                <div style="padding: 16px 20px; border-radius: 10px; margin-bottom: 25px; font-weight: 500; font-size: 14px; background: <?= $alert_type=='success' ? '#e8f5e9' : '#fbe9e7' ?>; color: <?= $alert_type=='success' ? '#2e7d32' : '#d84315' ?>; border: 1px solid <?= $alert_type=='success' ? '#c8e6c9' : '#ffccbc' ?>; box-shadow: var(--shadow-sm);">
                    <i class="fa-solid <?= $alert_type=='success'?'fa-check-circle':'fa-exclamation-circle' ?>" style="margin-right: 10px;"></i>
                    <?= $alert_msg ?>
                </div>
            <?php endif; ?>

            <?php if($view == 'dashboard'): ?>

                <div class="stats-grid">
                    <div class="stat-card sc-autumn">
                        <small>Pending Requests</small>
                        <h2><?= $stats_pending ?></h2>
                        <i class="fa-solid fa-clock stat-icon"></i>
                    </div>
                    <div class="stat-card sc-wood">
                        <small>Reserved Properties</small>
                        <h2><?= $stats_reserved ?></h2>
                        <i class="fa-solid fa-bookmark stat-icon"></i>
                    </div>
                    <div class="stat-card sc-stone"> 
                        <small>Sold Units</small>
                        <h2><?= $stats_sold ?></h2>
                        <i class="fa-solid fa-handshake stat-icon"></i>
                    </div>
                    <div class="stat-card sc-leaf">
                        <small>Available Lots</small>
                        <h2><?= $stats_avail ?></h2>
                        <i class="fa-solid fa-map stat-icon"></i>
                    </div>
                </div>

                <div class="dashboard-widgets">
                    <div class="widget-card">
                        <div class="widget-title"><span><i class="fa-solid fa-chart-column" style="color: #43a047; margin-right: 8px;"></i> Monthly Income</span><a href="financial.php" style="font-size: 12px; color: var(--primary); text-decoration: none; font-weight: 600;">Details <i class="fa-solid fa-arrow-right"></i></a></div>
                        <div style="position: relative; height: 240px; width: 100%;"><canvas id="incomeChart"></canvas></div>
                    </div>
                    <div class="widget-card">
                        <div class="widget-title"><span><i class="fa-solid fa-money-check-dollar" style="color: #d84315; margin-right: 8px;"></i> Total Expenses</span><a href="financial.php" style="font-size: 12px; color: var(--primary); text-decoration: none; font-weight: 600;">Details <i class="fa-solid fa-arrow-right"></i></a></div>
                        <div style="position: relative; height: 240px; width: 100%;"><canvas id="expenseChart"></canvas></div>
                    </div>
                    <div class="widget-card">
                        <div class="widget-title"><span><i class="fa-solid fa-calendar-days" style="color: var(--primary); margin-right: 8px;"></i> Schedule Tracker</span></div>
                        <div id="miniCalendar" style="height: 240px;"></div>
                    </div>
                </div>

                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const formatCurrency = (val) => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', minimumFractionDigits: 0 }).format(val);

                    const commonOptions = { 
                        responsive: true, maintainAspectRatio: false, 
                        plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(context) { return ' Total: ' + formatCurrency(context.raw); } } } }, 
                        scales: { 
                            y: { beginAtZero: true, border: {display: false}, grid: {color: '#eceff1'}, ticks: { font: {family: 'Inter', size: 10}, color: '#78909c', callback: function(value) { if(value >= 1000000) return '₱' + (value/1000000).toFixed(1) + 'M'; if(value >= 1000) return '₱' + (value/1000).toFixed(1) + 'K'; return '₱' + value; } } }, 
                            x: { grid: { display: false }, ticks: { font: {family: 'Inter', size: 11}, color: '#607d8b' } } 
                        } 
                    };

                    const ctxIncome = document.getElementById('incomeChart').getContext('2d');
                    new Chart(ctxIncome, { type: 'bar', data: { labels: <?= json_encode($income_months) ?>, datasets: [{ label: 'Income', data: <?= json_encode($income_totals) ?>, backgroundColor: 'rgba(46, 125, 50, 0.85)', hoverBackgroundColor: 'rgba(27, 94, 32, 1)', borderRadius: 6, barThickness: 20 }] }, options: commonOptions });

                    const ctxExpense = document.getElementById('expenseChart').getContext('2d');
                    new Chart(ctxExpense, { type: 'line', data: { labels: <?= json_encode($expense_months) ?>, datasets: [{ label: 'Expenses', data: <?= json_encode($expense_totals) ?>, backgroundColor: 'rgba(216, 67, 21, 0.1)', borderColor: 'rgba(216, 67, 21, 0.9)', borderWidth: 3, tension: 0.3, fill: true, pointRadius: 4, pointBackgroundColor: '#fff', pointBorderColor: 'rgba(216, 67, 21, 1)', pointHoverRadius: 6 }] }, options: commonOptions });

                    var calendarEl = document.getElementById('miniCalendar');
                    var calendar = new FullCalendar.Calendar(calendarEl, { initialView: 'dayGridMonth', height: 250, headerToolbar: { left: 'prev,next', center: 'title', right: 'today' }, events: <?= json_encode($calendar_events) ?>, displayEventTime: false, nowIndicator: true, themeSystem: 'standard' });
                    calendar.render();
                });
                </script>

                <div class="table-container">
                    <div class="table-header">
                        <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: var(--dark);"><i class="fa-solid fa-list-check" style="color: var(--primary); margin-right: 8px;"></i> Recent Reservations</h3>
                        <a href="reservation.php" style="font-size: 13px; font-weight: 600; color: var(--primary); text-decoration: none;">View All <i class="fa-solid fa-arrow-right" style="margin-left: 4px;"></i></a>
                    </div>
                    <table>
                        <thead>
                            <tr><th>Date Submitted</th><th>Buyer Name</th><th>Property Details</th><th>Total Price</th><th>Status</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            <?php if($recent_reservations && $recent_reservations->num_rows > 0): ?>
                                <?php while($res = $recent_reservations->fetch_assoc()): ?>
                                <tr>
                                    <td style="font-weight: 500; color: var(--text-muted);"><?= date('M d, Y', strtotime($res['reservation_date'])) ?></td>
                                    <td style="font-weight: 600; color: #263238;"><?= htmlspecialchars($res['fullname']) ?></td>
                                    <td style="font-weight: 600; color: var(--primary);">Block <?= $res['block_no'] ?>, Lot <?= $res['lot_no'] ?></td>
                                    <td style="font-weight: 600;">₱<?= number_format($res['total_price']) ?></td>
                                    <td>
                                        <?php 
                                            $status_colors = ['PENDING' => ['bg'=>'#fff3e0', 'col'=>'#e65100'], 'APPROVED' => ['bg'=>'#e8f5e9', 'col'=>'#2e7d32'], 'REJECTED' => ['bg'=>'#ffebee', 'col'=>'#c62828']];
                                            $sc = $status_colors[$res['status']] ?? ['bg'=>'#eceff1', 'col'=>'#546e7a'];
                                        ?>
                                        <span class="status-badge" style="background: <?= $sc['bg'] ?>; color: <?= $sc['col'] ?>;"><?= $res['status'] ?></span>
                                    </td>
                                    <td><a href="reservation.php?status=<?= $res['status'] ?>" class="btn-action btn-edit"><i class="fa-solid fa-pen-to-square" style="margin-right:4px;"></i> Manage</a></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" style="text-align: center; padding: 30px; color: var(--text-muted); font-weight: 500;">No recent reservations available.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif($view == 'inventory'): ?>
                <div class="table-container" style="padding: 0; overflow: visible; background: transparent; border: none; box-shadow: none;">
                    
                    <div style="background: white; padding: 35px; border-radius: 16px; border: 1px solid var(--gray-border); box-shadow: var(--shadow-sm); margin-bottom: 30px;">
                        
                        <div style="margin-bottom: 25px; border-bottom: 1px solid var(--gray-border); padding-bottom: 15px;">
                            <span style="font-size: 18px; font-weight: 700; color: var(--dark);"><i class="fa-solid <?= $edit_mode ? 'fa-pen-to-square' : 'fa-plus-circle' ?>" style="color: var(--primary); margin-right: 8px;"></i> <?= $edit_mode ? 'Edit Property Details' : 'Add New Property' ?></span>
                        </div>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="lot_id" value="<?= $edit_mode ? $edit_data['id'] : '' ?>">
                            <input type="hidden" name="current_image" value="<?= $edit_mode ? $edit_data['lot_image'] : '' ?>">
                            <input type="hidden" name="latitude" id="lat" value="<?= $edit_mode ? $edit_data['latitude'] : '' ?>">
                            <input type="hidden" name="longitude" id="lng" value="<?= $edit_mode ? $edit_data['longitude'] : '' ?>">
                            
                            <?php if(!$edit_mode): ?>
                            <div class="input-group" id="entryModeSection" style="grid-column: 1 / -1; margin-bottom: 25px; background: #f8fafc; padding: 18px 24px; border-radius: 10px; border: 1px dashed #94a3b8;">
                                <label style="margin-bottom: 12px; font-size: 15px; color: #0f172a;"><i class="fa-solid fa-layer-group" style="color: var(--primary); margin-right: 6px;"></i> Select Entry Mode</label>
                                <div style="display: flex; gap: 30px;">
                                    <label style="cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 15px; font-weight: 600; color: #334155;">
                                        <input type="radio" name="entry_mode" value="single" checked onchange="toggleEntryMode()" style="width: 18px; height: 18px; accent-color: var(--primary);"> 
                                        Single Lot Entry
                                    </label>
                                    <label style="cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 15px; font-weight: 600; color: #334155;">
                                        <input type="radio" name="entry_mode" value="bulk" onchange="toggleEntryMode()" style="width: 18px; height: 18px; accent-color: var(--primary);"> 
                                        Bulk Entry (Multiple Lots)
                                    </label>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="section-header"><i class="fa-solid fa-circle-info" style="color: var(--primary);"></i> Location Information</div>
                            <div class="form-grid">
                                <div class="input-group" style="grid-column: 1 / -1;">
                                    <label>Location / Area Database</label>
                                    <select name="location" id="locationSelect" class="form-control" onchange="checkNewLocation()">
                                        <option value="">-- Select Existing Area --</option>
                                        <?php 
                                        $locQuery = $conn->query("SELECT DISTINCT location FROM lots WHERE location IS NOT NULL AND location != '' ORDER BY location ASC");
                                        while($locRow = $locQuery->fetch_assoc()):
                                            $locVal = htmlspecialchars($locRow['location']);
                                        ?>
                                            <option value="<?= $locVal ?>" <?= ($edit_mode && ($edit_data['location']??'') == $locVal) ? 'selected' : '' ?>><?= $locVal ?></option>
                                        <?php endwhile; ?>
                                        <option value="NEW_AREA" style="font-weight: bold; color: var(--primary);">+ Add New Area / Municipality...</option>
                                    </select>
                                    <input type="text" name="new_location" id="newLocationInput" class="form-control" placeholder="Type new area name here..." style="display: none; margin-top: 10px; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.1);">
                                    <small id="newAreaHelperText" style="display:none; color: #0284c7; font-weight: 600; margin-top: 8px;"><i class="fa-solid fa-circle-info"></i> The form below has been hidden. You are now only creating an area category.</small>
                                </div>
                            </div>

                            <div id="lotDetailsWrapper">
                                <div class="section-header" style="margin-top: 20px;"><i class="fa-solid fa-tags" style="color: var(--primary);"></i> Property Classification</div>
                                <div class="form-grid">
                                    <div class="input-group">
                                        <label>Property Type</label>
                                        <select name="property_type" class="form-control">
                                            <option value="Lot" <?= ($edit_mode && ($edit_data['property_type']??'')=='Lot')?'selected':'' ?>>Lot</option>
                                            <option value="Subdivision" <?= ($edit_mode && ($edit_data['property_type']??'')=='Subdivision')?'selected':'' ?>>Subdivision</option>
                                            <option value="Land" <?= ($edit_mode && ($edit_data['property_type']??'')=='Land')?'selected':'' ?>>Land</option>
                                            <option value="Farm" <?= ($edit_mode && ($edit_data['property_type']??'')=='Farm')?'selected':'' ?>>Farm</option>
                                            <option value="Shop" <?= ($edit_mode && ($edit_data['property_type']??'')=='Shop')?'selected':'' ?>>Shop</option>
                                            <option value="Business" <?= ($edit_mode && ($edit_data['property_type']??'')=='Business')?'selected':'' ?>>Business</option>
                                        </select>
                                    </div>
                                    <div class="input-group">
                                        <label>Current Status</label>
                                        <select name="status" class="form-control">
                                            <option value="AVAILABLE" <?= ($edit_mode && $edit_data['status']=='AVAILABLE')?'selected':'' ?>>Available</option>
                                            <option value="RESERVED" <?= ($edit_mode && $edit_data['status']=='RESERVED')?'selected':'' ?>>Reserved</option>
                                            <option value="SOLD" <?= ($edit_mode && $edit_data['status']=='SOLD')?'selected':'' ?>>Sold</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="section-header" style="margin-top: 30px;"><i class="fa-solid fa-calculator" style="color: var(--primary);"></i> Lot Detail & Installment Terms</div>
                                <div class="form-grid-3">
                                    <div class="input-group">
                                        <label>Block</label>
                                        <input type="text" name="block_no" id="block_no" class="form-control req-field" placeholder="e.g., 5" value="<?= $edit_mode?$edit_data['block_no']:'' ?>" required>
                                    </div>
                                    
                                    <div class="input-group single-mode-field">
                                        <label>Lot No.</label>
                                        <input type="text" name="lot_no" id="lot_no" class="form-control req-field" placeholder="e.g., 12" value="<?= $edit_mode?$edit_data['lot_no']:'' ?>" <?= !$edit_mode ? 'required' : '' ?>>
                                    </div>
                                    <?php if(!$edit_mode): ?>
                                    <div class="input-group bulk-mode-field" style="display: none;">
                                        <label>Lot Range (Start to End)</label>
                                        <div style="display: flex; gap: 10px;">
                                            <input type="number" name="start_lot" id="start_lot" class="form-control req-field" placeholder="Start (e.g. 1)">
                                            <input type="number" name="end_lot" id="end_lot" class="form-control req-field" placeholder="End (e.g. 20)">
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <div class="input-group">
                                        <label>Lot Area (sqm) <?= !$edit_mode ? '<span class="bulk-mode-field" style="display:none; color:#64748b; font-weight:normal;">(Applied to all in range)</span>' : '' ?></label>
                                        <input type="number" name="area" id="area" class="form-control req-field" placeholder="0" value="<?= $edit_mode?$edit_data['area']:'' ?>" required oninput="calcTotal()">
                                    </div>
                                    
                                    <div class="input-group">
                                        <label>Base Price / SQM</label>
                                        <input type="number" id="base_price" class="form-control req-field" placeholder="0.00" value="<?= $edit_mode?$edit_data['price_per_sqm']:'' ?>" required oninput="calcTotal()">
                                    </div>
                                    <div class="input-group">
                                        <label>Classification</label>
                                        <select name="lot_class" id="lot_class" class="form-control" onchange="calcTotal()">
                                            <option value="Inner Lot">Inner Lot</option>
                                            <option value="Front Lot">Front Lot</option>
                                        </select>
                                    </div>
                                    <div class="input-group">
                                        <label>Installment Type</label>
                                        <select name="terms" id="terms" class="form-control" onchange="calcTotal()">
                                            <option value="0">Cash Payment</option>
                                            <option value="1">1 Year Installment</option>
                                            <option value="2">2 Years Installment</option>
                                            <option value="3">3 Years Installment</option>
                                        </select>
                                    </div>

                                    <input type="hidden" name="price_sqm" id="price_sqm" value="<?= $edit_mode?$edit_data['price_per_sqm']:'' ?>">
                                    <input type="hidden" name="total_price" id="total" value="<?= $edit_mode?$edit_data['total_price']:'' ?>">

                                    <div class="box-highlight">
                                        <div style="flex: 1;">
                                            <span style="display: block; font-size: 11px; font-weight: 700; color: #0369a1; text-transform: uppercase; letter-spacing: 0.5px;">Price / SQM</span>
                                            <span id="display_price_sqm" style="display: block; font-size: 20px; font-weight: 800; color: #0284c7; margin-top: 4px;">₱ 0.00</span>
                                        </div>
                                        <div style="flex: 1; border-left: 1px solid #bae6fd; padding-left: 20px;">
                                            <span style="display: block; font-size: 11px; font-weight: 700; color: #0369a1; text-transform: uppercase; letter-spacing: 0.5px;">Total Contract Price</span>
                                            <span id="display_total" style="display: block; font-size: 20px; font-weight: 800; color: #0284c7; margin-top: 4px;">₱ 0.00</span>
                                        </div>
                                        <div style="flex: 1.5; border-left: 1px solid #bae6fd; padding-left: 20px;">
                                            <span style="display: block; font-size: 11px; font-weight: 700; color: #0369a1; text-transform: uppercase; letter-spacing: 0.5px;">Monthly Amortization</span>
                                            <span id="display_monthly" style="display: block; font-size: 20px; font-weight: 800; color: #0284c7; margin-top: 4px;">Spot Cash</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="section-header" style="margin-top: 30px;"><i class="fa-solid fa-images" style="color: var(--primary);"></i> Media & Details</div>
                                
                                <div class="input-group">
                                    <label>Property Overview & Description</label>
                                    <textarea name="property_overview" class="form-control" rows="4" placeholder="Describe the property, nearby landmarks, or specific features..."><?= $edit_mode ? ($edit_data['property_overview'] ?? '') : '' ?></textarea>
                                </div>

                                <div class="form-grid">
                                    <div class="input-group">
                                        <label>Main Property Image</label>
                                        <input type="file" name="lot_image" class="form-control" style="padding: 9px 16px;">
                                        <?php if($edit_mode && $edit_data['lot_image']): ?>
                                            <small style="display:block; margin-top:6px; color: var(--text-muted); font-weight: 500;">Current File: <?= $edit_data['lot_image'] ?></small>
                                        <?php endif; ?>
                                    </div>

                                    <div class="input-group">
                                        <label>Other Angles / Gallery (Multiple)</label>
                                        <input type="file" name="gallery[]" class="form-control" multiple accept="image/*" style="padding: 9px 16px;">
                                        <small style="color: var(--text-muted); font-weight: 500; display: block; margin-top: 6px;">Hold Ctrl/Cmd to select multiple images.</small>
                                    </div>
                                </div>

                                <?php if($edit_mode): ?>
                                    <div style="display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap;">
                                        <?php 
                                        $gal_res = $conn->query("SELECT * FROM lot_gallery WHERE lot_id='$id'");
                                        while($img = $gal_res->fetch_assoc()):
                                        ?>
                                            <div style="width: 70px; height: 70px; border-radius: 8px; overflow: hidden; border: 1px solid var(--gray-border); box-shadow: var(--shadow-sm);">
                                                <img src="uploads/<?= $img['image_path'] ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="input-group single-mode-field" style="margin-top: 10px;">
                                    <label><i class="fa-solid fa-map-pin" style="color:#d84315; margin-right: 5px;"></i> Pin Location (Search or Click)</label>
                                    <div id="map"></div>
                                    <small style="color: var(--text-muted); display: block; margin-top: 8px; font-weight: 500;">Use the search icon (top-left) to find a city, or click anywhere to pin manually.</small>
                                </div>
                            </div>
                            <div style="margin-top: 35px; padding-top: 20px; border-top: 1px solid var(--gray-border); text-align: right;">
                                <?php if($edit_mode): ?>
                                    <a href="admin.php?view=inventory" class="btn-action" style="background:#eceff1; color:#546e7a; margin-right:12px; padding: 12px 24px; font-size: 14px; border: 1px solid #cfd8dc;">Cancel Edit</a>
                                <?php endif; ?>
                                <button type="submit" name="save_lot" class="btn-save">
                                    <i class="fa-solid fa-cloud-arrow-up" style="margin-right: 6px;"></i> <span id="submitBtnText"><?= $edit_mode ? 'Update Property' : 'Save Property' ?></span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <?php if(!$edit_mode): ?>
                        <div class="directory-wrapper" style="margin-bottom: 30px;">
                            <div style="background: white; border-radius: 12px; margin-bottom: 20px; padding: 20px 24px; border: 1px solid var(--gray-border); box-shadow: var(--shadow-sm);">
                                <span style="font-size: 16px; font-weight: 700; color: var(--dark);"><i class="fa-solid fa-list-ul" style="color: var(--primary); margin-right: 8px;"></i> Existing Property Inventory (Grouped by Area)</span>
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
                            ksort($groupedLots);

                            foreach($groupedLots as $locName => $locLots): 
                                // Filter out the "dummy" lots used to create Area labels
                                $validLots = array_filter($locLots, function($l) { 
                                    return !empty($l['block_no']) || !empty($l['lot_no']) || $l['area'] > 0; 
                                });
                                $availLots = array_filter($validLots, function($l) { 
                                    return strtoupper($l['status']) === 'AVAILABLE'; 
                                });
                                $locId = md5($locName);
                            ?>
                            
                            <div class="location-card" style="background: white; border-radius: 12px; margin-bottom: 15px; border: 1px solid var(--gray-border); box-shadow: var(--shadow-sm); overflow: hidden;">
                                <div class="loc-header" onclick="toggleDirectory('<?= $locId ?>')" style="padding: 18px 24px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; background: #f8fafc; transition: background 0.2s;">
                                    <div style="display: flex; align-items: center; gap: 15px;">
                                        <div style="width: 40px; height: 40px; background: #e0f2fe; color: #0284c7; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px;">
                                            <i class="fa-solid fa-map-location"></i>
                                        </div>
                                        <div>
                                            <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: #0f172a;"><?= htmlspecialchars($locName) ?></h3>
                                            <span style="font-size: 12px; color: #64748b; font-weight: 600;"><?= count($availLots) ?> Available out of <?= count($validLots) ?> Total Lots</span>
                                        </div>
                                    </div>
                                    <i class="fa-solid fa-chevron-down dir-icon" id="icon-<?= $locId ?>" style="color: #64748b; transition: transform 0.3s;"></i>
                                </div>
                                
                                <div class="loc-body" id="body-<?= $locId ?>" style="display: none; border-top: 1px solid var(--gray-border);">
                                    <div style="overflow-x: auto;">
                                        <table style="width: 100%; min-width: 800px;">
                                            <thead>
                                                <tr>
                                                    <th>Image</th>
                                                    <th>Property Type</th>
                                                    <th>Block/Lot</th>
                                                    <th>Area</th>
                                                    <th>Total Price</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach($validLots as $lot): ?>
                                                <tr>
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
                                                        <a href="admin.php?view=inventory&edit_id=<?= $lot['id'] ?>" class="btn-action btn-edit"><i class="fa-solid fa-pen" style="margin-right: 4px;"></i> Edit</a>
                                                        <a href="admin.php?delete_id=<?= $lot['id'] ?>" class="btn-action btn-delete" onclick="return confirm('Are you sure you want to delete this property? This action cannot be undone.');"><i class="fa-solid fa-trash" style="margin-right: 4px;"></i> Delete</a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                                
                                                <?php if(count($validLots) == 0): ?>
                                                    <tr>
                                                        <td colspan="7" style="text-align: center; padding: 30px; color: var(--text-muted); font-weight: 500;">
                                                            No properties have been added to this area yet.
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                </div>

                <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
                <script src="https://unpkg.com/leaflet-geosearch@3.11.0/dist/bundle.min.js"></script>
                <script>
                // --- NEW FUNCTION: Hides form if "NEW_AREA" is selected ---
                function checkNewLocation() {
                    var sel = document.getElementById('locationSelect');
                    var inp = document.getElementById('newLocationInput');
                    var wrapper = document.getElementById('lotDetailsWrapper');
                    var entryModeSec = document.getElementById('entryModeSection');
                    var btnText = document.getElementById('submitBtnText');
                    var helperText = document.getElementById('newAreaHelperText');
                    
                    if(sel && sel.value === 'NEW_AREA') {
                        // 1. Show the text input to type the area
                        inp.style.display = 'block';
                        inp.required = true;
                        helperText.style.display = 'block';
                        
                        // 2. Hide everything else
                        if(wrapper) wrapper.style.display = 'none';
                        if(entryModeSec) entryModeSec.style.display = 'none';
                        
                        // 3. Remove 'required' tags so the form can submit without them
                        document.querySelectorAll('.req-field').forEach(el => el.removeAttribute('required'));
                        
                        // 4. Change button text
                        btnText.innerText = "Save New Area Only";
                        
                    } else {
                        // Restore Everything
                        if(inp) {
                            inp.style.display = 'none';
                            inp.required = false;
                        }
                        if(helperText) helperText.style.display = 'none';
                        if(wrapper) wrapper.style.display = 'block';
                        if(entryModeSec) entryModeSec.style.display = 'block';
                        
                        // Restore 'required' fields based on mode
                        document.getElementById('block_no').setAttribute('required', 'true');
                        document.getElementById('area').setAttribute('required', 'true');
                        document.getElementById('base_price').setAttribute('required', 'true');
                        
                        if(document.querySelector('input[name="entry_mode"]:checked') && document.querySelector('input[name="entry_mode"]:checked').value === 'single') {
                            if(document.getElementById('lot_no')) document.getElementById('lot_no').setAttribute('required', 'true');
                        }
                        
                        btnText.innerText = "<?= $edit_mode ? 'Update Property' : 'Save Property' ?>";
                    }
                }

                // Bulk vs Single Toggle Function
                function toggleEntryMode() {
                    const mode = document.querySelector('input[name="entry_mode"]:checked').value;
                    const singleFields = document.querySelectorAll('.single-mode-field');
                    const bulkFields = document.querySelectorAll('.bulk-mode-field');
                    
                    if (mode === 'bulk') {
                        singleFields.forEach(el => { el.style.display = 'none'; });
                        bulkFields.forEach(el => { el.style.display = 'block'; });
                        document.getElementById('lot_no').required = false;
                        document.getElementById('start_lot').required = true;
                        document.getElementById('end_lot').required = true;
                        document.getElementById('submitBtnText').innerText = "Process Bulk Entry";
                    } else {
                        singleFields.forEach(el => { el.style.display = 'block'; });
                        bulkFields.forEach(el => { el.style.display = 'none'; });
                        document.getElementById('lot_no').required = true;
                        document.getElementById('start_lot').required = false;
                        document.getElementById('end_lot').required = false;
                        document.getElementById('submitBtnText').innerText = "Save Single Property";
                    }
                }

                // Accordion Function for Directory
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

                // SIMPLIFIED PRICING LOGIC
                function calcTotal(){
                    let area = parseFloat(document.getElementById('area').value) || 0;
                    let basePrice = parseFloat(document.getElementById('base_price').value) || 0;
                    let terms = parseInt(document.getElementById('terms').value) || 0;
                    
                    let adjustedPriceSqm = basePrice; 
                    let totalPrice = area * adjustedPriceSqm;
                    
                    document.getElementById('price_sqm').value = adjustedPriceSqm.toFixed(2);
                    document.getElementById('total').value = totalPrice.toFixed(2);
                    
                    const fmt = (val) => val.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});

                    document.getElementById('display_price_sqm').innerText = "₱ " + fmt(adjustedPriceSqm);
                    document.getElementById('display_total').innerText = "₱ " + fmt(totalPrice);
                    
                    let moElement = document.getElementById('display_monthly');
                    if (terms === 0 || totalPrice === 0) {
                        moElement.innerText = "Spot Cash";
                    } else {
                        let totalMonths = terms * 12;
                        let monthly = totalPrice / totalMonths;
                        moElement.innerText = "₱ " + fmt(monthly) + " / mo.";
                    }
                }

                document.addEventListener('DOMContentLoaded', function() {
                    calcTotal(); 
                    
                    var initialLat = <?= $edit_mode && !empty($edit_data['latitude']) ? $edit_data['latitude'] : '14.5995' ?>; 
                    var initialLng = <?= $edit_mode && !empty($edit_data['longitude']) ? $edit_data['longitude'] : '120.9842' ?>;
                    
                    var streetLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 });
                    var satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19 });

                    var map = L.map('map', { center: [initialLat, initialLng], zoom: 13, layers: [satelliteLayer] });
                    L.control.layers({"Satellite": satelliteLayer, "Streets": streetLayer}).addTo(map);

                    const provider = new GeoSearch.OpenStreetMapProvider();
                    const searchControl = new GeoSearch.GeoSearchControl({ provider: provider, style: 'bar', showMarker: true });
                    map.addControl(searchControl);

                    var marker;
                    function updatePin(lat, lng) {
                        document.getElementById('lat').value = lat;
                        document.getElementById('lng').value = lng;
                        if (marker) marker.setLatLng([lat, lng]);
                        else marker = L.marker([lat, lng]).addTo(map);
                    }

                    <?php if($edit_mode && !empty($edit_data['latitude'])): ?>
                        marker = L.marker([initialLat, initialLng]).addTo(map);
                    <?php endif; ?>

                    map.on('click', function(e) { updatePin(e.latlng.lat, e.latlng.lng); });
                    map.on('geosearch/showlocation', function(result) { updatePin(result.location.y, result.location.x); });
                });
                </script>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>