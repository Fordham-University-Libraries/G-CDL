import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { AppCustomizationComponent } from './app-customization.component';

describe('AppCustomizationComponent', () => {
  let component: AppCustomizationComponent;
  let fixture: ComponentFixture<AppCustomizationComponent>;

  beforeEach(async(() => {
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
