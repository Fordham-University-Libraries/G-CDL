import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, Router, NavigationStart, NavigationEnd, NavigationError } from '@angular/router';
import { MatDialog, MatDialogRef } from '@angular/material/dialog';
import { AuthenticationService } from './auth.service';
import { User } from './models/user.model';
import { Config } from './models/config.model';
import { Language } from './models/language.model';
import { Customization } from './models/customization.model';
import { ConfigService } from './config.service';
import { ReaderService } from './reader.service';
import { GaService } from './ga.service';
import { environment } from 'src/environments/environment';
import { IdleDialogComponent } from './idle-dialog/idle-dialog.component';
import { HostListener } from '@angular/core';

@Component({
  selector: 'app-root',
  templateUrl: './app.component.html',
  styleUrls: ['./app.component.scss'],
})
export class AppComponent implements OnInit {
  user: User;
  hasMultiLibraries: boolean;
  library: string;
  isDefaultLibraryRoute: boolean;
  isStaff: boolean;
  isAdmin: boolean;
  appPath: string;
  mode: string;
  timeOut: any;
  idleDialogRef: MatDialogRef<any>;
  skipLinkPath: string;
  lang: Language;
  config: Config;
  customization: Customization;
  unauthedMode: boolean;
  appPathToCheckIdle: string[] = ['home', 'item', 'my'];

