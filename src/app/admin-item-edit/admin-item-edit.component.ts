import { Component, OnInit, Input, Inject, Injector } from '@angular/core';
import { MAT_DIALOG_DATA } from '@angular/material/dialog';
import { MatDialogRef } from "@angular/material/dialog";
import { DriveService } from '../drive.service';

@Component({
  selector: 'app-admin-item-edit',
  templateUrl: './admin-item-edit.component.html',
  styleUrls: ['./admin-item-edit.component.scss']
})
export class AdminItemEditComponent implements OnInit {
  @Input() fileId: string;
  dialogRef = null;
  item: any;
  partDesc: string;
  part: number;
  partTotal: number;

  constructor(
    private driveService: DriveService,
    private injector: Injector,
    @Inject(MAT_DIALOG_DATA) public data: any
  ) {
    this.dialogRef = this.injector.get(MatDialogRef, null);
   }

  ngOnInit(): void {
    this.fileId = this.data.fileId;
    if (this.fileId) {
      this.driveService.getItemEditAdmin(this.fileId).subscribe(res => {
        this.item = res;        
        if (this.item.partDesc) this.partDesc = this.item.partDesc;
        if (this.item.part) this.part = this.item.part;
        if (this.item.partTotal) this.partTotal = this.item.partTotal;
        //console.log(this.item);
      })
    }
  }

  save() {
    this.dialogRef.close({'fileId': this.fileId, 'partDesc' : this.partDesc, 'part': this.part, 'partTotal': this.partTotal});
  }

  close() {
    this.dialogRef.close(false);
  }

  adminDownloadFile(fileId: string, accessibleVersion: boolean = false) {
    this.driveService.downloadFileAdmin(fileId, accessibleVersion);
  }

}
