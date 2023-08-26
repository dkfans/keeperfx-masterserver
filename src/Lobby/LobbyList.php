<?php

namespace KeeperFX\MasterServer\Lobby;

use Xenokore\Utility\Helper\StringHelper;
use KeeperFX\MasterServer\Console;

class LobbyList {

    // Singleton
    public static array $lobbies = [];

    public static function getPublicLobbyData(): array
    {
        $return = [];

        foreach(self::$lobbies as $lobby_id => $lobby) {
            $return[] = [
                'name'         => (string) $lobby->name,
                'ip'           => (string) $lobby->ip,
                'port'         => (int)    $lobby->port,
                'players'      => (array)  $lobby->players,
                'status'       => (string) $lobby->status->value,
                'has_password' => (bool)   $lobby->has_password,
                'game_version' => (string) $lobby->game_version,
            ];
        }

        return $return;
    }

    public static function generateUniqueLobbyToken(): string
    {
        do {
            $token = StringHelper::generate(16);
        } while (\array_key_exists($token, self::$lobbies));

        return $token;
    }

    public static function createLobby(
        string $name,
        string $ip,
        string $port,
        array $players = [],
        bool $has_password = false,
        ?string $game_version = null,
    ): Lobby
    {
        $lobby = new Lobby(
            name        : $name,
            ip          : $ip,
            port        : $port,
            token       : self::generateUniqueLobbyToken(),
            players     : $players,
            status      : LobbyStatus::OPEN,
            has_password: $has_password,
            game_version: $game_version
        );

        self::$lobbies[$lobby->token] = $lobby;

        return $lobby;
    }

    public static function removeStaleLobbies(): array
    {
        $stale_lobby_tokens = [];

        $current_timestamp = (new \DateTime('now'))->getTimestamp();

        // Loop trough active lobbies
        foreach(self::$lobbies as $token => $lobby){
    
            // Calculate difference
            $lobby_heartbeat_timestamp = $lobby->timestamp->getTimestamp();
            $timestamp_difference      = $current_timestamp - $lobby_heartbeat_timestamp;
    
            // Remove stale lobbies
            if($timestamp_difference > KEEPALIVE_TIME_SECONDS){
                if(array_key_exists($token, self::$lobbies)){
                    unset(self::$lobbies[$token]);
                    $stale_lobby_tokens[] = $token;
                }
            }
        }

        return $stale_lobby_tokens;
    }

}