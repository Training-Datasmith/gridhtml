<?php

class GridHtmlModuleTest extends GridHtmlTestCase
{
    protected function gridhtmlSetUp()
    {
        Module::resetDoubleState();
        ModuleGridEngine::resetDoubleState();
    }

    public function testModuleConstructorSetsMetadata()
    {
        $module = new GridHtml();

        $this->assertSame('gridhtml', $module->name);
        $this->assertSame('administration', $module->tab);
        $this->assertSame('2.0.3', $module->version);
        $this->assertSame('PrestaShop', $module->author);
        $this->assertSame(0, $module->need_instance);
        $this->assertSame('T:Simple HTML table display', $module->displayName);
        $this->assertSame(
            'T:Just allow statistics to be displayed (and therefore analyzed) on your back office.',
            $module->description
        );
        $this->assertSame(
            array('min' => '1.7.1.0', 'max' => '1.7.8.7'),
            $module->ps_versions_compliancy
        );
        $this->assertSame(1, Module::$constructed);
        $this->assertCount(2, Module::$transCalls);
        $this->assertSame('Simple HTML table display', Module::$transCalls[0]['id']);
        $this->assertSame(array(), Module::$transCalls[0]['parameters']);
        $this->assertSame('Modules.Gridhtml.Admin', Module::$transCalls[0]['domain']);
        $this->assertSame(
            'Just allow statistics to be displayed (and therefore analyzed) on your back office.',
            Module::$transCalls[1]['id']
        );
        $this->assertSame(array(), Module::$transCalls[1]['parameters']);
        $this->assertSame('Modules.Gridhtml.Admin', Module::$transCalls[1]['domain']);
    }

    public function testNullTypeUsesModuleConstructor()
    {
        $module = new GridHtml(null);

        $this->assertSame('gridhtml', $module->name);
        $this->assertSame(1, Module::$constructed);
    }

    public function testEngineConstructorOnlySetsType()
    {
        new GridHtml('customers');

        $this->assertSame(array('customers'), ModuleGridEngine::$constructorArgs);
        $this->assertSame(0, Module::$constructed);
    }

    public function testInstallRegistersGridEngineHookWhenParentSucceeds()
    {
        Module::$installResult = true;
        Module::$hookResult = true;

        $module = new GridHtml();
        $this->assertTrue($module->install());
        $this->assertSame(array('GridEngine'), Module::$hooks);
    }

    public function testInstallDoesNotRegisterHookWhenParentFails()
    {
        Module::$installResult = false;
        Module::$hookResult = true;

        $module = new GridHtml();
        $this->assertFalse($module->install());
        $this->assertSame(array(), Module::$hooks);
    }

    public function testInstallReturnsFalseWhenHookRegistrationFails()
    {
        Module::$installResult = true;
        Module::$hookResult = false;

        $module = new GridHtml();
        $this->assertFalse($module->install());
        $this->assertSame(array('GridEngine'), Module::$hooks);
    }
}
