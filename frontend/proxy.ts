import { NextResponse } from 'next/server';
import type { NextRequest } from 'next/server';

const PUBLIC_ROUTES = ['/login'];

export function proxy(request: NextRequest) {
    const { pathname } = request.nextUrl;

    const isPublicRoute = PUBLIC_ROUTES.some(route =>
        pathname.startsWith(route)
    );

    if (isPublicRoute) {
        return NextResponse.next();
    }

    const sessionCookie = request.cookies.get('PLANNER_SESSION');

    if (!sessionCookie) {
        const redirectUrl = new URL('/login', request.url);
        redirectUrl.searchParams.set('redirect', pathname);
        return NextResponse.redirect(redirectUrl);
    }

    return NextResponse.next();
}

export const config = {
    matcher: [
        '/((?!_next/static|_next/image|favicon.ico|.*\\..*|api).*)',
    ],
};