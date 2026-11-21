<?php

namespace App\Chatbot;

/**
 * Deterministic checks around the language model.
 *
 * The system instruction already tells the model the rules. These checks make
 * the rules that matter most hold even when the model doesn't follow them:
 *  - obvious attempts to override or expose the instructions never reach it;
 *  - an answer may only quote rates, phone numbers, emails and links that
 *    appear in the resort data or in the guest's own words;
 *  - an answer may not claim to have booked, cancelled or paid for anything.
 */
class Guardrails
{
    /** Phrases that only make sense as an attempt to override or expose the assistant's instructions. */
    private const INJECTION_PATTERNS = [
        // "ignore all previous instructions", "disregard your rules"
        '/\b(?:ignore|disregard|forget|override|bypass)\s+(?:all\s+|any\s+|the\s+)?(?:previous|prior|above|earlier|preceding|your|system|these|those)\s+(?:instructions?|rules?|prompts?|guidelines|directives|messages?)\b/iu',
        // "reveal your system prompt", "print the hidden instructions"
        '/\b(?:reveal|show|print|repeat|output|display|dump|leak|tell\s+me|give\s+me)\s+(?:me\s+)?(?:your|the)\s+(?:system\s+prompt|prompt|system\s+(?:message|instructions)|(?:hidden|initial|original|secret|internal|developer)\s+(?:instructions|prompt|message|rules)|secret\s+code)\b/iu',
        // persona swaps and well-known jailbreaks
        '/\b(?:you\s+are\s+now\s+(?:dan|an?\s+(?:unrestricted|unfiltered|jailbroken|different|new))|do\s+anything\s+now|developer\s+mode|jailbreak|dan\s+mode)\b/iu',
        // "pretend you have no rules", "act as if you have no restrictions", "from now on you are ..."
        '/\b(?:pretend|act\s+as\s+if|imagine)\s+(?:that\s+)?you\s+(?:have|had)\s+no\s+(?:rules|restrictions|limits|filters|guidelines)\b/iu',
        '/\bfrom\s+now\s+on,?\s+you\s+(?:are|will\s+be|must\s+act\s+as)\b/iu',
        // Filipino: "kalimutan mo ang mga instructions", "huwag mong sundin ang rules mo"
        '/\b(?:kalimutan|balewalain|huwag\s+(?:mo(?:ng)?\s+)?(?:pansinin|sundin))\s+(?:mo\s+)?(?:na\s+)?(?:ang\s+|yung\s+)?(?:lahat\s+ng\s+)?(?:mga\s+)?(?:naunang\s+)?(?:instructions?|utos|rules?|patakaran|bilin)\b/iu',
        '/\b(?:ipakita|sabihin|ibigay)\s+(?:mo\s+)?(?:sa\s+akin\s+)?(?:ang\s+|yung\s+)?(?:system\s+prompt|prompt\s+mo|instructions?\s+mo|lihim\s+na\s+code)\b/iu',
    ];

    /** Secrets a guest should never type into a chat: they are not sent to the model at all. */
    private const SECRET_PATTERNS = [
        // card numbers: 13–19 digits, optionally grouped by spaces or dashes
        '/(?<!\d)(?:\d[ -]?){12,18}\d(?!\d)/u',
        // "my MPIN is 1234", "OTP: 482913", "password: hunter2", "CVV 123"
        '/\b(?:m-?pin|otp|one[\s-]time\s+(?:pin|code|password)|cvv|cvc|password|passcode|pin\s+code)\b\s*(?:is|ko\s+ay|ko|:|=|-)?\s*\S*\d/iu',
    ];

    /** An answer that asks the guest for a secret, which the resort never does. */
    private const SECRET_REQUEST_PATTERNS = [
        '/\b(?:send|give|share|type|enter|provide|tell|reply\s+with|ibigay|i-?send|sabihin)\b[^.?!\n]{0,40}\b(?:m-?pin|otp|one[\s-]time\s+(?:pin|code|password)|password|passcode|cvv|cvc|card\s+number|pin)\b/iu',
    ];

    /** First-person claims of having acted on a booking, which the assistant cannot do. */
    private const ACTION_CLAIM_PATTERNS = [
        // "I've booked", "I have cancelled", "I just reserved"
        '/\bI(?:[\'’]ve|\s+have|\s+had)?(?:\s+(?:already|just|now|successfully))?\s+(?:booked|reserved|cancell?ed|refunded|rescheduled)\b/iu',
        // "I'll reserve it", "I will book", "I can cancel"
        '/\bI(?:[\'’]ll|\s+will|\s+can|\s+could)(?:\s+(?:now|also|go\s+ahead\s+and))?\s+(?:book|reserve|cancel|refund|reschedule)\b/iu',
        // "I confirmed your booking", "I've made a reservation"
        '/\bI(?:[\'’]ve|[\'’]ll|\s+have|\s+will)?(?:\s+(?:already|just|now|successfully|go\s+ahead\s+and))?\s+(?:confirm(?:ed)?|approved?|process(?:ed)?|made|make|submit(?:ted)?|placed?)\s+(?:a|the|your)\s+(?:booking|reservation|payment|request)\b/iu',
        // "Your reservation is confirmed." but not "…is confirmed once staff approve it"
        '/\b(?:your|the)\s+(?:booking|reservation)\s+(?:is|has\s+been)\s+(?:now\s+|already\s+)?(?:booked|reserved|confirmed|cancell?ed)\b(?!\s+(?:by|once|when|after|if|only|until|as\s+soon))/iu',
        // Filipino: "na-book ko na", "kinansela ko na"
        '/\b(?:na-?book|nai-?book|na-?reserve|nai-?reserve|na-?cancel|nakansela|kinansela|binook)\s+ko\s+na\b/iu',
    ];

