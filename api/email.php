<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function email(string $kind, User $user, CdlItem $cdlItem)
{
    global $config;
    global $lang;
    if (!$lang) $lang = getLanguages();

    $host = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
    $baseDir = rtrim(strtok($_SERVER["REQUEST_URI"], '?'), "/");
    $baseDir = str_replace('/api', '', $baseDir);

    // gmail
    if ($config->emails['method'] == 'gMail') {
        $client = getClient();
        try {
            $gmailService = new Google_Service_Gmail($client);
        } catch (Google_Service_Exception $e) {
            logError('cannot email with Gmail');
            logError($e->getMessage());
            return 0;
        }
        $sender = 'me'; //The special value **me** can be used to indicate the authenticated user. (in this case, the driveOwner)
        $toName = isset($user->fullName) ? $user->fullName : $user->userName;
        $toEmail = $user->email;
        $strSubject = '';
        $strBody = '';
        if ($kind == 'borrow') {
            $strSubject = str_replace('{{$title}}', $cdlItem->title, $lang['libraries'][$cdlItem->library]['emails']['borrowSubject']);
            $strBody = str_replace('{{$title}}', $cdlItem->title, $lang['libraries'][$cdlItem->library]['emails']['borrowBody']);
            $strBody = str_replace('{{$libraryName}}', $config->libraries[$cdlItem->library]->name, $strBody);
            $strBody = str_replace('{{$borrowingPeriod}}', '' . $config->libraries[$cdlItem->library]->borrowingPeriod, $strBody);
            $strBody = str_replace('{{$readLink}}', $host . $baseDir . '/read', $strBody);
            $strBody = str_replace('{{$returnLink}}', $host . $baseDir . '/return', $strBody);
        } elseif ($kind == 'return') {
            $strSubject = str_replace('{{$title}}', $cdlItem->title, $lang['libraries'][$cdlItem->library]['emails']['returnSubject']);
            $strBody = str_replace('{{$title}}', $cdlItem->title, $lang['libraries'][$cdlItem->library]['emails']['returnBody']);
            $strBody = str_replace('{{$libraryName}}', $config->libraries[$cdlItem->library]->name, $strBody);
        } else {
            return 0;
            die();
        }

        $oauth2 = new \Google_Service_Oauth2($client);
        $userInfo = $oauth2->userinfo->get();
        $fromEmail = $userInfo->email;
        $strRawMessage = "From: \"$config->appName\"<$fromEmail>\r\n";
        $strRawMessage .= "To: \"$toName\"<$toEmail>\r\n";
        $strRawMessage .= 'Subject: =?utf-8?B?' . base64_encode($strSubject) . "?=\r\n";
        $strRawMessage .= "MIME-Version: 1.0\r\n";
        $strRawMessage .= "Content-Type: text/html; charset=utf-8\r\n";
        $strRawMessage .= 'Content-Transfer-Encoding: quoted-printable' . "\r\n\r\n";
        $strRawMessage .= "$strBody\r\n";
        $mime = rtrim(strtr(base64_encode($strRawMessage), '+/', '-_'), '='); // The message needs to be encoded in Base64URL
        $msg = new Google_Service_Gmail_Message();
        $msg->setRaw($mime);
        try {
            $gmailService->users_messages->send($sender, $msg);
            return 1;
        } catch (Exception $e) {
            return 0;
        }
    } else if ($config->emails['method'] == 'SMTP') {
        // SMTP
        $fromEmail = $config->emails['SMTP']['fromEmail'];
        $fromName = $config->emails['SMTP']['fromName'];

        $mailer = new PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = $config->emails['SMTP']['host'];
        $mailer->Port = $config->emails['SMTP']['port'];
        $mailer->SMTPAuth = false;
        $mailer->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        $mailer->isHTML(true);
        $mailer->setFrom($fromEmail, $fromName);
        $mailer->addAddress($user->email);
        //$mailer->addBCC();
        if ($kind == 'borrow') {
            $mailer->Subject = str_replace('{{$title}}', $cdlItem->title, $lang['libraries'][$cdlItem->library]['emails']['borrowSubject']);
            $strBody = str_replace('{{$title}}', $cdlItem->title, $lang['libraries'][$cdlItem->library]['emails']['borrowBody']);
            $strBody = str_replace('{{$libraryName}}', $config->libraries[$cdlItem->library]->name, $strBody);
            $strBody = str_replace('{{$borrowingPeriod}}', '' . $config->libraries[$cdlItem->library]->borrowingPeriod, $strBody);
            $strBody = str_replace('{{$readLink}}', $host . $baseDir . '/read', $strBody);
            $mailer->Body = str_replace('{{$returnLink}}', $host. $baseDir . '/return', $strBody);
        } elseif ($kind == 'return') {
            $mailer->Subject = str_replace('{{$title}}', $cdlItem->title, $lang['libraries'][$cdlItem->library]['emails']['returnSubject']);
            $strBody = str_replace('{{$title}}', $cdlItem->title, $lang['libraries'][$cdlItem->library]['emails']['returnBody']);
            $mailer->Body = str_replace('{{$libraryName}}', $config->libraries[$cdlItem->library]->name, $strBody);
        } else {
            return 0;
            die();
        }

        try {
            $mailer->send();
            return 1;
        } catch (Exception $e) {
            return 0;
        }
    }
}

