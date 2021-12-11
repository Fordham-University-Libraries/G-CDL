import { Component, OnInit } from '@angular/core';
import { Title } from '@angular/platform-browser';
import { MatSnackBar } from '@angular/material/snack-bar';
import { Config } from '../models/config.model';
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
  config: Config;
  userNameColIndex: number;
  emailColIndex: number;
  fullNameColIndex: number;
  lastnameColIndex: number;
  firstnameColIndex: number;
  firstRowWithDataIndex: number;
  accessbileUsers: string[];
  uploadedUsers: User[] = [];
  validUsers: User[] = [];
  invalidUsers: User[] = [];
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
    //console.log('file change');
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
      const data: string[][] = XLSX.utils.sheet_to_json(ws, { header: 1 }); // to get 2d array pass 2nd parameter as object {header: 1}
      //console.log(data.length); // Data will be logged in array format containing objects

      //find row that contains the column head
      let r = 0;
      const validColNames = ['username', 'email', 'fullName', 'firstname', 'lastname'];      
      for (let row of data) {        
        const filteredArray = row.filter(header => validColNames.includes(header.toLowerCase()));
        if (filteredArray.length) {
          this.firstRowWithDataIndex = r + 1;
          for (let hCol of filteredArray) {            
            switch (hCol.toLowerCase().trim()) {
              case 'username':
                this.userNameColIndex = row.indexOf(hCol);
                break;
              case 'email':
                this.emailColIndex = row.indexOf(hCol);
                break;
              case 'fullName':
                this.fullNameColIndex = row.indexOf(hCol);
                break;
              case 'firstname':
                this.firstnameColIndex = row.indexOf(hCol);
                break;
              case 'lastname':
                this.lastnameColIndex = row.indexOf(hCol);
                break;
              default:                
              //
            }
          }
          break;
        }
        r++;
      }

      if (!this.firstRowWithDataIndex) {
        this.error = `No column with valid header found, please use one of these as a column header: ${validColNames}`;
        this.isAnalyzing = false;
        return;
      }

      //has col head, start processing
      r = 0;
      for (let row of data) {
        let user = new User;
        if (r++ < this.firstRowWithDataIndex) continue;

        if (this.userNameColIndex > -1 && row[this.userNameColIndex]) {
          user.userName = row[this.userNameColIndex];
        }
        if (this.emailColIndex > -1 && row[this.emailColIndex]) {
          user.email = row[this.emailColIndex];
        }

        if (this.fullNameColIndex > -1 && row[this.fullNameColIndex]) {
          user.fullName = row[this.fullNameColIndex];
        } else {
          if (this.firstnameColIndex > -1 && row[this.firstnameColIndex]) {
            user.fullName = row[this.firstnameColIndex];
          }
          if (this.lastnameColIndex > -1 && row[this.lastnameColIndex]) {
            if (user.fullName) {
              user.fullName += ' ' + row[this.lastnameColIndex];
            } else {
              user.fullName = row[this.lastnameColIndex];
            }
          }
          if (Object.values(user).length) this.uploadedUsers.push(user);
        }
      }

      //have users now, next check if it's new users and etc.
      for (let user of this.uploadedUsers) {
        //has username, it's a valid user
        if (user.userName) {
          user.userName = user.userName.replace(this.config.emailDomain, ''); //remove email domain just incase
          this.validUsers.push(user);
        } else if (user.email) {
          //no username but has email
          //if email is valid domain, it's a valid user
          if (user.email.includes(this.config.emailDomain)) {
            user.userName = user.email.replace(this.config.emailDomain, ''); //just use the username portion of email
            this.validUsers.push(user);
          } else {
            this.invalidUsers.push(user);
          }
        } else {
          this.invalidUsers.push(user);
        }
      }

      //check 'valid' users if it's a new users and etc.
      for (let user of this.validUsers) {
        if (!this.accessbileUsers.includes(user.userName)) {
          this.newUsers.push(user.userName);
        } else {
          this.usersAlreadyInSystem.push(user.userName);
        }
      }

      this.isAnalyzing = false;
      console.log(`uploadedUsers: ${this.uploadedUsers.length}`);
      console.log(`validUsers: ${this.validUsers.length}`);
      console.log(`invalidUsers: ${this.invalidUsers.length}`);
      console.log(`usersAlreadyInSystem: ${this.usersAlreadyInSystem.length}`);
      console.log(`newUsers: ${this.newUsers.length}`);

    } //end .onLoad LOL
  }

  lookupUsers() {
    this.isLookingUp = true;
    let names = [];
    this.invalidUsers.forEach(user => {
      names.push(user.fullName);
    });

    this.adminService.lookupUsersByNames(names).subscribe(res => {
      //console.log(res);
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
      //remove of found users frominvalid list
      let allNotFoundUsers = this.usersLookupResult.notFoundUsers.multipleMatches.concat(this.usersLookupResult.notFoundUsers.zeroMatches);
      this.invalidUsers = this.invalidUsers.filter((user, index, arr) => {
        return allNotFoundUsers.includes(user.fullName);
      });
      this.isLookingUp = false;
    });
  }

  //add new users to GSheet
  process() {
    this.isProcessing = true;
    this.adminService.addAccessibleUsers(this.newUsers).subscribe(res => {
      //console.log(res);
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
    this.firstRowWithDataIndex = null;
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
    //console.log(this.usersToBeRemoved);
  }

  removeSelectedUsers() {
    //console.log('removeSelectedUsers');
    this._removeUsers(this.usersToBeRemoved).subscribe(success => {
      if (success) this.usersToBeRemoved = [];
    });
  }

  _removeUsers(userNames: string[]): Observable<boolean> {
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
      //console.log(res);
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
