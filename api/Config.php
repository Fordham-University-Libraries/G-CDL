<?php
require 'Library.php';
class Config
{
    public $isProd = false;
    public $mainFolderId; //also stored on G app's data config.json 
    public $accessibleUsersSheetId; //also stored on G app's data config.json 
    public $gSuitesDomain; //also stored on G app's data config.json 
    public $driveOwner; //also stored on G app's data config.json 
    public $timeZone = null; //https://www.php.net/manual/en/timezones.america.php
    public $appName = 'My Library CDL APP';
    public $appSuperAdmins = []; //can change everything and will get email when there's a problem
    public array $auth = [
        'kind' => 'GoogleOAuth', //only option at the moment (since users must login to their Google account to view the Gdrive items any way)
        'gSuitesDomain' => '',//without @ sign
        'sessionName' => 'CDL_APP_SESS',
        'sessionTtl' => 180, //minutes
        'clientDomain' => '',
        'clientPath' => '/',
        'clientSecure' => true,
        'clientHttpOnly' => true,
    ];
    public $googleTagManagerUA;
    public $libraries = [];
    public $maxFileSizeInMb = 100;
    public $useEmbedReader = true; //if false, send user directly to Google viewer
    public $notifications = [
        'emailOnBorrow' => true,
        'emailOnManualReturn' => true,
        'emailOnAutoReturn' => [
            'enable' => false,
            'method' => 'cronJob',
            'dataFile' => 'items_currently_out.php.serialized',
            'publicCronUrl' =>  '',
            'secret' => '',
        ]
    ];
    public $emails = [
        'method' => 'gMail', //'gMail' Or 'SMTP'
        'SMTP' => [
            //no auth
            'host' => 'smtp.someuniv.edu',
            'port' => 25,
            'fromEmail' => 'no-reply-libraries@someuniv.edu',
            'fromName' => 'No-reply Some Univ Libraries'
        ]
    ];

    //props that users shoudn't change
    public $privateDataDirPath = './private_data/';
    public $accessibleUserCachefileName = 'accessible_users.php.serialized';
    public $accessibleUserCacheMinutes = 60;
    public $allItemsCacheFileName = 'all_items.php.serialized';
    //GDrive API config
    public $fields = 'nextPageToken, files(id,name,description,mimeType,size,ownedByMe,parents,viewersCanCopyContent,copyRequiresWriterPermission,permissions,webViewLink,webContentLink,appProperties,createdTime)';
    public $fieldsGet = 'id,name,description,mimeType,size,ownedByMe,parents,viewersCanCopyContent,copyRequiresWriterPermission,permissions,webViewLink,webContentLink,appProperties';
    public $pageSize = 100;
    public $orderBy = 'createdTime desc'; //created descending

