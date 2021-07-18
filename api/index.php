<?php
declare(strict_types = 1);
ini_set('display_errors', '0');
error_reporting(E_ALL);

require 'Config.php';
require 'CdlItem.php';
require 'User.php';
require 'respond.php';
require 'get_client.php';
require 'stats_action.php';
require_once('email.php');
require_once('ils_api_action.php');
require_once('retry.php');
require_once('utils.php');
require __DIR__ . '/vendor/autoload.php';

//force trailing slash
if (strpos($_SERVER["REQUEST_URI"], 'api/') === FALSE) {
    $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
    $url .= str_replace('/api', '/api/', $_SERVER["REQUEST_URI"]);
    header("location: $url");
    die();
}

//get config
$credsPath = Config::getLocalFilePath('credentials.json', 'creds');
$serviceAccountcredsPath = Config::getLocalFilePath('serviceAccountCreds.json', 'creds');
$tokenPath = Config::getLocalFilePath('token.json', 'creds');
$hasCreds = file_exists($credsPath);
$hasToken = file_exists($tokenPath);
$hasServiceAccountCreds = file_exists($serviceAccountcredsPath);
try {
    $config = new Config();
} catch (Google_Service_Exception $e) {
    $error = json_decode($e->getMessage());
    $errMsg = 'ERROR: can\'t get app config';
    if (isset($error->error->errors[0]->reason)) $errMsg .= ' -- ' . $error->error->errors[0]->reason;
    respondWithFatalError(500, $errMsg);
    die();
}

//login
$action = $_GET['action'];

if ($action == 'login') {
    //when init step2 (link accont, will have state == init), let the init_action handle it
    if($_GET['state'] != 'init') {
        $authCode = $_GET['code'] ?? null;
        $apiAction = $_GET['apiActionTarget'] ?? null;
        $target = $_GET['target'] ?? null;
        endUserGoogleLogin($authCode, $target, $apiAction);
        die();
    }
}

//init wizard (if no token and etc.)
$needsInit = true;
if ($_GET['state'] != 'init' && $_GET['action'] != 'init') {
    if (($hasCreds && $hasToken) || ($hasServiceAccountCreds && $hasCreds)) {
        if ($config->mainFolderId && count($config->libraries)) {
            $needsInit = false;
        }
    }
}

if ($needsInit) {
    //if front end try to access
    if ($_GET['action'] == 'auth') {
        respondWithFatalError(500, 'API not set up');
    } else {
        require 'init_action.php';
        $step = $_GET['step'] ?? 1;
        $authCode = $_GET['code'] ?? null;
        init($step,  $authCode);
        die();
    }
}

// Get the API client and construct the service object.
$client = getClient();
$service = new Google_Service_Drive($client);

if ($config->timeZone) date_default_timezone_set($config->timeZone);
if (Config::$isProd) error_reporting(E_ERROR);

//allow annon access
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    if ($action == 'logout') {
        logout();
        die();
    } else if ($action == 'get_config') {
        getConfig();
        die();
    } else if ($action == 'get_lang') {
        $lang = getLanguages();
        respondWithData($lang);
        die();
    } else if ($action == 'get_customization') {
        getCustomizations();
        die();
    } else if ($action == 'get_custom_css') {
        require 'Customization.php';
        $customization = new Customization();
        $customization->generateCustomCss();
        die();
    } else if ($action == 'check_availability') {
        require 'check_availability_action.php';
        $key = $_GET['key'] ?? null;
        if (!$key) respondWithError(400, 'No Key');
        $keyType = $_GET['keyType'] ?? 'bibId';
        $configs = $config->getFrontendConfig();
        $libKey = $_GET['libKey'] ?? $configs['defaultLibrary'];
        checkAvailability($key, $keyType, $libKey);
        die();
    }
}

