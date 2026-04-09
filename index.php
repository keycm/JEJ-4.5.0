<?php
// 1. SAFE REDIRECTS: Start output buffering to prevent "Headers already sent" errors
ob_start(); 

// 2. SAFE SESSIONS: Only start a session if one hasn't been started yet
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. Include your database connection
require_once 'config.php';

// Include PHPMailer files (required for sending OTP)
require_once 'PHPMailer/Exception.php';
require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';

// --- CENTRALIZED AUTHENTICATION LOGIC ---
$auth_message = '';
$auth_status = ''; // 'success' or 'error'
$show_modal = '';  // Determines which modal to keep open ('loginModal', 'registerModal', or 'otpModal')

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // STEP 1: Process Registration Request & Send OTP
    if (isset($_POST['register_request'])) {
        $fullname = trim($_POST['fullname']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        if ($password !== $confirm_password) {
            $auth_message = "Passwords do not match.";
            $auth_status = "error";
            $show_modal = "registerModal";
        } else {
            // Check if email already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            if (!$stmt) {
                $auth_message = "Database Error: " . $conn->error;
                $auth_status = "error";
                $show_modal = "registerModal";
            } else {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->store_result();
                
                if ($stmt->num_rows > 0) {
                    $auth_message = "Email is already registered. Please sign in.";
                    $auth_status = "error";
                    $show_modal = "registerModal";
                } else {
                    // Generate 6-digit OTP
                    $otp = rand(100000, 999999);
                    
                    // Store registration data temporarily in session
                    $_SESSION['temp_reg'] = [
                        'fullname' => $fullname,
                        'phone' => $phone,
                        'email' => $email,
                        'password' => md5($password), // Hashing matching existing DB structure
                        'otp' => $otp
                    ];

                    // Send OTP via Email
                    $mail = new PHPMailer\PHPMailer\PHPMailer();
                    try {
                        $mail->isSMTP();
                        $mail->Host       = 'smtp.gmail.com'; 
                        $mail->SMTPAuth   = true;
                        $mail->Username   = 'publicotavern@gmail.com'; 
                        $mail->Password   = 'xcvgrzzsjvnbtsti';   
                        $mail->SMTPSecure = 'tls'; 
                        $mail->Port       = 587;
                        $mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));

                        $mail->setFrom('publicotavern@gmail.com', 'JEJ Surveying');
                        $mail->addAddress($email); 
                        $mail->isHTML(true);
                        $mail->Subject = 'Your Registration OTP';
                        $mail->Body    = "<h3>Verify Your Account</h3><p>Your OTP for registration is: <b style='font-size:20px; letter-spacing:2px;'>$otp</b></p><p>Please enter this code on the website to complete your registration.</p>";
                        
                        if($mail->send()){
                            $auth_message = "OTP sent to your email. Please verify to continue.";
                            $auth_status = "success";
                            $show_modal = "otpModal"; // Switch to OTP Modal
                        }
                    } catch (Exception $e) {
                        $auth_message = "Failed to send OTP. Please check your internet connection.";
                        $auth_status = "error";
                        $show_modal = "registerModal";
                    }
                }
                $stmt->close();
            }
        }
    } 

    // STEP 2: Verify OTP and Finalize Account Creation
    elseif (isset($_POST['verify_otp'])) {
        $user_otp = trim($_POST['otp_code']);
        
        if (isset($_SESSION['temp_reg']) && $user_otp == $_SESSION['temp_reg']['otp']) {
            $data = $_SESSION['temp_reg'];
            $role = 'BUYER'; // Default role
            
            $insert_stmt = $conn->prepare("INSERT INTO users (fullname, phone, email, password, role) VALUES (?, ?, ?, ?, ?)");
            
            if (!$insert_stmt) {
                $auth_message = "SQL Error: " . $conn->error;
                $auth_status = "error";
                $show_modal = "otpModal";
            } else {
                $insert_stmt->bind_param("sssss", $data['fullname'], $data['phone'], $data['email'], $data['password'], $role);
                
                if ($insert_stmt->execute()) {
                    unset($_SESSION['temp_reg']); // Clear temporary session
                    $auth_message = "Registration successful! You can now log in.";
                    $auth_status = "success";
                    $show_modal = "loginModal"; // Switch to Login Modal
                } else {
                    $auth_message = "Error saving account. Please try again.";
                    $auth_status = "error";
                    $show_modal = "otpModal";
                }
                $insert_stmt->close();
            }
        } else {
            $auth_message = "Invalid OTP code. Please check your email.";
            $auth_status = "error";
            $show_modal = "otpModal";
        }
    }
    
    // STEP 3: Process Login Request
    elseif (isset($_POST['login'])) {
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        $stmt = $conn->prepare("SELECT id, fullname, password, role FROM users WHERE email = ?");
        
        if (!$stmt) {
            $auth_message = "Database Error: " . $conn->error;
            $auth_status = "error";
            $show_modal = "loginModal";
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                if (md5($password) === $row['password']) {
                    // Login Success
                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['fullname'] = $row['fullname'];
                    $_SESSION['role'] = $row['role'];
                    
                    // Refresh the page to clear POST data and close modals
                    header("Location: index.php");
                    exit;
                } else {
                    $auth_message = "Invalid password.";
                    $auth_status = "error";
                    $show_modal = "loginModal";
                }
            } else {
                $auth_message = "No account found with that email.";
                $auth_status = "error";
                $show_modal = "loginModal";
            }
            $stmt->close();
        }
    }
}

