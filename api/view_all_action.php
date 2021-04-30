<?php
function getAllFiles($libKey, $nextPageToken = null, $internal = false)
{
    global $config;
    $items = get($libKey, $nextPageToken);
    if(!$internal) {
         respondWithData($items);
    } else {
        return $items;
    }
}

function get($libKey, $nextPageToken = null) {
    global $service;
    global $config;

    $folderId = $config->libraries[$libKey]->noOcrFolderId;

    if ($nextPageToken) {
        //for folder on my drive - if use Shared drive have to query it differently!
        $optParams = array(
            'pageToken' => $nextPageToken,
            'q' => "'$folderId' in parents AND mimeType != 'application/vnd.google-apps.folder' AND NOT appProperties has {key='isSuspended' and value='true'} AND trashed = false",
            'pageSize' => $config->pageSize,
            'fields' => $config->fields,
            'orderBy' => $config->orderBy
        );
    } else {
        $optParams = array(
            'pageSize' => $config->pageSize,
            'fields' => $config->fields,
            'q' => "'$folderId' in parents AND mimeType != 'application/vnd.google-apps.folder' AND NOT appProperties has {key='isSuspended' and value='true'} AND trashed = false",
            'orderBy' => $config->orderBy
        );
    }

    //get all the files - with expential back off (see retry.php)
    try {
        $driveList = retry(function () use ($service, $optParams) {
            return $service->files->listFiles($optParams);
        });
    } catch (Google_Service_Exception $e) {
        $gError = json_decode($e->getMessage());
        logError($gError);
        $errMsg = $gError->error->message ?? $gError->error . ': ' . $gError->error_description;
        $errCode = $gError->error->code ?? 500;
        respondWithFatalError($errCode, "cannnot get items on GDrive, reason: " . $errMsg);
        die();
    }
    $nextPageToken = $driveList->getNextPageToken();

    if (count($driveList->getFiles()) == 0) {
        return ['library' => $libKey, 'items' => []];
    } else {
        $files = [];
        foreach ($driveList->getFiles() as $file) {
            //exclude folder and other Google stuff e.g. GSheet
            if (strpos($file->getMimeType(), 'vnd.google-apps') !== false) continue;

            $cdlItem = new CdlItem($file);
            array_push($files, $cdlItem->serialize());
        }
        return ['library' => $libKey, 'nextPageToken' => $nextPageToken, 'items' =>$files];
    }
}
?>