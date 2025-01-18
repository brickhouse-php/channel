<?php

namespace Brickhouse\Channel\Websocket;

class WebsocketGateway
{
    public const int BROADCAST_CHUNK_SIZE = 20;

    public const float BROADCAST_TIMEOUT = 20;

    /**
     * Gets all the connected clients in the gateway.
     *
     * @var array<int,WebsocketClient>
     */
    private array $clients = [];

    /**
     * Registers a new client with the gateway.
     *
     * @param WebsocketClient $client
     *
     * @return void
     */
    public function register(WebsocketClient $client): void
    {
        $id = $client->id();
        $this->clients[$id] = $client;

        $client->onClose(fn() => $this->unregister($id));
    }

    /**
     * Unregisters an existing client from the gateway.
     *
     * @param integer|WebsocketClient   $client
     *
     * @return void
     */
    public function unregister(int|WebsocketClient $client): void
    {
        $id = $client instanceof WebsocketClient
            ? $client->id()
            : $client;

        unset($this->clients[$id]);
    }

    /**
     * Broadcasts a message to all connected clients in the gateway.
     *
     * @param string    $message
     *
     * @return void
     */
    public function broadcast(string $message): void
    {
        /** @var array<int,array<int,WebsocketClient>> $clientChunks */
        $clientChunks = array_chunk($this->clients, self::BROADCAST_CHUNK_SIZE, preserve_keys: true);

        foreach ($clientChunks as $clientChunk) {
            $futures = [];
            $cancellation = new \Amp\TimeoutCancellation(self::BROADCAST_TIMEOUT);

            foreach ($clientChunk as $client) {
                $futures[] = \Amp\async(fn() => $client->send($message));
            }

            \Amp\async(fn() => \Amp\Future\awaitAll($futures, $cancellation));
        }
    }
}
