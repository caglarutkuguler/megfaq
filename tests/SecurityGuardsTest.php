<?php
/**
 * Static regression checks for megfaq 1.2.2 security guards (wave 4a + MEG-38).
 * Run: docker run --rm -v "$PWD:/app" -w /app php:8.2-cli-alpine php tests/SecurityGuardsTest.php
 */
$root = dirname(__DIR__);
$fail = 0;

function ok($cond, $msg)
{
    global $fail;
    if ($cond) {
        echo "OK  $msg\n";
    } else {
        echo "FAIL $msg\n";
        ++$fail;
    }
}

$main = file_get_contents($root . '/megfaq.php');
$ask = file_get_contents($root . '/controllers/front/askquestion.php');
$ads = file_get_contents($root . '/classes/MegVentureAdsWidget.php');

ok(strpos($main, "version = '1.2.2'") !== false, 'module version 1.2.2');
ok(strpos($main, 'REQUEST_METHOD') !== false && strpos($main, "=== 'POST'") !== false,
    'configure checks POST');
ok(preg_match('/!\$isPost \|\| !\$submitted/', $main) === 1, 'writes gated by $isPost');
ok(strpos($main, 'NOTIFY_MAX_PER_HOUR') !== false, 'mail budget constant present');
ok(strpos($main, 'notificationIsWithinBudget') !== false, 'mail budget helper present');
ok(strpos($main, 'employeeWriteIsDenied') !== false, 'edit-rights gate present');
ok(strpos($main, 'public static function escape') !== false, 'MegFaq::escape present');
ok(strpos($ask, 'isPostRequest') !== false, 'ask FO POST gate');
ok(strpos($ask, 'hash_equals') !== false, 'ask token constant-time compare');
ok(strpos($ask, 'productIsInThisShop') !== false, 'shop product ownership check');
ok(strpos($ads, 'isAllowedWidgetUrl') !== false, 'ads URL host pin');
ok(strpos($ads, 'safeUrl') !== false, 'ads safeUrl scheme check');
ok(strpos($ads, 'CURLOPT_FOLLOWLOCATION => false') !== false
    || strpos($ads, 'CURLOPT_FOLLOWLOCATION') !== false, 'ads fetch no redirect follow');
ok(file_exists($root . '/upgrade/upgrade-1.2.2.php'), 'upgrade-1.2.2.php exists');
ok(file_exists($root . '/TEST_CHECKLIST.md'), 'TEST_CHECKLIST.md exists');

if ($fail) {
    echo "\n$fail check(s) failed\n";
    exit(1);
}
echo "\nAll security helper checks passed.\n";
