<?php

function getAlmaApiClient(string $library): GuzzleHttp\Client
{
    global $config;
    if ($config->libraries[$library]->ils['api']['base']) {
        $url = rtrim($config->libraries[$library]->ils['api']['base'], '/');
    } else {
        $url = 'https://api-na.hosted.exlibrisgroup.com/almaws/v1';
    }
    $apiKey = $config->libraries[$library]->ils['api']['key'];
    $client = new GuzzleHttp\Client([
        'base_uri' => $url . '/',
        'headers' => [
            'Accept' => 'application/json',
            'Authorization' => "apikey $apiKey"
        ]
    ]);
    return $client;
}

function getAlmaBibByBibId(string $bibId, string $library, bool $includesHoldings = false): object
{
    if (!$bibId) respondWithError(400, 'No Bib/Item ID');
    $client = getAlmaApiClient($library);
    $params = 'bibs/' . $bibId . '/holdings';
    try {
        $response = $client->request('GET', $params);
    } catch (GuzzleHttp\Exception\ClientException $e) {
        if ($e->hasResponse()) {
            $eRes = $e->getResponse();
            $eBody = json_decode($eRes->getBody(), true);
            respondWithError($eRes->getStatusCode(), $eBody['errorList']['error'][0]['errorMessage']);
        }
    }

    $obj = json_decode($response->getBody());
    $bib = [
        'title' => $obj->bib_data->title,
        'author' => $obj->bib_data->author,
        'bibId' => $obj->bib_data->mms_id,
        'callNumber' => $obj->holding[0]->call_number,
        'published' => $obj->bib_data->date_of_publication,
        'publisher' => $obj->bib_data->publisher,
        'isbn' => $obj->bib_data->isbn
    ];
    if ($includesHoldings) {
        $holdings = [];
        foreach ($obj->holding as $holding) {
            $holdings[] = [
                'id' => $holding->holding_id,
                'callNumber' => $holding->call_number,
                'library' => $holding->library->desc,
                'location' => $holding->location->desc
            ];
        }
        $bib['holdings'] = $holdings;
    }

    return (object) $bib;
}

//by get item by bercode (NOT pid) -- https://developers.exlibrisgroup.com/alma/apis/docs/bibs/R0VUIC9hbG1hd3MvdjEvYmlicy97bW1zX2lkfS9ob2xkaW5ncy97aG9sZGluZ19pZH0vaXRlbXMve2l0ZW1fcGlkfQ==/
function getAlmaBibByBarcode(string $barcode, string $library): object
{
    $client = getAlmaApiClient($library);
    $params = 'items/?item_barcode=' . $barcode;
    try {
        $response = $client->request('GET', $params);
    } catch (GuzzleHttp\Exception\ClientException $e) {
        if ($e->hasResponse()) {
            $eRes = $e->getResponse();
            $eBody = json_decode($eRes->getBody(), true);
            respondWithError($eRes->getStatusCode(), $eBody['errorList']['error'][0]['errorMessage']);
        }
    }

    if ($response->getStatusCode() == 302) {
        $redirectedUrl = $response->getHeader('Location')[0];
        preg_match('/\/bibs\/(\d+)\//', $redirectedUrl, $matches);
        if (count($matches) > 1 && isset($matches[1])) {
            $bibId = $matches[1];
            return getAlmaBibByBibId($bibId, $library);
        } else {
            respondWithError(404, 'not found');
        }
    } else if ($response->getStatusCode() == 200) {
        $data = json_decode($response->getBody());
        $bibId = $data->bib_data->mms_id;
        return getAlmaBibByBibId($bibId, $library);
    }
}

