<?php

declare(strict_types=1);

namespace Cyrnetix\MonetDB\Io;

use Cyrnetix\MonetDB\Command\CommandInterface;
use Cyrnetix\MonetDB\Command\QueryCommand;
use Cyrnetix\MonetDB\Command\QuitCommand;
use Cyrnetix\MonetDB\Exception\AuthenticationException;
use Cyrnetix\MonetDB\Command\AuthenticateCommand;
use Cyrnetix\MonetDB\Command\XAutoCommitCommand;
use Cyrnetix\MonetDB\Command\XReplySizeCommand;
use Cyrnetix\MonetDB\Exception\Exception;
use Cyrnetix\MonetDB\Exception\InvalidResponseException;
use Cyrnetix\MonetDB\Exception\QueryException;
use Cyrnetix\MonetDB\Tool\HexDump;
use Cyrnetix\MonetDB\Type\Header;
use Cyrnetix\MonetDB\Type\Row;
use React\Stream\DuplexStreamInterface;
use RuntimeException;

/**
 * Message parser
 *
 * @package Cyrnetix\MonetDB\Io
 */
class Parser
{
    /**
     * Auth status
     *
     * @const int
     */
    private const PHASE_INIT          = 0;
    private const PHASE_GOT_INIT      = 1;
    private const PHASE_AUTH_SENT     = 2;
    private const PHASE_AUTHENTICATED = 3;

    /**
     * Default block size is 1 mb
     *
     * @const int
     */
    const DEFAULT_BLOCK_SIZE = 1e6;

    /**
     * Protocol versions
     *
     * @const int
     */
    private const PROTOCOL_MAPI_9 = 9;
    private const PROTOCOL_MAPI_10 = 10;

    /**
     * State
     */
    private const STATE_STANDBY = 0;
    private const STATE_BODY = 1;

    /**
     * Socket stream
     *
     * @var DuplexStreamInterface
     */
    private $stream;

    /**
     * Executor
     *
     * @var Executor
     */
    private $executor;

    /**
     * Data buffer
     *
     * @var Buffer
     */
    private $buffer;

    /**
     * Connection phase
     *
     * @var int
     */
    private $phase = self::PHASE_INIT;

    /**
     * Protocol (9 or 10)
     *
     * @var null
     */
    private $protocol = null;

    /**
     * Final package flag
     *
     * @var bool
     */
    private $final = false;

    /**
     * Block size
     *
     * @var int
     */
    private $blockSize = 0;

    /**
     * Current communication state
     *
     * @var int
     */
    private $state = self::STATE_STANDBY;

    /**
     * Connection options
     *
     * @var ConnectOptions|null
     */
    private $connectOptions = null;

    /**
     * Current command
     *
     * @var CommandInterface
     */
    private $currCommand;

    /**
     * Authentication challenge
     *
     * @var string
     */
    private $challenge;

    /**
     * Result
     *
     * @var \Cyrnetix\MonetDB\Io\Env|null
     */
    private $resultFields;


    /**
     * Result key
     *
     * @var string
     */
    private $resKey;


    /**
     * Query type key
     *
     * @var int
     */
    private $queryTypeKey;

    /**
     * Type state
     *
     * @var string
     */
    private $typeState;

    /**
     * Parser constructor.
     *
     * @param DuplexStreamInterface $stream
     * @param Executor $executor
     */
    public function __construct(DuplexStreamInterface $stream, Executor $executor)
    {
        $this->stream   = $stream;
        $this->executor = $executor;
        $this->buffer   = new Buffer();

        $executor->on('new', function () {
            $this->nextRequest();
        });
    }

    /**
     * Debug some data
     *
     * @param $message
     */
    public function debug($message): void
    {
        if (true === is_scalar($message)) {
            echo $message . PHP_EOL;
        } else {
            var_dump($message);
        }
    }

    /**
     * Hex dump the data
     *
     * @param $data
     */
    public function dump($data): void
    {
        HexDump::dump($data);
    }

    /**
     * Attach to the stream and start parsing
     *
     * @return void
     */
    public function start(): void
    {
        $this->stream->on('data', [$this, 'parse']);
        $this->stream->on('close', [$this, 'onClose']);
    }

