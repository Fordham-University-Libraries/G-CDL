import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { Title } from '@angular/platform-browser';
import { Location } from '@angular/common';
import { MatDialog } from '@angular/material/dialog';
import { CatalogService } from '../catalog.service';
import { DriveService } from '../drive.service';
import { AuthenticationService } from '../auth.service';
import { ConfigService } from '../config.service';
import { GaService, ACTIONS, CATEGORIES } from '../ga.service';
import { SubscriptionLike } from 'rxjs';
import { EbookSearchComponent } from '../ebook-search/ebook-search.component'
import { User } from '../models/user.model';
//import { Item } from '../models/item.model';
import { Config } from '../models/config.model';
import { Language } from '../models/language.model';
import { Customization } from '../models/customization.model';

@Component({
  selector: 'app-catalog-reserves',
  templateUrl: './reserves.component.html',
  styleUrls: ['./reserves.component.scss']
})
export class ReservesComponent implements OnInit {
  pageTitle: string;
  mode: string;
  locationSub: SubscriptionLike;
  browseMode: string;
  reservesDesks: any;
  searchTerm: string;
  courseId: string;
  userPk: number; //ILS stuff
  isLoading: boolean;
  results: any;
  more: boolean;
  library: string;
  isDefaultLibraryRoute: boolean;
  courseIdwithMultipleCourses: any[] = [];
  professorsCourses: any[] = [];
  courseDetailedView: boolean;
  courseDetailedResult: any;
  courseDetailedResultPhysical: any = [];
  courseDetailedResultCdl: any = [];
  isCheckingCdlItems: boolean = true;
  error: string;
  user: User;
  lang: Language;
  config: Config;
  customizations: Customization;
  showRequestButton: boolean;
  //ils location
  locations: any;

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private titleService: Title,
    private location: Location,
    private dialog: MatDialog,
    private catalogService: CatalogService,
    private driveService: DriveService,
    private gaService: GaService,
    private authService: AuthenticationService,
    private configService: ConfigService
  ) {

  }

  ngOnInit(): void {

    this.locationSub = this.location.subscribe(e => {
      //console.log(e);
      //make back button works when back from courseDetailedView page
      if (e.pop) {
        //console.log('its a pop');
        this.courseDetailedView = false;
        delete (this.courseDetailedView);
        this.isLoading = false;
      }
    })

    this.configService.getLang().subscribe(res => {
      this.lang = res;
      this.configService.getCustomization().subscribe(res => {
        this.customizations = res;
        //console.log(this.customizations);
        this.configService.getConfig().subscribe(res => {
          this.config = res;
          this.authService.getUser().subscribe(res => {
            this.user = res;
            this.route.paramMap.subscribe(paramMap => {
              this.pageTitle = `Search Course: ${this.config.appName}`;
              this.titleService.setTitle(this.pageTitle);
              this.gaService.logPageView(this.pageTitle, location.pathname);
              this.mode = this.route.snapshot.data.mode;
              if (paramMap.get('library')) {
                this.library = paramMap.get('library');
              } else if (this.user.homeLibrary != this.config.defaultLibrary) {
                this.router.navigate(['/library', this.user.homeLibrary]);
                return;
              } else {
                this.library = this.config.defaultLibrary;
              }
              this._checkShowRequestButton();
              this.getIlsLocationsDefinition(this.library);              
              if (!this.customizations.libraries[this.library].reserves.enable) {
                this.router.navigate(['/error-disabled'], { skipLocationChange: true });
                return;
              }

              if (this.mode == "browse") {
                this.browseMode = paramMap.get('browseMode') ?? 'courseName';
                this.searchTerm = paramMap.get('searchTerm');
                if (this.browseMode && this.searchTerm) {
                  //console.log('search');
                  this.search();
                  this.courseDetailedView = false;
                } else {
                  //this.error = "Bad Request!";
                }
              } else if (this.mode == "details") {
                this.courseId = paramMap.get('courseId');
                this.getDetailedCourseReserve({ id: this.courseId });
              }
            })
          })
        })
      })
    });
  }

  search() {
    //console.log('search');
    this.isLoading = true;
    //console.log(this.library);
    this.catalogService.searchReservesCourses(this.library, this.browseMode, this.searchTerm).subscribe(res => {
      //console.log(res);
      if (!res.error) {
        this.results = res;        
      } else {
        this.error = res.error;
      }
      this.isLoading = false;
      //console.log(this.results);
    }, error => {
      console.error(error);
      this.error = this.lang.libraries[this.library].error.genericError;
      this.isLoading = false;
    });
  }

  getReserveCourseInfo(course: any) {
    this.isLoading = true;
    //console.log('reserveCompo.getReserveCourseInfo()');
    //console.log(course);
    if (course.items?.length) {
      //can go directly to full course view
      this.getDetailedCourseReserve(course);
    } else {
      //has to make another call to get actual course(s)
      this.catalogService.getReserveCourseInfo(this.library, course).subscribe(res => {
        //console.log('catalogService.getReserveCourseInfo');
        //console.log(res);
        if (!res.error) {
          this.error = null;
          if (res.sections?.length == 1) {
            let course = res.sections[0];
            this.getDetailedCourseReserve(course);
          } else {
            //console.log('theres multiple courses: ' + res.reserveInfo.length);
            this.courseIdwithMultipleCourses[course.courseNumber] = res.sections;
            this.courseIdwithMultipleCourses[course.courseNumber].forEach(item => {
              item['courseNumber'] = course.courseNumber;
            })
            this.isLoading = false;
            //console.log(this.courseIdwithMultipleCourses);          
          }
        } else {
          this.error = res.error;
          this.isLoading = false;
        }
      }, error => {
        console.error(error);
        this.error = this.lang.libraries[this.library].error.genericError;
        this.isLoading = false;
      });
    }
  }

  getReservesCoursesByUser(userPk: number) {
    this.isLoading = true;
    this.catalogService.getReservesCoursesByUser(this.library, userPk).subscribe(res => {
      if (!res.error) {
        this.professorsCourses[res.profPk] = res.courses;
      } else {
        this.error = res.error;
      }
      this.isLoading = false;
      //console.log(this.professorsCourses);
    }, error => {
      console.error(error);
      this.error = this.lang.libraries[this.library].error.genericError;
      this.isLoading = false;
    });
  }

  getDetailedCourseReserve(course: any) {
    this.isLoading = true;
    //console.log('reservesCompo.getDetailedCourseReserve()');
    //console.log(course);
    this.courseDetailedResult = null;
    let bibIds = [];
    this.catalogService.getDetailedCourseReserve(this.library, course).subscribe(res => {
      if (this.isDefaultLibraryRoute) {
        this.location.go(`/search/reserves/course/${course.id}`);
      } else {
        this.location.go(`/library/${this.library}/search/reserves/course/${course.id}`);
      }
      this.courseDetailedResult = res;
      //console.log(this.courseDetailedResult);

      this.courseDetailedView = true;
      var i = 1;
      //check if items on reserve for course is avaialbe as CDL
      if (this.courseDetailedResult.items) {
        this.courseDetailedResultPhysical.length = 0;
        this.courseDetailedResult.items.forEach(item => {
          bibIds.push(item.bibId);
        });
        //console.log(bibIds);
        this.driveService.checkItemInSystemByBibIds(bibIds).subscribe(res => {
          //console.log(res);
          if (!res.error) {
            this.error = null;
            this.courseDetailedResult.items.forEach(item => {
              //console.log(item);
              if (res.results.includes('' + item.bibId)) {
                this.courseDetailedResultCdl.push(item);
              } else {
                this.courseDetailedResultPhysical.push(item);
              }
              if (i == this.courseDetailedResult.items.length) this.isCheckingCdlItems = false;
              i++;
            });
            this.isLoading = false;
            //console.log(this.courseDetailedResultCdl);
            //console.log(this.courseDetailedResultPhysical);
          } else {
            this.error = res.error;
            this.isLoading = false;
          }
        }, error => {
          console.error(error);
          this.error = this.lang.libraries[this.library].error.genericError;
          this.isLoading = false;
        });
      } else {
        this.isCheckingCdlItems = false;
        this.isLoading = false;
      }
    });
    
  }

  backToResult() {
    window.history.back();
  }

  openInCatalog(library: string, bibId?: string, itemId?: string) {
    if (!this.customizations.libraries[library].reserves?.catalogUrl) return;

    let url = `${this.customizations.libraries[library].reserves.catalogUrl}`;
    if (url.includes('{{$bibId}}')) {
      url = url.replace('{{$bibId}}', bibId)
    }
    if (url.includes('{{$itemId}}')) {
      url = url.replace('{{$itemId}}', itemId)
    }
    this.gaService.logEvent(ACTIONS.openInCatalog, CATEGORIES.item, '' + bibId);
    window.open(url, '_blank');
  }

  digiReservesRequest(courseName: string, courseId: string, profName: string, item: any) {
    const formUrl = this.customizations.libraries[this.library].reserves.requestFormUrl;
    window.open(`${formUrl}?course_name=${encodeURIComponent(courseName)}&course_number=${encodeURIComponent(courseId)}&name=${encodeURIComponent(profName)}&book_title=${encodeURIComponent(item.title)}&book_author-edi=${encodeURIComponent(item.author)}`);
  }

  searchEbook(library: string, title: string, author?: string) {
    this.dialog.open(EbookSearchComponent, { data: { library: library, title: title, author: author }, panelClass: 'accessible-user-dialog' });
  }

  getIlsLocationsDefinition(library: string) {
    this.catalogService.getIlsLocationsDefinition(library).subscribe(res => {this.locations = res});
  }

  private _checkShowRequestButton() {
    if (this.customizations.libraries[this.library].reserves.showRequestButton) {
      if (this.customizations.libraries[this.library].reserves.showRequestButtonOnlyTo?.length) {        
        for (let userType of this.customizations.libraries[this.library].reserves.showRequestButtonOnlyTo) {          
          if (this.user[userType]) {
            this.showRequestButton = true;
            break;
          }
        }
      } else {
        this.showRequestButton = true;
      }
    }
  }

}
