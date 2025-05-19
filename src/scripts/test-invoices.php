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

    echo "Fetched " . count($invoices) . " unpaid invoice(s):\n";
    foreach ($invoices as $invoice) {
        echo "- Invoice #: {$invoice['invoice_number']}, Customer: {$invoice['customer_name']}, Status: {$invoice['status']}, Due: {$invoice['due_date']}\n";
    }

    if (empty($invoices)) {
        echo "⚠️ No unpaid invoices found.\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
