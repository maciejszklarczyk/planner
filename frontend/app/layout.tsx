import "./globals.css";
import {Providers} from "@/components/providers/Providers";
import {ThemeProvider} from "@/components/layout/ThemeProvider";
import type {Metadata} from "next";

export const metadata: Metadata = {
    title: {
        template: '%s | EventPlanner4000',
        default: 'EventPlanner4000',
    },
    robots: {
        index: false,
        follow: false,
    },
};

export default function RootLayout({
    children,
}: {
    children: React.ReactNode;
}) {
    return (
        <html lang="pl" suppressHydrationWarning>
            <body className="antialiased">
            <ThemeProvider
                attribute="class"
                defaultTheme="system"
                enableSystem
                disableTransitionOnChange
            >
                <Providers>{children}</Providers>
            </ThemeProvider>
            </body>
        </html>
    );
}