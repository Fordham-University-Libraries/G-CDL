<?php
class CdlItem
{
    private Google_Service_Drive_DriveFile $driveFile;
    private int $borrowingPeriod;
    private int $backToBackBorrowCoolDown;
    private array $lang;
    private string $currentlyCheckedOutToUser;
    public string $id;
    public string $parentId;
    public string $name; //file name
    public string $title;
    public string $author;
    public $bibId;
    public $itemId;
    public bool $available = true;
    public $createdTime; //zulu date
    public $library; //item of which library
    public int $part;
    public int $partTotal;
    public string $partDesc;
    public string $fileWithOcrId;
    public string $due; //zulu date
    public bool $isCheckedOutToMe = false;
    public int $lastReturned;
    public int $lastBorrowed;
    public string $lastViewer;
    public string $url;
    public bool $isSuspended = false;
    public bool $isTrashed  = false;
    public string $accessibleFileId;
    public string $webContentLink; //????
    public string $downloadLink;
    public $ilsMetadata = [
        'publisher' => '',
        'pubDate' => '',
        'physDesc' => '',
        'isbn' => ''
    ];
    
    public function __construct(Google_Service_Drive_DriveFile $driveFile)
    {
        global $config;
        global $lang;
        $this->driveFile = $driveFile;
        $this->id = $driveFile->getId();
        $this->parentId = $driveFile->getParents()[0];
        $this->name = $driveFile->getName();
        $this->title = $driveFile->getDescription();
        $this->createdTime = $driveFile->getCreatedTime();
        $this->isTrashed = $driveFile->getTrashed() ?? false;
        foreach ($driveFile->getAppProperties() as $key => $value) {
            $rp = new ReflectionProperty($this, $key);
            $propType = $rp->getType();
            if ($propType && ($propType->getName() == gettype($value))) {
                $this->$key = $value;
            } else if ($propType && $propType->getName() == 'bool') {
                    $this->$key = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            } else if (!$propType) {
                $this->$key = $value;   
            }
        }
        $this->checkPerms($driveFile);
        foreach ($config->libraries as $key => $library) {
            if (isset($library->isDefault) && $library->isDefault) $defaultLibrary = $key;
            if ($library->noOcrFolderId == $this->parentId) {
                $this->library = $key;
                $this->borrowingPeriod = $library->borrowingPeriod;
                $this->backToBackBorrowCoolDown = $library->backToBackBorrowCoolDown;
                if ($defaultLibrary) break;
            }
        }
        if (!isset($this->library)) {
            respondWithError(401, $lang[$defaultLibrary]['error']['item']['notPartOfCollecton']);
            die();
        }

        //check that the app is the owner of the file -- needs to be to be able to setCopyRequiresWriterPermission()
        if (!$this->driveFile->getOwnedByMe()) {
            respondWithError(401, $lang[$this->library]['error']['item']['notOwnedByMe']);
            die();
        }
        //$this->ilsMetadata = $driveFile->getId();
    }

    private function checkPerms()
    {
        global $user;
        global $config;

        $permissions = $this->driveFile->getPermissions();
        $viewerCount = 0;
        foreach ($permissions as $permission) {
            if ($permission->getRole() == 'reader') {
                $viewerCount++;
                if ($viewerCount > 1) {
                    compliantBreachNotify('File ' . $this->name . '(' . $this->id . ') has more than one viewer!', $this->id);
                }
                $this->available = false;
                $this->currentlyCheckedOutToUser = $permission->getEmailAddress();
                if (!$permission->getExpirationTime()) {
                    compliantBreachNotify('File ' . $this->name . '(' . $this->id . ') is shared to viewer WITHOUT expiration time!', $this->id);
                } else {
                    $this->due = $permission->getExpirationTime();
                }
                if ($user->email == $permission->getEmailAddress() &&  $permission->getRole() == 'reader') {
                    $this->isCheckedOutToMe = true;
                    $this->url = $this->driveFile->getWebViewLink();
                    if ($user->isAccessibleUser) {
                        //NOT SURE
                        $this->url = "https://drive.google.com/a/" . $config->auth['gSuitesDomain'] . "/uc?id=" . $this->fileWithOcrId;
                        $this->downloadLink = "https://drive.google.com/a/" . $config->auth['gSuitesDomain'] . "/uc?id=" . $this->fileWithOcrId . "&export=download";
                    }
                } else {
                    $this->isCheckedOutToMe = false;
                }
            }
        }
    }

