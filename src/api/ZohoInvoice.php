<?php
namespace Api;

use Auth\TokenManager;

class ZohoInvoice
{
    private $tokenManager;
    private $orgId;

    public function __construct(TokenManager $tokenManager, $orgId)
    {
        $this->tokenManager = $tokenManager;
        $this->orgId = $orgId;
    }

    public function getInvoices()
    {
        $accessToken = $this->tokenManager->getAccessToken();
        $url = 'https://www.zohoapis.com/billing/v1/invoices'; 


        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Zoho-oauthtoken {$accessToken}",
                "X-com-zoho-subscriptions-organizationid: {$this->orgId}"
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);

        if ($httpCode !== 200) {
            throw new \Exception("Error fetching invoices: $httpCode\n$response");
        }

        return $data['invoices'] ?? [];
    }
}
