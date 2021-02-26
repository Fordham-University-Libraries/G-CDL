import { Injectable } from '@angular/core';
import { ConfigService } from './config.service';

@Injectable({
  providedIn: 'root'
})
export class GaService {
  gaTrackingId: string;

  constructor(
    private configService: ConfigService
  ) { 
    this.configService.getConfig().subscribe(res => {
      //console.log(res);
      if (res?.gTagUA) {
      this.gaTrackingId = res.gTagUA;
      const gaScript = document.createElement('script');
      gaScript.setAttribute('async', 'true');
      gaScript.setAttribute('src', `https://www.googletagmanager.com/gtag/js?id=${this.gaTrackingId}`);
      document.documentElement.firstChild.appendChild(gaScript);

      const gaFunctScript = document.createElement('script');
      gaFunctScript.innerText = `window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '${this.gaTrackingId}', { 'send_page_view': false });`; //will manually tracl 
      document.documentElement.firstChild.appendChild(gaFunctScript);
      //console.log('GA service constructed');
      }
    });
  }

  init() {
    //construct me!
  }

  logPageView (title: string, location: string, path?: string) {
    if (!this.gaTrackingId) return;
    (<any>window).gtag('config', this.gaTrackingId, {
      'page_title': title,
      'page_location': location,
      'page_path': path
    })
  };

  logEvent (action : ACTIONS, cat : string, label? : string, val? : number) {
    if (!this.gaTrackingId) return;
    (<any>window).gtag('event', action, {
      'event_category': cat,
      'event_label': label,
      'value': val
    });
  }

  logError (desc: string, fatal: boolean = false) {
    if (!this.gaTrackingId) return;
    (<any>window).gtag('event', 'exception', {
      'description': desc,
      'fatal': fatal,
    });
  }
}

export enum ACTIONS {
  borrow = 'borrow',
  read = 'read',
  download = 'download',
  return = 'return_manual',
  openInCatalog = 'open_in_catalog',
  itemView = 'item_level_view',
  login = 'login',
  openNewWindow = 'open_in_new_windows'
}

export enum CATEGORIES {
  login = 'login',
  home = 'home',
  item = 'item',
  my = 'my account',
  reader = 'reader'
}
