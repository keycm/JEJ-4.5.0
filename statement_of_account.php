<?php
// statement_of_account.php
include 'config.php';

if(!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['SUPER ADMIN', 'ADMIN', 'MANAGER'])){
    die("<div style='color:red; text-align:center; padding:20px;'>Unauthorized access.</div>");
}

if(!isset($_GET['res_id'])) die("<div style='color:red; text-align:center; padding:20px;'>Invalid Request.</div>");
$res_id = (int)$_GET['res_id'];

// 1. Fetch Reservation Data (FIXED SQL QUERY: Changed u.contact_number to u.phone, removed strict u.address to avoid crashes)
$query = "SELECT r.*, u.fullname, u.email, u.phone, l.block_no, l.lot_no, l.total_price, l.area 
          FROM reservations r 
          JOIN users u ON r.user_id = u.id 
          JOIN lots l ON r.lot_id = l.id 
          WHERE r.id = $res_id";
$resData = $conn->query($query)->fetch_assoc();

if(!$resData) die("<div style='color:red; text-align:center; padding:20px;'>Reservation not found.</div>");

$tcp = $resData['total_price'];
$dp_required = $tcp * 0.20;
$balance_to_amortize = $tcp - $dp_required;

// 2. Fetch Payments (Down Payments & Amortizations) strictly based on Description
$transactions = [];
$total_dp_paid = 0;
$total_amort_paid = 0;

// Fetch all income records containing this Res# in the description safely
$desc_filter = "%Res#$res_id%";
$tx_query = $conn->prepare("SELECT * FROM transactions WHERE type='INCOME' AND description LIKE ? ORDER BY transaction_date ASC");
$tx_query->bind_param("s", $desc_filter);
$tx_query->execute();
$tx_result = $tx_query->get_result();

while($t = $tx_result->fetch_assoc()){
    $transactions[] = $t;
    // Categorize based on keywords in the description
    if(stripos($t['description'], 'Down Payment') !== false || stripos($t['description'], 'DP') !== false){
        $total_dp_paid += floatval($t['amount']);
    } else if (stripos($t['description'], 'Amortization') !== false || stripos($t['description'], 'Monthly') !== false) {
        $total_amort_paid += floatval($t['amount']);
    } else {
        // If it doesn't say DP or Amortization, default to Amortization if DP is already fully paid
        if ($total_dp_paid >= $dp_required) {
            $total_amort_paid += floatval($t['amount']);
        } else {
            $total_dp_paid += floatval($t['amount']);
        }
    }
}

// 3. Determine Payment Terms
$years = isset($resData['terms']) ? (int)$resData['terms'] : 2; 
if ($years == 0) $years = 1; 

$total_months = $years * 12;
$monthly_payment = $total_months > 0 ? ($balance_to_amortize / $total_months) : 0;

?>

