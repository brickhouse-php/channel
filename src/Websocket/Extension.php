<?php

namespace Brickhouse\Channel\Websocket;

use Brickhouse\Channel\ChannelSettings;
use Brickhouse\Core\Application;

class Extension extends \Brickhouse\Core\Extension
{
    /**
     * Gets the human-readable name of the extension.
     */
    public string $name = "brickhouse/channel.websocket";

    public function __construct(
        private readonly Application $application,
        private readonly ChannelSettings $settings
    ) {
    }

    /**
     * Invoked before the application has started.
     */
    public function register(): void
    {
        if (!$this->settings->websocket->enabled) {
            return;
        }

        $this->application->singleton(WebsocketGateway::class);
    }

    /**
     * Invoked after the application has started.
     */
    public function boot(): void
    {
        if (!$this->settings->websocket->enabled) {
            return;
        }
    }

    /**
     * @inheritDoc
     *
     * @return array<int,class-string<\Brickhouse\Core\Extension>>
     */
    public function dependencies(): array
    {
        return [\Brickhouse\Channel\Extension::class];
    }
}
