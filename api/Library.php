<?php
class Library {
    public string $key = 'main';
    public string $name;
    public bool $isDefault;
    public string $withOcrFolderId = '';
    public string $noOcrFolderId = '';
    public string $statsSheetId = '';
    public int $borrowingPeriod = 3; //hours
    public int $backToBackBorrowCoolDown = 60; //minutes
    public array $admins = []; //can edit config of this library
    public array $staff = []; //can manage/upload items of this library
    public array $ils = [
        'kind' => null,
        'itemIdInFilenameRegexPattern' => null, //for fileupload, will extract itemId from file name and query ILS api
        'api' => [
            'enable' => false,
            'base' => null,
            'key' => null,
            'secret' => null,
            'clientId' => null,
            'appId' => null,
            'courseCacheFile' => 'course_cache.php.serialized',
            'courseCacheFileRefreshMinutes' => 1440,
            'changeItemStatusOnBorrowReturn' => false,
            'itemStatus' => [
                'borrow' => 'd',
                'return' => '-'
            ]
        ]
    ];    
    //the app is authenticated by Google OAuth (with specific domain required)
    //but since most gSuites impementation don't include users info, we'll need to authenticate with local system to be able to tell which kind of users are and etc.
    public array $authorization = [
        'enable' => false,
        'customUserHomeLibrary' => [], //manually put username here
        'auth' => [
            'kind' => 'CAS', //CAS
            'CAS' => [
                'protocol' => 'https://',
                'host' => '',
                'context' => '/cas',
                'port' => 443,
                'version' => '3.0',
                'caCertPath' => '/etc/ssl/cacert.pem',
                'attributesMapping' => [
                    'fullName' => 'fullname',
                    'univId' => 'loginid'
                ],
                'checkHomeLibrary' => [
                    'enable' => true,
                    'attrToCheck' => 'eduPersonAffiliation',
                    'validAttrs' => ['main_campus'],
                ],
                'checkUserIsActive' => [
                    'enable' => true,
                    'attrToCheck' => 'eduPersonAffiliation',
                    'validAttrs' => ['student_current', 'student_active', 'faculty', 'employee'],
                ],
                'checkUserIsFaculyOrStaff' => [
                    'enable' => true,
                    'attrToCheck' => 'eduPersonAffiliation',
                    'validAttrs' => ['faculty', 'employee'],
                ],
                'checkUserIsGradStudent' => [
                    'enable' => true,
                    'attrToCheck' => 'eduPersonAffiliation',
                    'validAttrs' => ['student_current_g'],
                ],
            ],
        ]

    ]; 

    public function __construct($library) {
        $this->_map($library);
        //print_r($this);
        //die();
    }


    private function _map($library) {
        foreach($library as $key => $val) {
            if(property_exists(__CLASS__,$key)) {
                if (isset($this->$key) && gettype($this->$key) == 'array' && gettype($val) == 'object') {
                    $this->$key = json_decode(json_encode($val), true);
                } else {
                    $this->$key = $val;
                }
            }
        }
    }

    public function serialize() {
        global $user;
        if (!in_array($this->key, $user->isAdminOfLibraries)) {
            respondWithError(401, 'Not Authorized');
            die();
        }

        $config = [];
        foreach ($this as $key => $value) {
            if (strpos($key, '_') !== 0) {
                $config[$key] = $value;
             }
        }
        return $config;
    }
}