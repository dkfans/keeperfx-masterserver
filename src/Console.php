<?php 

namespace KeeperFX\MasterServer;

use React\Socket\Connection;

class Console {

    static bool $is_verbose = false;

    static function printLine(bool $type, string $message, bool $is_verbose = false): void {
        if(!$is_verbose || ($is_verbose && self::$is_verbose)) {
            echo '[' . ($type ? '+' : '-') . '] ' . $message . PHP_EOL;
        }
    }

    static function printConnLine(Connection $connection, string $text, bool $is_verbose = false): void {
        if(!$is_verbose || ($is_verbose && self::$is_verbose)) {
            echo "[{$connection->getRemoteAddress()}] {$text}" . PHP_EOL;
        }
    }

    static function setVerbose(bool $is_verbose): void
    {
        self::$is_verbose = $is_verbose;
    }

}