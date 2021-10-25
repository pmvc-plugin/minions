<?php

namespace PMVC\PlugIn\minions;

use PMVC\PlugIn\curl\CurlResponder;

${_INIT_CONFIG}[_CLASS] = __NAMESPACE__.'\CacheDev';

class CacheDev {
    public function __invoke(CurlResponder $r, $purgeKey)
    {
      $rinfo = null;
      \PMVC\dev(
        /**
         * @help Decode body with json, use with ?--trace=cache 
         */
        function () use (&$rinfo, $r) {
          $rinfo = (array)$r;
          $rinfo['body'] = \PMVC\plug('curl')->body_dev($r->body); 
          unset($rinfo['info']);
        }, 'curl'
      );

      if (empty($rinfo)) {
        $rinfo = \PMVC\get(
          $r,
          ['hash', 'expire', 'dbCompositeKey', 'createTime']
        );
        $rinfo['help'] = 'get move info use ?--trace=curl';
      }
      $rinfo['createLocalTime'] = date("Y/m/d H:i:s", $rinfo['createTime']);
      $rinfo['-url'] = $r->url;
      $rinfo['purge'] = $purgeKey;
      unset($rinfo['url']);
      
      return $rinfo;
    }
}
