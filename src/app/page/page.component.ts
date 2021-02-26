import { Component, OnInit } from '@angular/core';
import { Title } from '@angular/platform-browser';
import { ActivatedRoute } from '@angular/router';
import { ConfigService } from '../config.service';
import { AuthenticationService } from '../auth.service';

@Component({
  selector: 'app-page',
  templateUrl: './page.component.html',
  styleUrls: ['./page.component.scss']
})
export class PageComponent implements OnInit {
  mode: string;
  lang: any;
  config: any;
  library: string;

  constructor(
    private route: ActivatedRoute,
    private titleService: Title,
    private configService: ConfigService,
    private authService: AuthenticationService
  ) { }

  ngOnInit(): void {
    this.mode = this.route.snapshot.data.mode;
    if (this.mode == 'loggedOut') {
      this.authService.getUser().subscribe(res => {
        if(res && res.isAuthenticated) { 
          this.authService.logout();
          return;
        }
      });
    }

    this.configService.getLang().subscribe(res => {
      this.lang = res;      
      this.route.paramMap.subscribe(paramMap => {
        if (paramMap.get('library')) {
          this.library = paramMap.get('library');
        } else {
          this.configService.getConfig().subscribe(res => {
            this.config = res;
            this.titleService.setTitle(`${this.config.appName}`);
            this.library = this.config.defaultLibrary;
          })
        }
      })
    });
  }

}
