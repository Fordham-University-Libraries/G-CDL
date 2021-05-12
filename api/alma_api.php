<?php

function getAlmaApiClient($library): GuzzleHttp\Client
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

function getAlmaBibByBibId($bibId, $library): object
{
    if (!$bibId) respondWithError(400, 'No Bib/Item ID');
    $client = getAlmaApiClient($library);
    $params = 'bibs/' . $bibId . '/holdings';
    $response = $client->request('GET', $params);

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

    return (object) $bib;
}

//by get item by bercode (NOT pid) -- https://developers.exlibrisgroup.com/alma/apis/docs/bibs/R0VUIC9hbG1hd3MvdjEvYmlicy97bW1zX2lkfS9ob2xkaW5ncy97aG9sZGluZ19pZH0vaXRlbXMve2l0ZW1fcGlkfQ==/
function getAlmaBibByBarcode($barcode, $library): object
{
    $client = getAlmaApiClient($library);
    $params = 'items/?item_barcode=' . $barcode;
    $response = $client->request('GET', $params);
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

function getAlmaItemByBarcode($barcode, $library)
{
    $client = getAlmaApiClient($library);
    $params = 'items/?item_barcode=' . $barcode;
    $response = $client->request('GET', $params);
    $data = json_decode($response->getBody());
    return $data;
}

//get all course
function getAlmaCourses($library, $field = "courseName", $term = null): array
{
    global $config;
    $cacheSec = 86400; //1 day
    $fileName = Config::getLocalFilePath($library . '_' . $config->libraries[$library]->ils['api']['courseCacheFile']);
    if (!$term && file_exists($fileName) && time() - filemtime($fileName) < $cacheSec) {
        //browse all -- use cache
        $file = file_get_contents($fileName);
        $data = unserialize($file);
        //$isCachedData = true;
    } else {
        $client = getAlmaApiClient($library);
        $courseStatus = "ALL"; //['ALL','ACTIVE','INACTIVE'] 
        $params = "courses?limit=99999999&offset=0&status=$courseStatus&order_by=code%2Csection&direction=ASC&exact_search=false";
        if ($term) {
            if ($field == 'courseName') $field = 'name';
            else if ($field == 'courseNumber') $field = 'code';
            else if ($field == 'courseProf') $field = 'instructors';

            $q = "$field~$term";
            $params .= "&q=$q";
        }
        $response = $client->request('GET', $params);
        $data = json_decode($response->getBody(), true);
        if (!$term && $data) {
            try {
                $file = fopen($fileName, 'wb');
                fwrite($file, serialize($data));
                fclose($file);
            } catch (Exception $e) {
                logError($e);
            }
        }
    }

    return $data['course'] ?? [];
}

function getAlmaCourseIdFromCourseNumber($library, $courseNumber): string
{
    $courses = getAlmaCourses($library);
    foreach ($courses as $course) {
        if ($course['code'] == $courseNumber) return $course['id'];
    }
}

function getAlmaCourseInstructors($library, $courseId): array
{
    $courses = getAlmaCourses($library);
    foreach ($courses as $course) {
        if ($course['id'] == $courseId) return $course['instructor'];
    }
}

//return readingList
function getAlmaCourseReservesInfo($library, $courseNumber, $courseId = null): array
{
    $client = getAlmaApiClient($library);
    if (!$courseId) $courseId = getAlmaCourseIdFromCourseNumber($library, $courseNumber);
    $params = "courses/$courseId/reading-lists";
    $response = $client->request('GET', $params);
    $data = json_decode($response->getBody(), true);
    $readingLists = $data['reading_list'];
    if ($readingLists) {
        $cdlReadingList = [];
        foreach ($readingLists as $list) {
            $cdlReadingList[] = [
                'id' => $courseId . "!!" . $list['id'],
                'prof' => $list['name'] //sigh...
            ];
        }

        $instructors = getAlmaCourseInstructors($library, $courseId);
        $profs = [];
        foreach ($instructors as $instructor) {
            $profs[] = $instructor['last_name'] . ', ' . $instructor['first_name'];
        }

        return [
            'courseName' => $readingLists[0]['name'],
            'courseNumber' => $readingLists[0]['id'], //sigh...
            'courseCode' => $readingLists[0]['code'],
            'courseProfs' => $profs,
            'sections' => $cdlReadingList
        ];
    } else {
        return [];
    }

}

function getAlmaCitations($library, $courseId, $listId): object
{
    $client = getAlmaApiClient($library);
    $params = "courses/$courseId/reading-lists/$listId/citations";
    $response = $client->request('GET', $params);


    $data = json_decode($response->getBody(), true);
    $citations = $data['citation'];

    $items = [];
    foreach ($citations as $citation) {
        //only return stuff from ILS (e.g. books) -- has MMS_ID
        if ($citation['metadata']['mms_id'] && $citation['metadata']['title']) {
            $items[] = $citation;
        }
    }
    return (object) $items;
}
