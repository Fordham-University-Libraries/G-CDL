import { Deserializable } from './deserializable.model';

export class Item implements Deserializable {
    public id: string;
    public name: string;
    public title: string;
    public author?: string;
    public library: string;
    public bibId: string;
    public itemId: string;
    public createdTime: string;
    public part?: any;
    public partTotal?: number;
    public partDesc?: number;
    public available: boolean;
    public isCheckedOutToMe?: boolean;
    public due?: string;
    public url?: string;
    public webContentLink?: string;
    public downloadLink?: string;
    public accessibleFileId?: string;
    //cataelog api
    // public publisherName?: string;
    // public extent?: string;
    //internal
    // public appProperties?: {
    //     bibId: string,
    //     itemId: string,
    //     fileWithOcrId: string,
    //     author?: string,
    //     lastReturned?: string;
    //     lastViewer?: string;
    //     lastBorrowed?: string;
    // };
    //admin - stats
    public shouldCreateNoOcr?: boolean;
    public fileWithOcrId?: string;
    public isSuspended?: number;
    public created?: string;
    public lastBorrowed?: string;
    public mime?: string;
    public size?: string;

    deserialize(input: any): this {
        return input;
    }
}
