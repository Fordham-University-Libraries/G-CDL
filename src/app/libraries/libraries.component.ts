import { Component, OnInit } from '@angular/core';
import { Title } from '@angular/platform-browser';
import { GaService, ACTIONS, CATEGORIES } from '../ga.service';
import { ConfigService } from '../config.service';

@Component({
  selector: 'app-libraries',
  templateUrl: './libraries.component.html',
  styleUrls: ['./libraries.component.scss']
})
export class LibrariesComponent implements OnInit {
  config: any;
  libraries = [];

  constructor(
    private titleService: Title,
    private gaService: GaService,
    private configService: ConfigService
  ) { }

  ngOnInit(): void {
    this.titleService.setTitle('Libraries Selector');
    this.configService.getConfig().subscribe(res => {
      this.config = res; 
      for (const [key, value] of Object.entries(res.libraries)) {
        value['key'] = key;
        this.libraries.push(value);
      }      
    });
  }

}
