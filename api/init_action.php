<?php
require 'View.php';

function init($step = 1, $authCode = null)
{
    $hasCreds = file_exists('./private_data/credentials.json');
    if ($hasCreds) {
        global $config;
        $config = new Config();

        if ($config) {
            if (count($config->libraries)) {
                require 'view_all_action.php';
                $configs = $config->getFrontendConfig();
                $defaultLibrary = $configs['defaultLibrary'] ?? null;
                $client = getClient();
                global $service;
                $service = new Google_Service_Drive($client);
                if ($defaultLibrary) {
                    $items = getAllFiles($defaultLibrary, null, true);
                }
                if (count($items['items'])) {
                    die('The App has been initialized and has items in collection! You are good to go!');
                }
            }
        }
    }

    $view = new View();
    $view->data['hasCreds'] = $hasCreds;
    if ($hasCreds) {
        $creds = file_get_contents('./private_data/credentials.json');
        $creds = json_decode($creds, true);
        $view->data['creds'] = $creds['web'];
        $view->data['creds']['project_id'] = '🤐 ⬛⬛⬛⬛⬛ 🕵️';
        $view->data['creds']['client_secret'] = '🤐 ⬛⬛⬛⬛⬛ 🕵️';
        //if it's local 
        if (in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1','::1'])) {
            $authed = true;
            $view->data['authed'] = $authed;
            $view->data['showNext'] = true;
        } else {
            //if it's not local, ask for project_id
            $cookie_name = "cdl_app_init_project_id";
            if ($_POST['project_id']) {
                if ($_POST['project_id'] == $creds['web']['project_id']) {
                    setcookie($cookie_name, $_POST['project_id'], 0, "/");
                    $authed = true;
                    $view->data['authed'] = $authed;
                    $view->data['showNext'] = true;
                } else {
                    $view->data['errMsg'] = "That's not a correct project_id, may be just go open up the credentials.json file that you've just added to the app and copy that value and paste it here?";
                }
            } elseif (isset($_COOKIE[$cookie_name])) {
                if ($_COOKIE[$cookie_name] == $creds['web']['project_id']) {
                    $authed = true;
                    $view->data['authed'] = $authed;
                    $view->data['showNext'] = true;
                }
            }
        }
    }
    $hasToken = file_exists('./private_data/token.json');

    $view->data['hasToken'] = $hasToken;
    $view->data['showRefresh'] = false;

    if (!$authed || !$hasCreds) $step = 1;
    if (!$hasToken && $step > 2) $step = 2;
    $view->data['step'] = $step;

    $view->data['scopeDefinitions'] = [
        Google_Service_Oauth2::USERINFO_EMAIL => 'View your email address',
        Google_Service_Oauth2::USERINFO_PROFILE => 'View your personal info, including any personal info you\'ve made publicly available',
        Google_Service_PeopleService::DIRECTORY_READONLY => 'read your org GSuites\' directory (to get end users info)',
        Google_Service_Drive::DRIVE_FILE => 'Allow app the create files on your Drive (the app only have access to files it created)',
        Google_Service_Drive::DRIVE_APPDATA => 'Allows access to the Application Data folder. (for storing app\'s config)',
        Google_Service_Gmail::GMAIL_SEND => 'Allows the app to send email on your behalf (for sending borrow/return notifications to users)',
        Google_Service_DriveActivity::DRIVE_ACTIVITY_READONLY => 'Allows the app to read your Drive Activity (for sending auto return notifications to users with webHook)'
    ];

    if ($authCode) { //redirect back from Google login, generate token and init folders and etc.
        $client = getClient($authCode);  //gen token
        if(!initFolders($client)) {
            die('Error: this application is designed to be used with G Suite (now Google Workspace) only i.e. you log in to your work Gmail as jdoe@myinstitution.edu. You tried to login with @gmail.com account.');
        }
    } else if ($step == 1) { //create creds
        if (!$hasCreds) $view->data['showRefresh'] = true;
    } else if ($step == 2) { //gen token
        if ($hasToken) {
            $client = getClient();
            $oauth2 = new \Google_Service_Oauth2($client);
            $userInfo = $oauth2->userinfo->get();
            $view->data['driveOwner'] = $userInfo->email;
            $tokenPath = './private_data/token.json';
            $token = json_decode(file_get_contents($tokenPath), true);
            $view->data['scopes'] = explode(" ", $token['scope']);
            $client = getClient();
        } else { 
          $authUrlInfo = getClient(null, 'init'); //will return auth info if no token & no authcode
          $view->data['authUrl'] = $authUrlInfo['authUrl'];
          $view->data['scopes'] = $authUrlInfo['scopes'];
          $view->data['showNext'] = false;
      } 
    } else if ($step == 3) { //add first library
        global $user;
        $config = new Config();
        try {
            $user = new User(true);
            if (!$user->isDriveOwner) die("only drive owner can init stuff! please log in with the account " . $config->driveOwner . '@' . $config->gSuitesDomain);
        } catch (Exception $e) {
            endUserGoogleLogin(null,null,'init&step=3');
        }
        //GET        
        if (!$_POST['libKey'] && !$_POST['libName']) {
            $view->data['userName'] = $user->userName;
            $view->data['libKey'] = array_key_first((array) $config->libraries) ?? 'main';
            $view->data['libName'] = $config->libraries[$view->data['libKey']]->name;
            $view->data['borrowPeriod'] =  $config->libraries[$view->data['libKey']]->borrowingPeriod ?? 2;
            $view->data['cooldown'] = $config->libraries[$view->data['libKey']]->backToBackBorrowCoolDown ?? 60;
            $view->data['driveOwner'] = $config->driveOwner;
            $view->data['gSuitesDomain'] = $config->auth['gSuitesDomain'];
            if (count($config->libraries)) {
                $view->data['admins'] = implode(',', $config->libraries[$view->data['libKey']]->admins);
                $view->data['staff'] = implode(',', $config->libraries[$view->data['libKey']]->staff);
            } else {
                $view->data['showNext'] = false;
            }
        } elseif ($_POST['libKey'] && $_POST['libName']) {
            $libKey = $_POST['libKey'];
            if (!ctype_alpha($libKey)) die('Error: Library Short Name Must be Aplha only');
            $libName = $_POST['libName'];
            $options = [];
            $options['borrowingPeriod'] = $_POST['borrowPeriod'];
            $options['backToBackBorrowCoolDown'] = $_POST['cooldown'];
            if ($_POST['admins']) {
                $options['admins'] = array_map('trim', explode(",", $_POST['admins']));
            }
            if ($_POST['staff']) {
                $options['staff'] = array_map('trim', explode(",", $_POST['staff']));
            }
            $config->createNewLibrary($libKey, $libName, $options);
            header("location: ./?action=init&step=3");
            die();
        } 
    } else if ($step == 4) { //next step info
        global $user;
        try {
            $user = new User(true);
            if (!$user->isDriveOwner) die("only drive owner can init stuff! please log in with the account " . $config->driveOwner . '@' . $config->gSuitesDomain);
        } catch (Exception $e) {
            endUserGoogleLogin(null,null,'init&step=4');
        }
        $host = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
        $baseDir = rtrim(strtok($_SERVER["REQUEST_URI"], '?'),"/");
        $view->data['privateDataWritable'] = is_writable('./private_data');
        $view->data['privateTempWritable'] = is_writable('./private_temp');
        $view->data['shellExecEnable'] = is_callable('shell_exec') && false === stripos(ini_get('disable_functions'), 'shell_exec');

        $config = new Config();
        if (!count($config->libraries)) {
            header("location: ./?action=init&step=3");
            die();
        } else {
            $view->data['host'] = $host . $baseDir;
            $view->data['libKey'] = array_key_first((array) $config->libraries);
            $view->data['libName'] = $config->libraries[$view->data['libKey']]->name;
            $view->data['mainFolderId'] = $config->mainFolderId;
            $view->data['borrowPeriod'] =  2;
            $view->data['cooldown'] = 60;
            if ($config->libraries[$view->data['libKey']]->staff) {
                $view->data['staff'] = implode(',', $config->libraries[$view->data['libKey']]->staff);
            }
            $view->data['driveOwner'] = $config->driveOwner;
            $view->data['gSuitesDomain'] = $config->gSuitesDomain;
            $view->data['showNext'] = $false;
        }
    } else {
        header("location: ./?action=init&step=1");
        die();
    }

    // render
    $view->render(dirname(__DIR__) . '/api/init.template.php');
}