//everything below this needs to have library setup and user authenticated
if (!count($config->libraries)) {
    respondWithFatalError(500, 'No Library');
}
$sheetService = new Google_Service_Sheets($client);
$user = new User();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $target = $_GET['target'] ?? null;
    $action = $_GET['action'] ?? null;
    $key = $_GET['key'] ?? null;
    $keyType = $_GET['keyType'] ?? null;
    $libKey = $_GET['libKey'] ?? $user->homeLibrary;

    if ($target) {
        //if coming back from auth, redirect to app
        $host = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
        $host .= $_SERVER['SERVER_NAME'];
        //if in dev, redirect to local Angular (running on diff port -- 4200)
        if (!Config::$isProd) $host .= ':4200';
        header('Location: ' . $host . $target);
    } else if ($action == 'auth') { 
        respondWithData($user->serialize());
    } else if (!isset($action) || $action == '' || $action == 'view_all') {
        require 'view_all_action.php';
        $nextPageToken = $_GET['nextPageToken'] ?? null;
        getAllFiles($libKey, $nextPageToken);
    } else if ($action == 'view_items_with_copies') {
        require 'search_action.php';
        checkMulti($libKey);
    } else if ($action == 'view_borrowed'){
        require 'view_borrowed_action.php';
        getFilesForViewer();
    } else if ($action == 'get_items'){
        require 'get_items_action.php';
        getItems($key, $keyType, $libKey);
    } else if ($action == 'search'){
        require 'search_action.php';
        $field = $_GET['field'] ?? 'title';
        $term = $_GET['term'] ?? '';
        search($field, $term, $libKey, false);
    } else if ($action == 'get_about'){
        require_once('Lang.php');
        $langObj = new Lang();
        respondWithHtml($langObj->getAbout($libKey));
    } else if ($action == 'admin'){
        require 'admin_action.php';
        getItemsAdmin($libKey);
    } else if ($action == 'admin_upload'){
        require 'upload_action.php';
        adminUploadGet($libKey);
    } else if ($action == 'get_config_admin') {
        getConfigAdmin();
        die();
    } else if ($action == 'get_customization_admin') {
        getCustomizationsAdmin();
        die();
    } else if ($action == 'get_lang_admin') {
        getLangAdmin();
        die();
    } else if ($action == 'search_by_bib_ids'){
        require 'search_action.php';
        $bibIds = $_GET['bibIds'];
        searchByBibIds($libKey, $bibIds);
    } else if ($action == 'get_ils_bib'){ //get data from ILS
        if ($keyType == 'itemId') {
            $data = getIlsBibByItemId($libKey, $key);
            respondWithData((array) $data);
        } else if ($keyType == 'bibId') {
            $data = getIlsBibByBibId($libKey, $key);
            respondWithData((array) $data);
        }
    } else if ($action == 'get_stats') {
        $from = $_GET['from'] ?? null;
        $to = $_GET['to'] ?? null;
        getStats($libKey, $from, $to);
    } else if ($action == 'get_item_edit_admin') {
        require 'admin_action.php';
        $fileId = $_GET['fileId'];
        getItemEditAdmin($fileId);
    } else if ($action == 'download_file_admin') {
        require 'admin_action.php';
        $fileId = $_GET['fileId']; //noOrc
        $accessibleVersion = $_GET['accessibleVersion'] ?? false;
        downloadFileAdmin($fileId, $accessibleVersion);
    } else if ($action == 'get_accessible_users') {
        require 'admin_action.php';
        getAccessibleUsers($libKey);
    } else if ($action == 'admin_get_backup_config') {
        $response = [];
        $driveFile = $config->getFileFromAppFolder('config.json');
        if ($driveFile) {
            $response['id'] = $driveFile->getId();
            $response['name'] = $driveFile->getName();
            $response['version'] = $driveFile->getVersion();
            $response['lastModified'] = $driveFile->getModifiedTime();
            $revList = $service->revisions->listRevisions($driveFile->getId(),['fields' => 'revisions(id,mimeType,modifiedTime,size)']);
            if ($revList) $response['revisions'] = $revList->getRevisions();
            respondWithData($response);
        }
    } else if ($action == 'admin_get_file_revision_data') {
        $fileId = $_GET['fileId'] ?? null;
        $revId = $_GET['revId'] ?? null;
        if ($fileId && $revId) respondWithData($config->getFileRevisionData($fileId, $revId));
    } else if ($action == 'search_courses') {
        $field = $_GET['field'] ?? null;
        $term = $_GET['term'] ?? null;
        $courses = searchIlsCourseReserves($libKey, $field, $term);
    } else if ($action == 'get_course_info') {
        $courseNumber = $_GET['courseNumber'] ?? null;
        $courses = getIlsCourseReservesInfo($libKey, $courseNumber);
    } else if ($action == 'get_courses_by_user') {
        $userPk = $_GET['userPk'] ?? null;
        $courses = getAllCoursesByProfessor($libKey, $userPk);
        respondWithData($courses);
    } else if ($action == 'get_course_full') {
        $courses = getIlsFullCourseReserves($libKey, $key);
    } else if ($action == 'search_ils_ebook') {
        $title = $_GET['title'] ?? null;
        $author = $_GET['author'] ?? null;
        $ebook = searchIlsForEbook($libKey, $title, $author);
        respondWithData($ebook);
    } else if($action == 'get_ils_locations') {
        getIlsLocationsDefinition($libKey);
    } else if ($action == 'test'){
        $fileName = $_GET['fileName'] ?? null;
        if (!Config::$isProd) test($service, $fileName, $user->email);
    } 
    ///// POST /////
} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_SERVER["CONTENT_LENGTH"])) {
        if (stripos(ini_get('post_max_size'),'M') && (int)$_SERVER["CONTENT_LENGTH"] > ((int)ini_get('post_max_size') * 1024 * 1024)) {
            respondWithFatalError(413, 'File is too large');
            die();
        }
    }
    require_once('Lang.php');
    $langObj = new Lang();
    $lang = $langObj->serialize();

    $action = $_POST['action'];
    $fileId = $_POST['fileId'] ?? null;
    if ($action == 'borrow') {
        require 'borrow_action.php';
        borrowItem($fileId);
    } else if ($action == 'return'){
        require 'return_action.php';
        returnItem($fileId);
    } else if ($action == 'place_hold'){
        //addHoldToFileProperties($fileId);
    } else if ($action == 'suspend'){
        require 'admin_action.php';
        suspendItem($fileId, true);
    } else if ($action == 'unsuspend'){
        require 'admin_action.php';
        suspendItem($fileId, false);
    } else if ($action == 'trash'){ //sorry REST god
        require 'admin_action.php';
        trashItem($fileId, false);
    } else if ($action == 'edit_item_admin') {
        require 'admin_action.php';
        $partDesc = $_POST['partDesc'];
        $part = $_POST['part'];
        $partTotal = $_POST['partTotal'];
        postItemEditAdmin($fileId, $partDesc, $part, $partTotal);
    } else if ($action == 'upload'){
        require 'upload_action.php';
        $libKey = $_POST['libKey'] ?? null;
        $uploadedFile = $_FILES['uploaded_file'];
        adminUploadPost($uploadedFile, $libKey);
    } else if ($action == 'lookup_users') {
        require 'accessible_action.php';
        $names = explode(",", $_POST['names']);
        lookupUser($names);
    } else if ($action == 'add_accessible_users') {
        require 'accessible_action.php';
        $userNames = explode(",", $_POST['userNames']);
        addAccessibleUsers($userNames);
    } else if ($action == 'remove_accessible_users') {
        require 'accessible_action.php';
        $userNames = explode(",", $_POST['userNames']);
        removeAccessibleUsers($userNames);
    } else if ($action == 'update_config_admin') {
        $properties = $_POST['properties'] ?? null;
        $kind = $_POST['kind'] ?? null;
        $libKey = $_POST['libKey'] ?? null;
        require 'admin_action.php';
        updateConfigAdmin($properties, $kind, $libKey);
    } else if ($action == 'update_lang_admin') {
        $properties = $_POST['properties'] ?? null;
        $libKey = $_POST['libKey'] ?? null;
        require 'admin_action.php';
        updateLangAdmin($properties, $libKey);
    } else if ($action == 'update_about_admin'){
        $html = $_POST['html'] ?? null;
        $libKey = $_POST['libKey'] ?? null;
        respondWithData($langObj->editAbout($libKey, $html));
    } else if ($action == 'update_customization_admin') {
        $properties = $_POST['properties'] ?? null;
        $libKey = $_POST['libKey'] ?? null;
        require 'admin_action.php';
        updateCustomizationAdmin($properties, $libKey);
    } else if ($action == 'add_new_library_config_admin') {
        $libName = $_POST['name'] ?? null;
        $libKey = $_POST['key'] ?? null;
        require 'admin_action.php';
        addNewLibrary($libKey, $libName);
    } else if ($action == 'remove_library_config_admin') {
        $libKey = $_POST['key'] ?? null;
        require 'admin_action.php';
        removeLibrary($libKey);
    } else if ($action == 'test') {
        if (!Config::$isProd) test();
    } 
} else {
    die();
}

