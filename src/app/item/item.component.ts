import { Component, OnInit } from '@angular/core';
import { Title } from '@angular/platform-browser';
import { ActivatedRoute, Router } from '@angular/router';
import { MatSnackBar } from '@angular/material/snack-bar';
import { MatDialog, MatDialogRef } from '@angular/material/dialog';
import { AuthenticationService } from '../auth.service';
import { CatalogService } from '../catalog.service';
import { DriveService } from '../drive.service';
import { Item } from '../models/item.model';
import { User } from '../models/user.model';
import { TermOfServiceComponent } from '../term-of-service/term-of-service.component';
import { GaService, ACTIONS, CATEGORIES } from '../ga.service';
import { environment } from 'src/environments/environment';
import { ConfigService } from '../config.service';
import { Subject } from 'rxjs';


@Component({
  selector: 'app-item',
  templateUrl: './item.component.html',
  styleUrls: ['./item.component.scss']
})
export class ItemComponent implements OnInit {
  user: User;
  mode: string = 'bibId';
  pageTitle: string;
  library: string;
  fileId: string;
  bibId: string;
  itemId: string;
  error: string;
  isBusy: boolean;
  busyAction: string;
  userHasItemCheckedOut: boolean;
  readClickedSubject: Subject<void> = new Subject<void>();
  checkedOutitemRefreshSubject: Subject<void> = new Subject<void>();
  catalogBib: any;
  checkedOutItem: any;
  items: Item[];
  isLoadingItems: boolean;
  accessibleUserDialogRef: MatDialogRef<TermOfServiceComponent, boolean>;
  syndeticClientId: string;
  lang:any;
  config: any;
  customization: any;

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private snackBar: MatSnackBar,
    private dialog: MatDialog,
    private catalogService: CatalogService,
    private driveService: DriveService,
    private authService: AuthenticationService,
    private titleService: Title,
    private gaService: GaService,
    private configService: ConfigService
  ) { 
  }

  ngOnInit(): void {
    this.configService.getLang().subscribe(res => {this.lang = res;});
    this.configService.getConfig().subscribe(res => {
      this.config = res; 
      this.route.paramMap.subscribe(paramMap => {
      this.pageTitle = `Item View : ${this.config.appName}`;
      this.titleService.setTitle(this.pageTitle);
      this.gaService.logPageView(this.pageTitle, location.pathname);
      this.mode = this.route.snapshot.data.mode;
      //console.log(this.mode);
      this.authService.getUser().subscribe(res => {
        this.user = res;
      });
      //console.log(this.library);
      this.fileId = paramMap.get('fileId') ?? null;
      this.bibId = paramMap.get('bibId') ?? null;
      this.itemId = paramMap.get('itemId') ?? null;
      this.library = paramMap.get('library') ?? this.config.defaultLibrary;
      if (!this.config.libraries[this.library]) {
        this.router.navigate(['/error-no-library'], {skipLocationChange: true});
        return;
      }
      this.configService.getCustomization().subscribe(custRes => { 
        this.customization = custRes;         
        this.syndeticClientId = this.customization[this.library].item.syndeticClientId;
        this._getItem();
      });
    });
  });
  }

  private _getItem() {    
    this.isLoadingItems = true;
    this._getDriveItem(this.bibId ? this.bibId : this.itemId, this.bibId ? 'bibId' : 'itemId');
    if (this.config.libraries[this.library].ilsApiEnabled && this.customization[this.library].item.useIlsApiForMetadataEnhancement) {
      this.catalogService.getBib(this.bibId ? this.bibId : this.itemId, this.library).subscribe(res => {        
        if (res) { this.catalogBib = res; }
      });
    }
  }

  private _getDriveItem(key: any, keyType: string) {
    this.gaService.logEvent(ACTIONS.itemView, CATEGORIES.item, `${keyType}: ${key}`);
    this.driveService.getItems(key, keyType, this.library).subscribe(res => {
      this.items = res;
      //for multi parts item
      this.items.sort((a: any, b: any) => {
        if (a.part && b.part) {
          return a.part - b.part;
        } else {
          return a;
        }
      });
      this.isLoadingItems = false;
      //console.log(this.items);
    }, error => {
      console.error(error);
      this.gaService.logError('item-compo: getItem fail', true);
      this.error = "Digital Reserves Item NOT found";
      this.isLoadingItems = false;
    })
  }


  borrow(id: string, title?: string, tosAccepted: boolean = false) {
    //console.log(`borrowing: ${id} / ${title}`);
    if (this.user.isAccessibleUser && !tosAccepted) {
      this.accessibleUserDialogRef = this.dialog.open(TermOfServiceComponent, { data: { title: title }, panelClass: 'accessible-user-dialog' });
      this.accessibleUserDialogRef.afterClosed().subscribe(accept => {
        if (accept) this.borrow(id, title, true)
      });
      return;
    }
    this.gaService.logEvent(ACTIONS.borrow, CATEGORIES.item, id);
    this.busyAction = 'borrow';
    this.isBusy = true;
    this.driveService.borrowItem(id).subscribe(res => {
      //console.log(res);
      if (res?.borrowSuccess) {
        this.checkedOutitemRefreshSubject.next();
        this._getItem();
        this.driveService.clearAllItemsCache();
        this.isBusy = false;
      } else {
        console.error(res);
        this.isBusy = false;
        this.snackBar.open(res.error, 'Dismiss', {
          duration: 8000,
        });
      }
    }, (error) => {
      console.error(error);
      this.isBusy = false;
      this.snackBar.open(this.lang.error.borrow.unknownError, 'Dismiss', {
        duration: 3000,
      });
    });
  }

  read() {
    this.readClickedSubject.next();
  }

  onUserHasItemCheckedOutCheck(event: boolean) {
    this.userHasItemCheckedOut = event;
  }

  onRefreshParent() {
    this.driveService.clearAllItemsCache();
    this._getItem();
  }

  openInCatalog() {
    if (!this.customization[this.library].item.catalogUrl) return;

    let url = `${this.customization[this.library].item.catalogUrl}`;
    if (this.customization[this.library].item.catalogUrl.includes('{{$bibId}}')) {
      url = this.customization[this.library].item.catalogUrl.replace('{{$bibId}}', this.bibId)
    }
    if (this.customization[this.library].item.catalogUrl.includes('{{$itemId}}')) {
      url = this.customization[this.library].item.catalogUrl.replace('{{$itemId}}', this.itemId)
    }
    this.gaService.logEvent(ACTIONS.openInCatalog, CATEGORIES.item, '' + this.bibId);
    window.open(url, '_blank');
  }

  getSierraCheckDigit(recordNumber: string) {
    let m = 2;
    let x = 0;
    let i = +recordNumber;
    while (i > 0) {
      let a = i % 10;
      i = Math.floor(i / 10);
      x += a * m;
      m += 1;
    }
    let r = x % 11;
    return r === 10 ? 'x' : r;
  }
}
