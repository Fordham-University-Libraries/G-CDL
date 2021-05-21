<?php

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class User
{
    public bool $isDriveOwner = false;
    public string $userName;
    public string $email;
    public ?string $univId;
    public ?string $fullName;
    public ?string $homeLibrary;
    public ?string $photoUrl;
    public bool $isActiveUser = false;
    public bool $isAccessibleUser = false;
    public bool $isFacStaff = false;
    public bool $isGradStudent = false;
    public bool $isSuperAdmin = false;
    public array $isStaffOfLibraries = [];
    public array $isAdminOfLibraries = [];
    private array $attributes;
    private $debug;

    public function __construct($internal = false)
    {
        global $config;

        // can only authenticate with GoogleOAuth, if need to get users attrs, use authorize()
        if ($config->auth['kind'] == 'GoogleOAuth') {
            if (session_status() == PHP_SESSION_NONE) {
                session_name($config->auth['sessionName']);
                if (Config::$isProd) session_set_cookie_params(0, $config->auth['clientPath'], $config->auth['clientDomain'], $config->auth['clientSecure'], $config->auth['clientHttpOnly']);
                session_start();
            }

            if (isset($_SESSION['gUserName']) && $_SESSION['gExpire'] > time()) {
                $this->userName = str_replace('@' . $config->gSuitesDomain, '', $_SESSION['gEmail']);
                $this->email = $_SESSION['gEmail'];
                if ($_SESSION["photoUrl"]) $this->photoUrl = $_SESSION["photoUrl"];
                if (!Config::$isProd) {
                    //dev -- become somebody else
                    //$this->userName = 'djohn';
                    //$this->email = 'djohn@mustard.edu';
                }
                $this->fullName = $_SESSION['gFullName'];
                $_SESSION['gExpire'] = time() + ($config->auth['sessionTtl'] * 60);
                if (!$config->libraries[array_key_first($config->libraries)]->authorization['enable']) setcookie('cdlLogin', '1', $_SESSION['gExpire'], '/');
            } else {
                $_SESSION = [];
                session_destroy();
                setcookie('cdlLogin', '0', time(), '/');
                if (!$internal) {
                    respondWithFatalError(401, 'Unauthorized User');
                } else {
                    throw new Exception("Not Logged In");
                    die();
                }
                //front end will send user to login at ?action=login when get 401
            }
        }

        if (!isset($this->userName)) {
            respondWithFatalError(401, 'Unauthorized User');
        }

        //user is Authed
        //check if user is owner of the drive
        if (!$config->driveOwner) {
            logError('NO driveOwner info in config');
            $this->isDriveOwner = false;
        } else if ($this->email == $config->driveOwner) {
            $this->isDriveOwner = true;
            $this->isSuperAdmin = true;
        } else if (in_array($this->userName, $config->appSuperAdmins)) {
            $this->isSuperAdmin = true;
        }

        //set home library for user - if no match, default to first library
        //also check if user is a staff of a library
        foreach ($config->libraries as $libKey => $_library) {
            //check staff
            if (in_array($this->userName, $_library->staff) || in_array($this->userName, $_library->admins) || $this->isSuperAdmin) {
                array_push($this->isStaffOfLibraries, $libKey);
            }
            //check admin
            if (in_array($this->userName, $_library->admins) || $this->isSuperAdmin) {
                array_push($this->isAdminOfLibraries, $libKey);
            }
            //if authz is enabled (check users attrs)
            if ($_library->authorization['enable']) {
                $this->authorize($_library->authorization, $libKey);
            }
            //customUserHomeLibrary overrides the attrs check 
            if ($_library->customUserHomeLibrary && in_array($this->userName, $_library->customUserHomeLibrary)) {
                $this->homeLibrary = $libKey;
            }
        }

        if (!isset($this->homeLibrary)) $this->homeLibrary = array_key_first($config->libraries);

        //if authz check active is not enabled, always set user to active (since we don't check, everybody is active)
        if (!$config->libraries[$this->homeLibrary]->authorization['enable'] || !$config->libraries[$this->homeLibrary]->authorization['auth']['CAS']['checkUserIsActive']['enable']) {
            $this->isActiveUser = true;
        }

        //check accessible user
        $fileName = Config::getLocalFilePath($config->accessibleUserCachefileName);
        if (file_exists($fileName) && time() - filemtime($fileName) < $config->accessibleUserCacheMinutes * 3600) {
            // use cache
            $file = file_get_contents($fileName);
            $accessibleUsers = unserialize($file);
            $this->isAccessibleUser = in_array($this->userName, $accessibleUsers);
        } else {
            // grab new
            $client = getClient();
            $sheetService = new Google_Service_Sheets($client);
            $range = 'Sheet1!A1:A';
            try {
                $response = $sheetService->spreadsheets_values->get($config->accessibleUsersSheetId, $range);
                $values = $response->getValues();
                $accessibleUsers = [];
                if (!empty($values)) {
                    foreach ($values as $row) {
                        array_push($accessibleUsers, $row[0]);
                    }
                }
                $file = fopen($fileName, 'wb');
                fwrite($file, serialize($accessibleUsers));
                fclose($file);
                $this->isAccessibleUser = in_array($this->userName, $accessibleUsers);
            } catch (Google_Service_Exception $e) {
                $errMsg = json_decode($e->getMessage());
                logError('cannot get accessible users data from sheet: ' + $config->accessibleUsersSheetId);
                logError($errMsg);
                $this->isAccessibleUser = false;
            }
        }
    }

    //get users arrtibutes from auth system
    public function authorize($authzConfig, $libKey)
    {
        // CAS
        if ($authzConfig['auth']['kind'] == 'CAS') {
            //if it's default library
            if (!phpCAS::isInitialized()) {

                if (!$authzConfig['auth']['CAS']['version'] || !$authzConfig['auth']['CAS']['host'] || !$authzConfig['auth']['CAS']['port'] || !$authzConfig['auth']['CAS']['context']) {
                    return;
                }

                phpCAS::client(
                    $authzConfig['auth']['CAS']['version'],
                    $authzConfig['auth']['CAS']['host'],
                    intval($authzConfig['auth']['CAS']['port']),
                    $authzConfig['auth']['CAS']['context']
                );

                if (Config::$isProd) {
                    phpCAS::setCasServerCACert($authzConfig['auth']['CAS']['caCertPath']);
                } else {
                    $logger = new Logger('cas');
                    $logger->pushHandler(new StreamHandler(Config::getLocalFilePath('CAS-debug.log'), Logger::DEBUG));
                    phpCAS::setLogger($logger);
                    phpCAS::setVerbose(true);
                    phpCAS::setNoCasServerValidation();
                    if ($authzConfig['auth']['CAS']['protocol'] == 'http://') {
                        $httpUrl = 'http://' . $authzConfig['auth']['CAS']['host'] . ':' . $authzConfig['auth']['CAS']['port'] . $authzConfig['auth']['CAS']['context'];
                        phpCAS::setServerLoginURL($httpUrl . '/login?service=http%3A%2F%2Flocalhost%3A8080%2Fapi%2F%3Faction%3Dauth');
                        phpCAS::setServerLogoutURL($httpUrl . '/logout');
                        if (substr($authzConfig['auth']['CAS']['version'], 0, 1) ===  '3') {
                            $validateUrl = $httpUrl . '/p3/serviceValidate';
                        } else {
                            $validateUrl = $httpUrl . '/serviceValidate';
                        }
                        phpCAS::setServerServiceValidateURL($validateUrl);
                    }
                }

                //phpCAS::forceAuthentication();
                //phpCAS::checkAuthentication();
                if (!phpCAS::isAuthenticated()) {
                    //soft error, tell front-end to redirect
                    respondWithError(302, 'needs local login', phpCAS::getServerLoginURL());
                }
                //for frontend
                setcookie('cdlLogin', '1', $_SESSION['gExpire'], '/');
                $this->attributes = phpCAS::getAttributes();

                //check user's home library
                if ($authzConfig['auth']['CAS']['checkHomeLibrary']['enable']) {
                    if (count(array_intersect($this->attributes[$authzConfig['auth']['CAS']['checkHomeLibrary']['attrToCheck']], $authzConfig['auth']['CAS']['checkHomeLibrary']['validAttrs']))) {
                        $this->homeLibrary = $libKey;
                    }
                }

                //map attrs return fomr CAS to user
                if (isset($authzConfig['auth']['CAS']['attributesMapping'])) {
                    foreach ($authzConfig['auth']['CAS']['attributesMapping'] as $userKey => $casAttrKey) {
                        if (property_exists($this, $userKey)) {
                            if ($this->attributes[$casAttrKey]) $this->$userKey = $this->attributes[$casAttrKey];
                        }
                    }
                }


                //check if user is active
                if ($authzConfig['auth']['CAS']['checkUserIsActive']['enable']) {
                    if (count(array_intersect($this->attributes[$authzConfig['auth']['CAS']['checkUserIsActive']['attrToCheck']], $authzConfig['auth']['CAS']['checkUserIsActive']['validAttrs']))) {
                        $this->isActiveUser = true;
                    }
                }

                //check if user is faculty or staff
                if ($authzConfig['auth']['CAS']['checkUserIsFaculyOrStaff']['enable']) {
                    if (count(array_intersect($this->attributes[$authzConfig['auth']['CAS']['checkUserIsFaculyOrStaff']['attrToCheck']], $authzConfig['auth']['CAS']['checkUserIsFaculyOrStaff']['validAttrs']))) {
                        $this->isFacStaff = true;
                    }
                }

                //check if user is grad student
                if ($authzConfig['auth']['CAS']['checkUserIsGradStudent']['enable']) {
                    if (!$authzConfig['auth']['CAS']['checkUserIsGradStudent']['contains']) {
                        if (count(array_intersect($this->attributes[$authzConfig['auth']['CAS']['checkUserIsGradStudent']['attrToCheck']], $authzConfig['auth']['CAS']['checkUserIsGradStudent']['validAttrs']))) {
                            $this->isGradStudent = true;
                        }
                    } else {
                        foreach ($this->attributes[$authzConfig['auth']['CAS']['checkUserIsGradStudent']['attrToCheck']] as $userAttr) {
                            foreach ($authzConfig['auth']['CAS']['checkUserIsGradStudent']['validAttrs'] as $validAttr) {
                                if (strpos($userAttr, $validAttr) !== false) {
                                    $this->isGradStudent = true;
                                    break 2;
                                }
                            }
                        }
                    }
                }
            } else {
                //PHPCAS has been init'ed (not the default library)
                //check user's home library
                if ($authzConfig['auth']['CAS']['checkHomeLibrary']['enable']) {
                    if (count(array_intersect($this->attributes[$authzConfig['auth']['CAS']['checkHomeLibrary']['attrToCheck']], $authzConfig['auth']['CAS']['checkHomeLibrary']['validAttrs']))) {
                        $this->homeLibrary = $libKey;
                    }
                }
            }
        }
    }

    public function serialize()
    {
        $user = [
            'isAuthenticated' => true,
            'userName' => $this->userName,
            'fullName' => $this->fullName ?? null,
            'photoUrl' => $this->photoUrl ?? null,
            'univId' => $this->univId ?? null,
            'email' => $this->email ?? null,
            'homeLibrary' => $this->homeLibrary ?? null,
            'isActiveUser' => $this->isActiveUser ?? null,
            'isAccessibleUser' => $this->isAccessibleUser ?? null,
            'isFacStaff' => $this->isFacStaff ?? null,
            'isGradStudent' => $this->isGradStudent ?? null,
            //'debug' => $this->attributes ?? null
        ];
        if ($this->isDriveOwner) $user['isDriveOwner'] = true;
        if ($this->isSuperAdmin) $user['isSuperAdmin'] = true;
        if (count($this->isStaffOfLibraries)) $user['isStaffOfLibraries'] = $this->isStaffOfLibraries;
        if (count($this->isAdminOfLibraries)) $user['isAdminOfLibraries'] = $this->isAdminOfLibraries;

        return $user;
    }
}
