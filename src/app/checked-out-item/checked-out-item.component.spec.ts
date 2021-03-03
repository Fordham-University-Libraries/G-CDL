import { ComponentFixture, TestBed, waitForAsync } from '@angular/core/testing';

import { CheckedOutItemComponent } from './checked-out-item.component';

describe('CheckedOutItemComponent', () => {
  let component: CheckedOutItemComponent;
  let fixture: ComponentFixture<CheckedOutItemComponent>;

  beforeEach(waitForAsync(() => {
    TestBed.configureTestingModule({
      declarations: [ CheckedOutItemComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(CheckedOutItemComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
