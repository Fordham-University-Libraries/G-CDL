<?php

/* 
 * Retry function for e.g. external API calls
 * https://gist.github.com/orottier/3ac79378dd38b91ac6953c8618708eb4
 * Will try the risky API call, and retries with an ever increasing delay if it fails
 * Throws the latest error if $maxRetries is reached,
 * otherwise it will return the value returned from the closure.
 *
 * You specify the exceptions that you expect to occur.
 * If another exception is thrown, the script aborts 
 *
 */

function retry(callable $callable, $expectedErrors = 'Google_Service_Exception', $maxRetries = 5, $initialWait = 1.0, $exponent = 2)
{
    if (!is_array($expectedErrors)) {
        $expectedErrors = [$expectedErrors];
    }

    try {
        return call_user_func($callable);
    } catch (Exception $e) {
        // get whole inheritance chain
        $errors = class_parents($e);
        array_push($errors, get_class($e));

        // if unexpected, re-throw
        if (!array_intersect($errors, $expectedErrors)) {
            throw $e;
        }

        //only retry 5xx stuff
        $gErrorCode = json_decode($e->getMessage(), true)['error']['code'];
        if (is_int($gErrorCode)) {
            if (substr($gErrorCode, 0, 1) != '5') {
                logError($gErrorCode);
                throw $e;
            }
        }

        // exponential backoff
        if ($maxRetries > 0) {
            usleep($initialWait * 1E6);
            return retry($callable, $expectedErrors, $maxRetries - 1, $initialWait * $exponent);
        }

        // max retries reached
        //echo "max retries reached";
        throw $e;
    }
}