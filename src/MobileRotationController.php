<?php

namespace GuzzleMobileProxy;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

class MobileRotationController {

    const DEFAULT_MIN_WAIT_BETWEEN_ROTATIONS = 20;

    const DEFAULT_VERBOSE_AFTER_ATTEMPT_NUMBER = 3;

    const DEFAULT_VERBOSE = false;

    private $nextRotation = NULL;

    private $timesRotated = 0;

    private $minTimeBetweenRotations;

    private $registrationIds;

    private $fcmAuthorization;

    private $verboseAfterAttemptNumber;

    private $verbose;


    public function __construct(array $config = []){

        // These parameters are mandatory
        $mandatoryParams = ['rotation_fcm_authorization', 'rotation_registration_ids'];
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

        $this->fcmAuthorization = $config['rotation_fcm_authorization'];
        $this->registrationIds = is_array($config['rotation_registration_ids']) ? $config['rotation_registration_ids'] : array($config['rotation_registration_ids']);
        $this->minTimeBetweenRotations = $config['rotation_min_time_between_rotations'];
        $this->verbose = $config['rotation_min_time_between_rotations'];
        $this->verboseAfterAttemptNumber = $config['rotation_verbose_after_attempt_number'];

    }


    // Rotate proxy only if it is not being already rotated
    public function rotate(int $attemptNumber, float $delay, &$request, array &$options, ?Response $response){

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

        // Only rotate request if the next rotation time has been reached
        $now = new \DateTime();
        if(!$this->nextRotation || $now > $this->nextRotation){
            $this->timesRotated++;
            if($this->verbose){
                echo date("H:i:s")." - Rotating IP... (".$this->timesRotated.")".PHP_EOL;
            }
            $this->nextRotation = $now->add(new \DateInterval("PT".$this->minTimeBetweenRotations."S"));
            $client = new Client();
            $client->post("https://fcm.googleapis.com/fcm/send",[
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => $this->fcmAuthorization
                ],
                'json' => [
                    'data' =>  ['my_custom_key' => 'my_custom_value'],
                    'android' => [
                        'priority' => 'high'
                    ],
                    "apns" => [
                        "headers" => [
                            "apns-priority" => "10"
                        ]
                    ],
                    "webpush" => [
                        "headers" => [
                            "Urgency" => "high"
                        ]
                    ],
                    'registration_ids' => $this->registrationIds
                ]
            ]);
        }

    }
}