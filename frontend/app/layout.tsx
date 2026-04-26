import "./globals.css";
import {Providers} from "@/components/providers/Providers";
import {ThemeProvider} from "@/components/layout/ThemeProvider";
import type {Metadata} from "next";
import {Manrope} from "next/font/google";

const manrope = Manrope({
    subsets: ["latin"],
    variable: "--font-manrope",
    display: "swap",
});

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
        <html lang="pl" suppressHydrationWarning className={manrope.variable}>
            <body className="antialiased font-sans">
            <ThemeProvider
                attribute="class"
                defaultTheme="dark"
                enableSystem
                disableTransitionOnChange
            >
                <Providers>{children}</Providers>
            </ThemeProvider>
            </body>
        </html>
    );
}