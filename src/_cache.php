<?php

namespace PMVC\PlugIn\minions;

use LengthException;
use PMVC\ListIterator;

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
        $hash = \PMVC\hash($options);
        if (isset($this->_db[$hash])) {
            $expire = $this->_db->ttl($hash);
            if ($expire > $ttl) {
                $r = json_decode($this->_db[$hash]);
                $r->body = gzuncompress(urldecode($r->body));
                $r->expire = $expire;
                $r->hash = $this->_db->getCompositeKey($hash);
                if (is_callable($callback)) {
                    $callback($r);
                }
                return;
            }
        }
        $oCurl = $this->_curl->get(
            null,
            function($r) use ($callback, $hash, $ttl){
                if (is_callable($callback)) {
                    $callback($r);
                }
                $r->body = urlencode(gzcompress($r->body,9));
                $this->_db->setCache($ttl);
                $this->_db[$hash] = json_encode($r);
            }
        );
        $oCurl->set($options);
    }

    public function finish()
    {
        $this->_curl->process();
    }

    public function setCurl($curl)
    {
        $this->_curl = $curl;
    }

}
