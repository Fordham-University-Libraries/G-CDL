import { Component, OnInit } from '@angular/core';
import { Title } from '@angular/platform-browser';
import { MatSnackBar } from '@angular/material/snack-bar';
import { ConfigService } from '../config.service';
import { AdminService } from '../admin.service';
import { AuthenticationService } from '../auth.service';
import * as XLSX from 'xlsx';
import { User } from '../models/user.model';
import { Observable, Subject } from 'rxjs';

@Component({
  selector: 'app-accessible-users',
  templateUrl: './accessible-users.component.html',
  styleUrls: ['./accessible-users.component.scss']
})
export class AccessibleUsersComponent implements OnInit {
  config: any;
  userNameColIndex: number;
  emailColIndex: number;
  nameColsIndexes: number[] = [];
  firstRowWithDataIndex: number;
  accessbileUsers: string[];
  uploadedUserNames: string[] = [];
  uploadedEmails: string[] = [];
  invalidUsers: { name: string, email: string }[] = [];
  newUsers: string[] = [];
  usersAlreadyInSystem: string[] = [];
  isAnalyzing: boolean;
  isLookingUp: boolean;
  usersLookupResult: {
    foundUsers: {
      users: string[],
      newUsers: string[],
      alreadlyInSystemUsers: string[];
    },
    notFoundUsers: {
      multipleMatches: string[],
      zeroMatches: string[]
    }
  };
  isProcessing: boolean;
  isProcessingToggle: boolean;
  usersAddedViaExcel: string[];
  usersAdded: string[];
  usersNotAdded: string[];
  error: string;
  user: User;
  manuallyAddedUsers: string;
  usersToBeRemoved: string[] = [];

  constructor(
    private titleService: Title,
    private snackBar: MatSnackBar,
    private configService: ConfigService,
    private adminService: AdminService,
    private authService: AuthenticationService
  ) { }

  ngOnInit(): void {
    this.configService.getConfig().subscribe(res => {
      this.config = res;
      this.titleService.setTitle(`Admin/AccessibleUsers : ${this.config.appName}`);
      this._getCurrentAccessibleUser();
    });
    this.authService.getUser().subscribe(res => this.user = res);
  }

  private _getCurrentAccessibleUser(forceRefresh: boolean = false) {
    this.configService.getAccessibleUsers(forceRefresh).subscribe(res => {
      this.accessbileUsers = res;
      //console.log(this.accessbileUsers);
    })
  }

