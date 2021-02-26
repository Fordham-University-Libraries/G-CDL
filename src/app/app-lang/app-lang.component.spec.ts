import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { AppLangComponent } from './app-lang.component';

describe('AppLangComponent', () => {
  let component: AppLangComponent;
  let fixture: ComponentFixture<AppLangComponent>;

  beforeEach(async(() => {
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
