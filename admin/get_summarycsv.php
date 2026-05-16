<?php

require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="risk_summary.csv"');

$output = fopen('php://output', 'w');

fputcsv($output, [
    'City',
    'Barangay',
    'Risk Level',
    'Risk Score',
    'Confidence',
    'Prediction Date'
]);

$database = new Database();
$pdo = $database->getConnection();

/*
    Replace with your actual table/query
*/

$stmt = $pdo->prepare("
    SELECT
        city,
        barangay,
        zone_type,
        risk_score,
        confidence,
        outbreak_date
    FROM asf_predictions
");

$stmt->execute();

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {

    fputcsv($output, [
        $row['city'],
        $row['barangay'],
        $row['zone_type'],
        $row['risk_score'],
        $row['confidence'],
        $row['outbreak_date']
    ]);
}

fclose($output);
exit;