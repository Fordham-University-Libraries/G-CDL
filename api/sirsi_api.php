<?php
function getSirsiBib($key, $library, $keyType = 'barcode')
{
    global $config;
    if ($keyType == 'barcode') {
        $url = $config->libraries[$library]->ils['api']['base'] . "/rest/standard/lookupTitleInfo?json=true&includeOPACInfo=true&includeItemInfo=true&includeItemCategory=true&itemID=$key";
    } else if ($keyType = 'ckey') {
        $url = $config->libraries[$library]->ils['api']['base'] . "/rest/standard/lookupTitleInfo?json=true&includeOPACInfo=true&includeItemInfo=true&includeItemCategory=true&titleID=$key";
    } else {
        respondWithError(400, 'only support barcode or ckey (Sirsi\' lingo for BibId)');
    }

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => array(
            "Content-Type: application/json",
            "Accept: application/json",
            "SD-Originating-App-Id: " . $config->libraries[$library]->ils['api']['appId'],
            "x-sirs-clientID: " . $config->libraries[$library]->ils['api']['clientId']
        ),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    $json = json_decode($response);
    if (isset($json->TitleInfo[0])) {
        $obj = $json->TitleInfo[0];
        $items = [];
        foreach ($obj->CallInfo as $library) {
            foreach ($library->ItemInfo as $item) {
                array_push($items, [
                    'library' => $library->libraryID,
                    'itemId' => $item->itemID,
                    'callNumber' => $library->callNumber,
                    'location' => $item->currentLocationID,
                    'type' => $item->itemTypeID
                ]);
            }
        }
        $bib = [
            'bibId' => $obj->titleID,
            'title' => $obj->title,
            'author' => $obj->author,
            'callNumber' => $obj->baseCallNumber,
            'publisher' => $obj->publisherName,
            'published' => $obj->yearOfPublication,
            'physDesc' => $obj->extent,
            'isbn' => implode(', ', $obj->ISBN),
            'oclc' => $obj->OCLCControlNumber,
            'items' => $items
        ];
    }
    return $bib;
    //return $json->TitleInfo[0];
}

//get all course
function getSirsiCourses($library)
{
    global $config;
    $cacheSec = $config->libraries[$library]->ils['api']['courseCacheFileRefreshMinutes'] * 60;
    $fileName = Config::getLocalFilePath($library . "_" . $config->libraries[$library]->ils['api']['courseCacheFile']);
    if (file_exists($fileName) && time() - filemtime($fileName) < $cacheSec) {
        //use cache
        $file = file_get_contents($fileName);
        $data = unserialize($file);
        //$isCachedData = true;
    } else {
        $url = $config->libraries[$library]->ils['api']['base'] . "/rest/reserve/browseReserve?json=true&browseType=COURSE_ID&hitsToDisplay=9999";
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Accept: application/json",
                "SD-Originating-App-Id: " . $config->libraries[$library]->ils['api']['appId'],
                "x-sirs-clientID: " . $config->libraries[$library]->ils['api']['clientId']
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        $data = json_decode($response, true);
        if ($data) {
            if (!isset($data['faultResponse'])) {
                $file = fopen($fileName, 'wb');
                try {
                    fwrite($file, serialize($data));
                    fclose($file);
                } catch (Exception $e) {
                    logError($e);
                    respondWithError(500, 'Internal Error');
                }
            } else {
                respondWithError(500, $data['faultResponse']['code']);
            }
        } else {
            return;
        }
    }
    $courses = [];
    foreach ($data['reserveInfo'] as $course) {

        array_push($courses, [
            'id' => $course['uniqueID'],
            'courseNumber' => $course['courseID'],
            'courseName' => $course['courseName'],
            'desk' => $course['reserveDeskID'],
            'term' => $course['courseTermID'],
            'numReservesForCourse' => $course['numReservesForCourse']
        ]);
    }
    return $courses;
}

//search course by professor's name
function getSirsiCoursesProf($library)
{
    global $config;
    $cacheSec = $config->libraries[$library]->ils['api']['courseCacheFileRefreshMinutes'] * 60;
    $fileName = Config::getLocalFilePath($library . "_prof_" . $config->libraries[$library]->ils['api']['courseCacheFile']);
    if (file_exists($fileName) && time() - filemtime($fileName) < $cacheSec) {
        //use cache
        $file = file_get_contents($fileName);
        $data = unserialize($file);
        //$isCachedData = true;
    } else {
        $url = $config->libraries[$library]->ils['api']['base'] . "/rest/reserve/browseReserve?json=true&browseType=USER_NAME&hitsToDisplay=9999";
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Accept: application/json",
                "SD-Originating-App-Id: " . $config->libraries[$library]->ils['api']['appId'],
                "x-sirs-clientID: " . $config->libraries[$library]->ils['api']['clientId']
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        $data = json_decode($response, true);
        if ($data) {
            if (!isset($data['faultResponse'])) {
                $file = fopen($fileName, 'wb');
                try {
                    fwrite($file, serialize($data));
                    fclose($file);
                } catch (Exception $e) {
                    logError($e);
                    respondWithError(500, 'Internal Error');
                }
            } else {
                respondWithError(500, $data['faultResponse']['code']);
            }
        } else {
            return;
        }
    }
    return $data['reserveInfo'];
}

