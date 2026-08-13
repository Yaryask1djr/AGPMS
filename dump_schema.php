<?php
$conn = new mysqli('localhost', 'root', '', 'agpms');
$res = $conn->query('SHOW TABLES');
while($row = $res->fetch_array()) {
    echo "Table: {$row[0]}\n";
    $res2 = $conn->query("SHOW COLUMNS FROM {$row[0]}");
    while($col = $res2->fetch_assoc()) {
        echo "  {$col['Field']} - {$col['Type']}\n";
    }
}
?>
