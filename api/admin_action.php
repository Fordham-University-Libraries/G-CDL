<?php
function getItemsAdmin($libKey)
{
    global $config;
    global $user;
    require_once 'search_action.php';
    $items = search(null, null, $libKey, true, true);
    if (!in_array($libKey, $user->isStaffOfLibraries)) {
        respondWithError(401, 'Not Authorized');
        die();
    }
    $results = [];
    foreach ($items as $item) {
        array_push($results, $item->serialize('admin'));
    }
    respondWithData([
        'library' => $libKey,
        'results' => $results,
        'admins' => $config->libraries[$libKey]->admins,
        'staff' => $config->libraries[$libKey]->staff,
        'configs' => [ 
           'borrowingPeriod' => $config->libraries[$libKey]->borrowingPeriod,
           'backToBackBorrowCoolDown' => $config->libraries[$libKey]->backToBackBorrowCoolDown,
        ]
    ]);
}

function getItemEditAdmin($fileId)
{
    global $service;
    global $config;
    global $user;

    try {
        $driveFile = $service->files->get($fileId, ['fields' => '*']);
    } catch (Google_Service_Exception $e) {
        $errMsg = json_decode($e->getMessage());
        respondWithFatalError(500, $errMsg);
    }
    $cdlItem = new CdlItem($driveFile);
    if (!in_array($cdlItem->library, $user->isStaffOfLibraries)) {
        respondWithError(401, 'Not Authorized');
        die();
    }
    respondWithData($cdlItem->serialize('admin'));
}

function postItemEditAdmin($fileId, $partDesc, $part = NULL, $partTotal = NULL)
{
    global $service;
    global $config;
    global $user;

    $driveFile = $service->files->get($fileId, ['fields' => '*']);
    $cdlItem = new CdlItem($driveFile);
    if (!in_array($cdlItem->library, $user->isStaffOfLibraries)) {
        respondWithError(401, 'Not Authorized');
        die();
    }
    
    $tempFile = new Google_Service_Drive_DriveFile;
    $tempFile->setAppProperties([
        'partDesc' =>  $partDesc,
        'part' => $part,
        'partTotal' => $partTotal
    ]);
    try {
        $service->files->update($fileId, $tempFile);
        respondWithData(['success' => true]);
    } catch (Google_Service_Exception $e) {
        respondWithError(500, "Internal Error");
    }
}

function suspendItem($fileId, $suspend = true)
{
    global $service;
    global $config;
    global $user;

    if (!count($user->isStaffOfLibraries)) {
        respondWithError(401, 'Not Authorized');
        die();
    }
    $optParams = array('fields' => $config->fieldsGet);
    try {
        $driveFile = $service->files->get($fileId, $optParams);
    } catch (Google_Service_Exception $e) {
        $errMsg = json_decode($e->getMessage());
        respondWithFatalError(500, $errMsg);
    }
    $cdlItem = new CdlItem($driveFile);
    if (!in_array($cdlItem->library, $user->isStaffOfLibraries)) {
        respondWithError(401, 'Not Authorized');
        die();
    }
    $cdlItem->suspend($suspend);
}

function trashItem($fileId)
{
    global $service;
    global $config;
    global $user;

    if (!count($user->isStaffOfLibraries)) {
        respondWithError(401, 'Not Authorized');
        die();
    }
    
    $optParams = array('fields' => $config->fieldsGet);
    try {
        $driveFile = $service->files->get($fileId, $optParams);
    } catch (Google_Service_Exception $e) {
        $errMsg = json_decode($e->getMessage());
        respondWithFatalError(500, $errMsg);
    }
    $cdlItem = new CdlItem($driveFile);
    if (!in_array($cdlItem->library, $user->isStaffOfLibraries)) {
        respondWithError(401, 'Not Authorized');
        die();
    }
    $cdlItem->trash();    
}

function downloadFileAdmin($fileId, $accessibleVersion = false)
{
    global $service;
    global $config;
    global $user;

    if (!count($user->isStaffOfLibraries)) {
        respondWithError(401, "Unauthorized");
        die();
    }
    try {
        $driveFile = $service->files->get($fileId, ['fields' => '*']);
    } catch (Google_Service_Exception $e) {
        $errMsg = json_decode($e->getMessage());
        respondWithFatalError(500, $errMsg);
    }
    $cdlItem = new CdlItem($driveFile);
    if (!in_array($cdlItem->library, $user->isStaffOfLibraries)) {
        respondWithError(401, 'Not Authorized');
        die();
    }

    $driveFile = $service->files->get($fileId, ['fields' => $config->fieldsGet]);
    $cdlItem = new CdlItem($driveFile);
    $cdlItem->adminDownload($accessibleVersion);
}

