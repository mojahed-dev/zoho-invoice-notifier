<?php

namespace Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Services\TwilioSender;
use Auth\TokenManager;
use Services\OracleUploader;
use Services\LoggerService;
// use DateTime;

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
     * Builds a custom WhatsApp message based on interval days.
     *
     * @param array $invoice
     * @param int $interval
     * @param string $pdfUrl
     * @return string
     */
    private function generateReminderMessage(array $invoice, int $interval, string $pdfUrl): string
    {
        date_default_timezone_set('Asia/Riyadh');
        $companyPhone = '+966 920001785';
        $companyWebsite = 'https://tawasul.tech/';
        $customerName = $invoice['customer_name'] ?? 'Customer';
        $invoiceNo = $invoice['number'];
        $invoiceDate = $invoice['invoice_date'] ?? $invoice['date'] ?? 'N/A';
        $amount = $invoice['total'];
        $dueDate = $invoice['due_date'] ?? 'N/A';

        $todayMidnight = (new \DateTime())->setTime(0, 0);
        $invoiceDateObj = new \DateTime($invoiceDate);
        $createdDaysAgo = (int) $invoiceDateObj->diff($todayMidnight)->format('%r%a');

        $daysLeftText = $interval >= 0
            ? "$interval day(s) remaining until the due date: $dueDate."
            : "Overdue by " . abs($interval) . " day(s).";

        // Detect new unpaid invoice created today
        $isNewUnpaid = $createdDaysAgo === 0 && strtolower($invoice['status']) === 'unpaid';

        // Only allow overdue invoices if they are -1 to -10 days past due
        if ($interval < -10) {
            return ''; // Skip too old overdue
        }


        // Determine message level
        // if ($isNewUnpaid) {
        //     echo "NEW AND UNPAID\n";
        //     $level = '*🆕 Unpaid Invoice*';
        //     $daysLeftText = "This invoice was just issued and remains unpaid.";
        // }
        if ($interval > 20) {
            $level = '🟢 *Friendly Reminder*';
        } elseif ($interval > 5) {
            $level = '🟡 *Important Reminder*';
        } elseif ($interval >= 0) {
            $level = '🔴 *Final Reminder*';
        } elseif ($interval >= -10) {
            $level = '❗ *Overdue Invoice Notification*';
        } else {
            $level = '*Invoice Reminder*';
        }

        return "🌐 Tawasul AVL Tracking – Invoice Reminder 📄\n\n"
            . "Dear $customerName,\n\n"
            . "$level\n\n"
            . "This is a reminder for your invoice *#$invoiceNo*, dated *$invoiceDate*, with a total of *SAR $amount*.\n\n"
            . "🧾 Attached Invoice: 📎 $pdfUrl\n\n"
            . "🕒 $daysLeftText\n\n"
            . "Please ensure payment is ready onsite in cash. Our representative will collect it as scheduled.\n\n"
            . "If already paid, kindly ignore this message.\n\n"
            . "We're always here to help:\n📞 $companyPhone\n🔗 $companyWebsite";
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
            echo "⚠️ Skipping invoice {$invoice['number']} — no phone\n";
            return 'FAILED';
        }

        // $dueDate = $invoice['due_date'] ?? 'N/A';

        $fileName = "invoice_{$invoice['invoice_id']}.pdf";
        $pdfPath = "$pdfDir/$fileName";

        // Step 1: Download the invoice PDF
        $this->downloadInvoicePdf($invoice['invoice_id'], $pdfPath);

        // Step 2: Upload the PDF to Oracle and get a public or PAR link
        $pdfUrl = $this->oracleUploader->upload($pdfPath, $fileName);

        // Step 3: Compose the WhatsApp message
        $customerName = $invoice['customer_name'] ?? 'Customer';
        $invoiceNumber = $invoice['number'];
        $invoiceDate = $invoice['invoice_date'];
        $invoiceAmount = $invoice['total'];
        $dueDate = $invoice['due_date'];
        $daysLeft = $interval;

        // $message = "*Tawasul AVL Tracking – Invoice Reminder* 📄\n\n" .
        //     "Dear $customerName,\n\n" .
        //     "This is a gentle reminder from Tawasul AVL Tracking regarding your upcoming invoice #$invoiceNumber, " .
        //     "dated $invoiceDate, with a total of SAR$invoiceAmount.\n\n" .
        //     "🧾 Please find the attached invoice in PDF format for your reference.\n\n" .
        //     "🕒 $daysLeft day(s) remaining until the due date: $dueDate.\n\n" .
        //     "* Kindly ensure payment is ready onsite in cash. Our representative will collect it as scheduled.\n\n" .
        //     "If you have already made the payment, kindly ignore this message.\n\n" .
        //     "We're always here to assist if you have any questions.\n" .
        //     "📞 +966 920001785\n\n" .
        //     "Best regards,\n" .
        //     "Tawasul AVL Tracking Team\n" .
        //     "🌐 tawasul.tech\n\n" .
        //     "📎 Download Invoice: $pdfUrl";

        $message = $this->generateReminderMessage($invoice, $interval, $pdfUrl);


        try {
            $sid = $this->twilio->sendWhatsAppText($customerPhone, $_ENV['TWILIO_FROM'], $message);
            echo "✅ Sent to $customerPhone (SID: $sid) Invoice #:$invoiceNumber \n";

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
