import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { environment } from '../environments/environment';
import { Item } from './models/item.model'


@Injectable({
  providedIn: 'root'
})
export class CatalogService {

  constructor(
    private httpClient: HttpClient
  ) { } 

  getBib(key: string, libKey: string): Observable<any> {
    return this.httpClient.get<Item>(`${environment.apiBase}/?action=get_ils_bib&keyType=bibId&key=${key}&libKey=${libKey}`, {withCredentials: true}).pipe(
      map((res) => res['data']),
    );
  }

  //item barcode (ItemId)
  getBibByItemId(itemId: string, libKey: string): Observable<any> {
    return this.httpClient.get<Item>(`${environment.apiBase}/?action=get_ils_bib&keyType=itemId&key=${itemId}&libKey=${libKey}`, {withCredentials: true}).pipe(
      map((res) => res['data']),
    );
  }

  //we can't search 'current item location' with API (can onlt searc homeLoc)
  //item search how just filter out in template
  // search(term: string, field: string = 'GENERAL', location: string = 'RESERVE-RH'):Observable<any> {
  //   return this.httpClient.get(this.searchApiBase + this.searchCatalog + `&term1=${term}&includeAvailabilityInfo=true&searchType1=${field}&homeLocationFilter=${location}`, {withCredentials: true}).pipe(map(val => val));
  // }

  getIlsLocationsDefinition(libKey: string) {
    return this.httpClient.get<any>(`${environment.apiBase}/?action=get_ils_locations&libKey=${libKey}`, {withCredentials: true}).pipe(map(val => val['data']));
  }

  searchReservesCourses(libKey: string, field: string, searchTerm: string) {
    return this.httpClient.get<any>(`${environment.apiBase}/?action=search_courses&libKey=${libKey}&term=${searchTerm}&field=${field}`, {withCredentials: true}).pipe(map(val => val['data']));
  }

  //for Sirsi, course must be matched with prof
  getReserveCourseInfo(libKey: string, course: any) {    
    return this.httpClient.get<any>(`${environment.apiBase}/?action=get_course_info&libKey=${libKey}&courseNumber=${course.courseNumber}`, {withCredentials: true}).pipe(map(val => val['data']));
  }

  getReservesCoursesByUser(libKey: string, userPk: number) {
    return this.httpClient.get<any>(`${environment.apiBase}/?action=get_courses_by_user&libKey=${libKey}&userPk=${userPk}`, {withCredentials: true}).pipe(map(val => val['data']));
  }

  getDetailedCourseReserve(libKey: string, course: any) {
   return this.httpClient.get<any>(`${environment.apiBase}/?action=get_course_full&libKey=${libKey}&key=${course.id}`, {withCredentials: true}).pipe(map(val => val['data']));
  }

  searchForOnline(library: string, title: string, author?: string):Observable<any> {   
    return this.httpClient.get<any>(`${environment.apiBase}/?action=search_ils_ebook&libKey=${library}&title=${title}&author=${author}`, {withCredentials: true}).pipe(map(val => val['data']));
  }
}
