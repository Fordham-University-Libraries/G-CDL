import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { EbookSearchComponent } from './ebook-search.component';

describe('EbookSearchComponent', () => {
  let component: EbookSearchComponent;
  let fixture: ComponentFixture<EbookSearchComponent>;

  beforeEach(async(() => {
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
