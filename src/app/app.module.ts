import { BrowserModule } from '@angular/platform-browser';
import { NgModule } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { HttpClientModule } from '@angular/common/http';
import { A11yModule } from '@angular/cdk/a11y';
import { FlexLayoutModule } from '@angular/flex-layout';
import { MatButtonModule } from '@angular/material/button';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatToolbarModule } from '@angular/material/toolbar';
import { MatMenuModule } from '@angular/material/menu';
import { MatSnackBarModule } from '@angular/material/snack-bar';
import { MatTabsModule } from '@angular/material/tabs';
import { MatExpansionModule } from '@angular/material/expansion';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonToggleModule } from '@angular/material/button-toggle';
import { MatSelectModule } from '@angular/material/select';
//import { MatRadioModule } from '@angular/material/radio';
import { MatDialogModule } from '@angular/material/dialog';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatNativeDateModule } from '@angular/material/core';
import { MatTableModule } from '@angular/material/table';
import { MatSortModule } from '@angular/material/sort';
import { MatPaginatorModule } from '@angular/material/paginator';
import { MatStepperModule } from '@angular/material/stepper';
import { MatSlideToggleModule } from '@angular/material/slide-toggle';
import { MatCardModule } from '@angular/material/card';

import { AngularEditorModule } from '@kolkov/angular-editor';

import { SafePipe } from './pipes/safe.pipe';
import { AppRoutingModule } from './app-routing.module';
import { AppComponent } from './app.component';
import { BrowserAnimationsModule } from '@angular/platform-browser/animations';
import { HomeComponent } from './home/home.component';
import { ItemComponent } from './item/item.component';
import { MyComponent } from './my/my.component';
import { ReservesComponent } from './reserves/reserves.component';
import { ReservesSearchBarComponent } from './reserves-search-bar/reserves-search-bar.component';
import { TermOfServiceComponent } from './term-of-service/term-of-service.component';
import { StatsComponent } from './stats/stats.component';
import { AdminComponent } from './admin/admin.component';
import { AboutComponent } from './about/about.component';
import { AdminItemEditComponent } from './admin-item-edit/admin-item-edit.component';
import { EbookSearchComponent } from './ebook-search/ebook-search.component';
import { ReaderComponent } from './reader/reader.component';
import { CheckedOutItemComponent } from './checked-out-item/checked-out-item.component';
import { ErrorComponent } from './error/error.component';
import { AccessibleUsersComponent } from './accessible-users/accessible-users.component';
import { PageComponent } from './page/page.component';
import { AppConfigComponent } from './app-config/app-config.component';
import { AppLangComponent } from './app-lang/app-lang.component';
import { AppCustomizationComponent } from './app-customization/app-customization.component';
import { LibrariesComponent } from './libraries/libraries.component';
import { AdminUploadComponent } from './admin-upload/admin-upload.component';
import { IdleDialogComponent } from './idle-dialog/idle-dialog.component';

@NgModule({
  declarations: [
    SafePipe,
    AppComponent,
    HomeComponent,
    ItemComponent,
    MyComponent,
    ReservesComponent,
    ReservesSearchBarComponent,
    TermOfServiceComponent,
    StatsComponent,
    AdminComponent,
    AboutComponent,
    AdminItemEditComponent,
    EbookSearchComponent,
    ReaderComponent,
    CheckedOutItemComponent,
    ErrorComponent,
    AccessibleUsersComponent,
    PageComponent,
    AppConfigComponent,
    AppLangComponent,
    AppCustomizationComponent,
    LibrariesComponent,
    AdminUploadComponent,
    IdleDialogComponent
  ],
  imports: [
    BrowserModule,
    FormsModule,
    AppRoutingModule,
    BrowserAnimationsModule,
    HttpClientModule,
    A11yModule,
    FlexLayoutModule,
    MatButtonModule,
    MatTooltipModule,
    MatIconModule,
    MatProgressSpinnerModule,
    MatToolbarModule,
    MatMenuModule,
    MatSnackBarModule,
    MatTabsModule,
    MatExpansionModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonToggleModule,
    MatSelectModule,
    //MatRadioModule,
    MatDialogModule,
    MatCheckboxModule,
    MatDatepickerModule,
    MatNativeDateModule,
    MatSortModule,
    MatTableModule,
    MatPaginatorModule,
    MatStepperModule,
    MatSlideToggleModule,
    MatCardModule,
    AngularEditorModule
  ],
  providers: [],
  bootstrap: [AppComponent]
})
export class AppModule { }
