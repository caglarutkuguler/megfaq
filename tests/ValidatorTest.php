<?php
/**
 * Product FAQ and Customer Questions
 *
 * The validator, against plain PHP - no PrestaShop, no database.
 *
 * The rules here decide what a stranger can put in the shop's moderation queue,
 * so each one is worth a test rather than a code review. The awkward cases are
 * the ones that matter: a question that is exactly at the limit, a name with an
 * apostrophe in it, an address with a plus sign, text that arrives with Windows
 * line endings, and the spam test refusing to fire on the one link a helpful
 * shopper pastes to show which page they mean.
 *
 * Run: php tests/ValidatorTest.php
 *
 * @author    MEG Venture <info@megventure.com>
 * @copyright 2019-2026 MEG Venture
 * @license   Academic Free License 3.0 (AFL-3.0)
 */

define('MEGFAQ_TESTS', true);

require_once __DIR__ . '/../classes/MegFaqValidator.php';

$passed = 0;
$failed = 0;

function check($label, $condition)
{
    global $passed, $failed;

    if ($condition) {
        ++$passed;

        return;
    }

    ++$failed;
    echo '  FAIL: ' . $label . PHP_EOL;
}

/* ------------------------------------------------------------------ cleaning */

echo 'Cleaning' . PHP_EOL;

check(
    'a single line loses its newlines',
    MegFaqValidator::cleanLine("Does it\nwork with\r\nPrestaShop 9?") === 'Does it work with PrestaShop 9?'
);
check(
    'runs of whitespace collapse',
    MegFaqValidator::cleanLine("Two     spaces\t\there") === 'Two spaces here'
);
// strip_tags() runs first and removes NUL outright, while the character class
// turns the rest into spaces. Both paths end with nothing below 0x20 left in the
// string, which is the property that matters; asserting the exact spacing would
// pin down an implementation detail of strip_tags instead.
check(
    'no control character survives cleanLine',
    !preg_match('/[\x00-\x1F\x7F]/', MegFaqValidator::cleanLine("Bad\x00char\x07here\x1Fnow"))
);
check(
    'and the words either side are kept',
    MegFaqValidator::cleanLine("Bad\x07char") === 'Bad char'
);
check(
    'no control character survives cleanText either, newlines aside',
    !preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', MegFaqValidator::cleanText("a\x07b\x0Cc\nd"))
);
check(
    'tags are stripped rather than escaped',
    MegFaqValidator::cleanLine('<b>bold</b> question') === 'bold question'
);
check(
    'an apostrophe survives untouched',
    MegFaqValidator::cleanLine("O'Brien's question") === "O'Brien's question"
);
check(
    'an ampersand is not encoded on the way in',
    MegFaqValidator::cleanLine('Fees & charges?') === 'Fees & charges?'
);
check(
    'accented characters survive',
    MegFaqValidator::cleanLine('Ürün değerlendirmesi görünür mü?') === 'Ürün değerlendirmesi görünür mü?'
);

check(
    'a paragraph break survives in an answer',
    MegFaqValidator::cleanText("First line.\n\nSecond line.") === "First line.\n\nSecond line."
);
check(
    'windows line endings are normalised',
    MegFaqValidator::cleanText("First.\r\n\r\nSecond.") === "First.\n\nSecond."
);
check(
    'three or more blank lines collapse to one break',
    MegFaqValidator::cleanText("First.\n\n\n\n\nSecond.") === "First.\n\nSecond."
);
check(
    'a single newline is left alone',
    MegFaqValidator::cleanText("Line one.\nLine two.") === "Line one.\nLine two."
);
check('cleanText trims', MegFaqValidator::cleanText("   padded   ") === 'padded');

/* ------------------------------------------------------------------- lengths */

echo 'Lengths' . PHP_EOL;

check('length counts characters, not bytes', MegFaqValidator::length('ğüşiöç') === 6);
check('length of an empty string is zero', MegFaqValidator::length('') === 0);

$atFloor = str_repeat('a', MegFaqValidator::QUESTION_MIN);
$belowFloor = str_repeat('a', MegFaqValidator::QUESTION_MIN - 1);
$atCeiling = str_repeat('a', MegFaqValidator::QUESTION_MAX);
$aboveCeiling = str_repeat('a', MegFaqValidator::QUESTION_MAX + 1);

