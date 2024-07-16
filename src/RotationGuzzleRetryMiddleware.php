<?php

namespace GuzzleMobileProxy;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use GuzzleRetry\GuzzleRetryMiddleware;

class RotationGuzzleRetryMiddleware extends GuzzleRetryMiddleware {

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

    protected function onRejected(RequestInterface $request, array $options): callable
    {
        return function (Throwable $reason) use ($request, $options): PromiseInterface {
            // If was bad response exception, test if we retry based on the response headers
            if ($reason instanceof BadResponseException) {
                if ($this->shouldRetryHttpResponse($options, $reason->getResponse())) {
                    return $this->doRetry($request, $options, $reason->getResponse());
                }
                // If this was a connection exception, test to see if we should retry based on connect timeout rules
            } elseif ($reason instanceof ConnectException) {
                // If was another type of exception, test if we should retry based on timeout rules
                if ($this->shouldRetryConnectException($options)) {
                    return $this->doRetry($request, $options);
                }
            } elseif ($reason instanceof RequestException) {
                // If was another type of exception, test if we should retry based on timeout rules
                if ($this->shouldRetryRequestException($options, $reason->getResponse())) {
                    return $this->doRetry($request, $options);
                }
            }
            
            // If made it here, then we have decided not to retry the request
            // Future-proofing this; remove when bumping minimum Guzzle version to 7.0
            if (class_exists('\GuzzleHttp\Promise\Create')) {
                return \GuzzleHttp\Promise\Create::rejectionFor($reason);
            } else {
                return rejection_for($reason);
            }
        };
    }
    
    protected function shouldRetryRequestException(array $options, ?ResponseInterface $response = null): bool
    {
        return true;
    }

}
