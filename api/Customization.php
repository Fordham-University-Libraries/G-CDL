<?php
class Customization
{
    private $csses = [];
    private $cssTempArr = [];
    private $cssStr = '';
    
    //custom for each library, saved in customization.json -- if set, will overide the defaults
    public $libraries;

    //this will used for the whole app
    public $appGlobal = [
        'externalCss' => '',
        'floatingButton' => [
            'enable' => false,
            'matIcon' => '',
            'text' => '',
            'url' => '',
            'position' => 'right',
            'css' => [
                'background-color' => '',
                'color' => '',
            ],
        ]
    ];

    private $_appGlobalDefinitions = [
        'externalCss' => ['A URL to a CSS file to load into the app (full or relative path)', 1],
        'floatingButton' => [
            'enable' => ['Display a CSS position: fixed button that will always be visible throughout the app, e.g. for Ask a Librarian button',1],
            'matIcon' => ['Materials Icon https://fonts.google.com/icons?selected=Material+Icons to display before button text e.g. live_help',1],
            'text' => ['Text to display on the button e.g. Ask a Librarian',1],
            'url' => ['URL of the page user will get sent to (open new tab) when button is clicked',1],
            'position' => ['where to display the button',1,['right','bottomRight']],
            'css' => [
                'background-color' => ['button background color',1],
                'color' => ['button text color',1],
            ],
        ]
    ];

    //this will be used for each library
    public $default = [
        'body' => [
            'css' => [
                'background-color' => '',
                'color' => '',
            ],
        ],
        'a' => [
            'css' => [
                'color' => '',
            ],
            ':visited' => [
                'css' => [
                    'color' => '',
                ]
            ],
            ':hover' => [
                'css' => [
                    'color' => '',
                ]
            ],
        ],
        'header' => [
            'first' => [
                'display' => true,
                'logo' => 'univ-logo.png',
                'logoAltText' => 'University Seal',
                'text' => 'CDL University',
                'link' => '',
                'css' => [
                    'background-color' => '',
                    'color' => ''
                ]
            ],
            'second' => [
                'display' => true,
                'logo' => '',
                'logoAltText' => '',
                'text' => '', //if blank will use app's name
                'link' => '',
                'css' => [
                    'background-color' => '',
                    'color' => '',
                ]
            ],
            'third' => [
                'display' => true,
                'css' => [
                    'background-color' => '',
                    'color' => '',
                ],
                'is-active' => [
                    'css' => [
                        'color' => '',
                    ],
                ],
                'user-button' => [
                    'css' => [
                        'background' => '',
                        'color' => '',
                    ],
                ],
            ]
        ],
        'bread' => [
            'libName' => 'Library',
            'libHomeLink' => '',
            'css' => [
                'background' => '',
            ],
            'active' => [
                'css' => [
                    'color' => '',
                ],
            ],
            'bread-link' => [
                'css' => [
                    'color' => '',
                ],
            ]
        ],
        'home' => [
            'showCurrentCheckoutSnippet' => 2, //0 = never, 1 = only when has checkout item, 2 = always
            'showAboutSnippet' => true,
            'showCourseSearchSnippet' => true,
            'css' => [
                'background-color' => '',
                'color' => '',
            ],
            'item-card' => [
                'css' => [
                    'background-color' => '',
                    'color' => '',
                ]
            ],
        ],
        'item' => [
            'syndeticClientId' => '',
            'catalogUrl' => '',
            'useIlsApiForMetadataEnhancement' => true,
            'showCurrentCheckoutSnippet' => 1,
            'showAboutSnippet' => false,
            'showCourseSearchSnippet' => false,
            'css' => [
                'background-color' => '',
                'color' => '',
            ],
            'copy-card' => [
                'css' => [
                    'background-color' => '',
                    'color' => '',
                ]
            ],
        ],
        'borrowing' => [ //currentCheckoutItem
            'css' => [
                'background-color' => '',
                'color' => '',
            ]
        ],
        'button-primary' => [
            'css' => [
                'background-color' => '',
                'color' => '',
            ]
        ],
        'reserves' => [
            'enable' => false,
            'catalogUrl' => '',
            'showSearchForEbooks' => false,
            'ilsEbookLocationName' => 'ONLINE',
            'showRequestButton' => false,
            'showRequestButtonOnlyTo' => [],
            'requestFormUrl' => '',
            'css' => [
                'background-color' => '',
                'color' => '',
            ]
        ],
    ];


