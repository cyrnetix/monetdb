<?php

declare(strict_types=1);

namespace Cyrnetix\MonetDB\Command;

use function array_merge;

use Cyrnetix\MonetDB\Io\ConnectOptions;
use Cyrnetix\MonetDB\Io\Constants;

/**
 * Class AuthenticateCommand
 *
 * @package Cyrnetix\MonetDB\Command
 */
class AuthenticateCommand extends AbstractCommand
{
    /**
     * The username
     *
     * @var string
     */
    private $user;

    /**
     * The password
     *
     * @var string
     */
    private $password;

    /**
     * The db name
     *
     * @var string|null
     */
    private $schema;

    private const PWD_HASH_FUNCTION = 'sha512';

    /**
     * AuthenticateCommand constructor.
     *
     * @param string $user
     * @param string $password
     * @param string|null $database
     */
    public function __construct(string $user, string $password, string $database = null)
    {
        $this->user = $user;
        $this->password = $password;
        $this->schema = $database;
    }

    /**
     * Get the command id
     *
     * @return string
     */
    public function getId(): string
    {
        return '';
    }

    /**
     * The authentication block looks like this:
     *    endian:username:pwhash:language:database[PROT10:compression:blocksize]
     *
     * - endian indicates endianness of the client (BIG or LIT)
     * - username is the login username
     * - pwhash is a hash of the password
     * - language is SQL
     * - database is a name of the database the client is connecting to
     *
     * MAPI10 (not supported yet)
     * - PROT10 is a constant that indicates you want to use protocol 10
     * - compression is the compression you want to use (COMPRESSION_NONE, COMPRESSION_SNAPPY or COMPRESSION_LZ4)
     * - blocksize is the requested blocksize for the new block stream in bytes (typically 1MB, i.e. 1000000);
     *
     * @param string $challenge
     * @param ConnectOptions $connectOptions
     * @param string $language
     * @return string
     */
    public function authenticatePacket(
        string $challenge,
        ConnectOptions $connectOptions,
        string $language = Constants::LANGUAGE_SQL
    ): string {
        $message = [
            $connectOptions->endianness,
            $this->user,
            $this->getPasswordHash($challenge, $connectOptions->hash, $connectOptions->hash),
            $language,
            $this->schema
        ];

        // Not supported yet.....
        // @todo implement
        if (Constants::PROTOCOL_V10 === $connectOptions->protocolVersion) {
            throw new \LogicException('V10 is not supported yet');
            $message = array_merge($message, [Constants::PROT10, $compression, $blockSize]);
        }

        return implode(':', $message) . ':';
    }

    /**
     * We first hash the password with the server-requested hash function
     * (SHA512 in the example above).
     * Then, we hash that hash together with the server-provided salt using
     * a hash function from the list provided by the server.
     *
     * By default, we use SHA512 for both.
     *
     * @param string $challenge
     * @param string $pwHashFunc
     * @param string $endHashFunc
     * @return string
     */
    private function getPasswordHash(
        string $challenge,
        string $pwHashFunc = self::PWD_HASH_FUNCTION,
        string $endHashFunc = self::PWD_HASH_FUNCTION
    ): string {
        return '{' . strtoupper($endHashFunc) . '}' . hash($endHashFunc, hash($pwHashFunc, $this->password) . $challenge);
    }
}
