<?php

namespace Services;

/**
 * LoggerService handles logging for invoice reminders.
 * It manages both plain text logs and CSV logs to track which invoices have been reminded.
 */
class LoggerService
{
    private string $logFile;
    private string $csvFile;

    /**
     * LoggerService constructor.
     *
     * @param string $logFile Path to the plain text log file.
     * @param string $csvFile Path to the CSV log file.
     */
    public function __construct(string $logFile, string $csvFile)
    {
        $this->logFile = $logFile;
        $this->csvFile = $csvFile;
    }

    /**
     * Get all entries from the plain text log.
     *
     * @return array List of log lines, one per reminder sent.
     */
    public function getLogEntries(): array
    {
        return file_exists($this->logFile) ? file($this->logFile, FILE_IGNORE_NEW_LINES) : [];
    }

    /**
     * Removes log entries for invoices that have already been marked as paid.
     *
     * @param array $invoices List of invoices retrieved from Zoho.
     */
    public function cleanPaidEntries(array $invoices): void
    {
        $entries = $this->getLogEntries();

        $paidIds = array_column(
            array_filter($invoices, fn($inv) => strtolower($inv['status']) === 'paid'),
            'invoice_id'
        );

        $filtered = array_filter($entries, fn($entry) =>
            !in_array(explode('_', $entry)[0], $paidIds)
        );

        file_put_contents($this->logFile, implode(PHP_EOL, $filtered) . PHP_EOL);
    }

    /**
     * Appends a single entry to the plain text log file.
     *
     * @param string $entry The log entry string (e.g., invoiceID_interval).
     */
    public function appendLogEntry(string $entry): void
    {
        file_put_contents($this->logFile, $entry . PHP_EOL, FILE_APPEND);
    }

    /**
     * Appends a row of data to the CSV log file.
     *
     * @param array $row An array representing a row of log data.
     */
    public function appendCsvEntry(array $row): void
    {
        file_put_contents($this->csvFile, implode(',', $row) . "\n", FILE_APPEND);
    }
}
