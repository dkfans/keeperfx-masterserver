<?php

namespace KeeperFX\MasterServer\Packet;

class ResponsePacket {

    public function __construct(
        public bool $success = true,
        public array $data = [],
    ){}

    public function __toString(): string
    {
        // Combine success, protocol version, and the data into a JSON packet
        // We do some weird array concatenation so our packet is nicely structured.
        return \json_encode((object) (
            ['success' => $this->success]
            + $this->data
            + ['v' => PROTOCOL_VERSION]
        )) . PHP_EOL;
    }
    
    static function create(array $data = [])
    {
        return new self(true, $data);
    }

    static function createSuccess()
    {
        return new self(true);
    }

    static function createError(string $error)
    {
        return new self(false, ['error' => $error]);
    }
}