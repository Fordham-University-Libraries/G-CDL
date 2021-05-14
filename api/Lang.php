<?php
class Lang {
    //custom for each library, saved in lang.json -- if set, will overide the defaults
    public $libraries;

    public $default = [
        'home' => [
            'homeHead' => 'Home',
            'itemsHead' => 'Digital Reserves Items at {{$libraryName}}',
            'copy' => 'Copy',
            'multiParts' => 'multiple parts item',
            'part' => 'Part'
        ],
        'currentCheckout' => [
            'head' => 'Current Digital Reserves Checkout:',
            'noItem' => 'You do not have a Digital Reserves item currently checked out.',
            'itemHead' => 'You\'re borrowing one item:',
            'itemHeadThis' => 'You\'re borrowing THIS item:'
        ],
        'item' => [
            'itemHead' => 'Item View',
            'copies' => 'Copies',
            'copy' => 'Copy',
            'part' => 'Part'
        ],
        'reader' => [
            'readerHead' => 'Reading: {{$title}}',
            'dueBack' => 'Due back: {{$due}}',
            'itemHasBeenAutoReturned' => 'The book has been automatically returned.',
            'help' => [
                'helpHead' => 'Troubleshooting',
                'helpDesc' => 'Read me if the book is not displayed below',
                'helpText' => '<p>Try these steps:</p><ol><li><strong>Check Borwser Extensions</strong>: do you have any privacy/tracking related extensions such as <em>Privacy Badger</em>, <em>uBlock Origin</em>, and <em>Ghostery</em> installed? If so, try disable it or add this page to its "allowed list"</li></ol>',
                'openReaderInNewWindowText' => 'If none of the above works, try open the reader in a new window',
                'openReaderInNewWindowButtonText' => 'Open Reader in New Window'
            ]
        ],
        'reserves' => [
            'searchFields' => [
                'courseName' => [
                    'text' => 'Course Name',
                    'placeholder' => 'e.g. Introduction to Art History',
                    'hint' => ''
                ],
                'courseNumber' => [
                    'text' => 'Course Number',
                    'placeholder' => 'e.g. ENGL101',
                    'hint' => ''
                ],
                'courseProf' => [
                    'text' => 'Professor\'s Name',
                    'placeholder' => 'e.g. Hawking',
                    'hint' => 'You can enter only the beginning of the professor\'s last name and leave the rest. e.g. "Hawk" for professor "Hawking, Stephen William"'
                ]
            ],
            'head' => 'Search Digital Reserves Collection at {{$libraryName}}',
            'subtitle' => 'A virtual Reserves Desk containing digital copies of faculty requested items required
            for your courses.',
            'details' => 'Course Details',
            'course' => 'Course:',
            'prof' => 'Professor:',
            'items' => 'Items on Reserves for this Course',
            'courseHasNoItems' => 'This Course has no Items on Reserves',
            'availDigitalHead' => 'Available as Digital Reserves',
            'unavailDigital' => 'This course does not yet have any Digital Reserves copies.',
            'availPrintHead' => 'Available as Print Reserves',
            'availPrintSubhead' => 'Print Reserve items (physical copy) are not available for checkout for the Fall 2020 semester due to the pandemic.',
            'unavailPrint' => 'This course does not have any Print Reserves copies.',
            'snippetHead' => 'Course Reserves',
            'snippetDescription' => 'Search Course Reserves by cours name and etc.'
        ],
        'about' => [
            'snippetHead' => 'About Digtal Reserves',
            'snippetDescription' => 'e.g. borrowing rules and etc.',
            'aboutHead' => 'About'
        ],
        'upload' => [
            'helpText' => 'Help Text Here! If you are an admin of this library, edit me in the app\'s language config'
        ],
        'emails' => [
            //placeholder for item title = {{$title}}, return link = {{$returnLink}} read link = {{$readLink}}, borrow period = {{$borrowingPeriod}}
            'borrowSubject' => 'Digital Reserves Item Borrowed',
            'returnSubject' => 'Digital Reserves Item Returned',
            'borrowBody' => '<p>Thank you for using Libraries\' Digital Reserves service. Your item <strong><em>{{$title}}</em></strong> will be automatically returned in {{$borrowingPeriod}} hours. Click the link below to access the item:</p><p>{{$readLink}}</p> <p>If you\'re done with this item early, please consider returning it right away so other users can use it.</p><p>{{$returnLink}}</p>',
            'returnBody' => '<p>Your Libraries Digital Reserves item <strong><em>{{$title}}</em></strong> has been returned.</p> <p>Thank you for using Libraries\' Digital Reserves service.</p>',
        ],
        'error' => [
            'genericError' => 'ERROR: Oops! Something went wrong!',
            'unauthed' => 'ERROR: You\'re not authorized to access this page.',
            'unknownLibrary' => 'ERROR: Unknown Library',
            'disabled' => 'ERROR: This functionality is not avaible',
            'item' => [
                'notPartOfCollecton' => 'ERROR: This item is not part of Digital Reserves Collection',
                'notOwnedByMe' => 'ERROR: This item is NOT avaiable for borrowing'
            ],
            'reader' => [
                'noItemCheckedOut' => 'ERROR: you don\'t currently have any items checked out'
            ],
            'borrow' => [
                'unknownError' => 'ERROR: something went wrong! Please try again in a little bit, if problem persists, please contact library staff for assistance',
                'notAvailHaveOtherViewer' => 'ERROR: item has already been checked out. Try refreshing the page to get the most up-to-date data',
                'notAvailGeneric' => 'ERROR: This item is NOT avaiable for borrowing',
                //{{$backToBackBorrowCoolDown}} = minutes token
                'backToBack' => 'ERROR: You cannot re-borrow this item yet since you\'ve just returned it less than {{$backToBackBorrowCoolDown}} minutes ago. Please try again later!',
                'backToBackCopy' => 'ERROR: You cannot re-borrow this title yet since you\'ve just returned a copy of it less than {{$backToBackBorrowCoolDown}} minutes ago. Please try again later!'
            ],
            'return' => [
                'unknownError' => 'ERROR: something went wrong! Please try again in a little bit, if problem persists, please contact library staff for assistance',
                'userDoesNotHaveItemCheckedOut' => 'ERROR: User doesn\'t have the item checked out',
            ],
            'getItemsHome' => [
                'noItems' => 'ERROR: NO Digital Reserves Items at this Library (yet!)',
                'page' => 'ERROR: cannot get Digital Reserves Items',
                'snackBar' => 'ERROR: something went wrong! Please refresh the page to try again in a little bit'
            ],
            'accessible' => [
                'downloadNotFound' => 'ERROR: accessible version of the file is not available'
            ],
            '404' => '404 - PAGE NOT FOUND',
            'loggedOut' => 'You’ve been logged out of the application! To also log out of Google, visit <a href="https://accounts.google.com/logout">https://accounts.google.com/logout</a>'
        ],
    ];

