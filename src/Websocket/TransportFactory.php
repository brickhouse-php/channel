<?php

namespace Brickhouse\Channel\Websocket;

use Brickhouse\Http\Request;

class TransportFactory extends \Brickhouse\Channel\TransportFactory
{
    /**
     * @inheritDoc
     */
    public function shouldUpgrade(Request $request): bool
    {
        if ($request->method() !== 'GET') {
            return false;
        }

        $upgradeHeader = $request->headers->get('upgrade');
        if (!$upgradeHeader || strcasecmp($upgradeHeader, 'websocket')) {
            return false;
        }

        $connectionHeader = $request->headers->get('connection');
        if (!$connectionHeader || strcasecmp($connectionHeader, 'upgrade')) {
            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function create(): Transport
    {
        return new Transport;
    }
}