<div style="font-family: 'Inter', sans-serif; color: #334155;">
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 20px; border-radius: 12px;">
            <h4 style="margin: 0 0 15px; color: #0f172a; font-size: 15px; border-bottom: 1px solid #cbd5e1; padding-bottom: 8px;">Buyer Information</h4>
            <div style="font-size: 13px; margin-bottom: 6px;"><strong>Name:</strong> <?= htmlspecialchars($resData['fullname'] ?? 'N/A') ?></div>
            <div style="font-size: 13px; margin-bottom: 6px;"><strong>Email:</strong> <?= htmlspecialchars($resData['email'] ?? 'N/A') ?></div>
            <div style="font-size: 13px; margin-bottom: 6px;"><strong>Contact:</strong> <?= htmlspecialchars($resData['phone'] ?? $resData['contact_number'] ?? 'N/A') ?></div>
            <div style="font-size: 13px;"><strong>Address:</strong> <?= htmlspecialchars($resData['address'] ?? 'N/A') ?></div>
        </div>

        <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 20px; border-radius: 12px;">
            <h4 style="margin: 0 0 15px; color: #0f172a; font-size: 15px; border-bottom: 1px solid #cbd5e1; padding-bottom: 8px;">Property Overview</h4>
            <div style="font-size: 13px; margin-bottom: 6px;"><strong>Property:</strong> Block <?= $resData['block_no'] ?> Lot <?= $resData['lot_no'] ?></div>
            <div style="font-size: 13px; margin-bottom: 6px;"><strong>Lot Area:</strong> <?= $resData['area'] ?> sqm</div>
            <div style="font-size: 13px; margin-bottom: 6px;"><strong>Total Contract Price (TCP):</strong> ₱<?= number_format($tcp, 2) ?></div>
            <div style="font-size: 13px; color: #2e7d32; font-weight: bold;"><strong>Required 20% DP:</strong> ₱<?= number_format($dp_required, 2) ?></div>
        </div>
    </div>

    <div style="display: flex; gap: 15px; margin-bottom: 30px;">
        <div style="flex: 1; background: white; border: 1px solid #c8e6c9; border-left: 4px solid #10b981; padding: 15px; border-radius: 8px;">
            <span style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Total DP Paid</span>
            <div style="font-size: 18px; font-weight: 800; color: #10b981; margin-top: 5px;">₱<?= number_format($total_dp_paid, 2) ?></div>
            <div style="font-size: 11px; color: <?= ($total_dp_paid >= $dp_required) ? '#10b981' : '#ef4444' ?>; margin-top: 5px; font-weight: 600;">
                <?= ($total_dp_paid >= $dp_required) ? '<i class="fa-solid fa-check-circle"></i> DP Fully Settled' : 'Balance: ₱' . number_format($dp_required - $total_dp_paid, 2) ?>
            </div>
        </div>
        <div style="flex: 1; background: white; border: 1px solid #bfdbfe; border-left: 4px solid #3b82f6; padding: 15px; border-radius: 8px;">
            <span style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Total Amortization Paid</span>
            <div style="font-size: 18px; font-weight: 800; color: #3b82f6; margin-top: 5px;">₱<?= number_format($total_amort_paid, 2) ?></div>
        </div>
        <div style="flex: 1; background: white; border: 1px solid #fecaca; border-left: 4px solid #ef4444; padding: 15px; border-radius: 8px;">
            <span style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Overall Outstanding Balance</span>
            <div style="font-size: 18px; font-weight: 800; color: #ef4444; margin-top: 5px;">₱<?= number_format($tcp - ($total_dp_paid + $total_amort_paid), 2) ?></div>
        </div>
    </div>

    <h3 style="font-size: 16px; color: #0f172a; margin-bottom: 10px; border-bottom: 2px solid #e2e8f0; padding-bottom: 5px;"><i class="fa-solid fa-list-check" style="color:var(--primary); margin-right:5px;"></i> Official Payment Ledger</h3>
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 13px;">
        <thead>
            <tr style="background: #f1f5f9;">
                <th style="padding: 10px; text-align: left; border: 1px solid #e2e8f0; color:#475569;">Date</th>
                <th style="padding: 10px; text-align: left; border: 1px solid #e2e8f0; color:#475569;">OR / Ref Number</th>
                <th style="padding: 10px; text-align: left; border: 1px solid #e2e8f0; color:#475569;">Description / Category</th>
                <th style="padding: 10px; text-align: right; border: 1px solid #e2e8f0; color:#475569;">Amount Paid</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($transactions)): ?>
                <tr><td colspan="4" style="padding: 20px; text-align: center; border: 1px solid #e2e8f0; color:#94a3b8;">No payments recorded yet. Record a payment to see it here.</td></tr>
            <?php else: ?>
                <?php foreach($transactions as $t): ?>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #e2e8f0;"><?= date('M d, Y', strtotime($t['transaction_date'])) ?></td>
                        <td style="padding: 10px; border: 1px solid #e2e8f0; font-weight: bold; color: #0369a1;"><?= htmlspecialchars($t['or_number']) ?></td>
                        <td style="padding: 10px; border: 1px solid #e2e8f0;"><?= htmlspecialchars($t['description']) ?></td>
                        <td style="padding: 10px; border: 1px solid #e2e8f0; text-align: right; font-weight: bold; color: #10b981;">₱<?= number_format($t['amount'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <h3 style="font-size: 16px; color: #0f172a; margin-bottom: 10px; border-bottom: 2px solid #e2e8f0; padding-bottom: 5px;">
        <i class="fa-solid fa-calendar-days" style="color:var(--primary); margin-right:5px;"></i> Monthly Amortization Schedule (<?= $years ?> Years)
    </h3>
    
    <?php if ($total_dp_paid < $dp_required): ?>
        <div style="background: #fffbeb; border: 1px solid #fef08a; padding: 15px; border-radius: 8px; color: #b45309; font-size: 13px; font-weight: 600; margin-bottom: 20px;">
            <i class="fa-solid fa-triangle-exclamation"></i> Note: The amortization schedule officially begins only after the 20% Down Payment is fully settled.
        </div>
    <?php endif; ?>

    <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
        <thead>
            <tr style="background: #f8fafc;">
                <th style="padding: 10px; text-align: center; border: 1px solid #cbd5e1; width: 60px;">Month</th>
                <th style="padding: 10px; text-align: left; border: 1px solid #cbd5e1;">Monthly Due</th>
                <th style="padding: 10px; text-align: left; border: 1px solid #cbd5e1;">Remaining Balance</th>
                <th style="padding: 10px; text-align: center; border: 1px solid #cbd5e1; width: 160px;">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // ADVANCED TRACKING: Distribute the total_amort_paid across the months accurately
            $remaining_amort_funds = $total_amort_paid;
            $running_balance = $balance_to_amortize;
            
            for($i = 1; $i <= $total_months; $i++):
                $running_balance -= $monthly_payment;
                if ($running_balance < 0) $running_balance = 0;
                
                // Determine Status based on available paid funds
                if ($remaining_amort_funds >= $monthly_payment) {
                    $status_html = '<span style="background: #dcfce7; color: #16a34a; padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; text-transform: uppercase;"><i class="fa-solid fa-check"></i> Fully Paid</span>';
                    $remaining_amort_funds -= $monthly_payment;
                } else if ($remaining_amort_funds > 0) {
                    $status_html = '<span style="background: #fef3c7; color: #b45309; padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; text-transform: uppercase;"><i class="fa-solid fa-spinner"></i> Partial (₱'.number_format($remaining_amort_funds).')</span>';
                    $remaining_amort_funds = 0; // Funds exhausted
                } else {
                    $status_html = '<span style="background: #f1f5f9; color: #94a3b8; padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; text-transform: uppercase;">Unpaid</span>';
                }
            ?>
            <tr>
                <td style="padding: 10px; text-align: center; border: 1px solid #e2e8f0; font-weight: bold;"><?= $i ?></td>
                <td style="padding: 10px; border: 1px solid #e2e8f0;">₱<?= number_format($monthly_payment, 2) ?></td>
                <td style="padding: 10px; border: 1px solid #e2e8f0; color: #64748b;">₱<?= number_format($running_balance, 2) ?></td>
                <td style="padding: 10px; text-align: center; border: 1px solid #e2e8f0;">
                    <?= $status_html ?>
                </td>
            </tr>
            <?php endfor; ?>
        </tbody>
    </table>
    
    <div style="margin-top: 20px; text-align: right;">
        <button type="button" onclick="window.print()" style="background:#f1f5f9; border: 1px solid #cbd5e1; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s;">
            <i class="fa-solid fa-print"></i> Print Statement
        </button>
    </div>
</div>