function getAlmaHoldingsByBibIds(array $bibIds, string $library): array
{
    if (!count($bibIds)) respondWithFatalError(400, 'No Bib IDs');
    if (count($bibIds) > 100) respondWithFatalError(400, 'Max at 100 IDs please!');

    $client = getAlmaApiClient($library);
    $params = 'bibs?mms_id=' . implode(',', $bibIds) . '&expand=p_avail';
    try {
        $response = $client->request('GET', $params);
    } catch (GuzzleHttp\Exception\ClientException $e) {
        if ($e->hasResponse()) {
            $eRes = $e->getResponse();
            $eBody = json_decode($eRes->getBody(), true);
            respondWithError($eRes->getStatusCode(), $eBody['errorList']['error'][0]['errorMessage']);
        }
    }

    $obj = json_decode($response->getBody());
    $codes = [
        '0' => 'pid',
        '8' => 'barcode',
        'a' => 'institution',
        'b' => 'library',
        'c' => 'locationName',
        'd' => 'callNumber',
        'e' => 'status',
        'j' => 'location',
        'q' => 'libraryName'
    ];
    $response = [];
    $i = 0;
    foreach ($obj->bib as $bib) {
        $response[$i] = [
            'title' => $bib->title,
            'author' => $bib->author,
            'bibId' => $bib->mms_id,
            'published' => $bib->date_of_publication,
            'publisher' => $bib->publisher_const,
            'isbn' => $bib->isbn
        ];

        $xml_string = $bib->anies[0];
        $encoding = mb_detect_encoding($xml_string, 'UTF-16,UTF-8');
        if ($encoding != 'UTF-16') $xml_string = str_replace('UTF-16', $encoding, $xml_string);
        $xml = simplexml_load_string($xml_string);
        if ($xml) {
            $availabilities = $xml->xpath('datafield[@tag="AVA"]/subfield');
            $holdings = [];

            foreach ($availabilities as $subfield) {
                $code = (string) $subfield['code'];
                if (isset($codes[$code])) {
                    $holdings[$codes[$code]] = (string) $subfield;
                }
            }
            $response[$i]['callNumber'] = $holdings['callNumber'];
            $response[$i]['library'] = $holdings['libraryName'];
            $response[$i]['location'] = $holdings['locationName'];
        }

        $i++;
    }

    return $response;
}

function getAlmaItemByBarcode(string $barcode, string $library)
{
    $client = getAlmaApiClient($library);
    $params = 'items/?item_barcode=' . $barcode;
    try {
        $response = $client->request('GET', $params);
    } catch (GuzzleHttp\Exception\ClientException $e) {
        if ($e->hasResponse()) {
            $eRes = $e->getResponse();
            $eBody = json_decode($eRes->getBody(), true);
            respondWithError($eRes->getStatusCode(), $eBody['errorList']['error'][0]['errorMessage']);
        }
    }

    $data = json_decode($response->getBody());
    return $data;
}

//get all course or search, will cache if gets all
function getAlmaCourses(string $library, string $field = "courseName", string $term = null): array
{
    global $config;
    $cacheSec = 86400; //1 day
    $fileName = Config::getLocalFilePath($library . '_' . $config->libraries[$library]->ils['api']['courseCacheFile']);
    if (!$term && file_exists($fileName) && time() - filemtime($fileName) < $cacheSec) {
        //browse all -- use cache
        $file = file_get_contents($fileName);
        $courses = unserialize($file);
        //$isCachedData = true;
    } else {
        $client = getAlmaApiClient($library);
        $courseStatus = "ACTIVE"; //['ALL','ACTIVE','INACTIVE']
        $offSet = 0;
        $totalRecords = 0;
        if ($term) {
            if ($field == 'courseName') $field = 'name';
            else if ($field == 'courseNumber') $field = 'code';
            else if ($field == 'courseProf') $field = 'instructors';
        }

        $i = 0;
        $courses = [];
        while (true) {
            $params = "courses?limit=99999999&offset=$offSet&status=$courseStatus&order_by=code%2Csection&direction=ASC&exact_search=false";
            if ($term) {
                $q = "$field~$term";
                $params .= "&q=$q";
            }
            try {
                $response = $client->request('GET', $params);
            } catch (GuzzleHttp\Exception\ClientException $e) {
                if ($e->hasResponse()) {
                    $eRes = $e->getResponse();
                    $eBody = json_decode($eRes->getBody(), true);
                    respondWithError($eRes->getStatusCode(), $eBody['errorList']['error'][0]['errorMessage']);
                }
            }

            $data = json_decode($response->getBody(), true);
            $totalRecords = $data['total_record_count'];
            if ($totalRecords) {
                $courses = array_merge($courses, $data['course']);
                if (!$totalRecords || $totalRecords <= 100) {
                    break;
                } else if ($totalRecords - $offSet > 100) {
                    $offSet += 100;
                } else {
                    break;
                }
            } else {
                break;
            }

            if ($i++ > 100) break;
        }

        if (!$term && $courses) {
            try {
                $file = fopen($fileName, 'wb');
                fwrite($file, serialize($courses));
                fclose($file);
            } catch (Exception $e) {
                logError($e);
            }
        }
    }

    return $courses ?? [];
}

