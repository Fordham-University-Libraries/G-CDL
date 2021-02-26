<?php
//@input fileId
function borrowItem($fileId)
{
    global $service;
    global $config;
    global $user;

    $driveFile = $service->files->get($fileId, ['fields' => $config->fieldsGet]);
    $cdlItem = new CdlItem($driveFile);
    $cdlItem->borrow($user);
}
?>