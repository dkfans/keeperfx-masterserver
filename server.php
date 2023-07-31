<?php

/**
 * KeeperFX Masterserver
 * 
 * Author: Yani <yani@protonmail.com>
 * License: GNU
 * Date: 13/6/2023
 */

define('PROTOCOL_VERSION', 1);
define('KEEPALIVE_TIME_SECONDS', 20); // 2 minutes = 120

use KeeperFX\MasterServer\Console;
use KeeperFX\MasterServer\Lobby\Lobby;
use KeeperFX\MasterServer\Lobby\LobbyList;
use KeeperFX\MasterServer\Lobby\LobbyStatus;
use KeeperFX\MasterServer\Player\Player;
use KeeperFX\MasterServer\Player\PlayerColor;
use KeeperFX\MasterServer\Packet\IncomingPacket;
use KeeperFX\MasterServer\Packet\ResponsePacket;
use KeeperFX\MasterServer\Exception\PacketNoMethodException;
use KeeperFX\MasterServer\Exception\PacketInvalidJsonException;
use Xenokore\Utility\Helper\StringHelper;
use React\EventLoop\Loop;

// Load libraries
require __DIR__ . '/vendor/autoload.php';

// Get IP from command line options
$ip   = $argv[1] ?? '127.0.0.1';
$port = $argv[2] ?? '0';
$listen_address = $ip . ':' . $port;

// Check last argument
$last_arg = $argv[3] ?? null;
if($last_arg && $last_arg === '-v'){
    Console::setVerbose(true);
}

// Show startup message
Console::printLine(true, 'KeeperFX MasterServer', false);
Console::printLine(true, 'Protocol version: '. PROTOCOL_VERSION, true);

// Create the socket server
$socket = new React\Socket\SocketServer($listen_address, []);
if(!$socket){
    Console::printLine(false, 'Failed to setup masterserver');
    return 0;
}

