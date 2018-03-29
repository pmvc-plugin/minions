<?php

namespace PMVC\PlugIn\minions;

use PHPUnit_Framework_TestCase;
use PMVC;
use ReflectionClass;

class AskTest extends PHPUnit_Framework_TestCase
{
    private $_plug = 'minions';

    function setup()
    {
        \PMVC\unplug($this->_plug);
    }

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
        $fakeCurl->assertCallback = function(
            $i,
            $host,
            $options
        ) use ($minions) {
            $index = $i % count($minions['hosts']);
            $this->assertEquals(
                $minions['hosts'][$index],
                $host
            ); 
        };
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
        $ask = $minions->ask();
        $class = new ReflectionClass('\PMVC\PlugIn\minions\ask');
        $methodName = '_storeCookies';
        $method = $class->getMethod($methodName);
        $method->setAccessible(true);
        $method->invokeArgs($ask, [
            $fakeSetCookie,
            $host
        ]);
        $this->assertEquals('id=a3fWa', $minions->ask()->cookies[$host]['id']);
    }

    function testMergeCookie()
    {
        $minions = \PMVC\plug($this->_plug);
        $host = 'fake1';
        $minions['hosts'] = [
            $host,
        ];
        $fakeCurl = new fakeCurlWithAsk();
        $fakeCurl->assertCallback = function(
            $i,
            $host,
            $options
        ){
            $expected = 'foo=ccc; bar=bar; abc=def; ';
            $this->assertEquals(
                $expected,
                $options['curl'][CURLOPT_COOKIE]
            );
        };
        $ask = $minions->ask();
        $ask->cookies = [
            'fake1'=>[
                'foo'=>'foo=bar',
                'bar'=>'bar=bar'
            ]
        ];
        $ask->setCurl($fakeCurl);
        $minions->get('http://yahoo.com')->set([
           CURLOPT_COOKIE=>'abc=def; foo=ccc;' 
        ]);
        $minions->process();
    }
}

class fakeCurlWithAsk {
    private $_assert;
    private $_hosts;
    private $_i=0;
    public $assertCallback;
    function post($host, $callback, $options) {
        $r = (object)[
            'body'=>json_encode([
                'r'=>[
                    'body'=>urlencode(gzcompress('', 9))
                ],
                'serverTime'=>111
            ])
        ];
        $callback($r, $this);
        call_user_func_array(
            $this->assertCallback,
            [
                $this->_i,
                $host,
                $options
            ]
        );
        $this->_i++;
    }

    function process() {
    }

}