    // private $_appGlobalDefaultDefinitions = [
    //     'externalCss' => ['URL of a CSS file to load', 1],
    // ];

    private $_definitions = [
        'body' => [
            'css' => [
                'background-color' => ['background color of the whole page', 1],
                'color' => ['text color', 1],
            ],
        ],
        'a' => [
            'css' => [
                'color' => ['link text color', 1],
            ],
            ':visited' => [
                'css' => [
                    'color' => ['visited link text color', 1],
                ]
            ],
            ':hover' => [
                'css' => [
                    'color' => ['link text color when hoved on', 1],
                ]
            ],
        ],
        'header' => [
            'first' => [
                'display' => ['should the first row of the header be displayed?', 1],
                'logo' => ['FULL URL of image filename of the logo to be display e.g. https://www.myuniv.edi/images/seal.png OR enter filename to use image store in Angular\'s /assets folder e.g. univ-logo.png', 1],
                'logoAltText' => ['alt text for the logo', 1],
                'text' => ['a text to be displayed after logo'],
                'link' => ['a URL, if set, the logo image and text will be a link'],
                'css' => [
                    'background-color' => ['background color', 1],
                    'color' => ['text color', 1]
                ]
            ],
            'second' => [
                'display' => ['should the second row of the header be displayed?', 1],
                'logo' => ['FULL URL of image filename of the logo to be display e.g. https://library.myuniv.edi/images/lib-logo.png OR enter filename to use image store in Angular\'s /assets folder e.g. lib-logo.png', 1],
                'logoAltText' => ['alt text for the logo', 1],
                'text' => ['a text to be displayed after logo, leave blank to use \'app name @library name\' (if has multiple libraries)'],
                'link' => ['a URL, if set, override the default link (home of CDL app for this library) with the link you provided'],
                'css' => [
                    'background-color' => ['background color', 1],
                    'color' => ['text color', 1],
                ]
            ],
            'third' => [
                'display' => ['should the third row of the header be displayed?', 1],
                'css' => [
                    'background-color' => ['background color', 1],
                    'color' => ['color of the icon menu', 1],
                ],
                'is-active' => [
                    'css' => [
                        'color' => ['color of the active icon menu',1],
                    ],
                ],
                'user-button' => [
                    'css' => [
                        'background' => ['background of the user menu',1],
                        'color' => ['text color of the user menu',1]
                    ],
                ],
            ]
        ],
        'bread' => [
            'libName' => ['name of the library. If this and the libHomeLink field below are set, show first bread crumb as a link back to the library', 1],
            'libHomeLink' => ['URL of the library website that the first crumb should link to', 1],
            'css' => [
                'background' => ['background of the breadcrumbs area',1],
            ],
            'active' => [
                'css' => [
                    'color' => ['text color of the active crumb (current page)',1]
                ],
            ],
            'bread-link' => [
                'css' => [
                    'color' => ['text color of the inactive crumbs (clickable)',1]
                ],
            ]
        ],
        'home' => [
            //options = [key, val]
            'showCurrentCheckoutSnippet' => ['should the current checked out items be displayed on the home page', 1, [[0,'never'], [1,'only when user has checkout item'], [2,'always']]],
            'showAboutSnippet' => ['should the about snippet be displayed on the home page', 1],
            'showCourseSearchSnippet' => ['should the course search snippet be displayed on the home page (if course reserve search is enabled)', 1],
            'css' => [
                'background-color' => ['background color', 1],
                'color' => ['text color', 1],
            ],
            'item-card' => [
                'css' => [
                    'background-color' => ['background color of each items shown on the homepage', 1],
                    'color' => ['text color', 1],
                ]
            ],
        ],
        'item' => [
            'syndeticClientId' => ['if you have subscriptio to Syndetics, put your client id here and the app will display book jacket cover on the item page', 1],
            'catalogUrl' => ['should the item page show a link back to the catalog? use the token {{$bibId}} OR {{$itemId}} to link to book/item level e.g. https://opac.univ.edu/item/{{$itemId}}', 1],
            'useIlsApiForMetadataEnhancement' => ['also call ILS API to get more metadata e.g. publisher, pubdate, call# (ILS API MUST BE ENABLED)', 1],
            'showCurrentCheckoutSnippet' => ['should the current checked out items be displayed on the item page', 1, [[0,'never'], [1,'only when user has checkout item'], [2,'always']]],
            'showAboutSnippet' => ['should the about snippet be displayed on the item page', 1],
            'showCourseSearchSnippet' => ['should the course search snippet be displayed on the item page (if course reserve search is enabled)', 1],
            'css' => [
                'background-color' => ['background color', 1],
                'color' => ['text color', 1],
            ],
            'copy-card' => [
                'css' => [
                    'background-color' => ['background color of each copy/part of the item', 1],
                    'color' => ['text color', 1],
                ]
            ],
        ],
        'borrowing' => [
            'css' => [
                'background-color' => ['background color of the Current Checkout Snippet', 1],
                'color' => ['text color', 1],
            ]
        ],
        'button-primary' => [
            'css' => [
                'background-color' => ['background color of the primary button e.g. "Borrow"', 1],
                'color' => ['text color of the primary button', 1],
            ]
        ],
        'reserves' => [
            'enable' => ['enable course reserve search functionalites? (ILS api must be setup)', 1],
            'catalogUrl' => ['show a link back to the catalog on course servers search results? use the token {{$bibId}} OR {{$itemId}} to link to book/item level e.g. https://opac.univ.edu/item/{{$bibId}}', 1],
            'showSearchForEbooks' => ['on course search results, show to link to try to search for ebook in your ILS if an item is not available as CDL?', 1],
            'ilsEbookLocationName' => ['location name(key) of ebooks in your ILS', 1],
            'showRequestButton' => ['show request button (open a link to a CDL request form) if an item is not available as CDL?', 1],
            'showRequestButtonOnlyTo' => ['if showRequestButton is enabled, only display it for these user types (separate mulitple with a comma, leave blank to show all). [isAccessibleUser, isFacStaff, isGradStudent]', -2],
            'requestFormUrl' => ['URL of the request form', 1],
            'css' => [
                'background-color' => ['background color', 1],
                'color' => ['text color', 1],
            ]
        ],
    ];

