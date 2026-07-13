import { QueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { ApiError } from "./api";

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 60 * 1000, // 1 minute
      retry: 1,
      refetchOnWindowFocus: false,
    },
    mutations: {
      onError: (error) => {
        // Global handler for 401 errors in mutations
        if (error instanceof ApiError && error.status === 401) {
          toast.error("Sesja wygasła", {
            description: "Zaloguj się ponownie, aby kontynuować",
          });

          // Redirect to login after a short delay
          setTimeout(() => {
            window.location.href = "/login";
          }, 1500);
        }
      },
    },
  },
});
