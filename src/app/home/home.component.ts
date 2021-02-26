import { Component, OnInit } from '@angular/core';
import { Title } from '@angular/platform-browser';
import { ActivatedRoute, Router } from '@angular/router';
import { LiveAnnouncer } from '@angular/cdk/a11y';
import { MatSnackBar } from '@angular/material/snack-bar';
import { MatDialog, MatDialogRef } from '@angular/material/dialog';
import { User } from '../models/user.model';
import { Item } from '../models/item.model';
import { TermOfServiceComponent } from '../term-of-service/term-of-service.component';
import { DriveService } from '../drive.service';
import { AuthenticationService } from '../auth.service';
import { GaService, ACTIONS, CATEGORIES } from '../ga.service';
import { environment } from 'src/environments/environment';
import { ConfigService } from '../config.service';
import { Subject } from 'rxjs';

@Component({
  selector: 'app-home',
  templateUrl: './home.component.html',
  styleUrls: ['./home.component.scss']
})
export class HomeComponent implements OnInit {

  pageTitle: string;
  items: {bibId: string, items: Item[], totalCopies?: number}[] = [];
  isAllItemsLoading: boolean;
  itemsOriginalOrders: any[] = [];
  isLoadingMore: boolean;
  nextPageToken: string;
  userHasItemCheckedOut: boolean = null;
  readClickedSubject: Subject<void> = new Subject<void>();
  checkedOutitemRefreshSubject: Subject<void> = new Subject<void>();
  isBusy: boolean;
  busyAction: string;
  user: User;
  library: string;
  accessibleUserDialogRef: MatDialogRef<TermOfServiceComponent, boolean>; //return bool on close
  error: string;

