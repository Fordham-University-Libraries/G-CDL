import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, Subject, of } from 'rxjs';
import { map, publishReplay, refCount } from 'rxjs/operators';
import { Item } from './models/item.model';
import { environment } from '../environments/environment';

@Injectable({
  providedIn: 'root'
})
export class DriveService {

  apiBase: string = environment.apiBase;
  allItems$: Observable<any>[] = [];
  userCheckedOutItem: any;
  itemsWithMultipleCopies: any = [];

  constructor(
    private httpClient: HttpClient,
  ) { }

  getAllItems(forceRefresh: boolean = false, library: string): Observable<{library: string, nextPageToken?: string, items: Item[]}> {
      if (!this.allItems$[library] || forceRefresh) {                
        if (!forceRefresh) {          
          this.allItems$[library] = this.httpClient.get<any>(`${this.apiBase}/?libKey=${library}`, { withCredentials: true }).pipe(
                map((res) => res.data),
                publishReplay(1),
                refCount()
            );
        } else {
          this.allItems$[library] = this.httpClient.get<any>(`${this.apiBase}/?libKey=${library}`, { withCredentials: true }).pipe(
                map((res) => res.data)
            );
        }
    }

    return this.allItems$[library];
  }

  clearAllItemsCache() {
    this.allItems$ = [];
  }

  getAllItemsNextPage(nextPageToken: string): Observable<{nextPageToken?: string, items?: Item[]}> {
    let subject = new Subject<{nextPageToken?: string, items?: Item[]}>();
    if (!nextPageToken) subject.error('no next page token');
    this.httpClient.get(`${this.apiBase}/?action=view_all&nextPageToken=${nextPageToken}`, {withCredentials: true}).subscribe(res => {
      subject.next(res['data']);      
    }, (error) => {
      subject.error(error);
    })
    return subject;
  }

  getItemsWithMultipleCopies(library): Observable<any> {
    if (this.itemsWithMultipleCopies[library]) {      
      return of(this.itemsWithMultipleCopies[library]);
    }

    let subject = new Subject<any>();
    this.httpClient.get(`${this.apiBase}/?action=view_items_with_copies&libKey=${library}`, {withCredentials: true}).subscribe(res => {
      this.itemsWithMultipleCopies[library] = res['data'];
      subject.next(res['data']);
    });
    return subject;
  }

  getUserCheckedOutItem(forceRefresh: boolean = false): Observable<{usersLibrary: string, isAccessibleUser: boolean, item: Item}> | null {
    if (this.userCheckedOutItem && !forceRefresh) {
      return of(this.userCheckedOutItem);
    }
    let subject = new Subject<any>();
    this.httpClient.get(`${this.apiBase}/?action=view_borrowed`, {withCredentials: true}).subscribe(res => {
      subject.next(res['data']);
      this.userCheckedOutItem = res['data'];
    })
    return subject;
  }

  getItems(key: any, keyType: string, library: string = 'main'): Observable<Item[]> {
    return this.httpClient.get(`${this.apiBase}/?action=get_items&key=${key}&keyType=${keyType}&libKey=${library}`, {withCredentials: true}).pipe(map(val => val['data']));
  }

  borrowItem(fileId: string): Observable<any> {
    let formData: any = new FormData();
    formData.append("action", 'borrow');
    formData.append("fileId", fileId);
    return this.httpClient.post(`${this.apiBase}/`, formData, {withCredentials: true}).pipe(map(val => val['data']));
  }

  returnItem(fileId: string): Observable<any> {
    let formData: any = new FormData();
    formData.append("action", 'return');
    formData.append("fileId", fileId);
    return this.httpClient.post(`${this.apiBase}/`, formData, {withCredentials: true}).pipe(map(val => val['data']));
  }

  search(field: string, term: string, library: string): Observable<{field: string, term: string, library: string, isCachedData: boolean, results: Item[]}> {
    return this.httpClient.get(`${this.apiBase}/?action=search&field=${field}&term=${term}&libKey=${library}`, {withCredentials: true}).pipe(map(val => val['data']));
  }

  getItemsForAdmin(libKey: string): Observable<{field: string, term: string, library: string, results: Item[], staff: string[], admins: string[], configs: any[]}> {
    return this.httpClient.get(`${this.apiBase}/?action=admin&libKey=${libKey}`, {withCredentials: true}).pipe(map(val => val['data']));
  }

  suspendItem(fileId: string): Observable<any> {
    let formData: any = new FormData();
    formData.append("action", 'suspend');
    formData.append("fileId", fileId);
    return this.httpClient.post(`${this.apiBase}/`, formData, {withCredentials: true}).pipe(map(val => val['data']));
  }

  unsuspendItem(fileId: string): Observable<any> {
    let formData: any = new FormData();
    formData.append("action", 'unsuspend');
    formData.append("fileId", fileId);
    return this.httpClient.post(`${this.apiBase}/`, formData, {withCredentials: true}).pipe(map(val => val['data']));
  }

  trashItem(fileId: string): Observable<any> {
    let formData: any = new FormData();
    formData.append("action", 'trash');
    formData.append("fileId", fileId);
    return this.httpClient.post(`${this.apiBase}/`, formData, {withCredentials: true}).pipe(map(val => val['data']));  
  }

  checkItemInSystemByBibIds(bibIds: any[]): Observable<any> {
    let param = '?action=search_by_bib_ids&bibIds=' + bibIds.join(',');
    return this.httpClient.get(`${this.apiBase}/${param}`, {withCredentials: true}).pipe(map(val => val['data']));
  }

  //for admin compo
  getItemEditAdmin(fileId: string): Observable<Item> {
    return this.httpClient.get(`${this.apiBase}/?action=get_item_edit_admin&fileId=${fileId}`, {withCredentials: true}).pipe(map(val => val['data']));
  }

  editItemAdmin(fileId: string, partDesc: string, part: number, partTotal: number): Observable<any> {
    let formData: any = new FormData();
    formData.append("action", 'edit_item_admin');
    formData.append("fileId", fileId);
    formData.append("partDesc", partDesc);
    formData.append("part", part);
    formData.append("partTotal", partTotal);
    return this.httpClient.post(`${this.apiBase}/`, formData, {withCredentials: true}).pipe(map(val => val['data']));  
  }

  downloadFileAdmin(fileId: string, accessibleVersion: boolean = false) {
    window.open(`${this.apiBase}/?action=downalod_file_admin&fileId=${fileId}&accessibleVersion=${accessibleVersion ? 1 : 0}`);
  }

  uploadAdmin(file: any, libKey: string): Observable<any> {
    let formData: any = new FormData();
    formData.append('respondFormat','json')
    formData.append('action', 'upload');
    formData.append("libKey", libKey);
    formData.append("uploaded_file", file.file);
    for (const [key, value] of Object.entries(file)) {
      if (file[key].value && key != 'file') formData.append(key, file[key].value);
    }

    return this.httpClient.post(`${this.apiBase}/?action=upload`, formData, {withCredentials: true}).pipe(map(val => val['data']));
  }
}
