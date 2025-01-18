<?php

namespace Brickhouse\Channel\Websocket;

class FrameCompiler
{
    private const int MASK_FLAG = 0x80;

    /**
     * Compile the given frame parameters into a binary WebSocket message.
     *
     * @param   WebsocketFrameType  $type
     * @param   string              $data
     * @param   bool                $final
     * @param   bool                $useMasking
     *
     * @return string
     */
    public function compile(
        WebsocketFrameType $type,
        string $data,
        bool $final = true,
        bool $useMasking = false
    ): string {
        $maskFlag = $useMasking ? self::MASK_FLAG : 0;

        $frame = '';
        $frame .= \chr(((int) $final << 7) | ($type->value & 0x0F));

        $length = strlen($data);
        if ($length > 0xFFFF) {
            $frame .= chr(0x7F | $maskFlag) . pack('J', $length);
        } else if ($length > 0x7D) {
            $frame .= chr(0x7E | $maskFlag) . pack('n', $length);
        } else {
            $frame .= chr($length | $maskFlag);
        }

        if ($useMasking) {
            $mask = random_bytes(4);
            $frame .= $mask;
            $frame .= ($data ^ str_repeat($mask, ($length + 3) >> 2));

            return $frame;
        }

        $frame .= $data;

        return $frame;
    }
}
