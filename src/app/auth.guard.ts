import { Injectable } from '@angular/core';
import { CanActivate, ActivatedRouteSnapshot, RouterStateSnapshot, Router } from '@angular/router';
import { User } from './models/user.model';
import { AuthenticationService } from './auth.service';
import { GaService, ACTIONS, CATEGORIES } from './ga.service';
import { Subject } from 'rxjs';

@Injectable({ providedIn: 'root' })
export class AuthGuard implements CanActivate {
    user: User;
    sessionTtlMinutes: number = 60;
    constructor(
        private router: Router,
        private authenticationService: AuthenticationService,
        private gaService: GaService
    ) { }

    canActivate(route: ActivatedRouteSnapshot, state: RouterStateSnapshot) {
        //console.log('canActivate');
        let subject = new Subject<boolean>();
        const cookie = document.cookie.split('; ').find(row => row.startsWith('cdlLogin=1'));
        const isLoggedIn = !!cookie;         
        if (isLoggedIn) return true;
        
        this.authenticationService.getUser(true).subscribe(res => {
            console.log(res);
            if (res) {
                if (!res.error) {
                    this.user = res;
                    subject.next(true);                    
                    this.gaService.logEvent(ACTIONS.login, CATEGORIES.login, `homeLibrary: ${this.user.homeLibrary}, isAccessibleUser: ${this.user.isAccessibleUser}`);          
                } else if (res.url) {
                    //main auth coplete but also need authicate with local auth system                
                    //auth returns a soft (recoverable) 302, go to the local auth URL provided
                    window.location.href = res.url + encodeURIComponent('&target=' + window.location.pathname);
                    return;
                } else {                    
                    if(res.error = "No Library") {
                        this.router.navigate(['/api-error/no-lib'], {skipLocationChange: true});   
                    }
                    subject.next(false);
                }
            } else {   
                this.authenticationService.login(window.location.pathname);
                subject.next(false);
            }
        }, (error) => {
            console.log('auth guard hard err');
            if (error.status == 401) {
                this.authenticationService.login(window.location.pathname);
                subject.next(false);
            } else {
                if(error.error = "No Library") {
                    this.router.navigate(['/api-error/no-lib'], {skipLocationChange: true});   
                } else {
                    //this.router.navigate(['/api-error'], {skipLocationChange: true});
                }
            }
            subject.next(false);
        });
        return subject;
    }
}