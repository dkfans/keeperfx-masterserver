<?php

namespace KeeperFX\MasterServer\Player;

class Player {

    public function __construct(
        public string       $name  = 'Keeper',
        public ?string      $ip    = null,
        public ?PlayerColor $color = PlayerColor::RED
    ){}

}