    /**
     * Parse incoming data
     *
     * @param string $data
     * @return void
     */
    public function parse(string $data): void
    {
        $buffer = &$this->buffer;

        $this->debug('RX:');
        $this->dump($data);

        $buffer->append($data);

        // @TODO: Need to get rid of the goto
        packet:
        $length = $buffer->length();

        // Determine the prefix size based on the selected protocol
        $prefixSize = null === $this->protocol || self::PROTOCOL_MAPI_9 === $this->protocol ? 2 : 8;
        if (self::STATE_STANDBY === $this->state) {
            // We need at least two bytes for v9 and 8 for v10
            if ($length < $prefixSize) {
                return;
            }

            $prefix = 2 === $prefixSize ? $buffer->readInt2() : $buffer->readInt8();
            $this->blockSize = $prefix >> 1;
            $this->final = 1 === ($prefix & 1);
            $this->state = self::STATE_BODY;
        }

        // Partial block received
        if ($length < $this->blockSize + $prefixSize) {
            // @TODO Should be fixed
            $this->debug(sprintf('Need more data, received: %s, block: %s, prefix: %s', $length, $this->blockSize, $prefixSize));
            return;
        }

        if (true === $this->final) {
            $this->debug('Received finial packet');
            $buffer->finalize();
        }

        $this->state = self::STATE_STANDBY;

        // Init phase, send authentication response
        if (self::PHASE_INIT === $this->phase) {
            if (false === $this->final) {
                return;
            }

            $this->phase = self::PHASE_GOT_INIT;
            $response = $this->buffer->read($this->blockSize);
            $message = explode(':', $response, 7);
            $this->challenge = $message[0];
            $this->connectOptions = new ConnectOptions(
                $message[1],
                $message[2],
                explode(',', $message[3]),
                $message[4],
                $message[5]
            );

            $this->nextRequest(true);
        }
        // Handle authentication response
        elseif (self::PHASE_AUTH_SENT === $this->phase) {
            // Fix this
            if (false === $this->final) {
                return;
            }

            $authResponse = $this->buffer->read($this->blockSize);
            $respKey = substr($authResponse, 0, 1);
            if (Constants::MSG_PROMPT !== $respKey) {
                if (Constants::MSG_REDIRECT === $respKey) {
                    $redirects = explode("\n", $authResponse, 2);
                    $link = substr($redirects[0], 6);
                    $redirect = explode("://", $link, 2);
                    $protocol = $redirect[0];
                    if (Constants::PROTOCOL_MEROVINGIAN === $protocol) {
                        // Retry auth on same connection, we will get a new challenge
                        $this->executor->enqueue(clone $this->currCommand);
                        $this->currCommand = null;
                        $this->phase = self::PHASE_INIT;
                    }

                    if (Constants::PROTOCOL_MONETDB === $protocol) {
                        // @todo define a proper exception type
                        throw new Exception("Forwarding to another server (", $link, ") not supported.");
                    }
                }

                if (Constants::MSG_MESSAGE === $respKey) {
                    throw new AuthenticationException('Authentication error: ' . $authResponse);
                }
            } else {
                $this->debug("II: Authentication successful.");
                $this->phase = self::PHASE_AUTHENTICATED;
                $this->executor->enqueue(new XReplySizeCommand(Constants::REPLY_SIZE));
                $this->executor->enqueue(new XAutoCommitCommand(true));
                $this->onSuccess();
                $this->nextRequest();
            }
        } else {
            $this->debug('Reading block with size: ' . $this->blockSize);

            $at = $buffer->at();

            // We should receive a key whe we receive the first block
            if (null === $this->resKey) {
                $this->resKey = $buffer->readByte();
                $this->debug('Received new key');
                $this->dump($this->resKey);
            }

            $resKey = $this->resKey;
            // Empty key
            if (Constants::MSG_PROMPT === $resKey) {
                $this->debug('Message: Got prompt');
                if (false === $this->final) {
                    $this->debug('Expecting some more data');
                    $buffer->seek($at);
                    return;
                }

                $this->onResultDone();
                $this->nextRequest();
                $this->debug('Prompt procedure complete');
            } else {
                $this->debug('No prompt: res key: ' . $resKey);
                print_r($buffer);
                if ($buffer->length() < 1) {
                    throw new InvalidResponseException('Invalid response from server. Try re-connecting.');
                }

                // Async reply
                if (Constants::MSG_ASYNC_REPLY === $resKey) {
                    $this->debug('Message: async reply');
                    $resultFields = new Env();
                    $resultFields->type = Constants::MSG_ASYNC_REPLY;
                    $this->resultFields = $resultFields;

                    if (false === $this->final) {
                        $buffer->seek($at);
                        $this->debug('Expecting more data');
                        return;
                    }

                    $this->onResultDone();
                    $this->nextRequest();
                }

                // Receiving a error message
                if (Constants::MSG_MESSAGE === $resKey) {
                    $this->debug('Message: message');

                    //(list(type=MSG_MESSAGE, message=typeLine));

                    if (false === $this->final) {
                        $buffer->seek($at);
                        $this->debug('Expecting more data');
                        return;
                    }

                    list($errno, $errmsg) = explode('!', $buffer->readLine(), 2);
                    $this->onError(new QueryException($errmsg, (int)$errno));
                    $this->nextRequest();
                }

                // Receiving schema header
                if (Constants::MSG_SCHEMA_HEADER === $resKey) {
                    $this->debug('Message: Schema header received');
                    if (false === $this->final) {
                        $buffer->seek($at);
                        $this->debug('Expecting more data');
                        return;
                    }

                    $this->onResultDone();
                    $this->nextRequest();

                    $this->debug('Header procedure complete');
                }

                // Receiving query result
                if (Constants::MSG_QUERY === $resKey) {
                    $this->debug('Message: query result');
                    if (null === $this->queryTypeKey) {
                        $queryTypeKey = (int)$buffer->readByte();
                        $this->queryTypeKey = $queryTypeKey;
                    }

                    $queryType = $this->queryTypeKey;

                    // Query results
                    if (Constants::Q_TABLE === $queryType || Constants::Q_PREPARE === $queryType) {
                        $this->debug('Query type: table/prepare');

                        $typeState = &$this->typeState;

                        // Set new start position to continue from here
                        $at = $buffer->at();

                        // First byte
                        if (null === $this->typeState || $typeState === Constants::Q_TABLE_STATE_RECEIVE_HEADER) {
                            $this->debug('Query type state: header');
                            $this->typeState = Constants::Q_TABLE_STATE_RECEIVE_HEADER;
                            $typeLine = $buffer->readLine();
                            if (null === $typeLine) {
                                $this->debug('More header data expected');
                                $buffer->seek($at);
                                return;
                            }

                            $header = $this->parseHeader($typeLine);
                            $this->debug("QQ: Query result for query " .  $header->id .
                                " with " .  $header->rows . " rows and " .
                                $header->cols .  " cols, " .  $header->index .   " rows.");

                            $resultFields = new Env();
                            $resultFields->type = Constants::Q_TABLE;
                            $resultFields->id = $header->id;
                            $resultFields->rows = $header->rows;
                            $resultFields->cols = $header->cols;
                            $resultFields->index = $header->index;
                            $this->resultFields = $resultFields;
                            $this->typeState = Constants::Q_TABLE_STATE_RECEIVE_TABLES;
                        }

                        $resultFields = &$this->resultFields;

                        if (Constants::Q_TABLE_STATE_RECEIVE_TABLES === $this->typeState) {
                            $this->debug('Query type state: tables');
                            $at = $buffer->at();
                            $tablesLine = $buffer->readLine();
                            if (null === $tablesLine) {
                                $this->debug('require more blocks');
                                $buffer->seek($at);
                                return;
                            }

                            $resultFields->tables = $this->parseTableHeader($tablesLine);
                            $this->typeState = Constants::Q_TABLE_STATE_RECEIVE_NAMES;
                        }

                        if (Constants::Q_TABLE_STATE_RECEIVE_NAMES === $this->typeState) {
                            $this->debug('Query type state: names');
                            $at = $buffer->at();
                            $namesLine = $buffer->readLine();
                            if (null === $namesLine) {
                                $this->debug('require more blocks');
                                $buffer->seek($at);
                                return;
                            }

                            $resultFields->names = $this->parseTableHeader($namesLine);
                            $this->typeState = Constants::Q_TABLE_STATE_RECEIVE_TYPES;
                        }

                        if (Constants::Q_TABLE_STATE_RECEIVE_TYPES === $this->typeState) {
                            $this->debug('Query type state: types');
                            $at = $buffer->at();
                            $typesLine = $buffer->readLine();
                            if (null === $typesLine) {
                                $this->debug('require more blocks');
                                $buffer->seek($at);
                                return;
                            }

                            $resultFields->types = $resultFields->dbtypes = \array_map('\strtoupper', $this->parseTableHeader($typesLine));
                            $this->typeState = Constants::Q_TABLE_STATE_RECEIVE_LENGTHS;
                        }

                        if (Constants::Q_TABLE_STATE_RECEIVE_LENGTHS === $this->typeState) {
                            $this->debug('Query type state: lengths');
                            $at = $buffer->at();
                            $lengthsLine = $buffer->readLine();
                            if (null === $lengthsLine) {
                                $this->debug('require more blocks');
                                $buffer->seek($at);
                                return;
                            }

                            $resultFields->lengths = \array_map('\intval', $this->parseTableHeader($lengthsLine));
                            $this->typeState = Constants::Q_TABLE_STATE_RECEIVE_TUPLES;
                        }

                        $complete = true;
                        if ($resultFields->rows > 0) {
                            $complete = false;
                            if (Constants::Q_TABLE_STATE_RECEIVE_TUPLES === $this->typeState) {
                                while (true) {
                                    $at = $buffer->at();
                                    $tuple = $buffer->readLine();
                                    if (null === $tuple && false === $this->final) {
                                        $this->debug('Incomplete row received, await more data...');
                                        $buffer->seek($at);
                                        return;
                                    }

                                    if ('' === $tuple) {
                                        $this->debug('Tuples complete, abort');
                                        $complete = true;
                                        break;
                                    }
                                    ++$resultFields->rowsReceived;
                                    $this->onResultRow($this->parseTuple($tuple));
                                }
                            }
                        }

                        if (true === $complete) {
                            // More rows?
                            if ($resultFields->rowsReceived < $resultFields->rows) {
                                $this->debug('More data!');
                                $this->sendPacket('Xexport '
                                    . $resultFields->id . ' '
                                    . $resultFields->index . ' '
                                    . ($resultFields->rows - $resultFields->rowsReceived));
                                sleep(1);
                            } else {
                                // Complete
                                $this->onResultDone();
                                $this->nextRequest();
                            }
                        }
                    }

                    // Continuation of Q_TABLE without headers describing table structure
                    if (Constants::Q_BLOCK === $queryType) {
                        $this->debug('Q_BLOCK');

                        throw new Exception('Not implemented yet');

                        $header = $this->parseHeader($typeLine, true);
                        $this->debug("QQ: Continuation for query " . $header->id .
                            " with " .  $header->rows . " rows and "
                            . $header->cols . " cols, index " . $header->index . ".");

                        $resultFields->type = Constants::Q_BLOCK;
                        $resultFields->id = $header->id;
                        $resultFields->rows = $header->rows;
                        $resultFields->cols = $header->cols;
                        $resultFields->index = $header->index;
                        $resultFields->tuples = [];

                        while ('' !== ($line = $block->readLine())) {
                            $resultFields->tuples[] = $line;
                            $this->onResultRow($line);
                        }

                        if (count($resultFields->tuples) !== $header->rows) {
                            throw new InvalidResponseException('Invalid number of rows returned');
                        }

                        $this->resultFields = $resultFields;
                    }

                    // Result of db-altering query
                    if ($queryType == Constants::Q_UPDATE || $queryType == Constants::Q_CREATE) {
                        $typeLine = $buffer->readLine();
                        if (null === $typeLine) {
                            $buffer->seek($at);
                            return;
                        }

                        $header = $this->parseHeader($typeLine, true);
                        $resultFields->type = Constants::Q_UPDATE;
                        $resultFields->id = $header->id;
                        $resultFields->rows = 0;

                        $this->resultFields = $resultFields;

                        $this->onSuccess();
                        $this->nextRequest();
                    }

                    // FIX ME!
                    if ($queryType == Constants::Q_TRANSACTION) {
                        $resultFields->type = Constants::Q_UPDATE;
                        // No need to check the returned values, as there is none. If we get no error, all is well.
                        $this->onSuccess();
                        $this->nextRequest();
                    }
                }
            }

            if (true === $this->final) {
                $this->resKey = null;
            }
        }

        $buffer->trim();
        $this->debug('__goto');

        goto packet;
    }

