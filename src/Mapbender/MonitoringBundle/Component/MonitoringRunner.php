<?php
namespace Mapbender\MonitoringBundle\Component;
use Mapbender\MonitoringBundle\Entity\MonitoringDefinition;
use Mapbender\MonitoringBundle\Entity\MonitoringJob;
use Mapbender\Component\HTTP\HTTPClient;

class MonitoringRunner {
    protected $md;
    protected $client;

    public function __construct(MonitoringDefinition $md,HTTPclient $client){
        $this->md = $md;
        $this->client = $client;
    }

    public function run(){
        $job = new MonitoringJob();
        $time_pre = microtime(true);
        $result = $this->client->open($this->md->getRequestUrl());
        $time_post = microtime(true);
        $job->setMonitoringDefinition($this->md);
        $job->setResult($result->getData());
        $job->setLatency(round($time_post-$time_pre,3));
        return $job;
    }
}
