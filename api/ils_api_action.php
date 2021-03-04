<?php
require_once('./sirsi_api.php');
require_once('./sierra_api.php');

function getIlsBibByBibId($libKey, $bibId)
{
    checkIlsApiSettings($libKey);
    global $config;
    if ($config->libraries[$libKey]->ils['api']['enable']) {
        if (strtolower($config->libraries[$libKey]->ils['kind']) == 'sierra') {
            return getSierraBibByBibId($bibId, $libKey);
        } elseif (strtolower($config->libraries[$libKey]->ils['kind']) == 'sirsi') {
            return getSirsiBib($bibId, $libKey, 'ckey');
        }
    }
}

function getIlsBibByItemId($libKey, $itemId)
{
    checkIlsApiSettings($libKey);
    global $config;
    if ($config->libraries[$libKey]->ils['api']['enable']) {
        if (strtolower($config->libraries[$libKey]->ils['kind']) == 'sierra') {
            return getSierraBibByItemId($itemId, $libKey);
        } elseif (strtolower($config->libraries[$libKey]->ils['kind']) == 'sirsi') {
            return getSirsiBib($itemId, $libKey, 'barcode');
        }
    }
}

function getIlsLocationsDefinition($libKey)
{
    global $config;
    $fileName = $config->privateDataDirPath . $libKey . "_ils_locations.json"; //key: loc name //mock for now
    if (file_exists($fileName)) {
        $file = file_get_contents($fileName);
        $locations = json_decode($file, true);
    }
    respondWithData($locations);
}

function searchIlsCourseReserves(string $libKey, ?string $field, string $term)
{
    checkIlsApiSettings($libKey);
    global $config;
    if (!$field) {
        $field = 'courseName';
    }
    $matchedCourses = [];
    if (strtolower($config->libraries[$libKey]->ils['kind']) == 'sierra') {
        $date = new DateTime();
        $nowStamp = $date->format('Y-m-d\TH:i:s\Z');
        $courses = getSierraCourses($libKey);
        foreach ($courses['entries'] as $course) {
            $isAMatch = false;
            if ($course['beginDate'] <= $nowStamp && $course['endDate'] >= $nowStamp) {
                if (!$term) {
                    $isAMatch = true;
                } else {
                    if ($field == 'courseName') {
                        foreach ($course['courseNames'] as $courseName) {
                            if (stripos($courseName, $term) !== false) {
                                $isAMatch = true;
                                continue;
                            }
                        }
                    } elseif ($field == 'courseProf') {
                        foreach ($course['professorInstructors'] as $professorInstructor) {
                            if (stripos($professorInstructor, $term) !== false) {
                                $isAMatch = true;
                                continue;
                            }
                        }
                    }
                }
            }
            if ($isAMatch) {
                $cdlCourseItems = [];
                foreach ($course['reserves'] as $item) {
                    array_push($cdlCourseItems, ['id' => $item['id']]);
                }
                $cdlCourse = [
                    'id' => $course['id'],
                    'courseName' => $course['courseNames'][0],
                    'professors' => $course['professorInstructors'],
                    'publicNotes' => $course['courseNotes'],
                    'items' => $cdlCourseItems
                ];
                array_push($matchedCourses, $cdlCourse);
            }
        }
        respondWithData(['courses' => $matchedCourses]);
    } elseif (strtolower($config->libraries[$libKey]->ils['kind']) == 'sirsi') {
        if (!$field) {
            $field = 'courseName';
        }

        $matchedCourses = [];
        if ($field == 'courseProf') {
            $profs = getSirsiCoursesProf($libKey);
            foreach ($profs as $prof) {
                $score = 0.0;
                if ($prof['numReservesForCourse'] > 0 && $prof['userDisplayName'] != "$<name_not_yet_supplied>") {
                    if (strtolower($prof['userDisplayName']) == strtolower($term)) $score += 1; //exact
                    if (stripos($prof['userDisplayName'], $term) === 0) $score += 0.50; //starts
                    if (stripos($prof['userDisplayName'], $term) !== false) $score += 0.25; //anywhere
                    $nameStems = explode(' ', $prof['userDisplayName']);
                    foreach($nameStems as $name) {
                        if (stripos($name, $term) === 0) $score += 0.75; //starts of word
                    }
                    $termStems = explode(' ', $term);
                    $intersect = array_intersect(array_map('strtolower', $nameStems), array_map('strtolower', $termStems));
                    if ($intersect) $score += count($intersect) * .5; //full word match
                }
                if ($score || !$term) {
                    $cdlCourse = [
                        'id' => $prof['uniqueID'],
                        'profName' => $prof['userDisplayName'],
                        'score' => $score,
                        'profPk' => $prof['userPrimaryKey'],
                        'itemsCount' => $prof['numReservesForCourse']
                    ] ;
                    array_push($matchedCourses, $cdlCourse);
                }
            }
        } else { //courseName || courseId
            $termStems = explode(' ', $term);
            $courses = getSirsiCourses($libKey);
            if ($courses) {
                foreach ($courses as $course) {
                    $score = 0.0;
                    if (stripos($course[$field], $term) !== false) {
                        //term full match
                        $score += 1;
                    }
                    if (stripos($course[$field], $term) === 0) {
                        //starts with
                        $score += .5;
                    }
                    foreach ($termStems as $stem) {
                        if (stripos($course[$field], $stem) !== false) {
                            //any match
                            $score += .25;
                        }
                    }
                    $courseStems = explode(' ', $course[$field]);
                    $intersect = array_intersect(array_map('strtolower', $courseStems), array_map('strtolower', $termStems));
                    //full word match
                    if ($intersect) $score += count($intersect) * .5;
                    
                    if ($score || !$term) {
                        $cdlCourse = [
                            'id' => $course['id'],
                            'courseName' => $course['courseName'],
                            'courseNumber' => $course['courseNumber'],
                            'score' => $score,
                            'itemsCount' => $course['numReservesForCourse']
                        ];
                        array_push($matchedCourses, $cdlCourse);
                    }
                }
            }
        }
        if (count($matchedCourses) > 1) {
            usort($matchedCourses, function ($a, $b) {
                return $a['score'] < $b['score'];
            });
        }
        if ($field == 'courseProf') {
            respondWithData(['professors' => $matchedCourses]);
        } else {
            respondWithData(['courses' => $matchedCourses]);
        }
    }
}


