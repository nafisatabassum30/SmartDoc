<?php
require_once __DIR__ . '/includes/db.php';

try {
    // Test the query used in selectSpecialist.php
    $result = $con->query("SELECT symptom_id, symptom_name, AffectedArea, AffectedDuration FROM `symptom` ORDER BY symptom_name");

    if ($result) {
        $symptoms = [];
        $areas = [];
        $durations = [];

        while ($row = $result->fetch_assoc()) {
            $symptoms[] = $row;
            $area = trim((string)($row['AffectedArea'] ?? ''));
            $dur  = trim((string)($row['AffectedDuration'] ?? ''));
            if ($area !== '' && !in_array($area, $areas, true)) { $areas[] = $area; }
            if ($dur  !== '' && !in_array($dur,  $durations, true)) { $durations[] = $dur; }
        }

        echo "Database connection for selectSpecialist.php successful.\n";
        echo "Loaded " . count($symptoms) . " symptoms.\n";
        echo "Unique affected areas: " . count($areas) . "\n";
        echo "Unique affected durations: " . count($durations) . "\n";

        if (count($symptoms) > 0) {
            echo "Sample symptoms:\n";
            for ($i = 0; $i < min(5, count($symptoms)); $i++) {
                echo "- " . $symptoms[$i]['symptom_name'] . "\n";
            }
        }

        echo "All database queries for select specialist working correctly.\n";
    } else {
        echo "Query failed.\n";
        exit(1);
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
