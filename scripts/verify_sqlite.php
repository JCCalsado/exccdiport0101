<?php
// Quick database verification script
$dbPath = __DIR__ . '/database.sqlite';
if (!file_exists($dbPath)) {
    die("Database not found\n");
}

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "\n=== PAYMENT TERMS VERIFICATION ===\n\n";

$query = <<<SQL
SELECT 
    sa.id as assessment_id,
    sa.user_id,
    sa.total_assessment,
    ROUND(SUM(spt.amount), 2) as sum_of_terms,
    ROUND(SUM(spt.balance), 2) as total_balance,
    CAST(ABS(SUM(spt.amount) - sa.total_assessment) AS REAL) as discrepancy
FROM student_assessments sa
LEFT JOIN student_payment_terms spt ON spt.student_assessment_id = sa.id
GROUP BY sa.id
LIMIT 10
SQL;

$stmt = $pdo->query($query);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($results as $row) {
    $status = $row['discrepancy'] < 0.01 ? '✓' : '✗';
    echo sprintf(
        "Assessment #%d (User %d): Total=%.2f, Sum=%.2f, Balance=%.2f, Diff=%.4f %s\n",
        $row['assessment_id'],
        $row['user_id'],
        $row['total_assessment'],
        $row['sum_of_terms'],
        $row['total_balance'],
        $row['discrepancy'],
        $status
    );
}

echo "\nChecking for any remaining discrepancies...\n\n";

$badQuery = <<<SQL
SELECT COUNT(*) as count
FROM student_assessments sa
LEFT JOIN student_payment_terms spt ON spt.student_assessment_id = sa.id
GROUP BY sa.id
HAVING ABS(SUM(spt.amount) - sa.total_assessment) >= 0.01
SQL;

$badStmt = $pdo->query($badQuery);
$badCount = count($badStmt->fetchAll(PDO::FETCH_ASSOC));

echo "Assessments with rounding discrepancies: $badCount\n";

if ($badCount > 0) {
    echo "\nDetails of remaining discrepancies:\n";
    $problemQuery = <<<SQL
SELECT 
    sa.id,
    sa.user_id,
    sa.total_assessment,
    GROUP_CONCAT(ROUND(spt.amount, 2), ', ') as term_amounts,
    ROUND(SUM(spt.amount), 2) as sum_terms,
    ABS(SUM(spt.amount) - sa.total_assessment) as diff
FROM student_assessments sa
LEFT JOIN student_payment_terms spt ON spt.student_assessment_id = sa.id
GROUP BY sa.id
HAVING ABS(SUM(spt.amount) - sa.total_assessment) >= 0.01
LIMIT 5
SQL;
    
    $problemStmt = $pdo->query($problemQuery);
    $problems = $problemStmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($problems as $p) {
        echo "  ID {$p['id']}: Total={$p['total_assessment']}, Sum={$p['sum_terms']}, Terms=[{$p['term_amounts']}]\n";
    }
}

echo "\n✅ Database verification complete!\n";
