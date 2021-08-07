// use Google Appscript instead of Cron
// go to Gsheet called "items currently checked out"
// click Tools -> Script toEditorSettings
// the in the default file 'Code.gs', copy code below and paste it in (remove all default sample code)
// update the first line const gcdlBaseUrl = 'https://www.myuniv.edu/G-CDL'; to your actual G-CDL app
// save
// click Run, authorize it, make sure no error
// click on 'Triggers (alarm clock icon) on left bar 
// click + Add trigger
// choose (run every minute)
//   - main
//   - head
//   - time-driven
//   - minutes timer
//   - every minute
// save

//note: using gmail to send has quota
//https://developers.google.com/apps-script/guides/services/quotas

//will try to get config from the G-CDL app, if fail, will use the default config
const gcdlBaseUrl = ''; //e.g. https://www.myuniv.edu/G-CDL
let numItems = 0;
let numDue = 0;
let numEmail = 0;
let config;
const defaultConfig = {
  'notifyOnAutoReturn': true,
  'returnSubject': 'Your Item has been Retuned',
  'returnBody': 'Your Item: {{$title}} has been Retuned, Thank you for using the Service',
  'fromName': 'NO-Reply',
  'replyTo': 'no-reply@univ.edu'
};

function main() {
  var sheet = SpreadsheetApp.getActive().getSheets()[0];
  //borrow item, file id, due time, user email, item's library
  var values = sheet.getRange('A2:E').getValues();
  //Logger.log(values);
  const now = new Date();
  for (let i = 0; i < values.length; i++) {
    //Logger.log(values[i]);
    let dueTimestamp = values[i][2];
    if (dueTimestamp) {
      numItems++;
      let due = new Date(dueTimestamp * 1000);
      if (due < now) {
        //Logger.log('ovedue');
        numDue++;

        let id = values[i][1];
        let borrowerEmail = values[i][3];
        let itemLibrary = values[i][4];

        //get config
        if (!config && gcdlBaseUrl) { 
          config = getConfig();
        } 
        
        if (!config) {
          config = defaultConfig;
        }

        //return
        try {
          var file = DriveApp.getFileById(id);
          var result = file.removeViewer(borrowerEmail);
        } catch (e) {
          Logger.log('cannnot return');
        }

        //remove row from sheet
        sheet.deleteRow(i + 1 + 1); //starting at 1 for the first row

        //email if enabled
        if (config.notifyOnAutoReturn) {
          let subject = config.returnSubject;
          let title = file.getDescription();
          let htmlBody = config.returnBody.replace('{{$title}}', title);
          //Logger.log(subject + ' : ' + htmlBody);
          let message = {
            to: borrowerEmail,
            subject: subject,
            htmlBody: htmlBody,
          };
          if (config.replyTo) message.replyTo = config.replyTo;
          if (config.fromName) message.name = config.fromName;
          try {
            MailApp.sendEmail(message);
            numEmail++
          } catch (e) {
            Logger.log('cannot email');
          }
        }
      } else {
        //Logger.log('not yet');
      }
    }
  }


  if (numDue) {
    Logger.log(`total items ${numItems} - ${numDue} items returned.`);
    if (numEmail) {
      var emailQuotaRemaining = MailApp.getRemainingDailyQuota();
      Logger.log(numEmail + " emails sent! Remaining email quota (recipients): " + emailQuotaRemaining);
    }
  } else {
    Logger.log(`total items ${numItems} - NO item returned.`);
  }
}


function getConfig() {
  Logger.log(`${gcdlBaseUrl}/api/?action=get_appsScript_config`);
  try {
    var response = UrlFetchApp.fetch(`${gcdlBaseUrl}/api/?action=get_appsScript_config`);
    let _config = JSON.parse(response)['data'];
    let config = {
      'notifyOnAutoReturn': _config.notifyOnAutoReturn,
      'returnSubject': _config.libraries[itemLibrary].lang.returnSubject,
      'returnBody': _config.libraries[itemLibrary].lang.returnBody
    };
    if (_config.emails['gMail']['fromName']) config.replyTo = _config.emails['gMail']['fromName'];
    if (_config.emails['gMail']['replyTo']) config.replyTo = _config.emails['gMail']['replyTo'];
    return config;
  } catch (e) {
    Logger.lang('cannot get config');
  }
}