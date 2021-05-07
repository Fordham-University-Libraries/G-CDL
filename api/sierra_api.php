<?php
function getSierraBibByBibId($bibId, $library)
{
    if (!$bibId) respondWithError(400, 'No Bib/Item ID');
    global $config;
    $sierraToken = getSierraToken($library);
    $bibId = str_replace('b', '', $bibId);
    $curl = curl_init();
    $url = $config->libraries[$library]->ils['api']['base'] . "bibs/" . $bibId . '?fields=default%2CvarFields';
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
            "Authorization: Bearer $sierraToken"
        ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);
    $obj = json_decode($response);
    $bib = [
        'title' => $obj->title,
        'author' => $obj->author,
        'bibId' => $obj->id,
        'callNumber' =>$obj->callNumber,
        'published' => $obj->publishYear
    ];
    foreach($obj->varFields as $field) {
        if (isset($field->marcTag) && $field->marcTag == '020') {
            foreach($field->subfields as $subField) {
                if ($subField->tag == 'a') $bib['isbn'] = $subField->content;
            }
        } else if (isset($field->marcTag) && $field->marcTag == '250') {
            foreach($field->subfields as $subField) {
                if (!isset($bib['edition'])) $bib['edition'] = '';
                if ($subField->tag == 'a') $bib['edition'] .= $subField->content;
                if ($subField->tag == 'b') $bib['edition'] .= $subField->content;
            }
        } else if (isset($field->marcTag) && $field->marcTag == '264') {
            foreach($field->subfields as $subField) {
                if (!isset($bib['publisher'])) $bib['publisher'] = '';
                if ($subField->tag == 'a') $bib['publisher'] .= $subField->content;
                if ($subField->tag == 'b') $bib['publisher'] .= $subField->content;
            }
        } else if (isset($field->marcTag) && $field->marcTag == '300') {
            foreach($field->subfields as $subField) {
                if (!isset($bib['physDesc'])) $bib['physDesc'] = '';
                if ($subField->tag == 'a') $bib['physDesc'] .= $subField->content;
                if ($subField->tag == 'b') $bib['physDesc'] .= $subField->content;
                if ($subField->tag == 'c') $bib['physDesc'] .= $subField->content;
            }
        } else if (isset($field->marcTag) && $field->marcTag == '035') {
            foreach($field->subfields as $subField) {
                if ($subField->tag == 'a') $bib['oclc'] = preg_replace('/[^0-9]/', '', $subField->content);
            }
        }
    }
    return (object) $bib;
}

function getSierraBibByItemId($itemId, $library)
{
    $itemId = str_replace('i', '', $itemId);
    $item = getSierraItem($itemId, $library);
    if (!$item) {
        //try remove check digit
        if(substr($itemId, -1) == getSierraCheckDigit(substr($itemId, 0, -1))) {
            return getSierraBibByItemId(substr($itemId, 0, -1), $library);
        } else {
            respondWithError(404,'Item ID not found');
        }
    } else {
        $bibId = $item['bibIds'][0];
        $bib = getSierraBibByBibId($bibId, $library);
        return $bib;
    }
}

function getSierraItem($itemId, $library)
{
    global $config;
    $sierraToken = getSierraToken($library);
    $itemId = str_replace('i', '', $itemId);
    $curl = curl_init();
    $url = $config->libraries[$library]->ils['api']['base'] . "items/" . $itemId;
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
            "Authorization: Bearer $sierraToken"
        ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);
    $data = json_decode($response, true);
    if ($data['httpStatus'] == 404) {
        return false;
    }
    return $data;
}

function setSierraItemStatus($isBorrow, $itemId, $library)
{
    global $config;
    $sierraToken = getSierraToken($library);
    $itemId = str_replace('i', '', $itemId);
    if ($isBorrow) {
        $status = 'd';
    } else {
        //return
        $status = '-';
    }

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => $config->libraries[$library]->ils['api']['base'] . "items/$itemId",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "PUT",
        CURLOPT_POSTFIELDS =>"{\"status\": \"$status\"}",
        CURLOPT_HTTPHEADER => array(
            "Authorization: Bearer $sierraToken",
            "Content-Type: application/json",
        ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);
    return ['result' => $response];
}

//get all course
function getSierraCourses($library)
{
    global $config;
    $cacheSec = 86400; //1 day
    $fileName = Config::getLocalFilePath($library . '_' . $config->libraries[$library]->ils['api']['courseCacheFile']);
    if (file_exists($fileName) && time() - filemtime($fileName) < $cacheSec) {
        //use cache
        $file = file_get_contents($fileName);
        $data = unserialize($file);
        //$isCachedData = true;
    } else {
        $sierraToken = getSierraToken($library);
        $curl = curl_init();
        curl_setopt_array($curl, array(
        CURLOPT_URL => $config->libraries[$library]->ils['api']['base'] . "courses/?limit=1000",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => array(
            "Authorization: Bearer $sierraToken"
        ),
    ));
        $response = curl_exec($curl);
        curl_close($curl);
        $data = json_decode($response, true);
        $file = fopen($fileName, 'wb');
        try {
            fwrite($file, serialize($data));
            fclose($file);
        } catch (Exception $e) {
            logError($e);
            respondWithError(500, 'Internal Error');
        }
    }
    return $data;
}

function getSierraToken($library)
{
    global $config;
    
    $fileName = Config::getLocalFilePath($library . $config->libraries[$library]->ils['api']['tokenFile']);
    if (file_exists($fileName)) {
        $file = file_get_contents($fileName);
        $sierraTokenInfo = unserialize($file);
        $expires = $sierraTokenInfo['expires'];
        if (time() < $expires) {
            return $sierraTokenInfo['access_token'];
            //echo "still good. use cached token.\n";
            //echo time() - $expires;
        } else {
            //echo "cached token expired, grabbign new one";
            return getNewSierraToken($library);
        }
    } else {
        //echo "no cache, getting new one";
        return getNewSierraToken($library);
    }
    
}

function getNewSierraToken($library)
{
    global $config;
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => $config->libraries[$library]->ils['api']['base'] . "token",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_HTTPHEADER => array(
            "Authorization: Basic " . $config->libraries[$library]->ils['api']['key']
        ),
    ));

    $response = curl_exec($curl);
    $sierraToken = json_decode($response, true);
    $sierraToken['expires'] = time() + $sierraToken['expires_in'] - 60; //minus one minute
    $file = fopen(Config::getLocalFilePath($library . $config->libraries[$library]->ils['api']['tokenFile']), 'wb');
    try {
        fwrite($file, serialize($sierraToken));
        fclose($file);
    } catch (Exception $e) {
        logError($e);
        respondWithError(500, 'Internal Error');
    }

    curl_close($curl);
    return $sierraToken['access_token'];
}

function getSierraRawByUrl($library, $apiUrl)
{
    global $config;
    $sierraToken = getSierraToken($library);
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => array(
            "Authorization: Bearer $sierraToken"
        ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);
    $obj = json_decode($response);
    return $obj;
}

function getSierraCheckDigit($recordNumber)
{
    $m = 2;
    $x = 0;
    $i = (int) $recordNumber;
    while ($i > 0)
    {
        $a = $i % 10;
        $i = floor($i / 10);
        $x += $a * $m;
        $m += 1;
    }
    $r = $x % 11;
    return $r === 10 ? 'x' : $r;
}
