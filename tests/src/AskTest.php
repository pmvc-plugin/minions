<?php

namespace PMVC\PlugIn\minions;

use PHPUnit_Framework_TestCase;
use PMVC;

class AskTest extends PHPUnit_Framework_TestCase
{
    private $_plug = 'minions';
    function testAskProcess()
    {
        $minions = \PMVC\plug($this->_plug);
        $minions['hosts'] = [
            'fake1',
            'fake2'
        ];
        $minions->get('http://yahoo.com');
        $minions->get('http://google.com');
        $minions->get('http://bing.com');
        $fakeCurl = new fakeCurlWithAsk();
        $fakeCurl->setPhpUnit($this, $minions['hosts']);
        $minions->ask()->setCurl($fakeCurl);
        $minions->process();
    }

    function testStoreCookies()
    {
        $minions = \PMVC\plug($this->_plug);
        $host = 'fake1';
        $minions['hosts'] = [
            $host,
        ];
        $fakeSetCookie = 'id=a3fWa; Expires=Wed, 21 Oct 2015 07:28:00 GMT; Secure; HttpOnly';
        $minions->ask()->storeCookies($fakeSetCookie, $host);
        $this->assertEquals($fakeSetCookie, $minions->ask()->cookies[$host]['id']);
    }
}

class fakeCurlWithAsk {
    private $_assert;
    private $_hosts;
    private $_i=0;
    function post($host, $callback) {
        $r = (object)[
            'body'=>json_encode([
                'r'=>[
                    'body'=>urlencode(gzcompress('', 9))
                ],
                'serverTime'=>111
            ])
        ];
        $callback($r, $this);
        $this->_assert->assertEquals($this->_hosts[$this->_i], $host);
        $this->_i++;
        if ($this->_i>=count($this->_hosts)) {
            $this->_i = 0;
        }
    }

    function process() {
    }

    function setPhpUnit($assert, $host)
    {
        $this->_assert = $assert;
        $this->_hosts = $host;
    }
}
