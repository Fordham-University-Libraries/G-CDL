import { Component, OnInit } from '@angular/core';
import { Title } from '@angular/platform-browser';
import { Router } from '@angular/router';
import { User } from '../models/user.model';
import { Config } from '../models/config.model';
//import { Customization } from '../models/customization.model';
import { AuthenticationService } from '../auth.service';
import { ConfigService } from '../config.service';
import { AdminService } from '../admin.service';
import { Subject } from 'rxjs';
import { debounceTime } from 'rxjs/operators';

@Component({
  selector: 'app-app-customization',
  templateUrl: './app-customization.component.html',
  styleUrls: ['./app-customization.component.scss']
})
export class AppCustomizationComponent implements OnInit {
  user: User;
  isBusy: boolean;
  itemEditDialogRef: any;
  config: Config;
  appCustLibraries = [];
  appCustLibrariesCopy = [];
  appCustLibrariesDirtyCount: { libKey: string, name?: string, count: number, isLoading: boolean, isDefault?: boolean }[] = [];
  sectionDefinitions: any;
  appCustGlobal: any;
  appCustomizationSubject: Subject<string[]> = new Subject();
  obj = {};

  constructor(
    private router: Router,
    private configService: ConfigService,
    private authService: AuthenticationService,
    private titleService: Title,
    private adminService: AdminService,
  ) { }

  ngOnInit(): void {
    this._processAdminCustData();
    this.configService.getConfig().subscribe(cRes => {
      this.config = cRes;
      this.titleService.setTitle(`Admin/Config/Customizations : ${this.config.appName}`);
      this.authService.getUser().subscribe(res => {
        this.user = res;
      });
    });

    //debouce model change
    this.appCustomizationSubject.pipe(
      debounceTime(300)
    ).subscribe(libIndex => {
      this.onFieldChange(+libIndex ?? 0);
    });
  }

  ngOnDestroy() {
    this.appCustomizationSubject.unsubscribe();
  }

  private _processAdminCustData(kind: string = null) {
    this.adminService.getCustomizationAdmin().subscribe(res => {
      if (!res.error) {
        const keysOrder = res.keys;
        this.sectionDefinitions = res.sectionDefinitions;
        this.appCustGlobal = res.appGlobal;
        //console.log(this.appCustGlobal);
        this.appCustLibraries = [];
        this.appCustLibrariesCopy = [];
        this.appCustLibrariesDirtyCount = [];
        for (const [key, library] of Object.entries(res.libraries)) {
          this.appCustLibrariesDirtyCount.push({ libKey: key, name: this.config.libraries[key].name, count: 0, isLoading: false });
          let lib = [];
          for (const [areaKey, e] of Object.entries(library)) {
            lib.push(this._processConfigField(e, keysOrder));
          };
          this.appCustLibraries.push(lib);
        };
        //clone
        this.appCustLibrariesCopy = JSON.parse(JSON.stringify(this.appCustLibraries));
        // //console.log(this.appCustLibraries);
        //console.log(this.appCustLibrariesCopy);
        // //console.log(this.appCustLibrariesDirtyCount);
        this.isBusy = false;
      } else {
        this.router.navigate(['/unauthed'], { skipLocationChange: true });
      }
    }, error => {
      this.router.navigate(['/unauthed'], { skipLocationChange: true });
    });
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
      }  else {
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

  onFieldChange(libIndex: number = 0) {
    this.compareArrays(this.appCustLibrariesCopy[libIndex], this.appCustLibraries[libIndex], this.appCustLibrariesDirtyCount[libIndex]);
  }

  //mark field as dirty
  compareArrays(arr1: any, arr2: any, diffCount, isRecrusive: boolean = false) {
    for (var x = 0; x < arr1.length; x++) {
      if (arr1[x].type != 'group') {
        //console.log(arr1[x].key, arr1[x].value, arr2[x].value);                
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

  updateCust(config: any, libIndex: number = 0, isRecursive: boolean = false) {
    //convert back to assoc array
    // //console.log('update');
    let libKey: string = this.appCustLibrariesDirtyCount[libIndex].libKey;
    if (!isRecursive) {
      this.isBusy = true;
      this.appCustLibrariesDirtyCount[libIndex].isLoading = true;
      this.obj = {};
      config.forEach(f => {
        if (f.children) {
          let retVal = this.updateCust(f.children, libIndex, true);
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
          let retVal = this.updateCust(f.children, libIndex, true);
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
      // //console.log(libKey);
      // //console.log(this.obj);
      this.adminService.updateCustomizationAdmin(this.obj, libKey).subscribe(res => {
        // //console.log(res);
        this.configService.onForceRefresh.emit(true);
        this._processAdminCustData();
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

  updateAppCustGlobal() {
    this.isBusy = true;
    this.adminService.updateCustomizationAdmin(this.appCustGlobal, 'appGlobal').subscribe(res => {
      this.isBusy = false;
    });
  }

  isArray(obj : any ) {
    return Array.isArray(obj)
  }
 
}
