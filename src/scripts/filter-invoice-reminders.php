<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;
use Auth\TokenManager;
use Api\ZohoInvoice;
use Services\TwilioSender;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// === Load environment variables and set timezone ===
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();
date_default_timezone_set('Asia/Riyadh');

// === Prepare logging directory and file ===
$logDir = __DIR__ . '/../../log';
$logFile = $logDir . '/sent_log.txt';
$csvFile = $logDir . '/sent_log.csv';

if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
if (!file_exists($logFile)) {
    file_put_contents($logFile, '');
}
if (!file_exists($csvFile)) {
    file_put_contents($csvFile, "InvoiceID,Interval,Phone,Status,Date\n");
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
$logEntries = file($logFile, FILE_IGNORE_NEW_LINES);
$allInvoices = $zohoInvoice->getInvoices();
$paidIds = array_column(array_filter($allInvoices, fn($inv) => strtolower($inv['status']) === 'paid'), 'invoice_id');
$filteredLog = array_filter($logEntries, fn($entry) => !in_array(explode('_', $entry)[0], $paidIds));
file_put_contents($logFile, implode(PHP_EOL, $filteredLog) . PHP_EOL);

// === Step 2: Loop through all invoices and send reminders ===
foreach ($allInvoices as $invoice) {
    if (empty($invoice['due_date']) || strtolower($invoice['status']) === 'paid') continue;

    $dueDate = (new DateTime($invoice['due_date']))->setTime(0, 0);
    $interval = (int) $todayMidnight->diff($dueDate)->format('%r%a') + 1;

    if (!in_array($interval, $reminderDays)) continue;

    $logKey = "{$invoice['invoice_id']}_{$interval}";
    if (in_array($logKey, $filteredLog)) {
        echo "⚠️ Already reminded: $logKey\n";
        continue;
    }

    $rawPhone = $invoice['phone'] ?? $invoice['billing_address']['phone'] ?? $invoice['shipping_address']['phone'] ?? null;
    $customerPhone = $rawPhone ? 'whatsapp:' . $rawPhone : null;
    if (!$customerPhone) {
        echo "⚠️ Skipping invoice {$invoice['invoice_id']} — no phone\n";
        continue;
    }

    $message = "*Invoice Reminder*\n" .
               "Invoice No: *{$invoice['number']}*\n" .
               "Amount Due: *{$invoice['total']}*\n" .
               "Due Date: *{$invoice['due_date']}*\n\n" .
               "Please settle on or before the due date. Thank you!";

    $status = 'SENT';

    // ⚠️ Email fallback is disabled until SMTP credentials are provided.
    // try {
    //     $sid = $twilio->sendWhatsAppText($customerPhone, $twilioFrom, $message);
    //     echo "✅ Sent to $customerPhone (SID: $sid)\n";
    // } catch (Exception $e) {
        // echo "❌ WhatsApp failed for {$invoice['invoice_id']}. Trying email...\n";

        // $email = $invoice['customer_email'] ?? null;
        // if ($email) {
        //     try {
        //         $mail = new PHPMailer(true);
        //         $mail->isSMTP();
        //         $mail->Host = $_ENV['SMTP_HOST'];
        //         $mail->SMTPAuth = true;
        //         $mail->Username = $_ENV['SMTP_USER'];
        //         $mail->Password = $_ENV['SMTP_PASS'];
        //         $mail->SMTPSecure = 'tls';
        //         $mail->Port = 587;

        //         $mail->setFrom($_ENV['SMTP_FROM'], 'Billing Team');
        //         $mail->addAddress($email);
        //         $mail->Subject = "Invoice Reminder - {$invoice['number']}";
        //         $mail->Body = strip_tags($message);

        //         $mail->send();
        //         echo "✅ Email sent to $email\n";
        //         $status = 'EMAIL';
        //     } catch (Exception $ex) {
        //         echo "❌ Email failed for $email: " . $mail->ErrorInfo . "\n";
        //         $status = 'FAILED';
        //     }
        // } else {
        //     echo "❌ No email available for invoice {$invoice['invoice_id']}\n";
        //     $status = 'FAILED';
        // }
    

    // Log result in both text and CSV
    file_put_contents($logFile, $logKey . PHP_EOL, FILE_APPEND);
    $logRow = [
        $invoice['invoice_id'],
        $interval,
        $customerPhone ?? 'none',
        $status,
        date('Y-m-d H:i:s')
    ];
    file_put_contents($csvFile, implode(',', $logRow) . "\n", FILE_APPEND);
}