function getIlsCourseReservesByUser($userPk)
{
    checkIlsApiSettings($libKey);
    global $config;
    if (strtolower($config->libraries[$libKey]->ils['kind']) == 'sirsi') {
    } elseif (strtolower($config->libraries[$libKey]->ils['kind']) == 'sierra') {
        respondWithError(501, 'Not Implemented');
    }
}

function getIlsCourseReservesInfo($libKey, $courseNumber)
{
    checkIlsApiSettings($libKey);
    global $config;
    if (!$config->libraries[$libKey]->ils['api']['enable']) {
        respondWithError(501, 'ILS API is not enabled for this library');
        die();
    }

    if (strtolower($config->libraries[$libKey]->ils['kind']) == 'sirsi') {
        $courseInfo = getSirsiCourseReservesInfo($libKey, $courseNumber);
        $sections = [];
        foreach ($courseInfo['reserveInfo'] as $section) {
            array_push($sections, [
                'id' => $courseInfo['courseID'] . '!!' . $section['userPrimaryKey'],
                'prof' => $section['userDisplayName'],
                'profDept' => $section['userDepartment'],
                'desk' => $section['reserveDeskID']
            ]);
        }
        $cdlCourse = [
            'courseName' => $courseInfo['courseName'],
            'courseNumber' => $courseInfo['courseID'],
            'sections' => $sections
        ];
        respondWithData($cdlCourse);
    } else {
        respondWithError(501, 'Not Implemented');
    }
}

