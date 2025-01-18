<?php

namespace Brickhouse\Channel;

use Brickhouse\Core\Application;

class Extension extends \Brickhouse\Core\Extension
{
    /**
     * Gets the human-readable name of the extension.
     */
    public string $name = "brickhouse/channel";

    public function __construct(private readonly Application $application)
    {
    }

    /**
     * Invoked before the application has started.
     */
    public function register(): void
    {
        $this->application->singleton(ChannelFactory::class);
    }

    /**
     * Invoked after the application has started.
     */
    public function boot(): void
    {
        //
    }
}
