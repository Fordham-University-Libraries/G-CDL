import { Component, OnInit, Input, Output, EventEmitter } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { DriveService } from '../drive.service';
import { GaService, ACTIONS, CATEGORIES } from '../ga.service';
import { MatDialog } from '@angular/material/dialog';
import { MatSnackBar } from '@angular/material/snack-bar';
import { LiveAnnouncer } from '@angular/cdk/a11y';
import { ConfigService } from '../config.service';
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
  checkedOutItem: any;
  isBusy: boolean;
  busyAction: string;
  accessibleUserDialogRef: any;
  config: any;
  usersLibrary:string;
  isAccessibleUser: boolean;
  private readClickedEventSubscription: Subscription;
  private refreshEventSubscription: Subscription;
  @Input() library: string;
  @Input() lang: any;
  @Input() readClickedEvent: Observable<void>;
  @Input() refreshEvent: Observable<void>;
  @Output() userHasItemCheckedOut = new EventEmitter<boolean>();
  @Output() refreshParent = new EventEmitter<string>();

  constructor(
    private driveService: DriveService,
    private route: ActivatedRoute,
    private router: Router,
    private gaService: GaService,
    private dialog: MatDialog,
    private snackBar: MatSnackBar,
    private liveAnnouncer: LiveAnnouncer,
    private configService: ConfigService
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
    this._getUserCheckedOutItem();
    console.log(this.lang);
    
  }

  ngOnDestroy() {
    this.readClickedEventSubscription?.unsubscribe();
    this.refreshEventSubscription?.unsubscribe();
  }

  private _getUserCheckedOutItem(forceRefresh: boolean = false) {
    this.isCheckedOutItemLoading = true;
    this.driveService.getUserCheckedOutItem(forceRefresh).subscribe(res => {      
      if (res) {
        this.isAccessibleUser = res['isAccessibleUser'];
        this.usersLibrary = res['usersLibrary'];
        this.checkedOutItem = res['item'];
      }
      this.isCheckedOutItemLoading = false;
      this.isBusy = false;
      //The !! (double bang) logical operators return a value’s truthy value.
      this.userHasItemCheckedOut.emit(!!this.checkedOutItem);
      
      //auto focus after borrow
      if (forceRefresh && this.checkedOutItem) { 
        setTimeout(() => {          
          document.getElementById('main-read-button').focus();
        }, 500);
      }
    }, error => {
      console.error(error);
      this.gaService.logError('home-compo: _getUserCheckedOutItem() error', false);
    });
  }

  return(id: string) {
    //console.log(`returning: ${id}`);
    this.busyAction = 'return';
    this.isBusy = true;
    this.driveService.returnItem(id).subscribe(res => {
      //console.log(res);
      if (res.returnSuccess) {
        this.checkedOutItem = null;
        this._getUserCheckedOutItem(true);
        this.refreshParent.emit('return');
      } else {
        console.error(res);
        this.isBusy = false;
        this.gaService.logError('home-compo: return() error', false);
        this.snackBar.open(this.lang.error.return.unknownError, 'Dismiss', {
          duration: 3000,
        });
      }
    }, (error) => {
      console.error(error);
      this.isBusy = false;
      this.gaService.logError('home-compo: return() error', true);
      this.snackBar.open(this.lang.error.return.unknownError, 'Dismiss', {
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
      window.open(this.checkedOutItem.url);
    }
  }

  download(id: string) {
   if (this.isAccessibleUser) {
      this.gaService.logEvent(ACTIONS.download, CATEGORIES.home, id);
      window.open(this.checkedOutItem.downloadLink);
   }
  }

}
