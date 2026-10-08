<?php

date_default_timezone_set('UTC');

if (!defined('GRIDHTML_REPO_ROOT')) {
    define('GRIDHTML_REPO_ROOT', dirname(__DIR__));
}

if (!defined('_PS_VERSION_')) {
    define('_PS_VERSION_', '1.7.8.7');
}

class Module
{
    public $name;
    public $tab;
    public $version;
    public $author;
    public $need_instance;
    public $displayName;
    public $description;
    public $ps_versions_compliancy;

    public static $constructed = 0;
    public static $installResult = true;
    public static $hookResult = true;
    public static $hooks = array();
    public static $transCalls = array();

    public function __construct()
    {
        self::$constructed++;
    }

    public function trans($id, array $parameters = array(), $domain = null, $locale = null)
    {
        self::$transCalls[] = array(
            'id' => $id,
            'parameters' => $parameters,
            'domain' => $domain,
            'locale' => $locale,
        );

        return 'T:' . $id;
    }

    public function install()
    {
        return self::$installResult;
    }

    public function registerHook($hook_name)
    {
        self::$hooks[] = $hook_name;

        return self::$hookResult;
    }

    public static function resetDoubleState()
    {
        self::$constructed = 0;
        self::$installResult = true;
        self::$hookResult = true;
        self::$hooks = array();
        self::$transCalls = array();
    }
}

class ModuleGridEngine extends Module
{
    public static $constructorArgs = array();

    public function __construct($type)
    {
        self::$constructorArgs[] = $type;
    }

    public static function resetDoubleState()
    {
        self::$constructorArgs = array();
    }
}

require GRIDHTML_REPO_ROOT . '/gridhtml.php';

