<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a CSV row cannot be imported because it fails validation.
 */
class InvalidProductRowException extends RuntimeException {}
