"use client";

import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { useRouter, useSearchParams } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import {
  Field,
  FieldError,
  FieldGroup,
  FieldLabel,
} from "@/components/ui/field";
import { toast } from "sonner";
import { useLogin } from "@/hooks/useLogin";

const loginSchema = z.object({
  email: z.string().email("Nieprawidłowy email"),
  password: z.string().min(1, "Hasło wymagane"),
});

type LoginFormData = z.infer<typeof loginSchema>;

export function LoginForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const { mutate: login, isPending } = useLogin();

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<LoginFormData>({
    resolver: zodResolver(loginSchema),
  });

  const onSubmit = (data: LoginFormData) => {
    login(data, {
      onSuccess: () => {
        toast.success("Zalogowano", {
          description: "Witaj z powrotem!",
        });
        const redirect = searchParams.get("redirect") || "/events";
        router.push(redirect);
      },
      onError: () => {
        toast.error("Błąd logowania", {
          description: "Nieprawidłowy email lub hasło",
        });
      },
    });
  };

  return (
    <Card className="w-full max-w-md backdrop-blur-md bg-card/60 border-white/10">
      <CardHeader>
        <CardTitle>Logowanie</CardTitle>
        <CardDescription>Zaloguj się do EventPlanner4000</CardDescription>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
          <FieldGroup>
            <Field data-invalid={!!errors.email}>
              <FieldLabel htmlFor="email">Email</FieldLabel>
              <Input
                id="email"
                type="email"
                {...register("email")}
                aria-invalid={!!errors.email}
                disabled={isPending}
              />
              {errors.email && <FieldError errors={[errors.email]} />}
            </Field>

            <Field data-invalid={!!errors.password}>
              <FieldLabel htmlFor="password">Hasło</FieldLabel>
              <Input
                id="password"
                type="password"
                {...register("password")}
                aria-invalid={!!errors.password}
                disabled={isPending}
              />
              {errors.password && <FieldError errors={[errors.password]} />}
            </Field>
          </FieldGroup>

          <Button type="submit" className="w-full" disabled={isPending}>
            {isPending ? "Logowanie..." : "Zaloguj się"}
          </Button>
        </form>
      </CardContent>
    </Card>
  );
}
