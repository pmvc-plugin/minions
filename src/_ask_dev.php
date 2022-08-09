<?php

namespace PMVC\PlugIn\minions;

${_INIT_CONFIG}[_CLASS] = __NAMESPACE__ . '\AskDev';

class AskDev
{
    public function __invoke($payload)
    {
        extract(
            \PMVC\assign(
                ['minionsServer', 'serverTime', 'r', 'curlHelper', 'debugs'],
                $payload
            )
        );
        $debugRespond = clone $r;
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
            foreach ($more as $mk) {
                $moreInfo[$mk] = $moreUtil($mk);
            }
            $curlField['POSTFIELDS']['cook']['more'] = $moreInfo;
        }
        $url = \PMVC\plug('url')->getUrl(
            \PMVC\get($curlField['POSTFIELDS']['cook']['curl'], 'URL')
        );
        $arrUrl = \PMVC\get($url);
        $arrUrl['query'] = \PMVC\get($url->query);
        $body = $pCurl->body_dev($debugRespond->body);
        $debugRespond->body = $body;
        unset($debugRespond->info);
        return [
            '-url' => (string) $url,
            'urlObj' => $arrUrl,
            'Minions Client' => $minionsServer,
            'Minions Debugs' => $debugs,
            'Minions Time' => $serverTime,
            'Respond' => \PMVC\get($debugRespond),
            'Curl Information' => $curlField,
        ];
    }
}
