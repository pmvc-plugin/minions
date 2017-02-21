<?php

namespace PMVC\PlugIn\minions;

use LengthException;
use PMVC\ListIterator;

${_INIT_CONFIG}[_CLASS] = __NAMESPACE__.'\ask';

class ask
{
    private $_curl;
    private $_hosts;
    public $cookies = [];
    public function __construct($caller)
    {
        $hosts = $caller[minions::hosts];
        if (empty($hosts) || !is_array($hosts)) {
            throw new LengthException('Minons hosts is empty.');
        }
        $this->_hosts = (new ListIterator($hosts))
            ->getIterator();
        $this->_curl = \PMVC\plug(minions::curl);
    }

    public function handleCookie($handler)
    {
        foreach ($this->_hosts as $h) {
            $this->ask(
                $h, 
                [
                    minions::options => $handler->set(),
                    minions::callback=> $handler->getCallback()
                ]
            );
        }
        $this->_curl->process();
    }
    
    public function __invoke()
    {
        return $this;
    }

    public function process($curlRequest, $more)
    {
        if (!$this->_hosts->valid()) {
            $this->_hosts->rewind();
        }
        $host = $this->_hosts->current();
        $this->ask($host, $curlRequest, $more);
        $this->_curl->process();
        $this->_hosts->next();
        sleep(1);
    }

    private function ask($minionsServer, $curl, $more=null)
    {
        $callback = $curl[minions::callback];
        $options = $curl[minions::options];
        $options[CURLOPT_URL] = (string)$options[CURLOPT_URL];
        $cookies = \PMVC\get($this->cookies, $minionsServer);
        if (!$this->caller['ignoreSetCookie'] && $cookies) {
            $options[CURLOPT_COOKIE] = ((empty($options[CURLOPT_COOKIE])) ? '' :
                $options[CURLOPT_COOKIE].';') .
                join(';', $cookies);
        }

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
        $this->_curl->post($host, function($r, $curlHelper) use($callback, $minionsServer) {
            $json =json_decode($r->body);
            if (!isset($json->r)) {
                return !trigger_error("Minions respond failed. ".var_export($json,true));
            }
            $serverTime = $json->serverTime;
            $r =& $json->r;
            $setCookie = \PMVC\get($r->header,'set-cookie');
            if (!empty($setCookie)) {
                $this->storeCookies($setCookie, $minionsServer);
            }
            unset($json);
            if (is_callable($callback)) {
                $r->body = gzuncompress(urldecode($r->body));
                call_user_func (
                    $callback, 
                    $r,
                    $curlHelper,
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

    public function storeCookies($cookies, $minionsServer)
    {
        $cookies = \PMVC\toArray($cookies);
        if (empty($this->cookies[$minionsServer])) {
            $this->cookies[$minionsServer] = [];
        }
        foreach ($cookies as $c) {
            $name = $this->_getCookieName($c);
            if ($name) {
                $this->cookies[$minionsServer][$name] = $c;
            }
        }
    }

    private function _getCookieName($one)
    {
        $one = explode('=', $one);
        return $one[0];
    }

    public function setCurl($curl)
    {
        $this->_curl = $curl;
    }
}
