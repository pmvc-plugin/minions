<?php

namespace PMVC\PlugIn\minions;

use PMVC;
use PMVC\TestCase;

class MinionsTest extends TestCase
{
    private $_plug = 'minions';
    function testPlugin()
    {
        ob_start();
        print_r(PMVC\plug($this->_plug));
        $output = ob_get_contents();
        ob_end_clean();
        $this->haveString($this->_plug,$output);
    }

}

