"use client";

import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { useRouter, useSearchParams } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { toast } from "sonner";
import { useSetPassword } from "@/hooks/useSetPassword";
import { useEffect, useState } from "react";

const setPasswordSchema = z.object({
  token: z.string().readonly(),
  password: z.string().min(1, "Hasło wymagane"),
});

type SetPasswordFormData = z.infer<typeof setPasswordSchema>;

export function SetPasswordForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const { mutate: setPassword, isPending } = useSetPassword();
  const token = searchParams.get("token") || "";
  const [isValid, setIsValid] = useState<boolean | null>(null);
  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<SetPasswordFormData>({
    resolver: zodResolver(setPasswordSchema),
  });

  useEffect(() => {
    let cancelled = false;

    async function verifyToken() {
      if (!token) {
        if (!cancelled) setIsValid(false);
        return;
      }
      try {
        const res = await fetch(
          `${process.env.NEXT_PUBLIC_API_URL}/invitation/verify?token=${token}`,
        );
        if (!res.ok) {
          const error = await res.json();
          throw new Error(error.message || "Token verification failed");
        }
        const data = await res.json();
        if (!cancelled) setIsValid(data.valid);
      } catch (error) {
        if (!cancelled) {
          setIsValid(false);
          toast.error("Błąd rejestracji", {
            description: error instanceof Error ? error.message : undefined,
          });
        }
      }
    }
    verifyToken();

    return () => {
      cancelled = true;
    };
  }, [token]);

  if (isValid === null) {
    return <div>Verifying token...</div>;
  }

  if (!isValid) {
    return <div>Token is invalid or expired</div>;
  }

  const onSubmit = (data: SetPasswordFormData) => {
    setPassword(data, {
      onSuccess: () => {
        toast.success("Zarejestrowano", {
          description: "Witaj w systemie! Możesz się teraz zalogować",
        });
        router.push("/login");
      },
      onError: (e) => {
        toast.error("Błąd rejestracji", {
          description: e.message,
        });
      },
    });
  };

  return (
    <Card className="w-full max-w-md">
      <CardHeader>
        <CardTitle>Rejestracja</CardTitle>
        <CardDescription>
          Ustaw hasło do swojego konta w EventPlanner4000
        </CardDescription>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
          <div className="space-y-2">
            <Input
              id="token"
              type="hidden"
              {...register("token")}
              disabled={isPending}
              value={token}
              readOnly={true}
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="password">Hasło</Label>
            <Input
              id="password"
              type="password"
              {...register("password")}
              disabled={isPending}
            />
            {errors.password && (
              <p className="text-sm text-red-500">{errors.password.message}</p>
            )}
          </div>

          <Button type="submit" className="w-full" disabled={isPending}>
            {isPending ? "Rejestracja..." : "Zarejestruj się"}
          </Button>
        </form>
      </CardContent>
    </Card>
  );
}
