<?php

namespace App\Exceptions;

use RuntimeException;

/** The requested payment action isn't allowed for this booking right now. */
class PaymentNotAllowedException extends RuntimeException {}
