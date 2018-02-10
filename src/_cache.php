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

    public function hasCache($hash)
    {
        return isset($this->_db[$hash]);
    }

    public function process($curlRequest, $more, $ttl)
    {
        $options = $curlRequest[minions::options];
        $callback = $curlRequest[minions::callback];
        $hash = $curlRequest[minions::hash];
        $setCacheCallback = function($r, $curlHelper) use (
                $callback,
                $hash,
                $ttl,
                $more
            ) {
                $bool = null;
                $keepMore = $r->more;
                if ($more) {
                    $r->more = \PMVC\get($keepMore, $more);
                } else {
                    $r->more = null;
                }
                if (is_callable($callback)) {
                    $bool = $callback($r, $curlHelper);
                }
                if ($bool!==false) {
                    $r->body = urlencode(gzcompress($r->body,9));
                    $r->createTime = time();
                    $r->more = $keepMore;
                    $this->_db->setCache($ttl);
                    $this->_db[$hash] = json_encode($r);
                }
            };
        if ($this->hasCache($hash)) {
            $r = json_decode($this->_db[$hash]);
            $createTime = \PMVC\get($r, 'createTime', 0);
            if ($createTime+ $ttl- time() > 0) {
                $r->body = gzuncompress(urldecode($r->body));
                $r->expire = $this->_db->ttl($hash);
                $r->hash = $hash;
                $r->purge = $this->getPurge($hash);
                $r->dbCompositeKey = $this->_db->getCompositeKey($hash);
                if (!empty($more)) {
                    $r->more = \PMVC\get($r->more, $more);
                } else {
                    $r->more = null;
                }

                $bool = null;
                if (is_callable($callback)) {
                    $CurlHelper = new CurlHelper();
                    $CurlHelper->setOptions(
                        null,
                        $setCacheCallback
                    );
                    $CurlHelper->resetOptions($options);
                    $bool = $callback($r, $CurlHelper);
                }
                if ($bool===false) {
                    call_user_func($r->purge);
                }
                \PMVC\dev(
                /**
                 * @help Minons cache status. could use with ?--trace=[curl|curl-json]
                 */
                function() use ($r){
                    /**
                     * @help Decode body with json, use with ?--trace=cache 
                     */
                    \PMVC\dev(function() use (&$rinfo, $r){
                        $rinfo = (array)$r;
                        if (!mb_detect_encoding($rinfo['body'],'utf-8',true)) {
                            $rinfo['body'] = utf8_encode($rinfo['body']);
                        }
                        /**
                         * @help Decode body with json, use with ?--trace=cache,curl 
                         */
                        \PMVC\dev(function() use (&$rinfo, $r){
                            $rinfo['body'] = \PMVC\fromJson($r->body);
                        },'curl-json');
                    },'curl');
                    if (empty($rinfo)) {
                        $rinfo = \PMVC\get(
                            $r,
                            ['hash', 'expire', 'dbCompositeKey']
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
        $oCurl->resetOptions($options);
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

    public function finish()
    {
        $this->_curl->process([true]);
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
