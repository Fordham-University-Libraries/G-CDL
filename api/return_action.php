<?php
//@input fileId
function returnItem($fileId)
{
    global $service;
    global $config;
    global $user;

    $driveFile = $service->files->get($fileId, ['fields' => $config->fieldsGet]);
    $cdlItem = new CdlItem($driveFile);
    $cdlItem->return($user);
}
?>