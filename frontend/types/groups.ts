export interface Group {
    id: number;
    name: string;
    description: string;
    membersCount: number;
}

export interface GroupsResponse {
    data: Group[];
}