    /**
     * Parse the header
     *
     * @param string $line
     * @param bool $inverseColsRows
     * @return Header
     */
    private function parseHeader(string $line, bool $inverseColsRows = false): Header
    {
        $this->debug('HEADER: ' . $line);
        $info = \explode(' ', $line);
        $id = (int)$info[1];
        if (false === $inverseColsRows) {
            $rows = (int)$info[2];
            $cols = (int)$info[3];
        } else {
            $rows = (int)$info[3];
            $cols = (int)$info[2];
        }

        $index = (int)$info[4];

        return new Header($id, $rows, $cols, $index);
    }

    /**
     * Parse table header
     *
     * @param string $line
     * @return string[]
     */
    private function parseTableHeader(string $line): array
    {
        $parts = \explode(' #', \substr($line, 2), 2);
        return \explode(",\t", $parts[0]);
    }

    /**
     * Respect the block length
     *
     * @param string $packet
     * @param bool $continue = false
     * @return bool
     */
    public function sendPacket(string $packet, bool $continue = false): bool
    {
        // Shift one bit to the left to make place for the continuation bit
        $prefix = (strlen($packet) << 1) | (true === $continue ? 0 : 1);
        return $this->stream->write(
            (self::PROTOCOL_MAPI_10 === $this->protocol ? Buffer::buildInt8($prefix) : Buffer::buildInt2($prefix)) . $packet
        );
    }

