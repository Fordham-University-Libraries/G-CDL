import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, Router, NavigationStart, NavigationEnd, NavigationError } from '@angular/router';
import { MatDialog, MatDialogRef } from '@angular/material/dialog';
import { AuthenticationService } from './auth.service';
import { User } from './models/user.model';
import { ConfigService } from './config.service';
import { GaService } from './ga.service';
import { environment } from 'src/environments/environment';
import { IdleDialogComponent } from './idle-dialog/idle-dialog.component';

@Component({
  selector: 'app-root',
  templateUrl: './app.component.html',
  styleUrls: ['./app.component.scss']
})
export class AppComponent implements OnInit {
  user: User;
  library: string;
  isUsersDefaultLibrary: boolean;
  isStaff: boolean;
  appPath: string;
  mode: string;
  timeOut: number;
  idleDialogRef: MatDialogRef<any>;
  skipLinkPath: string;
  lang: any;
  config: any;
  customization: any;
  unauthedMode: boolean;

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private dialog: MatDialog,
    private authService: AuthenticationService,
    private configService: ConfigService,
    private gaService: GaService
  ) {
  }

  ngOnInit() {
    this.gaService.init();
    this.router.events.subscribe(event => {
      if (event instanceof NavigationStart) {
        //console.log('nav start');        
      } else if (event instanceof NavigationEnd) {
        console.log('nav end');
        if (this.timeOut) clearTimeout(this.timeOut);
        if (this.router.onSameUrlNavigation == 'reload') {
          console.log('reset router config');
          this.router.onSameUrlNavigation = 'ignore';
        }
        this._init();
      } else if (event instanceof NavigationError) {
        console.error(event.error);
      }
    });
    this.configService.getLang().subscribe(res => { this.lang = res; });
    this.configService.onForceRefresh.subscribe(shouldRefresh => {
      if (shouldRefresh) this._init(true);
    });

    this.authService.getUser().subscribe(res => {      
      this.user = res;
      if (this.library) {
        if (!this.config.libraries[this.library]) this.router.navigate(['/error']);
      } else {
        this.library = this.user.homeLibrary;
      }
      document.body.classList.add(`library-${this.library}`); //for custom CSS
      this.isStaff = this.user.isStaffOfLibraries?.includes(this.library);
    }, error => {
      //NOT logged in, for a page like Logged out
      console.log('app compo: not auth');
      console.log(error);
      
      this.unauthedMode = true;
      if (!this.config) {
        this.configService.getConfig().subscribe(res => {
          this.config = res;
          this.library = res.defaultLibrary;
        });
      } else {
        this.library = this.config.defaultLibrary;
      }
    });
  }

  ngOnDestroy() {
    this.configService.onForceRefresh.unsubscribe();
    if (this.timeOut) clearTimeout(this.timeOut);
  }

  private _init(forceRefresh = false) {
    this.appPath = this.route.root.firstChild.snapshot.data.appPath;
    this.mode = this.route.root.firstChild.snapshot.data.mode ?? null;
    this.updateSkipLink();
    if (this.appPath == 'home' || this.appPath == 'item') this._checkIdle();
    if (!this.config) {
      this.configService.getConfig(forceRefresh).subscribe(res => {
        this.config = res;            
        this._setLibrary();
      });
    } else {
      this._setLibrary();
    }
    if (forceRefresh) this.loadCSS(`${environment.apiBase}/?action=get_custom_css`);
    if (!this.customization || forceRefresh) {
      this.configService.getCustomization(forceRefresh).subscribe(res => { 
        this.customization = res;          
        for (const [key, value] of Object.entries(this.customization)) {
          if (this.customization[key].header.first.logo && !this.customization[key].header.first.logo.startsWith('http') && !this.customization[key].header.first.logo.startsWith('//')) {
            this.customization[key].header.first.logo = 'assets/' + this.customization[key].header.first.logo;
          }
          if (this.customization[key].header.second.logo && !this.customization[key].header.second.logo.startsWith('http') && !this.customization[key].header.first.logo.startsWith('//')) {
            this.customization[key].header.second.logo = 'assets/' + this.customization[key].header.second.logo;
          }
        }
      });
    }
  }

  private _setLibrary() {
    this.isUsersDefaultLibrary = this.route.root.firstChild.snapshot.data.isUsersDefaultLibrary;
    if (!this.isUsersDefaultLibrary) {
      let requestedLibrary = this.route.root.firstChild.snapshot.params?.library; //getFromParam
      //console.log(requestedLibrary);
      if (requestedLibrary && this.config?.libraries[requestedLibrary]) {
        this.library = this.route.root.firstChild.snapshot.params.library;
      }
    }
  }

  private _checkIdle(seconds: number = 7200) {
    console.log('start checkIdle for ' + seconds);
    this.timeOut = setTimeout(() => {
      console.log(`Been idle for ${seconds} seconds...`);
      this.idleDialogRef = this.dialog.open(IdleDialogComponent, {data: {seconds: seconds}, disableClose: true});
      this.idleDialogRef.afterClosed().subscribe(refresh => {     
        if (refresh) {
          this.router.routeReuseStrategy.shouldReuseRoute = () => false;
          this.router.onSameUrlNavigation = 'reload';
          this.router.navigate(['./'], { relativeTo: this.route, queryParamsHandling: 'preserve' });
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

  loadCSS(url) {
    let oldLink = document.getElementById('app-custom-css');
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

  openNewWindow(url: string) {
    if (!url) return;
    window.open(url,'_blank');
  }

}
