import { Component, OnInit, Input, Output, EventEmitter } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { Config } from '../models/config.model';
import { Language } from '../models/language.model';
import { Customization } from '../models/customization.model';
import { Item } from '../models/item.model';
import { DriveService } from '../drive.service';
import { ReaderService } from '../reader.service';
import { ConfigService } from '../config.service';
import { GaService, ACTIONS, CATEGORIES } from '../ga.service';
import { MatSnackBar } from '@angular/material/snack-bar';
import { LiveAnnouncer } from '@angular/cdk/a11y';
import { Observable, Subscription } from 'rxjs';

@Component({
  selector: 'app-checked-out-item',
  templateUrl: './checked-out-item.component.html',
  styleUrls: ['./checked-out-item.component.scss']
})
export class CheckedOutItemComponent implements OnInit {
  mode: string;
  parentLibrary: string;
  parentBibId: string;
  parentItemId: string;
  isCheckedOutItemLoading: boolean;
  checkedOutItem: Item;
  due: Date;
  isBusy: boolean;
  busyAction: string;
  accessibleUserDialogRef: any;
  config: Config;
  customization: Customization;
  usersLibrary:string;
  isAccessibleUser: boolean;
  shouldHide: boolean;
  expirationCheckDelayMS = 45000; //wait extra x millsecs after item is auto returned since Google API is not as in sync
  private readClickedEventSubscription: Subscription;
  private refreshEventSubscription: Subscription;
  private timeOut: any;
  @Input() parent: string;
  @Input() library: string;
  @Input() lang: Language;
  @Input() readClickedEvent: Observable<void>;
  @Input() refreshEvent: Observable<void>;
  @Output() userHasItemCheckedOut = new EventEmitter<boolean>();
  @Output() refreshParent = new EventEmitter<string>();

  constructor(
    private driveService: DriveService,
    private route: ActivatedRoute,
    private router: Router,
    private gaService: GaService,
    private snackBar: MatSnackBar,
    private liveAnnouncer: LiveAnnouncer,
    private configService: ConfigService,
    private readerService: ReaderService
  ) {
  }

  ngOnInit(): void {
    this.configService.getConfig().subscribe(res => {
      this.config = res; 
      this.route.paramMap.subscribe(paramMap => {
        this.mode = this.route.root.firstChild.snapshot.data.appPath ?? null;
        if (this.mode == 'my') this.mode = this.route.root.firstChild.snapshot.data.mode ?? null;             
        this.parentItemId = paramMap.get('itemId') ?? null;
        this.parentBibId = paramMap.get('bibId') ?? null;
        this.parentLibrary = paramMap.get('library') ?? this.config.defaultLibrary;
      });
    });
    if (this.readClickedEvent) this.readClickedEventSubscription = this.readClickedEvent.subscribe(() => this.read(this.checkedOutItem.id));
    if (this.refreshEvent) this.refreshEventSubscription = this.refreshEvent.subscribe(() => this._getUserCheckedOutItem(true));
    this.configService.getCustomization().subscribe(res => {
      this.customization = res;
      this._getUserCheckedOutItem();
    })
    //console.log(this.lang);
  }

  ngOnDestroy() {
    this.readClickedEventSubscription?.unsubscribe();
    this.refreshEventSubscription?.unsubscribe();
    if (this.timeOut) clearTimeout(this.timeOut);
  }

  private _getUserCheckedOutItem(forceRefresh: boolean = false) {
    this.isCheckedOutItemLoading = true;
    if (forceRefresh) this.checkedOutItem = null;
    this.driveService.getUserCheckedOutItem(forceRefresh).subscribe(res => {      
      if (res) {        
        this.isAccessibleUser = res['isAccessibleUser'];
        this.usersLibrary = res['usersLibrary'];
        if (res['item']) {
          this.due = new Date(res['item'].due);          
          if (this.due > new Date()) {            
            this.checkedOutItem = res['item'];
            if (!this.library) this.library = this.checkedOutItem.library
            this._setExpirationRecheck();
          }
        }
      }
      //The !! (double bang) logical operators return a value’s truthy value.
      this.userHasItemCheckedOut.emit(!!this.checkedOutItem);
      //check customiztion settings to see if should display
      if (this.parent == 'home' || this.parent == 'item') {
        if (this.customization.libraries[this.library][this.parent].showCurrentCheckoutSnippet == 1) {          
          this.shouldHide = !this.checkedOutItem ? true : false;
        }
      }
      this.isCheckedOutItemLoading = false;
      this.isBusy = false;
      
      //auto focus after borrow
      if (forceRefresh && this.checkedOutItem) { 
        setTimeout(() => {          
          let readButton = document.getElementById('main-read-button');
          if (readButton) readButton.focus();
        }, 500);
      }
    }, error => {
      console.error(error);
      this.gaService.logError('home-compo: _getUserCheckedOutItem() error', false);
    });
  }

  private _setExpirationRecheck() {
    if (this.checkedOutItem.due) {
      const now:any = new Date();
      const due:any = new Date(this.checkedOutItem.due);
      if (due > now) {
        let diffTime = Math.abs(due - now); //millisecs         
        if (diffTime) {
          diffTime += this.expirationCheckDelayMS; //wait extra x secs since Google API is not as in sync
          this.timeOut = setTimeout(()=>{
            this._getUserCheckedOutItem(true);
            this.refreshParent.emit('return');
            this.readerService.closeWindowRef();
          }, diffTime);
        }
      }
    }
  }

  return(id: string) {
    //console.log(`returning: ${id}`);
    this.liveAnnouncer.announce('returning an item');
    this.busyAction = 'return';
    this.isBusy = true;
    this.driveService.returnItem(id).subscribe(res => {
      //console.log(res);
      if (res.returnSuccess) {
        this.checkedOutItem = null;
        this._getUserCheckedOutItem(true);
        this.refreshParent.emit('return');
        this.readerService.closeWindowRef();
      } else {
        console.error(res);
        this.isBusy = false;
        this.gaService.logError('home-compo: return() error', false);
        this.snackBar.open(this.lang.libraries[this.library].error.return.unknownError, 'Dismiss', {
          duration: 3000,
        });
      }
    }, (error) => {
      console.error(error);
      this.isBusy = false;
      this.gaService.logError('home-compo: return() error', true);
      this.snackBar.open(this.lang.libraries[this.library].error.return.unknownError, 'Dismiss', {
        duration: 3000,
      });
    });
  }

  read(id: string) {
    //console.log(`reading: ${id}`);
    this.gaService.logEvent(ACTIONS.read, CATEGORIES.home, id);
    if (this.config.useEmbedReader) {
      this.router.navigate(['/reader'])
    } else {
      this.readerService.openReaderDirectly(this.checkedOutItem);
    }
  }

  download(id: string) {
   if (this.isAccessibleUser) {
      this.liveAnnouncer.announce('downloading an accessible version of the item');
      this.gaService.logEvent(ACTIONS.download, CATEGORIES.home, id);
      window.open(this.checkedOutItem.downloadLink);
   }
  }

  hasReaderOpenedDirectly(): boolean {
    return this.readerService.hasWindowRef();
  }

}