//for front-end
function getConfig() {
    global $config;
    respondWithData($config->getFrontendConfig());
    die();
}
function getCustomizations() {
    require_once('Customization.php');
    $customization = new Customization();
    respondWithData($customization->serialize());
    die();
}
function getLanguages() {
    require_once('Lang.php');
    $langObj = new Lang();
    $lang = $langObj->serialize();
    return $lang;
}

//for admin config
function getConfigAdmin() {
    global $config;
    respondWithData($config->getAdminConfig());
}
function getCustomizationsAdmin() {
    require_once('Customization.php');
    $custom = new Customization();
    respondWithData($custom->getAdminCustomization());
}
function getLangAdmin() {
    require_once('Lang.php');
    $langObj = new Lang();
    $lang = $langObj->getAdminLang();
    respondWithData($lang);
    die();
}

function compliantBreachNotify(string $error, string $errorId = null)
{
    try {
        errorNotifyEmail("Compliant ERROR: $error", $errorId);
    } catch (Exception $e) {
        //
    }
}

function logout()
{
    global $config;
    global $user;

    if (session_status() == PHP_SESSION_NONE) {
        session_name($config->auth['sessionName']);
        if (Config::$isProd) session_set_cookie_params(0, $config->auth['clientPath'], $config->auth['clientDomain'], $config->auth['clientSecure'], $config->auth['clientHttpOnly']);
        session_start();
    }

    $host = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
    if (!Config::$isProd) $host = str_replace(':8080', ':4200', $host);
    if (!Config::$frontEndHost) {
        $baseDir = rtrim(strtok($_SERVER["REQUEST_URI"], '?'),"/");
        $baseDir = str_replace('/api', '', $baseDir);
    } else {
        $host = rtrim(Config::$frontEndHost,'/');
        $baseDir = '';
    }

    if ($config->libraries[$user->homeLibrary]->authorization['enable'] && $config->libraries[$user->homeLibrary]->authorization['auth']['kind'] == "CAS") {
        try {
            $user = new User(true); //internal call - will throw if not logged in
            if ($user) phpCAS::logout();
        } catch (Exception $e) {
            //already logged out
         }
    } 

    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,$params["path"], $params["domain"],$params["secure"], $params["httponly"]);
    }
    session_destroy();
    setcookie('cdlLogin','0',time(),'/');

    //redirect to log out frontend message page
    header("Location: " . $host . $baseDir . "/logged-out");
}

function test() {
    //echo "hello, I'm a quick function for testing";
}
 