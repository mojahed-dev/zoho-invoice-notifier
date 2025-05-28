<?php

namespace Services;

use Services\LoggerService;

class OracleUploader
{
    private string $uploadBaseUrl;

    public function __construct()
    {
        // PAR link with read and write access for invoice uploads and downloads
        $this->uploadBaseUrl = $_ENV['ORACLE_PAR_UPLOAD_URL'];
    }

    /**
     * Uploads a file using Oracle's Pre-Authenticated Request (PAR) URL
     *
     * @param string $filePath Full local path to the file
     * @param string $fileName Name to save the object as in the bucket
     * @return string Pre-authenticated URL to access the uploaded file
     */
    public function upload(string $filePath, string $fileName): string
    {
        $targetUrl = $this->uploadBaseUrl . rawurlencode($fileName);

        $file = fopen($filePath, 'r');
        $ch = curl_init($targetUrl);

        curl_setopt_array($ch, [
            CURLOPT_PUT => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_INFILE => $file,
            CURLOPT_INFILESIZE => filesize($filePath),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/pdf'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($file);

        if ($httpCode >= 200 && $httpCode < 300) {
            // Return the same PAR-based URL for downloading
            return $targetUrl;
        } else {
            echo "❌ Failed to upload PDF to Oracle (HTTP $httpCode)\n";
            return '';
        }
    }

    /**
     * Helper to format message log row
     *
     * @param string $invoiceId
     * @param string $invoiceNumber
     * @param string $status (SENT, FAILED, EMAIL, etc)
     * @param string $method (whatsapp or email)
     * @param string $phone
     * @param string $message
     * @return array CSV row as array values
     */
    public static function formatLogRow(string $invoiceId, string $invoiceNumber, string $status, string $method, string $phone, string $message, int $interval, string $dueDate): array
    {
        $timestamp = date('Y-m-d H:i:s');
        $msgShort = str_replace(["\n", "\r"], ' ', mb_substr($message, 0, 100));
        // return [$timestamp, $invoiceId, $invoiceNumber, $interval, $status, $method, $phone, $msgShort];
        return [
            $timestamp,
            '="' . $invoiceId . '"',
            '="' . $invoiceNumber . '"',
            $interval,
            $dueDate,
            $status,
            $method,
            '="' . $phone . '"',
            $msgShort
        ];
    }
}
