<?php

namespace PMVC\PlugIn\minions;

use LengthException;
use PMVC\ListIterator;
use PMVC\PlugIn\curl\CurlResponder;

${_INIT_CONFIG}[_CLASS] = __NAMESPACE__ . '\ask';

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
        $this->_hosts = (new ListIterator($hosts))->getIterator();
        $this->_curl = \PMVC\plug(minions::curl);
    }

    public function handleCookie()
    {
        $handlers = $this->caller['cookieHandler'];
        if (empty($handlers)) {
            return;
        }
        $more = [];
        foreach ($handlers as $handler) {
            foreach ($this->_hosts as $h) {
                $this->ask(
                    $h,
                    [
                        minions::options => $handler->set(),
                        minions::callback => $handler->getCallback(),
                    ],
                    $more
                );
            }
            $this->_curl->process();
        }
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

    private function ask($minionsServer, $curl, $more = null)
    {
        $callback = $curl[minions::callback];
        $options = $curl[minions::options];

        $cookies = \PMVC\get($this->cookies, $minionsServer);
        $pCookie = \PMVC\plug('cookie');
        if (!$this->caller['ignoreSetCookie'] && $cookies) {
            if (!empty($options[CURLOPT_COOKIE])) {
                $cookies = array_replace(
                    $cookies,
                    $pCookie->parseCookieString($options[CURLOPT_COOKIE])
                );
            }
            $options[CURLOPT_COOKIE] = $pCookie->toString($cookies);
        }

        $host = $this->_getHost($minionsServer);
        if (is_array($minionsServer) && isset($minionsServer[1])) {
            $options += $minionsServer[1];
        }
        $curlOptions = [
            'curl' => &$options,
            'more' => &$more,
        ];
        \PMVC\dev(function () use (&$curlOptions) {
            $curlOptions['--trace'] = 1;
        }, 'minions-client-debug');
        $this->_curl->post(
            $host,
            function ($r, $curlHelper) use ($callback, $minionsServer, $host) {
                $json = \PMVC\fromJson($r->body);
                if (!isset($json->r)) {
                    return !trigger_error(
                        'Minions respond failed. ' .
                            print_r([$json, $minionsServer], true)
                    );
                }
                $r = CurlResponder::fromObject($json->r);
                $serverTime = $r->serverTime;
                $debugs = &$json->debugs;
                $setCookie = \PMVC\get($r->header, 'set-cookie');
                if (!empty($setCookie)) {
                    $this->_storeCookies($setCookie, $host);
                }
                unset($json);
                \PMVC\dev(
                    /**
                     * @help Minions ask helper
                     */
                    function () use (
                        $minionsServer,
                        $serverTime,
                        $r,
                        $curlHelper,
                        $debugs
                    ) {
                        $pCurl = \PMVC\plug('curl');
                        $optUtil = [$pCurl->opt_to_str(), 'all'];
                        $curlField = $optUtil($curlHelper->set());
                        $curlField['POSTFIELDS']['cook']['curl'] = $optUtil(
                            $curlField['POSTFIELDS']['cook']['curl']
                        );
                        $more = \PMVC\get($curlField['POSTFIELDS']['cook'], 'more');
                        if (!empty($more)) {
                            $moreUtil = [$pCurl->info_to_str(), 'one'];
                            $moreInfo = [];
                            foreach($more as $mk) {
                                $moreInfo[$mk] = $moreUtil($mk);
                            }
                            $curlField['POSTFIELDS']['cook']['more'] = $moreInfo;
                        }
                        $body = $pCurl->bodyDev($r->body);
                        unset($r->info);
                        unset($r->body);
                        return [
                            'Minions Client' => $minionsServer,
                            'Minions Debugs' => $debugs,
                            'Minions Time' => $serverTime,
                            'Respond' => \PMVC\get($r),
                            'Curl Information' => $curlField,
                            'Body' => $body,
                        ];
                    },
                    'minions'
                );
                if (is_callable($callback)) {
                    call_user_func($callback, $r, $curlHelper, [
                        $minionsServer,
                        $serverTime,
                    ]);
                }
            },
            $curlOptions
        );
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
                'Minions server config not correct. ' .
                    var_export($minionsServer, true),
                E_USER_WARNING
            );
        }
        return $host;
    }

    private function _storeCookies($cookies, $host)
    {
        $pCookie = \PMVC\plug('cookie');
        if (empty($this->cookies[$host])) {
            $this->cookies[$host] = [];
        }
        $this->cookies[$host] = array_replace(
            $this->cookies[$host],
            $pCookie->parseSetCookieString($cookies)
        );
    }

    public function setCurl($curl)
    {
        $this->_curl = $curl;
    }
}
