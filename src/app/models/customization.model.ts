import { Deserializable } from './deserializable.model';

export interface LibraryCustomizationCSSValues {
    color?: string;
    'backgroud-color'?: string;
}

export interface LibraryCustomizationValues {
    a: {
        ':hover': {
            css: LibraryCustomizationCSSValues
        }
        ':visited': {
            css: LibraryCustomizationCSSValues
        }
        css: LibraryCustomizationCSSValues
    }
    body: {
        css: LibraryCustomizationCSSValues
    }
    borrowing: {
        css: LibraryCustomizationCSSValues
    }
    bread: {
        css: LibraryCustomizationCSSValues,
        active: {
            css: LibraryCustomizationCSSValues
        },
        breadLink: {
            css: LibraryCustomizationCSSValues
        },
        libHomeLink: string;
        libName: string;
    },
    'button-primary': {
        css: LibraryCustomizationCSSValues
    },
    header: {
        first: {
            css: LibraryCustomizationCSSValues,
            display: boolean,
            link: string,
            logo: string,
            logoAltText: string,
            text: string
        },
        second: {
            css: LibraryCustomizationCSSValues,
            display: boolean,
            link: string,
            logo: string,
            logoAltText: string,
            text: string
        },
        third: {
            css: LibraryCustomizationCSSValues,
            display: boolean,
            'is-active': {
                css: LibraryCustomizationCSSValues
            },
            'user-button': {
                css: LibraryCustomizationCSSValues
            }
        }
    },
    home: {
        css: LibraryCustomizationCSSValues,
        'item-card': {css: LibraryCustomizationCSSValues},
        showAboutSnippet: boolean,
        showCourseSearchSnippet: boolean,
        showCurrentCheckoutSnippet: number
    },
    item: {
        css: LibraryCustomizationCSSValues,
        catalogUrl: string,
        syndeticClientId: string,
        useIlsApiForMetadataEnhancement: boolean,
        'copy-card': {css: LibraryCustomizationCSSValues},
        showAboutSnippet: boolean,
        showCourseSearchSnippet: boolean,
        showCurrentCheckoutSnippet: number
    },
    reserves: {
        enable: boolean,
        catalogUrl: string,
        showSearchForEbooks: boolean,
        ilsEbookLocationName: string,
        showRequestButton: boolean,
        requestFormUrl: string,
        css: LibraryCustomizationCSSValues
    } 
}

export class Customization implements Deserializable {
    public libraries: {
        [libKey: string]: LibraryCustomizationValues
    };

    deserialize(input: any): this {
        return input;
    }
}
