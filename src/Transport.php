<?php

namespace Brickhouse\Channel;

use Amp\Socket\Socket;
use Brickhouse\Http\Request;
use Brickhouse\Http\Server\HttpClientDriver;

abstract class Transport
{
    /**
     * Upgrades the given request using the transport implementation.
     *
     * @param Request $request
     *
     * @return void
     */
    public abstract function upgrade(Socket $socket, Request $request, HttpClientDriver $driver): void;
}