    private $_definitions = [
        //[help text, editable status, options]
        //-2 = hide, -1 = read only, 1 = editable, 2 = use caution
        'home' => [
            'homeHead' => ['header (h1) of the home page',1, null],
            'itemsHead' => ['section header of the section that lists items at this library', 1, null],
            'copy' => ['what to called a "copy" of items with mutiliple copies',1, null],
            'multiParts' => ['what to called a "Multi Parts" items (item that was splitted e.g. by chapters)',1, null],
            'part' => ['what to called a "part" of "Multi Parts" items',1, null]
        ],
        'currentCheckout' => [
            'head' => ['the heading of the snippet that show an item currently checked out to a user',1, null],
            'noItem' => ['what to say when there\'s no item checked out',1, null],
            'itemHead' => ['what to say when the shippet is shown at item level view, if user is borrowing other item',1, null],
            'itemHeadThis' => ['what to say when the shippet is shown at item level view, if user is borrowing THIS item',1, null]
        ],
        'item' => [
            'itemHead' => ['header (h1) of the item page',1, null],
            'copies' => ['what to called a "copies" of items has mutiliple copies',1, null],
            'copy' => ['what to called a "copy" of items with mutiliple copies',1, null],
            'part' =>  ['what to called a "part" of "Multi Parts" items',1, null]
        ],
        'reader' => [
            'readerHead' => ['header (h1) of the item page, use the token {{$title}} for item\'s title',1, null],
            'dueBack' => ['Text that show when the item is due back, use the token {{$due}} for item\'s due date/time',1, null],
            'itemHasBeenAutoReturned' => ['text to show then the item has been automiatically return (will show this message inplace of the book reader)',1, null],
            'help' => [
                'helpHead' => ['title of the viewer troubshooting snippet',1, null],
                'helpDesc' => ['description of the viewer troubshooting snippet',1, null],
                'helpText' => ['help/troublshooting text', 1, 'htmlOk'],
                'openReaderInNewWindowText' => ['help to explain that uesr can try open the reader in a new window if it does not load. user the token {{$button}} to place the button within the help text',1, null],
                'openReaderInNewWindowButtonText' => ['Text of the button that allows user to open Google viewer directly on a new tab/window',1, null]
            ]
        ],
        'reserves' => [
            'searchFields' => [
                'courseName' => [
                    'text' => ['Text for search by course name',1, null],
                    'placeholder' => ['placeholder text (when search field is empty)',1, null],
                    'hint' => ['hint text under search field',1, null]
                ],
                'courseNumber' => [
                    'text' => ['Text for search by course id/number',1, null],
                    'placeholder' => ['placeholder text (when search field is empty)',1, null],
                    'hint' => ['hint text under search field',1, null]
                ],
                'courseProf' => [
                    'text' => ['Text for search by course\'s teacher/professor and etc.',1, null],
                    'placeholder' => ['placeholder text (when search field is empty)',1, null],
                    'hint' => ['hint text under search field',1, null]
                ]
            ],
            'head' => ['header (h1) of the reserves search page',1, null],
            'subtitle' => ['subtitle for the course reserves search page',1, null],
            'details' => ['heading of course details area',1, null],
            'course' => ['what to call "Course"',1, null],
            'prof' => ['what to call "Professor"',1, null],
            'items' => ['heading for items on reserve for the course',1, null],
            'courseHasNoItems' => ['display when there is no items on reserve for the course',1, null],
            'availDigitalHead' => ['heading for items available digitally in CDL app',1, null],
            'unavailDigital' => ['message to display when there\'s no CDL items for the course',1, null],
            'availPrintHead' => ['heading for items on reserves but NOT available digitally in CDL app',1, null],
            'availPrintSubhead' => ['sub-heading for items on reserves but NOT available digitally in CDL app',1, null],
            'unavailPrint' => ['message to display when there\'s no print reserve items for the course e.g. all reserves items are on CDL app',1, null],
            'snippetHead' => ['heading of the course reserves search snippet',1, null],
            'snippetDescription' => ['help text of the course reserves search snippet',1, null],
        ],
        'about' => [
            'snippetHead' => ['heading of the about info snippet',1, null],
            'snippetDescription' => ['help text of the about info snippet',1, null],
            'aboutHead' => ['header of the full about page', 1, null]
        ],
        'emails' => [
            //placeholder for item title = {{$title}}
            'borrowSubject' => ['subject for item borrow notification email -- token available: {{$title}}',1, null],
            'returnSubject' => ['subject for item return notification email -- token available: {{$title}}',1, null],
            'borrowBody' => ['body for item borrow notification email (HTML is ok!) -- tokens available: {{$title}}, {{$returnLink}}, {{$readLink}}, {{$borrowingPeriod}}',1, 'htmlOk'],
            'returnBody' => ['body for item return notification email (HTML is ok!) -- tokens available: {{$title}}, {{$returnLink}}, {{$readLink}}, {{$borrowingPeriod}}',1, 'htmlOk'],
        ],
        'upload' => [
            'helpText' => ['help text staff will see on the item (pdf) upload page -- token available: {{$linkToAdmin}}',1, 'htmlOk'],
        ],
        'error' => [
            'genericError' => ['generic error message for errors not defined below', 1, null],
            'unauthed' => ['error message to show when user is trying to access unauthorized area',1, null],
            'unknownLibrary' => ['error message to show when user try to access unknown library',1, null],
            'disabled' => ['error message to show when functionality is disabled',1, null],
            'item' => [
                'notPartOfCollecton' => ['error message to show when items cannot be found on CDL app',1, null],
                'notOwnedByMe' => ['error message to show when item is not owned by the drive owner',1, null]
            ],
            'reader' => [
                'noItemCheckedOut' => ['error message to show when user is on reader page but don\'t have any item checked out',1, null]
            ],
            'borrow' => [
                'unknownError' => ['error message to show when internal error occurs when borrowing',1, null],
                'notAvailHaveOtherViewer' => ['error message to show when try to borrow item that has been borrow by other user',1, null],
                'notAvailGeneric' => ['error message to show when user try to borrow item that is not available e.g. it is suspended',1, null],
                'backToBack' => ['error message to show when user try to borrow the same item they have just returned -- available token {{$backToBackBorrowCoolDown}}',1, null],
                'backToBackCopy' => ['error message to show when user try to borrow the same different copy of an item they have just returned -- available token {{$backToBackBorrowCoolDown}}',1, null],
            ],
            'return' => [
                'unknownError' => ['error message to show when internal error occurs when returning',1, null],
                'userDoesNotHaveItemCheckedOut' => ['error message to show when user try to return item that\'s not checked out the them',1, null],
            ],
            'getItemsHome' => [
                'noItems' => ['error message to show when there\'s no item in the system',1, null],
                'page' => ['error message to show when user try to load more items on home page',1, null],
                'snackBar' => ['error message to show when failing to load CDL item on home page',1, null]
            ],
            'accessible' => [
                'downloadNotFound' => ['error message to show when accessbile version of the PDf is not available',1, null]
            ],
            '404' => ['when the page reuqested does not exist',1, null],
            'loggedOut' => ['message to display when user click log out',1, null]
        ],
    ];

