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
          $rinfo['body'] = \PMVC\utf8Export($rinfo['body']);

          \PMVC\dev(
            /**
             * @help Decode body with json, use with ?--trace=cache,curl 
             */
            function () use (&$rinfo, $r) {
              $rinfo['body'] = \PMVC\fromJson($r->body, true);
            }, 'curl-json'
          );

        }, 'curl'
      );

      if (empty($rinfo)) {
        $rinfo = \PMVC\get(
          $r,
          ['hash', 'expire', 'dbCompositeKey']
        );
      }
      return [
        $r->url,
        'r'=>$rinfo,
        'purge'=> $purgeKey,
      ];
    }
}
