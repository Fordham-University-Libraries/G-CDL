import { ComponentFixture, TestBed, waitForAsync } from '@angular/core/testing';

import { AdminUploadComponent } from './admin-upload.component';

describe('AdminUploadComponent', () => {
  let component: AdminUploadComponent;
  let fixture: ComponentFixture<AdminUploadComponent>;

  beforeEach(waitForAsync(() => {
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