  onFileChange(event: any) {
    console.log('file change');

    this._reset();
    this.isAnalyzing = true;
    /* wire up file reader */
    const target: DataTransfer = <DataTransfer>(event.target);
    if (target.files.length !== 1) {
      throw new Error('Cannot use multiple files');
    }
    const reader: FileReader = new FileReader();
    reader.readAsBinaryString(target.files[0]);
    reader.onload = (e: any) => {
      /* create workbook */
      const binarystr: string = e.target.result;
      const wb: XLSX.WorkBook = XLSX.read(binarystr, { type: 'binary' });

      /* selected the first sheet */
      const wsname: string = wb.SheetNames[0];
      const ws: XLSX.WorkSheet = wb.Sheets[wsname];

      /* save data */
      const data: string[] = XLSX.utils.sheet_to_json(ws, { header: 1 }); // to get 2d array pass 2nd parameter as object {header: 1}
      //console.log(data); // Data will be logged in array format containing objects
      let r = 0;
      for (let row of data) {
        let c = 0;
        if (!this.firstRowWithDataIndex) {
          //console.log('looking for col heads');
          for (let col of row) {
            //locate userName columns
            if (col.toLowerCase() == 'username') {
              if (!this.firstRowWithDataIndex) this.firstRowWithDataIndex = r + 1;
              this.userNameColIndex = c;
              //console.log('fonund username in col', c);
            }
            //locate email column
            if (col.toLowerCase().replace('-', '') == 'email') {
              if (!this.firstRowWithDataIndex) this.firstRowWithDataIndex = r + 1;
              this.emailColIndex = c;
              //console.log('fonund email in col', c);
            }
            //locate name columns
            if ((col.toLowerCase().includes('fullname') || col.toLowerCase().includes('firstname') || col.toLowerCase().includes('lastname'))) {
              if (!this.firstRowWithDataIndex) this.firstRowWithDataIndex = r + 1;
              this.nameColsIndexes.push(c);
              //console.log('fonund name in col', c);
            }
            c++;
          }
        } else if (this.userNameColIndex !== null || this.emailColIndex !== null || this.nameColsIndexes.length) {
          //console.log('looking for vals');
          var userNameOrEmailFound = false;
          var email: string;
          for (let col of row) {
            //if it's a username col
            if (this.userNameColIndex != null && this.userNameColIndex == c && col) {
              let userName = col.replace(this.config.emailDomain, '');
              this.uploadedUserNames.push(userName);
              if (!this.accessbileUsers.includes(userName) && !this.newUsers.includes(userName)) {
                this.newUsers.push(userName);
              } else {
                this.usersAlreadyInSystem.push(userName);
              }
              userNameOrEmailFound = true;
            } else if (this.emailColIndex != null && this.emailColIndex == c && col && !userNameOrEmailFound) {
              //if it's an email col
              if (col.toLowerCase().includes(this.config.emailDomain)) {
                let userName = col.toLowerCase().replace(this.config.emailDomain, '');
                this.uploadedEmails.push(userName);
                if (!this.accessbileUsers.includes(userName) && !this.newUsers.includes(userName)) {
                  this.newUsers.push(userName);
                } else {
                  this.usersAlreadyInSystem.push(userName);
                }
                userNameOrEmailFound = true;
              } else {
                email = col;
              }
            } else if (this.nameColsIndexes.length && this.nameColsIndexes.includes(c) && col && !userNameOrEmailFound) {
              //full, first,lastname
              //console.log(`have to use names now. name: ${col}`);
              let name = '';
              this.nameColsIndexes.forEach(cIndex => {
                name += row[cIndex] + ' ';
              })
              if (this.nameColsIndexes[this.nameColsIndexes.length - 1] == c) this.invalidUsers.push({ name: name.trim(), email: email });
            }
            c++;
          }
        }
        r++;
      }

      // console.log(this.userNameColIndex);
      // console.log(this.emailColIndex);
      // console.log(this.nameColsIndexes.length);
      if (this.userNameColIndex === null && this.emailColIndex === null && !this.nameColsIndexes.length) this.error = 'No column headers found in Excel file!'
      this.isAnalyzing = false;
      //console.log(this.invalidUsers);
      //console.log(this.uploadedEmails);
      //console.log(this.nameColsIndexes);
    };
  }

  lookupUsers() {
    this.isLookingUp = true;
    let names = [];
    this.invalidUsers.forEach(user => {
      names.push(user.name);
    });

    this.adminService.lookupUsersByNames(names).subscribe(res => {
      console.log(res);
      this.usersLookupResult = {
        foundUsers: { users: [], newUsers: [], alreadlyInSystemUsers: [] },
        notFoundUsers: { multipleMatches: [], zeroMatches: [] }
      };
      this.usersLookupResult.foundUsers.users = res.foundUsers;
      this.usersLookupResult.notFoundUsers.multipleMatches = res.multipleMatchesNames;
      this.usersLookupResult.notFoundUsers.zeroMatches = res.notFoundNames;
      this.usersLookupResult.foundUsers.users.forEach(user => {
        if (!this.accessbileUsers.includes(user) && !this.newUsers.includes(user)) {
          this.newUsers.push(user);
          this.usersLookupResult.foundUsers.newUsers.push(user);
        } else {
          this.usersAlreadyInSystem.push(user);
          this.usersLookupResult.foundUsers.alreadlyInSystemUsers.push(user);
        }
      });
      //remove of invalid list
      this.invalidUsers = [];
      let notFoundUsers = this.usersLookupResult.notFoundUsers.multipleMatches.concat(this.usersLookupResult.notFoundUsers.zeroMatches);
      notFoundUsers.forEach(user => {
        this.invalidUsers.push({ name: user, email: null });
      });
      this.isLookingUp = false;
      console.log(this.usersLookupResult);
    });

  }

  //add new users to GSheet
  process() {
    this.isProcessing = true;
    this.adminService.addAccessibleUsers(this.newUsers).subscribe(res => {
      console.log(res);
      //refresh
      this.accessbileUsers = null;
      this._getCurrentAccessibleUser(true);
      this.usersAddedViaExcel = res.usersAdded;
      this.isProcessing = false;
    }, error => {
      this.isProcessing = false;
      this.usersAddedViaExcel = null;
      this.error = 'Something went wrong :(';
    })
  }

