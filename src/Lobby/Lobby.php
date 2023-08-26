<?php

namespace KeeperFX\MasterServer\Lobby;

use DateTime;

class Lobby {

    public function __construct(
        public string $name                       = 'Lobby',
        public string $ip                         = '127.0.0.1',
        public int $port                          = 5556,
        public array  $players                    = [],
        public LobbyStatus $status                = LobbyStatus::OPEN,
        public bool   $has_password               = false,
        public ?string $game_version              = null,
        public ?DateTime $timestamp               = null,
        public ?string $token                     = null,
    ){
        $this->timestamp = new DateTime('now');
        $this->token     = LobbyList::generateUniqueLobbyToken();
    }

}