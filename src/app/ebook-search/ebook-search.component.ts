import { Component, OnInit, Input, Inject, Injector } from '@angular/core';
import { MAT_DIALOG_DATA } from '@angular/material/dialog';
import { MatDialogRef } from '@angular/material/dialog';
import { CatalogService } from '../catalog.service';

@Component({
  selector: 'app-ebook-search',
  templateUrl: './ebook-search.component.html',
  styleUrls: ['./ebook-search.component.scss']
})
export class EbookSearchComponent implements OnInit {
  @Input() title: string;
  @Input() author: string;
  @Input() library: string;
  dialogRef = null;
  items: any;
  isLoading: boolean;

  constructor(
    private catalogService: CatalogService,
    private injector: Injector,
    @Inject(MAT_DIALOG_DATA) public data: any
  ) {
    this.dialogRef = this.injector.get(MatDialogRef, null);
   }

  ngOnInit(): void {
    this.isLoading = true;
    this.title = this.data.title;    
    if (this.data.author) this.author = this.data.author.split(',')[0]; //so it's not too specific
    this.library = this.data.library;
    this.catalogService.searchForOnline(this.library, this.title, this.author).subscribe(res => {
      this.items = res;
      this.isLoading = false;
      //console.log(res);
    })
  }

  access(item: any) {
    window.open(item.url);
  }

  close() {
    this.dialogRef.close(false);
  }

}