if (class_exists('PHPUnit\\Framework\\TestCase')) {
    if (PHP_VERSION_ID >= 70000) {
        eval('
            class GridHtmlTestCase extends PHPUnit\\Framework\\TestCase
            {
                protected function setUp(): void
                {
                    $this->gridhtmlSetUp();
                }

                protected function tearDown(): void
                {
                    $this->gridhtmlTearDown();
                }

                protected function gridhtmlSetUp()
                {
                }

                protected function gridhtmlTearDown()
                {
                    gridhtml_reset_grid_columns();
                }
            }
        ');
    } else {
        class GridHtmlTestCase extends PHPUnit\Framework\TestCase
        {
            protected function setUp()
            {
                $this->gridhtmlSetUp();
            }

            protected function tearDown()
            {
                $this->gridhtmlTearDown();
            }

            protected function gridhtmlSetUp()
            {
            }

            protected function gridhtmlTearDown()
            {
                gridhtml_reset_grid_columns();
            }
        }
    }
} else {
    class GridHtmlTestCase extends PHPUnit_Framework_TestCase
    {
        protected function setUp()
        {
            $this->gridhtmlSetUp();
        }

        protected function tearDown()
        {
            $this->gridhtmlTearDown();
        }

        protected function gridhtmlSetUp()
        {
        }

        protected function gridhtmlTearDown()
        {
            gridhtml_reset_grid_columns();
        }
    }
}

function gridhtml_reset_grid_columns()
{
    $property = new ReflectionProperty('GridHtml', '_columns');
    $property->setAccessible(true);
    $property->setValue(null, null);
}

function gridhtml_shell_quote($value)
{
    if (DIRECTORY_SEPARATOR === '\\') {
        return '"' . str_replace('"', '\"', $value) . '"';
    }

    return escapeshellarg($value);
}

function gridhtml_run_php_subprocess($phpCode, $timeoutSeconds = 10)
{
    $phpBinary = 'php';
    if (defined('PHP_BINARY')) {
        $phpBinary = PHP_BINARY;
    }
    $descriptorSpec = array(
        0 => array('pipe', 'r'),
        1 => array('pipe', 'w'),
        2 => array('pipe', 'w'),
    );

    if (PHP_VERSION_ID >= 70400) {
        $command = array(
            $phpBinary,
            '-d',
            'display_errors=1',
            '-d',
            'error_reporting=-1',
            '-r',
            $phpCode,
        );
        $process = proc_open($command, $descriptorSpec, $pipes);
    } else {
        $command = escapeshellarg($phpBinary)
            . ' -d display_errors=1 -d error_reporting=-1 -r '
            . escapeshellarg($phpCode);
        $process = proc_open($command, $descriptorSpec, $pipes);
    }

    if (!is_resource($process)) {
        throw new RuntimeException('Failed to start PHP subprocess');
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $stdout = '';
    $stderr = '';
    $exitCode = null;
    $deadline = microtime(true) + $timeoutSeconds;

    while (true) {
        $read = array($pipes[1], $pipes[2]);
        $write = null;
        $except = null;

        if (stream_select($read, $write, $except, 1) > 0) {
            foreach ($read as $stream) {
                $chunk = stream_get_contents($stream);
                if ($stream === $pipes[1]) {
                    $stdout .= $chunk;
                } else {
                    $stderr .= $chunk;
                }
            }
        }

        $status = proc_get_status($process);
        if (!$status['running']) {
            $stdout .= stream_get_contents($pipes[1]);
            $stderr .= stream_get_contents($pipes[2]);
            if ($status['exitcode'] !== -1) {
                $exitCode = $status['exitcode'];
            }
            break;
        }

        if (microtime(true) >= $deadline) {
            proc_terminate($process);
            proc_close($process);
            throw new RuntimeException('PHP subprocess timed out');
        }
    }

    fclose($pipes[1]);
    fclose($pipes[2]);

    $closeCode = proc_close($process);
    if (!isset($exitCode)) {
        $exitCode = $closeCode;
    }

    return array(
        'exitCode' => $exitCode,
        'stdout' => $stdout,
        'stderr' => $stderr,
    );
}

function gridhtml_extract_table_headers($html)
{
    if (!preg_match('/<thead>.*?<tr>(.*?)<\/tr>.*?<\/thead>/s', $html, $matches)) {
        return array();
    }

    preg_match_all('/<span[^>]*>(.*?)<\/span>/s', $matches[1], $headerMatches);

    return $headerMatches[1];
}

function gridhtml_extract_footer_colspan($html)
{
    if (!preg_match('/<tfoot>.*?<th[^>]*colspan="(\d+)"/s', $html, $matches)) {
        return null;
    }

    return (int) $matches[1];
}

function gridhtml_extract_ready_url($html)
{
    if (preg_match('/\$\(\s*document\s*\)\.ready\s*\(\s*function\s*\(\s*\)\s*\{\s*getGridData\s*\(\s*"([^"]+)"\s*\)/s', $html, $matches) !== 1) {
        return null;
    }

    return $matches[1];
}

function gridhtml_column_has_align($html, $dataIndex, $align)
{
    $pattern = "/newLine\s*\+=\s*\"<td\s+align=\\\\\"" . preg_quote($align, '/') . "\\\\\"\s*>\"\s*\+\s*row\\[\"" . preg_quote($dataIndex, '/') . "\"\\]/";

    return preg_match($pattern, $html) === 1;
}

function gridhtml_column_has_no_align($html, $dataIndex)
{
    $pattern = '/newLine\s*\+=\s*"<td\s*>"\s*\+\s*row\["' . preg_quote($dataIndex, '/') . '"\]/';

    return preg_match($pattern, $html) === 1;
}

function gridhtml_stop_directory_server()
{
    if (class_exists('DirectoryIndexTest')) {
        DirectoryIndexTest::stopServerPublic();
    }
}

function gridhtml_hook_params(array $overrides = array())
{
    $params = array(
        'columns' => array(
            array('header' => 'Last Name', 'dataIndex' => 'lastname', 'align' => 'center'),
            array('header' => 'Money', 'dataIndex' => 'totalMoneySpent'),
        ),
        'pagingMessage' => 'Displaying {0} to {1} of {2}',
        'defaultSortColumn' => 'totalMoneySpent',
        'defaultSortDirection' => 'DESC',
    );

    foreach ($overrides as $key => $value) {
        $params[$key] = $value;
    }

    return $params;
}
