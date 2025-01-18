<?php

namespace Brickhouse\Channel\Websocket;

class Frame
{
    private string $payload;

    public function __construct(
        public readonly bool $finished,
        public readonly WebsocketFrameType $opcode,
        string $payload = '',
    ) {
        $this->payload = $payload;
    }

    /**
     * Gets whether the frame is a control frame.
     *
     * @return boolean
     */
    public function isControlFrame(): bool
    {
        return (bool) ($this->opcode->value & 0x8);
    }

    /**
     * Gets the payload of the frame.
     *
     * @return string
     */
    public function payload(): string
    {
        return $this->payload;
    }

    /**
     * Sets the payload of the frame.
     *
     * @param   string  $payload
     *
     * @return void
     */
    public function setPayload(string $payload): void
    {
        $this->payload = $payload;
    }
}
