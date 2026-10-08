<?php

/**
 * Run the configured PHPUnit 4.8 suite in a single process with tests shuffled via mt_srand().
 *
 * Usage: PHPUNIT48_PHAR=/path/to/phpunit-4.8.36.phar php run-phpunit48-seeded-order.php [seed]
 */

if (!getenv('PHPUNIT48_PHAR')) {
    fwrite(STDERR, "PHPUNIT48_PHAR environment variable is required\n");
    exit(2);
}

$seed = 20261008;
if (isset($argv[1]) && $argv[1] !== '') {
    $seed = (int) $argv[1];
}

$repoRoot = dirname(dirname(__DIR__));
$configFile = $repoRoot . '/phpunit.xml.dist';

require getenv('PHPUNIT48_PHAR');

$config = PHPUnit_Util_Configuration::getInstance($configFile);
$phpunit = $config->getPHPUnitConfiguration();
require $phpunit['bootstrap'];

/**
 * @param PHPUnit_Framework_TestSuite $suite
 * @return PHPUnit_Framework_TestCase[]
 */
function gridhtml_testsuite_leaf_tests(PHPUnit_Framework_TestSuite $suite)
{
    $tests = array();

    foreach ($suite as $test) {
        if ($test instanceof PHPUnit_Framework_TestSuite) {
            $tests = array_merge($tests, gridhtml_testsuite_leaf_tests($test));
        } elseif ($test instanceof PHPUnit_Framework_TestCase) {
            $tests[] = $test;
        }
    }

    return $tests;
}

$defaultSuite = $config->getTestSuiteConfiguration(null);
if (!$defaultSuite instanceof PHPUnit_Framework_TestSuite) {
    fwrite(STDERR, "Failed to load test suite from configuration\n");
    exit(2);
}

$expectedCount = $defaultSuite->count();
$leafTests = gridhtml_testsuite_leaf_tests($defaultSuite);

if (count($leafTests) !== $expectedCount) {
    fwrite(STDERR, sprintf(
        "Leaf test count %d does not match suite count %d\n",
        count($leafTests),
        $expectedCount
    ));
    exit(2);
}

usort(
    $leafTests,
    function ($left, $right) {
        return strcmp($left->getName(), $right->getName());
    }
);

mt_srand($seed);
$testCount = count($leafTests);
for ($i = $testCount - 1; $i > 0; --$i) {
    $j = mt_rand(0, $i);
    if ($j !== $i) {
        $swap = $leafTests[$i];
        $leafTests[$i] = $leafTests[$j];
        $leafTests[$j] = $swap;
    }
}

$shuffledSuite = new PHPUnit_Framework_TestSuite('Seeded floor order');
foreach ($leafTests as $test) {
    $shuffledSuite->addTest($test);
}

echo "GRIDHTML_SEEDED_ORDER seed={$seed}\n";
foreach ($leafTests as $test) {
    echo $test->getName(), "\n";
}
echo "GRIDHTML_SEEDED_ORDER_END\n";

$arguments = array(
    'configuration' => $configFile,
    'bootstrap' => $phpunit['bootstrap'],
);

$runner = new PHPUnit_TextUI_TestRunner();
$result = $runner->doRun($shuffledSuite, $arguments);

$ranCount = $result->count();
$exitCode = 0;

if (!$result->wasSuccessful()) {
    $exitCode = 1;
}

if ($ranCount < $expectedCount) {
    fwrite(STDERR, sprintf(
        "Ran %d tests, expected %d\n",
        $ranCount,
        $expectedCount
    ));
    $exitCode = $exitCode === 0 ? 2 : $exitCode;
}

exit($exitCode);
