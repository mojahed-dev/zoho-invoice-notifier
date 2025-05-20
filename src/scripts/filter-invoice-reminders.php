<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;
use Auth\TokenManager;
use Api\ZohoInvoice;
use Services\TwilioSender;

// === Load environment variables and set timezone ===
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();
date_default_timezone_set('Asia/Riyadh');

// === Prepare logging directory and file ===
$logDir = __DIR__ . '/../../log';
$logFile = $logDir . '/sent_log.txt';

if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true); // Create log/ folder if it doesn't exist
}
if (!file_exists($logFile)) {
    file_put_contents($logFile, ''); // Create empty sent_log.txt if missing
}

// === Initialize Zoho and Twilio clients ===
$tokenManager = new TokenManager(
    $_ENV['ZOHO_CLIENT_ID'],
    $_ENV['ZOHO_CLIENT_SECRET'],
    $_ENV['ZOHO_REFRESH_TOKEN']
);

$zohoInvoice = new ZohoInvoice($tokenManager, $_ENV['ZOHO_ORG_ID']);
$twilio = new TwilioSender($_ENV['TWILIO_SID'], $_ENV['TWILIO_TOKEN']);
$twilioFrom = $_ENV['TWILIO_FROM'];

$reminderDays = [15, 13, 11, 9, 7, 5, 3, 1, 2, 0];
$todayMidnight = (new DateTime())->setTime(0, 0);

// === Step 1: Clean sent_log.txt by removing paid invoice reminders ===
$logEntries = file($logFile, FILE_IGNORE_NEW_LINES); // Load sent logs
$allInvoices = $zohoInvoice->getInvoices();

// Filter and collect all paid invoice IDs
$paidIds = array_column(array_filter($allInvoices, fn($inv) => strtolower($inv['status']) === 'paid'), 'invoice_id');

// Remove log entries related to paid invoices
$filteredLog = array_filter($logEntries, fn($entry) => !in_array(explode('_', $entry)[0], $paidIds));
file_put_contents($logFile, implode(PHP_EOL, $filteredLog) . PHP_EOL); // Overwrite cleaned log

// === Step 2: Loop through all invoices and send reminders ===
foreach ($allInvoices as $invoice) {
    // Skip if invoice has no due date or already paid
    if (empty($invoice['due_date']) || strtolower($invoice['status']) === 'paid') continue;

    $dueDate = (new DateTime($invoice['due_date']))->setTime(0, 0);
    $interval = (int) $todayMidnight->diff($dueDate)->format('%r%a') + 1; // Calculate days until due

    if (!in_array($interval, $reminderDays)) continue; // Skip if not in reminder schedule

    $logKey = "{$invoice['invoice_id']}_{$interval}";
    if (in_array($logKey, $filteredLog)) {
        echo "⚠️ Already reminded: $logKey\n";
        continue; // Skip if already sent
    }

    // Try to fetch the customer phone number
    $rawPhone = $invoice['phone'] ?? $invoice['billing_address']['phone'] ?? $invoice['shipping_address']['phone'] ?? null;
    $customerPhone = $rawPhone ? 'whatsapp:' . $rawPhone : null;
    if (!$customerPhone) {
        echo "⚠️ Skipping invoice {$invoice['invoice_id']} — no phone\n";
        continue;
    }

    // Build WhatsApp message
    $message = "*Invoice Reminder*\n" .
               "Invoice No: *{$invoice['number']}*\n" .
               "Amount Due: *{$invoice['total']}*\n" .
               "Due Date: *{$invoice['due_date']}*\n\n" .
               "Please settle on or before the due date. Thank you!";

    // Attempt to send the message
    try {
        $sid = $twilio->sendWhatsAppText($customerPhone, $twilioFrom, $message);
        echo "✅ Sent to $customerPhone (SID: $sid)\n";
        file_put_contents($logFile, $logKey . PHP_EOL, FILE_APPEND); // Log sent reminder
    } catch (Exception $e) {
        echo "❌ Failed to send to $customerPhone: " . $e->getMessage() . "\n";
    }
}