    private $sectionDefinitions = [
        'home' => 'Home page -- the section "home" is the landing page of the app, showing all CDL items in the system',
        'currentCheckout' => 'Current item user is borrowing snippets',
        'item' => 'Item level page',
        'reader' => 'Item reader page, where the Google viewer is embeded in this page',
        'reserves' => 'Course Reserves search page',
        'about' => 'About page',
        'emails' => 'texts for emails notfications',
        'upload' => 'the upload page for library staff',
        'error' => 'Error messages',
    ];

    private $globalLangToken = [
        '{{$libraryName}}' => 'this token will be replaced with the actual library name',
    ];

    public function __construct($forceRefresh = false) {
        global $config;
        $fileName = Config::getLocalFilePath('lang.json');
        if (file_exists($fileName)) {
            $file = file_get_contents($fileName);
            $this->libraries = json_decode($file, true);
        }
    }


    function serialize() {
        global $config;
        foreach($config->libraries as $libKey => $library) {
            $this->infiniteLoop = 0;
            $this->_serialize($this->default, $this->libraries[$libKey]);
            $this->libraries[$libKey]['about']['html'] = $this->getAbout($libKey);
            //replace token -- oh god PHP
            array_walk_recursive($this->libraries, function(&$value, $key, $library) {
                $value = str_replace('{{$libraryName}}', $library->name , $value);
            }, $library);
        }

        return ['libraries' => $this->libraries];
    }