    /**
     * Execute next request
     *
     * @param bool $isHandshake
     * @return bool
     */
    protected function nextRequest(bool $isHandshake = false): bool
    {
        if (false === $isHandshake && $this->phase !== self::PHASE_AUTHENTICATED) {
            return false;
        }

        if ($this->currCommand === null && false === $this->executor->isIdle()) {
            $command = $this->executor->dequeue();
            $this->currCommand = $command;

            if ($command instanceof AuthenticateCommand) {
                $this->phase = self::PHASE_AUTH_SENT;
                $this->sendPacket($command->authenticatePacket($this->challenge, $this->connectOptions));
            } else {
                $this->sendPacket($command->getId() . $command->getSql());
            }
        }

        return true;
    }

    /**
     * On error
     *
     * @param Exception $error
     * @return void
     */
    private function onError(Exception $error): void
    {
        // Reject current command with error if we're currently executing any commands
        // Ignore unsolicited server error in case we're not executing any commands (connection will be dropped)
        if ($this->currCommand !== null) {
            $command = $this->currCommand;
            $this->currCommand = null;

            $command->emit('error', [$error]);
        }
    }

    /**
     * Handle result row
     *
     * @param $row
     */
    private function onResultRow($row): void
    {
        // $this->debug('row data: ' . json_encode($row));
        $command = $this->currCommand;
        $command->emit('result', [$row]);
    }

