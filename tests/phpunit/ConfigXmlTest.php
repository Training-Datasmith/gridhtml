<?php

class ConfigXmlTest extends GridHtmlTestCase
{
    public function testXmlMetadataMatchesModuleConstructor()
    {
        $module = new GridHtml();
        $xml = simplexml_load_file(GRIDHTML_REPO_ROOT . '/config.xml');

        $this->assertSame('gridhtml', (string) $xml->name);
        $this->assertSame($module->version, (string) $xml->version);
        $this->assertSame($module->author, (string) $xml->author);
        $this->assertSame($module->tab, (string) $xml->tab);
        $this->assertTrue(isset($xml->need_instance));
        $this->assertSame((int) $module->need_instance, (int) $xml->need_instance);
    }
}
