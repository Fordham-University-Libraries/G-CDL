<?php
//upload is not part or Angular frontend so user can try uploading stuff right after they done initing API
require 'View.php';

function adminUploadGet(string $libKey = null) {
    global $config;
    global $user;

    if (!count($user->isStaffOfLibraries)) respondWithFatalError(401, 'Unauthorized - Get Items Admin');
    if(!$libKey) $libKey = $user->isStaffOfLibraries[0];

    if (!isset($config->libraries[$libKey])) {
        respondWithFatalError(400, 'Error: Unknown Library: ' . $libKey);
    }

    if (!in_array($libKey, $user->isStaffOfLibraries) && $user->email != $config->driveOwner) {
        respondWithFatalError(401, 'This application is only available to certain ' . $config->libraries[$libKey]->name . ' Staff. You are logged in as: ' . $user->userName);
    }

    $view = new View();
    $view->data['user'] = $user->userName;
    $view->data['libraryKey'] = $libKey;
    $view->data['libraryName'] = $config->libraries[$libKey]->name;
    $view->data['regex'] = $config->libraries[$libKey]->ils['itemIdInFilenameRegexPattern'];
    $view->data['trimmedRegex']= trim($view->data['regex'],'/');
    $view->data['ilsApi'] = $config->libraries[$libKey]->ils['api'];
    require_once('Lang.php');
    $langObj = new Lang();
    $lang = $langObj->serialize();
    $uploadLang = str_replace('{{$linkToAdmin}}', '../admin/config/lang', $lang['libraries'][$libKey]['upload']);
    $view->data['lang'] = $uploadLang;

    // render
    $view->render(dirname(__DIR__) . '/api/upload.template.php');
}

function adminUploadPost($uploadedFile, string $libKey) {
    global $config;
    global $service;
    global $user;
    $respondFormat = $_POST['respondFormat'] ?? null;

    if (!isset($config->libraries[$libKey])) {
        respondWithFatalError(400, 'Error: Unknown Library: ' . $libKey);
    }

    if (!in_array($libKey, $user->isStaffOfLibraries) && $user->email != $config->driveOwner) {
        respondWithFatalError(401, 'This application is only available to certain ' . $config->libraries[$libKey]->name . ' Staff. You are logged in as: ' . $user->userName);
    }

    $folderId = $config->libraries[$libKey]->noOcrFolderId;
    if (empty($folderId)) {
        respondWithFatalError(400,'NO Upload Directory for this Library');
    }

    if ($uploadedFile) {
        $dir = Config::getLocalFilePath('','temp');
        $path = $dir . basename($uploadedFile['name']);

        try {
            if (move_uploaded_file($uploadedFile['tmp_name'], $path)) {
                $bib = [
                'bibId' => $_POST['bibId'],
                'itemId' => $_POST['itemId'],
                'title' => $_POST['title'],
            ];
                if ($_POST['author']) {
                    $bib['author'] = $_POST['author'];
                }
                if ($_POST['part']) {
                    $bib['part'] = $_POST['part'];
                }
                if ($_POST['partTotal']) {
                    $bib['partTotal'] = $_POST['partTotal'];
                }
                if ($_POST['partDesc']) {
                    $bib['partDesc'] = $_POST['partDesc'];
                }

                $shouldCreateNoOcr = filter_var($_POST['should_create_no_ocr'], FILTER_VALIDATE_BOOLEAN);
                //do all the works!
                if ($shouldCreateNoOcr) {
                    generateNoOcrVersion($path);
                }
                upload($path, $bib, $libKey, $uploadedFile['name'], $shouldCreateNoOcr);
            } else {
                $errMsg = "There was an error uploading the file, please try again in a little bit! Let your admins know if problem persists";
                if (!$respondFormat) {
                    respondWithHtml($errMsg);
                } else {
                    respondWithError(500, $errMsg);
                }
            }
        } catch (Exception $e) {
            respondWithError(500, $e);
        }
    } else {
        respondWithError(500, 'No File!');
    }
}

