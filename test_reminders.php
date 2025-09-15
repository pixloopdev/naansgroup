<?php
// Test rent reminders
$url = 'http://localhost/propx-beta/api.php?action=get_all_data';
$data_json = file_get_contents($url);
$data = json_decode($data_json, true);

echo "Rent Reminders (" . count($data['rent_reminders']) . "):\n";
echo "================================\n";

if (empty($data['rent_reminders'])) {
    echo "No rent reminders found.\n";
} else {
    foreach ($data['rent_reminders'] as $reminder) {
        echo "- " . $reminder['name'] . " (" . $reminder['status'] . "): " . $reminder['days_info'] . "\n";
        echo "  Property: " . $reminder['propertyName'] . "\n";
        echo "  Amount: AED " . number_format($reminder['monthlyRent'], 2) . "\n";
        echo "  Due: " . $reminder['rentDueDate'] . "th of each month\n\n";
    }
}

echo "\nCurrent date: " . date('Y-m-d') . " (Day " . date('d') . ")\n";
?>
