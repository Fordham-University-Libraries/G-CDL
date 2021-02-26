import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { environment } from '../environments/environment';

@Injectable({
  providedIn: 'root'
})
export class AdminService {
  uploadUrl: string;

  constructor(
    private httpClient: HttpClient
  ) { 
    this.uploadUrl = environment.apiBase + '/?action=admin_upload';
  }

  lookupUsersByNames(names: string[]): Observable<any> {
    let formData: any = new FormData();
    formData.append("action", 'lookup_users');
    formData.append("names", names);
    return this.httpClient.post(`${environment.apiBase}/`, formData, {withCredentials: true}).pipe(map(val => val['data']));  
  }

  addAccessibleUsers(userNames: string[]): Observable<any> {
    let formData: any = new FormData();
    formData.append("action", 'add_accessible_users');
    formData.append("userNames", userNames);
    return this.httpClient.post(`${environment.apiBase}/`, formData, {withCredentials: true}).pipe(map(val => val['data']));  
  }

  removeAccessibleUsers(userNames: string[]): Observable<any> {
    let formData: any = new FormData();
    formData.append("action", 'remove_accessible_users');
    formData.append("userNames", userNames);
    return this.httpClient.post(`${environment.apiBase}/`, formData, {withCredentials: true}).pipe(map(val => val['data']));  
  }

  getConfigsAdmin(): Observable<any> {
    return this.httpClient.get(`${environment.apiBase}/?action=get_config_admin`,{withCredentials: true}).pipe(map(val => val['data'])); 
  }

  getLangAdmin(): Observable<any> {
    return this.httpClient.get(`${environment.apiBase}/?action=get_lang_admin`,{withCredentials: true}).pipe(map(val => val['data'])); 
  }

  getCustomizationAdmin(): Observable<any> {
    return this.httpClient.get(`${environment.apiBase}/?action=get_customization_admin`,{withCredentials: true}).pipe(map(val => val['data'])); 
  }

  updateConfigAdmin(props: any, kind: string = 'global', libKey: string = null): Observable<any> {    
    let formData: any = new FormData();
    formData.append("action", 'update_config_admin');
    formData.append("kind", kind);
    if (libKey) formData.append("libKey", libKey);
    formData.append("properties", JSON.stringify(props));
    
    return this.httpClient.post(`${environment.apiBase}/`, formData, {withCredentials: true}).pipe(map(val => val['data']));  
  }

  updateLangAdmin(props: any, libKey: string): Observable<any> {    
    let formData: any = new FormData();
    formData.append("action", 'update_lang_admin');
    formData.append("libKey", libKey);
    formData.append("properties", JSON.stringify(props));
    
    return this.httpClient.post(`${environment.apiBase}/`, formData, {withCredentials: true}).pipe(map(val => val['data']));  
  }

  updateAboutAdmin(html: string, libKey: string): Observable<any> {    
    let formData: any = new FormData();
    formData.append("action", 'update_about_admin');
    formData.append("libKey", libKey);
    formData.append("html", html);
    
    return this.httpClient.post(`${environment.apiBase}/`, formData, {withCredentials: true}).pipe(map(val => val['data']));  
  }

  updateCustomizationAdmin(props: any, libKey: string): Observable<any> {    
    let formData: any = new FormData();
    formData.append("action", 'update_customization_admin');
    formData.append("libKey", libKey);
    formData.append("properties", JSON.stringify(props));
    
    return this.httpClient.post(`${environment.apiBase}/`, formData, {withCredentials: true}).pipe(map(val => val['data']));  
  }

  addNewLibrary(newLib: {key: string, name: string}): Observable<any> {    
    let formData: any = new FormData();
    formData.append("action", 'add_new_library_config_admin');
    formData.append("key", newLib.key);
    formData.append("name", newLib.name);
    return this.httpClient.post(`${environment.apiBase}/`, formData, {withCredentials: true}).pipe(map(val => val['data']));  
  }

  removeLibrary(libKey: string): Observable<any> {    
    let formData: any = new FormData();
    formData.append("action", 'remove_library_config_admin');
    formData.append("key", libKey);
    return this.httpClient.post(`${environment.apiBase}/`, formData, {withCredentials: true}).pipe(map(val => val['data']));  
  }
}
