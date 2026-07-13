import { defineConfig, globalIgnores } from "eslint/config";
import nextVitals from "eslint-config-next/core-web-vitals";
import nextTs from "eslint-config-next/typescript";

const eslintConfig = defineConfig([
  ...nextVitals,
  ...nextTs,
  // Override default ignores of eslint-config-next.
  globalIgnores([
    // Default ignores of eslint-config-next:
    ".next/**",
    "out/**",
    "build/**",
    "next-env.d.ts",
  ]),
  {
    // shadcn/ui primitives — do not modify (CLAUDE.md). Only silence the
    // rule that flags generated code we're not allowed to touch; keep
    // other rules active so real bugs in this directory still get caught.
    files: ["components/ui/**"],
    rules: { "react-hooks/purity": "off" },
  },
]);

export default eslintConfig;
