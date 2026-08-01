import { ApiError } from "@/lib/api";

const ERROR_MESSAGES: Record<string, string> = {
  CANNOT_FRIEND_SELF: "Nie możesz wysłać zaproszenia do samego siebie.",
  USER_NOT_FOUND: "Nie znaleziono użytkownika o podanym adresie email.",
  ALREADY_FRIENDS: "Jesteście już znajomymi.",
  DUPLICATE_FRIEND_REQUEST: "Zaproszenie do tej osoby już zostało wysłane.",
  FRIEND_REQUEST_COOLDOWN_ACTIVE:
    "Musisz poczekać, zanim ponownie wyślesz zaproszenie do tej osoby.",
  FRIEND_REQUEST_NOT_FOUND: "Nie znaleziono zaproszenia.",
  FRIEND_REQUEST_NOT_PENDING: "To zaproszenie zostało już rozpatrzone.",
};

/**
 * Maps the backend's { error: "CODE", ... } envelope to a specific, user-facing Polish
 * message. Falls back to the caller-supplied generic message for unmapped codes.
 */
export function getApiErrorMessage(error: unknown, fallback: string): string {
  if (error instanceof ApiError) {
    const code = (error.body as { error?: string } | undefined)?.error;
    if (code && code in ERROR_MESSAGES) {
      return ERROR_MESSAGES[code];
    }
  }

  return fallback;
}