    private function _serialize(&$default, &$libLang) {
        if ($this->infiniteLoop++ > 1000) respondWithFatalError(500, 'Error Processing Languages (infinit loop)');

        foreach ($default as $dKey => $dValue) {
            if (is_array($dValue) && $this->_has_string_keys($dValue)) {
                $this->_serialize($default[$dKey], $libLang[$dKey]);
            } else {
                if (!isset($libLang[$dKey])) {
                    $libLang[$dKey] = $dValue;
                }
            }
        }
    }

    private $_currentLibray;
    function getAdminLang() {
        global $config;
        global $user;
        if (!$user->isAdminOfLibraries || !count($user->isAdminOfLibraries)) {
            respondWithError(401, 'Not Authorized - Languages Admin');
        }

        $lang = [];
        $lang['keys'] = ['key','value','type','isDefault','desc','editable','options'];
        foreach ($config->libraries as $library) {
            if (!in_array($library->key, $user->isAdminOfLibraries)) continue; //skip it since only show library that user is admin
            $this->_currentLibray = $library->key;
            $lang['libraries'][$library->key]['home'] = $this->_createField('home');
            $lang['libraries'][$library->key]['item'] = $this->_createField('item');
            $lang['libraries'][$library->key]['currentCheckout'] = $this->_createField('currentCheckout');
            $lang['libraries'][$library->key]['reader'] = $this->_createField('reader');
            $lang['libraries'][$library->key]['about'] = $this->_createField('about');
            $lang['libraries'][$library->key]['reserves'] = $this->_createField('reserves');
            $lang['libraries'][$library->key]['emails'] = $this->_createField('emails');
            $lang['libraries'][$library->key]['upload'] = $this->_createField('upload');
            $lang['libraries'][$library->key]['error'] = $this->_createField('error');
            $lang['abouts'][$library->key] = $this->getAbout($library->key);
        }
        $lang['sectionDefinitions'] = $this->sectionDefinitions;
        $lang['globalLangToken'] = $this->globalLangToken;
        return $lang;
    }

