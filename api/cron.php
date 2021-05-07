<?php
//run this file with Cron, may be every minute?
//$ crontab - e
//* * * * * php [path_CDL_app]/cron.php

//this file is accessible to the public! make sure to NOT leak any sensitive info
require 'Config.php';

$config = new Config();
$isEnabled = $config->notifications['emailOnAutoReturn']['enable'];
$method = $config->notifications['emailOnAutoReturn']['method'];
if (!$isEnabled) die('this feature is not enabled');
if ($method == 'cronJob') die('this feature is only enabled for using local cronjob');
$secret = $config->notifications['emailOnAutoReturn']['secret'];

if ($method == 'web') {
    if ($secret) {
        if (!isset($_GET['secret']) || $_GET['secret'] != $secret) die('unauthorized');
    }

}

if ($method == 'webHook') {
    $headers = getallheaders();
    if (!Config::$isProd) {
        $logFilePath = Config::getLocalFilePath('webHook-debug.log');
        error_log(time() . ': ' . print_r($headers, true), 3, $logFilePath);
    }
    if ($secret) {
        if (!isset($headers['X-Goog-Channel-Id']) || strpos($headers['X-Goog-Channel-Id'], $secret) === false) die('unauthorized');
        if (!isset($headers['X-Goog-Changed'])) die('ignored');
        if (strpos($headers['X-Goog-Changed'],'permissions') === false || $headers['X-Goog-Resource-State'] != 'update') die('ignored');
    }
}

//make sure we don't send double/triple same return notif emails
sleep(5);

date_default_timezone_set($config->timeZone);
$fileName = Config::getLocalFilePath($config->notifications['emailOnAutoReturn']['dataFile']);
if (!file_exists($fileName)) die('no items currenlty checked out');

require 'Lang.php';
require 'get_client.php';
require 'CdlItem.php';
require 'User.php';
require 'email.php';
require __DIR__ . '/vendor/autoload.php'; //for Google_Service_Drive_DriveFile

$langObj = new Lang();
$lang = $langObj->serialize();

$file = file_get_contents($fileName);
$currentOutItems = unserialize($file); //serialized CdlItem object 
if (!$currentOutItems) die('no items currenlty checked out');
$newCurrentOutItems = unserialize($file); //make a copy so we cam remove emailed items

$now = time();
$nowStr = date("c", $now); //RFC 3339 / ISO 8601 date
$totalItems = count($currentOutItems);
$itemsEmail = 0;
$itemsRemoved = 0;
//item and user serialized as object
foreach ($currentOutItems as $key => $item) {
    $cdlItem = $item['cdlItem'];
    $user = $item['user'];
    $dueStr = $cdlItem->due;
    $due = strtotime($dueStr);
    $secDiff = $due - $now;
    if ($secDiff < 1) { //past due
        if ($secDiff < 86400) { //only email if item due is less than a day (in case cron wasn't running and the file is backing up)
            email('return', $user, $cdlItem);
            $itemsEmail++;
        }
        //remove from array
        unset($newCurrentOutItems[$key]);
        $itemsRemoved++;
    }
}

if ($itemsRemoved) {
    echo "total items $totalItems ($itemsRemoved removed), emailed $itemsEmail item(s)! ";
    file_put_contents($fileName, serialize($newCurrentOutItems));
} else {
    echo "total items $totalItems, no changes";
}
