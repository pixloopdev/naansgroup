<?php
$db = new PDO('sqlite:propertymanagement.sqlite');
$stmt = $db->query('SELECT name, phone FROM tenants WHERE phone IS NOT NULL');

echo "Tenants with phone numbers:\n";
echo "==========================\n";
while ($row = $stmt->fetch()) {
    echo $row['name'] . ': ' . $row['phone'] . "\n";
}
?>
