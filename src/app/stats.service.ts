import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, throwError } from 'rxjs';
import { map } from 'rxjs/operators';
import { environment } from '../environments/environment';

@Injectable({
  providedIn: 'root'
})
export class StatsService {
  apiBase: string = environment.apiBase;

  constructor(
    private httpClient: HttpClient
  ) { }

  getStats(from: any = '', to: any = ''): Observable<any> {
    return this.httpClient.get(`${this.apiBase}/?action=get_stats&from=${from}&to=${to}`, {withCredentials: true}).pipe(
      map((res) => {        
        if (res['status'] === 200) {
          for (const [libkey, libvalue] of Object.entries(res['data'])) {
            for (const [key, value] of Object.entries(res['data'][libkey])) {
              value['last_borrow_tstamp'] = new Date(value['last_borrow_tstamp'] * 1000);          
            }
          }
          return res['data'];
        } else {
          throw new Error(res['data'].error);
        }
      }),
    );
  }
}
