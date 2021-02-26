import { Component, OnInit, Inject, Injector, Optional } from '@angular/core';
import { MAT_DIALOG_DATA } from '@angular/material/dialog';
import { MatDialogRef } from "@angular/material/dialog";

@Component({
  selector: 'app-term-of-service',
  templateUrl: './term-of-service.component.html',
  styleUrls: ['./term-of-service.component.scss']
})
export class TermOfServiceComponent implements OnInit {
  dialogRef = null;
  title: string;
  iAgree: boolean;

  constructor(
    private injector: Injector,
    @Optional() @Inject(MAT_DIALOG_DATA) public data: any
  ) {
    this.dialogRef = this.injector.get(MatDialogRef, null); //make this compo works as both dialog and standalone
   }

  ngOnInit(): void {
    this.title = this.data.title;  
  }

  onAccept(): void {
    this.dialogRef.close(true);
  }

  onDecline(): void {
    this.dialogRef.close(false);
  }
}
