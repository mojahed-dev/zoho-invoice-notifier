<?php

namespace Auth;

class TokenManager
{
    private $clientId;
    private $clientSecret;
    private $refreshToken;

    private $baseUrl;
    private $accessToken;
    private $tokenFile = __DIR__ . '/../../access_token.json';

    public function __construct()
    {
        // $this->clientId = getenv('ZOHO_CLIENT_ID');
        // $this->clientSecret = getenv('ZOHO_CLIENT_SECRET');
        // $this->refreshToken = getenv('ZOHO_REFRESH_TOKEN');
        // $this->baseUrl = getenv('ZOHO_BASE_URL');

        $this->clientId = $_ENV['ZOHO_CLIENT_ID'] ?? null;
        $this->clientSecret = $_ENV['ZOHO_CLIENT_SECRET'] ?? null;
        $this->refreshToken = $_ENV['ZOHO_REFRESH_TOKEN'] ?? null;
        $this->baseUrl = $_ENV['ZOHO_AUTH_URL'] ?? null;

        // var_dump($this->baseUrl = 'https://www.zohoapis.com');



        $this->loadToken();
    }

    private function loadToken()
    {
        if (file_exists($this->tokenFile)) {
            $data = json_decode(file_get_contents($this->tokenFile), true);
            if (time() < $data['expires_at']) {
                $this->accessToken = $data['access_token'];
                return;
            }
        }

        $this->refreshAccessToken();
    }

    private function refreshAccessToken()
    {
        $url = $this->baseUrl . '/oauth/v2/token';

        $postFields = http_build_query([
            'refresh_token' => $this->refreshToken,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'refresh_token',
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ]);

        // Secure fix for Windows: specify path to CA cert bundle
        curl_setopt($ch, CURLOPT_CAINFO, __DIR__ . '/../certs/cacert.pem'); // Adjust path as needed

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false || $httpCode !== 200) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception("HTTP request failed with code $httpCode: " . ($error ?: $response));
        }

        curl_close($ch);
        $result = json_decode($response, true);

        echo "Zoho Response: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";

        if (isset($result['access_token'])) {
            $this->accessToken = $result['access_token'];

            file_put_contents($this->tokenFile, json_encode([
                'access_token' => $this->accessToken,
                'expires_at' => time() + 3500
            ]));
        } else {
            throw new \Exception("Failed to refresh token: " . json_encode($result));
        }
    }

    public function getAccessToken()
    {
        return $this->accessToken;
    }
}