function errorNotifyEmail($message, $errorId)
{
    global $config;
    //check so we don't email about same items too repeatedly
    if ($errorId) {
        $fileName = Config::getLocalFilePath('errorEmailLogs.json');
        if (file_exists($fileName)) {
            $file = file_get_contents($fileName);
            $logs = json_decode($file, true);
            if (isset($logs[$errorId])) {
                if (($logs[$errorId] - time()) < 60 * 60) { //just email about it less than an hour ago, skip
                    return;
                }
                $logs[$errorId] = time();
            } else {
                $logs[$errorId] = time();
            }
            $file = fopen($fileName, 'wb');
            fwrite($file, json_encode($logs));
            fclose($file);
        } else {
            $logs = [$errorId => time()];
            $file = fopen($fileName, 'wb');
            try {
                fwrite($file, json_encode($logs));
                fclose($file);
            } catch (Exception $e) {
                logError($e);
            }
        }
    }

    $client = getClient();
    try {
        $gmailService = new Google_Service_Gmail($client);
    } catch (Google_Service_Exception $e) {
        logError('cannot email with Gmail');
        logError($e->getMessage());
        return;
    }

    $sender = 'me';
    $strSubject = $config->appName . ' (CDL app) ERROR notification!';
    $to = '<' . $config->driveOwner . '>';
    if (count($config->appSuperAdmins) > 1) {
        $appSuperAdmins = array_map(function ($user, $gSuitesDomain) {
            return '<' . $user . '@' . $gSuitesDomain . '>';
        }, $config->appSuperAdmins, array_fill(0, count($config->appSuperAdmins), $config->gSuitesDomain));
        $to .= "," . implode(',', $appSuperAdmins);
    } else if (count($config->appSuperAdmins) == 1) {
        $to .= ',<' . $config->appSuperAdmins[0] . '@' . $config->gSuitesDomain . '>';
    }
    $strRawMessage = "From: CDL APP ERROR NOTIFIER <$config->driveOwner>\r\n";
    $strRawMessage .= "To: $to\r\n";
    $strRawMessage .= 'Subject: =?utf-8?B?' . base64_encode($strSubject) . "?=\r\n";
    $strRawMessage .= "MIME-Version: 1.0\r\n";
    $strRawMessage .= "Content-Type: text/html; charset=utf-8\r\n";
    $strRawMessage .= 'Content-Transfer-Encoding: quoted-printable' . "\r\n\r\n";
    $strRawMessage .= "$message\r\n";
    $mime = rtrim(strtr(base64_encode($strRawMessage), '+/', '-_'), '='); // The message needs to be encoded in Base64URL
    $msg = new Google_Service_Gmail_Message();
    $msg->setRaw($mime);
    try {
        $gmailService->users_messages->send($sender, $msg);
        return 1;
    } catch (Exception $e) {
        return 0;
    }
}
