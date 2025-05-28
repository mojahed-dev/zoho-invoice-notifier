<?php

// Auto-Delete Old PDFs and Archive Old Log Entries

$invoiceDir = __DIR__ . '/../../invoices';
$csvFile = __DIR__ . '/../../log/sent_log.csv';
$archiveFile = __DIR__ . '/../../log/archived_log.csv';
$maxAgeDays = 30;
$cutoff = time() - ($maxAgeDays * 86400);

// === Step 1: Delete PDFs older than 30 days ===
$deletedCount = 0;
foreach (glob("$invoiceDir/*.pdf") as $file) {
    if (filemtime($file) < $cutoff) {
        unlink($file);
        $deletedCount++;
    }
}
echo "🧹 Deleted $deletedCount old PDFs\n";

// === Step 2: Archive old sent_log.csv entries ===
if (!file_exists($csvFile)) {
    die("Log file not found\n");
}

$rows = file($csvFile, FILE_IGNORE_NEW_LINES);
$header = array_shift($rows);
$archiveRows = [];
$keepRows = [];

foreach ($rows as $line) {
    $cols = str_getcsv($line);
    if (count($cols) < 9) continue;

    $logTime = strtotime($cols[0]);
    if ($logTime < $cutoff) {
        $archiveRows[] = $line;
    } else {
        $keepRows[] = $line;
    }
}

if ($archiveRows) {
    file_put_contents($archiveFile, ($header . "\n"), FILE_APPEND);
    file_put_contents($archiveFile, implode("\n", $archiveRows) . "\n", FILE_APPEND);
    echo "📦 Archived " . count($archiveRows) . " log rows\n";
}

file_put_contents($csvFile, $header . "\n" . implode("\n", $keepRows) . "\n");
echo "✅ Log file cleaned and updated\n";
