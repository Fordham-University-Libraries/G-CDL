import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { AdminUploadComponent } from './admin-upload.component';

describe('AdminUploadComponent', () => {
  let component: AdminUploadComponent;
  let fixture: ComponentFixture<AdminUploadComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ AdminUploadComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(AdminUploadComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
