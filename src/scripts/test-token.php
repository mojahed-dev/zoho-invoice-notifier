<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;
use Auth\TokenManager;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

$tokenManager = new TokenManager();
echo "Access Token: " . $tokenManager->getAccessToken() . PHP_EOL;
echo "Access Token: " . $tokenManager->getAccessToken() . PHP_EOL;
