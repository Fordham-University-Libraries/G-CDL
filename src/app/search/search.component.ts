import { Component, OnInit } from '@angular/core';
import { Title } from '@angular/platform-browser';
import { ActivatedRoute, Router } from '@angular/router';
import { CatalogService } from '../catalog.service';
import { DriveService } from '../drive.service';
import { ConfigService } from '../config.service';
import { GaService, ACTIONS, CATEGORIES } from '../ga.service';
import { environment } from 'src/environments/environment';

@Component({
  selector: 'app-search',
  templateUrl: './search.component.html',
  styleUrls: ['./search.component.scss']
})
export class SearchComponent implements OnInit {
  config: any;
  pageTitle: string;
  searchField: string;
  searchTerm: string;
  searchLocation: string = 'RESERVE-RH';
  items: any;
  isLoading: boolean;

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private titleService: Title,
    private catalogService: CatalogService,
    private driveService: DriveService,
    private gaService: GaService,
    private configService: ConfigService
  ) { }

  ngOnInit(): void {
    this.configService.getConfig().subscribe(res => {
      this.config = res;
      this.route.paramMap.subscribe(paramMap => {
        this.searchField = paramMap.get('searchField');
        this.searchTerm = paramMap.get('searchTerm');
        this.pageTitle = `Search: ${this.config.appName}`;
        this.titleService.setTitle(this.pageTitle);
        this.gaService.logPageView(this.pageTitle, location.pathname);
        if (this.searchTerm && this.searchField) {
          this.search();
        }
      })
    });
  }

  search() {
    this.isLoading = true;
    // this.catalogService.search(this.searchTerm, this.searchField, this.searchLocation).subscribe(res => {
    //   this.items = res.HitlistTitleInfo;
    //   console.log(this.items);
    //   this.isLoading = false;
    // })
  }

}
