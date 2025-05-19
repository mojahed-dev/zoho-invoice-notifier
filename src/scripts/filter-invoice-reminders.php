<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;
use Auth\TokenManager;
use Api\ZohoInvoice;

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
$reminderDays = [15, 13, 11, 9, 7, 5, 3, 1];

// echo "Invoices needing reminders:\n";

foreach ($invoices as $invoice) {
    $dueDate = new DateTime($invoice['due_date']);  // Extract due date
    $interval = (int) $today->diff($dueDate)->format('%r%a'); // Days until due

    // Define the reminder schedule
    // $reminderDays = [10, 7, 5, 3, 1];
    //  print_r($interval);

    print_r($invoices);
    exit;


    if ($invoice['status'] !== 'paid' && in_array($interval, $reminderDays)) {
        // Extract customer phone from correct field
        $customerPhone = $invoice['phone'] ??
        $invoice['billing_address']['phone'] ??
        $invoice['shipping_address']['phone'] ??
        null;

          print_r($customerPhone);
          exit;

        if (!$customerPhone) {
            echo "Skipping invoice {$invoice['invoice_id']}, no phone number found.\n";
            continue;
        }

        // echo "DEBUG: Customer phone number for Invoice ID {$invoice['invoice_id']} => {$customerPhone}\n";

        // $message = "Reminder: Invoice {$invoice['invoice_id']} is due on {$invoice['due_date']}. 
        // Please pay to avoid penalties. Contact support if needed.";

        // Send SMS
        // $smsResponse = $infobipService->sendSMS($customerPhone, $message);

        // Print log
        echo "Sent reminder for Invoice ID: {$invoice['invoice_id']} to {$customerPhone}\n";
        print_r($smsResponse);
    }
}
