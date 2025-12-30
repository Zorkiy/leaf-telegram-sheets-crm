<?php

define('DIR_ROOT', dirname(__DIR__));

require DIR_ROOT . '/vendor/autoload.php';

use App\Services\GoogleSheetsService;
use App\Services\TelegramService;
use App\Controllers\WebhookController;
use Leaf\Log;
use Leaf\LogWriter;

// Environment configuration
$dotenv = Dotenv\Dotenv::createImmutable(DIR_ROOT);
$dotenv->load();

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'UTC');

// Logger initialization (Manual DI)
$logDir = DIR_ROOT . '/logs';
$logPath = $logDir . '/app.log';
$logWriter = new LogWriter($logPath);
$logger = new Log($logWriter);
$logger->enabled(true);

if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

// Global Error Handling

// Exception handling
set_exception_handler(function ($th) use ($logger) {
    $logger->error("Uncaught Exception: " . $th->getMessage());
    $logger->error("Stack trace: " . $th->getTraceAsString());

    if (!headers_sent()) {
        http_response_code(500);
        response()->json(['status' => 'error', 'message' => 'Internal Server Error']);
        exit;
    }
});

// PHP Error handling (Warnings, Notices)
set_error_handler(function ($errno, $errstr, $errfile, $errline) use ($logger) {
    // Ignore errors suppressed by @ operator
    if (!(error_reporting() & $errno)) {
        return false;
    }

    $errorType = match ($errno) {
        E_WARNING => 'WARNING',
        E_NOTICE => 'NOTICE',
        E_PARSE => 'PARSE ERROR',
        default => "UNKNOWN ERROR ($errno)"
    };

    $logger->error("[$errorType]: $errstr in $errfile:$errline");

    return true;
});

// Fatal error handling (Shutdown function)
register_shutdown_function(function () use ($logger) {
    $error = error_get_last();

    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $logger->critical("FATAL ERROR: {$error['message']} in {$error['file']}:{$error['line']}");
    }
});

// Database initialization
$setupDb = require DIR_ROOT . '/db/bootstrap.php';
try {
    $setupDb();
} catch (\Throwable $th) {
    $logger->critical("Database connection failed: " . $th->getMessage());
    die("Database Error");
}

// Service initialization (Dependency Injection)
try {
    $tgBot = new TelegramService($_ENV['TG_BOT_TOKEN'] ?? '', $logger);

    $googleSheets = new GoogleSheetsService(
        DIR_ROOT . '/' . ($_ENV['GOOGLE_CREDENTIALS_FILE'] ?? DIR_ROOT . '/credentials.json'),
        $_ENV['GOOGLE_SHEET_ID'] ?? '',
        $logger
    );

    $controller = new WebhookController($tgBot, $googleSheets, $logger);

} catch (\Throwable $e) {
    $logger->emergency("Service Initialization Failed: " . $e->getMessage());
    throw $e;
}

// Routing

app()->get('/', function() {
    response()->json(['status' => 'success', 'message' => 'Leaf Bot is running!']);
});

app()->post('/webhook', [$controller, 'handle']);

app()->run();
