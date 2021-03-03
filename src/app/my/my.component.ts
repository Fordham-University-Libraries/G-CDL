import { Component, OnInit } from '@angular/core';
import { Title } from '@angular/platform-browser';
import { ActivatedRoute } from '@angular/router';
import { Config } from '../models/config.model';
import { Language } from '../models/language.model';
import { AuthenticationService } from '../auth.service';
import { GaService, ACTIONS, CATEGORIES } from '../ga.service';
import { User } from '../models/user.model';
import { ConfigService } from '../config.service';

@Component({
  selector: 'app-my',
  templateUrl: './my.component.html',
  styleUrls: ['./my.component.scss']
})
export class MyComponent implements OnInit {

  lang: Language;
  config: Config;
  pageTitle: string;
  mode: string;
  isAllItemsLoading: boolean;
  userHasItemCheckedOut: boolean;
  isCheckedOutItemLoading: boolean;
  isBusy: boolean;
  busyAction: string;
  isLoadingUser: boolean = true;
  user: User;
  justReturned: boolean;
  hasMultiLibraries: boolean;

  constructor(
    private route: ActivatedRoute,
    private titleService: Title,
    private authService: AuthenticationService,
    private gaService: GaService,
    private configService: ConfigService
  ) { }

  ngOnInit(): void {
    this.configService.getLang().subscribe(res => {this.lang = res;});
    this.configService.getConfig().subscribe(res => {
      this.config = res;
      if (Object.keys(this.config.libraries).length > 1) this.hasMultiLibraries = true;
          
      this.route.paramMap.subscribe(paramMap => {
        this.mode = this.route.snapshot.data.mode;
        //console.log(this.mode);
        this.pageTitle = `My Account: ${this.config.appName}`;
        this.titleService.setTitle(this.pageTitle);
        this.gaService.logPageView(this.pageTitle, location.pathname);
        
        this.authService.getUser().subscribe(res => { 
          this.user = res;
          this.isLoadingUser = false;
        });
      });
    });
  }

  onUserHasItemCheckedOutCheck(event: boolean) {    
    this.userHasItemCheckedOut = event;
  }

  onRefreshParent(event: any) {
    //console.log(`onRefreshParent(${event})`);
    if (event == 'return') this.justReturned = true;    
  }
}
