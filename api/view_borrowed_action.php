<?php
function getFilesForViewer()
{
    global $service;
    global $config;
    global $user;

    if ($user->isDriveOwner) {
        respondWithData(
            [
                'error' => 'user is a owner of the drive'
            ]
        );
        die();
    }

    $parentFolderIds = [];
    foreach ($config->libraries as $libKey => $library) {
        $query = "'" . $library->noOcrFolderId . "' in parents";
        array_push($parentFolderIds, $query);
    }
    $parentFolderIdsQuery = implode(' OR ', $parentFolderIds);

    $optParams = array(
        'pageSize' => 100,
        'fields' => $config->fields,
        'q' => "'$user->email' in readers AND ($parentFolderIdsQuery) AND mimeType != 'application/vnd.google-apps.folder' AND trashed = false",
      );
    
    $results = retry(function () use ($service, $optParams) {
        return $service->files->listFiles($optParams);
    });
    $cdlItem;
    if (count($results) == 1) {
        foreach ($results->getFiles() as $file) {
            $cdlItem = new CdlItem($file);
        }
        respondWithData(
            [
                'usersLibrary' => $user->homeLibrary,
                'isAccessibleUser' => $user->isAccessibleUser,
                'item' => $cdlItem->serialize('borrow')
            ]
        );
    } elseif (count($results) > 1) {
        //this user has viewer access to more than one file, something is wrong
        if ($user->email != $config->driveOwner) {
            respondWithError(403, "Access Control Error");
            compliantBreachNotify("user $user->email has viewer access to more than one file", $user->email);
            die();
        }
    } else {
        respondWithData(null);
    }
}
?>