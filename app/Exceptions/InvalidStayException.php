<?php

namespace App\Exceptions;

use InvalidArgumentException;

/** The requested dates are not a valid stay (past, reversed, too long, …). */
class InvalidStayException extends InvalidArgumentException {}
