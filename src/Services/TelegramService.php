<?php

namespace App\Services;

use Leaf\Log;
use RuntimeException;
use Exception;

/**
 * Service for interaction with Telegram Bot API.
 */
class TelegramService
{
    private Log $logger;
    private string $token;
    private string $apiUrl = 'https://api.telegram.org/bot';

    /**
     * Initialize Telegram service.
     *
     * @param string $token Bot API access token.
     * @param Log $logger Logger instance.
     */
    public function __construct(string $token, Log $logger)
    {
        $this->logger = $logger;
        $this->token = $token;
    }

    /**
     * Send text message to a specified chat.
     *
     * @param int|string $chatId Target chat ID or @channelusername.
     * @param string $text Message text.
     * @param string $parseMode Mode for parsing entities (Markdown, HTML).
     * @return array Decoded Telegram API response.
     * @throws Exception If network or API error occurs.
     */
    public function sendMessage($chatId, string $text, string $parseMode = 'Markdown'): array
    {
        $text = $this->truncateText($text);

        $params = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode,
        ];

        try {
            return $this->sendRequest('sendMessage', $params);
        } catch (Exception $e) {
            $this->logger->error(
                "[TelegramService] Failed to send message to ChatID: {$chatId}. " .
                "Error: " . $e->getMessage()
            );

            throw $e;
        }
    }

    /**
     * Execute HTTP POST request to Telegram API via cURL.
     *
     * @param string $method API method name.
     * @param array $params Request payload parameters.
     * @return array Decoded JSON response.
     * @throws RuntimeException On cURL, JSON, or API logical errors.
     */
    private function sendRequest(string $method, array $params): array
    {
        $url = $this->apiUrl . $this->token . '/' . $method;

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $result = curl_exec($ch);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Check for cURL errors (network, DNS, timeout)
        if ($curlErrno) {
            $errorMsg = "cURL Error ({$curlErrno}): {$curlError}";
            throw new RuntimeException($errorMsg);
        }

        $decoded = json_decode($result, true);

        // Validate JSON decoding
        if (!is_array($decoded)) {
            throw new RuntimeException("JSON decode error. Status: $httpCode. Raw response: " . substr($result, 0, 200));
        }

        // Check for Telegram API logical errors
        if (($decoded['ok'] ?? false) === false) {
            $apiError = $decoded['description'] ?? 'Unknown API error';
            $errorCode = $decoded['error_code'] ?? $httpCode;
            throw new RuntimeException("Telegram API Error ($errorCode): $apiError");
        }

        return $decoded;
    }

    /**
     * Truncate message text according to Telegram API limits.
     *
     * @param string $text Input text.
     * @param int $limit Maximum allowed length.
     * @return string Truncated or original string.
     */
    private function truncateText(string $text, int $limit = 4096): string
    {
        if (mb_strlen($text) > $limit) {
            return mb_substr($text, 0, $limit);
        }
        return $text;
    }
}
