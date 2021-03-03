import { Component, OnInit, Input } from '@angular/core';
import { Title } from '@angular/platform-browser';
import { ActivatedRoute, Router } from '@angular/router';
import { AuthenticationService } from '../auth.service';
import { Config } from '../models/config.model';
import { Language } from '../models/language.model';
import { ConfigService } from '../config.service';
import { PageService } from '../page.service';


@Component({
  selector: 'app-about',
  templateUrl: `./about.component.html`,
  styleUrls: ['./about.component.scss']
})
export class AboutComponent implements OnInit {
  @Input() embed: boolean = false;
  @Input() isAccessibleUser: boolean;
  @Input() library: string;
  isLoading: boolean = false;
  html: string;
  config: Config;
  lang: Language;
  thisLibraryLang: any;

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private titleService: Title,
    private authService: AuthenticationService,
    private configService: ConfigService,
    private pageService: PageService
  ) { }

  ngOnInit(): void {
    this.configService.getLang().subscribe(langRes => {      
      if (!this.embed) {
        this.isLoading = true;
        this.configService.getConfig().subscribe(res => {
          this.config = res;
          this.titleService.setTitle(`About: ${this.config.appName}`);
          this.route.paramMap.subscribe(paramMap => {
            this.authService.getUser().subscribe(res => {
              this.library = paramMap.get('library') ?? res.homeLibrary;
              this.lang = langRes;
              this.thisLibraryLang = this.lang.libraries[this.library];
              this.isAccessibleUser = res.isAccessibleUser;
              this.isLoading = false;
              this.getAboutPage();
            });
          });
        });
      } else {
        this.getAboutPage();
      }
    });
  }

  getAboutPage() {
    this.pageService.getAbout(this.library).subscribe(res => {
      this.html = res;
    });
  }

}
