import { Component, OnInit, ViewChild } from '@angular/core';
import { Title } from '@angular/platform-browser';
import { ActivatedRoute, Router } from '@angular/router';
import { MatSort } from '@angular/material/sort';
import { MatTableDataSource } from '@angular/material/table';
import { MatPaginator } from '@angular/material/paginator';
import { MatDialog } from '@angular/material/dialog';
import { MatSnackBar } from '@angular/material/snack-bar';
import * as XLSX from 'xlsx';

import { User } from '../models/user.model';
import { Item } from '../models/item.model';
import { Config } from '../models/config.model';
import { AuthenticationService } from '../auth.service';
import { DriveService } from '../drive.service';
import { ConfigService } from '../config.service';
import { AdminService } from '../admin.service';
import { AdminItemEditComponent } from '../admin-item-edit/admin-item-edit.component';


@Component({
  selector: 'app-admin',
  templateUrl: './admin.component.html',
  styleUrls: ['./admin.component.scss']
})
export class AdminComponent implements OnInit {
  library: string;
  isStaff: boolean;
  user: User;
  isLoading: boolean;
  items: MatTableDataSource<Item>;
  rawItems: Item[]; //for Excel export
  displayedColumns: string[] = ['title', 'itemId', 'createdTime', 'lastBorrowed', 'isSuspended', 'action'];
  @ViewChild(MatSort, { static: false }) sort: MatSort;
  @ViewChild(MatPaginator, { static: false }) paginator: MatPaginator;
  staff: string[];
  admins: string[];
  mainFolder: any;
  about: any;
  itemEditDialogRef: any;
  config: Config;
  adminConfig: any;
  uploadUrl: string;

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private dialog: MatDialog,
    private driveService: DriveService,
    private configService: ConfigService,
    private authService: AuthenticationService,
    private titleService: Title,
    private adminService: AdminService,
    private snackBar: MatSnackBar,
  ) { }

  ngOnInit(): void {
    this.configService.getConfig().subscribe(cRes => {
      this.config = cRes;
      this.titleService.setTitle(`Admin : ${this.config.appName}`);
      this.authService.getUser().subscribe(res => {
        this.user = res;
        if (this.user.isStaffOfLibraries?.length) {
          this.isStaff = true;
          this.route.paramMap.subscribe(paramMap => {
            if (paramMap.get('library')) {              
              this.library = paramMap.get('library');
              if (!this.user.isStaffOfLibraries.includes(this.library)) {
                this.router.navigate(['/unauthed'], { skipLocationChange: true });
                return;
              } else {
                this._getItems();
              }
            } else {
              if (!this.user.isStaffOfLibraries.includes(this.config.defaultLibrary)) {
                this.router.navigate(['/unauthed'], { skipLocationChange: true });
                return;
              } else {
                this.library = this.config.defaultLibrary;
                this._getItems();
              }
            }
            this.uploadUrl = this.adminService.uploadUrl + "&libKey=" + this.library;
          });
        } else {
          this.router.navigate(['/unauthed'], { skipLocationChange: true });
          return;
        }
      });
    });
  }

  private _getItems() {
    //get all items
    this.driveService.getItemsForAdmin(this.library).subscribe(res => {      
      this.staff = res.staff;
      this.admins = res.admins;
      this.adminConfig = res.configs;
      this.items = new MatTableDataSource(res.results);
      this.items.paginator = this.paginator;
      this.items.sort = this.sort;
      this.rawItems = res.results;
      this.mainFolder = res.mainFolder;
      this.about = res.about;
      if (!this.about.storageQuota.limit) this.about.storageQuota.limit = 'unlimited';
      this.isLoading = false;
      //console.log(this.items);
    })
  }

  suspend(fileId: string) {
    this.isLoading = true;
    this.driveService.suspendItem(fileId).subscribe(res => {
      if (res) this._getItems();
    }, error => {
      this.snackBar.open('That didn\'t work  ', 'Dismiss', {
        duration: 3000,
      });
    })
  }

  unsuspend(fileId: string) {
    this.isLoading = true;
    this.driveService.unsuspendItem(fileId).subscribe(res => {
      this._getItems();
    })

  }

  trash(fileId: string) {
    this.isLoading = true;
    this.driveService.trashItem(fileId).subscribe(res => {
      //console.log(res);
      this._getItems();
    })
  }

  applyFilter(event: Event) {
    const filterValue = (event.target as HTMLInputElement).value;
    this.items.filter = filterValue.trim().toLowerCase();
  }

  editItem(fileId: string) {
    this.itemEditDialogRef = this.dialog.open(AdminItemEditComponent, { data: { fileId: fileId }, panelClass: 'accessible-user-dialog' });
    this.itemEditDialogRef.afterClosed().subscribe(data => {
      if (data) {
        //console.log(data);
        this.driveService.editItemAdmin(fileId, data.partDesc, data.part, data.partTotal).subscribe(res => {
          if (res) {
            this.snackBar.open('Item Updated!', 'Dismiss', {
              duration: 5000,
            });
          }
        });
      }
    });
  }

  export() {
    let data = [];
    data.push(['Bib ID', 'Title', 'Item ID', 'Added', 'Last Borrowed','Status','Part','Of Total Parts','Part Description','WITH-OCR file ID', 'NO-OCR file ID']);
    this.rawItems.forEach(item => {      
      data.push([item.bibId, item.title, item.itemId, item.createdTime, item.lastBorrowed, !item.isSuspended ? 'Active' : 'Suspended', item.part, item.partTotal, item.partDesc, item.fileWithOcrId, item.id]);
    });
    
    var wb = XLSX.utils.book_new();
    var ws_name = `Items-${this.library}`;
    var ws = XLSX.utils.aoa_to_sheet(data);
    XLSX.utils.book_append_sheet(wb, ws, ws_name);
    XLSX.writeFile(wb, 'cdl_items_metadata.xls');
  }
}
