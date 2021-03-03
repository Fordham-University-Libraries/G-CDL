import { Component, OnInit } from '@angular/core';
import { DatePipe } from '@angular/common';
import { Title } from '@angular/platform-browser';
import { ActivatedRoute } from '@angular/router';
import { User } from '../models/user.model';
import { Item } from '../models/item.model';
import { Config } from '../models/config.model';
import { Language } from '../models/language.model';
//import { Customization } from '../models/customization.model';
import { DriveService } from '../drive.service';
import { AuthenticationService } from '../auth.service';
import { ConfigService } from '../config.service';
import { GaService, ACTIONS, CATEGORIES } from '../ga.service';
import { HostListener } from '@angular/core';
import { Observable, Subject } from 'rxjs';
import { MatSnackBar } from '@angular/material/snack-bar';

@Component({
  selector: 'app-reader',
  templateUrl: './reader.component.html',
  styleUrls: ['./reader.component.scss'],
  providers: [DatePipe]
})
export class ReaderComponent implements OnInit {
  mode: number; //for debuggin'
  pageTitle: string;
  user: User;
  item: Item;
  isLoadingUser: boolean = true;
  checkedOutItem: Item;
  isCheckedOutItemLoading: boolean;
  isFullScreen: boolean;
  due: any;
  timeOut: any;
  hasExpired: boolean;
  thirdPartyCookiesSupported: boolean = true;
  showLoginBanner: boolean = true;
  popRef: any;
  config: Config;
  lang: Language;
  readerLang: any;

  constructor(
    private route: ActivatedRoute,
    private datePipe: DatePipe,
    private titleService: Title,
    private driveService: DriveService,
    private authService: AuthenticationService,
    private gaService: GaService,
    private ConfigService: ConfigService,
    private snackBar: MatSnackBar
  ) { }

  ngOnInit(): void {
    this.mode = this.route.snapshot.data.mode;
    this.ConfigService.getConfig().subscribe(res => { 
      this.config = res;
      this.route.paramMap.subscribe(paramMap => {
        this.pageTitle = `Reader: ${this.config.appName}`;
        this.titleService.setTitle(this.pageTitle);
        this.gaService.logPageView(this.pageTitle, location.pathname);
        this.authService.getUser().subscribe(res => {
          this.user = res;
          this.isLoadingUser = false;
          this._getUserCheckedOutItem();  
          //console.log(res);
        });
        this.check3rdPartyCookies().subscribe(res => this.thirdPartyCookiesSupported = res);
      });
    });
  }

  ngOnDestroy():void {
    if (this.timeOut) clearTimeout(this.timeOut);
  }

  private _getUserCheckedOutItem(forceRefresh: boolean = false) {
    this.isCheckedOutItemLoading = true;
    this.driveService.getUserCheckedOutItem(forceRefresh).subscribe(res => {
      if (res?.item) {
        this.checkedOutItem = res.item;
        //console.log(this.checkedOutItem);
        this.due = new Date(this.checkedOutItem.due);
        this._setExpiration();
      }
      this.isCheckedOutItemLoading = false;
      this.ConfigService.getLang().subscribe(langRes => {
        this.lang = langRes;
        this.readerLang = this.checkedOutItem ? this.lang.libraries[this.checkedOutItem.library].reader : this.lang.libraries[this.user.homeLibrary].reader;        
        if (this.checkedOutItem) {
          this.readerLang.readerHead = this.readerLang.readerHead.replace('{{$title}}', this.checkedOutItem.title);
          this.readerLang.dueBack = this.readerLang.dueBack.replace('{{$due}}', this.datePipe.transform(this.checkedOutItem.due, 'medium'));             
        }
      });
    })
  }

  private _setExpiration() {
    if (this.due) {
      const now:any = new Date();
      const diffTime= Math.abs(this.due - now); //millisecs    
      //const diffTime = 30000;
      this.timeOut = setTimeout(()=>{
        this.hasExpired = true;
        this.isFullScreen = false;
        //console.log('expired!');
      }, diffTime);
      //console.log(`expiring in ${diffTime} ms`);
    }
  }

  fullScreen() {
    this.isFullScreen = !this.isFullScreen;
  }

  @HostListener('document:keydown.escape', ['$event']) onKeydownHandler(event: KeyboardEvent) {
    if (event.code == "Escape" && this.isFullScreen) this.isFullScreen = false;
  }

  closeLoginBanner() {
    this.showLoginBanner = false;
  }

  check3rdPartyCookies(): Observable<boolean> {
    let subject = new Subject<boolean>();
    var receiveMessage = function (evt) {
      if (evt.data === 'MM:3PCunsupported') {
        //console.log('third party cookies are NOT supported');
        subject.next(false);
      } else if (evt.data === 'MM:3PCsupported') {
        //console.log('third party cookies are supported');
        subject.next(true);
      }
    };
    window.addEventListener("message", receiveMessage, false);
    return subject;
  }

  download() {
    if (this.user.isAccessibleUser) {
      this.gaService.logEvent(ACTIONS.download, CATEGORIES.reader, this.checkedOutItem.id);
      window.open(this.checkedOutItem.downloadLink);
    }
  }

  return() {
    //console.log(`returning: ${id}`);
    this.isCheckedOutItemLoading = true;
    this.driveService.returnItem(this.checkedOutItem.id).subscribe(res => {
      //console.log(res);
      if (res.returnSuccess) {
        this.checkedOutItem = null;
        this.isCheckedOutItemLoading = false;
        this.snackBar.open('The item has been returned', 'Dismiss', {
          duration: 3000,
        });
      } else {
        console.error(res);
        this.isCheckedOutItemLoading = false;
        this.gaService.logError('home-compo: return() error', false);
        this.snackBar.open(this.readerLang.error.return.unknownError, 'Dismiss', {
          duration: 3000,
        });
      }
    }, (error) => {
      console.error(error);
      this.isCheckedOutItemLoading = false;
      this.gaService.logError('home-compo: return() error', true);
      this.snackBar.open(this.readerLang.error.return.unknownError, 'Dismiss', {
        duration: 3000,
      });
    });
  }

  pop() {
    if(this.popRef) this.popRef.self.close();
    this.gaService.logEvent(ACTIONS.openNewWindow, CATEGORIES.reader, this.checkedOutItem.id);
    const readerUrl = `https://drive.google.com/a/${this.config.gSuitesDomain}/file/d/` + (this.user.isAccessibleUser ? this.checkedOutItem.accessibleFileId : this.checkedOutItem.id) + '/view';
    //console.log(readerUrl);
    this.popRef = window.open(readerUrl,'Book Reader',`directories=no,titlebar=no,toolbar=no,location=no,status=no,menubar=no,scrollbars=no,resizable=no,width=${screen.width},height=${screen.height}`);
    const now:any = new Date();
    const diffTime= Math.abs(this.due - now); //millisecs 
    //console.log('will auto close in ' + diffTime);
    setTimeout(() => {
      //Wow so much cleverness, very highly techical, many CDL compliants, such a great idea!
      if(this.popRef) this.popRef.self.close();      
    }, diffTime);
  }

  login() {
    this.authService.login();
  }

}
