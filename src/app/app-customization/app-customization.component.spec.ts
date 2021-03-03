import { ComponentFixture, TestBed, waitForAsync } from '@angular/core/testing';

import { AppCustomizationComponent } from './app-customization.component';

describe('AppCustomizationComponent', () => {
  let component: AppCustomizationComponent;
  let fixture: ComponentFixture<AppCustomizationComponent>;

  beforeEach(waitForAsync(() => {
    TestBed.configureTestingModule({
      declarations: [ AppCustomizationComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(AppCustomizationComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
