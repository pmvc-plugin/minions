<?php

namespace PMVC\PlugIn\minions;

use LengthException;
use PMVC\ListIterator;

${_INIT_CONFIG}[_CLASS] = __NAMESPACE__.'\ask';

class ask
{
    private $_curl;
    private $_host;
    public function __construct($caller)
    {
        $hosts = $caller[minions::hosts];
        if (empty($hosts) || !is_array($hosts)) {
            throw new LengthException('Minons host is empty.');
        }
        $this->_host = (new ListIterator($hosts))
            ->getIterator();
        $this->_curl = \PMVC\plug(minions::curl);
    }
    
    public function __invoke()
    {
        return $this;
    }

    public function process($curlRequest, $more)
    {
        if (!$this->_host->valid()) {
            $this->_host->rewind();
        }
        $host = $this->_host->current();
        $this->ask($host, $curlRequest, $more);
        $this->_curl->process();
        $this->_host->next();
        sleep(1);
    }

    private function ask($minionsServer, $curl, $more=null)
    {
        $callback = $curl[minions::callback];
        $options = $curl[minions::options];
        if (is_string($minionsServer)) {
            $host = $minionsServer;
        } else {
            if (!isset($minionsServer[0])) {
                return !trigger_error("Minions server config not correct. ".var_export($minionsServer,true), E_USER_WARNING);
            }
            $host = $minionsServer[0];
            if (isset($minionsServer[1])) {
                $options += $minionsServer[1];
            }
        }
        $this->_curl->post($host, function($r) use($callback, $minionsServer) {
            $json =json_decode($r->body);
            if (!isset($json->r)) {
                return !trigger_error("Minions respond failed. ".var_export($json,true));
            }
            $serverTime = $json->serverTime;
            $r =& $json->r;
            unset($json);
            $r->body = gzuncompress(urldecode($r->body));
            if (is_callable($callback)) {
                call_user_func (
                    $callback, 
                    $r, 
                    [ 
                        $minionsServer, 
                        $serverTime
                    ]
                );
            }
        }, [ 
            'curl'=>$options,
            'more'=>$more
        ]);
    }

    public function setCurl($curl)
    {
        $this->_curl = $curl;
    }
}