    /** ₱2,500 · PHP 2500.00 · P2500 · 2,500 pesos */
    private const AMOUNT_PATTERN = '/(?:₱|\bPHP|(?<![A-Za-z])P(?=\d))\s?(?<a>\d{1,3}(?:,\d{3})+|\d+)(?<ad>\.\d{1,2})?|(?<b>\d{1,3}(?:,\d{3})+|\d+)(?<bd>\.\d{1,2})?\s?(?:pesos?|piso|PHP)\b/iu';

    /** 0917 123 4567 · 0917-123-4567 · +63 917 123 4567 */
    private const PHONE_PATTERN = '/(?<![\d+])(?:\+?63|0)[\d\s-]{8,14}\d(?!\d)/u';

    private const EMAIL_PATTERN = '/[A-Z0-9._%+-]+@[A-Z0-9-]+(?:\.[A-Z0-9-]+)*\.[A-Z]{2,}/iu';

    private const LINK_PATTERN = '/\b(?:https?:\/\/|www\.)[^\s<>"\'()\[\]]+/iu';

    public function looksLikeInjection(string $question): bool
    {
        return $this->matchesAny(self::INJECTION_PATTERNS, $question);
    }

    /** The guest typed something that looks like a card number, OTP, MPIN or password. */
    public function containsSecret(string $question): bool
    {
        return $this->matchesAny(self::SECRET_PATTERNS, $question);
    }

    /** The answer asks the guest for an OTP, MPIN, password or card number. */
    public function asksForSecret(string $answer): bool
    {
        return $this->matchesAny(self::SECRET_REQUEST_PATTERNS, $answer);
    }

    public function claimsAction(string $answer): bool
    {
        return $this->matchesAny(self::ACTION_CLAIM_PATTERNS, $answer);
    }

    /**
     * Rates, phone numbers, emails and links in the answer that appear in
     * neither the resort data nor what the guest wrote.
     *
     * @param  string  $known  the resort data plus the guest's own messages
     * @return list<string> the figures as written in the answer
     */
    public function ungroundedFigures(string $answer, string $known): array
    {
        $missing = [];

        $amounts = $this->amounts($known);
        foreach ($this->amounts($answer) as $value => $raw) {
            if (! isset($amounts[$value])) {
                $missing[] = $raw;
            }
        }

        $phones = $this->phones($known);
        foreach ($this->phones($answer) as $digits => $raw) {
            if (! isset($phones[$digits])) {
                $missing[] = $raw;
            }
        }

        $knownText = mb_strtolower($known);

        foreach ($this->matches(self::EMAIL_PATTERN, $answer) as $email) {
            if (! str_contains($knownText, mb_strtolower($email))) {
                $missing[] = $email;
            }
        }

        $ownHost = mb_strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        foreach ($this->matches(self::LINK_PATTERN, $answer) as $link) {
            $bare = $this->bareLink($link);
            $host = strtok($bare, '/:');

            if (! str_contains($knownText, $bare) && ($ownHost === '' || $host !== $ownHost)) {
                $missing[] = rtrim($link, '.,;:!?');
            }
        }

        return $missing;
    }

    /**
     * @return array<string, string> amount with two decimals => as written
     */
    private function amounts(string $text): array
    {
        preg_match_all(self::AMOUNT_PATTERN, $text, $found, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);

        $amounts = [];

        foreach ($found as $m) {
            $digits = str_replace(',', '', $m['a'] ?? $m['b']);
            $amounts[bcadd($digits.($m['ad'] ?? $m['bd'] ?? ''), '0', 2)] ??= trim($m[0]);
        }

        return $amounts;
    }

    /**
     * @return array<string, string> local digits (09…) => as written
     */
    private function phones(string $text): array
    {
        $phones = [];

        foreach ($this->matches(self::PHONE_PATTERN, $text) as $raw) {
            $digits = (string) preg_replace('/\D/', '', $raw);

            if (str_starts_with($digits, '63')) {
                $digits = '0'.substr($digits, 2);
            }

            if (strlen($digits) >= 10 && strlen($digits) <= 11) {
                $phones[$digits] ??= trim($raw);
            }
        }

        return $phones;
    }

    /** "https://www.Facebook.com/Labnasan/." → "facebook.com/labnasan" */
    private function bareLink(string $link): string
    {
        $bare = mb_strtolower(rtrim($link, '.,;:!?'));
        $bare = (string) preg_replace('#^https?://#', '', $bare);
        $bare = (string) preg_replace('#^www\.#', '', $bare);

        return rtrim($bare, '/');
    }

    /**
     * @param  list<string>  $patterns
     */
    private function matchesAny(array $patterns, string $text): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function matches(string $pattern, string $text): array
    {
        preg_match_all($pattern, $text, $found);

        return $found[0];
    }
}
