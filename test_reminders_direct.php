<?php
require 'api.php';

$db = new PDO('sqlite:propertymanagement.sqlite');
$reminders = get_rent_reminders($db);

echo "Found " . count($reminders) . " rent reminders:\n";
echo "Current date: " . date('Y-m-d') . " (Day " . date('d') . ")\n\n";

foreach ($reminders as $reminder) {
    echo "- " . $reminder['name'] . " (" . $reminder['status'] . "): " . $reminder['days_info'] . "\n";
    echo "  Property: " . $reminder['propertyName'] . "\n";
    echo "  Amount: AED " . number_format($reminder['monthlyRent'], 2) . "\n";
    echo "  Due: " . $reminder['rentDueDate'] . "th of each month\n\n";
}

// Also check what tenants exist
$stmt = $db->query("SELECT name, rentAmount, rentDueDate FROM tenants");
$tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "\nAll tenants in database:\n";
foreach ($tenants as $tenant) {
    echo "- " . $tenant['name'] . " (Due: " . $tenant['rentDueDate'] . "th, Rent: AED " . $tenant['rentAmount'] . ")\n";
}

// Check payments for current month
$current_month = date('Y-m');
$stmt = $db->prepare("SELECT tenantId, amount, paymentDate FROM payments WHERE strftime('%Y-%m', paymentDate) = ?");
$stmt->execute([$current_month]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "\nPayments for " . $current_month . ":\n";
foreach ($payments as $payment) {
    echo "- Tenant ID " . $payment['tenantId'] . ": AED " . $payment['amount'] . " on " . $payment['paymentDate'] . "\n";
}
?>
