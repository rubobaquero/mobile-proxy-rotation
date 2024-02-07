<?php

namespace GuzzleMobileProxy;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

class UrlRotationController {

    const DEFAULT_MIN_WAIT_BETWEEN_ROTATIONS = 20;

    const DEFAULT_VERBOSE_AFTER_ATTEMPT_NUMBER = 3;

    const DEFAULT_VERBOSE = false;

    private $nextRotation = NULL;

    private $timesRotated = 0;

    private $minTimeBetweenRotations;

    private $rotationUrl;

    private $verboseAfterAttemptNumber;

    private $verbose;


    public function __construct(array $config = []){

        // These parameters are mandatory
        $mandatoryParams = ['rotation_url'];
        foreach($mandatoryParams as $param){
            if(!isset($config[$param])){
                throw new \Exception("$param parameter is mandatory");
            }
        }

        // Default values
        $defaults = [
            'rotation_min_time_between_rotations' => self::DEFAULT_MIN_WAIT_BETWEEN_ROTATIONS,
            'rotation_verbose' => self::DEFAULT_VERBOSE,
            'rotation_verbose_after_attempt_number' => self::DEFAULT_VERBOSE_AFTER_ATTEMPT_NUMBER
        ];

        // Shallow merge defaults underneath config
        $config = $config + $defaults;

        $this->rotationUrl = $config['rotation_url'];
        $this->minTimeBetweenRotations = $config['rotation_min_time_between_rotations'];
        $this->verbose = $config['rotation_verbose'];
        $this->verboseAfterAttemptNumber = $config['rotation_verbose_after_attempt_number'];

    }


    // Rotate proxy only if it is not being already rotated
    public function on_retry_callback(int $attemptNumber, float $delay, &$request, array &$options, ?Response $response){

        if($this->verbose && $attemptNumber >= $this->verboseAfterAttemptNumber){
            echo sprintf(
                "%s - Retrying URL: %s - Code: %s - Sleep %s sec - Attempt #%s".PHP_EOL,
                date("H:i:s"),
                $request->getUri()->getPath(),
                isset($response) ? $response->getStatusCode() : "NR",
                number_format($delay, 2),
                $attemptNumber
            );
        }

        $this->rotate();

    }

    public function rotate(){

        // Only rotate request if the next rotation time has been reached
        $now = new \DateTime();
        if(!$this->nextRotation || $now > $this->nextRotation){
            $this->timesRotated++;
            if($this->verbose){
                echo date("H:i:s")." - Rotating IP... (".$this->timesRotated.")".PHP_EOL;
            }
            $this->nextRotation = $now->add(new \DateInterval("PT".$this->minTimeBetweenRotations."S"));
            
            // Obtain current IP
            if($this->verbose){
                $currentIp = $this->currentIp();
                echo date("H:i:s")." - Current IP: ".$currentIp.PHP_EOL;
            }
            
            // Rotate
            $client = new Client();
            $client->get($this->rotationUrl);
            sleep(7);
            
            // Obtain new IP
            if($this->verbose){
                $currentIp = $this->currentIp();
                echo date("H:i:s")." - New IP: ".$currentIp.PHP_EOL;
            }
        }
    }
    
    private function currentIp(){
        $client = new Client();
        $ipUrl = "http://ip-api.com/json";
        $response = $client->get($ipUrl);
        $currentIp = json_decode($response->getBody()->getContents());
        if(isset($currentIp->query)){
            return $currentIp->query;
        }
        return NULL;
    }
}
