# Google Drive Library Controlled Digital Lending (G-CDL)

This is a [CDL](https://controlleddigitallending.org/) application that uses Google Drive as a backend. All files are stored and manged in Google Drive.

## Overview

This application automates the manual process of using Google Drive's share with “[Prevent commenters and viewers from downloading, printing, or copying files](https://support.google.com/a/users/answer/9308868?hl=en)”, and "[Share with expiration](https://support.google.com/a/users/answer/9308784?hl=en)" to provide access to a pdf to users using the [Controlled Digital Lending](https://controlleddigitallending.org/whitepaper) principles

Exmaple of Manual Process  

- We put a bunch of PDFs on GDrive
- User Jane found the item (somehow) and email library staff “hey, I want to borrow book ‘Foo’”
- library staff share the Foo Pdf to the user Jane as a ‘Viewer’ with auto expired set to in x hours then reply the user with the ‘access link’ to the pdf
- 5 minutes later, User John, ask to borrow book ‘Foo’
- library staff check that book ‘Foo’ is being shared to other users, reply ‘nope the book is not available, please ask again later’ 

Example of App/GDrive Interactions  

| User                                                                 | App                                                                                                                                                       | Note |
|----------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------|------|
| Library staff upload PDF                                             | gets item's info via ILS's API, generates a second copy of the pdf with NO-OCR data                                                                         |      |
| Users log to to app and see all the item with its availability info | app gets all the files in Google Drive, process it (e.g. being borrowed by someone), and return the data                                                   |      |
| User A borrow item 'foo'                                             | app checks the file on GDrive, found that it has no 'viewer' (therefore available), add user A as a 'viewer' and set the share expiration for e.g. 2 hours |      |
| User A click the 'Read' button                                       | app sends user A to the "Google Docs/Drive Viewer" ([drive#file->webContentLink](https://developers.google.com/drive/api/v3/reference/files#webContentLink)) since user A is a viewer of that file, s/he can read it                    |      |
| 5 minutes later, user B tried to borrow item 'foo'                        | app checks the file on GDrive, notice that it already has a 'viewer', return error                                                                         |      |
| 2 Hours later, item 'foo' expires                                    | app removes user A from item 'foo' file's permission                                                                                                                 |      |
| User A try to read item 'foo' again                                  | since user A is no longer a viewer, s/he will see the GDrive's "request access screen"                                                                        |      |


  
## Installation
- Requirements
    - local machine
        - PHP 7+
            - Composer
        - Node.js
        - Angular CLI
    - server
        - a server (Apache, NGINX, IIS, and etc.)
        - PHP 7+
- Clone this repo
    - CD into the cloned repo and run `npm install`
    - CD into ./api and install dependencies `composer install`
- Setup Google API
    - on your local machine
        - cd into the /{{G-CDL}} directory
        - run PHP development server on port 8080 to serve the API `php -S localhost:8080`
        - open a web browser and navigate to http://localhost:8080/api/
        - you'll see the app init wizard, follow the on-screen instructions
- development & testing
    - on your local machine
    - make sure you did run the `npm install`, and `composer install`
    - cd into the /api folder, run php test server `php -S localhost:8080`
    - on another terminal tab/window run `ng serve`
    - To get more help on the Angular CLI use `ng help` or go check out the [Angular CLI README](https://github.com/angular/angular-cli/blob/master/README.md).
    - go to http://localhost:4200 on your browser
- deployment
    - on your local machine
    - make sure you did run the `npm install`, and `composer install`
    - run `ng build --prod`
        - if you plan to put it under a directory run `ng build --prod --base-href /dir_name/`
    - copy the contect of /dist directory to your server
    - copy the dir /api to /api on your server
        - make sure the /api/private_data
            - is NOT accessible to the public
            - IS writable by your server
        
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)
