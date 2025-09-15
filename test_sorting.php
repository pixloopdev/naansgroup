<?php
require 'api.php';
$db = new PDO('sqlite:propertymanagement.sqlite');
$reminders = get_rent_reminders($db);

echo "Rent Reminders (Sorted by Priority):\n";
echo "=====================================\n";
foreach ($reminders as $i => $reminder) {
    echo ($i + 1) . ". " . $reminder['name'] . " - " . $reminder['status'] . " (" . $reminder['days_info'] . ")\n";
}
?>
