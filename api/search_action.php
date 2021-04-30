<?php
function search($field = 'title', $term = null, $library = null, $admin = null, $internal = false)
{
    global $service;
    global $config;
    global $user;
    $isCachedData = null;

    if ($admin) {
        if (!in_array($library, $user->isStaffOfLibraries)) {
            respondWithError(401, 'Not Authorized - Get Items Admin');
        }
    }

    if (!$library) $library = array_key_first($config->libraries);
    $folderId = $config->libraries[$library]->noOcrFolderId;
    if (!$folderId) {
        respondWithError(404, "unknown library $library");
    }
    $fileName = Config::getLocalFilePath($library . "_" . $config->allItemsCacheFileName);

    //get all items - also used by admin compo, will cache result if it's a normal search
    $cacheSec = 300; //normal search, 5 minutes
    if ($admin) $cacheSec = 2;
    if (!$admin && $internal) $cacheSec = 60;

    if (file_exists($fileName) && time() - filemtime($fileName) < $cacheSec) {
        // use cache
        $file = file_get_contents($fileName);
        $files = unserialize($file);
        $isCachedData = true;
    }  else {
        //grab new
        $isCachedData = false;
        $q = "'$folderId' in parents AND mimeType != 'application/vnd.google-apps.folder' AND trashed = false";
        $optParams = array(
            'pageSize' => 1000, //max at 1,000 -- DANGER, it WILL pagainate when more than 100 regardless
            'fields' => 'nextPageToken, files(id,name,description,mimeType,size,ownedByMe,parents,viewersCanCopyContent,copyRequiresWriterPermission,permissions,webViewLink,webContentLink,appProperties,createdTime)',
            'q' => $q,
            'orderBy' => $config->orderBy
        );
        $files = [];
        $i = 0;
        while (true) {
            //get all the files
            $results = retry(function () use ($service, $optParams) {
                return $service->files->listFiles($optParams);
            });
            foreach ($results->getFiles() as $file) {
                if (strpos($file->getMimeType(), 'vnd.google-apps') !== false) continue;
                $cdlItem = new CdlItem($file);
                array_push($files, $cdlItem);
            }
        
            //if has next page, keep getting it
            if ($results->getNextPageToken()) {
                $optParams['pageToken'] = $results->getNextPageToken();
            } else {
                //save it to cache file
                if (count($files)) {
                    $file = fopen($fileName, 'wb');
                    try {
                        fwrite($file, serialize($files));
                        fclose($file);
                    } catch (Exception $e) {
                        logError($e);
                        respondWithError(500, 'Internal Error');
                    }
                }
            
                break; //break out of the loop!
            }

            //just to be safe -- meet me at 1 infinite loop cupertino ca 95014
            if ($i++ > 1000) break;
        }
    }

    //then seach all the items
    $results = [];
    if ($term) {
        foreach ($files as $cdlItem) {
            if ($field == 'title' && stripos($cdlItem->title, $term) !== false) {
                if ($admin || !isset($cdlItem->isSuspended) || !$cdlItem->isSuspended) array_push($results, $cdlItem);
            } else if ($field == 'author' && stripos($cdlItem->author, $term) !== false) {
                if ($admin || !isset($cdlItem->isSuspended) || !$cdlItem->isSuspended) array_push($results, $cdlItem);
            } 
        }
    } else {
        foreach ($files as $cdlItem) {
            if ($admin || !isset($cdlItem->isSuspended) || !$cdlItem->isSuspended) array_push($results, $cdlItem);
        }
    }

    if ($internal) {
        return $results;
    } else {
         respondWithData(['field' => $field, 'term' => $term , 'library' => $library, 'isCachedData' => $isCachedData, 'results' => $results]);
    }
}

//for course reserve search
//@INPUT $bibIdsStr - string of BibIds separated by a comma (,)
function searchByBibIds($libKey, $bibIdsStr)
{
    $bibIds = array_unique(explode(",", $bibIdsStr));
    $allItems = search('title', null, $libKey, null, true); //internal call = true so it returns array
    $allBibIdsInSystem = [];
    foreach ($allItems as $item) {
        array_push($allBibIdsInSystem, $item->bibId);
    }

    $matchedBibIds = [];
    foreach ($bibIds as $bibId) {
        if (in_array($bibId, $allBibIdsInSystem)) {
            array_push($matchedBibIds, $bibId);
        }
    }
    
    respondWithData(['results' => array_unique($matchedBibIds)]);   
}

function checkMulti($library)
{
    $allItems = search('title', null, $library, null, true); //internal call
    $bibIdsCount = [];
    foreach ($allItems as $cdlItem) {
        if (isset($bibIdsCount[$cdlItem->bibId])) {
            $bibIdsCount[$cdlItem->bibId]++;
        } else {
            $bibIdsCount[$cdlItem->bibId] = 1;
        }
    }
    $result = [];
    foreach ($bibIdsCount as $bibId => $count) {        
        if ($count > 1) {
            $result[$bibId] = $count;
        }
    }
    respondWithData(['results' => $result]);
}

?>