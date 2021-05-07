<!DOCTYPE html>
<html>

<head>
    <title>Initialize G-CDL app</title>
    <style>
        body {
            padding: 1em;
        }

        .step {
            background: aliceblue;
            padding: .5em;
            margin-bottom: 1em;
        }

        .step-nav {
            width: 100%;
        }

        .step-nav a.btn {
            margin-right: 1em;
        }

        .config-form {
            background: antiquewhite;
        }

        a[target="_blank"]::after {
            content: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAYAAACNMs+9AAAAQElEQVR42qXKwQkAIAxDUUdxtO6/RBQkQZvSi8I/pL4BoGw/XPkh4XigPmsUgh0626AjRsgxHTkUThsG2T/sIlzdTsp52kSS1wAAAABJRU5ErkJggg==);
            margin: 0 3px 0 5px;
        }

        pre:not(:empty) {
            background: white;
            padding: 1em;
            font-size: small;
            font-family: monospace;
        }

        em.code {
            padding: .2em .4em;
            margin: 0;
            font-size: 85%;
            background-color: #e1e4e8;
            border-radius: 6px;
        }
    </style>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" integrity="sha384-JcKb8q3iqJ61gNV9KGb8thSsNjpSL0n8PARn9HuZOnIxN0hoP+VmmDGMN5t9UJ0Z" crossorigin="anonymous">
</head>

