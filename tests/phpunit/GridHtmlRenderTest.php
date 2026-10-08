<?php

class GridHtmlRenderTest extends GridHtmlTestCase
{
    private function renderGrid(array $values, $total, $start, $limit)
    {
        $grid = new GridHtml('grid');
        $grid->setTitle('Stats title');
        $grid->setSize(600, 920);
        $grid->setValues($values);
        $grid->setTotalCount($total);
        $grid->setLimit($start, $limit);

        ob_start();
        $grid->render();
        $output = ob_get_clean();

        $this->assertTrue(is_string($output));
        $this->assertNotSame('', $output);

        $decoded = json_decode($output, true);
        $this->assertSame(JSON_ERROR_NONE, json_last_error());
        $this->assertTrue(is_array($decoded));

        return $decoded;
    }

    public function testFirstPageClampsToTotal()
    {
        $decoded = $this->renderGrid(array(), 10, 0, 40);

        $this->assertSame(10, $decoded['total']);
        $this->assertSame(1, $decoded['from']);
        $this->assertSame(10, $decoded['to']);
        $this->assertSame(array(), $decoded['values']);
    }

    public function testMiddlePageIsOneBased()
    {
        $decoded = $this->renderGrid(array(array('a' => 1)), 100, 40, 40);

        $this->assertSame(41, $decoded['from']);
        $this->assertSame(80, $decoded['to']);
        $this->assertSame(100, $decoded['total']);
        $this->assertSame(array(array('a' => 1)), $decoded['values']);
    }

    public function testLastPartialPageClampsTo()
    {
        $decoded = $this->renderGrid(array(), 90, 80, 40);

        $this->assertSame(81, $decoded['from']);
        $this->assertSame(90, $decoded['to']);
    }

    public function testEmptyTotalIsZeroWindow()
    {
        $decoded = $this->renderGrid(array(), 0, 0, 40);

        $this->assertSame(0, $decoded['total']);
        $this->assertSame(0, $decoded['from']);
        $this->assertSame(0, $decoded['to']);
        $this->assertSame(array(), $decoded['values']);
    }

    public function testSetLimitCastsNumericStrings()
    {
        $grid = new GridHtml('grid');
        $grid->setLimit('80', '40');
        $grid->setTotalCount(90);
        $grid->setValues(array());

        $this->assertSame(80, $grid->_start);
        $this->assertSame(40, $grid->_limit);

        ob_start();
        $grid->render();
        $decoded = json_decode(ob_get_clean(), true);

        $this->assertSame(81, $decoded['from']);
        $this->assertSame(90, $decoded['to']);
    }

    public function testSetValuesReplacesPreviousRows()
    {
        $grid = new GridHtml('grid');
        $grid->setTotalCount(1);
        $grid->setLimit(0, 40);
        $grid->setValues(array(array('name' => 'first')));
        $grid->setValues(array(array('name' => 'second')));

        ob_start();
        $grid->render();
        $decoded = json_decode(ob_get_clean(), true);

        $this->assertSame(array(array('name' => 'second')), $decoded['values']);
    }

    public function testValuesRoundTripInOrder()
    {
        $values = array(
            array('name' => 'café'),
            array('name' => 'Ada'),
        );
        $decoded = $this->renderGrid($values, 2, 0, 40);

        $this->assertSame($values, $decoded['values']);
    }

    public function testEnginesDoNotShareRowState()
    {
        $first = new GridHtml('first');
        $first->setValues(array());
        $first->setTotalCount(3);
        $first->setLimit(0, 40);

        $second = new GridHtml('second');
        $second->setValues(array());
        $second->setTotalCount(7);
        $second->setLimit(0, 40);

        ob_start();
        $first->render();
        $firstDecoded = json_decode(ob_get_clean(), true);

        ob_start();
        $second->render();
        $secondDecoded = json_decode(ob_get_clean(), true);

        $this->assertSame(3, $firstDecoded['total']);
        $this->assertSame(7, $secondDecoded['total']);
    }
}
