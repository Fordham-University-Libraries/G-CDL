let config;
let language;

function main() {
  var sheet = SpreadsheetApp.getActive().getSheets()[0];
  var values = sheet.getRange('A2:D').getValues();
  //Logger.log(values);
  const now = new Date();
  for(let i = 0; i < values.length; i++) {
    //Logger.log(values[i]);
    let due = new Date(values[i][2] * 1000);
    if (due < now) {
      if (!config) getConfig();
      if (!language) getLanguage();
      Logger.log('ovedue');
      let id = values[i][1];
      let borrowerEmail = values[i][3];
      //return
      var file = DriveApp.getFileById(id);
      var result = file.removeViewer(borrowerEmail);
      //remove row fomr sheet
      sheet.deleteRow(i + 1); //starting at 1 for the first row
      //email if enabled
      let subject = "auto returned!";
      let message = 'yay!';
      if (config.notifyOnAutoReturn) MailApp.sendEmail(borrowerEmail, subject, message);
    } else {
      Logger.log('not yet');
    }
  }

}

function getConfig() {
  var response = UrlFetchApp.fetch("http://www.library.fordham.edu/digitalreserves/api/?action=get_config");
  config = JSON.parse(response)['data'];
}

function getLanguage() {
  var response = UrlFetchApp.fetch("http://www.library.fordham.edu/digitalreserves/api/?action=get_lang");
  language = JSON.parse(response)['data'];
}
