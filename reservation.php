<?php
// reservation.php
include 'config.php';

// Security Check
if(!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['SUPER ADMIN', 'ADMIN', 'MANAGER'])){
    header("Location: login.php");
    exit();
}

// --- EDITABLE DP APPROVAL ACTION ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['approve_with_dp'])) {
    $res_id = intval($_POST['res_id']);
    $lot_id = intval($_POST['lot_id']);
    $required_dp = floatval($_POST['required_dp']);
    
    // Update to Approved with custom DP
    $conn->query("UPDATE reservations SET status = 'APPROVED', required_dp = $required_dp WHERE id = $res_id");
    
    // Notify Buyer
    $r = $conn->query("SELECT user_id FROM reservations WHERE id = $res_id")->fetch_assoc();
    if ($r) {
        $uid = $r['user_id'];
        $msg = "Your reservation has been approved. Your Required Down Payment is ₱" . number_format($required_dp, 2) . ". Please settle it online to secure your property.";
        $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, 'Reservation Approved', ?)");
        $notif_stmt->bind_param("is", $uid, $msg);
        $notif_stmt->execute();
    }
    
    header("Location: reservation.php?msg=approved");
    exit();
}

// --- VERIFY DOWN PAYMENT ACTION ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_dp'])) {
    $res_id = intval($_POST['res_id']);
    
    // Update the DP status to PAID
    $conn->query("UPDATE reservations SET dp_status = 'PAID' WHERE id = $res_id");
    
    // Notify the buyer
    $r = $conn->query("SELECT user_id FROM reservations WHERE id = $res_id")->fetch_assoc();
    if ($r) {
        $uid = $r['user_id'];
        $notif_title = "Down Payment Verified";
        $notif_msg = "Your online GCash/Maya down payment has been successfully verified by our admin. Thank you!";
        $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
        $notif_stmt->bind_param("iss", $uid, $notif_title, $notif_msg);
        $notif_stmt->execute();
    }
    
    header("Location: reservation.php?msg=dp_verified");
    exit();
}

// Notification Check
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

$status_filter = $_GET['status'] ?? 'ALL';
$where_sql = "1";
if($status_filter != 'ALL'){
    $where_sql = "r.status = '$status_filter'";
}

$alert_msg = "";
$alert_type = "";
if(isset($_GET['msg'])){
    if($_GET['msg'] == 'approved') { $alert_msg = "Reservation approved with updated Down Payment Requirements!"; $alert_type = "success"; }
    if($_GET['msg'] == 'rejected') { $alert_msg = "Reservation rejected. Lot returned to inventory."; $alert_type = "error"; }
    if($_GET['msg'] == 'dp_verified') { $alert_msg = "Down Payment Receipt Verified and Marked as Paid!"; $alert_type = "success"; }
}

// Fetch Reservations (Using r.* fetches all columns like valid_id_file, selfie_with_id, buyer_address, agent)
$query = "SELECT r.*, u.fullname, u.email as user_email, l.block_no, l.lot_no, l.total_price, l.location 
          FROM reservations r 
          JOIN users u ON r.user_id = u.id 
          JOIN lots l ON r.lot_id = l.id 
          WHERE $where_sql 
          ORDER BY r.reservation_date DESC";

