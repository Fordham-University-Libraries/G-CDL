import { Component, OnInit } from '@angular/core';
import { Title } from '@angular/platform-browser';
import { ActivatedRoute } from '@angular/router';
import { ConfigService } from '../config.service';

@Component({
  selector: 'app-error',
  templateUrl: './error.component.html',
  styleUrls: ['./error.component.scss']
})
export class ErrorComponent implements OnInit {
  mode: string;
  reason: string;
  validReasons = ['no-lib'];
  lang: any;
  config: any;
  library: string;

  constructor(
    private route: ActivatedRoute,
    private titleService: Title,
    private configService: ConfigService
  ) { }

  ngOnInit(): void {
    this.mode = this.route.snapshot.data.mode;
    this.configService.getLang().subscribe(res => {
      this.lang = res;      
      this.route.paramMap.subscribe(paramMap => {
        this.reason = paramMap.get('reason') ?? null;
        if (this.reason && !this.validReasons.includes(this.reason)) this.reason = null;
        if (paramMap.get('library')) {
          this.library = paramMap.get('library');
        } else {
          this.configService.getConfig().subscribe(res => {
            this.config = res;
            this.titleService.setTitle(`Error: ${this.config.appName}`);
            this.library = this.config.defaultLibrary;
          });
        }
      });
    });
  }
}
