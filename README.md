# Google Drive Library Controlled Digital Lending (G-CDL)

This is a [CDL](https://controlleddigitallending.org/) application that uses Google Drive as a backend. All files are stored and managed in Google Drive.

## Overview

This application automates the manual process of using Google Drive's share with “[Prevent commenters and viewers from downloading, printing, or copying files](https://support.google.com/a/users/answer/9308868?hl=en)”, and "[Share with expiration](https://support.google.com/a/users/answer/9308784?hl=en)" with the goal of providing access to a PDF to specified users using the [Controlled Digital Lending](https://controlleddigitallending.org/whitepaper) principles.

Example of the Manual Process  

- We have uploaded many PDFs to GDrive.
- User *Jane* finds the item (somehow) and emails library staff to request access. “Hey, I want to borrow the book ‘History of Pi’.”
- Library staff shares the 'History of Pi' PDF with the user Jane as a ‘Viewer’ with auto expired set to in x hours. The library then replies to the user with the ‘access link’ to the PDF.
- 5 minutes later, User *John*, ask to borrow book ‘History of Pi.’
- Library staff checks the status of book ‘History of Pi’ and sees that it is being shared with a different user and denies access to User *John*. "This book is not available, please ask again later." 

Example of App/GDrive Interactions  

| User                                                                 | App                                                                                                                                                       | Note |
|----------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------|------|
| Library staff upload PDF                                             | gets item's info via ILS's API, generates a second copy of the pdf with NO-OCR data                                                                         |      |
| Users log in to app and see all the items with availability info | app gets all the files in Google Drive, processes it (e.g. is it being borrowed by someone?), and returns the data                                                   |      |
| User A borrow item 'History of Pi'                                             | app checks the file on GDrive, found that it has no 'viewer' (therefore available), add user A as a 'viewer' and set the share expiration for e.g. 2 hours |      |
| User A clicks the 'Read' button                                       | app sends user A to the "Google Docs/Drive Viewer" ([drive#file->webContentLink](https://developers.google.com/drive/api/v3/reference/files#webContentLink)) since user A is a viewer of that file, s/he can read it                    |      |
| 5 minutes later, user B tries to borrow item 'History of Pi'                        | app checks the file on GDrive, notices that it already has a 'viewer', returns error                                                                         |      |
| 2 Hours later, item 'History of Pi' expires                                    | app removes user A from item 'History of Pi' file's permission                                                                                                                 |      |
| User A tries to read item 'History of Pi' again                                  | since user A is no longer a viewer, s/he will see the GDrive's "request access screen"                                                                        |      |


  
## Installation
- Requirements
    - Google
      - your institution must have G Suite/Google Workspace
      - the account you chose to hosts all the files in G-Drive to must be able to create a ["project"](https://cloud.google.com/resource-manager/docs/access-control-proj)      
    - local machine
        - PHP 7.4+
            - [Composer](https://getcomposer.org/download/)
        - [Node.js](https://nodejs.org/en/download/)
        - [Angular CLI](https://cli.angular.io/)
    - server
        - a server (Apache, NGINX, IIS, and etc.)
        - PHP 7.4+
- Clone this repo
    - CD into the cloned repo directory (/G-CDL) and run `npm install` to install NODE dependencies
    - CD into ./api and run `composer install` to install PHP dependencies
- Setup Google API
    - on your local machine
        - cd into the /G-CDL directory
        - run PHP development server on port 8080 to serve the API `php -S localhost:8080`
        - open a web browser and navigate to [http://localhost:8080/api/](http://localhost:8080/api/)
        - you'll see the app init wizard, follow the on-screen instructions
- Development & testing
    - on your local machine
    - make sure you did run the `npm install`, and `composer install`
    - cd into the /G-CDL folder, run php test server `php -S localhost:8080`
    - on another terminal tab/window, cd into the /G-CDL folder (if not already), run `ng serve`
      - To get more help on the Angular CLI use `ng help` or go check out the [Angular CLI README](https://github.com/angular/angular-cli/blob/master/README.md).
    - go to [http://localhost:4200](http://localhost:4200) on your browser
- Deployment
    - on your local machine
    - make sure you did run the `npm install`, and `composer install`
      - if PHP on your server is of lower version than one on your local machine e.g. server = 7.4, local machine = 8.0
        - rename the file `/G-CDL/api/composer.json.php74` to `/G-CDL/api/composer.json` (and replace the original one)
          - run `composer update`   
    - run `ng build --prod`
        - if you plan to put it under a directory e.g. https://library.myuniv.edu/dir_name run `ng build --prod --base-href /dir_name/`
    - copy the content of the `dist` directory e.g. /G-CDL/dist/cdl/ directory to your server
      - add Angular rewrite/redirect e.g. https://julianpoemp.github.io/ngx-htaccess-generator/#/generator   
    - copy the dir /api to /api on your server
        - edit Config.php and set $isProd = true;   
        - make sure the /api/private_data
            - is NOT accessible to the public
            - IS writable by your server
    - NOTE: if you host the api on a different host than the front-end, you can edit Angular's `environment.prod.ts` and change the `apiBase` from `./api` to where ever e.g. `//gcdl-api.myuni.edu/api`
    - DONE! NOTE! If you use this app for production, please [drop us a line](https://docs.google.com/forms/d/e/1FAIpQLScqLS0DsEOrDLfgeqzFyNKI-MM3vp9MDwQdYGL9zxjM6gRoNw/viewform?usp=pp_url&entry.2033461527=__other_option__&entry.2033461527.other_option_response=letting+you+know+that+we're+using+the+app!) -- knowing how many institutions are using it would help us justify staff time maintaining the app and adding new features
        
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)

## CDL Resources
[![Awesome](https://awesome.re/badge.svg)](https://awesome.re)
- CDL info
  - https://controlleddigitallending.org/  
  - https://sites.google.com/view/cdl-implementers/  
- CDL implementation
   - [Internet Archive](https://archive.org/details/inlibrary) / [Open Library](https://github.com/internetarchive/openlibrary/)
   - [IIIF based](https://iiif.io/api/auth/1.0/#authentication-for-description-resources) -- being implemented in the wild by that two techy, comp sciency universities on the West Coast
      - well, since it's already on Github [https://github.com/caltechlibrary/dibs](https://github.com/caltechlibrary/dibs)  
   - [project ReShare](https://projectreshare.org/products/reshare-controlled-digital-lending/)
   - [CIRC project](https://www.cdlproject.org/)
   - Commercial System
      -  [DLSG](https://www.dlsg.com/) -- you might want to wear a safety glasses before visiting this website

