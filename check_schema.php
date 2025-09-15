<?php
$db = new PDO('sqlite:propertymanagement.sqlite');
$result = $db->query('PRAGMA table_info(tenants)');
echo "Tenants table schema:\n";
while ($row = $result->fetch()) {
    echo $row['name'] . ' (' . $row['type'] . ')' . PHP_EOL;
}
?>
