<?php

namespace Brickhouse\Channel\Websocket;

use Amp\Pipeline\ConcurrentIterator;
use Amp\Socket\Socket;
use Amp\Socket\SocketAddress;
use Revolt\EventLoop;

/**
 * @implements \IteratorAggregate<string>
 */
class WebsocketClient implements \IteratorAggregate
{
    private static int $nextId = 0;

    private readonly int $id;

    public readonly FrameHandler $handler;
    public readonly FrameParser $parser;

    /**
     * @var ConcurrentIterator<string>
     */
    private readonly ConcurrentIterator $messageIterator;

    public function __construct(private readonly Socket $socket)
    {
        $this->id = ++self::$nextId;

        $this->handler = new FrameHandler($socket);
        $this->parser = new FrameParser($socket, $this->handler);
        $this->messageIterator = $this->handler->iterate();

        EventLoop::queue($this->handler->read(...), $this->parser);
    }

    /**
     * Gets the unique ID of the WebSocket client.
     *
     * @return integer
     */
    public function id(): int
    {
        return $this->id;
    }

    /**
     * Gets the remote `SocketAddress` of the socket.
     *
     * @return SocketAddress
     */
    public function remoteAddress(): SocketAddress
    {
        return $this->socket->getRemoteAddress();
    }

    /**
     * Gets the local `SocketAddress` of the socket.
     *
     * @return SocketAddress
     */
    public function localAddress(): SocketAddress
    {
        return $this->socket->getLocalAddress();
    }

    /**
     * Registers a callback for when the client closes.
     *
     * @param   \Closure(): void    $callback
     *
     * @return void
     */
    public function onClose(\Closure $callback): void
    {
        $this->socket->onClose($callback);
    }

    /**
     * Closes the WebSocket connection.
     *
     * @return void
     */
    public function close(int $status, string $message): void
    {
        $this->socket->close();
    }

    /**
     * Gets whether the socket is closed.
     *
     * @return boolean
     */
    public function closed(): bool
    {
        return $this->socket->isClosed();
    }

    /**
     * Iterates over the messages in the message queue.
     *
     * @return ConcurrentIterator<string>
     */
    public function iterate(): ConcurrentIterator
    {
        return $this->messageIterator;
    }

    /**
     * Iterates over the messages in the message queue.
     *
     * @return \Traversable<string>
     */
    public function getIterator(): \Traversable
    {
        return $this->iterate();
    }

    /**
     * Sends a message over the WebSocket to the client.
     *
     * @param   string  $message
     *
     * @return void
     */
    public function send(string $message): void
    {
        if (mb_check_encoding($message, "UTF-8")) {
            $this->sendText($message);
        } else {
            $this->sendBinary($message);
        }
    }

    /**
     * Sends a text-based message over the WebSocket to the client.
     *
     * @param   string  $text
     *
     * @return void
     */
    public function sendText(string $text): void
    {
        $this->handler->write(WebsocketFrameType::TEXT, $text);
    }

    /**
     * Sends a binary message over the WebSocket to the client.
     *
     * @param   string  $content
     *
     * @return void
     */
    public function sendBinary(string $content): void
    {
        $this->handler->write(WebsocketFrameType::BINARY, $content);
    }
}
