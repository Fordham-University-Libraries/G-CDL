import { Injectable, isDevMode } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, Subject, of } from 'rxjs';
import { map, publishReplay, refCount } from 'rxjs/operators';
import { environment } from '../environments/environment';

@Injectable({
    providedIn: 'root'
})
export class AuthenticationService {
    authCheckUrl:string;
    getUser$: Observable<any>;

    constructor(
        private httpClient: HttpClient
    ) {
        this.authCheckUrl = environment.apiBase + '/?action=auth';
    }

    getUser(forceRefresh: boolean = false): Observable<any> {
            if (!this.getUser$ || forceRefresh) {
                if (!forceRefresh) {
                    this.getUser$ = this.httpClient.get<any>(this.authCheckUrl, { withCredentials: true }).pipe(
                        map((res) => res.data),
                        publishReplay(1),
                        refCount()
                    );
                } else {
                    this.getUser$ = this.httpClient.get<any>(this.authCheckUrl, { withCredentials: true }).pipe(
                        map((res) => res.data)
                    );
                }
            }

        return this.getUser$;
    }

    login(target: string = null) {
        this.getUser$ = null;
        let loginUrl = environment.apiBase + '/?action=login';
        if (target) loginUrl += '&target=' + target;
        window.location.href = loginUrl;
    }

    logout() {
        this.getUser$ = null;
        window.location.href = environment.apiBase + '/?action=logout';
    }

}