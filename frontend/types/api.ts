import { User } from './auth';

export interface ApiResponse<T> {
    data: T;
}

export interface UsersResponse {
    data: User[];
}
