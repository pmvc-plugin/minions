<?php

namespace PMVC\PlugIn\minions;

use LengthException;
use PMVC\PlugIn\curl\CurlHelper;
use PMVC\PlugIn\curl\CurlResponder;

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

    public function process($curlRequest, $more, $ttl)
    {
        $options = $curlRequest[minions::options];
        $callback = $curlRequest[minions::callback];
        $hash = $curlRequest[minions::hash];
        $setCacheCallback = function ($r, $curlHelper) use (
            $callback,
            $hash,
            $options,
            $ttl,
            $more
        ) {
            $bool = null;
            $this->_storeCache($r, $hash, $ttl);
            if ($more) {
                $r->more = \PMVC\get($r->more, $more);
            } else {
                $r->more = null;
            }
            $r->info = function () use ($hash, $options) {
              return [
                'status'=>'miss',
                'hash'=>$hash,
                'purge'=> $this->_getPurgeDevKey($hash),
                'url'=>$options[CURLOPT_URL],
                'options'=>$options,
              ];
            };
            if (is_callable($callback)) {
                $bool = $callback($r, $curlHelper);
            }
            if ($bool===false) {
                $this->purge($hash);
            }
            \PMVC\dev(
              /**
               * @help Minons cache status. could use with ?--trace=[curl|curl-json]
               */
              function () use ($r) {
                return $r->info();
              }, 'cache'
            ); // dev
        };
        if ($this->hasCache($hash)) {
            $r = $this->getCache($hash);
            $createTime = \PMVC\get($r, 'createTime', 0);
            if (!$this->_isExpire($createTime, $ttl)) {
                if (!empty($more)) {
                    $r->more = \PMVC\get($r->more, $more);
                } else {
                    $r->more = null;
                }

                $r->info = function() use($r) {
                  return $this->caller->cache_dev($r, $this->_getPurgeDevKey($r->hash));
                };

                if (is_callable($callback)) {
                    $CurlHelper = new CurlHelper();
                    $CurlHelper->setOptions(
                        null,
                        $setCacheCallback
                    );
                    $CurlHelper->resetOptions($options);
                    $r->cache = true;
                    $ttl = call_user_func_array(
                        $callback,
                        [
                            $r,
                            $CurlHelper,
                        ]
                    );
                }
                if ($this->_isExpire($createTime, $ttl)) {
                    $r->purge();
                }

                \PMVC\dev(
                    /**
                    * @help Purge minons cache
                    */
                    function () use ($r) {
                        $r->purge();
                        return ['Clean-Cache'=>$r->hash];
                    }, $this->_getPurgeDevKey($r->hash)
                );

                \PMVC\dev(
                  /**
                   * @help Minons cache status. could use with ?--trace=[curl|curl-json]
                   */
                  function () use ($r) {
                    return $r->info();
                  }, 'cache'
                ); // dev
                return;
            }
        }
        $oCurl = $this->_curl->get(
            null,
            $setCacheCallback
        );
        $oCurl->resetOptions($options);


    }

    private function _getPurgeDevKey($hash)
    {
        return 'purge-'.$hash;
    }

    private function _isExpire($createTime, $ttl)
    {
        if ($ttl === false) {
            return true;
        } else {
            if (is_numeric($ttl)) {
                return ($createTime+ $ttl- time() < 0);
            } else {
                return false;
            }
        }
    }

    private function _getHash($maybeCurl)
    {
        if ($maybeCurl instanceof CurlHelper) {
            return $maybeCurl->getHash();
        } else {
            return $maybeCurl;
        }
    }

    private function _storeCache($r, $hash, $ttl)
    {
      $r->createTime = time();
      $next = clone $r;
      $next->body = urlencode(gzcompress($r->body, 9));
      $this->_db->setCache($ttl);
      $this->_db[$hash] = json_encode($next);
    }

    public function getCache($maybeHash)
    {
        $hash = $this->_getHash($maybeHash);
        $r = CurlResponder::fromJson($this->_db[$hash]);
        if (!$r) {
            return false;
        }
        $r->expire = $this->_db->ttl($hash);
        $r->hash = $hash;
        $r->purge = $this->getPurge($hash);
        $r->dbCompositeKey = $this->_db->getCompositeKey($hash);
        return $r;
    }

    public function hasCache($maybeHash)
    {
        $hash = $this->_getHash($maybeHash);
        return isset($this->_db[$hash]);
    }

    public function getPurge($maybeHash)
    {
        $hash = $this->_getHash($maybeHash);
        return function () use ($hash) {
            $this->purge($hash);
        };
    }

    public function purge($maybeHash)
    {
        $hash = $this->_getHash($maybeHash);
        if (isset($this->_db[$hash])) {
            unset($this->_db[$hash]);
            return !isset($this->_db[$hash]);
        } else {
            return false;
        }
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
