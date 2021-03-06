import { Component, OnInit } from '@angular/core';
import { Title } from '@angular/platform-browser';
import { Router } from '@angular/router';
import { MatSnackBar } from '@angular/material/snack-bar';
import { User } from '../models/user.model';
import { Config } from '../models/config.model';
import { AuthenticationService } from '../auth.service';
import { ConfigService } from '../config.service';
import { AdminService } from '../admin.service';
import { Subject } from 'rxjs';
import { debounceTime } from 'rxjs/operators';
import { environment } from '../../environments/environment';

@Component({
  selector: 'app-app-config',
  templateUrl: './app-config.component.html',
  styleUrls: ['./app-config.component.scss']
})
export class AppConfigComponent implements OnInit {
  user: User;
  isBusy: boolean;
  config: Config;
  appConfig = []; //stuff we want to edit
  appConfigLibraries = [];
  appConfigCopy = [];
  appConfigLibrariesCopy = [];
  appConfigDirtyCount = { 'count': 0, 'isLoading': false };
  appConfigLibrariesDirtyCount: { libKey: string, name?: string, count: number, isLoading: boolean, isDefault?: boolean }[] = [];
  appConfigSubject: Subject<string[]> = new Subject();
  obj = {};
  newLib: { key: string, name: string };
  sectionDefinitions: any;
  serverCheck: {
    privateDataWritable: boolean,
    privateTempWritable: boolean,
    shellExecEnable: boolean,
  }
  staticConfigs: any;
  apiBase: string;
  configBackups: any;


  constructor(
    private router: Router,
    private configService: ConfigService,
    private authService: AuthenticationService,
    private titleService: Title,
    private adminService: AdminService,
    private snackBar: MatSnackBar,
  ) { 
    this.apiBase = environment.apiBase;
  }

  ngOnInit(): void {
    this.appConfigDirtyCount.isLoading = true;
    this.configService.getConfig().subscribe(cRes => {
      this.config = cRes;
      this.titleService.setTitle(`Admin/Config : ${this.config.appName}`);
      this.authService.getUser().subscribe(res => {
        this.user = res;
        //console.log(this.user);
        this._processAdminConfigData();
        if (this.user.isSuperAdmin) {
          this.adminService.getFilesInGDriveAppFolder().subscribe(res => {
            this.configBackups = res;
            //console.log(this.configBackups);
          });
        }
      });
    });

    //debouce model change
    this.appConfigSubject.pipe(
      debounceTime(300)
    ).subscribe(data => {
      this.onFieldChange(data[0], +data[1] ?? 0);
    });
  }

  ngOnDestroy() {
    this.appConfigSubject.unsubscribe();
  }

  private _processAdminConfigData(kind: string = null) {
    this.adminService.getConfigsAdmin().subscribe(res => {
      if (!res.error) {        
        //console.log(res);
        const keysOrder = res.keys;
        this.sectionDefinitions = res.sectionDefinitions;
        if ((!kind || kind == 'global') && res.global) this._processAdminGlobalConfigData(res.global, keysOrder);
        if ((!kind || kind == 'libraries') && res.libraries) this._processAdminLibrariesConfigData(res.libraries, keysOrder);
        this.serverCheck = res.serverCheck;
        this.staticConfigs = res.staticConfigs;
      } else {
        this.router.navigate(['/unauthed'], { skipLocationChange: true });
      }
    }, errror => {
      this.router.navigate(['/unauthed'], { skipLocationChange: true });
    });
  }

  private _processAdminGlobalConfigData(data: any, keysOrder: any) {
    this.appConfig = [];
    this.appConfigCopy = [];
    data.forEach(e => {
      this.appConfig.push(this._processConfigField(e, keysOrder));
      this.appConfigCopy.push(this._processConfigField(e, keysOrder));
    });
    this.appConfigDirtyCount.isLoading = false;
    this.appConfigDirtyCount.count = 0;
    this.isBusy = false;

    //console.log(this.appConfigCopy);
  }

