import { ComponentFixture, TestBed, waitForAsync } from '@angular/core/testing';

import { AdminItemEditComponent } from './admin-item-edit.component';

describe('AdminItemEditComponent', () => {
  let component: AdminItemEditComponent;
  let fixture: ComponentFixture<AdminItemEditComponent>;

  beforeEach(waitForAsync(() => {
    TestBed.configureTestingModule({
      declarations: [ AdminItemEditComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(AdminItemEditComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
