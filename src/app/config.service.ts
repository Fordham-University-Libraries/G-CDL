import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, Subject, of } from 'rxjs';
import { map, publishReplay, refCount } from 'rxjs/operators';
import { environment } from '../environments/environment';
import { EventEmitter } from '@angular/core';

@Injectable({
  providedIn: 'root'
})
export class ConfigService {

  apiBase: string = environment.apiBase;
  config$: Observable<any>
  authConfig$: Observable<any>
  lang$: Observable<any>
  customization$: Observable<any>
  accessibleUsers$: Observable<any>
  onForceRefresh = new EventEmitter<boolean>();

  constructor(private httpClient: HttpClient) { }

  getConfig(forceRefresh = false): Observable<any> {
    if (!this.config$) {
      this.config$ = this.httpClient.get<any>(`${this.apiBase}/?action=get_config`, {withCredentials: true}).pipe(
        map((res) => res['data']),
        publishReplay(1),
        refCount()
      );
    }
    return this.config$;
  }

  getAuthConfig(): Observable<any> {
    if (!this.authConfig$) {
      this.authConfig$ = this.httpClient.get<any>(`${this.apiBase}/?action=get_auth_config`, {withCredentials: true}).pipe(
        map((res) => res['data']),
        publishReplay(1),
        refCount()
      );
    }
    return this.authConfig$;
  }

  getLang(forceRefresh = false): Observable<any> {
    if (!this.lang$) {
      this.lang$ = this.httpClient.get<any>(`${this.apiBase}/?action=get_lang`, {withCredentials: true}).pipe(
        map((res) => res['data']),
        publishReplay(1),
        refCount()
      );
    }
    return this.lang$;
  }

  getCustomization(forceRefresh = false): Observable<any> {
    if (!this.customization$ || forceRefresh) {
      if (!forceRefresh) {
        this.customization$ = this.httpClient.get<any>(`${this.apiBase}/?action=get_customization`, {withCredentials: true}).pipe(
          map((res) => res['data']),
          publishReplay(1),
          refCount()
        );
      } else {
        this.customization$ = this.httpClient.get<any>(`${this.apiBase}/?action=get_customization`, {withCredentials: true}).pipe(
          map((res) => res['data'])
        );
      }
    }
    return this.customization$;
  }

  getAccessibleUsers(forceRefresh: boolean = false): Observable<any> {
    if (!this.accessibleUsers$ || forceRefresh) {
      if (!forceRefresh) {
        this.accessibleUsers$ = this.httpClient.get<any>(`${this.apiBase}/?action=get_accessible_users`, {withCredentials: true}).pipe(
          map((res) => res['data']),
          publishReplay(1),
          refCount()
        );
      } else {
        this.accessibleUsers$ = this.httpClient.get<any>(`${this.apiBase}/?action=get_accessible_users`, {withCredentials: true}).pipe(
          map((res) => res['data'])
        );
      }
    }
    return this.accessibleUsers$;
  }
}
