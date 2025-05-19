<?php
// Simple output to check script is working
echo "Notification script ran at " . date('Y-m-d H:i:s') . PHP_EOL;

// Also write to a log file for confirmation
file_put_contents(__DIR__ . '/log.txt', "Script ran at " . date('Y-m-d H:i:s') . PHP_EOL, FILE_APPEND);
