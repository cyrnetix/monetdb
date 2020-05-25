<?php

declare(strict_types=1);

namespace Cyrnetix\MonetDB\Tool;

/**
 * Utility classes
 *
 * @package Cyrnetix\MonetDB\Tool
 */
class HexDump
{
    /**
     * Dump data into a string
     *
     * @param $data
     * @param string $newline
     */
    public static function dump($data, string $newline = "\n")
    {
        static $from = '';
        static $to = '';
        static $width = 16; // Number of bytes per line
        static $pad = '.'; // Padding for non-visible characters

        if ($from === '') {
            for ($i = 0; $i <= 0xFF; $i++) {
                $from .= chr($i);
                $to .= ($i >= 0x20 && $i <= 0x7E) ? chr($i) : $pad;
            }
        }

        $hex = str_split(bin2hex($data), $width * 2);
        $chars = str_split(strtr($data, $from, $to), $width);

        $offset = 0;
        foreach ($hex as $i => $line) {
            echo sprintf('%6X', $offset) . ' : ' . implode(' ', str_split($line, 2)) . ' [' . $chars[$i] . ']' . $newline;
            $offset += $width;
        }
    }
}
