import { Component, OnInit, Input } from '@angular/core';
import { Router } from '@angular/router';
import { Config } from '../models/config.model';

@Component({
  selector: 'app-reserves-search-bar',
  templateUrl: './reserves-search-bar.component.html',
  styleUrls: ['./reserves-search-bar.component.scss']
})
export class ReservesSearchBarComponent implements OnInit {
  @Input() config: Config;
  @Input() thisLibraryReservesLang: any;
  @Input() currentReserveMode: string;
  @Input() currentReservesSearchTerm: string;
  @Input() library: string;
  @Input() isDefaultLibraryRoute: boolean;
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
    let encodedTerm = encodeURIComponent(this.reservesSearchTerm);
    if (this.isDefaultLibraryRoute) {
      this.router.navigate([`/search/reserves/${this.reserveMode}/${encodedTerm}`]);
    } else {
      this.router.navigate([`/library/${this.library}/search/reserves/${this.reserveMode}/${encodedTerm}`]);
    }
  }

  clear() {
    this.reservesSearchTerm = null;
  }

}