function initFolders($client)
{
    //create main folder and config
    $config = new Config();
    if (!$config || !isset($config->mainFolderId)) {
        //get user info
        $oauth2 = new \Google_Service_Oauth2($client);
        $userInfo = $oauth2->userinfo->get();
        //only allow GSuites account
        if (strpos($userInfo->email, '@gmail.com') !== false) {
            return false;
        }

        $client = getClient();
        $driveService = new Google_Service_Drive($client);
        $driveFile = new Google_Service_Drive_DriveFile;
        $driveFile->setName("CDL APP");
        $driveFile->setDescription("Folder for CDL Application data, contains the PDF files and etc. No dot touch it directly");
        $driveFile->setMimeType("application/vnd.google-apps.folder");
        try {
            $mainFolder = $driveService->files->create($driveFile);
        } catch (Exception $e) {
            die('failed');
        }
        
        //create sheet to store accessible users
        $driveFile = new Google_Service_Drive_DriveFile;
        $driveFile->setName("Accessible Users");
        $driveFile->setParents([$mainFolder->getId()]);
        $driveFile->setDescription("Spreadsheet for CDL Application accessible users data. Do NO touch it directly");
        $driveFile->setMimeType("application/vnd.google-apps.spreadsheet");
        try {
            $accessbileUsersSheet = $driveService->files->create($driveFile);
        } catch (Exception $e) {
            die('failed');
        }

        //save mainFolder id and etc.to AppConfig
        $configDriveFileMetadata = new Google_Service_Drive_DriveFile([
            'name' => 'config.json',
            'parents' => ['appDataFolder']
        ]);
        $config = [
            'driveOwner' => $userInfo->email,
            'gSuitesDomain' => preg_replace('/.*@/', '', $userInfo->email),
            'mainFolderId' => $mainFolder->getId(),
            'accessibleUsersSheetId' => $accessbileUsersSheet->getId()
        ];
        $configDriveFile = $driveService->files->create($configDriveFileMetadata, array(
            'data' => json_encode($config),
            'mimeType' => 'application/json',
            'uploadType' => 'multipart',
            'fields' => 'id'));

        //respondWithData(['configFileId' => $configDriveFile->getId()]);
        //die();
    }

    //redirect
    header("location: ./?action=init&step=3");
    die();
}
