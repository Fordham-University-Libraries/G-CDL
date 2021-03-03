import { Injectable, isDevMode } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, Subject, of } from 'rxjs';
import { map, publishReplay, refCount } from 'rxjs/operators';
import { environment } from '../environments/environment';

@Injectable({
  providedIn: 'root'
})
export class PageService {

  aboutPages$: any = {};

  constructor(
    private httpClient: HttpClient
  ) { }

  getAbout(library: string) {
    //console.log(this.aboutPages$);
    
    if (!this.aboutPages$[library]) {
      this.aboutPages$[library] = this.httpClient.get(`${environment.apiBase}/?action=get_about&libKey=${library}`, { withCredentials:true, responseType: 'text' }).pipe(
        map((res) => res),
        publishReplay(1),
        refCount()
      );
    }
    return this.aboutPages$[library]
  }

}
