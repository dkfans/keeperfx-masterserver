<?php

namespace KeeperFX\MasterServer\Packet;

use KeeperFX\MasterServer\Exception\PacketInvalidJsonException;
use KeeperFX\MasterServer\Exception\PacketNoMethodException;

class IncomingPacket {

    public function __construct(
        public string $method = 'invalid',
        public array $data = [],
    ){}

    static function create(string $json): self
    {
        try {
            $json_decoded = \json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Exception $ex) {
            throw new PacketInvalidJsonException('invalid JSON');
        }

        if(!isset($json_decoded['method'])){
            throw new PacketNoMethodException('missing method');
        }

        return new self(
            $json_decoded['method'],
            $json_decoded
        );
    }
}