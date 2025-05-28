<?php

namespace Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Services\TwilioSender;
use Auth\TokenManager;
use Services\OracleUploader;
use Services\LoggerService;

/**
 * NotificationService handles sending reminders via WhatsApp or email.
 * It downloads invoice PDFs, uploads to Oracle, and manages fallback logic.
 */
class NotificationService
{
    private TwilioSender $twilio;
    private TokenManager $tokenManager;
    private OracleUploader $oracleUploader;
    private LoggerService $logger;

    /**
     * NotificationService constructor.
     *
     * @param TwilioSender $twilio       The Twilio sending service.
     * @param TokenManager $tokenManager Token manager for Zoho authentication.
     */
    public function __construct(TwilioSender $twilio, TokenManager $tokenManager, LoggerService $logger)
    {
        $this->twilio = $twilio;
        $this->tokenManager = $tokenManager;
        $this->oracleUploader = new OracleUploader();
        $this->logger = $logger;
    }

    /**
     * Sends a WhatsApp reminder for a given invoice. Falls back to email if WhatsApp fails.
     *
     * @param array $invoice Invoice data from Zoho.
     * @param int $interval   The reminder interval in days.
     * @param string $pdfDir  Directory path to save the invoice PDF.
     * @return string         'SENT', 'EMAIL', or 'FAILED' status.
     */
    public function sendReminder(array $invoice, int $interval, string $pdfDir): string
    {
        $rawPhone = $invoice['phone'] ?? $invoice['billing_address']['phone'] ?? $invoice['shipping_address']['phone'] ?? null;
        $customerPhone = $rawPhone ? 'whatsapp:' . $rawPhone : null;
        if (!$customerPhone) {
            echo "⚠️ Skipping invoice {$invoice['invoice_id']} — no phone\n";
            return 'FAILED';
        }

        $dueDate = $invoice['due_date'] ?? 'N/A';

        $fileName = "invoice_{$invoice['invoice_id']}.pdf";
        $pdfPath = "$pdfDir/$fileName";

        // Step 1: Download the invoice PDF
        $this->downloadInvoicePdf($invoice['invoice_id'], $pdfPath);

        // Step 2: Upload the PDF to Oracle and get a public or PAR link
        $pdfUrl = $this->oracleUploader->upload($pdfPath, $fileName);

        // Step 3: Compose the WhatsApp message
        $message = "*Invoice Reminder*\n" .
            "Invoice No: *{$invoice['number']}*\n" .
            "Amount Due: *{$invoice['total']}*\n" .
            "Due Date: *{$invoice['due_date']}*\n\n" .
             '="' . "📎 Download Invoice: $pdfUrl\n\n" .
            "Please settle on or before the due date. Thank you!";

        try {
            $sid = $this->twilio->sendWhatsAppText($customerPhone, $_ENV['TWILIO_FROM'], $message);
            echo "✅ Sent to $customerPhone (SID: $sid)\n";

            $this->logger->appendCsvEntry(
                OracleUploader::formatLogRow(
                    $invoice['invoice_id'],
                    $invoice['number'],
                    'SENT',
                    'whatsapp',
                    $customerPhone,
                    $message,
                    $interval, 
                    $dueDate
                )
            );

            return 'SENT';
        } catch (\Exception $e) {
            echo "❌ WhatsApp failed for {$invoice['invoice_id']}. Trying email...\n";

            $this->logger->appendCsvEntry(
                OracleUploader::formatLogRow(
                    $invoice['invoice_id'],
                    $invoice['number'],
                    'FAILED',
                    'whatsapp',
                    $customerPhone,
                    $message,
                    $interval,
                    $dueDate
                )
            );

            return $this->sendEmailFallback($invoice, $message);
        }
    }

    /**
     * Downloads the PDF version of a Zoho invoice and saves it locally.
     *
     * @param string $invoiceId The Zoho invoice ID.
     * @param string $pdfPath   File path to save the downloaded PDF.
     */
    private function downloadInvoicePdf(string $invoiceId, string $pdfPath): void
    {
        try {
            $accessToken = $this->tokenManager->getAccessToken();
            $url = "https://www.zohoapis.sa/billing/v1/invoices/$invoiceId?accept=pdf";

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    "Authorization: Zoho-oauthtoken $accessToken",
                    "X-com-zoho-subscriptions-organizationid: {$_ENV['ZOHO_ORG_ID']}"
                ]
            ]);
            $pdfContent = curl_exec($ch);
            curl_close($ch);

            file_put_contents($pdfPath, $pdfContent);
        } catch (\Exception $e) {
            echo "❌ Failed to download PDF for invoice $invoiceId\n";
        }
    }

    /**
     * Sends a fallback email with the invoice reminder if WhatsApp fails.
     *
     * @param array $invoice Invoice data from Zoho.
     * @param string $message The message to send.
     * @return string         'EMAIL' or 'FAILED' status.
     */
    private function sendEmailFallback(array $invoice, string $message): string
    {
        // Email fallback logic can be implemented here
        return 'FAILED';
    }
}
