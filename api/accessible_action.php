<?php
//for upload function, use case = data is just random emails, call this to cehck if users exist in GSuites
function lookupUser(array $names)
{
    global $user;
    //accessible users sheet is shared by all libraries
    if (!count($user->isStaffOfLibraries)) {
        respondWithError(401, 'Not Authorized');
    }

    // Get the API client and construct the service object.
    $client = getClient();
    $peopleService = new Google_Service_PeopleService($client);
    $foundUsers = [];
    $notFoundNames = [];
    $multipleMatchesNames = [];
    $i = 0;
    foreach ($names as $name) {
        $optParam = [
            'query' => $name,
            'readMask' => 'emailAddresses',
            'sources' => 'DIRECTORY_SOURCE_TYPE_DOMAIN_PROFILE'
        ];
        $searchDirectoryPeopleResponse = $peopleService->people->searchDirectoryPeople($optParam);
        $resultCount = count($searchDirectoryPeopleResponse->people);
        if ($resultCount == 1) {
            $emails = $searchDirectoryPeopleResponse->people[0]->getEmailAddresses();
            $parts = explode("@", $emails[0]->getValue());
            $username = $parts[0];
            array_push($foundUsers, $username);
        } else {
            if ($resultCount > 1) {
                array_push($multipleMatchesNames, $name);
            } else {
                array_push($notFoundNames, $name);
            }
        }
        $i++;
        if ($i > 50) {
            break;
        }
        sleep(.25); //sec - rate limit
    }

    respondWithData([
        'foundUsers' => $foundUsers,
        'notFoundNames' => $notFoundNames,
        'multipleMatchesNames' => $multipleMatchesNames
    ]);

    // print_r($foundUsers);
    // print_r($notFoundNames);
    // print_r($multipleMatchesNames);
}

function addAccessibleUsers(array $userNames)
{
    require_once 'admin_action.php';
    global $sheetService;
    global $config;
    global $user;
    
    //accessible users sheet is shared by all libraries
    if (!count($user->isStaffOfLibraries)) {
        respondWithError(401, 'Not Authorized');
    }
    
    $currentUsers = getAccessibleUsers(null, true); //internal call
    $usersAdded = [];
    $usersAlreadyInSystem = [];    
    $range = 'Sheet1!A1:B';
    //append
    $valueInputOption = 'RAW'; //['USER_ENTERED', 'RAW']
    $values = [];
    foreach ($userNames as $userName) {
        if (!in_array($userName, $currentUsers)) {
            array_push($values, [$userName, 'Added ' . date('m/d/Y')]);
            array_push($usersAdded, $userName);
        } else {
            array_push($usersAlreadyInSystem, $userName);
        }
    }
    $body = new Google_Service_Sheets_ValueRange([
        'values' => $values
    ]);
    $params = [
        'valueInputOption' => $valueInputOption
    ];
    try {
        $sheetService->spreadsheets_values->append($config->accessibleUsersSheetId, $range, $body, $params);
        $fileName = Config::getLocalFilePath($config->accessibleUserCachefileName);
        if (file_exists($fileName)) unlink($fileName);
        respondWithData([
            'usersAdded' => $usersAdded,
            'usersNotAdded' => $usersAlreadyInSystem
        ]);
    } catch (Google_Service_Exception $e) {
        respondWithError(500, 'Internal Error - Adding Accessible User');
    }
}

function removeAccessibleUsers(array $userNames)
{
    require_once 'admin_action.php';
    global $sheetService;
    global $config;
    global $user;
    if (!count($user->isStaffOfLibraries)) {
        respondWithError(401, 'Not Authorized - Remove Accessible User');
    }

    $rowsIndexToDelete = [];
    $currentUsers = getAccessibleUsers(null, true); //internal call
    $usersRemoved = [];
    $usersNotRemoved = [];
    foreach ($userNames as $userName) {
        $key = array_search($userName, $currentUsers);
        if ($key || $key === 0) {
            array_push($rowsIndexToDelete, $key);
        } else {
            array_push($usersNotRemoved, $userName);
        }
    }

    if (count($rowsIndexToDelete)) {
        rsort($rowsIndexToDelete);
        foreach ($rowsIndexToDelete as $rowIndex) {
            $dimensionRange = new Google_Service_Sheets_DimensionRange();
            $dimensionRange->setDimension('ROWS');
            $dimensionRange->setSheetId(0);
            $dimensionRange->setStartIndex($rowIndex);
            $dimensionRange->setEndIndex($rowIndex + 1);
            $deleteDimensionRequest = new Google_Service_Sheets_DeleteDimensionRequest();
            $deleteDimensionRequest->setRange($dimensionRange);
            $sheetsRequest = new Google_Service_Sheets_Request();
            $sheetsRequest->setDeleteDimension($deleteDimensionRequest);
            $sheetsRequests[] = $sheetsRequest;
        }
        $batchUpdateRequest = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest();
        $batchUpdateRequest->setResponseIncludeGridData(true);
        $batchUpdateRequest->setRequests($sheetsRequests);

        try {
            $result = $sheetService->spreadsheets->batchUpdate($config->accessibleUsersSheetId, $batchUpdateRequest);
            $fileName = Config::getLocalFilePath($config->accessibleUserCachefileName);
            if (file_exists($fileName)) unlink($fileName);
            $usersRemoved = $userNames;
        } catch (Google_Service_Exception $e) {
            $usersNotRemoved = $userNames;
            $error = json_decode($e->getMessage());
        }
    } 

    respondWithData(['usersRemoved' => $usersRemoved, 'usersNotRemoved' => $usersNotRemoved]);
}