// Handle a connection
$socket->on('connection', function (React\Socket\ConnectionInterface $connection) use ($socket) {

    Console::printConnLine($connection, 'connected!', true);

    // Send a "keeperfx=true" packet.
    // This does 2 things:
    //     - It tells the client this TCP server is definitely the KeeperFX masterserver
    //     - It makes sure the initial protocol version is broadcast. (Because the version is added to each packet)
    $connection->write(ResponsePacket::create(['keeperfx' => true]));

    // Handle all packets of this client
    $connection->on('data', function ($data) use ($connection, $socket, &$client, &$master_server) {

        // Empty packet?
        if(!$data || !\is_string($data) || \strlen(trim($data)) < 1){
            return;
        }

        // Create an incoming packet from the incoming JSON data
        try {
            $packet = IncomingPacket::create($data);

        } catch (PacketInvalidJsonException $ex) {
            $connection->write(ResponsePacket::createError('INVALID_JSON'));
            Console::printConnLine($connection, 'unable to JSON decode the packet', true);
            return;

        } catch (PacketNoMethodException $ex) {
            $connection->write(ResponsePacket::createError('NO_METHOD'));
            Console::printConnLine($connection, 'missing method', true);
            return;
        }

        // Check for stale lobbies on data
        $stale_lobby_tokens = LobbyList::removeStaleLobbies();
        $stale_lobbies_count = \count($stale_lobby_tokens);
        if($stale_lobbies_count > 0){
            Console::printLine(true, (($stale_lobbies_count === 1) ? 'lobby' : 'lobbies') . " became stale: "  . \implode(', ', $stale_lobby_tokens), false);
        }

        //////////////////////////////////////////////////////////////////////////////////////////////////////////////
        // Handle packets that don't specify a lobby
        //////////////////////////////////////////////////////////////////////////////////////////////////////////////

        // Handle keepalive
        if($packet->method === 'ping'){
            $connection->write(ResponsePacket::create(['pong' => true]));
            return;
        }

        // Create lobby
        if($packet->method === 'create_lobby'){

            // Get IP address (lobby & host player)
            $address = $connection->getRemoteAddress();
            $ip = \trim(\parse_url($address, PHP_URL_HOST), '[]');

            // Get player name for host
            $host_player_name = 'Keeper';
            if(isset($packet->data['player_name']) && \is_string($packet->data['player_name'])) {
                $host_player_name = $packet->data['player_name'];
            }

            // Get lobby name
            $lobby_name = $host_player_name . "'s Lobby";
            if(isset($packet->data['name']) && \is_string($packet->data['name'])) {
                $lobby_name = $packet->data['name'];
            }

            // Create player object
            $host_player = new Player(
                name:  $host_player_name,
                ip:    $ip,
                color: PlayerColor::RED,
            );

            // Get lobby port
            $port = 5556;
            if(isset($packet->data['port']) && \is_numeric($packet->data['port'])) {
                $port = (int) $packet->data['port'];
            }

            // Get game version
            $game_version = null;
            if(isset($packet->data['game_version']) && \is_string($packet->data['game_version'])) {
                $game_version = $packet->data['game_version'];
            }
            
            // Create lobby
            $lobby = LobbyList::createLobby($lobby_name, $ip, $port, [$host_player], false, $game_version);

            $connection->write(ResponsePacket::create(['token' => $lobby->token]));
            Console::printConnLine($connection, "created lobby for [{$host_player_name}] -> {$lobby_name}", false);
            return;
        }

        // List all lobbies
        if($packet->method === 'list_lobbies'){

            $lobby_data  = LobbyList::getPublicLobbyData();
            $lobby_count = \count($lobby_data);

            $connection->write(ResponsePacket::create(['lobbies' => $lobby_data]));

            Console::printConnLine($connection, "asked for lobby list -> {$lobby_count} lobbies returned", true);
            return;
        }

        //////////////////////////////////////////////////////////////////////////////////////////////////////////////
        // Handle packets that specify a lobby
        //////////////////////////////////////////////////////////////////////////////////////////////////////////////

        // Lobby methods
        // These are grouped because all of these require a TOKEN check
        if(\in_array($packet->method, [
            'update_lobby',
            'remove_lobby',
            'keepalive_lobby',
        ])) {

            // Make sure a token is set
            if(!isset($packet->data['token'])) {
                Console::printConnLine($connection, "keepalive but no lobby token");
                $connection->write(ResponsePacket::createError('NO_LOBBY_TOKEN'));
                return;
            }

            // Make sure token is valid
            if(!is_string($packet->data['token'])) {
                Console::printConnLine($connection, "invalid token", false);
                $connection->write(ResponsePacket::createError('INVALID_LOBBY_TOKEN'));
                return;
            }

            // Make sure token exists
            if(!isset(LobbyList::$lobbies[$packet->data['token']])) {
                Console::printConnLine($connection, "tried to interact with non existing lobby: {$packet->data['token']}", false);
                $connection->write(ResponsePacket::createError('LOBBY_NOT_FOUND'));
                return;
            }

            // Load the lobby
            $lobby = LobbyList::$lobbies[$packet->data['token']];

            // Update lobby
            // The lobby is updated by the host on these events:
            //   - Game starts/ends
            //   - Players join/leave
            //   - Name is changed?
            if($packet->method === 'update_lobby'){

                $lobby_update_success = false;

                // Update the lobby status
                if(\array_key_exists('status', $packet->data) && \is_string($packet->data['status'])) {

                    // Get status enum or fail
                    $status = LobbyStatus::tryFrom($packet->data['status']);
                    if(!$status){
                        Console::printConnLine($connection, "invalid status: {$packet->data['status']}", false);
                        $connection->write(ResponsePacket::createError('INVALID_LOBBY_STATUS'));
                        return;
                    }

                    $lobby->status = $status;
                    $lobby_update_success = true;
                }

                // Update the players
                if(\array_key_exists('players', $packet->data) && \is_array($packet->data['players'])) {

                    // Get array for new players
                    $players = [];
                    foreach($packet->data['players'] as $player)
                    {
                        $players[] = new Player(
                            name: $player['name'] ?? 'Keeper',
                            ip: $player['ip'] ?? null,
                            color: PlayerColor::tryFrom($player['color'] ?? '') ?? null
                        );
                    }

                    // Update players
                    $lobby->players = $players;
                    $lobby_update_success = true;
                }

                // Update the lobby name
                if(\array_key_exists('name', $packet->data) && \is_string($packet->data['name'])) {
                    $lobby->name = $packet->data['name'];
                    $lobby_update_success = true;
                }

                if($lobby_update_success){
                    $lobby->timestamp = new \DateTime('now'); // also update heartbeat
                    $connection->write(ResponsePacket::createSuccess());
                } else {
                    $connection->write(ResponsePacket::createError("NOTHING_UPDATED_FOR_LOBBY"));
                }

                $lobby->timestamp = new \DateTime('now');

                return;
            }

            // Remove lobby
            // This can only be done by the host who will have the correct token.
            // Events where this packet will be sent:
            //   - Close lobby
            //   - Close game
            //   - Crash
            if($packet->method === 'remove_lobby'){
                unset(LobbyList::$lobbies[$packet->data['token']]);
                $connection->write(ResponsePacket::createSuccess());
                return;
            }

            // Keepalive Lobby.
            // This updates the last heartbeat timestamp of the lobby.
            // Lobbies with heartbeats that are too old will get removed.
            if($packet->method === 'keepalive_lobby'){
                $lobby->timestamp = new \DateTime('now');
                $connection->write(ResponsePacket::createSuccess());
                Console::printConnLine($connection, "keepalive for lobby: {$lobby->token}", true);
                return;
            }

        }

        //////////////////////////////////////////////////////////////////////////////////////////////////////////////
        // Everything behind this is when a packet can not be handled
        //////////////////////////////////////////////////////////////////////////////////////////////////////////////

        Console::printConnLine($connection, 'unknown packet type');
        $connection->write(ResponsePacket::createError('UNKNOWN_PACKET_TYPE'));
    });

    // Handle closing of socket between server and client
    $connection->on('close', function () use ($connection, $socket, &$client, &$master_server) {
        Console::printConnLine($connection, 'connection closed', true);
    });

    // Handle error between server and client
    $connection->on('error', function (Exception $e) use ($connection) {
        Console::printConnLine($connection, 'ERROR: ' . $e->getMessage(), false);
    });

});

// Handle socket error
$socket->on('error', function (Exception $e) {
    Console::printLine(false, "SOCKET ERROR: {$e->getMessage()}", false);
});

// Show nice message that our server is now running
Console::printLine(true, "Listening on: {$socket->getAddress()}", false);