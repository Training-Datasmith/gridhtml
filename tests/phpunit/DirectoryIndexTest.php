<?php

class DirectoryIndexTest extends GridHtmlTestCase
{
    private static $serverProcess = null;
    private static $serverPort = null;
    private static $shutdownRegistered = false;

    private static function stopServer()
    {
        self::stopServerPublic();
    }

    public static function stopServerPublic()
    {
        if (is_resource(self::$serverProcess)) {
            proc_terminate(self::$serverProcess);
            proc_close(self::$serverProcess);
        }

        self::$serverProcess = null;
        self::$serverPort = null;
    }

    private function startServer()
    {
        if (is_resource(self::$serverProcess)) {
            return self::$serverPort;
        }

        $phpBinary = defined('PHP_BINARY') ? PHP_BINARY : 'php';
        $deadline = microtime(true) + 10;
        $lastError = 'unknown';

        while (microtime(true) < $deadline) {
            $socket = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
            if ($socket === false) {
                $lastError = $errstr;
                usleep(100000);
                continue;
            }

            $address = stream_socket_get_name($socket, false);
            fclose($socket);
            $parts = explode(':', $address);
            $port = (int) array_pop($parts);

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
                    '-S',
                    '127.0.0.1:' . $port,
                    '-t',
                    GRIDHTML_REPO_ROOT,
                );
                $process = proc_open($command, $descriptorSpec, $pipes);
            } else {
                $command = escapeshellarg($phpBinary)
                    . ' -d display_errors=1 -d error_reporting=-1 -S 127.0.0.1:'
                    . $port
                    . ' -t '
                    . escapeshellarg(GRIDHTML_REPO_ROOT);
                $process = proc_open($command, $descriptorSpec, $pipes);
            }
            if (!is_resource($process)) {
                $lastError = 'proc_open failed';
                continue;
            }

            fclose($pipes[0]);
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);

            $ready = false;
            $bootDeadline = microtime(true) + 5;
            while (microtime(true) < $bootDeadline) {
                $read = array($pipes[1], $pipes[2]);
                $write = null;
                $except = null;

                if (stream_select($read, $write, $except, 0, 200000) > 0) {
                    foreach ($read as $stream) {
                        $chunk = stream_get_contents($stream);
                        if ($chunk !== '' && strpos($chunk, 'Address already in use') !== false) {
                            proc_terminate($process);
                            proc_close($process);
                            $lastError = 'address already in use';
                            break 2;
                        }
                    }
                }

                $status = proc_get_status($process);
                if (!$status['running']) {
                    $lastError = trim(stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]));
                    proc_close($process);
                    break;
                }

                $probe = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
                if ($probe !== false) {
                    fclose($probe);
                    $ready = true;
                    break;
                }
            }

            if ($ready) {
                self::$serverProcess = $process;
                self::$serverPort = $port;
                if (!self::$shutdownRegistered) {
                    self::$shutdownRegistered = true;
                    register_shutdown_function('gridhtml_stop_directory_server');
                }
                return $port;
            }

            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
        }

        throw new RuntimeException('Failed to start built-in server: ' . $lastError);
    }

    private function fetchHeaders($path)
    {
        $port = $this->startServer();
        $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 2);
        if ($socket === false) {
            throw new RuntimeException('Failed to connect to built-in server: ' . $errstr);
        }

        $request = "GET {$path} HTTP/1.0\r\nHost: 127.0.0.1\r\nConnection: close\r\n\r\n";
        fwrite($socket, $request);

        $response = '';
        while (!feof($socket)) {
            $response .= fgets($socket, 8192);
        }
        fclose($socket);

        $parts = explode("\r\n\r\n", $response, 2);
        $headerBlock = $parts[0];
        $body = isset($parts[1]) ? $parts[1] : '';

        $lines = explode("\r\n", $headerBlock);
        $statusLine = array_shift($lines);
        $headers = array();
        foreach ($lines as $line) {
            if (strpos($line, ':') === false) {
                continue;
            }
            list($name, $value) = explode(':', $line, 2);
            $key = strtolower(trim($name));
            $value = trim($value);
            if (isset($headers[$key])) {
                $headers[$key] .= ', ' . $value;
            } else {
                $headers[$key] = $value;
            }
        }

        return array(
            'statusLine' => $statusLine,
            'headers' => $headers,
            'body' => $body,
        );
    }

    public function testDirectoryGuardRedirectsForRootIndex()
    {
        $this->assertDirectoryGuard('/index.php');
    }

    public function testDirectoryGuardRedirectsForTranslationsIndex()
    {
        $this->assertDirectoryGuard('/translations/index.php');
    }

    private function assertDirectoryGuard($path)
    {
        self::stopServer();
        $response = $this->fetchHeaders($path);

        $this->assertTrue(strpos($response['statusLine'], '302') !== false);
        $this->assertArrayHasKey('location', $response['headers']);
        $this->assertSame('../', $response['headers']['location']);
        $this->assertArrayHasKey('expires', $response['headers']);
        $expires = strtotime($response['headers']['expires']);
        $this->assertTrue($expires !== false);
        $this->assertTrue($expires < time());
        $this->assertArrayHasKey('cache-control', $response['headers']);
        $this->assertTrue(strpos($response['headers']['cache-control'], 'no-store') !== false);
        $this->assertTrue(strpos($response['headers']['cache-control'], 'must-revalidate') !== false);
        $this->assertArrayHasKey('pragma', $response['headers']);
        $this->assertSame('no-cache', $response['headers']['pragma']);
        $this->assertSame('', $response['body']);
    }
}
