import { Component, OnInit } from '@angular/core';
import { Title } from '@angular/platform-browser';
import { ActivatedRoute, Router } from '@angular/router';
import { MatSnackBar } from '@angular/material/snack-bar';
import { User } from '../models/user.model';
//import { Item } from '../models/item.model';
import { Config } from '../models/config.model';
import { Language } from '../models/language.model';
import { AuthenticationService } from '../auth.service';
import { DriveService } from '../drive.service';
import { ConfigService } from '../config.service';
import { AdminService } from '../admin.service';
import { CatalogService } from '../catalog.service';
import { Subject } from 'rxjs';
import { debounceTime } from 'rxjs/operators';
interface pdfFileField { label: string, value?: string, hint?: string, group?: string, required?: boolean, error?: string };

@Component({
  selector: 'app-admin-upload',
  templateUrl: './admin-upload.component.html',
  styleUrls: ['./admin-upload.component.scss']
})

export class AdminUploadComponent implements OnInit {
  isBusy: boolean;
  library: string;
  isStaff: boolean;
  user: User;
  isLoading: boolean;
  staff: string[];
  admins: string[];
  config: Config;
  lang: Language;
  uploadLang: any;
  uploadUrl: string;
  pdfItem: {
    file?: File,
    fileName? : pdfFileField,
    fileSize? : pdfFileField,
    bibId? : pdfFileField,
    itemId? : pdfFileField,
    title? : pdfFileField,
    author? : pdfFileField,
    part? : pdfFileField,
    partTotal? : pdfFileField,
    partDesc? : pdfFileField,
    uploadedWithOcrId? : pdfFileField,
    shouldCreateNoOcr?: boolean,
    uploadedNoOcrId? : pdfFileField,
  } = {};
  itemIdInFilenameRegexPattern: string;
  regEx101Url: string;
  isIlsApiEnabled: boolean;
  getBibFromILS: boolean = true;
  getItemIdFromFilename: boolean = true;
  warning: string;
  error: string;
  fieldError: number;
  fieldSubject: Subject<any> = new Subject();
  formWasTouched: boolean;
  uploadedFileInfo: {
    success: boolean,
    fileName: string,
    uploadedNoOcrFileId: string,
  }

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private driveService: DriveService,
    private configService: ConfigService,
    private authService: AuthenticationService,
    private titleService: Title,
    private adminService: AdminService,
    private catalogService: CatalogService,
    private snackBar: MatSnackBar,
  ) { }

  ngOnInit(): void {
    this.configService.getConfig().subscribe(cRes => {
      this.config = cRes;
      this.titleService.setTitle(`Admin/Upload : ${this.config.appName}`);
      this.authService.getUser().subscribe(res => {
        this.user = res;
        if (this.user.isStaffOfLibraries?.length) {
          this.isStaff = true;
          this.route.paramMap.subscribe(paramMap => {
            if (paramMap.get('library')) {              
              this.library = paramMap.get('library');
              if (!this.user.isStaffOfLibraries?.includes(this.library)) {
                this.router.navigate(['/unauthed'], { skipLocationChange: true });
                return;
              } else {
              }
            } else {
              if (!this.user.isStaffOfLibraries?.includes(this.config.defaultLibrary)) {
                this.router.navigate(['/unauthed'], { skipLocationChange: true });
                return;
              } else {
                this.library = this.config.defaultLibrary;
              }
            }

            if (this.config.libraries[this.library]?.itemIdInFilenameRegexPattern) {
              this.itemIdInFilenameRegexPattern = this.config.libraries[this.library]?.itemIdInFilenameRegexPattern;
              this.regEx101Url = `https://regex101.com/?regex=${encodeURIComponent(this.itemIdInFilenameRegexPattern)}&testString=qwertyuiopasdfghjklzxcvbnm09876543211234567890.pdf`;
            }
            if (this.config.libraries[this.library]?.ilsApiEnabled) this.isIlsApiEnabled = true;
            this.configService.getLang().subscribe(res => {
              this.lang = res;   
              this.uploadLang = this.lang.libraries[this.library].upload   
            });
            this.uploadUrl = this.adminService.uploadUrl + "&libKey=" + this.library;
            this.generateForm();
          });
        } else {
          this.router.navigate(['/unauthed'], { skipLocationChange: true });
          return;
        }
      });
    });

    //debouce model change
    this.fieldSubject.pipe(
      debounceTime(300)
    ).subscribe(data => {
      this.onFieldChange(data);
    });
  }

  ngOnDestroy() {
    this.fieldSubject.unsubscribe();
  }

  generateForm() {
    this.pdfItem.bibId = {required: true, group: 'item', label: 'Bib ID', value: null, hint: 'Unique Record ID/Bibliographic ID of this item in your Library Management System. This field is used so the CDL app can group multiple items of that same "book" together'};
    this.pdfItem.itemId = {required: true, group: 'item', label: 'Item ID', value: null, hint: 'Unique Item Records ID/Barcode of this item. This filed is used so the CDL app differentiate multiple copies of the same "book"'};
    this.pdfItem.title = {required: true, group: 'item', label: 'Title', value: null};
    this.pdfItem.author = {group: 'item', label: 'Author', value: null};
    this.pdfItem.part = {group: 'part', label: 'Part #', value: null, hint: 'part of this file e.g 1'};
    this.pdfItem.partTotal = {group: 'part', label: 'of Total Part', value: null, hint: 'total part of "thing/book" this item is part of e.g. 6'};
    this.pdfItem.partDesc = {group: 'part', label: 'Part Description', value: null, hint: 'a description so end users have more info e.g. "Chaper 1-13" or "Letter A-F"'};
    this.pdfItem.fileName = {group: 'file', label: 'File Name', value: null};
    this.pdfItem.fileSize = {group: 'file', label: 'File Size', value: null};
    this.pdfItem.shouldCreateNoOcr = true;
    //console.log(this.pdfItem);
  }

  //for *ngFor | keyvalue: noSort
  noSort() {
    return 0;
  }

  onFieldChange(field: any) {
    //console.log(field);
    this.formWasTouched = true;
    if (field.value.required) {
      if (!field.value.value) {
        if(!this.pdfItem[field.key].error) { 
          this.pdfItem[field.key].error = 'This field is required!';
          this.fieldError ? this.fieldError++ : this.fieldError = 1;
        }
      } else {
        if(this.pdfItem[field.key].error) {
           this.pdfItem[field.key].error = null;
           this.fieldError ? this.fieldError-- : '';
        }
      }
      //console.log(this.fieldError);    
    }
  }

  handleFileInput(file: any) {
    //console.log(file);
    this.formWasTouched = true;
    if (file.length) {
      this.error = null;
      if (file[0].type != 'application/pdf') { 
        this.error = 'Only PDF file is supported';
        return;
      }
      //size in bytes but config in MB
      if (file[0].size > (this.config.maxFileSizeInMb * 1e+6)) { 
        this.error = `File size too big (must be smaller than ${this.config.maxFileSizeInMb} MB)`;
        return;
      }
      this.pdfItem.file = file[0];
      this.pdfItem.fileName.value = file[0].name;
      this.pdfItem.fileSize.value = file[0].size;
      //get itemId form file name
      if (this.itemIdInFilenameRegexPattern && this.getItemIdFromFilename) {
        const pattern = new RegExp(this.itemIdInFilenameRegexPattern);     
        const found = this.pdfItem.fileName.value.match(pattern);
        if (found?.length > 1) {
          this.pdfItem.itemId.value = found[1];
          this._checkItemIdAlreadyInSystem(this.pdfItem.itemId.value);
          //check part
          const partRegex = /\[(\d)+.?of.?(\d)+\]/;
          const partsFound = this.pdfItem.fileName.value.match(partRegex);
          if (partsFound?.length) {
            this.pdfItem.part.value = partsFound[1];
            this.pdfItem.partTotal.value = partsFound[2];
          }
        } else {
          this.warning = `Heads up! couldn't find an item ID from the filename: ${this.pdfItem.fileName.value}`;
        }
      } else {
        this.warning = null;
      }

      //get bib from ILS
      if (this.config.libraries[this.library].ilsApiEnabled && this.getBibFromILS && this.pdfItem.itemId.value) {
        this._getIlsBib(this.pdfItem.itemId.value);
      }
      
    } else {
      //no file
      for (const [key, value] of Object.entries(this.pdfItem)) {
        this.pdfItem[key].value = null;
      }
      this.error = 'Please choose a file to upload';   
    }
  }

  _getIlsBib(itemId: any) {
    this.catalogService.getBibByItemId(itemId, this.library).subscribe(res => {
      //console.log(res);
      if (res.title) {
        this.pdfItem.bibId.value = res.bibId;
        this.pdfItem.title.value = res.title;
        this.pdfItem.author.value = res.author ?? null;
      } else {
        this.warning = `Heads up! couldn't find an item with ID: ${this.pdfItem.itemId.value} in your ILS`;
      }
    }, error => {
      this.warning = `Heads up! couldn't find an item with ID: ${this.pdfItem.itemId.value} in your ILS`;
    })
  }

  _checkItemIdAlreadyInSystem(itemId: any) {
    this.driveService.getItems(itemId, 'itemId', this.library).subscribe(res => {
      if (res) {
        this.warning = `Heads up! This item (item ID: ${itemId}) is already in the CDL app, according to the CDL principle, we're supposed to maintain the own-to-borrow ratio! Please be careful`;
      }
    }, error => {
      this.warning = null;
    })
  }

  upload() {        
    if (this.error) return;
    Object.keys(this.pdfItem).forEach(key => {
      if (this.pdfItem[key].required && !this.pdfItem[key].value) this.error = 'Please fill in the required fields';
    });   
    if (!this.pdfItem.fileName.value) {
      this.error = 'Please choose a file to upload';
      this.warning = null;
      return;
    }
    if (this.error) return;

    this.isBusy = true;
    this.uploadedFileInfo = null;
    this.driveService.uploadAdmin(this.pdfItem, this.library).subscribe(res => {
        //console.log(res);
        if (res?.success) {
          if(res.uploadedNoOcrFileId) this.uploadedFileInfo = res;          
        } else if (res?.error) {
          this.error = res.error;
        } else {
          this.error = 'That did not work. Something went wrong!';
        }
        this.isBusy = false;
    }, error => {
      console.error(error);
      this.error = 'That did not work. Something went wrong!';
      this.isBusy = false;
    }) 
  }

  downLoadNoOcrVersion() {
    //console.log('downLoadNoOcrVersion: ' + this.uploadedFileInfo.uploadedNoOcrFileId);
    if (this.uploadedFileInfo.uploadedNoOcrFileId) this.driveService.downloadFileAdmin(this.uploadedFileInfo.uploadedNoOcrFileId, false);
  }

  onToggleChange(kind: string, enable: boolean) {
      if (!enable) {
        this.getItemIdFromFilename = false;
        this.getBibFromILS = false;
      } else {
        this.getItemIdFromFilename = true;
        this.getBibFromILS = true;
      }
  }

  resetForm(keepMetadata: boolean = false) {
    this.uploadedFileInfo = null;
    this.warning = null;
    this.error = null;
    this.formWasTouched = false;
    if (!keepMetadata) { 
      this.pdfItem = {};
      this.generateForm();
    } else {
      this.pdfItem.file = null;
      this.pdfItem.fileName.value = null;
      this.pdfItem.fileSize.value = null;
    }   
  }
}
