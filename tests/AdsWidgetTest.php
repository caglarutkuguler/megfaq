<?php
/**
 * Product FAQ and Customer Questions
 *
 * The two URL predicates in the configure-page ads widget, against plain PHP.
 *
 * Both exist because the widget puts strings from somewhere else into this
 * shop's back office: one decides which address the shop is willing to fetch,
 * the other decides which addresses out of that response the shop is willing to
 * put in an href or a src. Both are one-line-to-break and invisible when broken
 * - a widget that renders is a widget that looks fine - so they are worth a test
 * rather than a code review.
 *
 * Run: php tests/AdsWidgetTest.php
 *
 * @author    MEG Venture <info@megventure.com>
 * @copyright 2019-2026 MEG Venture & Consulting Ltd.
 * @license   https://opensource.org/licenses/MIT MIT License
 */

define('_PS_VERSION_', '9.1.4');

require_once __DIR__ . '/../classes/MegVentureAdsWidget.php';

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

/* ------------------------------------------------- what we will link to --- */

echo 'safeUrl - addresses out of the feed' . PHP_EOL;

check(
    'an ordinary https address passes through unchanged',
    MegVentureAdsWidgetV2::safeUrl('https://megventure.com/en/12-modules') === 'https://megventure.com/en/12-modules'
);
check(
    'http is allowed too - some shops still run one',
    MegVentureAdsWidgetV2::safeUrl('http://megventure.com/x') === 'http://megventure.com/x'
);
check(
    'a query string and a fragment survive',
    MegVentureAdsWidgetV2::safeUrl('https://megventure.com/x?a=1&b=2#c') === 'https://megventure.com/x?a=1&b=2#c'
);
check('surrounding whitespace is trimmed', MegVentureAdsWidgetV2::safeUrl("  https://megventure.com/x \n") === 'https://megventure.com/x');

// The whole point. htmlspecialchars makes each of these safe to sit inside an
// attribute and leaves it exactly as dangerous to click.
check('javascript: is refused', MegVentureAdsWidgetV2::safeUrl('javascript:alert(1)') === '');
check('JavaScript: is refused whatever the case', MegVentureAdsWidgetV2::safeUrl('JaVaScRiPt:alert(1)') === '');
check('data: is refused', MegVentureAdsWidgetV2::safeUrl('data:text/html;base64,PHNjcmlwdD4=') === '');
check('vbscript: is refused', MegVentureAdsWidgetV2::safeUrl('vbscript:msgbox(1)') === '');
check('file: is refused', MegVentureAdsWidgetV2::safeUrl('file:///etc/passwd') === '');

// A scheme with no host is not an address we can put in a page from a feed on
// another origin, and a relative one would resolve against the back office.
check('a relative path is refused', MegVentureAdsWidgetV2::safeUrl('/en/12-modules') === '');
check('a protocol-relative address is refused', MegVentureAdsWidgetV2::safeUrl('//attacker.test/x') === '');
check('a bare word is refused', MegVentureAdsWidgetV2::safeUrl('megventure.com') === '');
check('empty is empty', MegVentureAdsWidgetV2::safeUrl('') === '');
check('null is empty', MegVentureAdsWidgetV2::safeUrl(null) === '');
check('whitespace only is empty', MegVentureAdsWidgetV2::safeUrl('   ') === '');

/* ------------------------------------------------- what we will fetch ----- */

echo 'isAllowedWidgetUrl - the address the shop fetches' . PHP_EOL;

check('the widget host is allowed', MegVentureAdsWidgetV2::isAllowedWidgetUrl('https://megventure.com/index.php?fc=module') === true);
check('www of the widget host is allowed', MegVentureAdsWidgetV2::isAllowedWidgetUrl('https://www.megventure.com/x') === true);
check('the host match is case-insensitive', MegVentureAdsWidgetV2::isAllowedWidgetUrl('https://MegVenture.com/x') === true);

check('another host is refused', MegVentureAdsWidgetV2::isAllowedWidgetUrl('https://attacker.test/x') === false);
check('a lookalike subdomain is refused', MegVentureAdsWidgetV2::isAllowedWidgetUrl('https://megventure.com.attacker.test/x') === false);
check('the shop loopback is refused', MegVentureAdsWidgetV2::isAllowedWidgetUrl('http://127.0.0.1/admin') === false);
check('cloud metadata is refused', MegVentureAdsWidgetV2::isAllowedWidgetUrl('http://169.254.169.254/latest/meta-data/') === false);
check('file: is refused', MegVentureAdsWidgetV2::isAllowedWidgetUrl('file:///etc/passwd') === false);
check('a schemeless address is refused', MegVentureAdsWidgetV2::isAllowedWidgetUrl('megventure.com') === false);
check('empty is refused', MegVentureAdsWidgetV2::isAllowedWidgetUrl('') === false);

/* ------------------------------------------------------------------------- */

echo PHP_EOL;
echo $failed === 0
    ? 'OK - ' . $passed . ' assertions passed' . PHP_EOL
    : $failed . ' of ' . ($passed + $failed) . ' assertions FAILED' . PHP_EOL;

exit($failed === 0 ? 0 : 1);