    private $sectionDefinitions = [
        'body' => 'Applies to every areas of this library',
        'a' => 'Links',
        'header' => 'Header has 3 rows. The 1st row is designed to hold a logo image. The 2nd for is designed to hold text of the application name. The 3rd row is the application navigation/menu',
        'bread' => 'Breadcrumbs',
        'home' => 'The homepage of the app',
        'item' => 'The item level view of the app',
        'borrowing' => 'The current item user is borrowing snippet',
        'item-card' => 'The item card on home/item page',
        'reserves' => 'The course reserves search section of the app',
    ];


    public function __construct($forceRefresh = false) {
        
        $fileName = Config::getLocalFilePath('customization_app.json');
        if (file_exists($fileName)) {
            $file = file_get_contents($fileName);
            $this->appGlobal = json_decode($file, true);
        }
        $fileName = Config::getLocalFilePath('customization.json');
        if (file_exists($fileName)) {
            $file = file_get_contents($fileName);
            $this->libraries = json_decode($file, true);
        }
    }

    public function serialize()
    {
        global $config;
        //remove custs for lib that doesn't exist
        foreach($config->libraries as $libKey => $library) {
            if (!isset($config->libraries[$libKey])) unset($this->libraries[$libKey]);
        }

        foreach ($config->libraries as $libKey => $library) {
            if (!isset($this->libraries[$libKey])) $this->libraries[$libKey] = [];
            $this->_serializeRecursive($this->default, $this->libraries[$libKey]);
        }

        return ['global' => $this->appGlobal, 'libraries' => $this->libraries];
    }