function getIlsFullCourseReserves(string $libKey, string $key)
{
    checkIlsApiSettings($libKey);
    global $config;
    if (!$config->libraries[$libKey]->ils['api']['enable']) {
        respondWithError(501, 'ILS API is not enabled for this library');
        die();
    }

    if (strtolower($config->libraries[$libKey]->ils['kind']) == 'sierra') {
        $courses = getSierraCourses($libKey);
        $cdlCourse = [];
        foreach ($courses['entries'] as $course) {
            if ($course['id'] == $key) {
                $cdlCourse['id'] = $course['id'];
                $cdlCourse['courseName'] = $course['courseNames'][0];
                //$cdlCourse['courseNumber'] = null;
                $cdlCourse['professors'] = $course['professorInstructors'];
                $cdlCourse['publicNotes'] = $course['courseNotes'];
                $cdlCourse['items'] = [];
                $i = 0;
                foreach ($course['reserves'] as $item) {
                    $itemRaw = getSierraRawByUrl($libKey, $item['id']);
                    $bib = getSierraBibByBibId($itemRaw->bibIds[0], $libKey);
                    $cdlCourse['items'][$i]['bibId'] = $bib->bibId;
                    $cdlCourse['items'][$i]['itemId'] = $itemRaw->id;
                    $cdlCourse['items'][$i]['title'] = $bib->title;
                    $cdlCourse['items'][$i]['author'] = $bib->author;
                    $cdlCourse['items'][$i]['location'] = $itemRaw->location->name;
                    $cdlCourse['items'][$i]['callNumber'] = $itemRaw->callNumber;
                    $cdlCourse['items'][$i]['status'] = $itemRaw->status->display;
                    $i++;
                }
                respondWithData($cdlCourse);
                die();
            }
        }
        respondWithError(404, 'Not Found');
    } elseif (strtolower($config->libraries[$libKey]->ils['kind']) == 'sirsi') {
        $courseFull = getSirsiCourseReservesFull($libKey, $key);
        $cdlCourse = [];
        $cdlCourse['id'] = $key;
        $cdlCourse['courseName'] = $courseFull->courseName;
        $cdlCourse['courseNumber'] = $courseFull->courseID;
        $cdlCourse['professors'][0] = $courseFull->userDisplayName;
        $cdlCourse['desk'] = $courseFull->reserveDesk;
        $cdlCourse['items'] = [];
        $i = 0;
        foreach ($courseFull->reserveInfo as $item) {
            $cdlCourse['items'][$i]['bibId'] = $item->catalogKey;
            $cdlCourse['items'][$i]['itemId'] = $item->itemID;
            $cdlCourse['items'][$i]['title'] = $item->title;
            $cdlCourse['items'][$i]['author'] = $item->author;
            $cdlCourse['items'][$i]['location'] = $item->reserveDeskID;
            $cdlCourse['items'][$i]['callNumber'] = $item->displayableCallNumber;
            $i++;
        }
        respondWithData($cdlCourse);
    }
}

function searchIlsForEbook($libKey, $title, $author)
{
    checkIlsApiSettings($libKey);
    global $config;
    if (strtolower($config->libraries[$libKey]->ils['kind']) == 'sierra') {
        // $ebook = searchSierraForEbook($libKey, $title, $author);
        // return $ebook;
    } elseif (strtolower($config->libraries[$libKey]->ils['kind']) == 'sirsi') {
        $respond = searchSirsiForEbook($libKey, $title, $author);
        if (isset($respond['totalHits']) && $respond['totalHits'] > 0) {
            $ebooks = [];
            foreach ($respond['HitlistTitleInfo'] as $ebook) {
                array_push($ebooks, [
                    'title' => $ebook['title'],
                    'author' => $ebook['author'],
                    'edition' => $ebook['edition'],
                    'date' => $ebook['yearOfPublication'],
                    'url' => $ebook['url'],
                ]);
            }
            return $ebooks;
        } else {
            return null;
        }
    }
}

function setIlsItemStatus($isBorrow, $itemId, $libKey)
{
    checkIlsApiSettings($libKey);
    global $config;
    if ($config->libraries[$libKey]->ils['api']['enable']) {
        if (strtolower($config->libraries[$libKey]->ils['kind']) == 'sierra') {
            $result = setSierraItemStatus($isBorrow, $itemId, $libKey);
            respondWithData($result);
        } elseif (strtolower($config->libraries[$libKey]->ils['kind']) == 'sirsi') {
            respondWithError(501, 'Not Implemented');
        }
    }
}

function checkIlsApiSettings(string $libKey)
{
    global $config;
    if (!$config->libraries[$libKey]->ils['api']['enable']) {
        respondWithError(501, 'ILS API is not enabled for this library');
        die();
    }
    if (!$config->libraries[$libKey]->ils['api']['base']) {
        respondWithError(501, 'ILS API is not properly configured (no API base URL)');
        die();
    }
    if (!$config->libraries[$libKey]->ils['kind']) {
        respondWithError(501, 'ILS API is not properly configured (no type of ILS)');
        die();
    }
    if ($config->libraries[$libKey]->ils['kind'] == 'sirsi') {
        if (!$config->libraries[$libKey]->ils['api']['clientId'] || !$config->libraries[$libKey]->ils['api']['appId']) {
            respondWithError(501, 'ILS API is not properly configured (no credentials)');
            die();
        }
    }
    if ($config->libraries[$libKey]->ils['kind'] == 'sierra') {
        if (!$config->libraries[$libKey]->ils['api']['key'] || !$config->libraries[$libKey]->ils['api']['secret']) {
            respondWithError(501, 'ILS API is not properly configured (no credentials)');
            die();
        }
    }
}
