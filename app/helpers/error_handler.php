<?php
/**
 * Global error & exception handler.
 * Logs to /storage/logs/app.log — never exposes raw errors to users.
 */

define('LOG_FILE', __DIR__ . '/../../storage/logs/app.log');

function _lumina_write_log(string $type, string $message, string $file = '', int $line = 0): void
{
    $dir = dirname(LOG_FILE);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $entry = sprintf(
        "[%s] [%s] %s | %s:%d\n",
        date('Y-m-d H:i:s'),
        $type,
        $message,
        $file,
        $line
    );
    @file_put_contents(LOG_FILE, $entry, FILE_APPEND | LOCK_EX);
}

set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    $types = [
        E_ERROR             => 'E_ERROR',
        E_WARNING           => 'E_WARNING',
        E_NOTICE            => 'E_NOTICE',
        E_USER_ERROR        => 'E_USER_ERROR',
        E_USER_WARNING      => 'E_USER_WARNING',
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
    ];
    $type = $types[$errno] ?? "E_UNKNOWN({$errno})";
    _lumina_write_log($type, $errstr, $errfile, $errline);
    return false;
});

set_exception_handler(function (Throwable $e): void {
    _lumina_write_log(get_class($e), $e->getMessage(), $e->getFile(), $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo '<div style="font-family:sans-serif;padding:2rem;color:#721c24;background:#f8d7da;border:1px solid #f5c6cb;border-radius:4px;max-width:500px;margin:2rem auto">'
       . '<strong>An unexpected error occurred.</strong><br>Please try again or contact support.'
       . '</div>';
    exit(1);
});

register_shutdown_function(function (): void {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        _lumina_write_log('FATAL', $err['message'], $err['file'], $err['line']);
    }
});
