<?php
function logStats(CdlItem $item, $action, $usersLibrary = '', $isAccessibleUser = '', $isStaff = '')
{
    global $sheetService;
    global $config;

    $statsSheetId = $config->libraries[$item->library]->statsSheetId;
    if(!$statsSheetId) {
        respondWithError(500, 'No Stats Sheet Configured');
    }

    $range = 'Sheet1!A1:H';

    //append
    $valueInputOption = 'RAW'; //['USER_ENTERED', 'RAW']
    $values = [
        [
            time(),$item->itemId,$action,$item->library,$usersLibrary,$isAccessibleUser,$isStaff, isset($item->part) ? $item->part : ''
        ]
    ];
    $body = new Google_Service_Sheets_ValueRange([
        'values' => $values
    ]);
    $params = [
        'valueInputOption' => $valueInputOption
    ];
    try {
        $sheetService->spreadsheets_values->append($statsSheetId, $range, $body, $params);
    } catch (Google_Service_Exception $e) {
        logError($e->getMessage());
    }
}

function getStats($libKey, $from = null, $to = null)
{
    global $sheetService;
    global $config;
    global $user;
    if (!count($user->isStaffOfLibraries) || !in_array($libKey,$user->isStaffOfLibraries)) {
        respondWithError(401, 'Not Authorized - Get Stats');
    }

    if(!$config->libraries[$libKey]->statsSheetId) {
        respondWithError(500, 'No Stats Sheet Configured');
    }

    //if no Titles sheet, create one
    $sSheet = $sheetService->spreadsheets->get($config->libraries[$libKey]->statsSheetId);
    foreach ($sSheet->sheets as $sheet) {
        if ($sheet->properties->title == "Titles") { 
            $titlesSheet = $sheet;
            break;
        }
    }    
    if (!$titlesSheet) {
        $body = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
            'requests' => [
                'addSheet' => [
                    'properties' => [
                        'title' => 'Titles'
                    ]
                ]
            ]
        ]);
        $result = $sheetService->spreadsheets->batchUpdate($config->libraries[$libKey]->statsSheetId,$body);
    }

    //get stats data
    $fileName = Config::getLocalFilePath($libKey . '_stats_cache.php.serialized');
    if (file_exists($fileName) && time() - filemtime($fileName) < 1 * 3600) { //1 hour
        // use cache
        $file = file_get_contents($fileName);
        $values = unserialize($file);
    } else {
        $range = 'Sheet1!A1:H';
        $response = $sheetService->spreadsheets_values->get($config->libraries[$libKey]->statsSheetId, $range);
        $values = $response->getValues();
        if (!empty($values)) {
            $file = fopen($fileName, 'wb');
            try {
                fwrite($file, serialize($values));
                fclose($file);
            } catch (Exception $e) {
                logError($e);
                respondWithError(500, 'Error: cannot save cache stats file');
            }
        }
    }

    //process
    if (empty($values)) {
        respondWithError(404, 'No Stats Data!');
    } else {
        $data = [];
        $totalBorrow = 0;
        $totalManualReturn = 0;
        foreach ($values as $row) {
            $library = $row[3];
            if ( $library != $libKey) continue;
            if (isset($from) && strlen($from) && $from != 'null') {
                if ($row[0] < $from) continue;
            }
            if (isset($to) && strlen($to) && $to != 'null') {
                if ($row[0] > $to) continue;
            }
            if ($row[2] == 'borrow') {
                $totalBorrow++;
                $data[$libKey][$row[1]]['borrow'] = isset($data[$libKey][$row[1]]['borrow']) ? $data[$libKey][$row[1]]['borrow'] + 1 : 1 ;
                $data[$libKey][$row[1]]['last_borrow_tstamp'] = (int) $row[0];
                
            } else if($row[2] == 'manual_return') {
                $data[$libKey][$row[1]]['manual_return'] = isset($data[$libKey][$row[1]]['manual_return']) ? $data[$libKey][$row[1]]['manual_return'] + 1 : 1 ;
                if (!isset($data[$libKey][$row[1]]['manual_return_seconds_after_borrow']) && isset($data[$libKey][$row[1]]['last_borrow_tstamp'])) {
                    $data[$libKey][$row[1]]['manual_return_seconds_after_borrow'] =  $row[0] - $data[$libKey][$row[1]]['last_borrow_tstamp'];
                } else {
                    if (isset($data[$libKey][$row[1]]['last_borrow_tstamp'])) {
                        $data[$libKey][$row[1]]['manual_return_seconds_after_borrow'] =  ($row[0] - $data[$libKey][$row[1]]['last_borrow_tstamp']) + $data[$libKey][$row[1]]['manual_return_seconds_after_borrow'];
                    }
                }
                $totalManualReturn++;
            }
        }

        //sigh....
        if (!isset($data[$libKey])){
            respondWithError(404, 'No Stats Data for this Library!');
        }

        foreach ($data[$libKey] as $key => $val) {
            //if there's return entry but no borrow count, just skip it
            if (!$data[$libKey][$key]['borrow']) {
                continue;
            }
            $data[$libKey][$key]['itemId'] = $key;
            $data[$libKey][$key]['title'] = getTitleByItemId($key, $libKey);
            $manualReturn = $data[$libKey][$key]['manual_return'] ?? 0;
            $data[$libKey][$key]['auto_return'] = $data[$libKey][$key]['borrow'] - $manualReturn;
            if (isset($data[$libKey][$key]['manual_return_seconds_after_borrow'])) {
                $data[$libKey][$key]['avg_manual_return_seconds'] = $data[$libKey][$key]['manual_return_seconds_after_borrow'] / $data[$libKey][$key]['manual_return'];
                unset($data[$libKey][$key]['manual_return_seconds_after_borrow']);
            }
        }
        
        $counts = [];
        if (isset($data[$libKey])) {
            foreach ($data[$libKey] as $item) {
                array_push($counts, $item);
            }
        }

        respondWithData([
            $library => $counts,
            'totalBorrow' => $totalBorrow,
            'totalManualReturn' => $totalManualReturn,
        ]);
    }
}

