import { ComponentFixture, TestBed, waitForAsync } from '@angular/core/testing';

import { LibrariesComponent } from './libraries.component';

describe('LibrariesComponent', () => {
  let component: LibrariesComponent;
  let fixture: ComponentFixture<LibrariesComponent>;

  beforeEach(waitForAsync(() => {
    TestBed.configureTestingModule({
      declarations: [ LibrariesComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(LibrariesComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
