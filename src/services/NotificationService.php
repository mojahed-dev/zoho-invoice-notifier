<?php

namespace Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Services\TwilioSender;
use Auth\TokenManager;

/**
 * NotificationService handles sending reminders via WhatsApp or email.
 * It downloads invoice PDFs, constructs messages, and manages fallback logic.
 */
class NotificationService
{
    private TwilioSender $twilio;
    private TokenManager $tokenManager;

    /**
     * NotificationService constructor.
     *
     * @param TwilioSender $twilio       The Twilio sending service.
     * @param TokenManager $tokenManager Token manager for Zoho authentication.
     */
    public function __construct(TwilioSender $twilio, TokenManager $tokenManager)
    {
        $this->twilio = $twilio;
        $this->tokenManager = $tokenManager;
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

        $pdfPath = "$pdfDir/invoice_{$invoice['invoice_id']}.pdf";
        $pdfUrl = $this->downloadInvoicePdf($invoice['invoice_id'], $pdfPath);

        $message = "*Invoice Reminder*\n" .
                   "Invoice No: *{$invoice['number']}*\n" .
                   "Amount Due: *{$invoice['total']}*\n" .
                   "Due Date: *{$invoice['due_date']}*\n\n" .
                   "📎 Download Invoice: $pdfUrl\n\n" .
                   "Please settle on or before the due date. Thank you!";

        try {
            $sid = $this->twilio->sendWhatsAppText($customerPhone, $_ENV['TWILIO_FROM'], $message);
            echo "✅ Sent to $customerPhone (SID: $sid)\n";
            echo "Invoice No: *{$invoice['number']}*\n";
            echo "Amount Due: *{$invoice['total']}*\n" ;
            echo  "Due Date: *{$invoice['due_date']}*\n\n";
            return 'SENT';
        } catch (\Exception $e) {
            echo "❌ WhatsApp failed for {$invoice['invoice_id']}. Trying email...\n";
            return $this->sendEmailFallback($invoice, $message);
        }
    }

    /**
     * Downloads the PDF version of a Zoho invoice and saves it locally.
     *
     * @param string $invoiceId The Zoho invoice ID.
     * @param string $pdfPath   File path to save the downloaded PDF.
     * @return string            Public URL of the PDF (currently a placeholder).
     */
    private function downloadInvoicePdf(string $invoiceId, string $pdfPath): string
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
            return "https://your-oracle-bucket-url/invoices/invoice_{$invoiceId}.pdf"; // placeholder
        } catch (\Exception $e) {
            echo "❌ Failed to download PDF for invoice $invoiceId\n";
            return '';
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
        // Uncomment and implement this method when SMTP credentials are available
    }
}