    public function borrow(User $user)
    {
        global $isProd;
        global $service;
        global $config;
        global $lang;
        
        //check that file don't already have a viewer
        if (!$this->available) {
            respondWithError(401, $lang[$this->library]['error']['borrow']['notAvailHaveOtherViewer']);
            die();
        }
        //check that the file is net trashed or being suspended
        if ($this->isSuspended || $this->isTrashed) {
            respondWithError(401, $lang[$this->library]['error']['borrow']['notAvailGeneric']);
            die();
        }
        //check if user is drive owner
        if ($user->isDriveOwner) {
            respondWithError(400, 'can NOT borrow item since you are logged in as the drive owner (cannot add you as a "viewer" since you are already the owner of the file)');
            die();
        }

        $permissions = $this->driveFile->getPermissions();

        //check that user haven't just returned the item ($config->backToBackBorrowCoolDown minutes cooldown)
        //also check at title level (if title has mulitple copies, user shold not be able to borrow other copies of the same title right away)
        //BUT if it's a 'mulit-parts' item, it should allow e.g. can borrow book 'foo part 2 of 2' right after just returned 'foo part 1 of 2'
        $appProps = $this->driveFile->getAppProperties();
        $optParams = array(
            'q' => "appProperties has {key='bibId' and value='$this->bibId'} AND '$this->parentId' in parents AND mimeType != 'application/vnd.google-apps.folder' AND NOT appProperties has {key='isSuspended' and value='true'} AND trashed = false",
            'pageSize' => 100,
            'fields' => $config->fields,
        );
        $results = $service->files->listFiles($optParams);
        foreach ($results->getFiles() as $eachFile) {
            $eachAppProps = $eachFile->getAppProperties();
            if (!isset($this->part)) {
                if (isset($eachAppProps['lastViewer']) && $eachAppProps['lastViewer'] == $user->email) {
                    if (isset($eachAppProps['lastReturned']) && (time() - $eachAppProps['lastReturned']) < ($this->backToBackBorrowCoolDown * 60)) {
                        if ($eachFile->getId() == $this->id) {
                            $errMsg = str_replace('{{$backToBackBorrowCoolDown}}', $this->backToBackBorrowCoolDown, $lang[$this->library]['error']['borrow']['backToBack']);
                            respondWithError(401, $errMsg);
                        } else {
                            $errMsg = str_replace('{{$backToBackBorrowCoolDown}}', $this->backToBackBorrowCoolDown, $lang[$this->library]['error']['borrow']['backToBackCopy']);
                            respondWithError(401, $errMsg);
                        }
                        die();
                    }
                }
            } else {
                //it's a mulit-part item, only check if it's the same part
                if (isset($eachAppProps['lastViewer']) && $eachAppProps['lastViewer'] == $user->email && $appProps['part'] == $eachAppProps['part']) {
                    if (isset($eachAppProps['lastReturned']) && (time() - $eachAppProps['lastReturned']) < ($this->backToBackBorrowCoolDown * 60)) {
                        if ($eachFile->getId() == $this->id) {
                            $errMsg = str_replace('{{$backToBackBorrowCoolDown}}', $this->backToBackBorrowCoolDown, $lang[$this->library]['error']['borrow']['backToBack']);
                            respondWithError(401, $errMsg);
                        } else {
                            $errMsg = str_replace('{{$backToBackBorrowCoolDown}}', $this->backToBackBorrowCoolDown, $lang[$this->library]['error']['borrow']['backToBackCopy']);
                            respondWithError(401, $errMsg);
                        }
                        die();
                    }
                }
            }
        }
        
        //double check that copy/download protection is on
        if (!$this->driveFile->getCopyRequiresWriterPermission()) {
            $tempFile = new Google_Service_Drive_DriveFile;
            $tempFile->setCopyRequiresWriterPermission(true);
            $service->files->update($this->driveFile->getId(), $tempFile);
        }

        //all good - check out 'normal' copy with no ocr
        $newPermission = new Google_Service_Drive_Permission(array(
            'type' => 'user',
            'role' => 'reader',
            'emailAddress' => $user->email
        ));
        $optParams = array(
            'sendNotificationEmail' => false
        );

        try {
            //exp back off if 500
            $created = retry(function () use ($service, $newPermission, $optParams) {
                return $service->permissions->create($this->id, $newPermission, $optParams);
            });
            $permissionsId = $created->id;
            //$borrowingPeriod = '+' . $config->borrowingPeriod . ' hours';

            $expTime = date("c", strtotime('+' . $this->borrowingPeriod . 'hours')); //RFC 3339 / ISO 8601 date
            $expTimeTimeStamp = date(strtotime('+' . $this->borrowingPeriod . 'hours')); //it'll be used to set lastReturned prop
            //you CAN'T set EXP time on create... no shit!
            //NOTE: sharing with Expiration does NOT available on Shared Drives
            $updatedPermission = new Google_Service_Drive_Permission(array(
                'role' => 'reader',
                'expirationTime' => $expTime
            ));
            //exp back off if 500
            $updated = retry(function () use ($service, $permissionsId, $updatedPermission) {
                return $service->permissions->update($this->id, $permissionsId, $updatedPermission, ['fields' => 'id, expirationTime']);
            });

            $respond = ['borrowSuccess' => true, 'id' => $this->id, 'due' => $updated->expirationTime];
            
            //stats
            logStats($this, 'borrow', $user->homeLibrary, $user->isAccessibleUser ? 1 : 0, count($user->isStaffOfLibraries) ? 1 : 0);
            

            //if set to update ILS status on borrow/return
            if ($config->libraries[$this->library]->ils['api']['enable'] && $config->libraries[$this->library]->ils['api']['changeItemStatusOnBorrowReturn'] && $isProd) {
                setIlsItemStatus(true, $appProps['itemId'], $this->library); //borrow = true
            }

            //auto return notification
            if ($config->notifications['emailOnAutoReturn']['enable']) {
                //write it to file so we can look at later
                $this->due = $expTime;
                $fileName = $config->privateDataDirPath . $config->notifications['emailOnAutoReturn']['dataFile'];
                if (file_exists($fileName)) {
                    $file = file_get_contents($fileName);
                    $currentOutItems = unserialize($file);
                }
                if (!$currentOutItems) $currentOutItems = [];
                array_push($currentOutItems, [
                    'cdlItem' => $this,
                    'user' => $user,
                ]);
                file_put_contents($fileName, serialize($currentOutItems));

                //if webhook is enabled, set up a watch
                if ($config->notifications['emailOnAutoReturn']['method'] == 'webHook') {
                    //webhook
                    //setup watch so we'll get notified via Webhook when item is returned
                    $postBody = new Google_Service_Drive_Channel();
                    $postBody->setId($config->notifications['emailOnAutoReturn']['secret'] .'-'. $this->id);
                    $postBody->setType('web_hook');
                    $postBody->setAddress($config->notifications['emailOnAutoReturn']['publicCronUrl']);
                    //the docs said it's supposed to default to one day -- but... sometime it set to just an hour????
                    //let's set it to four hours, the channel will be stopped by webhook anyway
                    $fourHoursFromNowInMilliSec = round(microtime(true) * 1000) + 1.44e+7;
                    $postBody->setExpiration($fourHoursFromNowInMilliSec);
                    $watchOptParams = [];
                    try {
                        $channel = retry(function () use ($service, $postBody, $watchOptParams) {
                            return $service->files->watch($this->id, $postBody, $watchOptParams);
                        });
                        //debug
                        $respond['channelExp'] = $channel->getExpiration();
                    } catch (Google_Service_Exception $e) {
                        //watch fail
                        $respond['watchError'] = json_decode($e->getMessage());
                        logError($respond['watchError']);
                    }
                }
            }

            //track last borrower -- needed for back to back cooldown check
            //set lastReturned to expected loan expiration time, if user manully return, return.php will update it.
            $tempFile = new Google_Service_Drive_DriveFile;
            $tempFile->setAppProperties(['lastViewer' =>  $user->email, 'lastBorrowed' => time(), 'lastReturned' => $expTimeTimeStamp]);
            $service->files->update($this->id, $tempFile);

            //if it's a 'normal' user -- done
            if (!$user->isAccessibleUser) {
                //email notify user
                if ($config->notifications['emailOnBorrow']) {
                    email('borrow', $user, $this);
                }
                //return
                respondWithData($respond);
            }
        } catch (Google_Service_Exception $e) {
            //borrow fail
            respondWithError(500, "Internal Error - on Borrow");
            logError($e->getMessage());
        }

        //if user is in accessible users list
        //also add perms to the with orc version of the file
        if ($user->isAccessibleUser) {
            try {
                //exp back off if 500
                $withOcrFileId = $appProps['fileWithOcrId'];
                $created = retry(function () use ($service, $withOcrFileId, $newPermission, $optParams) {
                    return $service->permissions->create($withOcrFileId, $newPermission, $optParams);
                });
                $permissionsId = $created->id;
                //set exp
                $updatedPermission = new Google_Service_Drive_Permission(array(
                    'role' => 'reader',
                    'expirationTime' => $expTime
                ));
                $updated = retry(function () use ($service, $withOcrFileId, $permissionsId, $updatedPermission) {
                    return $service->permissions->update($withOcrFileId, $permissionsId, $updatedPermission, ['fields' => 'id, expirationTime']);
                });
                //email notify user
                email('borrow', $user, $this);
                
                //return
                respondWithData($respond);
            } catch (Google_Service_Exception $e) {
                //borrow fail
                respondWithError(500, "Internal Error - on Accessible Borrow");
                logError($e->getMessage());
            }
        }
    }

