<?php

class GridHtmlGuardTest extends GridHtmlTestCase
{
    public function testFileExitsWhenPsVersionMissing()
    {
        $repoRoot = GRIDHTML_REPO_ROOT;
        $gridFile = $repoRoot . '/gridhtml.php';
        $phpCode = 'include ' . var_export($gridFile, true) . '; echo "STILL_RUNNING";';

        $result = gridhtml_run_php_subprocess($phpCode, 10);

        $this->assertSame(0, $result['exitCode']);
        $this->assertSame('', $result['stdout']);
        $this->assertSame('', $result['stderr']);
    }
}
