import { Component, OnInit, ViewChild } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { User } from '../models/user.model';
import { StatsService } from '../stats.service';
import { AuthenticationService } from '../auth.service';
import { ConfigService } from '../config.service';
import { MatSort } from '@angular/material/sort';
import { MatTableDataSource } from '@angular/material/table';
import { MatPaginator } from '@angular/material/paginator';
import * as XLSX from 'xlsx';

@Component({
  selector: 'app-stats',
  templateUrl: './stats.component.html',
  styleUrls: ['./stats.component.scss']
})
export class StatsComponent implements OnInit {
  isLoading: boolean;
  statsRaw: any;
  stats: MatTableDataSource<any>;
  error: string;
  displayedColumns: string[] = ['title', 'itemId', 'borrow', 'auto_return', 'manual_return', 'avg_mins', 'last_borrow_tstamp'];
  @ViewChild(MatSort, { static: false }) sort: MatSort;
  @ViewChild(MatPaginator, { static: false }) paginator: MatPaginator;
  totalBorrow: number;
  totalAutoReturn: number;
  totalManualReturn: number;
  toDate: any;
  fromDate: any;
  library: string;
  user: User;
  config: any;

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private configService: ConfigService,
    private authService: AuthenticationService,
    private statsService: StatsService
  ) { }

  ngOnInit(): void {
    this.configService.getConfig().subscribe(cRes => {
      this.config = cRes;
      this.authService.getUser().subscribe(res => {
        this.user = res;
        if (this.user.isStaffOfLibraries?.length) {
          this.route.paramMap.subscribe(paramMap => {
            if (paramMap.get('library')) {
              this.library = paramMap.get('library');
              if (!this.user.isStaffOfLibraries.includes(this.library)) {
                this.router.navigate([`/library/${this.library}/unauthed`], { skipLocationChange: true });
                return;
              } else {
                this.getStats();
              }
            } else {
              if (!this.user.isStaffOfLibraries.includes(this.config.defaultLibrary)) {
                this.router.navigate(['/unauthed'], { skipLocationChange: true });
                return;
              } else {
                this.library = this.config.defaultLibrary;
                this.getStats();
              }
            }
          });
        } else {
          this.router.navigate(['/unauthed'], { skipLocationChange: true });
          return;
        }
      });
    });
  }

  getStats(from?: number, to?: number) {
    if (!this.config.libraries[this.library]) {
      this.router.navigate(['/error-no-library'], { skipLocationChange: true });
      return;
    }
    this.isLoading = true;
    this.statsService.getStats(from, to).subscribe(res => {
      if (res[this.library]) {
        this.statsRaw = res[this.library];
        this.stats = new MatTableDataSource(this.statsRaw);
        this.totalBorrow = res.totalLawBorrow;
        this.totalManualReturn = res.totalLawManualReturn;
        this.totalAutoReturn = this.totalBorrow - this.totalManualReturn;
        this.stats.paginator = this.paginator;
        this.stats.sort = this.sort;
      } else if (res.error) {
        this.error = res.error;
      } else {
        this.error = 'Error: something went wrong!';
      }
      this.isLoading = false;
    }, (error) => {
      this.error = error;
      this.isLoading = false;
    })
  }

  filter() {
    if (this.fromDate && this.toDate) {
      this.getStats(this.fromDate.valueOf() / 1000, this.toDate.valueOf() / 1000);
    } else if (this.fromDate) {
      this.getStats(this.fromDate.valueOf() / 1000);
    } else if (this.toDate) {
      this.getStats(null, this.toDate.valueOf() / 1000);
    }
  }

  clearFilter() {
    this.fromDate = null;
    this.toDate = null;
    this.getStats();
  }

  export() {
    console.log(this.statsRaw);
    let data = [];
    if (this.fromDate || this.toDate) data.push([`CDL Stats for ${this.library} [${this.fromDate} - ${this.toDate}]`]);
    data.push(['Title', 'Item ID', '#Borrow', '#Auto Return','#Manual Return', '#Avg Manual Result (minutes)', 'Last Borrowed']);
    this.statsRaw.forEach(item => {
      data.push([item.title, item.itemId, +item.borrow, +item.auto_return, item.manual_return ? +item.manual_return : 0, item.avg_manual_return_seconds ? +item.avg_manual_return_seconds / 60 : '-', item.last_borrow_tstamp])
    });
    console.log(this.fromDate);
    var wb = XLSX.utils.book_new();
    var ws_name = `Stats-${this.library}`;
    var ws = XLSX.utils.aoa_to_sheet(data);
    XLSX.utils.book_append_sheet(wb, ws, ws_name);
    XLSX.writeFile(wb, 'cdl_stats.xls');
  }

}