    public function return(User $user)
    {
        global $isProd;
        global $config;
        global $service;
        global $lang;

        $permissions = $this->driveFile->getPermissions();
        $permissionId; //to be removed
        foreach ($permissions as $permission) {
            if ($permission->getRole() == 'reader' && $permission->getEmailAddress() == $user->email) {
                $permissionId = $permission->getId();
                break;
            }
        }

        if (isset($permissionId)) {
            //remove the perm
            //If successful, this method returns an empty response body.
            try {
                retry(function () use ($service, $permissionId) {
                    $service->permissions->delete($this->id, $permissionId);
                });
            } catch (Google_Service_Exception $e) {
                logError(json_decode($e->getMessage()));
                respondWithError(500, "Internal Error - on Return");
            }
            //stats
            logStats($this, 'manual_return');

            //if set to update ILS status on borrow/return
            if ($config->libraries[$this->library]->ils['api']['enable'] && $config->libraries[$this->library]->ils['api']['changeItemStatusOnBorrowReturn'] && $isProd) {
                setIlsItemStatus(false, $appProps['itemId'], $this->library); //!borrow = return
            }
            
            //it's a manual return, update the lastReturn prop
            $tempFile = new Google_Service_Drive_DriveFile;
            $tempFile->setAppProperties(['lastReturned' => time()]);
            try {
                retry(function () use ($service, $tempFile) {
                    $service->files->update($this->id, $tempFile);
                });
            } catch (Google_Service_Exception $e) {
                logError(json_decode($e->getMessage()));
                respondWithError(500, "Internal Error - on update lastReturn");
            }

            //notification
            //email when manualReturnNotif is enabled && use Cron for autoReturnNotif
            $emailOnManualReturn = $config->notifications['emailOnManualReturn'];
            $emailOnAutoReturn = $config->notifications['emailOnAutoReturn']['enable'];
            if ($emailOnManualReturn) {
                email('return', $user, $this);
            }
            //remove it from cron watch list since it has been manually returned
            if ($emailOnAutoReturn) {
                $cronDataFileName = $config->privateDataDirPath . $config->notifications['emailOnAutoReturn']['dataFile'];
                if (file_exists($cronDataFileName)) {
                    $cronDataFile = file_get_contents($cronDataFileName);
                    $currentOutItems = unserialize($cronDataFile);
                    $newCurrentOutItems = unserialize($cronDataFile);
                    $i = 0;
                    foreach ($currentOutItems as $outItem) {
                        $cdlItem = $outItem['cdlItem'];
                        if ($cdlItem->id == $this->id) {
                            unset($newCurrentOutItems[$i]);
                            file_put_contents($cronDataFileName, serialize($newCurrentOutItems));
                            break;
                        }
                        $i++;
                    }
                }
            } 

            if (!$user->isAccessibleUser) {
                $respond = ['returnSuccess' => true, 'id' => $this->id];
                respondWithData($respond);
            }
        } else {
            respondWithError(400, $lang[$this->library]['error']['return']['userDoesNotHaveItemCheckedOut']);
        }

        //if user is in accessible users list
        //also return the with orc version file
        if ($user->isAccessibleUser) {
            $withOcrFileId = $this->fileWithOcrId;
            $withOcrFile = $service->files->get($withOcrFileId);
            $permissions = $withOcrFile->getPermissions();
            $permissionId; //to be removed
            foreach ($permissions as $permission) {
                if ($permission->getRole() == 'reader' && $permission->getEmailAddress() == $user->email) {
                    $permissionId = $permission->getId();
                    break;
                }
            }

            if (isset($permissionId)) {
                //remove the perm
                try {
                    retry(function () use ($service, $withOcrFileId, $permissionId) {
                        $service->permissions->delete($withOcrFileId, $permissionId);
                    });
                } catch (Google_Service_Exception $e) {
                    logError(json_decode($e->getMessage()));
                    respondWithError(500, "Internal Error - on Accessible Return");
                }
            }

            $respond = ['returnSuccess' => true, 'id' => $this->id];
            respondWithData($respond);
        }
    }