<body>
    <div class="container">
        <h1>Initialize API</h1>
        <?php if ($phpVerWarning) : ?>
            <div class="alert alert-danger">
                <?= $phpVerWarning ?>!
            </div>
        <?php endif ?>
        <?php if ($userName) : ?>
            Hello <?= $userName ?>!
        <?php endif ?>
        <?php if ($step == 1) : ?>
            <div class="row align-items-start step">
                <div class="col-8">
                    <h2>Step 1: Setup Credentials for Google API</h2>
                    <!-- no creds -->
                    <?php if (!$hasCreds) : ?>
                        <div class="alert alert-warning" role="alert">
                            TO DO: Generate credentials, download it, and put it as <?= realpath($credsDirPath) . '/credentials.json' ?>
                        </div>
                        <?php if ($hasToken) : ?>
                            <div class="alert alert-info" role="alert">
                                Heads up! Looks like you have the token.json file but don't have credentials.json. You DO need the <em>credentials.json</em> file even when you already have a token since the credentials.json is also used to authenticate end users.
                            </div>
                        <?php endif ?>
                        <ul>
                            <li>Pick Google Account to host all the files</li>
                            <ul>
                                <li>Ideally, you probably want to pick a 'coprate account' e.g. library@myuniv.edu so it won't
                                    get deleted if staff leaves and etc.</li>
                            </ul>
                            <li>Go to Google's API console (<a href="https://console.developers.google.com/" target="_blank">https://console.developers.google.com/</a>) -- and login with/swtich to account you picked in
                                step above</li>
                            <ul>
                                <li>create a new project</li>
                                <li>then, in the project you've just created</li>
                                <ul>
                                    <li>enable APIs for this project</li>
                                    <ul>
                                        <li>under 'Api & Services' -> 'Library', search for these APIs and enable them</li>
                                        <ul>
                                            <li>Google Drive API</li>
                                            <li>Google Sheets API</li>
                                            <li>Gmail API</li>
                                            <li>Google People API</li>
                                        </ul>
                                    </ul>
                                    <li>Setup OAuth consent screen</li>
                                    <ul>
                                        <li>under 'Api & Services' -> 'Oauth consent screen'</li>
                                        <li>User type: <em>Internal</em> (to allow only user within your GSuites domain)</li>
                                        <li>enter app's name (end users will see the first fime they login) and other required fields</li>
                                        <!-- nope - this only needed for WebHook -->
                                        <!-- <li>add Authorized domains (top-level domain of the location you plan to host the app for production on e.g. https://cdl.library.myuniv.edu put <em>myuniv.edu</em>) - you'll need to verify that you own the domain, see on-screen instructions (verify by URL prefix is simpler)</li> -->
                                        <li>you can skip the "Scopes" step (the app will only request non-sensitive data from end users e.g. get their username and email)</li>
                                    </ul>
                                    <li>Add OAuth 2.0 Client ID</li>
                                    <ul>
                                        <li>under 'Api & Services' -> 'Credentials'</li>
                                        <li>click '+ CREATE CREDENTIALS' -> 'OAuth 2.0 Client ID'</li>
                                        <ul>
                                            <li>Application type: <em>Web application</em></li>
                                            <ul>
                                                <li>Name it something meaningful i.e. so you won't accidentally delete it in the future</li>
                                                <li>Under <strong>Authorized redirect URIs</strong>, add a URI so Google can redirect users back to the app</li>
                                                <ul>
                                                    <li>{{app_url}}/api/?action=login</li>
                                                    <li> e.g. put 2 URLs, one for production and other for development</li>
                                                    <ul>
                                                        <li>https://library.myuniversity.edu/cdl/api/?action=login</li>
                                                        <li>http://localhost:8080/api/?action=login</li>
                                                    </ul>
                                                </ul>
                                            </ul>
                                            <li>download credentials as JSON</li>
                                            <li>put the downloaded JSON at <?= realpath($credsDirPath) . '/credentials.json' ?></li>
                                        </ul>
                                    </ul>
                                </ul>
                            </ul>
                        </ul>
                    <?php endif; ?>

                </div>
                <!-- has creds -->
                <?php if ($hasCreds) : ?>
                    <div class="container-fluid justify-content-center">
                        <div class="alert alert-success" role="alert">
                            credentials.json found! Looking good!
                        </div>
                        <div>
                            <?php
                            foreach ($creds as $key => $val) {
                                if (is_string($val)) {
                                    echo "$key : $val <br>";
                                }
                            }
                            ?>
                        </div>
                    </div>
                <?php endif; ?>


                <!-- "log in" -->
                <?php if (!$authed && $hasCreds) : ?>
                    <div class="container-fluid justify-content-center" style="margin-top: 1em; padding:1em; background:white">
                        <p>Since you're not accessing this on localhost, to proceed to the next step, let's make sure it's really you. Please enter the value of the "project_id" of the credentials.json file. This is ID of the project you created on <em>console.cloud.google.com</em></p>
                        <?php if ($errMsg) : ?>
                            <div class="alert alert-danger" role="alert">
                                <?= $errMsg ?>
                            </div>
                        <?php endif; ?>
                        <form method="post">
                            <div class="mb-3">
                                <label for="project_id" class="form-label">Project_id</label>
                                <input required type="text" class="form-control" id="project_id" name="project_id">
                            </div>
                            <button class="btn btn-primary" type="submit">Submit</button>
                        </form>
                    </div>
                <?php endif; ?>
                <?php if ($authed) : ?>
                    <div class="container-fluid justify-content-center" style="margin-top: 1em">
                        <div class="alert alert-success" role="alert">
                            You can proceed to the next step!
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        <?php endif; ?>

        <!-- step 2 -->
        <?php if ($step == 2) : ?>
            <div class="row align-items-start step">
                <h2>Step 2: Generate OAuth Token</h2>
                <div class="container-fluid justify-content-center">
                    <!-- no creds -->
                    <?php if (!$hasCreds) : ?>
                        <div class="alert alert-warning" role="alert">
                            No Credentials.json file found, please see the previous step
                        </div>
                        <!-- has creds but no token yet -->
                    <?php elseif (!$hasToken) : ?>
                        <div class="alert alert-info" role="alert">
                            Credentials.json file found! follow the steps below to generate token
                        </div>
                        <p>Click on the 'Authenticate' button below to authenticate with Google, please login with the account that you used to generate credentials.json with. You'll be asked if the app can access <a href="https://developers.google.com/identity/protocols/oauth2/scopes" target="_blank">these data</a>, please allow it.
                        <ul>
                            <?php
                            foreach ($scopes as $scope) {
                                echo "<li><b>$scope</b><ul><li>" . $scopeDefinitions[$scope] . "</li></ul></li>";
                            }
                            ?>
                        </ul>
                        <div><a href="<?= $authUrl ?>" class="btn btn-primary">Authenticate</a></div>
                    <?php endif; ?>
                    <!-- has token and connected to Drive -->
                    <?php if ($hasCreds && $hasToken && $appIsConnected) : ?>
                        <div class="alert alert-success" role="alert">
                            token.json found, looking good. The App is now connected to your Google Drive, the main folder for the app is: <em><?= $mainFolderId ?></em>. You can proceed to the next step (set up library)!
                        </div>
                        <div id="user-info">
                            <strong>Token Info</strong>
                            <ul>
                                <li>Token/Drive Owner: <?= $driveOwner ?></li>
                                <li>Main CDL app folder ID: <?= $mainFolderId ?></li>
                            </ul>
                        </div>
                        <div id="scopes">
                            <strong>Current token has <a href="https://developers.google.com/identity/protocols/oauth2/scopes" target="_blank">scopes</a>:</strong>
                            <ul>
                                <?php
                                foreach ($scopes as $scope) {
                                    echo "<li><b>$scope</b><ul><li>" . $scopeDefinitions[$scope] . "</li></ul></li>";
                                }
                                ?>
                                <ul>
                        </div>
                    <?php endif; ?>
                    <!-- not connected -->
                    <?php if ($hasCreds && $hasToken && !$appIsConnected) : ?>
                        <div class="alert alert-danger" role="alert">
                            token.json found, BUT it looks like the app is not connected to your Google Drive? (can't get the mainfolder <?= $mainFolderId ?></li>)
                        </div>
                        <p>Troubleshooting</p>
                        <ul>
                            <li>check that the main folder (<a href="https://drive.google.com/drive/folders/<?= $mainFolderId ?>" target="_blank">CDL APP</a>) is still there on your GDRIVE</li>
                            <li>check that the app is still connected to your account. see <a href="https://support.google.com/accounts/answer/3466521?hl=en" target="_blank">https://support.google.com/accounts/answer/3466521?hl=en</a></li>
                            <li>reconnect the app by removing the file token.json from your <?= realpath($credsDirPath) ?> and refresh this page</li>
                        </ul>
                    <?php endif; ?>

                </div>
            </div>
        <?php endif ?>

        <!-- step 3 -->
        <?php if ($step == 3) : ?>
            <div class="row align-items-start step">
                <h2>Step 3: Add Main (Default) Library</h2>
                <div class="container-fluid justify-content-center">
                    <div class="col-12">
                        <?php if ($libKey && $libName) : ?>
                            <div class="alert alert-success">
                                First Library has been created. Please proceed to the next step. If you need to change library name and etc., you'll be able to do so later! (using the frontend's UI)
                            </div>
                        <?php endif ?>
                        <form method="post">
                            <div class="mb-3">
                                <label for="lib-key" class="form-label">Library Short Name / Key (alphabets only)</label>
                                <input <?= $libKey && $libName ? 'readonly' : '' ?> required pattern="[a-zA-Z]*" type="text" class="form-control" id="lib-key" title="Only letters (a-zA-Z) are allowed" name="libKey" value="<?= $libKey; ?>">
                                <small class="form-text text-muted">The Library Short Name (cannot be changed later -- will only be visible to end users in the URL if you have multiple libraries)</small>
                            </div>
                            <div class="alert alert-secondary">
                                <strong>Note:</strong> this is just for quick initial setup, you can always add/change it later using the config page
                            </div>
                            <div class="mb-3">
                                <label for="lib-name" class="form-label">Library Name</label>
                                <input <?= $libKey && $libName ? 'readonly' : '' ?> required type="text" class="form-control" id="lib-name" name="libName" value="<?= $libName; ?>" placeholder="e.g. Some University Library">
                            </div>
                            <div class="mb-3">
                                <label for="borrowPeriod" class="form-label">Borrowing Period (hours)</label>
                                <input <?= $libKey && $libName ? 'readonly' : '' ?> required type="number" class="form-control" id="borrowPeriod" name="borrowPeriod" value="<?= $borrowPeriod; ?>" placeholder="">
                            </div>
                            <div class="mb-3">
                                <label for="cooldown" class="form-label">Back to back cooldown (minutes)</label>
                                <input <?= $libKey && $libName ? 'readonly' : '' ?> required type="number" class="form-control" id="cooldown" name="cooldown" value="<?= $cooldown; ?>" placeholder="">
                            </div>
                            <div class="alert alert-secondary">
                                <strong>Note:</strong> you (<?= $driveOwner ?>), the drive owner, has full access to everything of every library
                            </div>
                            <div class="mb-3">
                                <label for="staff" class="form-label">Admins</label>
                                <input <?= $libKey && $libName ? 'readonly' : '' ?> type="text" class="form-control" id="admins" name="admins" value="<?= $admins; ?>" placeholder="e.g. jane47, tpain69">
                                <small class="form-text text-muted">users (username without @<?= $gSuitesDomain ?>) who can edit configurations of this library(separate users with a comma)</small>
                            </div>
                            <div class="mb-3">
                                <label for="staff" class="form-label">Staff</label>
                                <input <?= $libKey && $libName ? 'readonly' : '' ?> type="text" class="form-control" id="staff" name="staff" value="<?= $staff; ?>" placeholder="e.g. jappleseed1, wmessing7, circstaff">
                                <small class="form-text text-muted">users (username without @<?= $gSuitesDomain ?>) who can upload/manage items for this library (separate users with a comma)</small>
                            </div>
                            <button <?= $libKey && $libName ? 'disabled' : '' ?> class="btn btn-primary">Submit</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif ?>

        <!-- step 4 -->
        <?php if ($step == 4) : ?>
            <div class="row align-items-start step">
                <h2>Step 4: Next Steps</h2>
                <div class="container-fluid justify-content-center">
                    <div class="col-12">
                        <div class="alert alert-success">
                            <p>The API has been successfully initiated. The first Library <em><?= $libName ?></em> (<?= $libKey ?>) has been created!.</p>
                            <p>If you go to your (<?= $driveOwner ?>) <a href="https://drive.google.com/drive/folders/<?= $mainFolderId ?>" target="_blank">Google Drive</a>, you should see a folder called CDL app, and inside that folder there should be 2 folders to store PDFs for this library</p>
                            <p>Here's the next steps</p>
                        </div>
                        <h3>Try out the API?</h3>
                        <p>Not required, but if you want to, you can try uploading stuff and see if it works</p>
                        <ul>
                            <li><a href="../api/?action=login&apiActionTarget=admin_upload">Upload</a></li>
                            <li><a href="../api/">Get All Items</a></li>
                        </ul>
                        <h3>Next Step</h3>
                        <h4>Set up front-end (Angular app)</h4>
                        <p>If you're running this on local machine</p>
                        <ul>
                            <li>open a new terminal/command line windows/tab</li>
                            <li>cd into the /G-CDL directory</li>
                            <li>Make sure you have <a href="https://nodejs.org/en/download/" target="_blank">Node.js</a> and <a href="https://cli.angular.io/" target="_blank">Angualar CLI</a> installed</li>
                            <li>Make sure you did run the <em class="code">npm install</em></li>
                            <li>issue a command <em class="code">ng serve</em></li>
                            <li>the frontend will be avaiable at <a href="http://localhost:4200" target="_blank">http://localhost:4200</a></li>
                        </ul>
                        <h3>Deployment</h3>
                        <ul>
                            <li>Build & Deploy frontend</li>
                            <ul>
                                <li>on command line, issue a command <em class="code">ng build --prod</em></li>
                                <ul>
                                    <li>NOTE! if you plan to put your app in a sub directory e.g. https://library.myuniv.edu/mycdlapp also add a param --base-href /{dirName}/ to the build command</li>
                                    <li>e.g. <em class="code">ng build --prod --base-href /mycdlapp/</em></li>
                                </ul>
                                <li>Once the build is done, you'll see a directory <em>dist</em> on your local machine</li>
                                <ul>
                                    <li>copy the directory /CDL inside the /dist to your server (rename it if needed)</li>
                                    <ul>
                                        <li>e.g. copy it to /var/www/mycdlapp</li>
                                    </ul>
                                </ul>
                                <li>
                                    NOTE: if you host the api on a different host than the front-end, you can edit Angular's environment.prod.ts and change the apiBase from ./api to where ever e.g. //gcdl-api.myuni.edu/api
                                </li>
                            </ul>
                            <li>Deploy API</li>
                            <ul>
                                <li>copy the /api directory (and all its subdirectories) to your server e.g. if you app is at /var/www/mycdlapp, copy it to /var/www/mycdlapp/api </li>
                                <li>update the static configs at <?= $dataDirRealPath ?>/config.php as needed (especially, set the <em>$isProd</em> property to TRUE)</li>
                                        <ul>
                                            <li>If you host the frontend of a different host, set it at the <em>$frontEndHost</em> property</li>
                                        </ul>
                                </li>
                                <li>The rest of the configs e.g. library settings can be edited using the frontend's UI at {{appFrontend}}/admin/configs</li>
                            </ul>
                            <li>Server Configs</li>
                            <ul>
                                <li>make sure the all the private dirs e.g. <em><?= $dataDirPath ?></em> is <strong>NOT</strong> accesssible to the public (Cannot Stress This Enough) try access it from your browser e.g.</li>
                                <ul>
                                    <li>CLICK ME ==> <a href="<?= $credsDirPath ?>credentials.json" target="_blank"><?= $credsDirPath ?>credentials.json</a> it MUST NOT be accessible</li>
                                </ul>
                                <li>make sure these directories are <strong>WRITABLE</strong> by your webserver</li>
                                <ul>
                                    <li><strong>Credentails Directory: </strong><?= $credsDirRealPath ?> : <?= $privateCredsWritable ? '<span style="color:green">OK! WRITABLE</span>' : '<span style="color:red">NOT WRITABLE</span>' ?>
                                    <li><strong>Private Data Directory: </strong><?= $dataDirRealPath ?> : <?= $privateDataWritable ? '<span style="color:green">OK! WRITABLE</span>' : '<span style="color:red">NOT WRITABLE</span>' ?>
                                    <li><strong>Temp Directory: </strong><?= $tempDirRealPath ?> : <?= $privateTempWritable ? '<span style="color:green">OK! WRITABLE</span>' : '<span style="color:red">NOT WRITABLE</span>' ?>
                                </ul>
                                <li>the app uses PHP's shell_exec() funtion to call <a href="https://github.com/coherentgraphics/cpdf-binaries" target="_blank">cpdf</a> (For non-commercial use only) to create a NO-OCR version (remove all text), so your php.ini must enable it</li>
                                <ul>
                                    <li><?= $shellExecEnable ? '<span style="color:green">OK! Enabled & Callable</span>' : '<span style="color:red">NOT ENABLED / NOT CALLABLE</span>' ?></li>
                                </ul>
                            </ul>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endif ?>

        <!-- STEP NAV -->
        <div class="row justify-content-center step-nav">
            <?php if ($step > 1) : ?><a class="btn btn-light" href=".?action=init&step=<?= $step - 1 ?>">&lt; Back</a><?php endif ?>
            <?php if ($showRefresh) : ?> <button class="btn btn-secondary" onClick="location.reload();">Refresh</button><?php endif ?>
            <?php if ($showNext) : ?><a class="btn btn-light" href=".?action=init&step=<?= $step + 1 ?>">Next &gt;</a><?php endif ?>
        </div>

    </div>
</body>

</html>