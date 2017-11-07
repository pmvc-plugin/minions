<?php

namespace PMVC\PlugIn\minions;

use LengthException;
use PMVC\ListIterator;
use PMVC\PlugIn\curl\CurlHelper;

${_INIT_CONFIG}[_CLASS] = __NAMESPACE__.'\cache';

class cache 
{
    private $_curl;
    private $_db;
    public function __construct($caller)
    {
        $this->_curl = \PMVC\plug(minions::curl);
        $this->_db = \PMVC\plug('guid')->getDb('MinionsCache');
    }
    
    public function __invoke()
    {
        return $this;
    }

    private function _getHash($options, $more)
    {
        unset($options[CURLINFO_HEADER_OUT]);
        return \PMVC\hash([$options, $more]);
    }

    public function process($curlRequest, $more, $ttl)
    {
        $options = $curlRequest[minions::options];
        $callback = $curlRequest[minions::callback];
        $hash = $this->_getHash($options, $more);
        $setCacheCallback = function($r, $curlHelper) use (
                $callback,
                $hash,
                $ttl
            ) {
                $bool = null;
                if (is_callable($callback)) {
                    $bool = $callback($r, $curlHelper);
                }
                $r->body = urlencode(gzcompress($r->body,9));
                $r->createTime = time();
                if ($bool!==false) {
                    $this->_db->setCache($ttl);
                    $this->_db[$hash] = json_encode($r);
                }
            };
        if (isset($this->_db[$hash])) {
            $r = json_decode($this->_db[$hash]);
            $createTime = \PMVC\get($r, 'createTime', 0);
            if ($createTime+ $ttl- time() > 0) {
                $r->body = gzuncompress(urldecode($r->body));
                $r->expire = $this->_db->ttl($hash);
                $r->hash = $this->_db->getCompositeKey($hash);
                $r->purge = $this->getPurge($hash);
                $bool = null;
                if (is_callable($callback)) {
                    $CurlHelper = new CurlHelper();
                    $CurlHelper->setCallback($setCacheCallback);
                    $CurlHelper->set($options);
                    $bool = $callback($r, $CurlHelper);
                }
                if ($bool===false) {
                    call_user_func($r->purge);    
                }
                \PMVC\dev(
                /**
                 * @help Minons cache status. could use with [json|curl]
                 */
                function() use ($r){
                    if (\PMVC\isDev('curl')) {
                        $rinfo = $r;
                        if (\PMVC\isDev('json')) {
                            $rinfo->json = \PMVC\fromJson($r->body);
                        }
                    } else {
                        $rinfo = \PMVC\get(
                            $r,
                            ['hash', 'expire']
                        );
                    }
                    return [
                        $r->url,
                        'r'=>$rinfo
                    ];
                },'cache');
                return;
            }
        }
        $oCurl = $this->_curl->get(
            null,
            $setCacheCallback
        );
        $oCurl->set($options);
        \PMVC\dev(
        /**
         * @help Minons cache status
         */
        function() use ($hash, $options){
            return [
                'status'=>'miss',
                'hash'=>$hash,
                'url'=>$options[CURLOPT_URL],
                'options'=>$options
            ];
        },'cache');
    }

    public function getPurge($hash)
    {
        return function() use ($hash) {
            $this->purge($hash);
        };
    }

    public function purge($id)
    {
        unset($this->_db[$id]);
    }

    public function finish($more=[])
    {
        $more = \PMVC\toArray($more);
        \PMVC\dev(function() use (&$more){
            $more[]= 'request_header';
        }, 'req');
        $this->_curl->process($more);
    }

    public function setCurl($curl)
    {
        $this->_curl = $curl;
    }

    public function setStore($db)
    {
        $this->_db = $db;
    }

}
