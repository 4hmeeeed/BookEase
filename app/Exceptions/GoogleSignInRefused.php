<?php

namespace App\Exceptions;

use RuntimeException;

/** A Google sign-in that must not log anyone in. The message is shown to the visitor. */
class GoogleSignInRefused extends RuntimeException {}