check('a question exactly at the floor is accepted', MegFaqValidator::isQuestionLength($atFloor));
check('one character under the floor is not', !MegFaqValidator::isQuestionLength($belowFloor));
check('a question exactly at the ceiling is accepted', MegFaqValidator::isQuestionLength($atCeiling));
check('one character over the ceiling is not', !MegFaqValidator::isQuestionLength($aboveCeiling));
check('"how?" is too short to be a question', !MegFaqValidator::isQuestionLength('how?'));
check(
    'a question without a question mark is still a question',
    MegFaqValidator::isQuestionLength('I need to know whether this works on 8.1')
);
check(
    'whitespace does not count towards the floor',
    !MegFaqValidator::isQuestionLength('   ok?   ')
);

check('a two-character name is accepted', MegFaqValidator::isNameLength('Jo'));
check('a one-character name is not', !MegFaqValidator::isNameLength('J'));
check('an empty name is not', !MegFaqValidator::isNameLength(''));
check('a name of only spaces is not', !MegFaqValidator::isNameLength('     '));
check(
    'a 60-character name is accepted',
    MegFaqValidator::isNameLength(str_repeat('a', MegFaqValidator::NAME_MAX))
);
check(
    'a 61-character name is not',
    !MegFaqValidator::isNameLength(str_repeat('a', MegFaqValidator::NAME_MAX + 1))
);

/* -------------------------------------------------------------------- blanks */

echo 'Blanks' . PHP_EOL;

check('an empty string is blank', MegFaqValidator::isBlank(''));
check('whitespace is blank', MegFaqValidator::isBlank("  \n\t "));
check('a tag with no text is blank', MegFaqValidator::isBlank('<br>'));
check('a zero is not blank', !MegFaqValidator::isBlank('0'));
check('real text is not blank', !MegFaqValidator::isBlank('yes'));

/* -------------------------------------------------------------------- e-mail */

echo 'E-mail' . PHP_EOL;

check('an ordinary address passes', MegFaqValidator::isEmail('someone@example.com'));
check('a plus tag passes', MegFaqValidator::isEmail('someone+faq@example.com'));
check('a subdomain passes', MegFaqValidator::isEmail('someone@mail.example.co.uk'));
check('surrounding whitespace is tolerated', MegFaqValidator::isEmail('  someone@example.com  '));
check('no at sign fails', !MegFaqValidator::isEmail('someone.example.com'));
check('no dotted domain fails', !MegFaqValidator::isEmail('someone@localhost'));
check('two at signs fail', !MegFaqValidator::isEmail('a@b@example.com'));
check('an empty address fails', !MegFaqValidator::isEmail(''));
check('a space inside fails', !MegFaqValidator::isEmail('some one@example.com'));
check(
    'an absurdly long address fails',
    !MegFaqValidator::isEmail(str_repeat('a', 130) . '@example.com')
);

/* ---------------------------------------------------------------------- spam */

echo 'Spam' . PHP_EOL;

check(
    'one link is not spam - people paste a link to show what they mean',
    !MegFaqValidator::looksLikeSpam('Is this like https://example.com/page ?')
);
check(
    'two links are spam',
    MegFaqValidator::looksLikeSpam('http://a.example http://b.example')
);
check(
    'a bbcode link is spam whatever else is in the text',
    MegFaqValidator::looksLikeSpam('nice shop [url=http://a.example]click[/url]')
);
check(
    'an anchor tag is spam',
    MegFaqValidator::looksLikeSpam('<a href="http://a.example">click</a>')
);
check(
    'a plain question is not spam',
    !MegFaqValidator::looksLikeSpam('Does the licence cover two shops?')
);
check(
    'the word www on its own once is not spam',
    !MegFaqValidator::looksLikeSpam('I found you at www.example.com, is this compatible?')
);

/* ------------------------------------------------------------------------- */

echo PHP_EOL;
echo $failed === 0
    ? 'OK - ' . $passed . ' assertions passed' . PHP_EOL
    : $failed . ' of ' . ($passed + $failed) . ' assertions FAILED' . PHP_EOL;

exit($failed === 0 ? 0 : 1);