    private $_propertiesInfo = [
        //[help text, editable status, select options]
        //-2 = hide, -1 = read only, 1 = editable, 2 = use caution
        'isProd' => ['is the app configred for production? When it is NOT in production, the API will 1) allow Access-Control-Allow-Origin: http://localhost:4200 and assume that you are running the frontend on port 4200. 2) will NOT change items status in your ILS (if enabled). 3) will NOT apply session cookies settings. 4) will allow unsecure CAS', 1],
        'appName' => ['name of the application (visible to end users)', 1],
        'timeZone' => ['timezone. see https://www.php.net/manual/en/timezones.php', 1],
        'maxFileSizeInMb' => ['Google PDF Viewer has max fix file size litmit of 100MB (as of early 2021), if you upload something bigger than that, it will not display',2],
        'useEmbedReader' => ['if enabled, embed Google Drive vieewer inside app\'s page. Else, will open Google Drive viewer directly in a new tab. NOTE: you might want to enable it since, as of early 2021, if end users open a reader and don\'t close it, they\'ll be able to keep reading even AFTER the share has expired. Using the embed reader, the app will embed the Google reader on its own page, and will automatically refresh the page when the item expires' , 1],
        'appSuperAdmins' => ['user(s) that can edit EVERYTHING incuding app\'s config, and EVERY library. Separate multiple users with a comma', 2],
        'auth' => [
            'kind' => ['how to authenticate user (telling who the user is). Only support Google OAuth (since end users need to be login to Google to view borrowed item)', -1, ['GoogleOAuth']],
            'gSuitesDomain' => ['your GSuites Domain WITHOUT the @ sign e.g. if your work email that you use to log in to GSuites is jdoe@someuniversity.edu, enter someuniversity.edu', -1],
            'sessionName' => ['name of the session cookie (user will NOT see it)', 1],
            'sessionTtl' => ['How long (minutes) before inactive user is logged out of the app (only the app, not GSuites account, nor whatever auth system GSuites uses to authenticate your users)', 1],
            'clientDomain' => ['see https://developer.mozilla.org/en-US/docs/Web/HTTP/Cookies#Domain_attribute',1],
            'clientPath' => ['see https://developer.mozilla.org/en-US/docs/Web/HTTP/Cookies#Path_attribute',1],
            'clientSecure' => ['see https://developer.mozilla.org/en-US/docs/Web/HTTP/Cookies#restrict_access_to_cookies',1],
            'clientHttpOnly' => ['see https://developer.mozilla.org/en-US/docs/Web/HTTP/Cookies#restrict_access_to_cookies',1]
        ],
        'mainFolderId' => ['FYI, ID of the main CDL folder in Google Drive', -1],
        'driveOwner' => ['FYI, the account that owns the main CDL Folder on Google Drive (drive owner has full/unlimited power, can edit EVERYTHING)', -1],
        'accessibleUsersSheetId' => ['FYI, ID of the Google Sheet that is used to store all the accessible users', -1],
        'accessibleUserCacheMinutes' => ['how long (minutes) should the accessible users data is cached', 1],
        'privateDataDirPath' => ['DANGER & IMPORTANT! READ ME! path to directory on your server to store credetails and cache files -- MUST be writable be the server, and MUST NOT be accessible to the public', -2],
        'accessibleUserCachefileName' => ['cache filename -- data is stored on Gsheet', -2],
        'allItemsCacheFileName' => ['cache filename -- for search', -2],
        'fields' => ['internal, for GDrive API -- which fields are returned', -2],
        'fieldsGet' => ['internal, for GDrive API -- which fields are returned', -2],
        'pageSize' => ['internal, for GDrive API -- results per page', -2],
        'orderBy' => ['internal, for GDrive API', -2],
        'notifications' => [
            'emailOnBorrow' => ['should the app email user when s/he borrows an item', 1],
            'emailOnManualReturn' => ['should the app email user when s/he manually returns an item', 1],
            'emailOnAutoReturn' => [
                'enable' => ['should the app email user when a borrowed item is automatically returned', 1],
                'method' => ['how? "cronJob" = run a cronjob locally on server e.g. [* * * * * /usr/bin/php /var/www/cdl/api/cron.php], "web" = make cron.php publicly available, then you can use service like uptimerobot.com to ping it a an interval, "webHook" = use Google Webhook which will send the app a meesage everytime borrowed items change (NEEDS ADDITIONAL SETUP see https://developers.google.com/drive/api/v3/push)', 1, ['cronJob','web','webHook']],
                'dataFile' => ['filename of local file used to track all the items currenly checked out to users (and when it\'s due)', -1],
                'publicCronUrl' =>  ['this is the URL that you need to ping at interval if you choose method = "web", will also be used for Google to send webHook notification meesage to the app if you choose method = "webHook"', -1],
                'secret' => ['if method is set to either "web" or "webHook", set a secret here so you can limit access e.g. /api/cron.php?secret=mySuperSecret (will be checked automatically if you use "webHook")', 1],
            ]
        ],
        'emails' => [
            'method' => ['How to email users. if choose: gMail, the app will send email with the drive owner GMail account ', 1, ['gMail','SMTP']],
            'SMTP' => [
                'host' => ['hostname of your SMTP server (currently set for SMTP relay with NO authentication)', 1],
                'port' => ['Standard port: 25, 587, 465', 1],
                'fromEmail' => ['email address to use as a sender', 1],
                'fromName' => ['name to use as a sender', 1]
            ]
        ],
        'googleTagManagerUA' => ['property ID of your Google Tag Manager (UA-XXXXXXXX) leave blank to not track', 1],
        'libraries' => [
            'key' => ['short name (key) of the library, only visible to end users if there\'s more than one library', -1],
            'name' => ['name of the library', 1],
            'isDefault' => ['is this the default library? (if there\'s multiple)', -1],
            'withOcrFolderId' => ['ID of the WITH ORC folder on GDrive of this library', -2],
            'noOcrFolderId' => ['ID of the NO ORC folder on GDrive of this library', -2],
            'statsSheetId' => ['ID of the Google Sheet file to store stats data for this library', -2],
            'borrowingPeriod' => ['how long the item is due back (hours)', 1],
            'backToBackBorrowCoolDown' => ['prevent user from borrwing the same item s/he just returned (minutes) i.e. have to wait for X minutes before can borrow it again', 1],
            'customUserHomeLibrary' => ['enter usernames here to manually make this library their home library (useful if you have multiple libraries setup) e.g enter \'jdoe\' will make this library a home library of user jdoe -- separate multiple users with a comma',1],
            'admins' => ['admins for this library (can change library\'s config) -- separate multiple users with a comma', 1],
            'staff' => ['staff of this library (can upload, administer items, view statistics) -- separate multiple users with a comma', 1],
            'ils' => [
                'kind' => ['ILS system', 1, ['Sirsi','Sierra']],
                'itemIdInFilenameRegexPattern' => ['Regular Expression pattern with a Capturing group [parentesis symbol] for getting itemId from filename when upload including the / delimiter at the begining and end of the pattern e.g. if your itemId is 13 digits and you plan to tell your staff the name the file "somerandomstring09876543210987.pdf" then enter /(\d{13})+/ the app will use the value of the first matched group as the itemId (first sequence of 13 digits it found)', 1],
                'api' => [
                    'enable' => ['use ILS api with the app (e.g. get bib info automatically when upload, search ILS reserves and etc.)', 1],
                    'base' => ['API URL base e.g. https://myopac.univ.edu/iii/sierra-api/v6/ or https://siris.univ.edu/sirsi_ilsws', 1],
                    'key' => ['API key', 1],
                    'secret' => ['API secret', 1],
                    'clientId' => ['clientId (for Sirsi only)', 1],
                    'appId' => ['appId (for Sirsi only)', 1],
                    'courseCacheFile' => ["file name of cache file of all the courses", -2],
                    'courseCacheFileRefreshMinutes' => ['how long should the app cache course reserves data pulled form the ILS (minutes)', 1],
                    'changeItemStatusOnBorrowReturn' => ['should the app change item\'s status in the ILS when user borrow/return item in the app (Sierra only)', 1],
                    'itemStatus' => [
                        'borrow' => ['item status code in ILS to change to when item is borrowed', 1],
                        'return' => ['item status code in ILS to change to when item is returned', 1]
                    ]
                ]
            ],
            'authorization' => [
                'enable' => ['enable to also check with your local authentication system to get users attributes.',1],
                'auth' => [
                    'kind' => ['type of auth system to authenticate and get users attributes from', 1],
                    'CAS' => [
                        'protocol' => ['https:// or http:// (only non-production mode)', 1],
                        'host' => ['hostname e.g. cas.myuniv.edu', 1],
                        'context' => ['path in the url e.g. /cas', 1],
                        'port' => ['port: usually 80 or 443', 1],
                        'version' => ['CAS version', 1],
                        'caCertPath' => ['path to local root certificate file on the server that hosts this app (REQUIRED for production)', 1],
                        'attributesMapping' => [
                            'fullName' => ['attribute name that containts users\'s full name', 1],
                            'univId' => ['attribute name that containts users\'s university id (e.g. the numberic one)', 1]
                        ],
                        'checkHomeLibrary' => [
                            'enable' => ['should the app try to assign user\'s home library?', 1],
                            'attrToCheck' => ['name of the attribute returned by CAS to check', 1],
                            'validAttrs' => ['if attribute to check contains this value(s), this library will be set as user\'s home library ', 1],
                        ],
                        'checkUserIsActive' => [
                            'enable' => ['should the app check if user is active -- e.g. some univeristies allow alumni to keep email address (if enabled, INACTIVE users will not be able to access the app)', 1],
                            'attrToCheck' => ['name of the attribute returned by CAS to check', 1],
                            'validAttrs' => ['if attribute to check contains this value(s) -- separate by a commna, the user is considered an active user', 1],
                        ],
                        'checkUserIsFaculyOrStaff' => [
                            'enable' => ['should the app check if user is faculty or staff', 1],
                            'attrToCheck' => ['name of the attribute returned by CAS to check', 1],
                            'validAttrs' => ['if attribute to check contains this value(s) -- separate by a commna, the user is considered a faculty or staff', 1],
                        ],
                        'checkUserIsGradStudent' => [
                            'enable' => ['should the app check if user is a graduate student', 1],
                            'attrToCheck' => ['name of the attribute returned by CAS to check', 1],
                            'validAttrs' => ['if attribute to check contains this value(s) -- separate by a commna, the user is considered a graduate student', 1],
                        ],
                    ],
                ]
            ]
        ]
    ];