    //for admins
    function adminDownload($accessibleVersion = false)
    {
        global $service;
        global $config;
        global $user;
        if (!count($user->isStaffOfLibraries)) {
            respondWithError(401, "Unauthorized");
            die();
        }
    
        if (!in_array($this->library, $user->isStaffOfLibraries)) {
            respondWithError(401, "Unauthorized - item is of library $this->library, you are " . join(", ", $user->isStaffOfLibraries) . " Staff");
            die();
        }

        $fileId;
        // echo $accessibleVersion;
        // die();

        if(!$accessibleVersion) {
            $fileId = $this->id;
            $fileName = $this->name;

        } else {
            $fileId = $this->fileWithOcrId;
            $fileName = str_replace('No-OCR', 'With-OCR', $this->name);
        }

        $fileMime = $this->driveFile->getMimeType();
        header("Content-type:$fileMime");
        header("Content-Disposition:attachment;filename=$fileName");
        $content = $service->files->get($fileId, array("alt" => "media"));
        echo $content->getBody();
    }

    public function suspend($suspend = true)
    {
        global $service;
        global $config;
        global $user;

        if (!count($user->isStaffOfLibraries)) {
            respondWithError(401, "Unauthorized");
            die();
        }
    
        if (!in_array($this->library, $user->isStaffOfLibraries)) {
            respondWithError(401, "Unauthorized - item is of library $this->library, you are " . join(", ", $user->isStaffOfLibraries) . " Staff");
            die();
        }

        $tempFile = new Google_Service_Drive_DriveFile;
        $tempFile->setAppProperties(['isSuspended' => $suspend]);
        try {
            retry(function () use ($service, $tempFile) {
                $service->files->update($this->id, $tempFile);
            });
            $this->isSuspended = $suspend;
        } catch (Google_Service_Exception $e) {
            respondWithError(500, "Internal Error - on Suspend");
            logError($e->getMessage());
        }
        respondWithData($this->serialize('admin'));
    }

