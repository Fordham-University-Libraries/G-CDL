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

const gcdlBaseUrl = 'https://www.myuniv.edu/G-CDL';
let config;

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
      let due = new Date(dueTimestamp * 1000);
      if (due < now) {
        if (!config) getConfig();
        Logger.log('ovedue');
        let id = values[i][1];
        let borrowerEmail = values[i][3];
        let itemLibrary = values[i][4];

        //return
        var file = DriveApp.getFileById(id);
        var result = file.removeViewer(borrowerEmail);

        //remove row from sheet
        sheet.deleteRow(i + 1 + 1); //starting at 1 for the first row

        //email if enabled
        if (config.notifyOnAutoReturn) {
          let subject = config.libraries[itemLibrary].lang.returnSubject;
          let title = file.getDescription();
          let htmlBody = config.libraries[itemLibrary].lang.returnBody.replace('{{$title}}', title);
          Logger.log(subject + ' : ' + htmlBody);
          MailApp.sendEmail({
            to: borrowerEmail,
            subject: subject,
            htmlBody: htmlBody,
          });
        }
      } else {
        Logger.log('not yet');
      }
    }
  }

}

function getConfig() {
  Logger.log(`${gcdlBaseUrl}/api/?action=get_appsScript_config`);
  var response = UrlFetchApp.fetch(`${gcdlBaseUrl}/api/?action=get_appsScript_config`);
  config = JSON.parse(response)['data'];
}