    private $_sectionDefinitions = [
        'notifications' => 'how should the app notify users when something happens',
        'emails' => 'emails configurations',
        'auth' => 'authentication service/system to use',
        //libraries
        'ils' => 'Integrated Library System',
        'api' => 'ILS\'s API',
        'authorization' => 'extra users info to get from secondary authentication system. Use case for universitiy that their GSuites does not include too many users info other than thier names e.g. if you want to tell if user is student or faculty.'
    ];

    public function __construct()
    {
        //4 basic config stored in GDrive's app's data, else stored locally
        $fileName = $this->privateDataDirPath . 'config.json';
        if (file_exists($fileName)) { //local config exists
            $file = file_get_contents($fileName);
            $data = json_decode($file);
            $this->_map($data);
        } else {
            //if no local config, grab the basic/essential config fron appData
            $client = getClient();
            if (!$client) {
                return null;
            } else {
                $service = new Google_Service_Drive($client);
                $file = $this->getAppDataConfigFile();
                if ($file) {
                    $content = $service->files->get($file->getId(), ["alt" => "media"]);
                    $data = json_decode((string) $content->getBody());
                    $this->_map($data);
                    $file = fopen($fileName, 'wb');
                    fwrite($file, json_encode($data));
                    fclose($file);
                } else {
                    return null;
                }
            }
        }

        $this->auth['gSuitesDomain'] = $this->gSuitesDomain;
        if (!isset($this->timeZone)) {
            $this->timeZone = date_default_timezone_get();
        }
        if (($this->notifications['emailOnAutoReturn']['method'] == 'web' || $this->notifications['emailOnAutoReturn']['method'] == 'webHook') && !isset($this->notifications['emailOnAutoReturn']['publicCronUrl']) || 1 == 1) {
            $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
            $url .= preg_replace('/\/api.*/', '/api/cron.php', $_SERVER["REQUEST_URI"]);
            $this->notifications['emailOnAutoReturn']['publicCronUrl'] = $url;
        }
    }

