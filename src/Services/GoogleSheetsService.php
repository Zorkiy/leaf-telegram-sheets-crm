<?php

namespace App\Services;

use Google\Client;
use Google\Service\Sheets;
use Google\Service\Sheets\ValueRange;
use Google\Service\Sheets\AppendValuesResponse;
use Leaf\Log;
use Throwable;

/**
 * Service wrapper for Google Sheets API interaction.
 */
class GoogleSheetsService
{
    private Sheets $service;
    private string $spreadsheetId;
    private Log $logger;

    /**
     * Initialize Google API client and Sheets service.
     *
     * @param array|string $authConfig Path to credentials JSON or config array.
     * @param string $spreadsheetId Target Spreadsheet ID.
     * @param Log $logger Logger instance.
     *
     * @throws Throwable If client initialization or authentication fails.
     */
    public function __construct($authConfig, string $spreadsheetId, Log $logger)
    {
        $this->spreadsheetId = $spreadsheetId;
        $this->logger = $logger;

        // Initialize Google Client
        try {
            $client = new Client();
            $client->setAuthConfig($authConfig);
            $client->addScope(Sheets::SPREADSHEETS);
            $client->setAccessType('offline');
            $this->service = new Sheets($client);
        } catch (Throwable $e) {
            $this->logger->critical("[GoogleSheetsService] Init failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Append a row of data to the specified spreadsheet range.
     *
     * @param array $values One-dimensional array of row data.
     * @param string $range A1 notation range (e.g., 'Sheet1!A:C').
     *
     * @return AppendValuesResponse API response object.
     *
     * @throws \InvalidArgumentException If row data is empty.
     * @throws Throwable On API errors or access issues.
     */
    public function appendRow(
        array $values,
        string $range = 'Sheet1!A:C'
    ): AppendValuesResponse {
        if (empty($values)) {
            $this->logger->warning("[GoogleSheetsService] Attempted to append empty row.");
            throw new \InvalidArgumentException("Row data cannot be empty");
        }

        $sanitizedValues = array_map(function ($value) {
            // Escape string if it starts with potentially unsafe characters
            if (is_string($value) && preg_match('/^[=\+\-@\t\r\n]/', $value)) {
                return "'" . $value;
            }
            return $value;
        }, $values);

        try {
            $body = new ValueRange([
                'values' => [$sanitizedValues]
            ]);

            $params = [
                'valueInputOption' => 'USER_ENTERED'
            ];

            $response = $this->service->spreadsheets_values->append(
                $this->spreadsheetId,
                $range,
                $body,
                $params
            );

            return $response;

        } catch (Throwable $e) {
            $contextData = json_encode($values, JSON_UNESCAPED_UNICODE);
            $this->logger->error(
                "[GoogleSheetsService] Failed to append row. " .
                "Error: {$e->getMessage()}. " .
                "Payload: {$contextData}"
            );

            throw $e;
        }
    }
}