function generateNoOcrVersion($filePath)
{
    $filePath = realpath($filePath);
    $respondFormat = $_POST['respondFormat'] ?? null;
    try {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            if (!file_exists(".\cpdf\cpdf.exe")) respondWithError(500, '.\api\cpdf\cpdf.exe (a binary we use to convert PDF) does not exist. see https://github.com/coherentgraphics/cpdf-binaries');
            $output = shell_exec(".\cpdf\cpdf.exe -remove-all-text " . escapeshellarg($filePath) . " -o " . escapeshellarg("$filePath-NO-OCR.pdf") . " 2>&1");   
        } else {
            if (!file_exists("./cpdf/cpdf")) respondWithError(500, './api/cpdf/cpdf (a binary we use to convert PDF) does not exist. see https://github.com/coherentgraphics/cpdf-binaries');
                $output = shell_exec("./cpdf/cpdf -remove-all-text " . escapeshellarg($filePath) . " -o " . escapeshellarg("$filePath-NO-OCR.pdf") . " 2>&1");   
        }
    } catch (Exception $e) {
        $errMsg = $e->getMessage();
        if (!$respondFormat) {
            respondWithHtml($errMsg);
        } else {
            respondWithError(500, $errMsg);
        }
    }

    if (!file_exists("$filePath-NO-OCR.pdf")) {
        $cpdfErrror = 'Fail to create a NO_OCR version.';
        if ($output) $cpdfErrror .= " cpdf output: $output";
        respondWithError(500, $cpdfErrror);
    }
}

function upload($filePath, $bib, $libKey, $orgFileName, $shouldCreateNoOcr = true)
{
    global $user;
    global $config;
    global $service;

    $respondFormat = $_POST['respondFormat'] ?? null;
    if (!isset($config->libraries[$libKey])) {
        respondWithFatalError(400, 'Error: Unknown Library: ' . $libKey);
    }
    if (!in_array($libKey, $user->isStaffOfLibraries) && $user->email != $config->driveOwner) {
        respondWithFatalError(401, 'This application is only available to certain ' . $config->libraries[$libKey]->name . ' Staff. You are logged in as: ' . $user->userName);
    }

    $noOcrFolderId = $config->libraries[$libKey]->noOcrFolderId;
    $withOcrFolderId = $config->libraries[$libKey]->withOcrFolderId;

    $withOcrFile = new Google_Service_Drive_DriveFile;
    $withOcrFile->setName($bib['itemId'] . '.pdf');
    $withOcrFile->setDescription($bib['title']);
    $withOcrFile->setParents([$withOcrFolderId]);
    $withOcrFile->setCopyRequiresWriterPermission(false); //allow viewer to copy/download
    if (!$shouldCreateNoOcr) $bib['shouldCreateNoOcr'] = false;
    $withOcrFile->setAppProperties($bib);
    $data = file_get_contents($filePath);
    $withOcrFile = retry(function () use ($service, $withOcrFile, $data) {
        return $service->files->create($withOcrFile, array(
            'data' => $data,
            'mimeType' => 'application/pdf',
            'uploadType' => 'multipart'
        ));
    });
    
    $noOcrFile = new Google_Service_Drive_DriveFile;
    if ($shouldCreateNoOcr) {
        $noOcrFile->setName($bib['itemId'] . "-No-OCR.pdf");
        $data = file_get_contents("$filePath-NO-OCR.pdf");
    } else {
        $noOcrFile->setName($bib['itemId']);
        $data = file_get_contents($filePath);
    }

    $noOcrFile->setDescription($bib['title']);
    $noOcrFile->setParents([$noOcrFolderId]);
    $noOcrFile->setCopyRequiresWriterPermission(true);
    $bib['fileWithOcrId'] = $withOcrFile->getId();
    $noOcrFile->setAppProperties($bib);
    $noOcrFile = retry(function () use ($service, $noOcrFile, $data) {
        return $service->files->create($noOcrFile, array(
            'data' => $data,
            'mimeType' => 'application/pdf',
            'uploadType' => 'multipart'
        ));
    });

    //remove temp
    if (file_exists("$filePath-NO-OCR.pdf")) unlink("$filePath-NO-OCR.pdf");
    if (file_exists($filePath))unlink($filePath);

    if (!$respondFormat) {
        $html = "<h1>Success!</h1>";
        $html .= "<div>The file ".  basename($orgFileName) . " has been uploaded, processed, and added the the CDL app</div><br><div><strong><a href='./?action=admin_upload'>Upload another one</a></strong></div><br>";
        $html .= "<div style='color: brown'>Click the link above to add more. <strong>Do NOT refresh this page</strong>, otherwise, it'll re-upload the same file you've just added!</div>";
        respondWithHtml($html);
    } else {
        respondWithData([
            'success' => true,
            'fileName' => basename($orgFileName),
            'uploadedNoOcrFileId' => $noOcrFile->getId()
        ]);
    }
}
?>