    private function getAppDataConfigFile()
    { //from GDrive's appDataFolder
        $client = getClient();
        $service = new Google_Service_Drive($client);
        try {
            $fileList = $service->files->listFiles([
                'spaces' => 'appDataFolder',
                'fields' => '*',
                'pageSize' => 10
            ]);
            foreach ($fileList->getFiles() as $file) {
                if ($file->getName() == 'config.json') {
                    return $file;
                }
            }
        } catch (Google_Service_Exception $e) {
            $errMsg = json_decode($e->getMessage());
            logError('cannot get config.json file on Drive AppData... is the app still connect to the drive? Error is: ' . $errMsg->error->message);
            die('Fatal Error: cannot get config file from Drive\'s AppData');
        }
    }

    private function _map($data)
    {
        foreach ($data as $key => $val) {
            if ($key == 'libraries') {
                foreach ($val as $lib) {
                    $library = new Library($lib);
                    $this->libraries[$library->key] = $library; //make it an assoc array so it's easier to get, actual data is stored in json
                }
            } elseif (property_exists(__CLASS__, $key)) {
                if (is_object($val)) {
                    //obj -> assocArray recursively
                    $this->$key = json_decode(json_encode($val), true);
                } else {
                    $this->$key = $val;
                }
            }
        }
    }

