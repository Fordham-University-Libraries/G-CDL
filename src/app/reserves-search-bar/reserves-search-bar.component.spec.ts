import { ComponentFixture, TestBed, waitForAsync } from '@angular/core/testing';

import { ReservesSearchBarComponent } from './reserves-search-bar.component';

describe('ReservesSearchBarComponent', () => {
  let component: ReservesSearchBarComponent;
  let fixture: ComponentFixture<ReservesSearchBarComponent>;

  beforeEach(waitForAsync(() => {
    TestBed.configureTestingModule({
      declarations: [ ReservesSearchBarComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(ReservesSearchBarComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
