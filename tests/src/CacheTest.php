<?php

namespace PMVC\PlugIn\minions;

use PMVC;
use PMVC\HashMap;
use PMVC\PlugIn;
use PMVC\TestCase;

class CacheTest extends TestCase
{
    private $_plug = 'minions';
    function testCacheProcess()
    {
        $minions = \PMVC\plug($this->_plug);
        $minions['hosts'] = [
            'fake1',
        ];
        $minions->get('http://yahoo.com');
        $guid = \PMVC\plug('guid',[
            _CLASS=>__NAMESPACE__.'\FakeGuid'
        ]);
        $minions->processCache(null, 1000);
        $this->assertTrue(isset($guid['model']));
    }
}

class FakeGuid extends PlugIn {
    function getModel()
    {
        $this['model'] = new FakeMinionsDb();
        return $this['model'];
    }
}

class FakeMinionsDb extends HashMap {
    function setTTL()
    {

    }
}