    //for global
    private function _reverseMap()
    {
        $data = [];
        foreach ($this as $key => $val) {
            if (substr($key, 0, 1) !== "_" && $key != 'libraries') {
                $data[$key] = $val;
            }
        }
        $data['libraries'] = [];
        foreach ($this->libraries as $libKey => $library) {
            array_push($data['libraries'], $library);
        }
        return $data;
    }

    //update ALL config to local config.json
    private function _updatePropsAll()
    {
        $fileName = $this->privateDataDirPath . 'config.json';
        try {
            $data = $this->_reverseMap();
            $file = fopen($fileName, 'wb');
            fwrite($file, json_encode($data));
            fclose($file);
            $result = ['success' => true];
        } catch (Exception $e) {
            $result = ['success' => false];
        }
        return $result;
    }

    //POST from config edit, only save custom values
    public function updatePropsDeep($chagedProperties, $libKey = null)
    {
        global $user;
        if (!count($user->isAdminOfLibraries)) {
            respondWithError(401, 'Not Authorized');
            die();
        }
        //if update library
        if ($libKey) {
            if (!in_array($libKey, $user->isAdminOfLibraries)) {
                respondWithError(401, 'Not Authorized to edit config of this library');
                die();
            }
            return $this->_updatePropsDeep($chagedProperties, $this->libraries[$libKey]);
        } else {
            if (!$user->isSuperAdmin) {
                respondWithError(401, 'Not Authorized to edit app global config');
                die();
            }
            return $this->_updatePropsDeep($chagedProperties, $this);
        }
    }

    private function _updatePropsDeep(&$input, &$target, $isRecursive = false)
    {
        foreach ($input as $key => $value) {
            if ($this->_has_string_keys((array) $value)) {
                if (is_object($target)) {
                    $this->_updatePropsDeep($value, $target->$key, true);
                } else {
                    $this->_updatePropsDeep($value, $target[$key], true);
                }
            } else {
                if (is_object($target)) {
                    $target->$key = $value;
                } else {
                    $target[$key] = $value;                
                }
            }
        }

        //if(!$isRecursive) return (array) $target;
        if(!$isRecursive) return $this->_updatePropsAll();
    }

    //for public -- annon acceess allowed
    public function getFrontendConfig()
    {
        $defaultLibrary;
        $libraries = [];
        foreach ($this->libraries as $key => $library) {
            $libraries[$key] = [
                'name' => $library->name,
                'ilsApiEnabled' => $library->ils['api']['enable'],
                'itemIdInFilenameRegexPattern' => trim($library->ils['itemIdInFilenameRegexPattern'], '/')
            ];
            if (!isset($defaultLibrary)) $defaultLibrary = $key;
            if (isset($library->isDefault) && $library->isDefault) $defaultLibrary = $key;
        }

        $frontEndConfig = [
            'appName' => $this->appName,
            'defaultLibrary' => $defaultLibrary,
            'gSuitesDomain' => $this->auth['gSuitesDomain'],
            'emailDomain' => '@' . $this->auth['gSuitesDomain'],
            'maxFileSizeInMb' => $this->maxFileSizeInMb,
            'useEmbedReader' => $this->useEmbedReader,
            'libraries' => $libraries
        ];
        if ($this->googleTagManagerUA) $frontEndConfig['gTagUA'] = $this->googleTagManagerUA;
        return $frontEndConfig;
    }