// --- FETCH NOTIFICATIONS ---
$unread_count = 0;
if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $notif_stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    if ($notif_stmt) {
        $notif_stmt->bind_param("i", $uid);
        $notif_stmt->execute();
        $notif_stmt->bind_result($unread_count);
        $notif_stmt->fetch();
        $notif_stmt->close();
    }
}

// --- FETCH LOCATIONS FOR DROPDOWN ---
$locations = [];
$loc_result = $conn->query("SELECT DISTINCT location FROM lots WHERE location IS NOT NULL AND location != ''");
if ($loc_result) { while($row = $loc_result->fetch_assoc()) { $locations[$row['location']] = $row['location']; } }
$phase_result = $conn->query("SELECT DISTINCT name FROM phases WHERE name IS NOT NULL AND name != ''");
if ($phase_result) { while($row = $phase_result->fetch_assoc()) { $locations[$row['name']] = $row['name']; } }
sort($locations);

// --- PROCESS SEARCH AND FILTERS ---
$where_clauses = ["1=1"];
$params = [];
$types = "";

if(!empty($_GET['q'])){
    $q = "%" . $_GET['q'] . "%";
    $where_clauses[] = "(l.location LIKE ? OR p.name LIKE ?)";
    $params[] = $q; $params[] = $q;
    $types .= "ss";
}
if(!empty($_GET['status']) && $_GET['status'] != 'ALL'){
    $where_clauses[] = "l.status = ?";
    $params[] = $_GET['status'];
    $types .= "s";
}

$where_sql = implode(" AND ", $where_clauses);
$query = "SELECT l.*, p.name as phase_name 
          FROM lots l 
          LEFT JOIN phases p ON l.phase_id = p.id 
          WHERE $where_sql 
          ORDER BY l.status = 'AVAILABLE' DESC, l.id DESC";