  searchTerm: string;
  searchField: string = 'title';
  titleFilter: string;
  authorFilter: string;
  realSearchTerm: string;
  isSearchDataCached: boolean;
  sort: string = 'default';
  lang: any;
  config: any;
  customization: any;

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private snackBar: MatSnackBar,
    private dialog: MatDialog,
    private liveAnnouncer: LiveAnnouncer,
    private driveService: DriveService,
    private authService: AuthenticationService,
    private titleService: Title,
    private gaService: GaService,
    private configService: ConfigService
  ) { 
  }

  ngOnInit(): void {
    this.configService.getCustomization().subscribe(res => { this.customization = res; });
    this.configService.getLang().subscribe(langRes => {      
      this.lang = langRes;
      this.configService.getConfig().subscribe(cRes => {        
        this.config = cRes;
        console.log(this.config);           
        this.authService.getUser().subscribe(uRes => {
          this.user = uRes;
          this.route.paramMap.subscribe(paramMap => {
            if (paramMap.get('library') && !this.config.libraries[paramMap.get('library')]) {
              this.router.navigate(['/error-no-library'], {skipLocationChange: true});
              return;
            } else if (paramMap.get('library')) {
              this.library = paramMap.get('library');
            } else if (this.user.homeLibrary != this.config.defaultLibrary) {
              this.router.navigate(['/library', this.user.homeLibrary]);
              return;
            } else {
              this.library = this.config.defaultLibrary;
            }
            this._getAllItems();
            this.pageTitle = `Home : ${this.config.appName}`;
            this.titleService.setTitle(this.pageTitle);
            this.gaService.logPageView(this.pageTitle, location.pathname);
          });
        });
      });
    });
  }

  private _getAllItems(forceRefresh: boolean = false) {
    this.isAllItemsLoading = true;
    this.items.length = 0;
    this.driveService.getAllItems(forceRefresh, this.library).subscribe(res => {
      console.log(res);
      
      res.nextPageToken ? this.nextPageToken = res.nextPageToken : this.nextPageToken = null;
      if (res.items?.length) {
        this._processItems(res.items, false);
      } else {
        this.isAllItemsLoading = false;
        this.error = this.lang[this.library].error.getItemsHome.noItems;
      }
    }, (error) => {      
      console.error(error);
      this.isAllItemsLoading = false;      
      this.error = this.lang[this.library].error.getItemsHome.snackBar;
      this.gaService.logError('home-compo: error getting all items', true);
      this.snackBar.open(this.lang[this.library].error.getItemsHome.snackBar, 'Dismiss', {
        duration: 9000,
      });
    });
  }

  private _processItems(items: any, isLoadMore: boolean) {
    //put multilple copies in same parent
    let processedItems = [];
    if (isLoadMore) {
      processedItems = this.items;
    }

    //since we load 100 items at a time, also get total copies count from api so we know the definite count
    let titlesWithMulti: any;
    this.driveService.getItemsWithMultipleCopies(this.library).subscribe(res => {
      titlesWithMulti = res['results'];      
      
      items.forEach(item => {
        let index = processedItems.findIndex(i => {
          return i.bibId == item['bibId'];
        });
        item['barcode'] = item.itemId;
        if (index > -1) {
          processedItems[index].items.push(item);
          if (titlesWithMulti[item.bibId]) {
            processedItems[index]['totalCopies'] = titlesWithMulti[item.bibId];
          }
        } else {
          processedItems.push({ bibId: item['bibId'], items: [item] });
        }
      });

      //sort multi parts/copy
      this.isAllItemsLoading = false;      
      this.items = processedItems;
      this.items.forEach(i => {
        i.items.sort((a: any,b: any) => {          
          if (a.part && b.part) {
            return a.part - b.part;
          } else {
            return a;
          }
        });
      });
      //sort bib
      if (this.sort == 'title') {
          this.items.sort(this.compareTitleAsc);
      }
      this.isLoadingMore = false;
      this.isBusy = false;
      //console.log(this.items);
    });
  }

  loadMore(nextPageToken: string) {
    this.isLoadingMore = true;
    this.driveService.getAllItemsNextPage(nextPageToken).subscribe(res => {
      res.nextPageToken ? this.nextPageToken = res.nextPageToken : this.nextPageToken = null;
      let items: any;
      items = res.items;
      this._processItems(items, true);
    })
  
  }

  search() {
    //not all items is loaded, can't just filter
    if (this.nextPageToken) {
      this.isAllItemsLoading = true;
      //console.log('real search');
      this.realSearchTerm = this.searchTerm;
      this.nextPageToken = null;
      this.driveService.search(this.searchField, this.searchTerm, this.library).subscribe(res => {
        this.isSearchDataCached = res.isCachedData;
        this._processItems(res.results, false);
      })
    } else {
      //console.log(`search ${this.searchTerm} by ${this.searchField}`);
      this.isAllItemsLoading = true;
      if (!this.searchTerm) {
        this.titleFilter = null;
        this.authorFilter = null;
      } else if (this.searchField == 'title') {
        this.titleFilter = this.searchTerm;
        this.authorFilter = null;
      } else {
        this.authorFilter = this.searchTerm;
        this.titleFilter = null;
      }
      setTimeout(()=>{
        this.isAllItemsLoading = false;
      }, Math.floor(Math.random() * 1000) + 100); //LOL
    }
  }

  clearFilter() {
    if (this.realSearchTerm) {
      this.titleFilter = null;
      this.authorFilter = null;
      this.searchTerm = null;
      this.realSearchTerm = null;
      this._getAllItems(true);
    } else {
      this.searchTerm = null;
      this.search();
    }
  }

  onSortChange(event: any) {
    //console.log(event);
    if (event == 'title') {
      this.sort = 'title';
      this.liveAnnouncer.announce('sorting by title ascending')
      this.items.sort(this.compareTitleAsc);
    } else if (event == 'default') {
      this.sort = 'default';
      this.liveAnnouncer.announce('sorting by recently added item first')
      this.items.sort(this.compareCreatedTimeDsc);
    }
  }

  compareTitleAsc(a: {bibId: string, items: Item[], totalCopies?: number}, b: {bibId: string, items: Item[], totalCopies?: number}) {
    if (a.items[0].title.toLowerCase() > b.items[0].title.toLowerCase()) return 1;
    if (a.items[0].title.toLowerCase() < b.items[0].title.toLowerCase()) return -1;
    return 0;
  }

  compareCreatedTimeDsc(a: {bibId: string, items: Item[], totalCopies?: number}, b: {bibId: string, items: Item[], totalCopies?: number}) {
    if (a.items[0].createdTime < b.items[0].createdTime) return 1;
    if (a.items[0].createdTime > b.items[0].createdTime) return -1;
    return 0;
  }

  borrow(id: string, title?: string, tosAccepted: boolean = false) {
    //console.log(`borrowing: ${id} / ${title}`);
    if (this.user?.isAccessibleUser && !tosAccepted) {
      this.accessibleUserDialogRef = this.dialog.open(TermOfServiceComponent, {data: {title: title},panelClass: 'accessible-user-dialog'});
      this.accessibleUserDialogRef.afterClosed().subscribe(accept => {
        if (accept) this.borrow(id, title, true);
      });
      return;
    }
    this.gaService.logEvent(ACTIONS.borrow, CATEGORIES.home, id);

    this.busyAction = 'borrow';
    this.isBusy = true;
    this.driveService.borrowItem(id).subscribe(res => {
      //console.log(res);
      if (res?.borrowSuccess) {
        this.checkedOutitemRefreshSubject.next();
        this._getAllItems(true);
      } else {
        console.error(res);
        this.isBusy = false;
        this.gaService.logError('home-compo: borrow() error', false);
        this.snackBar.open(res.error, 'Dismiss', {
          duration: 8000,
        });
      }
    }, (error) => {
      console.error(error);
      this.isBusy = false;
      this.gaService.logError('home-compo: borrow() error', true);
      this.snackBar.open(this.lang[this.library].error.borrow.unknownError, 'Dismiss', {
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
    this._getAllItems(true);
  }
}
