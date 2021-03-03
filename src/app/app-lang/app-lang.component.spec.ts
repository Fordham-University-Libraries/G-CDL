import { ComponentFixture, TestBed, waitForAsync } from '@angular/core/testing';

import { AppLangComponent } from './app-lang.component';

describe('AppLangComponent', () => {
  let component: AppLangComponent;
  let fixture: ComponentFixture<AppLangComponent>;

  beforeEach(waitForAsync(() => {
    TestBed.configureTestingModule({
      declarations: [ AppLangComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(AppLangComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
