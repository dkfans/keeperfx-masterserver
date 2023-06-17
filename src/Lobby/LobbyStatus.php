<?php

namespace KeeperFX\MasterServer\Lobby;

enum LobbyStatus: string {
    case OPEN    = 'open';
    case PLAYING = 'playing';
}