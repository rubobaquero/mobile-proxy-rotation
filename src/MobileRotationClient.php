<?php

namespace GuzzleMobileProxy;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class MobileRotationClient extends Client {

    const DEFAULT_TIMEOUT = 150;
    const DEFAULT_RETRY_MULTIPLIER = 1;

    private $rotationController;

    public function __construct(array $config = [])
    {

        // These parameters are mandatory
        $mandatoryParams = ['proxy'];
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

        // One of this parameters must be set
        if(!isset($config['retry_on_status']) && !isset($config['retry_on_content'])){
            throw new \Exception("retry_on_status or retry_on_content must be set");
        }

        $retryMiddleware = Middleware::retry(
            function ($retries, RequestInterface $request, ResponseInterface $response = null, \Exception $exception = null) {
                // Retry on "Connection reset by peer" error
                if ($exception instanceof \GuzzleHttp\Exception\ConnectException) {
                    if (strpos($exception->getMessage(), 'Connection reset by peer') !== false) {
                        return true;
                    }
                }
                else if ($exception instanceof \GuzzleHttp\Exception\RequestException) {
                    return true;
                }
                
                return false;
            },
            function ($retries) {
                // Wait 1000ms * $retries before retrying
                return 1000 * $retries;
            }
        );

        // Create handler stack
        $stack = HandlerStack::create();
        $stack->push(RotationGuzzleRetryMiddleware::factory());
        $stack->push($retryMiddleware);

        // Create rotation controller
        $this->rotationController = new MobileRotationController($config);

        // Set default config values
        $defaults = [
            'timeout' => self::DEFAULT_TIMEOUT,
            'handler' => $stack,
            'connect_timeout' => self::DEFAULT_TIMEOUT,
            'on_retry_callback' => [$this->rotationController, 'on_retry_callback'],
            'retry_on_timeout' => true,
            'give_up_after_secs' => self::DEFAULT_TIMEOUT,
            'default_retry_multiplier' => self::DEFAULT_RETRY_MULTIPLIER,
        ];

        // Shallow merge defaults underneath config
        $config = $config + $defaults;

        return parent::__construct($config);

    }

    public function rotate(){
        $this->rotationController->rotate();
    }

}
