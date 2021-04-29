<?php
/**
 * Returns an authorized API client.
 * @return Google_Client the authorized client object
 */
//for app / as drive owner
function getClient($authCode = null, $state = null)
{
    $credsPath = Config::getLocalFilePath('credentials.json', 'creds');
    if(!file_exists($credsPath)) throw new Exception('getClient error: no credentials');

    $client = new Google_Client();
    $scopes = [
        Google_Service_Drive::DRIVE_FILE,
        Google_Service_Drive::DRIVE_APPDATA,
        Google_Service_Gmail::GMAIL_SEND,
        Google_Service_PeopleService::DIRECTORY_READONLY,
        Google_Service_Oauth2::USERINFO_EMAIL,
        Google_Service_Oauth2::USERINFO_PROFILE,
    ];
    $client->setApplicationName('GDRIVE CDL APP'); //will show as editor of file and etc...
    $client->setScopes($scopes);
    $client->setAuthConfig($credsPath);
    $client->setAccessType('offline');
    $client->setPrompt('select_account consent');

    $tokenPath = Config::getLocalFilePath('token.json', 'creds');
    //if no token
    if (!file_exists($tokenPath)) {
        $host = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
        $baseDir = rtrim(strtok($_SERVER["REQUEST_URI"], '?'),"/");
        $redirectUri = $host .  $baseDir . '/?action=login';
        //check if redirect URI is set in creds.json
        $authConfig = file_get_contents($credsPath);
        $authConfig = json_decode($authConfig, true);
        $redirectUrisInCreds = $authConfig[array_keys($authConfig)[0]]['redirect_uris'];
        if (!in_array($redirectUri, $redirectUrisInCreds)) {
            respondWithFatalError(500, "ERROR! redirectUri NOT set in credentials.json. The app is trying to set it to $redirectUri but your credentials.json only has [" . join(',', $redirectUrisInCreds) . "]. If you add more redirectUri on Gogole Dev Console after you downloaded the credentials.json, try re-download it!");
        }

        $client->setRedirectUri($redirectUri);
        if ($state && $state == 'init') {
            // Return authorization URL
            $client->setState($state);
            return [
                'authUrl' => $client->createAuthUrl(),
                'scopes' => $scopes
            ];
        } else if ($authCode) {
            // has authCode (redirected back from Goolge)
            // Exchange authorization code for an access token.
            $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
            // Check accessToken to see if there was an error.
            if (array_key_exists('error', $accessToken)) {
                throw new Exception("\$client->fetchAccessTokenWithAuthCode() ERROR: [" . join(', ', $accessToken) . "]. Tried to fetch with authCode: $authCode");
            }

            $client->setAccessToken($accessToken);
            //check
            if(!$client->verifyIdToken()) {
                respondWithFatalError('401', 'Invalid Token');
                die();
            }
            // Save the token to a file.
            if (!file_exists(dirname($tokenPath))) {
                mkdir(dirname($tokenPath), 0700, true);
            }
            file_put_contents($tokenPath, json_encode($client->getAccessToken()));
            return $client;
        } else { 
            //no auth code, not trying to login
            //error
            return null;
        }
    } else {
        //if there's token
        $client->setAccessToken(json_decode(file_get_contents($tokenPath), true));
        // If token expired.
        if ($client->isAccessTokenExpired()) {
            // Refresh the token if possible, else fetch a new one.
            if ($client->getRefreshToken()) {
                $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            }
            //check
            if(!$client->verifyIdToken()) {
                logError('Cannot Refresh OAuth Token, most likely its been revolked / app is disconnected from GDrive');
                respondWithFatalError('401', 'Cannot Refresh Token');
                die();
            }

            // Save the token to a file.
            if (!file_exists(dirname($tokenPath))) {
                mkdir(dirname($tokenPath), 0700, true);
            }
            file_put_contents($tokenPath, json_encode($client->getAccessToken()));    
        }
        return $client;
    }
}

