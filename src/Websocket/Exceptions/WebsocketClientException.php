<?php

namespace Brickhouse\Http\Exceptions;

use Brickhouse\Channel\Websocket\WebsocketCloseCode;

class WebsocketClientException extends \Exception
{
    public function __construct(
        string $message,
        int $code = WebsocketCloseCode::UNEXPECTED_CONDITION,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
