<?php

namespace Brickhouse\Channel\Websocket;

use Amp\Parser\Parser;
use Amp\Socket\Socket;
use Brickhouse\Http\Exceptions\WebsocketClientException;

class FrameParser
{
    public const int MAX_PAYLOAD_SIZE_LIMIT = 10 * 1024 * 1024;

    private readonly Parser $parser;

    public function __construct(
        private readonly Socket $socket,
        private readonly FrameHandler $handler,
    ) {
        $this->parser = new Parser(self::parse(
            $this->socket,
            $this->handler
        ));
    }

    public function push(string $chunk): void
    {
        $this->parser->push($chunk);
    }

    public function cancel(): void
    {
        $this->parser->cancel();
    }

    public static function parse(
        Socket $socket,
        FrameHandler $handler,
    ): \Generator {
        $receivedDataBytes = 0;

        while (true) {
            $payload = ''; // Free memory from last frame payload.

            $buffer = yield 2;

            $firstByte = \ord($buffer[0]);
            $secondByte = \ord($buffer[1]);

            $finished = (bool) ($firstByte & 0b10000000);
            $opcode = $firstByte & 0b1111;

            if ($opcode >= 0x3 && $opcode <= 0x7) {
                throw new WebsocketClientException("Invalid opcode: reserved non-control frame.", WebsocketCloseCode::PROTOCOL_ERROR);
            }

            // @phpstan-ignore smallerOrEqual.alwaysTrue
            if ($opcode >= 0xB && $opcode <= 0xF) {
                throw new WebsocketClientException("Invalid opcode: reserved control frame.", WebsocketCloseCode::PROTOCOL_ERROR);
            }

            $opcode = WebsocketFrameType::tryFrom($opcode);
            if (!$opcode) {
                throw new WebsocketClientException("Invalid opcode: unsupported.", WebsocketCloseCode::PROTOCOL_ERROR);
            }

            $maskingEnabled = (bool)($secondByte & 0b10000000);
            $maskingKey = '';
            $payloadLength = $secondByte & 0b01111111;

            if ($payloadLength === 126) {
                [, $payloadLength] = unpack('n', yield 2);
            } else if ($payloadLength === 127) {
                [, $payloadLength] = unpack('J', yield 8);
            }

            if ($payloadLength + $receivedDataBytes > self::MAX_PAYLOAD_SIZE_LIMIT) {
                throw new WebsocketClientException(
                    "Received payload exceeds payload size limit.",
                    WebsocketCloseCode::PAYLOAD_TOO_LARGE
                );
            }

            $receivedDataBytes += $payloadLength;

            if ($maskingEnabled) {
                $maskingKey = yield 4;
            }

            if ($payloadLength > 0) {
                $payload = yield $payloadLength;
            }

            if ($maskingEnabled) {
                // This is memory hungry, but it's ~70x faster than iterating byte-by-byte
                // over the masked string. Deal with it; manual iteration is untenable.
                // Original source: https://github.com/amphp/websocket/blob/963904b6a883c4b62d9222d1d9749814fac96a3b/src/Parser/Rfc6455Parser.php#L194-L199
                $payload ^= \str_repeat($maskingKey, ($payloadLength + 3) >> 2);
            }

            $frame = new Frame($finished, $opcode, $payload);

            $handler->handleFrame($frame);

            if ($socket->isClosed()) {
                break;
            }
        }
    }
}
