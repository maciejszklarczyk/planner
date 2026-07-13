export interface Group {
  id: number;
  name: string;
  description: string;
  membersCount: number;
}

export interface GroupsResponse {
  data: Group[];
}

export interface GroupMemberUser {
  id: number;
  email: string;
  name: string;
}

export interface GroupMembership {
  id: number;
  user: GroupMemberUser;
  groupId: number;
  groupName: string;
  role: "member" | "owner";
  addedBy: GroupMemberUser | null;
}

export interface GroupMembersResponse {
  data: GroupMembership[];
}
