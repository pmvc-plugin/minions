<?php

namespace PMVC\PlugIn\minions;

use PMVC\PlugIn\curl\curl;

${_INIT_CONFIG}[_CLASS] = __NAMESPACE__.'\minions';

\PMVC\initPlugin(
    [
    'curl'=>null
    ]
);

class minions extends curl
{
    const options = 'options';
    const callback = 'callback';
    const hash = 'hash';
    const hosts = 'hosts';
    const curl = 'curl';

    public function init()
    {
        parent::init();
        $this->useCache(false);
        $this['ttl'] = 86400;
    }

    public function useCache($bool)
    {
        if ($bool) {
            $this['process'] = [
            $this,
            'processCache'
            ];
        } else {
            $this['process'] = [
            $this,
            'processClient'
            ];
        }
    }

    public function processClient($more=null)
    {
        if (isset($this['cookieHandler'])) {
            $this->ask()->handleCookie();
            unset($this['cookieHandler']);
        }
        \PMVC\dev(
            function () use (&$more) {
                $more[]= 'request_header';
            }, 'req'
        );
        $this->_handleQueue([$this->ask(), 'process'], [$more]);
    }

    public function processCache($more=null, $ttl=null)
    {
        \PMVC\dev(
            function () use (&$more) {
                $more[]= 'request_header';
            }, 'req'
        );
        if (is_null($ttl)) {
            $ttl = $this['ttl'];
        }
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
            $options = $curl->set();
            if (!empty($options)) {
                $queue[] = [ 
                    self::hash    =>$curl->getHash(),
                    self::options =>$options,
                    self::callback=>$curl->getCallback()
                ];
                $curl->clean();
            }
        }
        $this->clean();
        while(count($queue))
        {
            $first = array_shift($queue);
            call_user_func_array($callback, array_merge([$first], $params));
            if (empty($queue)) {
                break;
            }
        }
    }
}
