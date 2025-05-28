<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;
use Auth\TokenManager;
use Api\ZohoInvoice;
use Services\TwilioSender;
use Services\NotificationService;
use Services\LoggerService;
use Services\OracleUploader;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();
date_default_timezone_set('Asia/Riyadh');

$logDir = __DIR__ . '/../../log';
$pdfDir = __DIR__ . '/../../invoices';
$csvFile = "$logDir/sent_log.csv";
$retryLimit = 3;
$finalFailFile = "$logDir/final_failures.csv";

if (!file_exists($csvFile)) {
    echo "CSV log not found.\n";
    exit;
}

if (!file_exists($finalFailFile)) {
    file_put_contents($finalFailFile, "InvoiceID,InvoiceNumber,Interval,DueDate,Phone,Reason\n");
}

// === Initialize services ===
$tokenManager = new TokenManager(
    $_ENV['ZOHO_CLIENT_ID'],
    $_ENV['ZOHO_CLIENT_SECRET'],
    $_ENV['ZOHO_REFRESH_TOKEN']
);

$zohoInvoice = new ZohoInvoice($tokenManager, $_ENV['ZOHO_ORG_ID']);
$twilio = new TwilioSender($_ENV['TWILIO_SID'], $_ENV['TWILIO_TOKEN']);
$logger = new LoggerService("$logDir/sent_log.txt", $csvFile);
$notifier = new NotificationService($twilio, $tokenManager, $logger);

// === Fetch invoice data ===
$invoices = $zohoInvoice->getInvoices();
$invoiceMap = [];
foreach ($invoices as $inv) {
    $invoiceMap[$inv['invoice_id']] = $inv;
}

$rows = file($csvFile, FILE_IGNORE_NEW_LINES);
array_shift($rows); // Remove header

$retryCounts = [];
$alreadyRetried = [];

// === Count previous WhatsApp retry attempts ===
foreach ($rows as $row) {
    $cols = str_getcsv($row);
    if (count($cols) < 8) continue;

    [$timestamp, $invoiceId, $invoiceNumber, $interval, $status, $method, $phone, $message] = $cols;

    $retryKey = $invoiceId . '_' . $interval;

    if (!isset($retryCounts[$retryKey])) {
        $retryCounts[$retryKey] = 0;
    }

    if (strtolower($method) === 'whatsapp') {
        $retryCounts[$retryKey]++;
    }
}

// === Retry logic ===
foreach ($rows as $row) {
    $cols = str_getcsv($row);
    if (count($cols) < 8) continue;

    [$timestamp, $invoiceId, $invoiceNumber, $interval, $status, $method, $phone, $message] = $cols;

    if (strtoupper($status) !== 'FAILED' || strtolower($method) !== 'whatsapp') continue;
    if (!isset($invoiceMap[$invoiceId])) {
        echo "Invoice $invoiceId not found in current Zoho data.\n";
        continue;
    }

    $retryKey = $invoiceId . '_' . $interval;
    $invoice = $invoiceMap[$invoiceId];
    $dueDate = $invoice['due_date'] ?? 'N/A';

    // Mark final failure if retry limit is reached
    if ($retryCounts[$retryKey] >= $retryLimit) {
        echo "⏩ Skipping $invoiceId (retry limit reached)\n";
        file_put_contents($finalFailFile, "$invoiceId,$invoiceNumber,$interval,$dueDate,$phone,Retry limit reached\n", FILE_APPEND);
        continue;
    }

    // Prevent duplicate retries in this session
    if (isset($alreadyRetried[$retryKey])) continue;

    echo "🔁 Retrying WhatsApp for Invoice {$invoice['number']}...\n";
    $result = $notifier->sendReminder($invoice, (int)$interval, $pdfDir);

    // Log retry result to CSV
    $logger->appendCsvEntry(
        OracleUploader::formatLogRow(
            $invoice['invoice_id'],
            $invoice['number'],
            $result,
            'whatsapp',
            $invoice['phone'] ?? ($invoice['billing_address']['phone'] ?? 'N/A'),
            "Retry reminder for Invoice No: {$invoice['number']}",
            (int)$interval, 
            $dueDate
        )
    );

    $alreadyRetried[$retryKey] = true;
    $retryCounts[$retryKey]++;
    sleep(1); // Prevent spamming Twilio API
}

echo "✅ Retry process completed.\n";
