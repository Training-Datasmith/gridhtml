<?php

class GridHtmlHookTest extends GridHtmlTestCase
{
    private $grider = 'index.php?controller=AdminStats&ajax=1&render=gridhtml';

    public function testReturnsHtmlString()
    {
        $html = GridHtml::hookGridEngine(gridhtml_hook_params(), $this->grider);

        $this->assertTrue(is_string($html));
        $this->assertNotSame('', $html);
        $this->assertTrue(strpos($html, '<table') !== false);
    }

    public function testHeadersAppearInColumnOrder()
    {
        $html = GridHtml::hookGridEngine(gridhtml_hook_params(), $this->grider);
        $headers = gridhtml_extract_table_headers($html);

        $this->assertSame(array('Last Name', 'Money'), $headers);
    }

    public function testAlignIsEmittedOnlyForColumnsThatSetIt()
    {
        $html = GridHtml::hookGridEngine(gridhtml_hook_params(), $this->grider);

        $this->assertTrue(gridhtml_column_has_align($html, 'lastname', 'center'));
        $this->assertTrue(gridhtml_column_has_no_align($html, 'totalMoneySpent'));
    }

    public function testColspanEqualsColumnCount()
    {
        $html = GridHtml::hookGridEngine(gridhtml_hook_params(), $this->grider);

        $this->assertSame(2, gridhtml_extract_footer_colspan($html));
    }

    public function testColspanMatchesSingleColumnGrid()
    {
        $params = gridhtml_hook_params(
            array(
                'columns' => array(
                    array('header' => 'Only', 'dataIndex' => 'only'),
                ),
            )
        );

        $html = GridHtml::hookGridEngine($params, $this->grider);

        $this->assertSame(1, gridhtml_extract_footer_colspan($html));
        $this->assertTrue(preg_match('/colspan="1"/', $html) === 1);
    }

    public function testGivenEmptyMessageIsEmbedded()
    {
        $html = GridHtml::hookGridEngine(
            gridhtml_hook_params(array('emptyMsg' => 'Nothing here')),
            $this->grider
        );

        $this->assertTrue(strpos($html, 'Nothing here') !== false);
    }

    public function testMissingEmptyMessageDefaultsToEmpty()
    {
        $params = gridhtml_hook_params();
        unset($params['emptyMsg']);
        unset($params['emptyMessage']);

        $html = GridHtml::hookGridEngine($params, $this->grider);

        $this->assertTrue(strpos($html, 'Empty') !== false);
    }

    public function testEmptyMessageAliasIsUsedWhenEmptyMsgAbsent()
    {
        $params = gridhtml_hook_params();
        unset($params['emptyMsg']);
        $params['emptyMessage'] = 'Empty recordset returned';

        $html = GridHtml::hookGridEngine($params, $this->grider);

        $this->assertTrue(strpos($html, 'Empty recordset returned') !== false);
        $this->assertFalse(strpos($html, '>Empty</td>') !== false);
    }

    public function testEmptyMessageAliasEscapesQuotesForJavascript()
    {
        $params = gridhtml_hook_params();
        unset($params['emptyMsg']);
        $params['emptyMessage'] = 'No "rows"';

        $html = GridHtml::hookGridEngine($params, $this->grider);

        $this->assertTrue(strpos($html, 'No \"rows\"') !== false);
        $this->assertFalse(strpos($html, 'No "rows"') !== false);
    }

    public function testEmptyMsgPrecedenceOverEmptyMessage()
    {
        $html = GridHtml::hookGridEngine(
            gridhtml_hook_params(
                array(
                    'emptyMsg' => 'Custom empty',
                    'emptyMessage' => 'Ignored alias',
                )
            ),
            $this->grider
        );

        $this->assertTrue(strpos($html, 'Custom empty') !== false);
        $this->assertFalse(strpos($html, 'Ignored alias') !== false);
    }

    public function testPagingMessageIsEscapedAndKeepsPlaceholders()
    {
        $html = GridHtml::hookGridEngine(
            gridhtml_hook_params(array('pagingMessage' => 'Say "hi" {0}/{1}/{2}')),
            $this->grider
        );

        $this->assertTrue(strpos($html, 'Say \"hi\" {0}/{1}/{2}') !== false);
        $this->assertFalse(strpos($html, 'Say "hi" {0}/{1}/{2}') !== false);
    }

    public function testReadyUrlKeepsGriderAndParsedQuery()
    {
        $html = GridHtml::hookGridEngine(
            gridhtml_hook_params(
                array(
                    'defaultSortColumn' => 'total Money',
                    'defaultSortDirection' => 'DESC',
                    'customParams' => array(
                        'option' => 'a b',
                        'q' => 'a&b=c',
                    ),
                )
            ),
            $this->grider
        );

        $readyUrl = gridhtml_extract_ready_url($html);
        $this->assertTrue(is_string($readyUrl));
        $this->assertNotSame('', $readyUrl);

        $query = array();
        parse_str(str_replace('index.php?', '', $readyUrl), $query);

        $this->assertSame('AdminStats', $query['controller']);
        $this->assertSame('gridhtml', $query['render']);
        $this->assertSame('total Money', $query['sort']);
        $this->assertSame('DESC', $query['dir']);
        $this->assertSame('a b', $query['option']);
        $this->assertSame('a&b=c', $query['q']);
    }

    public function testOmittedSortBecomesEmptyQueryValues()
    {
        $params = gridhtml_hook_params();
        unset($params['defaultSortColumn']);
        unset($params['defaultSortDirection']);

        $html = GridHtml::hookGridEngine($params, $this->grider);
        $readyUrl = gridhtml_extract_ready_url($html);
        $this->assertTrue(is_string($readyUrl));
        $this->assertNotSame('', $readyUrl);

        $query = array();
        parse_str(str_replace('index.php?', '', $readyUrl), $query);

        $this->assertArrayHasKey('sort', $query);
        $this->assertArrayHasKey('dir', $query);
        $this->assertSame('', $query['sort']);
        $this->assertSame('', $query['dir']);
    }
}
