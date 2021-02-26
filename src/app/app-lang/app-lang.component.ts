import { Component, OnInit, ViewChild } from '@angular/core';
import { Title } from '@angular/platform-browser';
import { ActivatedRoute, Router } from '@angular/router';
import { MatSort } from '@angular/material/sort';
import { MatTableDataSource } from '@angular/material/table';
import { MatPaginator } from '@angular/material/paginator';
import { MatDialog } from '@angular/material/dialog';
import { MatSnackBar } from '@angular/material/snack-bar';
import { User } from '../models/user.model';
import { Item } from '../models/item.model';
import { AuthenticationService } from '../auth.service';
import { DriveService } from '../drive.service';
import { ConfigService } from '../config.service';
import { AdminService } from '../admin.service';
import { AdminItemEditComponent } from '../admin-item-edit/admin-item-edit.component';
import { stringify } from '@angular/compiler/src/util';
import { Subject } from 'rxjs';
import { debounceTime } from 'rxjs/operators';
import { AngularEditorConfig } from '@kolkov/angular-editor';

@Component({
  selector: 'app-app-lang',
  templateUrl: './app-lang.component.html',
  styleUrls: ['./app-lang.component.scss']
})
export class AppLangComponent implements OnInit {
  user: User;
  config: any;
  aboutLibraries = [];
  aboutLibrariesCopy = [];
  appLangLibraries = [];
  appLangLibrariesCopy = [];
  appConfigDirtyCount = { 'count': 0, 'isLoading': false };
  appLangLibrariesDirtyCount: { libKey: string, count: number, isLoading: boolean, isDefault?: boolean }[] = [];
  rootKeyTemp: string;
  appLangSubject: Subject<string[]> = new Subject();
  obj = {};
  newLib: { key: string, name: string };
  sectionDefinitions: any;
  availableTokens: any;
  editorConfig: AngularEditorConfig = {
    editable: true,
    minHeight: '200px',
    defaultParagraphSeparator: 'p'
  }

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private dialog: MatDialog,
    private driveService: DriveService,
    private configService: ConfigService,
    private authService: AuthenticationService,
    private titleService: Title,
    private adminService: AdminService,
    private snackBar: MatSnackBar,
  ) { }

  ngOnInit(): void {
    this.appConfigDirtyCount.isLoading = true;
    this._processAdminLangData();
    this.configService.getConfig().subscribe(cRes => {
      this.config = cRes;
      this.titleService.setTitle(`Admin/Config/Lang : ${this.config.appName}`);
      this.authService.getUser().subscribe(res => {
        this.user = res;
      });
    });

    //debouce model change
    this.appLangSubject.pipe(
      debounceTime(300)
    ).subscribe(libIndex => {
      this.onFieldChange(+libIndex ?? 0);
    });
  }

  ngOnDestroy() {
    this.appLangSubject.unsubscribe();
  }

  private _processAdminLangData(kind: string = null) {
    this.adminService.getLangAdmin().subscribe(res => {
      if (!res.error) {
        const keysOrder = res.keys;
        this.sectionDefinitions = res.sectionDefinitions;
        this.availableTokens = res.globalLangToken;
        //console.log(res);
        this.appLangLibraries = [];
        this.appLangLibrariesCopy = [];
        this.appLangLibrariesDirtyCount = [];
        for (const [key, library] of Object.entries(res.libraries)) {
          this.appLangLibrariesDirtyCount.push({ libKey: key, count: 0, isLoading: false });
          let lib = [];
          for (const [areaKey, e] of Object.entries(library)) {
            lib.push(this._processConfigField(e, keysOrder));
          };
          this.appLangLibraries.push(lib);
          this.aboutLibraries[key] = res.abouts[key];
          this.aboutLibrariesCopy[key] = res.abouts[key];
        };
        //clone
        this.appLangLibrariesCopy = JSON.parse(JSON.stringify(this.appLangLibraries));
        // console.log(this.appLangLibraries);
        // console.log(this.appLangLibrariesCopy);
        // console.log(this.appLangLibrariesDirtyCount);
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

  onFieldChange(libIndex: number = 0) {    
    this.compareArrays(this.appLangLibrariesCopy[libIndex], this.appLangLibraries[libIndex], this.appLangLibrariesDirtyCount[libIndex]);
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

    if (!isRecrusive) console.log(diffCount);
  }

  updateLang(config: any, libIndex: number = 0, isRecursive: boolean = false) {
    //convert back to assoc array
    // console.log('update');
    let libKey: string = this.appLangLibrariesDirtyCount[libIndex].libKey;
    if (!isRecursive) {
      this.appLangLibrariesDirtyCount[libIndex].isLoading = true;
      this.obj = {};
      config.forEach(f => {
        this.rootKeyTemp = f.key;
        if (f.children) {
          let retVal = this.updateLang(f.children, libIndex, true);
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
          let retVal = this.updateLang(f.children, libIndex, true);
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
      console.log(libKey);
      console.log(this.obj);

      this.adminService.updateLangAdmin(this.obj, libKey).subscribe(res => {
        console.log(res);
        this.configService.onForceRefresh.emit(true);
        this._processAdminLangData();
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

  updateAboutPage(libKey: string) {
    console.log(this.aboutLibrariesCopy[libKey]);
    this.adminService.updateAboutAdmin(this.aboutLibrariesCopy[libKey], libKey).subscribe(res => {
      console.log(res);
    })
  }

  isArray(obj : any ) {
    return Array.isArray(obj)
  }

}