function getAccessibleUsers(string $libKey = null, bool $internal = false)
{
    global $sheetService;
    global $config;
    global $user;

    //accessible users sheet is shared by all libraries
    if (!count($user->isStaffOfLibraries)) {
        respondWithError(401, 'Not Authorized');
        die();
    }

    if ($libKey && !in_array($libKey, $user->isStaffOfLibraries)) {
        respondWithError(401, 'Not Authorized');
        die();
    }
    $fileName = $config->privateDataDirPath . $config->accessibleUserCachefileName;
    $range = 'Sheet1!A1:A';
    try {
        $response = $sheetService->spreadsheets_values->get($config->accessibleUsersSheetId, $range);
    } catch (Google_Service_Exception $e) {
        $errMsg = json_decode($e->getMessage());
        respondWithFatalError(500, $errMsg);
    }
    $values = $response->getValues();
    $accessibleUsers = [];
    if (!empty($values)) {
        foreach ($values as $row) {
            array_push($accessibleUsers, $row[0]);    
        }
        $file = fopen($fileName, 'wb');
        fwrite($file, serialize($accessibleUsers));
        fclose($file);
    }
    if (!$internal) {
        respondWithData($accessibleUsers);
    } else {
        return $accessibleUsers;
    }
}

function updateConfigAdmin($properties, $kind, $libKey = null)
{
    global $service;
    global $config;
    global $user;

    if (!count($user->isAdminOfLibraries)) {
        respondWithError(401, 'Not Authorized');
        die();
    }

    $data = json_decode($properties, true);
    if ($kind == 'library') {
        if (!$libKey) die('must provide which library');
        if (!in_array($libKey, $user->isAdminOfLibraries)) {
            respondWithError(401, 'Not Authorized to edit config of this library');
            die();
        }
        //$response = ['update library: ' . $libKey];
        $response = $config->updatePropsDeep($data, $libKey);
    } else {
        if (!$user->isSuperAdmin) {
            respondWithError(401, 'Not Authorized to edit app global config');
            die();
        }
        $response = $config->updatePropsDeep($data);

    }

    respondWithData($response);  
}

function updateLangAdmin($properties, $libKey)
{   
    require_once('Lang.php');
    global $service;
    global $config;
    global $user;

    if (!in_array($libKey, $user->isAdminOfLibraries)) {
        respondWithError(401, 'Not Authorized');
        die();
    }

    $data = json_decode($properties, true);
    $langObj = new Lang();
    $response = $langObj->update($data, $libKey);
    respondWithData($response);  
}

function updateCustomizationAdmin($properties, $libKey)
{   
    require_once('Customization.php');
    global $service;
    global $config;
    global $user;

    if (!in_array($libKey, $user->isAdminOfLibraries)) {
        respondWithError(401, 'Not Authorized');
        die();
    }

    $data = json_decode($properties, true);
    $cust = new Customization();
    $response = $cust->update($data, $libKey);
    respondWithData($response);  
}

function addNewLibrary(string $libKey, string $libName)
{
    global $service;
    global $config;
    global $user;

    if (!$user->isSuperAdmin) {
        respondWithError(401, 'Not Authorized');
        die();
    }

    if (isset($config->libraries[$libKey])) {
        respondWithError(400, 'Duplicate Library Key');
        die();
    }

    $result = $config->createNewLibrary($libKey, $libName);
    $response = [
        'result' => $result
    ];

    respondWithData($response);  
}

function removeLibrary($libKey)
{
    global $service;
    global $config;
    global $user;

    if (!$user->isSuperAdmin) {
        respondWithError(401, 'Not Authorized');
        die();
    }

    if (!isset($config->libraries[$libKey])) {
        respondWithError(400, 'Library Key Not Exists');
        die();
    }

    $result = $config->removeLibrary($libKey);
    $response = [
        'result' => $result
    ];

    respondWithData($response);  
}
?>