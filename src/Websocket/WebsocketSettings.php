<?php

namespace Brickhouse\Channel\Websocket;

class WebsocketSettings
{
    public function __construct(
        public readonly bool $enabled,
    ) {}
}
