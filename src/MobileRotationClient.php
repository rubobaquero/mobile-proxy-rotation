<?php

namespace GuzzleMobileProxy;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleRetry\GuzzleRetryMiddleware;

class MobileRotationClient extends Client {

    const DEFAULT_TIMEOUT = 150;
    const DEFAULT_RETRY_MULTIPLIER = 1;

    private $rotationController;

    public function __construct(array $config = [])
    {

        // These parameters are mandatory
        $mandatoryParams = ['proxy', 'retry_on_status'];
        foreach($mandatoryParams as $param){
            if(!isset($config[$param])){
                throw new \Exception("$param parameter is mandatory");
            }
        }

        // These parameters are forbidden
        $forbiddenParams = ['handler', 'on_retry_callback'];
        foreach($forbiddenParams as $param){
            if(isset($config[$param])){
                throw new \Exception("$param parameter is forbidden");
            }
        }

        // Create handler stack
        $stack = HandlerStack::create();
        $stack->push(GuzzleRetryMiddleware::factory());

        // Create rotation controller
        $this->rotationController = new MobileRotationController($config);

        // Set default config values
        $defaults = [
            'timeout' => self::DEFAULT_TIMEOUT,
            'handler' => $stack,
            'connect_timeout' => self::DEFAULT_TIMEOUT,
            'on_retry_callback' => [$this->rotationController, 'rotate'],
            'retry_on_timeout' => true,
            'give_up_after_secs' => self::DEFAULT_TIMEOUT,
            'default_retry_multiplier' => self::DEFAULT_RETRY_MULTIPLIER,
        ];

        // Shallow merge defaults underneath config
        $config = $config + $defaults;

        return parent::__construct($config);

    }

}