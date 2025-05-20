<?php
namespace Services;

use Twilio\Rest\Client;

class TwilioSender
{
    private $twilio;

    public function __construct(string $sid, string $token)
    {
        $this->twilio = new Client($sid, $token);
    }

    public function sendWhatsAppText(string $to, string $from, string $message): string
    {
        $msg = $this->twilio->messages->create($to, [
            'from' => $from,
            'body' => $message
        ]);

        return $msg->sid;
    }
    
}
