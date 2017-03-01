<?php
namespace PMVC\PlugIn\minions;

use PMVC\PlugIn\curl\curl;

${_INIT_CONFIG}[_CLASS] = __NAMESPACE__.'\minions';

\PMVC\initPlugin(array(
    'curl'=>null
));

class minions extends curl
{
    private $_queue=[];
    const options = 'options';
    const callback = 'callback';
    const hosts = 'hosts';
    const curl = 'curl';
    const delay = 'delay';

    function process($more=null)
    {
        if (!empty($this['cookieHandler'])) {
            $this->ask()->handleCookie();
            unset($this['cookieHandler']);
        }
        $this->handleQueue([$this->ask(), 'process'], [$more]);
    }

    function processCache($more=null, $ttl=86400)
    {
        $this->handleQueue([$this->cache(), 'process'], [$more, $ttl]);
        $this['cache']->finish();
    }

    function handleQueue($callback, $params)
    {
        $curls = $this->getCurls();
        if (empty($curls) || !count($curls)) {
            return;
        }
        foreach ($curls as $curl) {
            $this->_queue[] = [ 
                self::options=>$curl->set(),
                self::callback=>$curl->getCallback()
            ];
            $curl->clean();
        }
        $this->clean();
        while(count($this->_queue))
        {
            $pop = array_pop($this->_queue);
            call_user_func_array($callback, array_merge([$pop], $params));
            if (empty($this->_queue)) {
                break;
            }
        }
    }
}
