<?php

namespace Brickhouse\Channel\Websocket;

use Amp\Pipeline\ConcurrentIterator;
use Amp\Pipeline\Queue;
use Amp\Socket\Socket;
use Brickhouse\Http\Exceptions\WebsocketClientException;
use Brickhouse\Log\Log;

class FrameHandler
{
    private readonly FrameCompiler $frameCompiler;

    /**
     * @var Queue<string>
     */
    private readonly Queue $messageQueue;

    private string $payloadBuffer = "";

    public function __construct(private readonly Socket $socket)
    {
        $this->frameCompiler = new FrameCompiler();
        $this->messageQueue = new Queue();
    }

    /**
     * @return ConcurrentIterator<string>
     */
    public function iterate(): ConcurrentIterator
    {
        return $this->messageQueue->iterate();
    }

    public function read(FrameParser $parser): void
    {
        try {
            while (($chunk = $this->socket->read()) !== null) {
                if ($chunk === "") {
                    continue;
                }

                $parser->push($chunk);
                $chunk = "";
            }
        } catch (\Throwable $e) {
            Log::channel("channel")->error("Unexpected exception: {message}", [
                "message" => $e->getMessage(),
                "exception" => $e,
            ]);
        } finally {
            $parser->cancel();
        }
    }

    public function write(
        WebsocketFrameType $type,
        string $data,
        bool $final = true
    ): void {
        $frame = $this->frameCompiler->compile($type, $data, $final);

        $this->socket->write($frame);
    }

    public function handleFrame(Frame $frame): void
    {
        // Control frames must have a payload length of 125 bytes or less.
        if ($frame->isControlFrame() && strlen($frame->payload()) > 125) {
            throw new WebsocketClientException(
                "Payload too large for control frame.",
                WebsocketCloseCode::PAYLOAD_TOO_LARGE
            );
        }

        // Control frames must not be fragmented.
        if ($frame->isControlFrame() && !$frame->finished) {
            throw new WebsocketClientException(
                "Control frames cannot be fragmented.",
                WebsocketCloseCode::PROTOCOL_ERROR
            );
        }

        // Message fragmentation can only occur on opcodes `0x00`, `0x01` and `0x02`.
        if (!$frame->finished && $frame->opcode->value >= 3) {
            throw new WebsocketClientException(
                "Fragmentation not allowed on frame type.",
                WebsocketCloseCode::PROTOCOL_ERROR
            );
        }

        // If the message is fragmented, but the frame is not a continuation frame,
        // it must be the first message in the queue. Otherwise, it is invalid.
        if (
            !$frame->finished &&
            $frame->opcode->value !== 0x00 &&
            strlen($this->payloadBuffer) > 0
        ) {
            throw new WebsocketClientException(
                "Subsequent fragmented message on invalid frame type.",
                WebsocketCloseCode::PROTOCOL_ERROR
            );
        }

        // If the frame is fragmented, append it to the buffer and wait until the message is finished.
        if (!$frame->finished) {
            $this->payloadBuffer .= $frame->payload();
            return;
        }

        $payload = $frame->payload();

        // If any previous messages are stored in the payload buffer,
        // prepend them to the current message.
        if (strlen($this->payloadBuffer) > 0) {
            $payload = $this->payloadBuffer . $payload;
            $this->payloadBuffer = "";
        }

        $frame->setPayload($payload);

        match ($frame->opcode) {
            WebsocketFrameType::CLOSE => $this->handleClose($frame),
            WebsocketFrameType::TEXT => $this->handleDataFrame($frame),
            WebsocketFrameType::BINARY => $this->handleDataFrame($frame),
            WebsocketFrameType::PING => $this->handlePing($frame),
            WebsocketFrameType::PONG => $this->handlePong($frame),

            default => throw new WebsocketClientException(
                "Invalid frame opcode.",
                WebsocketCloseCode::PROTOCOL_ERROR
            ),
        };
    }

    /**
     * Handle `Close` control frames.
     *
     * @param Frame $frame
     *
     * @return void
     */
    protected function handleClose(Frame $frame): void
    {
        $this->socket->close();
    }

    /**
     * Handle `Ping` control frames.
     *
     * @param Frame $frame
     *
     * @return void
     */
    protected function handlePing(Frame $frame): void
    {
        $this->write(WebsocketFrameType::PONG, $frame->payload());
    }

    /**
     * Handle `Pong` control frames.
     *
     * @param Frame $frame
     *
     * @return void
     */
    protected function handlePong(Frame $frame): void
    {
        // We currently don't handle pongs and we just assume they're valid.
    }

    /**
     * Handle both `Text` and `Binary` data frames.
     *
     * @param Frame $frame
     *
     * @return void
     */
    protected function handleDataFrame(Frame $frame): void
    {
        $this->messageQueue->push($frame->payload());
    }
}