function getAlmaCourseIdFromCourseNumber(string $library, string $courseNumber): ?string
{
    $courses = getAlmaCourses($library);
    foreach ($courses as $course) {
        if ($course['code'] == $courseNumber) return $course['id'];
    }
    return null;
}

function getAlmaCourseInstructors(string $library, string $courseId): array
{
    $courses = getAlmaCourses($library);
    foreach ($courses as $course) {
        if ($course['id'] == $courseId) return $course['instructor'];
    }
    return [];
}

//get course's readingLists
function getAlmaCourseReservesInfo(string $library, ?string $courseNumber, string $courseId = null): array
{
    $client = getAlmaApiClient($library);
    if (!$courseId && !$courseNumber) respondWithFatalError(400);
    if (!$courseId && $courseNumber) $courseId = getAlmaCourseIdFromCourseNumber($library, $courseNumber);
    if (!$courseId) respondWithError(404, 'Course Not Found');

    $params = "courses/$courseId/reading-lists";
    try {
        $response = $client->request('GET', $params);
    } catch (GuzzleHttp\Exception\ClientException $e) {
        if ($e->hasResponse()) {
            $eRes = $e->getResponse();
            $eBody = json_decode($eRes->getBody(), true);
            respondWithError($eRes->getStatusCode(), $eBody['errorList']['error'][0]['errorMessage']);
        }
    }

    $data = json_decode($response->getBody(), true);
    $readingLists = $data['reading_list'];
    if ($readingLists) {
        $cdlReadingList = [];
        foreach ($readingLists as $list) {
            $cdlReadingList[] = [
                'id' => $courseId . "!!" . $list['id'],
                'prof' => $list['name'] //sigh... TODO: normalize all the things...
            ];
        }

        $instructors = getAlmaCourseInstructors($library, $courseId);
        $profs = [];
        foreach ($instructors as $instructor) {
            $profs[] = $instructor['last_name'] . ', ' . $instructor['first_name'];
        }

        return [
            'courseName' => $readingLists[0]['name'],
            'courseNumber' => $readingLists[0]['id'], //sigh... TODO: normalize all the things...
            'courseCode' => $readingLists[0]['code'],
            'courseProfs' => $profs,
            'sections' => $cdlReadingList
        ];
    } else {
        return [];
    }
}

function getAlmaCitations(string $library, string $courseId, string $listId): object
{
    $client = getAlmaApiClient($library);
    $params = "courses/$courseId/reading-lists/$listId/citations";
    try {
        $response = $client->request('GET', $params);
    } catch (GuzzleHttp\Exception\ClientException $e) {
        if ($e->hasResponse()) {
            $eRes = $e->getResponse();
            $eBody = json_decode($eRes->getBody(), true);
            respondWithError($eRes->getStatusCode(), $eBody['errorList']['error'][0]['errorMessage']);
        }
    }


    $data = json_decode($response->getBody(), true);
    $citations = $data['citation'];

    $items = [];
    $mmsIdsToLookUp = [];
    foreach ($citations as $citation) {
        //only return completed stuff from ILS (e.g. books) -- has MMS_ID
        if ($citation['metadata']['mms_id'] && $citation['status']['value'] == 'Complete') {
            $mmsIdsToLookUp[] = $citation['metadata']['mms_id'];
        }
    }
    if (count($mmsIdsToLookUp)) {
        //TODO: umm... shouldn't this be handled by the function itself?
        while ($itemsLeft = count($mmsIdsToLookUp)) {        
            $spliceSize = ($itemsLeft < 100) ? $itemsLeft : 100;
            $mmsIds = array_splice($mmsIdsToLookUp, 0, $spliceSize);
            $holdings = getAlmaHoldingsByBibIds($mmsIds, $library);
            if ($holdings) $items = array_merge($items, $holdings);
        }
    }

    return (object) $items;
}
