<?php
require_once __DIR__ . '/includes/db.php';

try {
    // Test connection
    if ($con->ping()) {
        echo "Database connection successful.\n";
    } else {
        echo "Database connection failed.\n";
        exit(1);
    }

    // Test query
    $result = $con->query('SELECT COUNT(*) as count FROM patient');
    $row = $result->fetch_assoc();
    echo "Patient table has " . $row['count'] . " records.\n";

    // Test another table
    $result = $con->query('SELECT COUNT(*) as count FROM doctor');
    $row = $result->fetch_assoc();
    echo "Doctor table has " . $row['count'] . " records.\n";

    echo "All database connections and queries working correctly.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