$stmt = $conn->prepare($query);
if(!empty($params)){ $stmt->bind_param($types, ...$params); }
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JEJ Surveying Services | Premium Properties</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root { 
            --primary: #1e4b36; 
            --primary-light: #2d6a4f;
            --accent: #d4af37; /* Gold accent for premium feel */
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
            overflow-x: hidden; /* Prevent horizontal scroll on animations */
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
        
        /* Utility classes for IntersectionObserver */
        .animate-on-scroll {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.8s cubic-bezier(0.16, 1, 0.3, 1), transform 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .animate-on-scroll.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* Staggered load for hero elements */
        .hero-title { animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards; opacity: 0; }
        .hero-subtitle { animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) 0.2s forwards; opacity: 0; }
        .hero-search { animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) 0.4s forwards; opacity: 0; }


        /* Navbar - Glassmorphism */
        .nav {
            position: fixed;
            top: 0; left: 0; right: 0;
            display: flex; justify-content: space-between; align-items: center;
            padding: 15px 5%;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255,255,255,0.3);
            box-shadow: var(--shadow-sm);
            z-index: 100;
            transition: all 0.3s ease;
        }

        /* UPDATED BRANDING LOGO & TEXT */
        .brand-wrapper a {
            display: flex; align-items: center; gap: 12px;
            text-decoration: none; color: var(--primary);
            transition: transform 0.3s ease;
        }
        .brand-wrapper a:hover {
            transform: scale(1.02);
        }
        .brand-logo-img {
            height: 48px;
            width: auto;
            object-fit: contain;
        }
        .brand-text-container {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .nav-brand { 
            font-size: 1.3rem; 
            font-weight: 800; 
            letter-spacing: -0.5px;
            line-height: 1.1;
            color: var(--primary);
        }
        .nav-brand-sub {
            font-size: 0.65rem;
            color: var(--text-gray);
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }
        
        .nav-links { display: flex; gap: 30px; }
        .nav-links a {
            text-decoration: none; color: var(--text-gray);
            font-weight: 600; font-size: 0.95rem;
            transition: color 0.2s; position: relative;
        }
        .nav-links a:hover, .nav-links a.active { color: var(--primary); }
        .nav-links a.active::after {
            content: ''; position: absolute; bottom: -5px; left: 0;
            width: 100%; height: 2px; background: var(--primary); border-radius: 2px;
            animation: fadeIn 0.3s ease;
        }

        /* Buttons */
        .btn-outline {
            background: transparent; border: 2px solid var(--primary);
            color: var(--primary); padding: 10px 24px;
            border-radius: 30px; font-weight: 700; cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-outline:hover { background: var(--primary); color: white; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(30,75,54,0.15);}

        .btn-solid {
            background: var(--primary); color: white; border: none;
            padding: 10px 24px; border-radius: 30px;
            font-weight: 700; cursor: pointer;
            box-shadow: 0 4px 15px rgba(30, 75, 54, 0.2);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .btn-solid::after {
            content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: 0.5s;
        }
        .btn-solid:hover::after { left: 100%; }
        .btn-solid:hover { background: var(--primary-light); transform: translateY(-2px); box-shadow: 0 6px 20px rgba(30, 75, 54, 0.3); }

        /* Hero Section */
        .hero {
            margin-top: 76px; /* Offset for fixed nav */
            height: 60vh; min-height: 450px;
            background: linear-gradient(to right, rgba(15, 23, 42, 0.8), rgba(30, 75, 54, 0.7)), url('assets/login2.JPG') center/cover no-repeat;
            background-color: var(--primary); /* Fallback */
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            color: white; text-align: center; padding: 0 20px;
        }
        
        .hero h1 { font-size: 3.8rem; font-weight: 800; margin-bottom: 15px; letter-spacing: -1px; text-shadow: 0 2px 15px rgba(0,0,0,0.4); }
        .hero p { font-size: 1.2rem; font-weight: 400; opacity: 0.9; margin-bottom: 40px; max-width: 650px; text-shadow: 0 1px 5px rgba(0,0,0,0.3); }

        /* Floating Search Box */
        .search-box {
            background: white; padding: 10px; border-radius: 50px;
            display: flex; align-items: center; gap: 10px;
            width: 100%; max-width: 650px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
        }
        .search-input-group {
            flex: 1; display: flex; align-items: center; gap: 15px;
            padding: 5px 20px; border-right: 1px solid #e2e8f0;
        }
        .search-input-group i { color: var(--primary); font-size: 1.2rem; transition: transform 0.3s; }
        .search-box:focus-within .search-input-group i { transform: scale(1.2); }
        .search-input {
            width: 100%; border: none; outline: none;
            font-size: 1rem; color: var(--text-dark); background: transparent;
            font-family: inherit; font-weight: 500; appearance: none;
            cursor: pointer;
        }
        .btn-search {
            background: var(--primary); color: white; border: none;
            padding: 15px 35px; border-radius: 40px; font-weight: 700;
            font-size: 1rem; cursor: pointer; transition: 0.3s;
            display: flex; align-items: center; gap: 10px;
        }
        .btn-search:hover { background: var(--primary-light); transform: scale(1.05); }

        /* Container & Grid */
        .container { max-width: 1280px; margin: 80px auto; padding: 0 5%; }
        .section-title { 
            font-size: 2.2rem; font-weight: 800; color: var(--text-dark); 
            margin-bottom: 40px; border-left: 6px solid var(--accent); padding-left: 15px; 
        }

        .property-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 35px;
        }

        /* Hidden Card Class for "Load More" Functionality */
        .hidden-card {
            display: none !important;
        }

        /* Property Cards */
        .prop-card {
            background: var(--white); border-radius: 20px;
            overflow: hidden; text-decoration: none; color: inherit;
            box-shadow: var(--shadow-sm); border: 1px solid #f1f5f9;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex; flex-direction: column;
        }
        .prop-card:hover { transform: translateY(-10px); box-shadow: var(--shadow-lg); border-color: transparent; }
        
        .prop-img-box { position: relative; height: 240px; overflow: hidden; }
        .prop-img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.7s cubic-bezier(0.16, 1, 0.3, 1); }
        .prop-card:hover .prop-img { transform: scale(1.08); }
        
        .prop-badge {
            position: absolute; top: 15px; left: 15px;
            padding: 6px 14px; border-radius: 8px;
            font-size: 0.75rem; font-weight: 800; letter-spacing: 0.5px;
            color: white; text-transform: uppercase;
            backdrop-filter: blur(4px); box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .badge-AVAILABLE { background: rgba(16, 185, 129, 0.9); }
        .badge-SOLD { background: rgba(239, 68, 68, 0.9); }
        .badge-RESERVED { background: rgba(245, 158, 11, 0.9); }

        .prop-info { padding: 25px; display: flex; flex-direction: column; flex: 1; background: white; z-index: 2; transition: background 0.3s; }
        .prop-loc { color: var(--text-gray); font-size: 0.85rem; font-weight: 600; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }
        .prop-loc i { color: var(--primary); }
        .prop-title { font-size: 1.25rem; font-weight: 700; color: var(--text-dark); margin-bottom: 15px; line-height: 1.3; transition: color 0.3s; }
        .prop-card:hover .prop-title { color: var(--primary); }
        .prop-footer { margin-top: auto; padding-top: 15px; border-top: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
        .prop-price { font-size: 1.35rem; font-weight: 800; color: var(--primary); }
        .prop-view { color: var(--primary-light); font-weight: 700; font-size: 0.9rem; display: flex; align-items: center; gap: 5px; }
        .prop-view i { transition: transform 0.3s; }
        .prop-card:hover .prop-view i { transform: translateX(5px); }

        /* User Menu & Dropdown */
        .user-menu { display: flex; align-items: center; gap: 15px; }
        .notification-bell { position: relative; color: var(--text-gray); font-size: 22px; text-decoration: none; transition: color 0.3s; }
        .notification-bell:hover { color: var(--primary); transform: scale(1.1); }
        .notification-dot { position: absolute; top: 0; right: -2px; width: 10px; height: 10px; background-color: #ef4444; border-radius: 50%; border: 2px solid white; }
        
        .profile-dropdown-container { position: relative; }
        .profile-trigger {
            display: flex; align-items: center; gap: 12px; background: transparent;
            border: 1px solid #e2e8f0; cursor: pointer; padding: 6px 12px;
            border-radius: 40px; transition: all 0.2s ease;
        }
        .profile-trigger:hover { background: #f8fafc; border-color: #cbd5e1; box-shadow: var(--shadow-sm); }
        .profile-info { text-align: right; }
        .profile-name { display: block; font-weight: 700; font-size: 0.9rem; color: var(--text-dark); font-family: 'Plus Jakarta Sans', sans-serif;}
        .profile-role { display: block; font-size: 0.7rem; color: var(--primary); font-weight: 800; letter-spacing: 0.5px; text-transform: uppercase;}
        .avatar-circle { background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; border-radius: 50%; width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1.1rem; box-shadow: 0 2px 8px rgba(30,75,54,0.3); }
        
        .profile-dropdown-menu {
            display: none; position: absolute; top: 120%; right: 0; background: white;
            min-width: 240px; border-radius: 16px; box-shadow: var(--shadow-lg);
            border: 1px solid #f1f5f9; overflow: hidden; z-index: 100;
        }
        .profile-dropdown-menu.active { display: block; animation: dropIn 0.2s cubic-bezier(0.16, 1, 0.3, 1) forwards; transform-origin: top right; }
        @keyframes dropIn { from { opacity: 0; transform: scale(0.9) translateY(-10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        
        .profile-dropdown-item {
            display: flex; align-items: center; gap: 12px; padding: 15px 20px;
            text-decoration: none; color: var(--text-dark); font-size: 0.95rem; font-weight: 600;
            transition: all 0.2s;
        }
        .profile-dropdown-item:hover { background: #f8fafc; color: var(--primary); padding-left: 25px; }
        .profile-dropdown-item.logout-btn { color: #ef4444; border-top: 1px solid #f1f5f9; }
        .profile-dropdown-item.logout-btn:hover { background: #fef2f2; color: #dc2626; }

        /* Premium Modals */
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(8px);
            z-index: 1000; align-items: center; justify-content: center;
            opacity: 0; transition: opacity 0.3s ease; padding: 20px;
        }
        .modal-overlay.active { display: flex; opacity: 1; }
        .modal-content {
            background: white; display: flex; border-radius: 24px;
            width: 100%; max-width: 1000px; position: relative;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
            transform: translateY(30px) scale(0.95); transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            overflow: hidden; min-height: 550px;
        }
        .modal-overlay.active .modal-content { transform: translateY(0) scale(1); }
        .modal-close {
            position: absolute; top: 20px; right: 25px;
            background: #f1f5f9; border: none; width: 36px; height: 36px;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-size: 16px; cursor: pointer; color: var(--text-gray); z-index: 1001;
            transition: all 0.2s;
        }
        .modal-close:hover { background: #e2e8f0; color: #ef4444; transform: rotate(90deg); }
        
        .modal-left {
            flex: 1; background: linear-gradient(to bottom, rgba(30,75,54,0.4), rgba(15,23,42,0.9)), url('assets/modal-bg.jpg') center/cover;
            padding: 50px; display: flex; flex-direction: column; justify-content: flex-end; color: white;
        }
        .modal-left h3 { font-size: 2rem; font-weight: 800; margin-bottom: 15px; line-height: 1.2; }
        .modal-left p { font-size: 1rem; opacity: 0.9; }
        
        .modal-right { flex: 1.2; padding: 60px 50px; display: flex; flex-direction: column; justify-content: center; background: white; }
        .modal-title { font-size: 2rem; font-weight: 800; color: var(--text-dark); margin-bottom: 8px; letter-spacing: -0.5px; }
        .modal-subtitle { color: var(--text-gray); font-size: 0.95rem; margin-bottom: 35px; }
        
        .modal-form-group { margin-bottom: 20px; position: relative; }
        .modal-form-row { display: flex; gap: 20px; margin-bottom: 20px; }
        .modal-form-row > div { flex: 1; position: relative; }
        
        .modal-form-label { display: block; margin-bottom: 8px; font-size: 0.85rem; font-weight: 700; color: var(--text-dark); text-transform: uppercase; letter-spacing: 0.5px; }
        .modal-input {
            width: 100%; padding: 15px 20px; border: 2px solid #e2e8f0;
            border-radius: 12px; outline: none; background: #f8fafc;
            font-size: 1rem; color: var(--text-dark); font-family: inherit; font-weight: 500;
            transition: all 0.3s;
        }
        .modal-input:focus { border-color: var(--primary); background: white; box-shadow: 0 0 0 4px rgba(30,75,54,0.1); }
        
        .btn-modal-submit {
            background: var(--primary); color: white; border: none;
            padding: 18px; width: 100%; border-radius: 12px;
            font-size: 1.1rem; font-weight: 800; cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 10px;
            margin-top: 10px; transition: all 0.3s; box-shadow: 0 4px 15px rgba(30,75,54,0.2);
        }
        .btn-modal-submit:hover { background: var(--primary-light); transform: translateY(-2px); box-shadow: 0 8px 25px rgba(30,75,54,0.3); }
        
        .modal-footer-text { margin-top: 25px; text-align: center; font-size: 0.95rem; color: var(--text-gray); font-weight: 500; }
        .modal-footer-text a { color: var(--primary); font-weight: 700; text-decoration: none; transition: 0.2s; }
        .modal-footer-text a:hover { text-decoration: underline; }

        /* Alerts */
        .alert-box { padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; font-size: 0.9rem; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .alert-success { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }

        /* Footer */
        .footer { background: var(--text-dark); color: white; text-align: center; padding: 30px; margin-top: 80px; font-size: 0.9rem; opacity: 0.9; }
        
        /* Mobile Responive */
        @media (max-width: 768px) {
            .hero h1 { font-size: 2.5rem; }
            .modal-content { flex-direction: column; max-height: 90vh; overflow-y: auto; }
            .modal-left { min-height: 200px; padding: 30px; }
            .modal-right { padding: 40px 30px; }
            .nav-links.desktop-only { display: none; }
            .search-box { flex-direction: column; border-radius: 20px; padding: 20px; }
            .search-input-group { border-right: none; border-bottom: 1px solid #e2e8f0; padding-bottom: 15px; margin-bottom: 15px; }
            .btn-search { width: 100%; justify-content: center; }
            .brand-text-container { display: none; } /* Hide text on mobile, show only logo icon to save space */
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
            <a href="index.php" class="active">Properties</a>
            <a href="contact.php">Contact Us</a>
        </div>

        <div class="user-menu">
            <?php if(isset($_SESSION['user_id'])): ?>
                <a href="notifications.php" class="notification-bell">
                    <i class="fa-regular fa-bell"></i>
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
            <?php else: ?>
                <button class="btn-outline desktop-only" onclick="openModal('loginModal')">Sign In</button>
                <button class="btn-solid" onclick="openModal('registerModal')">Register Now</button>
            <?php endif; ?>
        </div>
    </nav>

    <header class="hero">
        <h1 class="hero-title">Find Your Perfect Space</h1>
        <p class="hero-subtitle">Discover sustainable lots, premium real estate, and build your future home surrounded by nature.</p>
        
        <form class="search-box hero-search" method="GET" action="index.php">
            <?php if(!empty($_GET['status'])): ?>
                <input type="hidden" name="status" value="<?= htmlspecialchars($_GET['status']) ?>">
            <?php endif; ?>
            <div class="search-input-group">
                <i class="fa-solid fa-location-dot"></i>
                <select name="q" class="search-input">
                    <option value="">Search by Location or Phase...</option>
                    <?php foreach($locations as $loc): ?>
                        <option value="<?= htmlspecialchars($loc) ?>" <?= (isset($_GET['q']) && $_GET['q'] == $loc) ? 'selected' : '' ?>><?= htmlspecialchars($loc) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-search">Find Property</button>
        </form>
    </header>

    <div class="container" id="results">
        <h2 class="section-title animate-on-scroll"><?= !empty($_GET['q']) ? 'Search Results' : 'Exclusive Properties' ?></h2>
        
        <div class="property-grid">
            <?php if($result && $result->num_rows > 0): ?>
                <?php 
                $card_count = 0; 
                while($row = $result->fetch_assoc()): 
                    $card_count++;
                    $hidden_class = ($card_count > 6) ? 'hidden-card' : '';
                ?>
                
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="lot_details.php?id=<?= urlencode($row['id']) ?>" class="prop-card animate-on-scroll <?= $hidden_class ?>">
                <?php else: ?>
                    <a href="#" onclick="event.preventDefault(); openModal('loginModal')" class="prop-card animate-on-scroll <?= $hidden_class ?>">
                <?php endif; ?>
                
                    <div class="prop-img-box">
                        <img src="<?= !empty($row['lot_image']) ? 'uploads/'.htmlspecialchars($row['lot_image']) : 'assets/default_lot.jpg' ?>" class="prop-img" alt="Lot Image">
                        <span class="prop-badge badge-<?= htmlspecialchars($row['status']) ?>"><?= htmlspecialchars($row['status']) ?></span>
                    </div>
                    <div class="prop-info">
                        <div class="prop-loc"><i class="fa-solid fa-map-pin"></i> <?= htmlspecialchars($row['location'] ?: $row['phase_name']) ?></div>
                        <h3 class="prop-title">Block <?= htmlspecialchars($row['block_no']) ?>, Lot <?= htmlspecialchars($row['lot_no']) ?></h3>
                        <div class="prop-footer">
                            <div class="prop-price">₱<?= number_format($row['total_price']) ?></div>
                            <div class="prop-view">View Details <i class="fa-solid fa-arrow-right"></i></div>
                        </div>
                    </div>
                </a>
                <?php endwhile; ?>
                
            <?php else: ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 80px 20px; background: white; border-radius: 20px; box-shadow: var(--shadow-sm);" class="animate-on-scroll">
                    <i class="fa-solid fa-magnifying-glass" style="font-size: 3rem; color: #cbd5e1; margin-bottom: 15px;"></i>
                    <h3 style="font-size: 1.5rem; color: var(--text-dark); margin-bottom: 10px;">No properties found</h3>
                    <p style="color: var(--text-gray);">Try adjusting your search filters to find what you're looking for.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if($result && $result->num_rows > 6): ?>
            <div id="viewMoreContainer" style="text-align: center; margin-top: 50px;" class="animate-on-scroll">
                <button id="btnViewMore" class="btn-outline" style="padding: 15px 40px; font-size: 1.05rem;">
                    View More Properties <i class="fa-solid fa-angle-down" style="margin-left: 8px;"></i>
                </button>
            </div>
        <?php endif; ?>
    </div>

    <div class="modal-overlay" id="loginModal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('loginModal')"><i class="fa-solid fa-xmark"></i></button>
            <div class="modal-left">
                <h3>Welcome Back</h3>
                <p>Log in to manage your reservations, track payments, and explore new properties.</p>
            </div>
            <div class="modal-right">
                <h2 class="modal-title">Sign In</h2>
                <p class="modal-subtitle">Access your JEJ Surveying account.</p>
                
                <?php if ($show_modal == 'loginModal' && !empty($auth_message)): ?>
                    <div class="alert-box alert-<?= $auth_status ?>">
                        <i class="fa-solid <?= $auth_status == 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
                        <?= htmlspecialchars($auth_message) ?>
                    </div>
                <?php endif; ?>
                
                <form action="index.php" method="POST">
                    <div class="modal-form-group">
                        <label class="modal-form-label">Email Address</label>
                        <input type="email" name="email" class="modal-input" placeholder="Enter your email" value="<?= (isset($_POST['login']) && isset($_POST['email'])) ? htmlspecialchars($_POST['email']) : '' ?>" required>
                    </div>
                    <div class="modal-form-group">
                        <label class="modal-form-label">Password</label>
                        <input type="password" name="password" class="modal-input" placeholder="Enter your password" required>
                    </div>
                    <button type="submit" name="login" class="btn-modal-submit">Sign In <i class="fa-solid fa-arrow-right"></i></button>
                </form>
                
                <div class="modal-footer-text">
                    New to JEJ Surveying? <a href="#" onclick="event.preventDefault(); switchModal('loginModal', 'registerModal')">Create an account</a>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="registerModal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('registerModal')"><i class="fa-solid fa-xmark"></i></button>
            <div class="modal-left">
                <h3>Start Your Journey</h3>
                <p>Create an account today to reserve your dream property and secure your future.</p>
            </div>
            <div class="modal-right">
                <h2 class="modal-title">Create Account</h2>
                <p class="modal-subtitle">We'll send a secure OTP to verify your email.</p>
                
                <?php if ($show_modal == 'registerModal' && !empty($auth_message)): ?>
                    <div class="alert-box alert-<?= $auth_status ?>">
                        <i class="fa-solid <?= $auth_status == 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
                        <?= htmlspecialchars($auth_message) ?>
                    </div>
                <?php endif; ?>
                
                <form action="index.php" method="POST" onsubmit="return validatePassword()">
                    <div class="modal-form-row">
                        <div>
                            <label class="modal-form-label">Full Name</label>
                            <input type="text" name="fullname" class="modal-input" placeholder="John Doe" value="<?= isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : '' ?>" required>
                        </div>
                        <div>
                            <label class="modal-form-label">Phone Number</label>
                            <input type="text" name="phone" class="modal-input" placeholder="09XX XXX XXXX" value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>" required>
                        </div>
                    </div>
                    <div class="modal-form-group">
                        <label class="modal-form-label">Email Address</label>
                        <input type="email" name="email" class="modal-input" placeholder="john@example.com" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required>
                    </div>
                    <div class="modal-form-row">
                        <div>
                            <label class="modal-form-label">Password</label>
                            <input type="password" name="password" id="reg_pass" class="modal-input" placeholder="••••••••" required>
                        </div>
                        <div>
                            <label class="modal-form-label">Confirm Password</label>
                            <input type="password" name="confirm_password" id="reg_confirm" class="modal-input" placeholder="••••••••" required>
                        </div>
                    </div>
                    <button type="submit" name="register_request" class="btn-modal-submit">Verify Email <i class="fa-solid fa-envelope-circle-check"></i></button>
                </form>
                
                <div class="modal-footer-text">
                    Already have an account? <a href="#" onclick="event.preventDefault(); switchModal('registerModal', 'loginModal')">Sign in here</a>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="otpModal">
        <div class="modal-content" style="max-width: 550px; min-height: 400px;">
            <button class="modal-close" onclick="closeModal('otpModal')"><i class="fa-solid fa-xmark"></i></button>
            <div class="modal-right" style="padding: 50px;">
                <div style="text-align: center; margin-bottom: 25px;">
                    <i class="fa-solid fa-shield-halved" style="font-size: 3rem; color: var(--primary); margin-bottom: 15px;"></i>
                    <h2 class="modal-title" style="text-align: center;">Verify Email</h2>
                    <p class="modal-subtitle" style="text-align: center;">Enter the 6-digit code sent to your email.</p>
                </div>
                
                <?php if ($show_modal == 'otpModal' && !empty($auth_message)): ?>
                    <div class="alert-box alert-<?= $auth_status ?>">
                        <i class="fa-solid <?= $auth_status == 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
                        <?= htmlspecialchars($auth_message) ?>
                    </div>
                <?php endif; ?>
                
                <form action="index.php" method="POST">
                    <div class="modal-form-group">
                        <input type="text" name="otp_code" class="modal-input" placeholder="000000" maxlength="6" style="text-align: center; letter-spacing: 10px; font-size: 28px; padding: 20px; font-weight: 800; color: var(--primary);" required autocomplete="off">
                    </div>
                    <button type="submit" name="verify_otp" class="btn-modal-submit">Complete Registration <i class="fa-solid fa-check"></i></button>
                </form>
                
                <div class="modal-footer-text">
                    Didn't receive the code? <a href="#" onclick="event.preventDefault(); switchModal('otpModal', 'registerModal')">Start over</a>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer">
        <p>&copy; <?= date('Y') ?> JEJ Surveying Services. All Rights Reserved. Built with trust and excellence.</p>
    </footer>

    <script>
        // Form Validation
        function validatePassword() {
            var pass = document.getElementById('reg_pass').value;
            var confirm = document.getElementById('reg_confirm').value;
            if(pass !== confirm) {
                alert("Passwords do not match. Please try again.");
                return false;
            }
            return true;
        }

        // Modal Logic
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
            document.body.style.overflow = 'auto';
        }
        
        function switchModal(closeId, openId) {
            document.getElementById(closeId).classList.remove('active');
            setTimeout(() => {
                document.getElementById(openId).classList.add('active');
            }, 300);
        }

        // Close modal if user clicks outside of the white box
        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.classList.remove('active');
                document.body.style.overflow = 'auto';
            }
        });

        document.addEventListener("DOMContentLoaded", function() {
            // PHP will inject the specific modal ID to open if there was an error/success
            <?php if (!empty($show_modal)): ?>
                openModal('<?= htmlspecialchars($show_modal) ?>');
            <?php endif; ?>

            // Profile Dropdown Setup
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

            // View More Properties Logic
            const btnViewMore = document.getElementById('btnViewMore');
            if (btnViewMore) {
                btnViewMore.addEventListener('click', function() {
                    const hiddenCards = document.querySelectorAll('.hidden-card');
                    
                    // Reveal hidden cards and trigger their animation
                    hiddenCards.forEach((card, index) => {
                        card.classList.remove('hidden-card');
                        // Small delay to make them stagger in smoothly
                        setTimeout(() => {
                            card.classList.add('visible');
                        }, index * 100); 
                    });
                    
                    // Hide the "View More" button container once clicked
                    document.getElementById('viewMoreContainer').style.display = 'none';
                });
            }

            // Smooth Scroll Animation Observer (IntersectionObserver)
            const observerOptions = {
                threshold: 0.1,
                rootMargin: "0px 0px -50px 0px"
            };

            const observer = new IntersectionObserver(function(entries, observer) {
                entries.forEach(entry => {
                    // Only trigger if it is intersecting AND it's not currently hidden by the 'Load More' logic
                    if (entry.isIntersecting && !entry.target.classList.contains('hidden-card')) {
                        entry.target.classList.add('visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);

            document.querySelectorAll('.animate-on-scroll').forEach(el => {
                observer.observe(el);
            });
        });
    </script>
</body>
</html>
<?php 
// End and flush output buffer properly
ob_end_flush(); 
?>