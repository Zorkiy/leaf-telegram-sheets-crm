<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\GoogleSheetsService;
use App\Services\TelegramService;
use Leaf\Log;

/**
 * Controller for handling Telegram Bot Webhook requests.
 * Manages update validation, idempotency, database logging,
 * and external service integration.
 */
class WebhookController
{
    private Log $logger;
    private TelegramService $tgBot;
    private GoogleSheetsService $googleSheets;

    /**
     * Initialize controller with dependencies.
     * @param TelegramService $tgBot Service for Telegram Bot API.
     * @param GoogleSheetsService $googleSheets Service for Google Sheets API.
     * @param Log $logger Logging component.
     */
    public function __construct(
        TelegramService $tgBot,
        GoogleSheetsService $googleSheets,
        Log $logger
    ) {
        $this->logger = $logger;
        $this->tgBot = $tgBot;
        $this->googleSheets = $googleSheets;
    }

    /**
     * Main webhook processing entry point.
     * @return void
     */
    public function handle(): void
    {
        // Security check: Secret Token validation

        $localSecret = $_ENV['TG_WEBHOOK_SECRET'] ?? '';

        $remoteToken = request()->headers('X-Telegram-Bot-Api-Secret-Token');

        if (empty($localSecret) || !hash_equals($localSecret, (string)$remoteToken)) {
            $this->logger->error("Security Alert: Unauthorized webhook access attempt.");
            response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
            return;
        }

        $data = request()->body();

        // Validate update structure
        if (!isset($data['update_id'])) {
            $this->logger->warning("Invalid webhook data received: missing update_id");
            response()->json(['status' => 'error', 'message' => 'No update_id'], 400);
            return;
        }

        $updateId = (int) $data['update_id'];
        $chatId   = isset($data['message']['chat']['id']) ? (int) $data['message']['chat']['id'] : null;
        $text     = (string) ($data['message']['text'] ?? '');
        $username = (string) ($data['message']['from']['username'] ?? 'Unknown');

        // Idempotency check
        $exists = db()->select('telegram_updates')
            ->where('update_id', $updateId)
            ->first();

        if ($exists) {
            $this->logger->info("Update $updateId already processed. Skipping.");
            response()->json(['status' => 'skipped', 'message' => 'Already processed']);
            return;
        }

        // Log update to database
        $this->logUpdateToDb($updateId, $chatId, $username, $text, $data);

        // Integration: Google Sheets
        $this->processGoogleSheets($username, $text);

        // Telegram response
        if ($chatId !== null) {
            $this->sendReply($chatId, "Дякую! Ваше повідомлення прийнято.");
        }

        response()->json(['status' => 'success']);
    }

    /**
     * Store raw update data and metadata in the database.
     * @param int $updateId Unique Telegram update identifier.
     * @param int|null $chatId Chat ID.
     * @param string $username User handle.
     * @param string $text Message text.
     * @param array $raw Full request payload.
     * @return void
     */
    private function logUpdateToDb(
        int $updateId,
        ?int $chatId,
        string $username,
        string $text,
        array $raw
    ): void {
        try {
            db()->insert('telegram_updates')
                ->params([
                    'update_id'    => $updateId,
                    'chat_id'      => $chatId,
                    'username'     => $username,
                    'message_text' => $text,
                    'raw_data'     => json_encode($raw, JSON_UNESCAPED_UNICODE)
                ])->execute();
        } catch (\Throwable $e) {
            $this->logger->error("Database Error (logUpdate): " . $e->getMessage());
        }
    }

    /**
     * Send interaction data to Google Sheets.
     * @param string $username Telegram username.
     * @param string $text Received message text.
     * @return void
     */
    private function processGoogleSheets(string $username, string $text): void
    {
        try {
            $this->googleSheets->appendRow([
                date('Y-m-d H:i:s'),
                $username,
                $text
            ]);
        } catch (\Throwable $e) {
            $this->logger->error("Google Sheets Integration Failed: " . $e->getMessage());
        }
    }

    /**
     * Send text reply via Telegram Bot API.
     * @param int $chatId Target chat identifier.
     * @param string $responseText Message text to send.
     * @return void
     */
    private function sendReply(int $chatId, string $responseText): void
    {
        $response = $this->tgBot->sendMessage($chatId, $responseText);

        $isOk = $response['ok'] ?? false;

        if (!$isOk) {
            $this->logger->error("Telegram API Error for Chat $chatId: " . json_encode($response));
        } else {
            $this->logger->info("Reply sent to Chat $chatId");
        }
    }
}
