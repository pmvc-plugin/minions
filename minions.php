<?php

namespace PMVC\PlugIn\minions;

use PMVC\PlugIn\curl\curl;

${_INIT_CONFIG}[_CLASS] = __NAMESPACE__.'\minions';

\PMVC\initPlugin(array(
    'curl'=>null
));

class minions extends curl
{
    const options = 'options';
    const callback = 'callback';
    const hosts = 'hosts';
    const curl = 'curl';
    const delay = 'delay';

    public function init()
    {
        parent::init();
        $this['process'] = [
            $this,
            \PMVC\get(
                $this,
                'processFunction',
                'processClient'
            )
        ];
    }

    public function processClient($more=null)
    {
        if (isset($this['cookieHandler'])) {
            $this->ask()->handleCookie();
            unset($this['cookieHandler']);
        }
        $this->_handleQueue([$this->ask(), 'process'], [$more]);
    }

    public function processCache($more=null, $ttl=86400)
    {
        $this->_handleQueue([$this->cache(), 'process'], [$more, $ttl]);
        $this['cache']->finish();
    }

    private function _handleQueue($callback, $params)
    {
        $curls = $this->getCurls();
        if (empty($curls) || !count($curls)) {
            return;
        }
        $queue = [];
        foreach ($curls as $curl) {
            $queue[] = [ 
                self::options=>$curl->set(),
                self::callback=>$curl->getCallback()
            ];
            $curl->clean();
        }
        $this->clean();
        while(count($queue))
        {
            $pop = array_shift($queue);
            call_user_func_array($callback, array_merge([$pop], $params));
            if (empty($queue)) {
                break;
            }
        }
    }
}