    /**
     * Handle end of result
     *
     * @return void
     */
    protected function onResultDone(): void
    {
        $command = $this->currCommand;
        $this->currCommand = null;

        if ($command instanceof QueryCommand) {
            $command->resultFields = null !== $this->resultFields ? $this->resultFields->toArray() : [];
        }

        $this->resultFields = null;
        $this->typeState = null;
        $this->resKey = null;
        $this->queryTypeKey = null;

        $command->emit('end');
    }

    /**
     * Fire onsuccess
     *
     * @return void
     */
    protected function onSuccess(): void
    {
        $command = $this->currCommand;
        $this->currCommand = null;
        $result = $this->resultFields;

        if ($command instanceof QueryCommand) {
            $command->affectedRows = $result->rows;
            $command->insertId     = $result->id;
            $command->warningCount = $this->warningCount ?? 0;
            $command->message      = $this->resultFields->message;
        }

        $command->emit('success');
    }

    /**
     * Handle on close event
     *
     * @return void
     */
    public function onClose(): void
    {
        if ($this->currCommand !== null) {
            $command = $this->currCommand;
            $this->currCommand = null;

            if ($command instanceof QuitCommand) {
                $command->emit('success');
            } else {
                $command->emit('error', array(
                    // @todo create a proper exception for this
                    new RuntimeException('Connection lost')
                ));
            }
        }
    }

