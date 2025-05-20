<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;
use Twilio\Rest\Client;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

$twilio = new Client($_ENV['TWILIO_SID'], $_ENV['TWILIO_TOKEN']);

$from = 'whatsapp:+14155238886';
$to = 'whatsapp:+639651582228'; // YOUR WhatsApp number

try {
    $message = $twilio->messages->create($to, [
        'from' => $from,
        'body' => "✅ Test message from Twilio Sandbox."
    ]);
    echo "✅ Sent. SID: {$message->sid}\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
