<?php

namespace GuzzleMobileProxy;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use GuzzleRetry\GuzzleRetryMiddleware;

class MobileRotationGuzzleRetryMiddleware extends GuzzleRetryMiddleware {

    /**
     * Check whether to retry a request that received an HTTP response
     *
     * This checks three things:
     *
     * 1. The response status code against the status codes that should be retried
     * 2. The number of attempts made thus far for this request
     * 3. If 'give_up_after_secs' option is set, time is still available
     *
     * @param array<string,mixed> $options
     * @param ResponseInterface|null $response
     * @return bool  TRUE if the response should be retried, FALSE if not
     */
    protected function shouldRetryHttpResponse(array $options, ?ResponseInterface $response = null): bool
    {
        $statuses = array_map('\intval', (array) $options['retry_on_status']);
        $hasRetryAfterHeader = $response && $response->hasHeader('Retry-After');

        switch (true) {
            case $options['retry_enabled'] === false:
            case $this->hasTimeAvailable($options) === false:
            case $this->countRemainingRetries($options) === 0: // No Retry-After header, and it is required?  Give up!
            case (! $hasRetryAfterHeader && $options['retry_only_if_retry_after_header']):
                return false;

            // Conditions met; see if status code matches one that can be retried
            default:
                $statusCode = $response ? $response->getStatusCode() : 0;
                // If response code is 200, still we have to check if retry_on_content is activated
                if($statusCode == 200 && isset($options['retry_on_content'])){
                    return mb_strpos($response->getBody(), $options['retry_on_content']) !== FALSE;
                }

                return in_array($statusCode, $statuses, true);
        }
    }

}