    private function _serializeRecursive(&$default, &$library)
    {
        foreach ($default as $dKey => $dValue) {
            //is group
            if (is_array($dValue) && $this->_has_string_keys($dValue)) {
                if (!isset($library[$dKey])) $library[$dKey] = [];
                $this->_serializeRecursive($dValue, $library[$dKey], true);
            } else {
                if (!isset($library[$dKey])) $library[$dKey] = $dValue;
            }
        }
    }

    public function generateCustomCss()
    {
        $this->serialize();
        if (isset($this->appGlobal['externalCss']) && $this->appGlobal['externalCss']) {
            $this->cssStr .= "@import url('" . $this->appGlobal['externalCss'] . "')\n";
        }
        $this->_extractCssRecursive($this->appGlobal, $this->csses);
        $this->_extractCssRecursive($this->libraries, $this->csses);
        $this->_genCssRecursive($this->csses, $this->cssTempArr);
        header("Content-type: text/css");
        echo $this->cssStr; 
    }

    private function _extractCssRecursive(&$data, &$target) {
        foreach ($data as $key => $value) {
            //is group
            if ($key != 'css' && is_array($value) && $this->_has_string_keys($value)) {
                $target[$key] = [];
                $this->_extractCssRecursive($value,$target[$key]);
            } else {
                //echo $key . "\n";
                if ($key == 'css') $target[$key] = $value;
            }
        }
    }

    private $_htmlTags = ['body','a','button',':visited', ':hover'];

    private function _genCssRecursive(&$data, &$tempArr, $level = 0) {
        foreach ($data as $key => $value) {
            //is group
            if ($key != 'css' && is_array($value) && $this->_has_string_keys($value)) {
                if (!in_array($key, $this->_htmlTags) && !strpos($key, ':') && $level) $key = "." . $key;
                $tempArr[$level]= $key;
                    while ($level < count($tempArr) - 1) {
                    array_pop($tempArr);
                }
                $this->_genCssRecursive($value, $tempArr, $level + 1);             
            } else {
                //echo $key . "\n";
                $hasVal = false;
                if ($key == 'css') {
                    $temp = '';
                    foreach ($value as $prop => $val) {
                        if ($val) {
                            $hasVal = true;
                            $temp .= $prop . ':' . $val . ' !IMPORTANT; ';
                        }
                    }
                    if ($hasVal) {
                        //sigh....
                        // echo implode(' ', $tempArr);
                        // die();
                        if (count($tempArr) == 1) {
                            $this->cssStr .= "#" . $tempArr[0];
                        } else if (count($tempArr) == 2 && $tempArr[1] == 'body') {
                            $this->cssStr .= "body.library-" . $tempArr[0] . " { $temp }";
                        } else {
                            $selectors = '.library-' . str_replace(' :', ':', implode(' ', $tempArr));
                        }
                        $this->cssStr .=  $selectors . ' { ' . $temp . " }\n";
                    }
                }
            }
        }
    }

    private $_keysOrder = ['key','value','type','isDefault','desc','editable','options'];
    private $_currentLibray;
    function getAdminCustomization() {
        global $config;
        global $user;
        if (!$user->isAdminOfLibraries || !count($user->isAdminOfLibraries)) {
            respondWithError(401, 'Not Authorized - View Customization');
        }

        $cust = [];
        $cust['keys'] = $this->_keysOrder;
        foreach ($this->appGlobal as $key=>$custs) {
            $cust['appGlobal'][$key] = $this->_createField($key, $this->appGlobal[$key], $this->appGlobal[$key], $this->_appGlobalDefinitions[$key]);
        }
        foreach ($config->libraries as $library) {
            if (!in_array($library->key, $user->isAdminOfLibraries)) continue; //skip it since only show library that user is admin
            $this->_currentLibray = $library->key;
            $cust['libraries'][$library->key]['global'] = $this->_createField('body');
            $cust['libraries'][$library->key]['a'] = $this->_createField('a');
            $cust['libraries'][$library->key]['header'] = $this->_createField('header');
            $cust['libraries'][$library->key]['bread'] = $this->_createField('bread');
            $cust['libraries'][$library->key]['home'] = $this->_createField('home');
            $cust['libraries'][$library->key]['item'] = $this->_createField('item');
            $cust['libraries'][$library->key]['borrowing'] = $this->_createField('borrowing');
            $cust['libraries'][$library->key]['reserves'] = $this->_createField('reserves');
        }
        $cust['sectionDefinitions'] = $this->sectionDefinitions;
        return $cust;
    }

