<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;
use Auth\TokenManager;
use Api\ZohoInvoice;

// Step 1: Load .env credentials
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

$clientId = $_ENV['ZOHO_CLIENT_ID'];
$clientSecret = $_ENV['ZOHO_CLIENT_SECRET'];
$refreshToken = $_ENV['ZOHO_REFRESH_TOKEN'];
$orgId = $_ENV['ZOHO_ORG_ID'];

// Step 2: Initialize TokenManager and ZohoInvoice
$tokenManager = new TokenManager($clientId, $clientSecret, $refreshToken);
$zohoInvoice = new ZohoInvoice($tokenManager, $orgId);

// Step 3: Call getUnpaidInvoices() and dump result
try {
    $invoices = $zohoInvoice->getInvoices();
    // var_dump(array_column($invoices, 'invoice_number')); // Should include your new INV number

   $reminderDays = [1];
        // echo "- Invoice #: {$invoice['invoice_number']}, Customer: {$invoice['customer_name']}, Status: {$invoice['status']}, Due: {$invoice['due_date']}\n";
        // echo "the date is " . ($invoice['invoice_date']  ?? 'N/A');
        


    // echo "Fetched " . count($invoices) . " unpaid invoice(s):\n";
    foreach ($invoices as $invoice) {

        $todayMidnight = (new \DateTime())->setTime(0, 0);
        $invoiceDateObj = new \DateTime($invoice['invoice_date']);
        $createdDaysAgo = (int) $invoiceDateObj->diff($todayMidnight)->format('%r%a');
        $dueDate = $invoice['due_date'] ?? 'N/A';
        $interval = $dueDate !== 'N/A'
            ? (int) $todayMidnight->diff(new \DateTime($dueDate))->format('%r%a')
            : null;

         

        $isNewInvoice = $createdDaysAgo === 0 && strtolower($invoice['status']) === 'unpaid';


        if (!in_array($interval, $reminderDays)) continue;
         var_dump(array_column($invoices, 'invoice_number'));
    }

    // if (empty($invoices)) {
    //     echo "⚠️ No unpaid invoices found.\n";
    // }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
