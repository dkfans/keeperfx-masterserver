<?php 

namespace KeeperFX\MasterServer;

use React\Socket\Connection;

class Console {

    static function printLine(bool $type, string $message): void {
        echo '[' . ($type ? '+' : '-') . '] ' . $message . PHP_EOL;
    }

    static function printConnLine(Connection $connection, string $text): void {
        echo "[{$connection->getRemoteAddress()}] {$text}" . PHP_EOL;
    }

}