//get all course by professor
function getAllCoursesByProfessor($library, $profPk)
{
    global $config;
    $url = $config->libraries[$library]->ils['api']['base'] . '/rest/reserve/listReserve?json=true&browseType=USER_NAME&userPrimaryKey=' . $profPk;
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => array(
            "Content-Type: application/json",
            "Accept: application/json",
            "SD-Originating-App-Id: " . $config->libraries[$library]->ils['api']['appId'],
            "x-sirs-clientID: " . $config->libraries[$library]->ils['api']['clientId']
        ),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    $data = json_decode($response, true);
    if ($data) {
        if (!isset($data['faultResponse'])) {
            $courses = [];
            foreach ($data['reserveInfo'] as $course) {
                array_push($courses, [
                    'id' => $course['courseID'] . '!!' . $profPk,
                    'courseNumber' => $course['courseID'],
                    'courseName' => $course['courseName'],
                    'desk' => $course['reserveDeskID'],
                    'term' => $course['courseTermID']
                ]);
            }
            $result = [
                'profName' => $data['userName'],
                'profPk' => $profPk,
                'courses' => $courses
            ];
            return $result;
        } else {
            respondWithError(500, $data['faultResponse']['code']);
        }
    } else {
        return;
    }
}

function getSirsiCourseReservesInfo($library, $courseNumber)
{
    global $config;
    $curl = curl_init();
    $url =  $config->libraries[$library]->ils['api']['base'] . "/rest/reserve/listReserve?json=true&browseType=COURSE_ID&courseID=" . curl_escape($curl, $courseNumber);
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => array(
            "Content-Type: application/json",
            "Accept: application/json",
            "SD-Originating-App-Id: " . $config->libraries[$library]->ils['api']['appId'],
            "x-sirs-clientID: " . $config->libraries[$library]->ils['api']['clientId']
        ),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    $data = json_decode($response, true);
    if ($data) {
        if (!isset($data['faultResponse'])) {
            return $data;
        } else {
            respondWithError(500, $data['faultResponse']['code']);
        }
    }
}

function getSirsiCourseReservesFull($library, $courseNameBangBangUserPk)
{
    global $config;
    $ids = explode('!!', $courseNameBangBangUserPk);
    $curl = curl_init();
    $url = $config->libraries[$library]->ils['api']['base'] . '/rest/reserve/lookupReserve?json=true&hitsToDisplay=1000&browseType=COURSE_NAME&courseID=' . curl_escape($curl, $ids[0]) . '&userPrimaryKey=' .  $ids[1];
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => array(
            "Content-Type: application/json",
            "Accept: application/json",
            "SD-Originating-App-Id: " . $config->libraries[$library]->ils['api']['appId'],
            "x-sirs-clientID: " . $config->libraries[$library]->ils['api']['clientId']
        ),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    $data = json_decode($response);
    if ($data) {
        if (!isset($data->faultResponse)) {
            return $data;
        } else {
            respondWithError(500, $data->faultResponse->code);
        }
    }
}

function searchSirsiForEbook($libKey, $title, $author)
{
    global $config;
    require 'Customization.php';
    $customization = new Customization();
    $customization = $customization->serialize();
    $curl = curl_init();
    $url =  $config->libraries[$libKey]->ils['api']['base'] . "/rest/standard/searchCatalog?json=true&includeAvailabilityInfo=true&libraryFilter=" . $customization['libraries'][$libKey]['reserves']['ilsEbookLocationName'] . "&term1=" . curl_escape($curl, $title) . "&searchType1=TITLE&operator1=AND&term2=" . curl_escape($curl, $author) . "&searchType2=AUTHOR";
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => array(
            "Content-Type: application/json",
            "Accept: application/json",
            "SD-Originating-App-Id: " . $config->libraries[$libKey]->ils['api']['appId'],
            "x-sirs-clientID: " . $config->libraries[$libKey]->ils['api']['clientId']
        ),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    $data = json_decode($response, true);
    if ($data) {
        if (!isset($data['faultResponse'])) {
            return $data;
        } else {
            respondWithError(500, $data['faultResponse']['code']);
        }
    } else {
        return;
    }
}

function getSirsiLoations($libKey)
{
    global $config;
    $curl = curl_init();
    $url =  $config->libraries[$libKey]->ils['api']['base'] . "/rest/admin/lookupLocationPolicyList";

    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Accept: application/json',
            "SD-Originating-App-Id: " . $config->libraries[$libKey]->ils['api']['appId'],
            "x-sirs-clientID: " . $config->libraries[$libKey]->ils['api']['clientId']
        ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);
    $rawLocations = json_decode($response, true);
    $locations = [];
    foreach($rawLocations['policyInfo'] as $location) {
        $locations[$location['policyID']] = [
            'id' => $location['policyNumber'],
            'key' => $location['policyID'],
            'name' => $location['policyDescription']   
        ];
    }
    return $locations;
}
