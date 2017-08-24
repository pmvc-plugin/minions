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

    public function handleCookie()
    {
        $handler = $this->caller['cookieHandler'];
        if (empty($handler)) {
            return;
        }
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
            if (!empty($options[CURLOPT_COOKIE])) {
                $cookies = array_replace(
                    $cookies,
                    $this->_parse_cookie_string(
                        $options[CURLOPT_COOKIE]
                    )
                );
            }
            $options[CURLOPT_COOKIE] = join('; ', $cookies);
        }

        $host = $this->_getHost($minionsServer);
        if (is_array($minionsServer) && isset($minionsServer[1])) {
            $options += $minionsServer[1];
        }
        $this->_curl->post($host, function($r, $curlHelper) use($callback, $minionsServer, $host) {
            $json =\PMVC\fromJson($r->body);
            $serverTime = $json->serverTime;
            if (!isset($json->r)) {
                return !trigger_error(
                    'Minions respond failed. '.
                    var_export([$json, $minionsServer, $serverTime],true)
                );
            }
            $r =& $json->r;
            $setCookie = \PMVC\get($r->header,'set-cookie');
            if (!empty($setCookie)) {
                $this->_storeCookies($setCookie, $host);
            }
            unset($json);
            $r->body = gzuncompress(urldecode($r->body));
            \PMVC\dev(
            /**
             * @help Minions ask helper 
             */
            function() use (
                $minionsServer,
                $serverTime,
                $r,
                $curlHelper
            ){
                $optUtil = [\PMVC\plug('curl')->opt_to_str(), 'all']; 
                $curlField = $optUtil($curlHelper->set());
                $curlField['POSTFIELDS']['curl'] = $optUtil($curlField['POSTFIELDS']['curl']);
                return [
                    'Minions Client'=>$minionsServer,
                    'Minions Time'=>$serverTime,
                    'Respond'=>$r,
                    'Curl Information'=> $curlField,
                    'Body'=> \PMVC\fromJson($r->body)
                ];
            },'minions');
            if (is_callable($callback)) {
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

    private function _getHost($minionsServer)
    {
        if (is_string($minionsServer)) {
            $host = $minionsServer;
        } else {
            $host = \PMVC\get($minionsServer, '0');
        }
        if (empty($host)) {
            return !trigger_error(
                'Minions server config not correct. '.
                var_export($minionsServer,true),
                E_USER_WARNING
            );
        }
        return $host;
    }

    private function _storeCookies($cookies, $host)
    {
        $cookies = \PMVC\toArray($cookies);
        if (empty($this->cookies[$host])) {
            $this->cookies[$host] = [];
        }
        foreach ($cookies as $c) {
            $name = $this->_getCookieName($c);
            $c = explode(';', $c)[0];
            if ($name) {
                $this->cookies[$host][$name] = $c;
            }
        }
    }

    private function _parse_cookie_string($s)
    {
        $cookies = explode(';', $s);
        $result = [];
        foreach ($cookies as $c) {
            $c = trim($c);
            $name = $this->_getCookieName($c);
            $result[$name] = $c;
        }
        return $c;
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
