export type UserStatus = 'new' | 'active' | 'inactive' | 'blocked' | 'deleted';

export interface User {
    id: number;
    email: string;
    name: string;
    roles: string[];
    status: UserStatus;
    avatar?: string;
}

export interface LoginCredentials {
    email: string;
    password: string;
}

export interface RegisterCradentials {
    password: string;
    token: string;
}

export interface LoginResponse {
    user: User;
}