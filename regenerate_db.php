<?php
require 'api.php';

// Regenerate database with new data
if (file_exists('propertymanagement.sqlite')) {
    unlink('propertymanagement.sqlite');
}

$db = new PDO('sqlite:propertymanagement.sqlite');
create_tables($db);
add_dummy_data($db);

echo "Database regenerated with updated rent reminder data\n";
?>
