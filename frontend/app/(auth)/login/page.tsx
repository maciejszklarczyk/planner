import { Suspense } from "react";
import { LoginForm } from "@/components/forms/LoginForm";
import { LoginBackground } from "@/components/layout/LoginBackground";
import type { Metadata } from "next";

export const metadata: Metadata = {
  title: "Logowanie",
};

export default function LoginPage() {
  return (
    <div className="relative flex min-h-screen items-center justify-center overflow-hidden bg-background">
      <LoginBackground />
      <Suspense fallback={<div>Ładowanie...</div>}>
        <LoginForm />
      </Suspense>
    </div>
  );
}
