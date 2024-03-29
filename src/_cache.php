<?php

namespace PMVC\PlugIn\minions;

use LengthException;
use PMVC\PlugIn\curl\CurlHelper;
use PMVC\PlugIn\curl\CurlResponder;

${_INIT_CONFIG}[_CLASS] = __NAMESPACE__ . '\cache';

class cache
{
    private $_curl;
    private $_storage;
    public function __construct($caller)
    {
        $this->_curl = \PMVC\plug(minions::curl);
        $this->setStore(\PMVC\plug('guid')->getModel('MinionsCache'));
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
            $r->info = function () use ($hash, $options, $ttl) {
                return [
                    '-url' => $options[CURLOPT_URL],
                    'status' => 'miss',
                    'ttl' => $ttl,
                    'hash' => $hash,
                    'purge' => $this->_getPurgeDevKey($hash),
                    'options' => \PMVC\plug('curl')
                        ->opt_to_str()
                        ->all($options),
                ];
            };
            if (is_callable($callback)) {
                $bool = $callback($r, $curlHelper);
            }
            if ($bool === false) {
                $this->purge($hash);
            }
            \PMVC\dev(
                /**
                 * @help Minions cache status. could use with ?--trace=curl
                 */
                function () use ($r) {
                    return $r->info();
                },
                'cache'
            ); // dev
        };

        /**
         * hasCache -> return cache
         * noCache -> run $this->_curl->get
         * isExipre default to true
         * finial process will call with $this->finish
         */
        $isExpire = true;
        $r = null;
        if ($this->hasCache($hash)) {
            $r = $this->getCache($hash);
            $isExpire = $this->_isExpire($r->createTime, $ttl);
        }
        if ($isExpire) {
            $oCurl = $this->_curl->get(null, $setCacheCallback);
            $oCurl->resetOptions($options);
        } else {
            $r->more = empty($more) ? null : \PMVC\get($r->more, $more);
            if (is_callable($callback)) {
                $CurlHelper = new CurlHelper();
                $CurlHelper->setOptions(null, $setCacheCallback);
                $CurlHelper->resetOptions($options);
                $r->cache = true;
                $nextTtl = call_user_func_array($callback, [$r, $CurlHelper]);
                if ($this->_isExpire($r->createTime, $nextTtl)) {
                    $r->purge();
                }
            }
            // locate after callback for update $ttl
            $r->info = function () use ($r, $ttl, $nextTtl) {
                if (is_numeric($nextTtl)) {
                    $r->expire = $this->_calExpireSec($r->createTime, $nextTtl);
                    $ttl = $nextTtl;
                }
                return $this->caller->cache_dev(
                    $r,
                    $this->_getPurgeDevKey($r->hash),
                    $ttl
                );
            };

            \PMVC\dev(
                /**
                 * @help PURGE: [url]
                 */
                function () use ($r) {
                    $r->purge();
                    return [
                        'Clean-Cache' => [
                            'hash' => $r->hash,
                            'url' => $r->url,
                        ],
                    ];
                },
                $this->_getPurgeDevKey($r->hash),
                ['url' => $r->url]
            );

            \PMVC\dev(
                /**
                 * @help Minons cache status. could use with ?--trace=curl
                 */
                function () use ($r) {
                    return $r->info();
                },
                'cache'
            ); // dev
        }
    }

    private function _getPurgeDevKey($hash)
    {
        return 'purge-' . substr($hash, 0, 8);
    }

    private function _calExpireSec($createTime, $ttl)
    {
        if (!$createTime) {
            $createTime = 0;
        }
        if (is_numeric($ttl)) {
            return $createTime + $ttl - time();
        } else {
            /**
             * use for $this->_isExpire 
             * only $ttl equal `false` or `number` will check expire, else will assume not expire.
             */
            return 0;
        }
    }

    private function _isExpire($createTime, $ttl)
    {
        if ($ttl === false) {
            return true;
        } else {
            return $this->_calExpireSec($createTime, $ttl) < 0;
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
        $nextBody = \PMVC\get($r, 'body');
        if ($nextBody) {
            $next->body = urlencode(gzcompress($nextBody, 9));
        }
        $this->_storage->setTTL($ttl);
        $this->_storage[$hash] = json_encode($next);
    }

    public function getCache($maybeHash)
    {
        $hash = $this->_getHash($maybeHash);
        $r = CurlResponder::fromJson($this->_storage[$hash]);
        if (!$r) {
            return false;
        }
        $r->expire = $this->_storage->ttl($hash);
        $r->hash = $hash;
        $r->purge = $this->getPurge($hash);
        $r->dbCompositeKey = $this->_storage->getCompositeKey($hash);
        return $r;
    }

    public function hasCache($maybeHash)
    {
        $hash = $this->_getHash($maybeHash);
        return isset($this->_storage[$hash]);
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
        if (isset($this->_storage[$hash])) {
            unset($this->_storage[$hash]);
            return !isset($this->_storage[$hash]);
        } else {
            return false;
        }
    }

    public function finish()
    {
        /**
         * set more to [true] for get all information
         *
         * @see https://github.com/pmvc-plugin/curl/blob/master/src/CurlResponder.php#L112-L114
         */
        $this->_curl->process([true]);
    }

    public function setCurl($curl)
    {
        $this->_curl = $curl;
    }

    public function setStore($model)
    {
        $this->_storage = $model;
    }
}
