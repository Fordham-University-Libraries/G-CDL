import { Component, OnInit } from '@angular/core';
import { Title } from '@angular/platform-browser';
import { ActivatedRoute, Router } from '@angular/router';
import { MatSnackBar } from '@angular/material/snack-bar';
import { User } from '../models/user.model';
import { AuthenticationService } from '../auth.service';
import { ConfigService } from '../config.service';
import { AdminService } from '../admin.service';
import { Subject } from 'rxjs';
import { debounceTime } from 'rxjs/operators';

@Component({
  selector: 'app-app-config',
  templateUrl: './app-config.component.html',
  styleUrls: ['./app-config.component.scss']
})
export class AppConfigComponent implements OnInit {
  user: User;
  isBusy: boolean;
  config: any;
  appConfig = []; //stuff we want to edit
  appConfigLibraries = [];
  appConfigCopy = [];
  appConfigLibrariesCopy = [];
  appConfigDirtyCount = { 'count': 0, 'isLoading': false };
  appConfigLibrariesDirtyCount: { libKey: string, count: number, isLoading: boolean, isDefault?: boolean }[] = [];
  appConfigSubject: Subject<string[]> = new Subject();
  obj = {};
  newLib: { key: string, name: string };
  sectionDefinitions: any;

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private configService: ConfigService,
    private authService: AuthenticationService,
    private titleService: Title,
    private adminService: AdminService,
    private snackBar: MatSnackBar,
  ) { }

  ngOnInit(): void {
    this.appConfigDirtyCount.isLoading = true;
    this._processAdminConfigData();
    this.configService.getConfig().subscribe(cRes => {
      this.config = cRes;
      this.titleService.setTitle(`Admin/Config : ${this.config.appName}`);
      this.authService.getUser().subscribe(res => {
        this.user = res;
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
        console.log(res);
        
        const keysOrder = res.keys;
        this.sectionDefinitions = res.sectionDefinitions;
        if ((!kind || kind == 'global') && res.global) this._processAdminGlobalConfigData(res.global, keysOrder);
        if ((!kind || kind == 'libraries') && res.libraries) this._processAdminLibrariesConfigData(res.libraries, keysOrder);
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

    console.log(this.appConfig);
  }

  private _processAdminLibrariesConfigData(data: any, keysOrder: any) {
    this.appConfigLibraries = [];
    this.appConfigLibrariesCopy = [];
    this.appConfigLibrariesDirtyCount = [];
    data.forEach(library => {
      let lib = [];
      library.forEach(e => {
        if (e[0] == 'key') this.appConfigLibrariesDirtyCount.push({ libKey: e[1], count: 0, isLoading: false });
        if (e[0] == 'isDefault') this.appConfigLibrariesDirtyCount[this.appConfigLibrariesDirtyCount.length - 1].isDefault = true;
        lib.push(this._processConfigField(e, keysOrder));
      });
      this.appConfigLibraries.push(lib);
    });
    //clone
    this.appConfigLibrariesCopy = JSON.parse(JSON.stringify(this.appConfigLibraries));
    console.log(this.appConfigLibraries);
    console.log(this.appConfigLibrariesDirtyCount);
    this.isBusy = false;

  }

  private _processConfigField(filed, keysOrder) {
    let i = 0;
    let field = {};
    for (const [key, value] of Object.entries(filed)) {
      //console.log(value);
      if (keysOrder[i] == 'editable') {
        let icon = 'edit'
        if (value == 2 && !this.user.isDriveOwner) {
          icon = 'edit_notifications'
        } else if (value == -1 && !this.user.isDriveOwner) {
          icon = 'warning'
        } else if (value == -2 && !this.user.isDriveOwner) {
          icon = 'hide'
        }

        field[keysOrder[i]] = icon;
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
        // console.log(arr1[x].key, arr1[x].value, arr2[x].value);                
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

    if (!isRecrusive) console.log(diffCount);
  }

  updateConfig(config: any, isRecursive: boolean = false) {
    //convert back to assoc array
    // console.log('update');
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
      console.log(kind);
      console.log(libKey);
      console.log(this.obj);
      this.adminService.updateConfigAdmin(this.obj, kind, libKey).subscribe(res => {
        console.log(res);
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
    console.log('add new lib', newLib);
    this.isBusy = true;

    this.adminService.addNewLibrary(newLib).subscribe(res => {
      console.log(res);
      if (res.result?.success) {
        this.newLib = null;
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
    console.log('remove lib', libIndex);
    this.isBusy = true;
    const libKey = this.appConfigLibrariesDirtyCount[libIndex].libKey;
    this.adminService.removeLibrary(libKey).subscribe(res => {
      console.log(res);
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

}
