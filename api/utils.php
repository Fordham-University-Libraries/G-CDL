<?php

function logError($error) {
    $logFilePath = Config::getLocalFilePath('error.log');
    if (is_array($error) || is_object($error)) {
        error_log(time() . ': ' . print_r($error, true), 3, $logFilePath);
    } else {
        error_log(time() . ": " . $error . "\n", 3, $logFilePath);
    }
}