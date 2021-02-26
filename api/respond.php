<?php
function respondWithData(array $data = null, bool $allowCreds = false)
{
    global $config;
    if (!$config->isProd) {
        header("Access-Control-Allow-Origin: http://localhost:4200");
        header("Access-Control-Allow-Credentials: true");
    } else {
        if ($allowCreds) header("Access-Control-Allow-Credentials: true");
    }
    header('Content-type: application/json');
    $respond = ['status' => 200, "data" => $data];
    echo json_encode($respond);
}

function respondWithHtml(string $html, bool $allowCreds = false)
{
    global $config;
    if (!$config->isProd) {
        header("Access-Control-Allow-Origin: http://localhost:4200");
        header("Access-Control-Allow-Credentials: true");
    } else {
        if ($allowCreds) header("Access-Control-Allow-Credentials: true");
    }
    header("Content-Type: text/html;");
    echo $html;
}

function respondWithFatalError(int $code, string $errMsg, $errorCode = null)
{
    global $config;
    if (!$config->isProd) {
        header("Access-Control-Allow-Origin: http://localhost:4200");
        header("Access-Control-Allow-Credentials: true");
    }
    $httpStatues = [
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        413 => 'Payload Too Large',
        404 => 'Not Found',
        500 => 'Internal Server Error'
    ];
    header("HTTP/1.0 $code $httpStatues[$code]");
    header('Content-type: application/json');
    $data = [];
    if ($errMsg) $data['error'] = $errMsg;
    $respond = ['status' => $code, "data" => $data];
    echo json_encode($respond);
    die();
}

function respondWithError(int $code, string $errMsg, $recommededAction = null)
{
    //still return 200, but the status code in respond
    global $config;
    if (!$config->isProd) {
        header("Access-Control-Allow-Origin: http://localhost:4200");
        header("Access-Control-Allow-Credentials: true");
    }
    header('Content-type: application/json');
    $data = [];
    if ($errMsg) $data['error'] = $errMsg;
    if ($recommededAction) $data['url'] = $recommededAction;
    $respond = ['status' => $code, "data" => $data];
    echo json_encode($respond);
    die();
}