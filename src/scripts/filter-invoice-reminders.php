<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;
use Auth\TokenManager;
use Api\ZohoInvoice;
use Services\TwilioSender;
use Services\NotificationService;
use Services\LoggerService;

// === Initialize environment ===
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();
date_default_timezone_set('Asia/Riyadh');

// === Setup directories ===
$logDir = __DIR__ . '/../../log';
$pdfDir = __DIR__ . '/../../invoices';
if (!is_dir($logDir)) mkdir($logDir, 0755, true);
if (!is_dir($pdfDir)) mkdir($pdfDir, 0755, true);

$logFile = "$logDir/sent_log.txt";
$csvFile = "$logDir/sent_log.csv";
if (!file_exists($logFile)) file_put_contents($logFile, '');
if (!file_exists($csvFile)) file_put_contents($csvFile, "InvoiceID,Interval,Phone,Status,Date\n");

// === Initialize services ===
$tokenManager = new TokenManager(
    $_ENV['ZOHO_CLIENT_ID'],
    $_ENV['ZOHO_CLIENT_SECRET'],
    $_ENV['ZOHO_REFRESH_TOKEN']
);

$zohoInvoice = new ZohoInvoice($tokenManager, $_ENV['ZOHO_ORG_ID']);
$twilio = new TwilioSender($_ENV['TWILIO_SID'], $_ENV['TWILIO_TOKEN']);
$notifier = new NotificationService($twilio, $tokenManager);
$logger = new LoggerService($logFile, $csvFile);

$reminderDays = [15, 13, 11, 9, 7, 5, 3, 1, 2, 0];
$todayMidnight = (new DateTime())->setTime(0, 0);

// === Fetch invoices and clean logs ===
$allInvoices = $zohoInvoice->getInvoices();
$logger->cleanPaidEntries($allInvoices);
$filteredLog = $logger->getLogEntries();

foreach ($allInvoices as $invoice) {
    if (empty($invoice['due_date']) || strtolower($invoice['status']) === 'paid') continue;

    $dueDate = (new DateTime($invoice['due_date']))->setTime(0, 0);
    $interval = (int) $todayMidnight->diff($dueDate)->format('%r%a');
    if (!in_array($interval, $reminderDays)) continue;

    $logKey = "{$invoice['invoice_id']}_{$interval}";
    if (in_array($logKey, $filteredLog)) {
        echo "⚠️ Already reminded: $logKey\n";
        continue;
    }

    $status = $notifier->sendReminder($invoice, $interval, $pdfDir);

    $logger->appendLogEntry($logKey);
    $logger->appendCsvEntry([
        $invoice['invoice_id'], $interval, $invoice['phone'] ?? 'none', $status, date('Y-m-d H:i:s')
    ]);
}
