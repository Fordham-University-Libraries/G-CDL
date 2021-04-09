import { Deserializable } from './deserializable.model';

export class User implements Deserializable {
    public userName?: string;
    public fullName?: string;
    public homeLibrary?: string;
    public univId?: string;
    public photoUrl?: string;
    public email?: string;
    public isActiveUser?: boolean;
    public isAccessibleUser?: boolean;
    public isFacStaff?: boolean;
    public isGradStudent?: boolean;
    public isStaffOfLibraries?: string[];
    public isAdminOfLibraries?: string[];
    public isDriveOwner?: boolean;

    deserialize(input: any): this {
        return input;
    }
}