  private _processAdminLibrariesConfigData(data: any, keysOrder: any) {
    this.appConfigLibraries = [];
    this.appConfigLibrariesCopy = [];
    this.appConfigLibrariesDirtyCount = [];
    data.forEach(library => {
      let lib = [];
      library.forEach(e => {        
        if (e[0] == 'key') this.appConfigLibrariesDirtyCount.push({ libKey: e[1], count: 0, isLoading: false });
        if (e[0] == 'name') this.appConfigLibrariesDirtyCount[this.appConfigLibrariesDirtyCount.length - 1].name = e[1];
        if (e[0] == 'isDefault') this.appConfigLibrariesDirtyCount[this.appConfigLibrariesDirtyCount.length - 1].isDefault = true;
        lib.push(this._processConfigField(e, keysOrder));
      });
      this.appConfigLibraries.push(lib);
      //console.log(this.appConfigLibrariesDirtyCount);
      
    });
    //clone
    this.appConfigLibrariesCopy = JSON.parse(JSON.stringify(this.appConfigLibraries));
    //console.log(this.appConfigLibraries);
    //console.log(this.appConfigLibrariesDirtyCount);
    this.isBusy = false;

  }

  private _processConfigField(_field, keysOrder) {    
    let i = 0;
    let field = {};
    if (!_field) return field;

    for (const [key, value] of Object.entries(_field)) {
      //console.log(value);
      if (keysOrder[i] == 'editable') {
        let icon = 'edit'
        let iconTooltip = 'editable';
        if (value == 2) {
          icon = 'edit_notifications'
          iconTooltip = 'editable with caution';
        } else if (value == -1) {
          icon = 'warning'
          iconTooltip = 'read only';
        } else if (value == -2) {
          icon = 'visibility_off'
          iconTooltip = 'hidden field';
        }

        field[keysOrder[i]] = value;
        field['icon'] = icon;
        field['iconTooltip'] = iconTooltip;
      } else {
        field[keysOrder[i]] = value;
      }
      i++;
    }

    if (field['type'] == 'group') {
      field['value'].forEach(e => {
        if (!field['children']) field['children'] = [];
        field['children'].push(this._processConfigField(e, keysOrder))
      });
      delete field['value'];
    }
    return field;
  }

  onFieldChange(group: string, libIndex: number = 0) {
    if (group == 'appConfig') {
      this.compareArrays(this.appConfigCopy, this.appConfig, this.appConfigDirtyCount);
    } else if (group == 'appConfigLibraries') {
      this.compareArrays(this.appConfigLibrariesCopy[libIndex], this.appConfigLibraries[libIndex], this.appConfigLibrariesDirtyCount[libIndex]);
    } else {
      return
    }
  }

  //mark field as dirty
  compareArrays(arr1: any, arr2: any, diffCount: { count: number }, isRecrusive: boolean = false) {
    for (var x = 0; x < arr1.length; x++) {
      if (arr1[x].type != 'group') {
        // //console.log(arr1[x].key, arr1[x].value, arr2[x].value);                
        if ((!Array.isArray(arr1[x].value) && arr1[x].value != arr2[x].value) || (Array.isArray(arr1[x].value) && arr1[x].value.toString() != arr2[x].value.toString())) {
          if (!arr1[x].isDirty) {
            diffCount.count++;
            arr1[x].isDirty = true;
          }
        } else {
          if (arr1[x].isDirty) {
            diffCount.count--;
            arr1[x].isDirty = false;
          }
        }
      } else {
        //recursive
        this.compareArrays(arr1[x].children, arr2[x].children, diffCount, true);
      }
    }

    //if (!isRecrusive) console.log(diffCount);
  }

