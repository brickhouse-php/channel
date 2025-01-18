<?php

namespace Brickhouse\Channel;

use Brickhouse\Http\Request;

abstract class TransportFactory
{
    /**
     * Determines whether the given update should be upgraded and handled by the transport implementation.
     *
     * @param Request $request
     *
     * @return boolean
     */
    public abstract function shouldUpgrade(Request $request): bool;

    /**
     * Creates a new transport from the factory implementation.
     *
     * @return Transport
     */
    public abstract function create(): Transport;
}
