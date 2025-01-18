<?php

namespace Brickhouse\Channel\Websocket;

use Amp\Socket\Socket;
use Brickhouse\Http\HttpStatus;
use Brickhouse\Http\Request;
use Brickhouse\Http\Response;
use Brickhouse\Http\Router;
use Brickhouse\Http\Server\HttpClientDriver;
use Brickhouse\Log\Log;
use Revolt\EventLoop;

class Transport extends \Brickhouse\Channel\Transport
{
    public const string WEBSOCKET_GUID = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11";

    /**
     * @inheritDoc
     */
    public function upgrade(Socket $socket, Request $request, HttpClientDriver $driver): void
    {
        $response = $this->handleHandshake($request);

        if ($response->status !== HttpStatus::SWITCHING_PROTOCOLS) {
            $response->headers->remove('Sec-WebSocket-Accept');
            $response->headers->set('Connection', 'close');

            $driver->send($socket, $response);
            return;
        }

        $response->upgrade(fn() => $this->enqueueUpgradedClient($socket, $request));

        $driver->send($socket, $response, close: false);
    }

    /**
     * Handles the initial handshake request from the client.
     *
     * @param Request   $request  The initial request from the client.
     *
     * @return Response
     */
    protected function handleHandshake(Request $request): Response
    {
        if ($request->method() !== 'GET') {
            $response = Response::new(status: HttpStatus::METHOD_NOT_ALLOWED);
            $response->headers->set('Allow', 'GET');

            return $response;
        }

        if ($request->protocol() !== '1.1') {
            $response = Response::new(status: HttpStatus::HTTP_VERSION_NOT_SUPPORTED);
            $response->headers->set('Upgrade', 'websocket');

            return $response;
        }

        if (!$request->headers->has('upgrade', 'websocket')) {
            $response = Response::new('"Upgrade: websocket" header is required.', status: HttpStatus::UPGRADE_REQUIRED);
            $response->headers->set('Upgrade', 'websocket');

            return $response;
        }

        if (!$request->headers->has('connection', 'upgrade')) {
            $response = Response::new('"Connection: Upgrade" header is required.', status: HttpStatus::UPGRADE_REQUIRED);
            $response->headers->set('Upgrade', 'websocket');

            return $response;
        }

        if (!$key = $request->headers->get('sec-websocket-key')) {
            $response = Response::new('"Sec-WebSocket-Key" header is required.', status: HttpStatus::BAD_REQUEST);
            return $response;
        }

        if (!$request->headers->has('sec-websocket-version', '13')) {
            $response = Response::new('Requested WebSocket version unavailable.', status: HttpStatus::BAD_REQUEST);
            $response->headers->set('Sec-WebSocket-Version', '13');

            return $response;
        }

        $response = Response::new(status: HttpStatus::SWITCHING_PROTOCOLS);
        $response->headers->set('Connection', 'Upgrade');
        $response->headers->set('Upgrade', 'websocket');
        $response->headers->set('Sec-WebSocket-Accept', $this->generateAcceptKey($key));

        return $response;
    }

    /**
     * Generates an "Accept Key" for a WebSocket request, using the given key from the client.
     *
     * @param string    $key    The WebSocket key from the initial handshake request.
     *
     * @return string
     */
    protected function generateAcceptKey(string $key): string
    {
        $hash = sha1($key . self::WEBSOCKET_GUID, binary: true);
        $acceptKey = base64_encode($hash);

        return $acceptKey;
    }

    /**
     * Creates a new `WebsocketClient`-instance and queues it on the event loop.
     *
     * @param Socket    $socket
     * @param Request   $request
     *
     * @return void
     */
    protected function enqueueUpgradedClient(Socket $socket, Request $request): void
    {
        $client = new WebsocketClient($socket);

        Log::channel('channel')->debug("Upgraded {remote} to WebSocket connection ({id})", [
            'remote' => $socket->getRemoteAddress()->toString(),
            'id' => $client->id()
        ]);

        EventLoop::queue($this->handleWebsocketClient(...), $client, $request);
    }

    /**
     * Attempts to route the Websocket client to it's appropriate route handler.
     *
     * @param WebsocketClient   $client
     * @param Request           $request
     *
     * @return void
     */
    protected function handleWebsocketClient(WebsocketClient $client, Request $request): void
    {
        $gateway = resolve(WebsocketGateway::class);
        $gateway->register($client);

        // Add the websocket client for resolved controller actions.
        $request->setBindings([...$request->bindings(), $client]);

        try {
            resolve(Router::class)->handle($request);
        } catch (\Throwable $e) {
            Log::channel('channel')->error("Unexpected exception thrown from Websocket client: {message}", [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            $client->close(WebsocketCloseCode::UNEXPECTED_CONDITION, 'Internal error.');
            return;
        }

        if (!$client->closed()) {
            $client->close(WebsocketCloseCode::CLOSURE, 'Closing connection.');
        }
    }
}
