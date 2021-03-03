import { Deserializable } from './deserializable.model';

export interface LibraryConfigValues {
    name: string;
    ilsApiEnabled: boolean;
    itemIdInFilenameRegexPattern: string;   
}

export class Config implements Deserializable {
    public appName: string;
    public defaultLibrary: string;
    public emailDomain: string;
    public gSuitesDomain: string;
    public gTagUA: string;
    public maxFileSizeInMb: number;
    public useEmbedReader: boolean;
    //UNION see https://stackoverflow.com/questions/38260414/typescript-interface-for-objects-with-some-known-and-some-unknown-property-names && https://www.typescriptlang.org/docs/handbook/advanced-types.html
    public libraries: {
        [libKey: string]: LibraryConfigValues
    };

    deserialize(input: any): this {
        return input;
    }
}
