<?php
function getFilesForViewer()
{
    global $service;
    global $config;
    global $user;

    if ($user->isDriveOwner) {
        respondWithError(500, 'user is a owner of the drive');
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

    if (count($results) == 1) {
        $files = $results->getFiles();
        $cdlItem = new CdlItem($files[0]);
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
            compliantBreachNotify("user $user->email has viewer access to more than one file", $user->email);
            respondWithError(403, "Access Control Error");
        }
    } else {
        respondWithData(null);
    }
}
?>