<?php

declare(strict_types=1);

namespace Cyrnetix\MonetDB\Io;

class Constants
{
    /**
     * Protocol types
     */
    public const PROTOCOL_V9  = 9;
    public const PROTOCOL_V10 = 10;
    public const PROT10       = 'PROT10';

    /**
     * Merovingian is meant as end-user program to start and stop one or more MonetDB databases
     *
     * @const string
     */
    public const PROTOCOL_MEROVINGIAN = 'merovingian';

    /**
     * Generic monet DB protocol
     *
     * @const string
     */
    public const PROTOCOL_MONETDB     = 'monetdb';

    /**
     * Endian types
     *
     * @const string
     */
    public const ENDIAN_BIG    = 'BIG';
    public const ENDIAN_LITTLE = 'LITTLE';

    /**
     * Query language (sql)
     *
     * @const string
     */
    public const LANGUAGE_SQL = 'sql';

    /**
     * Compression types (not supported yet)
     *
     * @const string
     */
    public const COMPRESSION_NONE   = 'COMPRESSION_NONE';
    public const COMPRESSION_SNAPPY = 'COMPRESSION_SNAPPY';
    public const COMPRESSION_LZ4    = 'COMPRESSION_LZ4';

    /**
     * Hash methods
     *
     * @const string
     */
    public const HASH_SHA256 = 'sha256';
    public const HASH_SHA512 = 'sha512';
    public const HASH_MD5    = 'md5';
    public const HASH_SHA1   = 'sha1';
    public const HASH_CRC32  = 'crc32';

    /**
     * Messages prefixes
     *
     * @const string
     */
    public const MSG_REDIRECT      = "^";
    public const MSG_MESSAGE       = "!";
    public const MSG_PROMPT        = "";
    public const MSG_QUERY         = "&";
    public const MSG_SCHEMA_HEADER = "%";
    public const MSG_ASYNC_REPLY   = "_";

    /**
     * Response types
     *
     * @const string
     */
    public const Q_TABLE       = 1;
    public const Q_UPDATE      = 2;
    public const Q_CREATE      = 3;
    public const Q_TRANSACTION = 4;
    public const Q_PREPARE     = 5;
    public const Q_BLOCK       = 6;

    /**
     * Response type details
     *
     * @const string
     */
    public const Q_TABLE_STATE_RECEIVE_HEADER  = 1;
    public const Q_TABLE_STATE_RECEIVE_TABLES  = 2;
    public const Q_TABLE_STATE_RECEIVE_NAMES   = 3;
    public const Q_TABLE_STATE_RECEIVE_TYPES   = 4;
    public const Q_TABLE_STATE_RECEIVE_LENGTHS = 5;
    public const Q_TABLE_STATE_RECEIVE_TUPLES  = 6;

    /**
     * Reply size
     *
     * @const int
     */
    public const REPLY_SIZE = 100;

    /**
     * Value types in messaging
     *
     * @const string
     */
    public const CT_INT  = '0L';
    public const CT_NUM  = '1L';
    public const CT_CHR  = '2L';
    public const CT_BOOL = '3L';
    public const CT_RAW  = '4L';

    /**
     * Value types as stored in the db
     *
     * @const string
     */
    public const DB_TYPE_WRD = 'WRD';
    public const DB_TYPE_TINYINT = 'TINYINT';
    public const DB_TYPE_SMALLINT = 'SMALLINT';
    public const DB_TYPE_INT = 'INT';
    public const DB_TYPE_MONTH_INTERVAL = 'MONTH_INTERVAL';
    public const DB_TYPE_BIGINT = 'BIGINT';
    public const DB_TYPE_HUGEINT = 'HUGEINT';
    public const DB_TYPE_REAL = 'REAL';
    public const DB_TYPE_DECIMAL = 'DECIMAL';
    public const DB_TYPE_DOUBLE = 'DOUBLE';
    public const DB_TYPE_SEC_INTERVAL = 'SEC_INTERVAL';
    public const DB_TYPE_CHAR = 'CHAR';
    public const DB_TYPE_VARCHAR = 'VARCHAR';
    public const DB_TYPE_CLOB = 'CLOB';
    public const DB_TYPE_STR = 'STR';
    public const DB_TYPE_INTERVAL = 'INTERVAL';
    public const DB_TYPE_DATE = 'DATE';
    public const DB_TYPE_TIME = 'TIME';
    public const DB_TYPE_TIMETZ = 'TIMETZ';
    public const DB_TYPE_TIMESTAMP = 'TIMESTAMP';
    public const DB_TYPE_TIMESTAMPTZ = 'TIMESTAMPTZ';
    public const DB_TYPE_BOOLEAN = 'BOOLEAN';
    public const DB_TYPE_BLOB = 'BLOB';
    public const NULL = 'NULL';

    /**
     * Boolean types
     *
     * @cons string
     */
    const TRUE = 'true';
    const FALSE = 'false';

    /**
     * Monet data types
     *
     * @var array
     */
    public static $monetTypes = [
        ["integer", "numeric", "character", "character", "logical", "raw"],
        [5, 6, 4, 6, 1, 1]
    ];

    /**
     * Type to native php
     *
     * @var string[]
     */
    public static $typeNames = [
        'WRD'              => 'int',
        'TINYINT'          => 'int',
        'SMALLINT'         => 'int',
        'INT'              => 'int',
        'MONTH_INTERVAL'   => 'int', // month_interval is the diff between date cols, int
        'BIGINT'           => 'int',
        'HUGEINT'          => 'int',
        'REAL'             => 'float',
        'DECIMAL'          => 'float',
        'DOUBLE'           => 'float',
        'SEC_INTERVAL'     => 'float', // sec_interval is the difference between timestamps, float
        'CHAR'             => 'string',
        'VARCHAR'          => 'string',
        'CLOB'             => 'string',
        'STR'              => 'string',
        'INTERVAL'         => 'string',
        'DATE'             => 'string',
        'TIME'             => 'string',
        'TIMETZ'           => 'string',
        'TIMESTAMP'        => 'string',
        'TIMESTAMPTZ'      => 'string',
        'BOOLEAN'          => 'bool',
        'BLOB'             => 'string',
    ];

    /**
     * Return the php type
     *
     * @param $type
     * @return string|null
     */
    public static function mapType($type): ?string
    {
        return self::$monetTypes[\strtoupper($type)] ?? null;
    }
}
