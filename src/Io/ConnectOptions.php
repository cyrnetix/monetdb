<?php

declare(strict_types=1);

namespace Cyrnetix\MonetDB\Io;

/**
 * Connection options
 *
 * @packege Cyrnetix\MonetDB\Io
 */
class ConnectOptions
{
    /**
     * Server type
     *
     * @var string
     */
    public $serverType;

    /**
     * Used protocol version
     *
     * @var int
     */
    public $protocolVersion;

    /**
     * Supported algorithms
     *
     * @var string[]
     */
    public $algorithms;

    /**
     * Little or big endian
     *
     * @var string
     */
    public $endianness;

    /**
     * Server hash
     *
     * @var string
     */
    public $hash;

    /**
     * ConnectOptions constructor.
     *
     * @param string $serverType
     * @param string $protocolVersion
     * @param array $algorithms
     * @param string $endianness
     * @param string $hash
     */
    public function __construct(
        string $serverType,
        string $protocolVersion,
        array $algorithms,
        string $endianness,
        string $hash
    ) {

        $this->serverType = $serverType;
        $this->protocolVersion = $protocolVersion;
        $this->algorithms = $algorithms;
        $this->endianness = $endianness;
        $this->hash = $hash;
    }
}