  //gets called everytime before unload,  to try to close an open gDrive Reader
  @HostListener('window:beforeunload', ['$event'])
  onBeforeUnload($event: Event) {
    //close GDrive reader, if user close app, can't really warn user since we can't custommize confirm dialog's message anymore    
    if (this.readerService.hasWindowRef) {
      this.readerService.closeWindowRef();
    }
  }

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private dialog: MatDialog,
    private authService: AuthenticationService,
    private configService: ConfigService,
    private gaService: GaService,
    private readerService: ReaderService
  ) {
  }

  ngOnInit() {
    this.gaService.init();
    this.router.events.subscribe(event => {
      if (event instanceof NavigationStart) {
        //console.log('nav start');        
      } else if (event instanceof NavigationEnd) {
        //console.log('nav end');
        if (this.router.onSameUrlNavigation == 'reload') this.router.onSameUrlNavigation = 'ignore';
        this._update();
      } else if (event instanceof NavigationError) {
        console.error(event.error);
      }
    });

    this.configService.getLang().subscribe(res => { this.lang = res; });
    this.configService.onForceRefresh.subscribe(shouldRefresh => { if (shouldRefresh) this._update(true); });

    this.configService.getConfig().subscribe(res => {
      this.config = res;
      if (Object.keys(this.config.libraries).length > 1) this.hasMultiLibraries = true;
      this.authService.getUser().subscribe(res => {
        this.user = res;
        this.isStaff = this.user.isStaffOfLibraries?.includes(this.library);
        this.isAdmin = this.user.isAdminOfLibraries?.includes(this.library);
      }, error => {
        //NOT logged in, for a page like Logged out
        console.log('app compo: not auth');
        //console.log(error);
        this.unauthedMode = true;
        this.library = this.config.defaultLibrary;
      });

    });
  }

  ngOnDestroy() {    
    this.configService.onForceRefresh.unsubscribe();
    if (this.timeOut) clearTimeout(this.timeOut);
  }

  private _update(forceRefresh = false) {
    this.appPath = this.route.root.firstChild.snapshot.data.appPath;
    this.mode = this.route.root.firstChild.snapshot.data.mode ?? null;
    this.updateSkipLink();
    if (this.timeOut) clearTimeout(this.timeOut);
    if (this.appPathToCheckIdle.includes(this.appPath)) this._checkIdle();
    if (!this.config) {
      this.configService.getConfig(forceRefresh).subscribe(res => {
        this.config = res;
        this._setLibrary();
      });
    } else {
      this._setLibrary();
    }
    this.loadCSS(`${environment.apiBase}/?action=get_custom_css`, forceRefresh);
    if (!this.customization || forceRefresh) {
      this.configService.getCustomization(forceRefresh).subscribe(res => {
        this.customization = res;        
        
        if (this.customization.libraries) {
          for (const [key, value] of Object.entries(this.customization.libraries)) {
            if (this.customization.libraries[key].header.first?.logo && !this.customization.libraries[key].header.first.logo.startsWith('http') && !this.customization.libraries[key].header.first.logo.startsWith('//')) {
              if (!this.customization.libraries[key].header.first?.logo.includes('./assets/')) this.customization.libraries[key].header.first.logo = './assets/' + this.customization.libraries[key].header.first.logo;
            }
            if (this.customization.libraries[key].header.second?.logo && !this.customization.libraries[key].header.second.logo.startsWith('http') && !this.customization.libraries[key].header.first.logo.startsWith('//')) {
              if (!this.customization.libraries[key].header.first?.logo.includes('./assets/')) this.customization.libraries[key].header.second.logo = './assets/' + this.customization.libraries[key].header.second.logo;
            }
          }
        }
      });
    }
  }

  private _setLibrary() {
    if (!Object.keys(this.config.libraries).length) {
      this.router.navigate(['/api-error/no-lib'], { skipLocationChange: true });
      return;
    }

    let previousRouteLibrary = this.library;
    //check if it's the /library/:library route
    this.isDefaultLibraryRoute = this.route.root.firstChild.snapshot.data.isDefaultLibraryRoute;
    if (!this.isDefaultLibraryRoute) {
      this.library = this.route.root.firstChild.snapshot.params?.library;
    }

    if (!this.library) {
      this.library = this.config.defaultLibrary;
    }

    if (!this.config.libraries[this.library]) {
      this.router.navigate(['/unknown-library'], { skipLocationChange: true });
      return;
    }

    //set class at body - for custom CSS
    if (previousRouteLibrary != this.library) {
      let bodyClassList = document.body.classList
      if (bodyClassList.length) {
        bodyClassList.remove(bodyClassList.item(0));
      }
      bodyClassList.add(`library-${this.library}`);
    }
  }

  private _checkIdle(seconds: number = 7200) {
    //console.log('start checkIdle for ' + seconds);
    this.timeOut = setTimeout(() => {
      //console.log(`Been idle for ${seconds} seconds...`);
      this.idleDialogRef = this.dialog.open(IdleDialogComponent, { data: { seconds: seconds }, disableClose: true });
      this.idleDialogRef.afterClosed().subscribe(refresh => {
        if (refresh) {
          this.router.routeReuseStrategy.shouldReuseRoute = () => false;
          this.router.onSameUrlNavigation = 'reload';
          this.router.navigate([this.router.url], { relativeTo: this.route, queryParamsHandling: 'preserve' });
        }
      });
    }, seconds * 1000);
  }


  logOut() {
    this.authService.logout();
  }

  updateSkipLink() {
    if (!window.location.href.endsWith('#main')) {
      this.skipLinkPath = `${window.location.href}#main`;
    } else {
      this.skipLinkPath = window.location.href;
    }
  }

  loadCSS(url: string, forceRefresh = false) {
    let oldLink = document.getElementById('app-custom-css');
    if (!forceRefresh && oldLink) return;
    if (oldLink) oldLink.remove();

    // Create link
    let link = document.createElement('link');
    link.id = 'app-custom-css'
    link.href = url;
    link.rel = 'stylesheet';
    link.type = 'text/css';

    let head = document.getElementsByTagName('head')[0];
    let links = head.getElementsByTagName('link');
    let style = head.getElementsByTagName('style')[0];

    // Check if the same style sheet has been loaded already.
    let isLoaded = false;
    for (var i = 0; i < links.length; i++) {
      var node = links[i];
      if (node.href.indexOf(link.href) > -1) {
        isLoaded = true;
      }
    }
    if (isLoaded) return;
    head.insertBefore(link, style);
  }

  openNewWindow(url: string, location?: string) {
    if (!url && !location) return;
    if (!url) {
      if (location == 'second') {
        if (this.library == this.config.defaultLibrary) {
          this.router.navigate(['/']);
        } else {
          this.router.navigate(['/library', this.library]);
        }
      }
    } else {
      window.open(url, '_blank');
    }
  }

  onFloatingButtonClicked() {
    if (this.customization?.global?.floatingButton?.enable && this.customization?.global?.floatingButton?.url) window.open(this.customization.global.floatingButton.url, '_blank');
  }

}
