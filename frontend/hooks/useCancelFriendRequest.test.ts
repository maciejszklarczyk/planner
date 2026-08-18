import { createElement } from "react";
import { describe, it, expect, vi, beforeEach } from "vitest";
import { renderHook, waitFor } from "@testing-library/react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { toast } from "sonner";
import { useCancelFriendRequest } from "@/hooks/useCancelFriendRequest";
import { api, ApiError } from "@/lib/api";

vi.mock("@/lib/api", async () => {
  const actual = await vi.importActual<typeof import("@/lib/api")>("@/lib/api");
  return { ...actual, api: { post: vi.fn() } };
});

vi.mock("sonner", () => ({
  toast: { success: vi.fn(), error: vi.fn() },
}));

function createWrapper() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });
  return function Wrapper({ children }: { children: React.ReactNode }) {
    return createElement(
      QueryClientProvider,
      { client: queryClient },
      children,
    );
  };
}

describe("useCancelFriendRequest", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("calls api.post with the cancel endpoint and shows a success toast", async () => {
    vi.mocked(api.post).mockResolvedValueOnce(undefined);

    const { result } = renderHook(() => useCancelFriendRequest(), {
      wrapper: createWrapper(),
    });

    result.current.mutate(9);

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(api.post).toHaveBeenCalledWith("/friend-requests/9/cancel");
    expect(toast.success).toHaveBeenCalled();
  });

  it("shows the mapped message for FRIEND_REQUEST_NOT_PENDING instead of the generic fallback", async () => {
    vi.mocked(api.post).mockRejectedValueOnce(
      new ApiError(409, "Conflict", {
        error: "FRIEND_REQUEST_NOT_PENDING",
        message: "...",
      }),
    );

    const { result } = renderHook(() => useCancelFriendRequest(), {
      wrapper: createWrapper(),
    });

    result.current.mutate(9);

    await waitFor(() => expect(result.current.isError).toBe(true));

    expect(toast.error).toHaveBeenCalledWith(
      "Błąd",
      expect.objectContaining({
        description: "To zaproszenie zostało już rozpatrzone.",
      }),
    );
  });
});
