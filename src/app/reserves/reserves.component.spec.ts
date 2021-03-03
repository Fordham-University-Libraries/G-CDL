import { ComponentFixture, TestBed, waitForAsync } from '@angular/core/testing';

import { ReservesComponent } from './reserves.component';

describe('ReservesComponent', () => {
  let component: ReservesComponent;
  let fixture: ComponentFixture<ReservesComponent>;

  beforeEach(waitForAsync(() => {
    TestBed.configureTestingModule({
      declarations: [ ReservesComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(ReservesComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
