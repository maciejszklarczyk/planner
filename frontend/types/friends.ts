export type FriendRequestStatus =
  "pending" | "accepted" | "declined" | "cancelled";

export interface FriendRequestOtherUser {
  id: number;
  name: string;
  email: string;
  avatar: string | null;
}

export interface FriendRequestDto {
  id: number;
  otherUser: FriendRequestOtherUser;
  status: FriendRequestStatus;
  createdAt: string;
}

// Deliberately not { data: T[] } — matches the backend's actual GET /friend-requests shape.
export interface FriendRequestsResponse {
  incoming: FriendRequestDto[];
  outgoing: FriendRequestDto[];
}

export interface FriendsResponse {
  data: FriendRequestOtherUser[];
}

export interface UserSearchResult {
  id: number;
  name: string;
  email: string;
  avatar: string | null;
}

export interface UserSearchResponse {
  data: UserSearchResult[];
}
