import "./globals.css";
import {Providers} from "@/components/providers/Providers";

export default function RootLayout({
    children,
}: {
    children: React.ReactNode;
}) {
    return (
        <html lang="pl">
            <body className="antialiased">
                <Providers>{children}</Providers>
            </body>
        </html>
    );
}