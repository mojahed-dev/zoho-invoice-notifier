<?php

// Path to the CSV log
$csvFile = __DIR__ . '/../../log/sent_log.csv';

if (!file_exists($csvFile)) {
    die('CSV log file not found.');
}

$rows = array_map('str_getcsv', file($csvFile));
$header = array_shift($rows);

// Group by invoice ID and interval to count retries
$retryCounts = [];
foreach ($rows as $row) {
    $key = $row[1] . '_' . $row[3];
    $retryCounts[$key] = ($retryCounts[$key] ?? 0) + 1;
}

// Handle filters
$filterStatus = $_GET['status'] ?? '';
$filterInvoice = $_GET['invoice'] ?? '';
$filterDue = $_GET['due'] ?? '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice Notification Logs</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #f5f5f5; }
        .sent { background-color: #e0ffe0; }
        .failed { background-color: #ffe0e0; }
        .email { background-color: #e0f0ff; }
    </style>
</head>
<body>
    <h2> Invoice Reminder Logs</h2>

    <form method="get" style="margin-bottom: 20px">
        <label>Status:
            <select name="status">
                <option value="">All</option>
                <option value="SENT" <?= $filterStatus === 'SENT' ? 'selected' : '' ?>>SENT</option>
                <option value="FAILED" <?= $filterStatus === 'FAILED' ? 'selected' : '' ?>>FAILED</option>
                <option value="EMAIL" <?= $filterStatus === 'EMAIL' ? 'selected' : '' ?>>EMAIL</option>
            </select>
        </label>
        <label>Invoice ID:
            <input type="text" name="invoice" value="<?= htmlspecialchars($filterInvoice) ?>">
        </label>
        <label>Due Date:
            <input type="text" name="due" placeholder="YYYY-MM-DD" value="<?= htmlspecialchars($filterDue) ?>">
        </label>
        <button type="submit">Filter</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>Timestamp</th>
                <th>Invoice ID</th>
                <th>Invoice No</th>
                <th>Interval</th>
                <th>Due Date</th>
                <th>Status</th>
                <th>Method</th>
                <th>Phone</th>
                <th>Message</th>
                <th>PDF Link</th>
                <th>Retries</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row):
            if (count($row) < 9) continue; // Ensure the row has enough columns

            if (
                ($filterStatus && strtoupper($row[5]) !== strtoupper($filterStatus)) ||
                ($filterInvoice && stripos($row[1], $filterInvoice) === false) ||
                ($filterDue && strpos($row[4], $filterDue) === false)
            ) continue;

            $statusClass = strtolower($row[5]);
            $retryKey = $row[1] . '_' . $row[3];
            $pdfFile = 'invoices/invoice_' . str_replace('="', '', trim($row[1], '"')) . '.pdf';
        ?>
            <tr class="<?= $statusClass ?>">
                <td><?= htmlspecialchars($row[0]) ?></td>
                <td><?= htmlspecialchars($row[1]) ?></td>
                <td><?= htmlspecialchars($row[2]) ?></td>
                <td><?= htmlspecialchars($row[3]) ?></td>
                <td><?= htmlspecialchars($row[4]) ?></td>
                <td><?= htmlspecialchars($row[5]) ?></td>
                <td><?= htmlspecialchars($row[6]) ?></td>
                <td><?= htmlspecialchars($row[7]) ?></td>
                <td><?= htmlspecialchars($row[8]) ?></td>
                <td><a href="/<?= $pdfFile ?>" target="_blank">View</a></td>
                <td><?= $retryCounts[$retryKey] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