    public function trash()
    {
        global $service;
        global $config;
        global $user;

        if (!count($user->isStaffOfLibraries)) {
            respondWithError(401, "Unauthorized");
            die();
        }
    
        if (!in_array($this->library, $user->isStaffOfLibraries)) {
            respondWithError(401, "Unauthorized - item is of library $this->library, you are " . join(", ", $user->isStaffOfLibraries) . " Staff");
            die();
        }

        $tempFile = new Google_Service_Drive_DriveFile;
        $tempFile->setTrashed(true);
        try {
            retry(function () use ($service, $tempFile) {
                $service->files->update($this->id, $tempFile);
            });
            $this->isTrashed = true;
        } catch (Google_Service_Exception $e) {
            respondWithError(500, "Internal Error");
            logError($e->getMessage());
        }
      
        //also delete the with OCR version
        if(isset($this->fileWithOcrId)) {
            try {
                retry(function () use ($service, $tempFile) {
                    $service->files->update($this->fileWithOcrId, $tempFile);
                });
            } catch (Google_Service_Exception $e) {
                respondWithError(500, "Internal Error - on Trash");
                logError($e->getMessage());
            }
        }
    
        respondWithData($this->serialize('admin'));
    }

    //@input $for ['all', 'item', 'borrow', 'admin']
    public function serialize($for = null)
    {
        global $config;

        $item = [
            'id' => $this->id,
            'name' => $this->name,
            'title' => $this->title,
            'author' => isset($this->author) ? $this->author : null,
            'bibId' => $this->bibId,
            'itemId' => $this->itemId,
            'available' => $this->available,
            'createdTime' => $this->createdTime,
            'library' => $this->library,
        ];

        if (!$this->available) {
            $item['isCheckedOutToMe'] = $this->isCheckedOutToMe;
        }

        if (isset($this->due)) {
            $item['due'] = $this->due;
        }

        if (isset($this->part)) {
            $item['part'] = $this->part;
            $item['partTotal'] = $this->partTotal;
            if (isset($this->partDesc)) {
                $item['partDesc'] = $this->partDesc;
            }
        }

        if ($for == 'borrow') {
            $item['libraryName'] = $config->libraries[$this->library]->name;
            if (isset($this->url)) $item['url'] = $this->url;
            if (isset($this->downloadLink)) $item['downloadLink'] = $this->downloadLink;
        }

        if ($for == 'admin') {
            $item['fileWithOrcId'] = $this->fileWithOcrId;
            $item['lastReturned'] = $this->lastReturned ?? null;
            $item['lastBorrowed'] = $this->lastBorrowed ?? null;
            $item['lastViewer'] = $this->lastViewer ?? null;
            $item['isSuspended'] = $this->isSuspended ?? false;
            $item['isTrashed'] = $this->isTrashed ?? null;
            $item['appProps'] = $this->driveFile->getAppProperties();
        }

        return $item;
    }
}