function getTitleByItemId($itemId, $libKey) {
    global $config;
    global $sheetService;

    //get titles data
    $fileName = Config::getLocalFilePath($libKey . '_stats_titles_cache.php.serialized');
    if (file_exists($fileName) && time() - filemtime($fileName) < 1 * 3600) { //1 hour
        // use cache
        $file = file_get_contents($fileName);
        $titles = unserialize($file);
    } else {
        //get from Sheet
        $range = 'Titles!A1:B';
        $response = $sheetService->spreadsheets_values->get($config->libraries[$libKey]->statsSheetId, $range);
        $values = $response->getValues();
        if ($values) {
            $titles = [];
            foreach ($values as $value) {
                $titles[$value[0]] = $value[1];
            }
            $file = fopen($fileName, 'wb');
            try {
                fwrite($file, serialize($titles));
                fclose($file);
            } catch (Exception $e) {
                logError($e);
                respondWithError(500, 'Error: cannot save cached titles data');
            }
        }
    }

    //if there's a title cached
    if (isset($titles[$itemId])) {
        return $titles[$itemId];
    } else {
        //get from Sheet and cache title data
        global $service;
        $folderId = $config->libraries[$libKey]->noOcrFolderId;
        $q = "appProperties has {key='itemId' and value='$itemId'} AND '$folderId' in parents AND mimeType != 'application/vnd.google-apps.folder'";
        $optParams = [
            'q' => $q,
            'pageSize' => 100,
            'fields' => $config->fields,
            'orderBy' => $config->orderBy
        ];
        $results = $service->files->listFiles($optParams);
        foreach ($results->getFiles() as $file) {
            $cdlItem = new CdlItem($file);
            //save to Titles sheet
            $range = 'Titles!A1:B';
            $valueInputOption = 'RAW'; //['USER_ENTERED', 'RAW']
            $values = [[$cdlItem->itemId,$cdlItem->title]];
            $body = new Google_Service_Sheets_ValueRange(['values' => $values]);
            $params = ['valueInputOption' => $valueInputOption];
            try {
                $sheetService->spreadsheets_values->append($config->libraries[$libKey]->statsSheetId, $range, $body, $params);
            } catch (Google_Service_Exception $e) {
                logError($e->getMessage());
            }
            return $cdlItem->title;
        }

    }
}
