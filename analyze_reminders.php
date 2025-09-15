<?php
$data = json_decode(file_get_contents('current_data.json'), true);

echo "=== RENT REMINDERS ANALYSIS ===\n";
echo "Current Date: " . date('Y-m-d') . " (August 1, 2025)\n\n";

echo "=== TENANTS AND THEIR RENT DUE DATES ===\n";
foreach($data['tenants'] as $tenant) {
    echo "- {$tenant['name']} (ID: {$tenant['id']})\n";
    echo "  Rent Due: Day {$tenant['rentDueDate']} of each month\n";
    echo "  Rent Amount: AED {$tenant['rentAmount']}\n";
    echo "  Next Due: 2025-08-" . str_pad($tenant['rentDueDate'], 2, '0', STR_PAD_LEFT) . "\n\n";
}

echo "=== PAYMENTS IN AUGUST 2025 ===\n";
$august2025Payments = [];
foreach($data['payments'] as $payment) {
    if(strpos($payment['paymentDate'], '2025-08') === 0) {
        $august2025Payments[] = $payment;
        echo "- Tenant ID {$payment['tenantId']}: AED {$payment['amount']} on {$payment['paymentDate']}\n";
    }
}

echo "\n=== PAID TENANT IDs IN AUGUST 2025 ===\n";
$paidTenantIds = array_unique(array_column($august2025Payments, 'tenantId'));
echo "Paid tenants: " . implode(', ', $paidTenantIds) . "\n";

echo "\n=== RENT REMINDERS RETURNED ===\n";
if(empty($data['rent_reminders'])) {
    echo "No rent reminders found.\n";
} else {
    foreach($data['rent_reminders'] as $reminder) {
        echo "- {$reminder['name']}: {$reminder['days_until_due']} days until due\n";
    }
}

echo "\n=== ANALYSIS ===\n";
foreach($data['tenants'] as $tenant) {
    $isPaid = in_array($tenant['id'], $paidTenantIds);
    $dueDate = (int)$tenant['rentDueDate'];
    $nextDueDate = new DateTime('2025-08-' . str_pad($dueDate, 2, '0', STR_PAD_LEFT));
    $today = new DateTime('2025-08-01');
    
    if ($nextDueDate < $today) {
        $nextDueDate->modify('+1 month');
    }
    
    $interval = $today->diff($nextDueDate);
    $daysUntilDue = (int)$interval->format('%r%a');
    
    echo "Tenant: {$tenant['name']}\n";
    echo "  Paid this month: " . ($isPaid ? 'YES' : 'NO') . "\n";
    echo "  Days until due: $daysUntilDue\n";
    echo "  Should show reminder: " . ((!$isPaid && $daysUntilDue >= -30 && $daysUntilDue <= 7) ? 'YES' : 'NO') . "\n\n";
}
?>
