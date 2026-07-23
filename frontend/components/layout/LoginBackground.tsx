"use client";

import { useEffect, useRef } from "react";

export function LoginBackground() {
  const brightDotsRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const handleMouseMove = (e: MouseEvent) => {
      if (!brightDotsRef.current) return;
      const mask = `radial-gradient(circle 160px at ${e.clientX}px ${e.clientY}px, black 0%, transparent 100%)`;
      brightDotsRef.current.style.maskImage = mask;
      brightDotsRef.current.style.webkitMaskImage = mask;
    };

    window.addEventListener("mousemove", handleMouseMove);
    return () => window.removeEventListener("mousemove", handleMouseMove);
  }, []);

  return (
    <div className="pointer-events-none absolute inset-0">
      {/* Base dots — dim */}
      <div
        className="absolute inset-0 opacity-20"
        style={{
          backgroundImage: "radial-gradient(circle, #666 1px, transparent 1px)",
          backgroundSize: "28px 28px",
        }}
      />

      {/* Bright dots — same grid, revealed near cursor via mask */}
      <div
        ref={brightDotsRef}
        className="absolute inset-0"
        style={{
          backgroundImage:
            "radial-gradient(circle, #4be277 1.5px, transparent 1.5px)",
          backgroundSize: "28px 28px",
          maskImage:
            "radial-gradient(circle 160px at -999px -999px, black 0%, transparent 100%)",
          WebkitMaskImage:
            "radial-gradient(circle 160px at -999px -999px, black 0%, transparent 100%)",
        }}
      />

      {/* Green glow — top right */}
      <div
        className="absolute -right-40 -top-40 size-[700px] rounded-full"
        style={{
          background:
            "radial-gradient(circle, rgba(34,197,94,0.35) 0%, transparent 65%)",
          animation: "glow-drift 9s ease-in-out infinite alternate",
        }}
      />

      {/* Lime glow — bottom left */}
      <div
        className="absolute -bottom-60 -left-40 size-[600px] rounded-full"
        style={{
          background:
            "radial-gradient(circle, rgba(132,204,22,0.25) 0%, transparent 65%)",
          animation: "glow-drift 13s ease-in-out infinite alternate-reverse",
        }}
      />

      {/* Center accent */}
      <div
        className="absolute left-1/2 top-1/2 size-[500px] -translate-x-1/2 -translate-y-1/2 rounded-full"
        style={{
          background:
            "radial-gradient(circle, rgba(75,226,119,0.10) 0%, transparent 60%)",
        }}
      />
    </div>
  );
}
