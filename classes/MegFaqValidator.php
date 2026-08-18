<?php
/**
 * Product FAQ and Customer Questions
 *
 * Validation and sanitising for questions and answers.
 *
 * Text is stored raw and escaped once, at render. Encoding at input and again at
 * output is how a shopper called O'Brien ends up published as O&amp;#039;Brien,
 * and it is a one-way mistake: by the time anyone notices, the double-encoded
 * text is what is in the table.
 *
 * Deliberately free of PrestaShop dependencies so it can be unit tested with
 * plain PHP - see tests/ValidatorTest.php.
 *
 * @author    MEG Venture <info@megventure.com>
 * @copyright 2019-2026 MEG Venture
 * @license   Academic Free License 3.0 (AFL-3.0)
 */

if (!defined('_PS_VERSION_') && !defined('MEGFAQ_TESTS')) {
    exit;
}

class MegFaqValidator
{
    const NAME_MIN = 2;
    const NAME_MAX = 60;

    /**
     * A question is one line of text. The ceiling is generous because a shopper
     * describing their setup before asking is writing a useful question, not
     * abusing the field; the floor exists to catch "?" and "how".
     */
    const QUESTION_MIN = 10;
    const QUESTION_MAX = 500;

    /** The answer column is TEXT; this is the form's ceiling, well under it. */
    const ANSWER_MAX = 5000;

    /**
     * Collapse whitespace and drop control characters from a single-line field.
     *
     * Newlines go here too: they are legitimate in an answer but not in a name
     * or a question, where they only break the layout.
     *
     * @param string $value
     *
     * @return string
     */
    public static function cleanLine($value)
    {
        $value = strip_tags((string) $value);
        $value = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value);
        $value = preg_replace('/\s+/u', ' ', $value);

        return trim($value);
    }

    /**
     * Multi-line text: paragraph breaks survive, control characters do not, and
     * a run of blank lines collapses to one.
     *
     * @param string $value
     *
     * @return string
     */
    public static function cleanText($value)
    {
        $value = strip_tags((string) $value);
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
        $value = preg_replace('/[ \t]+/u', ' ', $value);
        $value = preg_replace('/\n{3,}/u', "\n\n", $value);

        return trim($value);
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    public static function isBlank($value)
    {
        return self::length(self::cleanLine($value)) === 0;
    }

    /**
     * Character count, not byte count. mb_strlen is not guaranteed to exist on
     * every host, and a question limit that silently halves for a shop writing
     * in Turkish or Polish would be a strange kind of bug to chase.
     *
     * @param string $value
     *
     * @return int
     */
    public static function length($value)
    {
        $value = (string) $value;

        if (function_exists('mb_strlen')) {
            return (int) mb_strlen($value, 'UTF-8');
        }

        return (int) strlen(preg_replace('/[\x80-\xBF]/', '', $value));
    }

    /**
     * A question mark is not required. Plenty of real questions are phrased as
     * "I need to know whether ..." and rejecting those would teach shoppers that
     * the form is broken rather than that their phrasing is.
     *
     * @param string $value
     *
     * @return bool
     */
    public static function isQuestionLength($value)
    {
        $length = self::length(self::cleanLine($value));

        return $length >= self::QUESTION_MIN && $length <= self::QUESTION_MAX;
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    public static function isNameLength($value)
    {
        $length = self::length(self::cleanLine($value));

        return $length >= self::NAME_MIN && $length <= self::NAME_MAX;
    }

    /**
     * Deliberately permissive: the address is only ever used to reply to the
     * person who typed it, so the only failure that matters is one that cannot
     * be delivered, and no regular expression predicts that. Anything with a
     * local part, an @ and a dotted domain passes.
     *
     * @param string $value
     *
     * @return bool
     */
    public static function isEmail($value)
    {
        $value = self::cleanLine($value);

        if ($value === '' || self::length($value) > 128) {
            return false;
        }

        return (bool) preg_match('/^[^@\s]+@[^@\s.]+(\.[^@\s.]+)+$/u', $value);
    }

    /**
     * The crude spam test: a question that is mostly links is not a question.
     *
     * Kept crude on purpose. A stricter filter rejects real questions - people
     * paste a URL to show which page they mean - and the cost of a false
     * negative here is one row in a moderation queue that a human is reading
     * anyway.
     *
     * @param string $value
     *
     * @return bool
     */
    public static function looksLikeSpam($value)
    {
        $value = (string) $value;

        return (int) preg_match_all('~https?://|www\.~i', $value) >= 2
            || (bool) preg_match('/\[url[=\]]/i', $value)
            || (bool) preg_match('/<a\s/i', $value);
    }
}
