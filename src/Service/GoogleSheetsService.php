<?php

namespace App\Service;

use Google\Client;
use Google\Service\Sheets;

class GoogleSheetsService
{
    private Sheets $service;

    public function __construct(
        private readonly string $serviceAccountPath,
        private readonly string $spreadsheetId,
    ) {
        $client = new Client();
        $client->setAuthConfig($serviceAccountPath);
        $client->addScope(Sheets::SPREADSHEETS_READONLY);
        $this->service = new Sheets($client);
    }

    /**
     * Fetch rows from the sheet starting at a given 1-based row offset.
     * Returns an array of arrays: [date_string, action, avatar_key, display_name, username]
     *
     * @param int $fromRow 1-based row number (header is row 1, data starts at 2)
     * @param string $sheetRange Base range like "Sheet1!A:E"
     * @return array<int, array<int, string>>
     */
    public function fetchRows(int $fromRow, string $sheetRange): array
    {
        // Build a range like "Sheet1!A5:E" to start from a specific row
        [$sheet, $range] = explode('!', $sheetRange, 2);
        // Extract just the column letters from range (e.g. "A2:E" → "A:E")
        $cols = preg_replace('/\d+/', '', $range);
        [$startCol] = explode(':', $cols);
        $endCol = explode(':', $cols)[1] ?? 'E';

        $fullRange = sprintf('%s!%s%d:%s', $sheet, $startCol, $fromRow, $endCol);

        try {
            $response = $this->service->spreadsheets_values->get($this->spreadsheetId, $fullRange);
            return $response->getValues() ?? [];
        } catch (\Google\Service\Exception $e) {
            // 400 "exceeds grid limits" means fromRow is beyond the last row — nothing new to sync
            if ($e->getCode() === 400 && str_contains($e->getMessage(), 'exceeds grid limits')) {
                return [];
            }
            throw $e;
        }
    }

    /**
     * Get the total number of rows in the sheet (including header).
     */
    public function getTotalRows(string $sheetRange): int
    {
        [$sheet] = explode('!', $sheetRange, 2);
        $response = $this->service->spreadsheets_values->get($this->spreadsheetId, $sheet . '!A:A');
        return count($response->getValues() ?? []);
    }
}
