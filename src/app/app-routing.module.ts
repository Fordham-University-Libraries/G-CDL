import { NgModule } from '@angular/core';
import { Routes, RouterModule } from '@angular/router';
import { HomeComponent } from './home/home.component';
import { LibrariesComponent } from './libraries/libraries.component';
import { ItemComponent } from './item/item.component';
import { MyComponent } from './my/my.component';
import { ReaderComponent } from './reader/reader.component';
import { ReservesComponent } from './reserves/reserves.component';
import { StatsComponent } from './stats/stats.component';
import { AdminComponent } from './admin/admin.component';
import { AdminUploadComponent } from './admin-upload/admin-upload.component';
import { AboutComponent } from './about/about.component';
import { AccessibleUsersComponent } from './accessible-users/accessible-users.component';
import { ErrorComponent } from './error/error.component';
import { PageComponent } from './page/page.component';
import { AuthGuard } from './auth.guard';
import { AppConfigComponent } from './app-config/app-config.component';
import { AppLangComponent } from './app-lang/app-lang.component';
import { AppCustomizationComponent } from './app-customization/app-customization.component';

const routes: Routes = [
  { path: '', component: HomeComponent, canActivate: [AuthGuard], data: { isDefaultLibraryRoute: true, appPath: 'home' } }, //default library of user
  { path: 'item/:bibId', component: ItemComponent, canActivate: [AuthGuard], data: { isDefaultLibraryRoute: true, mode: 'bibId', appPath: 'item' } },
  { path: 'item/itemId/:itemId', component: ItemComponent, canActivate: [AuthGuard], data: { isDefaultLibraryRoute: true, mode: 'itemId', appPath: 'item' } },
  { path: 'item/:library/:bibId', redirectTo: 'library/:library/item/:bibId'}, //backward compat
  { path: 'search/reserves', component: ReservesComponent, canActivate: [AuthGuard], data: { isDefaultLibraryRoute: true, mode: 'browse', appPath: 'reserves' }},
  { path: 'search/reserves/course/:courseId', component: ReservesComponent, canActivate: [AuthGuard], data: { isDefaultLibraryRoute: true, mode: 'details', appPath: 'reserves' }},
  { path: 'search/reserves/:browseMode/:searchTerm', component: ReservesComponent, canActivate: [AuthGuard], data: { isDefaultLibraryRoute: true, mode: 'browse', appPath: 'reserves' }},
  { path: 'my', component: MyComponent, canActivate: [AuthGuard], data: { isDefaultLibraryRoute: true, mode: 'my', appPath: 'my' } },
  { path: 'return', component: MyComponent, canActivate: [AuthGuard], data: { isDefaultLibraryRoute: true, mode: 'return', appPath: 'my' } },
  { path: 'read', component: MyComponent, canActivate: [AuthGuard], data: { isDefaultLibraryRoute: true, mode: 'read', appPath: 'my' } },
  { path: 'reader', component: ReaderComponent, canActivate: [AuthGuard], data: { isDefaultLibraryRoute: true, appPath: 'reader' } },
  // { path: 'reader2', component: ReaderComponent, canActivate: [AuthGuard], data: { isDefaultLibraryRoute: true, appPath: 'reader', mode: 1 } },
  { path: 'about', component: AboutComponent, canActivate: [AuthGuard], data: { isDefaultLibraryRoute: true, appPath: 'about' } },
  { path: 'stats', component: StatsComponent, canActivate: [AuthGuard], data: { isDefaultLibraryRoute: true, appPath: 'stats' } },
  { path: 'admin', component: AdminComponent, canActivate: [AuthGuard], data: { isDefaultLibraryRoute: true, appPath: 'admin' } },
  { path: 'admin/upload', component: AdminUploadComponent, canActivate: [AuthGuard], data: { appPath: 'admin', mode: 'upload' } },
  { path: 'admin/accessible', component: AccessibleUsersComponent, canActivate: [AuthGuard], data: { appPath: 'admin', mode: 'accessible' } },
  { path: 'admin/config', component: AppConfigComponent, canActivate: [AuthGuard], data: { appPath: 'admin', mode: 'config' } },
  { path: 'admin/config/lang', component: AppLangComponent, canActivate: [AuthGuard], data: { appPath: 'admin', mode: 'lang' } },
  { path: 'admin/config/customization', component: AppCustomizationComponent, canActivate: [AuthGuard], data: { appPath: 'admin', mode: 'cust' } },
  { path: 'libraries', component: LibrariesComponent, canActivate: [AuthGuard], data: { appPath: 'libraries' } }, //libraries selector
  { path: 'library/:library', component: HomeComponent, canActivate: [AuthGuard], data: { appPath: 'home' } },
  { path: 'library/:library/item/itemId/:itemId', component: ItemComponent, canActivate: [AuthGuard], data: { mode: 'itemId', appPath: 'item' } },
  { path: 'library/:library/item/:bibId', component: ItemComponent, canActivate: [AuthGuard], data: { mode: 'bibId', appPath: 'item' } },
  { path: 'library/:library/search/reserves', component: ReservesComponent, canActivate: [AuthGuard], data: { mode: 'browse', appPath: 'reserves' }},
  { path: 'library/:library/search/reserves/course/:courseId', component: ReservesComponent, canActivate: [AuthGuard], data: { mode: 'details', appPath: 'reserves' }},
  { path: 'library/:library/search/reserves/:browseMode/:searchTerm', component: ReservesComponent, canActivate: [AuthGuard], data: { mode: 'browse', appPath: 'reserves' }},
  { path: 'library/:library/about', component: AboutComponent, canActivate: [AuthGuard], data: { appPath: 'about' } },
  { path: 'library/:library/stats', component: StatsComponent, canActivate: [AuthGuard], data: { appPath: 'stats' } },
  { path: 'library/:library/admin', component: AdminComponent, canActivate: [AuthGuard], data: { appPath: 'admin' } },
  { path: 'library/:library/admin/upload', component: AdminUploadComponent, canActivate: [AuthGuard], data: { appPath: 'admin', mode: 'upload' } },
  { path: 'library/:library/admin/accessible', component: AccessibleUsersComponent, canActivate: [AuthGuard], data: { appPath: 'admin', mode: 'accessible' } },
  { path: 'library/:library/admin/config', component: AppConfigComponent, canActivate: [AuthGuard], data: { appPath: 'admin', mode: 'config' } },
  { path: 'library/:library/admin/config/lang', component: AppLangComponent, canActivate: [AuthGuard], data: { appPath: 'admin', mode: 'lang' } },
  { path: 'library/:library/admin/config/customization', component: AppCustomizationComponent, canActivate: [AuthGuard], data: { appPath: 'admin', mode: 'cust' } },
  { path: 'library/:library/unauthed', component: ErrorComponent, canActivate: [AuthGuard], data: { mode: 'unauthed', appPath: 'error'}}, //authz
  { path: 'unauthed', component: ErrorComponent, canActivate: [AuthGuard], data: { mode: 'unauthed', appPath: 'error'}}, //authz
  { path: 'unknown-library', component: ErrorComponent, canActivate: [AuthGuard], data: { mode: 'unknownLibrary', appPath: 'error'}},
  { path: 'error-disabled', component: ErrorComponent, canActivate: [AuthGuard], data: { mode: 'disabled', appPath: 'error'}},
  { path: 'api-error/:reason', component: ErrorComponent, data: { mode: 'apiError', appPath: 'page'}},
  { path: 'api-error', component: ErrorComponent, data: { mode: 'apiError', appPath: 'page'}},
  { path: 'logged-out', component: PageComponent, data: { isDefaultLibraryRoute: true, mode: 'loggedOut', appPath: 'page'}},
  { path: '**', component: ErrorComponent, canActivate: [AuthGuard], data: { mode: '404', appPath: 'page'}},
];

@NgModule({
  imports: [RouterModule.forRoot(routes, { relativeLinkResolution: 'legacy' })],
  exports: [RouterModule]
})
export class AppRoutingModule { }
