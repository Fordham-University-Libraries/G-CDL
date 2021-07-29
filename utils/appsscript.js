const gcdlBaseUrl = 'https://www.library.fordham.edu/digitalreserves'; 
let config;

function main() {
  var sheet = SpreadsheetApp.getActive().getSheets()[0];
  //borrow item, file id, due time, user email, item's library
  var values = sheet.getRange('A2:E').getValues();
  //Logger.log(values);
  const now = new Date();
  for(let i = 0; i < values.length; i++) {
    //Logger.log(values[i]);
    let due = new Date(values[i][2] * 1000);
    if (due < now) {
      if (!config) getConfig();
      Logger.log('ovedue');
      let id = values[i][1];
      let borrowerEmail = values[i][3];
      let itemLibrary = values[i][4];
      
      //return
      var file = DriveApp.getFileById(id);
      var title = file.getDescription();
      var result = file.removeViewer(borrowerEmail);
      
      //remove row from sheet
      sheet.deleteRow(i + 1); //starting at 1 for the first row
      
      //email if enabled
      if (config.notifyOnAutoReturn) {
        let subject = config.libraries[itemLibrary].lang.returnSubject;
        let message = config.libraries[itemLibrary].lang.returnBody;
        Logger.log(subject + ' : ' + message);
        MailApp.sendEmail(borrowerEmail, subject, message);
      }
    } else {
      Logger.log('not yet');
    }
  }

}

function getConfig() {
  //Logger.log(`${gcdlBaseUrl}/api/?action=get_appsScript_config`);
  var response = UrlFetchApp.fetch(`${gcdlBaseUrl}/api/?action=get_appsScript_config`);
  config = JSON.parse(response)['data'];
}
