<?php

namespace PMVC\PlugIn\minions;

use PHPUnit_Framework_TestCase;
use PMVC;
use PMVC\HashMap;
use PMVC\PlugIn;

class CacheTest extends PHPUnit_Framework_TestCase
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
        $this->assertTrue(isset($guid['db']['31702a7af977c80d4d2a1daed66385c8']));
    }
}

class FakeGuid extends PlugIn {
    function getDb()
    {
        $this['db'] = new FakeMinionsDb();
        return $this['db'];
    }
}

class FakeMinionsDb extends HashMap {
    function setCache()
    {

    }
}