    public function getAdminConfig()
    {
        global $user;
        if (!count($user->isAdminOfLibraries)) {
            respondWithError(401, 'Not Authorized');
            die();
        }

        $config = [];
        $config['keys'] = ['key','value','type','desc','editable','options'];
        if ($user->isSuperAdmin) {
            $config['global'] = [
                $this->_createField('isProd'),
                $this->_createField('timeZone'),
                $this->_createField('appName'),
                $this->_createField('driveOwner'),
                $this->_createField('appSuperAdmins'),
                $this->_createField('googleTagManagerUA'),
                $this->_createField('auth'),
                $this->_createField('mainFolderId'),
                $this->_createField('accessibleUsersSheetId'),
                $this->_createField('maxFileSizeInMb'),
                $this->_createField('useEmbedReader'),
                $this->_createField('privateDataDirPath'),
                $this->_createField('accessibleUserCachefileName'),
                $this->_createField('accessibleUserCacheMinutes'),
                $this->_createField('allItemsCacheFileName'),
                $this->_createField('fields'),
                $this->_createField('fieldsGet'),
                $this->_createField('pageSize'),
                $this->_createField('orderBy'),
                $this->_createField('notifications'),
                $this->_createField('emails')
            ];
        }
        $config['sectionDefinitions'] = $this->_sectionDefinitions;

        $config['libraries'] = [];
        foreach ($this->libraries as $library) {
            if (!in_array($library->key, $user->isAdminOfLibraries)) continue; //skip it since only show library that user is admin

            $this->_propertiesInfo['libraries'][$library->key] = $this->_propertiesInfo['libraries'];
            array_push($config['libraries'], $this->_createLibraryField($library->serialize(), $this->_propertiesInfo['libraries'][$library->key]));
        }

        $config['serverCheck'] = [];
        $config['serverCheck']['privateDataWritable'] = is_writable('./private_data');
        $config['serverCheck']['privateTempWritable'] = is_writable('./private_temp');
        $config['serverCheck']['shellExecEnable'] = is_callable('shell_exec') && false === stripos(ini_get('disable_functions'), 'shell_exec');

        return $config;
    }

    private function _getScopes()
    {
        $tokenPath = './private_data/token.json';
        $accessToken = json_decode(file_get_contents($tokenPath), true);
        return explode(' ', $accessToken['scope']);
    }

    public function createNewLibrary(string $libKey, string $libName, array $options = null)
    {
        global $user;
        if (!$user->isSuperAdmin) {
            respondWithError(401, 'Not Authorized');
            die();
        }

        //create folders
        $client = getClient();
        $driveService = new Google_Service_Drive($client);
        //no ocr
        $driveFile = new Google_Service_Drive_DriveFile;
        $driveFile->setName("$libKey - NO OCR");
        $driveFile->setParents([$this->mainFolderId]);
        $driveFile->setDescription("Folder for $libKey library's PDFs with NO OCR. Do NOT touch it directly");
        $driveFile->setMimeType("application/vnd.google-apps.folder");
        try {
            $noOcrFolder = $driveService->files->create($driveFile);
        } catch (Exception $e) {
            die('creating folder failed');
        }
        //with ocr
        $driveFile->setName("$libKey - WITH OCR");
        $driveFile->setDescription("Folder for $libKey library's PDFs WITH OCR. Do NOT touch it directly");
        try {
            $withOcrFolder = $driveService->files->create($driveFile);
        } catch (Exception $e) {
            die('creating folder failed');
        }

        //create sheet to store stats
        $driveFile = new Google_Service_Drive_DriveFile;
        $driveFile->setName("$libKey - Statistics");
        $driveFile->setParents([$this->mainFolderId]);
        $driveFile->setDescription("Spreadsheet for CDL Application to store usage statistics for $libKey library. Do not touch it directly");
        $driveFile->setMimeType("application/vnd.google-apps.spreadsheet");
        try {
            $statsSheet = $driveService->files->create($driveFile);
        } catch (Exception $e) {
            die('failed');
        }

        //add library to config
        $libData = [
            'key' => $libKey,
            'name' => $libName,
            'isDefault' => false,
            'withOcrFolderId' => $withOcrFolder->getId(),
            'noOcrFolderId' => $noOcrFolder->getId(),
            'statsSheetId' => $statsSheet->getId()
        ];
        if ($options) $libData += $options;
        if (!count($this->libraries)) $libData['isDefault'] = true;
        $library = new Library($libData);
        $this->libraries[$libKey] = $library;
        $result = $this->_updatePropsAll();

        return $result;
    }

