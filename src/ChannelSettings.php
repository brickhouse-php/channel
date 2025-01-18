<?php

namespace Brickhouse\Channel;

use Brickhouse\Channel\Websocket\WebsocketSettings;

class ChannelSettings
{
    public function __construct(
        public readonly WebsocketSettings $websocket = new WebsocketSettings(true),
    ) {}
}