    private $infiniteLoop;
    private function _createField($key, &$props = null, &$definitions = null, &$library = null, $isRecursive = false)
    {
        if (!$isRecursive) $this->infiniteLoop = 0;
        if ($this->infiniteLoop++ > 1000) respondWithFatalError(500, 'Error Processing Languages (infinit loop)');
        if (!$props) $props = &$this->default[$key];
        if (!$definitions) $definitions = &$this->_definitions[$key];
        if (!$library && isset($this->libraries[$this->_currentLibray][$key])) $library = &$this->libraries[$this->_currentLibray][$key];

        if ($props && is_array($props) && $this->_has_string_keys($props)) {
            foreach ($props as $subKey => $value) {
                $this->_createField($subKey, $value, $definitions[$subKey], $library[$subKey], true);
            }
        } else {
            $xValue = isset($library) ? $library : $props;
            $isDefault = !isset($library);
            //OMG! really? refactor me plz! - why don't do it the opposit way?
            if (count($definitions) <= 3) {
                array_unshift($definitions, $key, $xValue, gettype($xValue) != "NULL" ? gettype($xValue) : 'string', $isDefault);
            } else {
                $definitions[1] = $xValue;
            }
        }

        if (!$isRecursive) {
            if (is_array($props) && $this->_has_string_keys($props)) {
                return [$key, $this->_array_values_recursive($this->_definitions[$key]), 'group'];
            } else {
                return $this->_definitions[$key];
            }
        }
    }

    private function _array_values_recursive($arr)
    {
        $arr2=[];
        foreach ($arr as $key => $value) {
            if (is_array($value) && $this->_has_string_keys($value)) {
                $arr2[] = [$key, $this->_array_values_recursive($value), 'group'];
            } else {
                $arr2[] =  $value;
            }
        }
    
        return $arr2;
    }

    private function _has_string_keys(array $array)
    {
        return count(array_filter(array_keys($array), 'is_string')) > 0;
    }

    public function update($data, $libKey) {
        global $user;
        if (!in_array($libKey, $user->isAdminOfLibraries)) {
            respondWithError(401, 'Not Authorized - Edit Languages');
        }

        if (!isset($this->libraries[$libKey])) $this->libraries[$libKey] = [];
        $org = $this->libraries[$libKey];
        $result = $this->_update($data, $this->libraries[$libKey]);
        $fileName = Config::getLocalFilePath('lang.json');
        $file = fopen($fileName, 'wb');
        try {
            fwrite($file, json_encode($this->libraries));
            fclose($file);
        } catch (Exception $e) {
            logError($e);
            respondWithError(500, 'ERROR: cannot save Language data');
        }
        return [
            'libKey' => $libKey,
            'data' => $data,
            'org' => $org,
            'result' => $result
        ];
    }

    private function _update(&$input, &$target, $isRecursive = false) {
        if (is_array($input) && $this->_has_string_keys($input)) {
            foreach ($input as $key => $value) {
                if (!isset($target[$key])) $target[$key] = null;
                $this->_update($input[$key],$target[$key],true);
            }
        } else {
            $target = $input;
        }

        if (!$isRecursive) {
            return $target;
        }
    }

    function getAbout(string $libKey)
    {
        global $user;
        $fileName = Config::getLocalFilePath($libKey . '_about.html');
        if (file_exists($fileName)) {
            $html = file_get_contents($fileName);
        } else {
            $html = '<div>About Page</div>';
        }
        return $html;
    }

    function editAbout(string $libKey, string $html)
    {
        global $user;

        if (!in_array($libKey, $user->isAdminOfLibraries)) {
            respondWithError(401, 'Not Authorized - Edit About');
        }
        
        $fileName = Config::getLocalFilePath($libKey . '_about.html');
        $result = ['success' => false];
        try {
            $file = fopen($fileName, 'wb');
            fwrite($file, $html);
            fclose($file);
            $result['success'] = true;
        } catch (Exception $e) {
            logError($e);
            respondWithError(500, 'ERROR: cannot save about page data');
        }
        return $result;
    }

    public function removeLibrary($libKey): bool {
        global $user;
        if(!$user->isSuperAdmin) respondWithFatalError(401, 'Unauthorized - Remove Library Languages');
        if(!$this->libraries[$libKey]) return false;

        unset($this->libraries[$libKey]);
        $fileName = Config::getLocalFilePath('lang.json');
        $file = fopen($fileName, 'wb');
        try {
            fwrite($file, json_encode($this->libraries));
            fclose($file);
        } catch (Exception $e) {
            logError($e);
            respondWithError(500, 'ERROR: cannot save Laguage data');
        }

        $aboutFile = Config::getLocalFilePath($libKey . '_about.html');
        if (file_exists($aboutFile)) unlink($aboutFile);

        return true;
    }
}