    /**
     * Parse a tuple
     *
     * @param string|null $tuple
     * @return array
     */
    private function parseTuple(?string $tuple): array
    {
        $headers = $this->resultFields;
        $fieldCount = $headers->cols;

        $length = \strlen($tuple);
        $field = 0;
        $last = 2;
        $cursor = 2;
        $enclosed = false;
        $value = null;
        $eol = false;
        $fields = [];

        while (true) {
            $char = $tuple[$cursor];

            ++$cursor;
            if ($cursor >= $length) {
                $eol = true;
            }

            if ((chr(0x2c) === $char && false === $enclosed) || true === $eol) {
                $value = \trim(\substr($tuple, $last, $cursor - $last - 1));
                $last = $cursor + 1;
                $type = $headers->dbtypes[$field];
                switch ($type) {
                    case Constants::DB_TYPE_WRD:
                    case Constants::DB_TYPE_TINYINT:
                    case Constants::DB_TYPE_SMALLINT:
                    case Constants::DB_TYPE_INT:
                    case Constants::DB_TYPE_MONTH_INTERVAL:
                    case Constants::DB_TYPE_BIGINT:
                    case Constants::DB_TYPE_HUGEINT:
                        $fields[$headers->names[$field]] = Constants::NULL !== $value ? (int)$value : null;
                        break;

                    case Constants::DB_TYPE_REAL:
                    case Constants::DB_TYPE_DECIMAL:
                    case Constants::DB_TYPE_DOUBLE:
                    case Constants::DB_TYPE_SEC_INTERVAL:
                        $fields[$headers->names[$field]] = Constants::NULL !== $value ? (float)$value : null;
                        break;

                    case Constants::DB_TYPE_BOOLEAN:
                        $fields[$headers->names[$field]] = $this->parseBool($value);
                        break;

                    case Constants::DB_TYPE_CHAR:
                    case Constants::DB_TYPE_VARCHAR:
                    case Constants::DB_TYPE_CLOB:
                    case Constants::DB_TYPE_STR:
                    case Constants::DB_TYPE_INTERVAL:
                    case Constants::DB_TYPE_DATE:
                    case Constants::DB_TYPE_TIME:
                    case Constants::DB_TYPE_TIMETZ:
                    case Constants::DB_TYPE_TIMESTAMP:
                    case Constants::DB_TYPE_TIMESTAMPTZ:
                    case Constants::DB_TYPE_BLOB:
                        $fields[$headers->names[$field]] = Constants::NULL !== $value ? (string)$value : null;
                        break;
                }

                $last = $cursor;
                ++$field;
            }

            if ('"' === $char) {
                if (false === $enclosed) {
                    $last = $cursor;
                }

                if (true === $enclosed && $field - 1 === $fieldCount) {
                    $eol = true;
                }

                $enclosed = !$enclosed;
            }

            if (true === $eol || $field > $fieldCount) {
                break;
            }
        }

        return $fields;
    }

    /**
     * Parse boolean value
     *
     * @param string $value
     * @return bool|null
     * @throws Exception
     */
    private function parseBool(string $value): ?bool
    {
        switch ($value) {
            case Constants::NULL:
                return null;

            case Constants::TRUE:
                return true;

            case Constants::FALSE:
                return false;

            default:
                throw new Exception('Invalid boolean value encountered');
        }
    }
}
