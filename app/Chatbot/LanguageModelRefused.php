<?php

namespace App\Chatbot;

use RuntimeException;

/** The model's safety filters blocked the prompt or the answer. */
class LanguageModelRefused extends RuntimeException {}
