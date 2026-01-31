'use client'

import React, {ReactNode} from 'react'

import {usePathname} from 'next/navigation'

import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList, BreadcrumbPage,
    BreadcrumbSeparator
} from "@/components/ui/breadcrumb";

type TBreadCrumbProps = {
    homeElement: ReactNode,
    listClasses?: string,
    activeClasses?: string,
    capitalizeLinks?: boolean
}

export function BreadcrumbHelper({
                                     homeElement,
                                     listClasses,
                                     activeClasses,
                                     capitalizeLinks
                                 }: TBreadCrumbProps) {
    const paths = usePathname()
    const pathNames = paths.split('/').filter(path => path)

    return (
        <Breadcrumb>
            <BreadcrumbList>
                <BreadcrumbItem className="hidden md:block">
                    <BreadcrumbLink href="#">
                        {homeElement}
                    </BreadcrumbLink>
                </BreadcrumbItem>
                {pathNames.map((link, index) => {
                    let href = `/${pathNames.slice(0, index + 1).join('/')}`
                    let itemClasses = paths === href ? `${listClasses} ${activeClasses}` : listClasses
                    let itemLink = capitalizeLinks ? link[0].toUpperCase() + link.slice(1, link.length) : link
                    return (
                        <>
                            <BreadcrumbSeparator className="hidden md:block"/>
                            <BreadcrumbLink href={href}>
                                <BreadcrumbPage>{itemLink}</BreadcrumbPage>
                            </BreadcrumbLink>
                        </>
                    )
                })}

            </BreadcrumbList>
        </Breadcrumb>
    )
}