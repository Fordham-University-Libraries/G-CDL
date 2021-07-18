import { Injectable } from '@angular/core';
import { Item } from './models/item.model';

@Injectable({
  providedIn: 'root'
})
export class ReaderService{
  checkedOutItem: Item
  gDriveReaderWindow: Window;
  expirationCheckDelayMS = 45000;

  constructor() { }

  openReaderDirectly(item: Item) {    
    this.checkedOutItem = item;    
    if (!this.gDriveReaderWindow || this.gDriveReaderWindow.closed) {
      this.gDriveReaderWindow = window.open(this.checkedOutItem.url, 'DGrive_Reader_' + this.checkedOutItem.id);
      const now: any = new Date();
      const due: any = new Date(this.checkedOutItem.due);
      const diffTime= Math.abs(due - now); //millisecs 
      //try to close the reader window when expired
      setTimeout(() => {
        if(this.hasWindowRef()) this.gDriveReaderWindow.self.close();      
      }, diffTime + this.expirationCheckDelayMS);
    } else {
      this.gDriveReaderWindow.focus();
    }
  }

  hasWindowRef(): boolean {
    if (!this.gDriveReaderWindow) {
      return false;
    } else if (this.gDriveReaderWindow.closed) {
      return false;
    } else {
      return true;
    }
  }

  closeWindowRef() {
    if (this.gDriveReaderWindow) {
      try {
        this.gDriveReaderWindow.self.close();
        this.gDriveReaderWindow = null;
      } catch (error) {
        console.error(error);    
      }
    }
  }

}
