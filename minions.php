<?php
namespace PMVC\PlugIn\minions;

${_INIT_CONFIG}[_CLASS] = __NAMESPACE__.'\minions';

\PMVC\initPlugin(array(
    'curl'=>null
));

class minions extends \PMVC\PlugIn\curl\curl
{
    private $queue=array();
    const options = 'options';
    const callback = 'callback';
    const hosts = 'hosts';
    const curl = 'curl';
    const delay = 'delay';

    function process($more=null)
    {
        $curls = $this->getCurls();
        if (!empty($curls) && \PMVC\isArray($curls)) {
            foreach ($curls as $curl) {
                $this->queue[] = array(
                    self::options=>$curl->set(),
                    self::callback=>$curl->getCallback()
                );
                $curl->clean();
            }
            $this->clean();
        }
        $curlPlug = \PMVC\plug(self::curl);
        while(count($this->queue))
        {
            if (empty($this[self::hosts]) || !is_array($this[self::hosts])) {
                break;
            }
            foreach($this[self::hosts] as $host){
                $pop = array_pop($this->queue);
                $this->askMinions($host, $pop, $more);
                if (empty($this->queue))
                {
                    break;
                }
            }
            $curlPlug->process();
            sleep(1);
        }
    }

    function askMinions($minionsServer, $curl, $more=null)
    {
        $curlPlug = \PMVC\plug(self::curl);
        $callback = $curl[self::callback];
        $options = $curl[self::options];
        if (is_string($minionsServer)) {
            $host = $minionsServer;
        } else {
            $host = $minionsServer[0];
            if (isset($minionsServer[1])) {
                $options += $minionsServer[1];
            }
        }
        $curlPlug->post($host, function($r) use($callback, $minionsServer) {
            $json = \PMVC\fromJson($r->body);
            if (!isset($json->r)) {
                return !trigger_error("Minions respond failed. ".var_export($json,true));
            }
            $r = $json->r;
            $r->body = base64_decode($r->body);
            call_user_func (
                $callback, 
                $r, 
                array (
                    $minionsServer, 
                    $json->serverTime
                )
            );
        }, array(
            'curl'=>$options,
            'more'=>$more
        ));
    }

}
