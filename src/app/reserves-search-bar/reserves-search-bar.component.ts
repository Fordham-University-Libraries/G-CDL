import { Component, OnInit, Input } from '@angular/core';
import { Router } from '@angular/router';

@Component({
  selector: 'app-reserves-search-bar',
  templateUrl: './reserves-search-bar.component.html',
  styleUrls: ['./reserves-search-bar.component.scss']
})
export class ReservesSearchBarComponent implements OnInit {
  @Input() config: any;
  @Input() lang: any;
  @Input() currentReserveMode: string;
  @Input() currentReservesSearchTerm: string;
  @Input() library: string;
  @Input() isUsersDefaultLibrary: boolean;
  reserveMode: string;
  reservesSearchTerm: string;
 
  constructor(private router: Router) { }

  ngOnInit(): void {
    if (this.currentReserveMode) this.reserveMode = this.currentReserveMode;
    if (!this.reserveMode) this.reserveMode = "courseName";
    if (this.currentReservesSearchTerm) this.reservesSearchTerm = this.currentReservesSearchTerm;    
  }

  searchReserve() {
    if (!this.reservesSearchTerm) return;
    
    this.currentReservesSearchTerm = this.reservesSearchTerm;
    this.currentReserveMode = this.reserveMode;
    if (this.isUsersDefaultLibrary) {
      this.router.navigate([`/search/reserves/${this.reserveMode}/${this.reservesSearchTerm}`]);
    } else {
      this.router.navigate([`/library/${this.library}/search/reserves/${this.reserveMode}/${this.reservesSearchTerm}`]);
    }
  }

  clear() {
    this.reservesSearchTerm = null;
  }

}
