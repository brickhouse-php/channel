<?php

namespace Brickhouse\Channel\Websocket;

readonly final class WebsocketCloseCode
{
    public const CLOSURE = 1000;
    public const GOING_AWAY = 1001;
    public const PROTOCOL_ERROR = 1002;
    public const DATA_TYPE_MISMATCH = 1007;
    public const POLICY_VIOLATION = 1008;
    public const PAYLOAD_TOO_LARGE = 1009;
    public const EXTENSION_NEGOTIATION_FAILED = 1010;
    public const UNEXPECTED_CONDITION = 1011;
}
