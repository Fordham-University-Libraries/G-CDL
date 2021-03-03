import { ComponentFixture, TestBed, waitForAsync } from '@angular/core/testing';

import { AccessibleUsersComponent } from './accessible-users.component';

describe('AccessibleUsersComponent', () => {
  let component: AccessibleUsersComponent;
  let fixture: ComponentFixture<AccessibleUsersComponent>;

  beforeEach(waitForAsync(() => {
    TestBed.configureTestingModule({
      declarations: [ AccessibleUsersComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(AccessibleUsersComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
