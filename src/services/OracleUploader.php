<?php

namespace Services;

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
     * @param string $filePath Full local path to the file
     * @param string $fileName Name to save the object as in the bucket
     * @return string Public URL to access the uploaded file
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
            // Construct public URL for download (you can use a different base if needed)
            // return "https://objectstorage.me-jeddah-1.oraclecloud.com/n/ax7xwlfakloa/b/invoice_pdfs/o/" . rawurlencode($fileName);
            return $this->uploadBaseUrl . rawurlencode($fileName);
        } else {
            echo "❌ Failed to upload PDF to Oracle (HTTP $httpCode)\n";
            return '';
        }
    }
}