    function removeLibrary(string $libKey)
    {
        global $user;
        if (!$user->isSuperAdmin) {
            respondWithError(401, 'Not Authorized');
            die();
        }

        //delete stuff on GDrive
        $client = getClient();
        $driveService = new Google_Service_Drive($client);
        $tempFile = new Google_Service_Drive_DriveFile;
        $tempFile->setTrashed(true);
        $libConfig = $this->libraries[$libKey];

        try {
            retry(function () use ($driveService, $tempFile, $libConfig) {
                $driveService->files->update($libConfig->withOcrFolderId, $tempFile);
                $driveService->files->update($libConfig->noOcrFolderId, $tempFile);
                $driveService->files->update($libConfig->statsSheetId, $tempFile);
            });
        } catch (Google_Service_Exception $e) {
            respondWithError(500, "Internal Error");
            logError($e->getMessage());
        }
        //remove from cust/lang
        require_once('Customization.php');
        $customization = new Customization();
        $customization->removeLibrary($libKey);
        require_once('Lang.php');
        $lang = new Lang();
        $lang->removeLibrary($libKey);
        //remove from config
        unset($this->libraries[$libKey]);
        $result = $this->_updatePropsAll();
        return $result;
        
    }

    private function _createField($key, &$props = null, &$propsInfo = null, $isRecursive = false)
    {
        if ($props === null) $props = &$this->$key;
        if (!$propsInfo) $propsInfo = &$this->_propertiesInfo[$key];

        if ($props && is_array($props) && $this->_has_string_keys($props)) {
            foreach ($props as $subKey => $value) {
                $this->_createField($subKey, $value, $propsInfo[$subKey], true);
            }
        } else {
            if ($props === false) {
                array_unshift($propsInfo, $key, $props, 'boolean');
            } else if (gettype($props) !== "NULL") {
                array_unshift($propsInfo, $key, $props, gettype($props) ? gettype($props) : 'string');
            } else {
                array_unshift($propsInfo, $key, $props, 'string');
            }
        }

        if (!$isRecursive) {
            if (is_array($props) && $this->_has_string_keys($props)) {
                return [$key, $this->_array_values_recursive($this->_propertiesInfo[$key]), 'group'];
            } else {
                return $this->_propertiesInfo[$key];
            }
        }
    }

    private function _createLibraryField(&$library, &$propsInfo, $isRecursive = false)
    {
        foreach ($library as $key => $value) {
            if (is_array($value) && $this->_has_string_keys($value)) {
                $this->_createLibraryField($value, $propsInfo[$key], true);
            } else {
                if (!$propsInfo[$key]) echo "bad $key \n";
                array_unshift($propsInfo[$key], $key, $value, (gettype($value) && gettype($value) != 'NULL') ? gettype($value) : 'string');
            }
        }
        if (!$isRecursive) return $this->_array_values_recursive($propsInfo);
    }

    private function _array_values_recursive($arr)
    {
        $arr2=[];
        foreach ($arr as $key => $value) {
            // echo "$key : " . implode(', ', $value) . "\n";

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
}
