<?php

class ComposerJsonTest extends GridHtmlTestCase
{
    public function testComposerRequiresPhp54OrHigher()
    {
        $composer = json_decode(file_get_contents(GRIDHTML_REPO_ROOT . '/composer.json'), true);

        $this->assertArrayHasKey('require', $composer);
        $this->assertArrayHasKey('php', $composer['require']);
        $this->assertSame('>=5.4', $composer['require']['php']);
    }
}
