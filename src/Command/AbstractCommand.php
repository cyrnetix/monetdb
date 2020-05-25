<?php

declare(strict_types=1);

namespace Cyrnetix\MonetDB\Command;

use Evenement\EventEmitter;

/**
 * Abstract monet db command
 *
 * @package Cyrnetix\MonetDB\Command
 */
abstract class AbstractCommand extends EventEmitter implements CommandInterface
{

}