  updateConfig(config: any, isRecursive: boolean = false) {
    //convert back to assoc array
    // //console.log('update');
    //console.log(config);
    let kind: string = 'global';
    let libKey: string = null;
    let libIndex: number = null;

    if (!isRecursive) {
      this.isBusy = true;
      this.appConfigDirtyCount.isLoading = true;
      this.obj = {};
      config.forEach(f => {
        //check wht kind of config
        if (f.key == 'key') {
          kind = 'library';
          libKey = f.value;
          libIndex = this.appConfigLibrariesDirtyCount.findIndex(lib => { return lib.libKey == libKey });
          this.appConfigLibrariesDirtyCount[libIndex].isLoading = true;
          this.appConfigDirtyCount.isLoading = false;
        }
        if (f.children) {
          let retVal = this.updateConfig(f.children, true);
          if (retVal) this.obj[f.key] = retVal;
        } else {
          if (f.isDirty) {
            if (f.type == 'array') {
              let trimedArr = f.value.split(',').map(str => str.trim());
              this.obj[f.key] = trimedArr;
            } else {
              this.obj[f.key] = f.value;
            }
          }
        }
      })
    } else {
      let vals = {};
      config.forEach(f => {
        if (f.children) {
          let retVal = this.updateConfig(f.children, true);
          if (retVal) vals[f.key] = retVal;
        } else {
          if (f.isDirty) {
            if (f.type == 'array') {
              let trimedArr = f.value.split(',').map(str => str.trim());
              vals[f.key] = trimedArr;
            } else {
              vals[f.key] = f.value;
            }
          } else {
            vals[f.key] = null;
          }
        }
      });
      let temp = this._removeEmpty(vals);
      if (Object.keys(temp).length) {
        return temp;
      } else {
        return
      }
    }

    if (!isRecursive) {
      //console.log(kind);
      //console.log(libKey);
      //console.log(this.obj);
      this.adminService.updateConfigAdmin(this.obj, kind, libKey).subscribe(res => {
        //console.log(res);
        if (kind == 'global') {
          this._processAdminConfigData('global');
        } else {
          this._processAdminConfigData('libraries');
        }
      });
    }

  }

  private _removeEmpty(obj) {
    return Object.entries(obj)
      .filter(([_, v]) => {
        return v != null
      })
      .reduce(
        (acc, [k, v]) => ({ ...acc, [k]: v === Object(v) ? this._removeEmpty(v) : v }),
        {}
      );
  }

  addNewLib(newLib?: { key: string, name: string }) {
    if (!newLib && !this.newLib) {
      this.newLib = { key: '', name: '' };
    } else if (!newLib && this.newLib) {
      this.newLib = null;
    } else if (newLib) {
      this._submitNewLib(newLib)
    }
  }

  private _submitNewLib(newLib) {
    //console.log('add new lib', newLib);
    this.isBusy = true;

    this.adminService.addNewLibrary(newLib).subscribe(res => {
      //console.log(res);
      if (res.result?.success) {
        this.newLib = null;
        this.configService.getConfig(true).subscribe(res => this.config = res);
        this.configService.getLang(true).subscribe();
        this.configService.getCustomization(true).subscribe();
        this._processAdminConfigData('libraries');
      } else {
        this.snackBar.open(`ERROR: ${res.error}`, '', {
          duration: 5000,
        });
      }
    }, error => {
      this.snackBar.open(`ERROR: unexpected error a.k.a. I haz fail`, '', {duration: 5000})
    })
  }

  removeLibrary(libIndex: number) {
    //console.log('remove lib', libIndex);
    this.isBusy = true;
    const libKey = this.appConfigLibrariesDirtyCount[libIndex].libKey;
    this.adminService.removeLibrary(libKey).subscribe(res => {
      //console.log(res);
      if (res.result?.success) {
        this.newLib = null;
        this._processAdminConfigData('libraries');
      } else {
        this.snackBar.open(`ERROR: ${res.error}`, '', {
          duration: 5000,
        });
      }
    },error => {
      this.snackBar.open(`ERROR: unexpected error a.k.a. I haz fail`, '', {duration: 5000})
    })

  }

  //for *ngFor | keyvalue: noSort
  noSort() {
    return 0;
  }

  getRevision(index: number) {
    //console.log(`getting revision for: ${this.configBackups.id} - ${this.configBackups.revisions[index].id}`);
    if (!this.configBackups.revisions[index].data) {
      this.adminService.getFileRevisionData(this.configBackups.id, this.configBackups.revisions[index].id).subscribe( res => {
        //console.log(res);
        this.configBackups.revisions[index].data = res.body; 
      })
      
    }
    this.configBackups.viewRevIndex = index;
    
  }

}
