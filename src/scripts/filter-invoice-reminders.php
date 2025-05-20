<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;
use Auth\TokenManager;
use Api\ZohoInvoice;
use Services\TwilioSender;

$logDir = __DIR__ . '/../../log';

if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true); // ✅ creates log/ if missing
}

$logFile = $logDir . '/sent_log.txt';
if (!file_exists($logFile)) {
    file_put_contents($logFile, ''); // ✅ creates empty sent_log.txt
}



date_default_timezone_set('Asia/Riyadh'); // or 'Asia/Riyadh' if needed

$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

$tokenManager = new TokenManager(
    $_ENV['ZOHO_CLIENT_ID'],
    $_ENV['ZOHO_CLIENT_SECRET'],
    $_ENV['ZOHO_REFRESH_TOKEN']
);

$orgId = $_ENV['ZOHO_ORG_ID'];
$zohoInvoice = new ZohoInvoice($tokenManager, $orgId);

$invoices = $zohoInvoice->getInvoices();

$today = new DateTime();
$reminderDays = [15, 13, 11, 9, 7, 5, 3, 1, 2, 0];


$twilio = new TwilioSender($_ENV['TWILIO_SID'], $_ENV['TWILIO_TOKEN']);
$twilioFrom = $_ENV['TWILIO_FROM'];

foreach ($invoices as $invoice) {

    if (empty($invoice['due_date'])) {
        echo "No due date for this invoice.\n";
        continue;
    }

    $rawDueDate = $invoice['due_date'];
    $dueDate = (new DateTime($rawDueDate))->setTime(0, 0);
    $todayMidnight = (new DateTime())->setTime(0, 0);

    $interval = (int) $todayMidnight->diff($dueDate)->format('%r%a') + 1;

    if (
        $invoice['status'] !== 'paid' &&
        in_array($interval, $reminderDays)
    ) {
        echo "✅ MATCH: Invoice ID {$invoice['invoice_id']} is due in $interval days. \n  Invoice Number {$invoice['number']}\n";

        $rawPhone =
            $invoice['phone'] ??
            ($invoice['billing_address']['phone'] ?? null) ??
            ($invoice['shipping_address']['phone'] ?? null);

        $customerPhone = $rawPhone ? 'whatsapp:' . $rawPhone : null;

        if (!$customerPhone) {
            echo "⚠️ Skipping — no phone found\n";
            continue;
        }

        echo "Phone: $customerPhone\n";

        $invoiceNumber = $invoice['number'];
        $due = $invoice['due_date'];
        $amount = $invoice['total'] ?? 'N/A';

        $logKey = "{$invoice['invoice_id']}_{$interval}";

        // ✅ Skip if already logged
        if (str_contains(file_get_contents($logFile), $logKey)) {
            echo "⚠️ Already sent for Invoice ID {$invoice['invoice_id']} (Day $interval)\n";
            continue;
        }

        $message = "🧾 *Invoice Reminder*\n" .
            "Invoice No: *$invoiceNumber*\n" .
            "Amount Due: *$amount*\n" .
            "Due Date: *$due*\n\n" .
            "Please settle on or before the due date. Thank you!";

        try {
            $sid = $twilio->sendWhatsAppText($customerPhone, $twilioFrom, $message);
            echo "✅ WhatsApp sent to $customerPhone (SID: $sid)\n";
            file_put_contents($logFile, $logKey . PHP_EOL, FILE_APPEND);
        } catch (Exception $e) {
            echo "❌ Error sending WhatsApp: " . $e->getMessage() . "\n";
        }
    }
}
