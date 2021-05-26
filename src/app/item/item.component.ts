import { Component, OnInit } from '@angular/core';
import { Title } from '@angular/platform-browser';
import { LiveAnnouncer } from '@angular/cdk/a11y';
import { ActivatedRoute } from '@angular/router';
import { MatSnackBar } from '@angular/material/snack-bar';
import { MatDialog, MatDialogRef } from '@angular/material/dialog';
import { AuthenticationService } from '../auth.service';
import { CatalogService } from '../catalog.service';
import { DriveService } from '../drive.service';
import { Item } from '../models/item.model';
import { User } from '../models/user.model';
import { Config } from '../models/config.model';
import { Language } from '../models/language.model';
import { Customization } from '../models/customization.model';
import { TermOfServiceComponent } from '../term-of-service/term-of-service.component';
import { GaService, ACTIONS, CATEGORIES } from '../ga.service';
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
  lang: Language;
  config: Config;
  customization: Customization;

  constructor(
    private route: ActivatedRoute,
    private liveAnnouncer: LiveAnnouncer,
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
      this.configService.getCustomization().subscribe(custRes => { 
        this.customization = custRes;         
        this.syndeticClientId = this.customization.libraries[this.library].item.syndeticClientId;
        this._getItem();
      });
    });
  });
  }

  private _getItem() {    
    this.isLoadingItems = true;
    this._getDriveItem(this.bibId ? this.bibId : this.itemId, this.bibId ? 'bibId' : 'itemId');
    if (this.config.libraries[this.library].ilsApiEnabled && this.customization.libraries[this.library].item.useIlsApiForMetadataEnhancement) {
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
    this.liveAnnouncer.announce('borrowing an item');
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
      this.snackBar.open(this.lang.libraries[this.library].error.borrow.unknownError, 'Dismiss', {
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
    if (!this.customization.libraries[this.library].item.catalogUrl) return;

    let url = `${this.customization.libraries[this.library].item.catalogUrl}`;
    if (url.includes('{{$bibId}}') && this.bibId) {
      url = url.replace('{{$bibId}}', this.bibId)
    }
    if (url.includes('{{$itemId}}')) {
      if (this.itemId) {
        url = url.replace('{{$itemId}}', this.itemId)
      } else if (this.items?.length && this.items[0].itemId) {
        url = url.replace('{{$itemId}}', this.items[0].itemId)
      }
    }

    this.gaService.logEvent(ACTIONS.openInCatalog, CATEGORIES.item, '' + this.bibId);
    window.open(url, '_blank');
  }
}
