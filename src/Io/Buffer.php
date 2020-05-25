<?php

declare(strict_types=1);

namespace Cyrnetix\MonetDB\Io;

use LogicException;

/**
 * Class Buffer
 *
 * @package Cyrnetix\MonetDB\Io
 */
class Buffer
{
    /**
     * The current data buffer
     *
     * @var string
     */
    private $buffer = '';

    /**
     * Current cursor position
     *
     * @var int
     */
    private $cursor = 0;

    /**
     * Finalized (last block received)
     *
     * @var bool
     */
    private $finalized = false;

    /**
     * Add data to the buffer
     *
     * @param string $data
     */
    public function append(string $data): void
    {
        $this->buffer .= $data;
        $this->finalized = false;
    }

    /**
     * Returns the buffer length measures in number of bytes
     *
     * @return int
     */
    public function length(): int
    {
        return \strlen($this->buffer) - $this->cursor;
    }

    /**
     * Finalize the current buffer
     *
     * @return void
     */
    public function finalize(): void
    {
        $this->finalized = true;
    }

    /**
     * Seek
     *
     * @param int $position
     * @param bool $relative
     * @return void
     */
    public function seek(int $position, bool $relative = true): void
    {
        if (true === $relative) {
            $this->cursor += $position;
        }

        $this->cursor = $position;
    }

    /**
     * Read a part of the buffer
     *
     * @param int $length
     * @return string
     */
    public function read(int $length): string
    {
        // Happy path to return empty string for zero length string
        if (0 === $length) {
            return '';
        }

        // Happy path for single byte strings without using substrings
        if (1 === $length && true === isset($this->buffer[$this->cursor])) {
            return $this->buffer[$this->cursor++];
        }

        // Ensure buffer size contains $length bytes by checking target buffer position
        if ($length < 0 || false === isset($this->buffer[$this->cursor + $length - 1])) {
            throw new LogicException('Not enough data in buffer to read ' . $length . ' bytes');
        }

        $buffer = \substr($this->buffer, $this->cursor, $length);
        $this->cursor += $length;

        return $buffer;
    }

    /**
     * Read a single line
     *
     * @param string $newLine
     * @return string|null
     */
    public function readLine(string $newLine = "\n"): ?string
    {
        $buffer = &$this->buffer;
        $cursor = &$this->cursor;

        // End of string
        if ($cursor === \strlen($buffer) && true === $this->finalized) {
            return '';
        }

        $pos = \strpos($buffer, $newLine, $cursor);
        if (false !== $pos) {
            $length = $pos - $cursor;
            $v = \substr($this->buffer, $cursor, $length);
            $cursor += $length + \strlen($newLine);

            return $v;
        }

        return true === $this->finalized ? \substr($this->buffer, $cursor) : null;
    }

    /**
     * Read a tiny int
     *
     * @return int 1 byte / 8 bit integer (0 to 255)
     */
    public function readInt1(): int
    {
        return \ord($this->buffer[$this->cursor++]);
    }

    /**
     * Read a 16 bit int
     *
     * @return int 2 byte / 16 bit integer (0 to 64 K / 0xFFFF)
     */
    public function readInt2()
    {
        $v = \unpack('v', \substr($this->buffer, $this->cursor, 2));
        $this->cursor += 2;
        return $v[1];
    }

    /**
     * Get a 24 bit int
     *
     * @return int 3 byte / 24 bit integer (0 to 16 M / 0xFFFFFF)
     */
    public function readInt3(): int
    {
        $v = \unpack('V', \substr($this->buffer, $this->cursor, 3) . "\0");
        $this->cursor += 3;
        return $v[1];
    }

    /**
     * Get a 32 bit int
     *
     * @return int 4 byte / 32 bit integer (0 to 4 G / 0xFFFFFFFF)
     */
    public function readInt4(): int
    {
        $v = \unpack('V', \substr($this->buffer, $this->cursor, 4));
        $this->cursor += 4;
        return $v[1];
    }

    /**
     * Read a 64 bit int
     *
     * @return int 8 byte / 64 bit integer (0 to 2^64-1)
     */
    public function readInt8(): int
    {
        // PHP < 5.6.3 does not support packing 64 bit ints, so use manual bit shifting
        if (\PHP_VERSION_ID >= 50603) {
            $v = \unpack('P', \substr($this->buffer, $this->cursor, 8));
            $this->cursor += 8;
            return $v[1];
        }

        $v = \unpack('V*', $this->read(8));
        return $v[1] + ($v[2] << 32);
    }

    /**
     * Clean consumed data
     *
     * @return void
     */
    public function trim(): void
    {
        if (false === isset($this->buffer[$this->cursor])) {
            $this->buffer = '';
        } else {
            $this->buffer = \substr($this->buffer, $this->cursor);
        }
        $this->finalized = false;
        $this->cursor = 0;
    }

    /**
     * Create a single int
     *
     * @param int $int
     * @return string
     */
    public static function buildInt1(int $int): string
    {
        return \chr($int);
    }

    /**
     * Build a 16 bit int
     *
     * @param int $int
     * @return string
     */
    public static function buildInt2(int $int): string
    {
        return \pack('v', $int);
    }

    /**
     * Build a 24 bit int
     *
     * @param int $int
     * @return string
     */
    public static function buildInt3(int $int): string
    {
        return \substr(\pack('V', $int), 0, 3);
    }

    /**
     * Build a 64 bit int
     *
     * @param int $int
     * @return string
     */
    public static function buildInt8(int $int): string
    {
        // PHP < 5.6.3 does not support packing 64 bit ints, so use manual bit shifting
        if (\PHP_VERSION_ID >= 50603) {
            return \pack('P', $int);
        }

        return \pack('VV', $int, $int >> 32);
    }

    /**
     * Read a single byte
     *
     * @return string
     */
    public function readByte(): string
    {
        if (\strlen($this->buffer) <= $this->cursor) {
            return '';
        }

        $v = $this->buffer[$this->cursor];
        ++$this->cursor;

        return $v;
    }

    /**
     * Return the current cursor position
     *
     * @return int
     */
    public function at(): int
    {
        return $this->cursor;
    }
}
