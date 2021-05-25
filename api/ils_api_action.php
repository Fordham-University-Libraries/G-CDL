<?php
require_once('./sirsi_api.php');
require_once('./sierra_api.php');
require_once('./alma_api.php');


function getIlsBibByBibId($libKey, $bibId)
{
    checkIlsApiSettings($libKey);
    global $config;
    if ($config->libraries[$libKey]->ils['api']['enable']) {
        if (strtolower($config->libraries[$libKey]->ils['kind']) == 'sierra') {
            return getSierraBibByBibId($bibId, $libKey);
        } elseif (strtolower($config->libraries[$libKey]->ils['kind']) == 'sirsi') {
            return getSirsiBib($bibId, $libKey, 'ckey');
        } elseif (strtolower($config->libraries[$libKey]->ils['kind']) == 'alma') {
            return getAlmaBibByBibId($bibId, $libKey);
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
        } elseif (strtolower($config->libraries[$libKey]->ils['kind']) == 'alma') {
            return getAlmaBibByBarcode($itemId, $libKey);
        }
    }
}

function getIlsLocationsDefinition($libKey)
{
    global $config;
    if (!$config->libraries[$libKey]->ils['api']['enable'] || strtolower($config->libraries[$libKey]->ils['kind']) != 'sirsi') {
        respondWithData([]);
    } else {
    $fileName = Config::getLocalFilePath($libKey . "_ils_locations.json"); //key: loc name
    if (file_exists($fileName)) {
        $file = file_get_contents($fileName);
        $locations = json_decode($file, true);
    } else {
        $locations = getSirsiLoations($libKey);
    }
        respondWithData($locations);
    }
}

function searchIlsCourseReserves(string $libKey, string $field = null, string $term = null)
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
                $profName = $prof['userDisplayName'];
                $prof['userDisplayName'] = str_replace(',', '', $prof['userDisplayName']);
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
                        'profName' => $profName,
                        'score' => $score,
                        'profPk' => $prof['userPrimaryKey'],
                        'itemsCount' => $prof['numReservesForCourse']
                    ] ;
                    array_push($matchedCourses, $cdlCourse);
                }
            }
        } else { //courseName || courseId
            if ($field == 'courseNumber') {
                //remove white space
                $term = str_replace(' ', '', $term);
            }
            $termStems = explode(' ', $term);
            $courses = getSirsiCourses($libKey);
            $conjuctions = ['for', 'and', 'nor', 'but', 'or', 'yet', 'so', 'a', 'an', 'the'];

            if ($courses) {
                foreach ($courses as $course) {
                    // if search by course ID, remove white space
                    $courseNameId = ($field == 'courseNumber') ? str_replace(' ', '', $course[$field]) : $course[$field];
                    
                    $score = 0.0;
                    if (stripos($courseNameId, $term) !== false) {
                        //term full match
                        $score += (!in_array($term, $conjuctions)) ? 2 : .25;
                    }
                    if (stripos($courseNameId, $term) === 0) {
                        //starts with
                        $score += 1.5;
                    }
                    foreach ($termStems as $stem) {
                        if (stripos($courseNameId, $stem) !== false) {
                            //any match
                            if (!in_array(strtolower($stem), $conjuctions)) {
                                $score += strlen($stem) * .1;
                            }
                        }
                    }
                    $courseStems = explode(' ', str_replace([':',',','?'], '', $courseNameId));
                    $intersect = [];
                    $intersect = array_intersect(array_map('strtolower', $courseStems), array_map('strtolower', $termStems));
                    //full word match
                    if ($intersect) { 
                        $count = 0;
                        foreach ($intersect as $word) {
                            if (!in_array($word, $conjuctions)) $count++;
                        }
                        $score += ($count);
                    }
                    
                    if ($score || !$term) {
                        $cdlCourse = [
                            'id' => $course['id'],
                            'courseName' => $course['courseName'],
                            'courseNumber' => $course['courseNumber'],
                            'score' => $score,
                            'count' => $count,
                            'itemsCount' => $course['numReservesForCourse']
                        ];
                        array_push($matchedCourses, $cdlCourse);
                    }
                }
            }
        }
        if (count($matchedCourses) > 1) {
            usort($matchedCourses, function ($a, $b) {
                return $b['score'] <=> $a['score'];
            });
        }
        if ($field == 'courseProf') {
            respondWithData(['professors' => $matchedCourses]);
        } else {
            respondWithData(['courses' => $matchedCourses]);
        }
    }  elseif (strtolower($config->libraries[$libKey]->ils['kind']) == 'alma') {
        // ALMA course API can actually search now
        $courses = getAlmaCourses($libKey, $field, $term);
        $cdlCourses = [];
        foreach($courses as $course) {
            $profs = [];
            foreach ($course['instructor'] as $instructor) {
                $profs[] = $instructor['last_name'] . ', ' . $instructor['first_name'];
            }
            $cdlCourse = [
                'id' => $course['id'],
                'courseName' => $course['name'],
                'courseNumber' => $course['code'],
                'professors' => $profs,
                'itemsCount' => 1
            ];
            array_push($cdlCourses, $cdlCourse);
        }
        respondWithData(['courses' => $cdlCourses]);
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
    } elseif (strtolower($config->libraries[$libKey]->ils['kind']) == 'alma') {
        respondWithData(getAlmaCourseReservesInfo($libKey, $courseNumber));
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
    } elseif (strtolower($config->libraries[$libKey]->ils['kind']) == 'alma') {
        $keys = explode('!!', $key);
        $citations = getAlmaCitations($libKey, $keys[0], $keys[1]);
        $course = getAlmaCourseReservesInfo($libKey, null, $keys[0]);
        $cdlCourse = [];
        $cdlCourse['id'] = $keys[0];
        $cdlCourse['courseName'] = $course['courseName'];
        $cdlCourse['courseNumber'] = $course['courseCode'];
        $cdlCourse['professors'] = $course['courseProfs'];
        $i = 0;
        foreach ($citations as $item) {
            $cdlCourse['items'][$i] = $item;
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
    if (!$config->libraries[$libKey]->ils['api']['base'] && strtolower($config->libraries[$libKey]->ils['kind']) != 'alma') {
        respondWithError(501, 'ILS API is not properly configured (no API base URL)');
        die();
    }

    if (!$config->libraries[$libKey]->ils['kind']) {
        respondWithError(501, 'ILS API is not properly configured (no type of ILS)');
        die();
    }

    if (strtolower($config->libraries[$libKey]->ils['kind']) == 'sirsi') {
        if (!$config->libraries[$libKey]->ils['api']['clientId'] || !$config->libraries[$libKey]->ils['api']['appId']) {
            respondWithError(501, 'ILS API is not properly configured (no credentials)');
            die();
        }
    } else if (strtolower($config->libraries[$libKey]->ils['kind']) == 'sierra') {
        if (!$config->libraries[$libKey]->ils['api']['key']) {
            respondWithError(501, 'ILS API is not properly configured (no credentials)');
            die();
        }
    } else if (strtolower($config->libraries[$libKey]->ils['kind']) == 'alma') {
        if (!$config->libraries[$libKey]->ils['api']['key']) {
            respondWithError(501, 'ILS API is not properly configured (no api key)');
            die();
        }
    }
}