    private function _createField($key, $value = null, &$valuesRef = null, &$definitions = null, &$library = null, $isRecursive = false)
    {
        if (!$valuesRef) $valuesRef = &$this->default[$key];
        if (!$definitions) $definitions = &$this->_definitions[$key];
        if (!$library && isset($this->libraries[$this->_currentLibray][$key])) $library = &$this->libraries[$this->_currentLibray][$key];

        if (is_array($valuesRef) && $this->_has_string_keys($valuesRef)) {
            foreach ($valuesRef as $subKey => $value) {
                $this->_createField($subKey, $value, $value, $definitions[$subKey], $library[$subKey], true);
            }
        } else {
            $xValue = isset($library) ? $library : $value;
            $isDefault = !isset($library);

            if ($xValue === false) {
                $type = 'boolean';
            } else {
                $type = gettype($xValue) ? gettype($xValue) : 'string';
            }
            //omg... need to refactor the refactor
            if ($definitions) {
                if (count($definitions) > 3) {
                    $definitions[array_search('key', $this->_keysOrder)] = $key;
                    $definitions[array_search('value', $this->_keysOrder)] = $xValue;
                    $definitions[array_search('type', $this->_keysOrder)] = $type;
                    $definitions[array_search('isDefault', $this->_keysOrder)] = $isDefault;
                } else {
                    array_unshift($definitions, $key, $xValue, $type, $isDefault);
                }
            } else {
               logError("no definition for customzation: $value");
            }
        }

        if (!$isRecursive) {
            if (is_array($valuesRef) && $this->_has_string_keys($valuesRef)) {
                return [$key, $this->_array_values_recursive($definitions), 'group'];
            } else {
                return $definitions;
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
        if ($libKey == 'appGlobal') {
            if (!$user->isSuperAdmin) {
                respondWithError(401, 'Not Authorized - Edit App Global Customization');
            }
            $result = $this->_update($data, $this->appGlobal);
            $fileName = Config::getLocalFilePath('customization_app.json');
            $file = fopen($fileName, 'wb');
            try {
                fwrite($file, json_encode($this->appGlobal));
                fclose($file);
            } catch (Exception $e) {
                logError($e);
                respondWithError(500, 'ERROR: cannot save appGlobal customization data');
            }
            return ['success' => true];
        } else {
            if (!in_array($libKey, $user->isAdminOfLibraries)) {
                respondWithError(401, 'Not Authorized - Edit Customization');
            }
            if (!isset($this->libraries[$libKey])) {
                $this->libraries[$libKey] = [];
            }
            $org = $this->libraries[$libKey];
            $result = $this->_update($data, $this->libraries[$libKey]);
            $fileName = Config::getLocalFilePath('customization.json');
            $file = fopen($fileName, 'wb');
            try {
                fwrite($file, json_encode($this->libraries));
                fclose($file);
            } catch (Exception $e) {
                logError($e);
                respondWithError(500, 'ERROR: cannot save customization data');
            }
            return [
            'libKey' => $libKey,
            'data' => $data,
            'org' => $org,
            'result' => $result
        ];
        }
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

    public function removeLibrary($libKey): bool {
        global $config;
        global $user;
        if(!$user->isSuperAdmin) respondWithFatalError(401, 'Unauthorized - Remove Library Customization');
        if(!$this->libraries[$libKey]) return false;

        unset($this->libraries[$libKey]);
        $fileName = Config::getLocalFilePath('customization.json');
        $file = fopen($fileName, 'wb');
        try {
            fwrite($file, json_encode($this->libraries));
            fclose($file);
        } catch (Exception $e) {
            logError($e);
            respondWithError(500, 'ERROR: cannot save customization data');
        }
        return true;
    }
}