$res = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Reservations | JEJ Admin</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
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
        .sidebar { width: 260px; background: #ffffff; border-right: 1px solid var(--gray-border); display: flex; flex-direction: column; position: fixed; height: 100vh; z-index: 100; box-shadow: var(--shadow-sm); }
        .brand-box { padding: 25px; border-bottom: 1px solid var(--gray-border); display: flex; align-items: center; gap: 12px; }
        .sidebar-menu { padding: 20px 15px; flex: 1; overflow-y: auto; }
        .menu-link { display: flex; align-items: center; gap: 12px; padding: 12px 18px; color: #455a64; text-decoration: none; font-weight: 500; font-size: 14px; border-radius: 10px; margin-bottom: 6px; transition: all 0.2s ease; }
        .menu-link:hover { background: var(--gray-light); color: var(--primary); }
        .menu-link.active { background: var(--primary-light); color: var(--primary); font-weight: 600; border-left: 4px solid var(--primary); }
        .menu-link i { width: 20px; text-align: center; font-size: 16px; opacity: 0.8; }
        
        .main-panel { margin-left: 260px; flex: 1; padding: 0; width: calc(100% - 260px); display: flex; flex-direction: column; }
        .top-header { display: flex; justify-content: space-between; align-items: center; background: #ffffff; padding: 20px 40px; border-bottom: 1px solid var(--gray-border); box-shadow: var(--shadow-sm); z-index: 50; }
        .header-title h1 { font-size: 22px; font-weight: 800; color: var(--dark); margin: 0 0 4px 0; letter-spacing: -0.5px;}
        .header-title p { color: var(--text-muted); font-size: 13px; margin: 0; }
        
        .content-area { padding: 35px 40px; flex: 1; }

        .table-container { background: white; border-radius: 16px; border: 1px solid var(--gray-border); box-shadow: var(--shadow-sm); overflow: hidden; margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 16px 24px; font-size: 12px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; background: var(--gray-light); border-bottom: 1px solid var(--gray-border); letter-spacing: 0.5px;}
        td { padding: 16px 24px; border-bottom: 1px solid var(--gray-border); color: #37474f; font-size: 14px; vertical-align: top; }
        tr:hover td { background: #fdfdfd; }
        tr:last-child td { border-bottom: none; }

        .tabs { display: flex; gap: 12px; margin-bottom: 25px; flex-wrap: wrap;}
        .tab-link { padding: 10px 20px; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; color: #455a64; background: white; border: 1px solid var(--gray-border); transition: 0.2s; box-shadow: var(--shadow-sm);}
        .tab-link.active { background: var(--primary); color: white; border-color: var(--primary); box-shadow: 0 4px 10px rgba(46, 125, 50, 0.2); }
        
        .status-badge { padding: 6px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; letter-spacing: 0.3px; display: inline-block;}
        .status-PENDING { background: #fff3e0; color: #e65100; } 
        .status-APPROVED { background: #e8f5e9; color: #2e7d32; } 
        .status-REJECTED { background: #ffebee; color: #c62828; } 

        .btn-doc { display: inline-flex; align-items: center; gap: 8px; padding: 8px 12px; background: #f8fafc; color: #334155; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; font-weight: 600; text-decoration: none; width: 100%; cursor: pointer; transition: 0.2s; box-sizing: border-box; justify-content: center;}
        .btn-doc:hover { background: #e0f2fe; color: #0284c7; border-color: #bae6fd; }

        .action-forms { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .btn-action { padding: 8px 12px; border:none; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; color: white; display: inline-flex; align-items: center; gap: 5px; transition: 0.2s; box-shadow: var(--shadow-sm);}
        
        .btn-approve { background: #10b981; } .btn-approve:hover { background: #059669; transform: translateY(-1px); } 
        .btn-reject { background: #ef4444; } .btn-reject:hover { background: #dc2626; transform: translateY(-1px); } 
        .btn-receipt { background: #64748b; color: white; text-decoration:none; } .btn-terms { background: #3b82f6; color: white; text-decoration:none; } 
        .btn-verify-dp { background: #0284c7; } 

        /* Modals */
        .doc-modal { display: none; position: fixed; z-index: 2000; inset: 0; background-color: rgba(0,0,0,0.85); backdrop-filter: blur(3px); align-items: center; justify-content: center; }
        .doc-modal img { max-width: 90%; max-height: 90vh; border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); object-fit: contain; }
        .doc-close { position: absolute; top: 20px; right: 30px; color: white; font-size: 40px; cursor: pointer; transition: 0.2s; }
        .doc-close:hover { color: #d84315; transform: scale(1.1); }
        .verify-modal-content { display: flex; flex-direction: column; align-items: center; gap: 15px; }
        .verify-card { background: white; padding: 25px; border-radius: 12px; width: 100%; max-width: 400px; text-align: center; }
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
            <a href="reservation.php" class="menu-link active"><i class="fa-solid fa-file-signature"></i> Reservations</a>
            <a href="master_list.php" class="menu-link"><i class="fa-solid fa-map-location-dot"></i> Master List / Map</a>
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
                <h1>Reservation Management</h1>
                <p>Review reservations, verify documents, and process down payments.</p>
            </div>
        </div>

        <div class="content-area">

            <?php if($alert_msg): ?>
                <div style="padding: 16px 20px; border-radius: 10px; margin-bottom: 25px; font-weight: 500; font-size: 14px; background: <?= $alert_type=='success' ? '#e8f5e9' : '#fbe9e7' ?>; color: <?= $alert_type=='success' ? '#2e7d32' : '#d84315' ?>; border: 1px solid <?= $alert_type=='success' ? '#c8e6c9' : '#ffccbc' ?>; box-shadow: var(--shadow-sm);">
                    <i class="fa-solid <?= $alert_type=='success'?'fa-check-circle':'fa-exclamation-circle' ?>" style="margin-right: 10px;"></i>
                    <?= $alert_msg ?>
                </div>
            <?php endif; ?>

            <div class="tabs">
                <a href="reservation.php?status=ALL" class="tab-link <?= $status_filter=='ALL'?'active':'' ?>">All Requests</a>
                <a href="reservation.php?status=PENDING" class="tab-link <?= $status_filter=='PENDING'?'active':'' ?>">Pending</a>
                <a href="reservation.php?status=APPROVED" class="tab-link <?= $status_filter=='APPROVED'?'active':'' ?>">Approved</a>
                <a href="reservation.php?status=REJECTED" class="tab-link <?= $status_filter=='REJECTED'?'active':'' ?>">Rejected</a>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 25%;">Buyer Information</th>
                            <th style="width: 20%;">Property Details</th>
                            <th style="width: 20%;">Submitted Documents</th>
                            <th style="width: 10%;">Status</th>
                            <th style="width: 25%;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($res && $res->num_rows > 0): ?>
                            <?php while($row = $res->fetch_assoc()): 
                                $dp_status = isset($row['dp_status']) ? $row['dp_status'] : 'UNPAID';
                            ?>
                            <tr style="<?= ($dp_status == 'VERIFYING') ? 'background-color: #f0f9ff;' : '' ?>">
                                <td>
                                    <div style="font-weight: 700; color: #263238; margin-bottom: 8px; font-size: 14px;">
                                        <?= htmlspecialchars($row['fullname']) ?>
                                    </div>
                                    <div style="font-size:12px; color:#546e7a; margin-bottom: 4px; display: flex; gap: 8px;">
                                        <i class="fa-solid fa-phone" style="color:#90a4ae; margin-top: 2px;"></i> 
                                        <span><?= htmlspecialchars(isset($row['contact_number']) && !empty($row['contact_number']) ? $row['contact_number'] : 'N/A') ?></span>
                                    </div>
                                    <div style="font-size:12px; color:#546e7a; margin-bottom: 4px; display: flex; gap: 8px;">
                                        <i class="fa-solid fa-envelope" style="color:#90a4ae; margin-top: 2px;"></i> 
                                        <span><?= htmlspecialchars($row['email'] ?? $row['user_email']) ?></span>
                                    </div>
                                    
                                    <div style="font-size:12px; color:#546e7a; margin-bottom: 4px; display: flex; gap: 8px;">
                                        <i class="fa-solid fa-house" style="color:#90a4ae; margin-top: 2px;"></i> 
                                        <span><?= htmlspecialchars(isset($row['buyer_address']) && !empty($row['buyer_address']) ? $row['buyer_address'] : 'Address Not Provided') ?></span>
                                    </div>

                                    <div style="font-size:12px; color:#546e7a; margin-bottom: 4px; display: flex; gap: 8px; background: #f8fafc; padding: 4px 6px; border-radius: 4px; border: 1px solid #e2e8f0; display: inline-flex; margin-top: 4px;">
                                        <i class="fa-solid fa-user-tie" style="color:var(--primary); margin-top: 2px;"></i> 
                                        <strong>Agent:</strong> 
                                        <span><?= htmlspecialchars(isset($row['agent']) && !empty($row['agent']) ? $row['agent'] : 'None') ?></span>
                                    </div>
                                </td>

                                <td>
                                    <div style="font-weight: 700; color: var(--primary);">Block <?= htmlspecialchars($row['block_no']) ?>, Lot <?= htmlspecialchars($row['lot_no']) ?></div>
                                    <div style="font-weight: 700; font-size: 13px; margin-top: 6px; color: #263238;">₱<?= number_format($row['total_price']) ?></div>
                                </td>

                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 6px; max-width: 150px;">
                                        <?php if(!empty($row['payment_proof'])): ?>
                                            <button class="btn-doc" onclick="showDoc('uploads/<?= htmlspecialchars($row['payment_proof']) ?>')" title="View Proof of Payment">
                                                <i class="fa-solid fa-receipt" style="color: #0284c7;"></i> Fee Proof
                                            </button>
                                        <?php endif; ?>

                                        <?php if(!empty($row['valid_id_file'])): ?>
                                            <button class="btn-doc" onclick="showDoc('uploads/<?= htmlspecialchars($row['valid_id_file']) ?>')" title="View Valid ID">
                                                <i class="fa-solid fa-id-card" style="color: #d97706;"></i> Valid ID
                                            </button>
                                        <?php endif; ?>

                                        <?php if(!empty($row['selfie_with_id'])): ?>
                                            <button class="btn-doc" onclick="showDoc('uploads/<?= htmlspecialchars($row['selfie_with_id']) ?>')" title="View Selfie with ID">
                                                <i class="fa-solid fa-camera-retro" style="color: #16a34a;"></i> Selfie w/ ID
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if(empty($row['payment_proof']) && empty($row['valid_id_file']) && empty($row['selfie_with_id'])): ?>
                                            <span style="font-size: 11px; color: #94a3b8; font-style: italic;">No Documents</span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td>
                                    <span class="status-badge status-<?= htmlspecialchars($row['status']) ?>" style="margin-bottom:5px; display:inline-block;"><?= htmlspecialchars($row['status']) ?></span>
                                    <br>
                                    <?php if($dp_status == 'VERIFYING'): ?>
                                        <span style="font-size:10px; font-weight:700; color:#0284c7; background:#e0f2fe; padding:3px 8px; border-radius:4px;"><i class="fa-solid fa-arrows-rotate fa-spin"></i> DP Verification</span>
                                    <?php elseif($dp_status == 'PAID'): ?>
                                        <span style="font-size:10px; font-weight:700; color:#16a34a; background:#dcfce7; padding:3px 8px; border-radius:4px;"><i class="fa-solid fa-check"></i> DP Paid</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <div class="action-forms">
                                        
                                        <?php if($dp_status == 'VERIFYING'): ?>
                                            <button class="btn-action btn-verify-dp" onclick="showVerifyModal('uploads/<?= htmlspecialchars($row['dp_proof'] ?? '') ?>', <?= $row['id'] ?>)">
                                                <i class="fa-solid fa-qrcode"></i> Verify QR Payment
                                            </button>
                                        <?php endif; ?>

                                        <?php if($row['status'] == 'APPROVED'): ?>
                                            <a href="receipt.php?id=<?= $row['id'] ?>" target="_blank" class="btn-action btn-receipt">
                                                <i class="fa-solid fa-print"></i> Receipt
                                            </a>
                                            <a href="payment_terms.php?res_id=<?= $row['id'] ?>" class="btn-action btn-terms">
                                                <i class="fa-solid fa-calculator"></i> Terms
                                            </a>
                                        <?php endif; ?>

                                        <?php if($row['status'] == 'PENDING'): ?>
                                            <button type="button" class="btn-action btn-approve" onclick="showApproveModal(<?= $row['id'] ?>, <?= $row['lot_id'] ?>, <?= $row['total_price'] * 0.20 ?>)" title="Approve">
                                                <i class="fa-solid fa-check"></i> Approve
                                            </button>

                                            <form action="actions.php" method="POST" style="margin: 0;" onsubmit="return confirm('Reject this reservation?')">
                                                <input type="hidden" name="action" value="reject_res">
                                                <input type="hidden" name="res_id" value="<?= $row['id'] ?>">
                                                <button class="btn-action btn-reject" title="Reject"><i class="fa-solid fa-xmark"></i> Reject</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align: center; padding: 50px; color: var(--text-muted);">
                                <i class="fa-solid fa-folder-open" style="font-size: 34px; margin-bottom: 15px; display: block; color: #cfd8dc;"></i>
                                <span style="font-weight: 500;">No reservations found matching this filter.</span>
                            </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
        </div>
    </div>

    <div id="docModal" class="doc-modal" onclick="closeDoc()">
        <span class="doc-close">&times;</span>
        <img id="docImage" src="">
    </div>

    <div id="verifyModal" class="doc-modal">
        <span class="doc-close" onclick="closeVerify()">&times;</span>
        <div class="verify-modal-content" onclick="event.stopPropagation()">
            <img id="verifyImage" src="" style="max-height: 65vh; border: 3px solid white;">
            
            <form action="reservation.php" method="POST" class="verify-card">
                <input type="hidden" name="res_id" id="verifyResId">
                <h3 style="margin: 0 0 5px 0; color:#1e293b; font-family:'Inter', sans-serif;">Verify QR Down Payment</h3>
                <p style="font-size: 13px; color:#64748b; margin-bottom: 20px;">Review the receipt above carefully. Once verified, the buyer will be notified that their down payment is fully paid.</p>
                
                <button type="submit" name="verify_dp" class="btn-action btn-approve" style="width: 100%; padding: 14px; font-size: 15px; justify-content: center;">
                    <i class="fa-solid fa-check-double"></i> Mark DP as PAID
                </button>
            </form>
        </div>
    </div>

    <div id="approveModal" class="doc-modal">
        <span class="doc-close" onclick="closeApprove()">&times;</span>
        <div class="verify-modal-content" onclick="event.stopPropagation()">
            <form action="reservation.php" method="POST" class="verify-card">
                <input type="hidden" name="res_id" id="approveResId">
                <input type="hidden" name="lot_id" id="approveLotId">
                
                <h3 style="margin: 0 0 5px 0; color:#1e293b; font-family:'Inter', sans-serif;">Approve Reservation</h3>
                <p style="font-size: 13px; color:#64748b; margin-bottom: 20px;">Set the required down payment to finalize this reservation approval.</p>
                
                <div style="text-align: left; margin-bottom: 20px;">
                    <label style="font-size: 13px; font-weight: 600; color: #455a64; display: block; margin-bottom: 5px;">Required Down Payment (₱)</label>
                    <input type="number" step="0.01" name="required_dp" id="approveDpAmount" required style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 16px; font-weight: 700; color: var(--primary);">
                </div>

                <button type="submit" name="approve_with_dp" class="btn-action btn-approve" style="width: 100%; padding: 14px; font-size: 15px; justify-content: center;">
                    <i class="fa-solid fa-check-double"></i> Confirm & Approve
                </button>
            </form>
        </div>
    </div>

    <script>
        function showDoc(src) {
            document.getElementById('docImage').src = src;
            document.getElementById('docModal').style.display = 'flex';
        }
        function closeDoc() {
            document.getElementById('docModal').style.display = 'none';
        }

        function showVerifyModal(src, resId) {
            document.getElementById('verifyImage').src = src;
            document.getElementById('verifyResId').value = resId;
            document.getElementById('verifyModal').style.display = 'flex';
        }
        function closeVerify() {
            document.getElementById('verifyModal').style.display = 'none';
        }

        function showApproveModal(resId, lotId, defaultDpAmount) {
            document.getElementById('approveResId').value = resId;
            document.getElementById('approveLotId').value = lotId;
            document.getElementById('approveDpAmount').value = defaultDpAmount.toFixed(2);
            document.getElementById('approveModal').style.display = 'flex';
        }
        function closeApprove() {
            document.getElementById('approveModal').style.display = 'none';
        }

        document.addEventListener('keydown', function(e){
            if(e.key === "Escape") { closeDoc(); closeVerify(); closeApprove(); }
        });
    </script>

</body>
</html>
