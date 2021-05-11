<?php
function getAlmaApiUrl($library): string
{
    global $config;
    //https://api-na.hosted.exlibrisgroup.com/almaws/v1
    if ($config->libraries[$library]->ils['api']['base']) {
        $url = rtrim($config->libraries[$library]->ils['api']['base'], '/');
    } else {
        $url = 'https://api-na.hosted.exlibrisgroup.com/almaws/v1';
    }
    return $url;
}

function getAlmaApiKey($library): string
{
    global $config;
    return $config->libraries[$library]->ils['api']['key'];
}

function getAlmaBibByBibId($bibId, $library)
{
    global $config;
    if (!$bibId) respondWithError(400, 'No Bib/Item ID');
    $url = getAlmaApiUrl($library);
    $apiKey = getAlmaApiKey($library);
    $url .= '/bibs/' . $bibId . '/holdings?apikey=' . $apiKey;

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            'Accept: application/json'
        ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);
    $obj = json_decode($response);


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
function getAlmaBibByBarcode($barcode, $library)
{
    $url = getAlmaApiUrl($library);
    $apiKey = getAlmaApiKey($library);
    $url .= '/items/?item_barcode=' . $barcode . '&apikey=' . $apiKey;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    // Catch output (do NOT print!)
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    // Return follow location true
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
    curl_exec($ch);
    // Getinfo or redirected URL from effective URL
    $redirectedUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    preg_match('/\/bibs\/(\d+)\//', $redirectedUrl, $matches);
    if(count($matches) > 1 && isset($matches[1])) {
        $bibId = $matches[1];
        return getAlmaBibByBibId($bibId,$library);
    } else {
        respondWithError(404,'not found');
    }
}

function getAlmaItemByBarcode($barcode, $library)
{
    $url = getAlmaApiUrl($library);
    $apiKey = getAlmaApiKey($library);

    $ch = curl_init();
    $getByBarcodeUrl = $url . '/items/?item_barcode=' . $barcode . '&apikey=' . $apiKey;
    curl_setopt($ch, CURLOPT_URL, $getByBarcodeUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
    curl_exec($ch);
    $redirectedUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $i = 0;
    //sometime the api doesn't redirect
    while ($redirectedUrl == $getByBarcodeUrl) {
        curl_exec($ch);
        $redirectedUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        if ($i++ > 100) {
            sleep($i);
            curl_close($ch);
            respondWithFatalError(500,'Internal Error');
        }
    }
    curl_close($ch);

    if ($redirectedUrl) {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $redirectedUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json'
            ),
        ));
        
        $response = curl_exec($curl);
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $i = 0;
        //the guest sandbox api is garbage -- it just randomly 401 sometimes
        while ($statusCode == 401) {
            $response = curl_exec($curl);
            $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            if ($i++ > 100) {
                sleep($i);
                curl_close($ch);
                respondWithFatalError($statusCode,'cannot get item info');
            }
        }

        curl_close($curl);
        $data = json_decode($response);
        if (!$data) {
            echo $redirectedUrl;
            print_r($response);
        } else {
            return $data;
        }

    } else {
        respondWithError(404,'Not Found');
    }
}

//get all course
function getAlmaCourses($library, $field = "courseName", $term = null)
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
        $url = getAlmaApiUrl($library);
        $apiKey = getAlmaApiKey($library);
        $courseStatus = "ALL"; //['ALL','ACTIVE','INACTIVE'] 
        $url .= "/courses?limit=99999999&offset=0&status=$courseStatus&order_by=code%2Csection&direction=ASC&exact_search=false&apikey=$apiKey";
        if ($term) {
            if ($field == 'courseName') $field = 'name';
            else if ($field == 'courseNumber') $field = 'code';
            else if ($field == 'courseProf') $field = 'instructor';

            $q = "$field~$term";
            $url .= "&q=$q";
        }

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json'
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        $data = json_decode($response, true);
        $file = fopen($fileName, 'wb');
        if (!$term && $data) {
            try {
                fwrite($file, serialize($data));
                fclose($file);
            } catch (Exception $e) {
                logError($e);
            }
        }
    }

    return $data['course'];
}

function getAlmaCourseIdFromCourseNumber($library, $courseNumber) {
    $courses = getAlmaCourses($library);
    foreach ($courses as $course) {
        if ($course['code'] == $courseNumber) return $course['id'];
    }
}

//return readingList
function getAlmaCourseReservesInfo($library, $courseNumber) {
    $url = getAlmaApiUrl($library);
    $apiKey = getAlmaApiKey($library);
    $courseId = getAlmaCourseIdFromCourseNumber($library, $courseNumber);
    $url .= "/courses/$courseId/reading-lists?apikey=$apiKey";
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => array(
            'Accept: application/json'
        ),
    ));
    $response = curl_exec($curl);
    $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($statusCode != 200) {
        respondWithError($statusCode, "this guest sandox is garbage");
    }
    $data = json_decode($response, true);
    $readingLists = $data['reading_list'];
    if (isset($readingLists) && count($readingLists) == 1) {
        getAlmaCitations($library, $courseId, $readingLists[0]['id']);
    } else {

    }
    //respondWithData($readingLists);

}

function getAlmaCitations($library, $courseId, $listId): object {
    $url = getAlmaApiUrl($library);
    $apiKey = getAlmaApiKey($library);
    $url .= "/courses/$courseId/reading-lists/$listId/citations?apikey=$apiKey";
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 1,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => array(
            'Accept: application/json'
        ),
    ));

    $response = curl_exec($curl);
    $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($statusCode != 200) {
        respondWithError($statusCode, "this guest sandox is garbage");
    }

    $data = json_decode($response, true);
    $citations = $data['citation'];


    $items = [];
    foreach ($citations as $citation) {
        if ($citation['metadata']['mms_id']) {
            $items[] = $citation;
        }
    }
    return (object) $items;
}



