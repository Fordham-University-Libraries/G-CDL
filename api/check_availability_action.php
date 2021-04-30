<?php

function checkAvailability($key, $keyType, $libKey)
{
    global $service;
    global $config;    
    $folderId = $config->libraries[$libKey]->noOcrFolderId;
    if ($keyType == 'bibId') {
        $q = "appProperties has {key='bibId' and value='$key'} AND '$folderId' in parents AND mimeType != 'application/vnd.google-apps.folder' AND NOT appProperties has {key='isSuspended' and value='true'} AND trashed = false";
    } else if ($keyType == 'itemId') {
        $q = "appProperties has {key='itemId' and value='$key'} AND '$folderId' in parents AND mimeType != 'application/vnd.google-apps.folder' AND NOT appProperties has {key='isSuspended' and value='true'} AND trashed = false";
    }

    if (!isset($q)) {
        respondWithFatalError(400, "Bad Request: query not supported");
    }

    $optParams = [
        'q' => $q,
        'pageSize' => 100,
        'fields' => $config->fields,
        'orderBy' => $config->orderBy
    ];
    $results = $service->files->listFiles($optParams);
    $reponse = [];
    $i = 0;
    if (count($results) > 0) {
        foreach ($results->getFiles() as $file) {
            //if owner is NOT me, skip it cuz can't change userCanCopy without being an owner
            if (!$file->getOwnedByMe()) continue;
            $cdlItem = new CdlItem($file);
            $item = [
                'bibId' => $cdlItem->bibId,
                'itemId' => $cdlItem->itemId,
                'available' => $cdlItem->available
            ];
            if (!$cdlItem->available) {
                $item['due'] = $cdlItem->due;
                $item['dueStr'] = date("m/d/yy g:iA", strtotime($item['due']));
            }
            array_push($reponse, $item);
        }
    } else {
        respondWithFatalError(404, "no item by that $keyType: $key found");
    }

    respondWithData($reponse);
}
?>