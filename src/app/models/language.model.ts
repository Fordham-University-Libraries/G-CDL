import { Deserializable } from './deserializable.model';

export interface LibraryLangValues {
    about: {
        aboutHead: string;
        html: string;
        snippetDescription: string;
        snippetHead: string;
    }
    currentCheckout: {
        head: string;
        itemHead: string;
        itemHeadThis: string;
        noItem: string;
    }
    emails: {
        borrowBody: string;
        borrowSubject: string;
        returnBody: string;
        returnSubject: string;
    },
    error: {
        404: string;
        accessible: { 
            downloadNotFound: string;
        }
        borrow: {
            backToBack: string;
            backToBackCopy: string;
            notAvailGeneric: string;
            notAvailHaveOtherViewer: string;
            unknownError: string;
        }
        disabled: string;
        genericError: string;
        getItemsHome: {
            noItems: string;
            page: string;
            snackBar: string;
        }
        item: {
            notOwnedByMe: string;
            notPartOfCollecton: string;
        }
        loggedOut: string;
        reader: {
            noItemCheckedOut: string
        }
        return: {
            unknownError: string;
            userDoesNotHaveItemCheckedOut: string;
        }
        unauthed: string;
        unknownLibrary: string;
    }
    home: {
        copy: string;
        homeHead: string;
        itemsHead: string;
        multiParts: string;
        part: string;
    }
    item: {
        copies: string;
        copy: string;
        itemHead: string;
        part: string;
    }
    reader: {
        dueBack: string;
        help: {
            helpDesc: string;
            helpHead: string;
            helpText: string;
            openReaderInNewWindowButtonText: string;
            openReaderInNewWindowText: string;
        }
        itemHasBeenAutoReturned: string;
        readerHead: string;
    }
    reserves: {
        availDigitalHead: string;
        availPrintHead: string;
        availPrintSubhead: string;
        course: string;
        details: string;
        head: string;
        items: string;
        prof: string;
        searchFields: {
            courseName: {hint: string, placeholder: string, text: string};
            courseNumber: {hint: string, placeholder: string, text: string}
            courseProf: {hint: string, placeholder: string, text: string}
        }
        snippetDescription: string;
        snippetHead: string;
        subtitle: string;
        unavailDigital: string;
        unavailPrint: string;
    }
    upload: {
        helpText: string;
    },
}

export class Language implements Deserializable {
    public libraries: {
        [libKey: string]: LibraryLangValues
    };

    deserialize(input: any): this {
        return input;
    }
}