  _reset() {
    //console.log('resetting');
    this.userNameColIndex = null;
    this.emailColIndex = null;
    this.nameColsIndexes = [];
    this.firstRowWithDataIndex = null;
    this.uploadedEmails = [];
    this.invalidUsers = [];
    this.newUsers = [];
    this.usersAlreadyInSystem = [];
    this.isAnalyzing = false;
    this.isLookingUp = false;
    this.usersLookupResult = null;
    this.isProcessing = false;
    this.usersAddedViaExcel = null;
    this.usersAdded = null;
    this.usersNotAdded = null;
    this.error = null;
  }

  toggleCurrentUser() {
    this.isProcessingToggle = true;
    if (!this.user.isAccessibleUser) {
      this.addManuallyAddedUsers(this.user.userName);
    } else {
      this._removeUsers([this.user.userName]);
    }
  }

  markUserToBeRemoved(userName: string) {
    if (!this.usersToBeRemoved.includes(userName)) {
      this.usersToBeRemoved.push(userName);
    } else {
      const index = this.usersToBeRemoved.findIndex(u => { return u == userName });
      if (index > -1) this.usersToBeRemoved.splice(index, 1);
    }
    console.log(this.usersToBeRemoved);
  }

  removeSelectedUsers() {
    console.log('removeSelectedUsers');
    this._removeUsers(this.usersToBeRemoved).subscribe(success => {
      if (success) this.usersToBeRemoved = [];
    });
  }

  _removeUsers(userNames: string[]):Observable<boolean> {
    let subject = new Subject<boolean>();
    this.adminService.removeAccessibleUsers(userNames).subscribe(res => {
      if (res.usersRemoved?.includes(this.user.userName)) {
        this.user.isAccessibleUser = false;
      }
      this.isProcessingToggle = false;
      if (res.usersRemoved?.length) {
        this.accessbileUsers = null;
        this._getCurrentAccessibleUser(true);
        this.snackBar.open(`Success! ${res.usersRemoved.length} user(s) removed form Accessible Users List`, 'Dismiss', {
          duration: 5000,
        });
        subject.next(true);
      } else {
        this.snackBar.open(`Error: Something went wrong!`, 'Dismiss', {
          duration: 5000,
        });
      }
    });
    return subject;
  }

  addManuallyAddedUsers(userNamesStr: string) {
    const userNames = userNamesStr.split(',');
    this.usersAdded = [];
    this.usersNotAdded = [];
    this.isProcessing = true;
    this.adminService.addAccessibleUsers(userNames).subscribe(res => {
      console.log(res);
      this.usersAdded = res.usersAdded;
      this.usersNotAdded = res.usersNotAdded;
      if (userNames.includes(this.user.userName)) {
        if (res.usersAdded.includes(this.user.userName)) {
          this.user.isAccessibleUser = true;
        }
      }
      this.isProcessing = false;
      this.isProcessingToggle = false;
      if (this.usersAdded.length && !this.usersNotAdded.length) {
        this.manuallyAddedUsers = null;
        this.accessbileUsers = null;
        this._getCurrentAccessibleUser(true);
        this.snackBar.open(`Success! ${this.usersAdded.length} user(s) were added`, 'Dismiss', {
          duration: 5000,
        });
      } else if (this.usersAdded.length && this.usersNotAdded.length) {
        this.accessbileUsers = null;
        this._getCurrentAccessibleUser(true);
        this.snackBar.open(`Success! Some ${this.usersAdded.length} user(s) were added. BUT other ${this.usersNotAdded.length} user(s) were NOT added`, 'Dismiss', {
          duration: 5000,
        });
      } else if (!this.usersAdded.length && this.usersNotAdded.length) {
        this.snackBar.open(`Error! No users added. ${this.usersNotAdded.length} user(s) were NOT added`, 'Dismiss', {
          duration: 5000,
        });
      } else if (!this.usersAdded.length && !this.usersNotAdded.length) {
        this.snackBar.open(`Error! something went horribly wrong`, 'Dismiss', {
          duration: 5000,
        });
      }
    });
  }
}
