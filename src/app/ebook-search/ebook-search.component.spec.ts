import { ComponentFixture, TestBed, waitForAsync } from '@angular/core/testing';

import { EbookSearchComponent } from './ebook-search.component';

describe('EbookSearchComponent', () => {
  let component: EbookSearchComponent;
  let fixture: ComponentFixture<EbookSearchComponent>;

  beforeEach(waitForAsync(() => {
    TestBed.configureTestingModule({
      declarations: [ EbookSearchComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(EbookSearchComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
