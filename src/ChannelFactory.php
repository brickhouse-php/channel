<?php

namespace Brickhouse\Channel;

use Amp\Socket\Socket;
use Brickhouse\Http\Request;
use Brickhouse\Http\Server\HttpClientDriver;

class ChannelFactory
{
    /**
     * Gets all the supported transport factory instances.
     *
     * @var array<int,class-string<TransportFactory>>
     */
    public const TRANSPORTS = [
        Websocket\TransportFactory::class,
    ];

    /**
     * Gets all the transport factory instances available.
     *
     * @var array<int,TransportFactory>
     */
    private readonly array $factories;

    public function __construct()
    {
        $this->factories = array_map(resolve(...), static::TRANSPORTS);
    }

    /**
     * Determines whether the given update should be upgraded and handled by the transport implementation.
     *
     * @param Request $request
     *
     * @return boolean
     */
    public function shouldUpgrade(Request $request): bool
    {
        foreach ($this->factories as $factory) {
            if ($factory->shouldUpgrade($request)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Upgrades the given request to a channel transport, if one supports the request.
     *
     * @param Request $request
     *
     * @return boolean  `true` if the request was upgraded. Otherwise, `false`.
     */
    public function upgrade(Socket $socket, Request $request, HttpClientDriver $driver): bool
    {
        foreach ($this->factories as $factory) {
            if (!$factory->shouldUpgrade($request)) {
                continue;
            }

            $transport = $factory->create();
            $transport->upgrade($socket, $request, $driver);

            return true;
        }

        return false;
    }
}