//for end user
function endUserGoogleLogin($authCode = null, $target = null, $apiAction = null)
{
    global $config;
    if (session_status() == PHP_SESSION_NONE) {
        session_name($config->auth['sessionName']);
        if (Config::$isProd) session_set_cookie_params(0, $config->auth['clientPath'], $config->auth['clientDomain'], $config->auth['clientSecure'], $config->auth['clientHttpOnly']);
        session_start();
    }

    $host = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
    $baseDir = rtrim(strtok($_SERVER["REQUEST_URI"], '?'),"/");

    //already login
    if (isset($_SESSION['gUserName']) && $_SESSION['gExpire'] > time()) {
        $_SESSION['gExpire'] = time() + ($config->auth['sessionTtl'] * 60);
        //redirect
        if($apiAction) {
            //to api
            $url = $host . $baseDir . '/?action=' . $apiAction;
            header("Location: " . $url);
            die();
        } else {
            //redirect to front end
            if (!Config::$isProd) { 
                echo "not prod";
                $host = str_replace(':8080', ':4200', $host);
            } else {
                if ($config::$frontEndHost) {
                    $host = rtrim($config::$frontEndHost, '/');
                    $url = $host;
                } else {
                    //samehost as API
                    $baseDir = str_replace('/api', '', $baseDir);
                    $url = $host . $baseDir;
                }
            }
            if ($target) $url .= $target;
            echo $url;
            header("Location: " . $url);

            die();
        }
    }

    $client = new Google_Client();
    $credsPath = Config::getLocalFilePath('credentials.json', 'creds');
    if(!file_exists($credsPath)) respondWithFatalError(500, 'no credentials');
    $client->setAuthConfig($credsPath);
    $client->setAccessType('online');
    $scopes = [
        Google_Service_Oauth2::USERINFO_EMAIL,
        Google_Service_Oauth2::USERINFO_PROFILE, //See your personal info, including any personal info you've made publicly available.
    ];
    $client->addScope($scopes);

    $client->setHostedDomain($config->auth['gSuitesDomain']);
    $redirectUrl = $host . $baseDir . "/?action=login";
    $client->setRedirectUri($redirectUrl);
    if (!$authCode) {
        //send user to login at Google
        //pass original target user wants to do along, so we can redirect later
        if ($target) {
            $client->setState("target!!$target");
        } else if ($apiAction) {
            $client->setState("apiAction!!$apiAction");
        }
        $auth_url = $client->createAuthUrl();
        header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
        die();
    } else {
        //come back from Google with authcode
        $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
        if (array_key_exists('error', $accessToken)) {
            logError('end users G-OAuth failed ' . join(', ', $accessToken));
            die('Login Error');
            //throw new Exception(join(', ', $accessToken));
        }
        $oauth2 = new \Google_Service_Oauth2($client);
        $userInfo = $oauth2->userinfo->get();

        if ($userInfo->hd == $config->auth['gSuitesDomain']) {
            $_SESSION["gUserName"] = str_replace($config->emailDomain, '', $userInfo->email);
            $_SESSION["gEmail"] = $userInfo->email;
            $_SESSION["gFullName"] = $userInfo->name;
            $_SESSION["photoUrl"] = $userInfo->picture;
            $_SESSION['gExpire'] = time() + ($config->auth['sessionTtl'] * 60);
            //redirect
            //state format: frontOrBackend!!targetPath
            if (isset($_GET['state'])) {
                $states = explode('!!', $_GET['state']);
            }
            if(isset($states) && count($states) == 2 && $states[0] == 'apiAction') {
                //to api
                $url = $host . $baseDir . '/?action=' . $states[1];
                header("Location: " . $url);
                die();
            } else {
                //to frontend
                if (!Config::$isProd) { 
                    $host = str_replace(':8080', ':4200', $host);
                } else {
                    if ($config::$frontEndHost) {
                        $host = rtrim($config::$frontEndHost, '/');
                    } else {
                        //samehost as API
                        $host .= str_replace('/api','',$baseDir);
                    }
                }
                if ($states) $target = $states[1];
                $url = $host . $target;
                header("Location: " . $url);
                die();
            }
        } else {
            echo "Please login with " . $config->auth['gSuitesDomain']  . " email";
        }
    }
}
