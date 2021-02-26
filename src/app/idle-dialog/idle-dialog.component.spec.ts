import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { IdleDialogComponent } from './idle-dialog.component';

describe('IdleDialogComponent', () => {
  let component: IdleDialogComponent;
  let fixture: ComponentFixture<IdleDialogComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ IdleDialogComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(IdleDialogComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
