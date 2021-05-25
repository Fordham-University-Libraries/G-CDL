<?php
function getItems($key, $keyType, $libKey)
{
    global $service;
    global $config;

    if ($config->libraries[$libKey]->ils['kind'] == 'Sierra') {
        $key = cleanSierraRecordNuber($key);
    }
    
    $folderId = $config->libraries[$libKey]->noOcrFolderId;
    if ($keyType == 'bibId') {
        $q = "appProperties has {key='bibId' and value='$key'} AND '$folderId' in parents AND mimeType != 'application/vnd.google-apps.folder' AND NOT appProperties has {key='isSuspended' and value='true'} AND trashed = false";
    } else if ($keyType == 'itemId') {
        $q = "appProperties has {key='itemId' and value='$key'} AND '$folderId' in parents AND mimeType != 'application/vnd.google-apps.folder' AND NOT appProperties has {key='isSuspended' and value='true'} AND trashed = false";
    }


    if (!isset($q)) {
        respondWithFatalError(400, "Bad Request: query not supported");
        die();
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

            $fileModel = new CdlItem($file);
            array_push($reponse, $fileModel->serialize());
        }
    } else {
        respondWithFatalError(404, "no item by that $keyType: $key found");
        die();
    }
    respondWithData($reponse);
}
?>