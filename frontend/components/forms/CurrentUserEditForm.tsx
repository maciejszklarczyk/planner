'use client'

import { zodResolver } from "@hookform/resolvers/zod"
import {Controller, useForm} from "react-hook-form"
import * as z from "zod"
import {useEffect} from "react";
import {Input} from "@/components/ui/input";
import {Field, FieldError, FieldGroup, FieldLabel} from "@/components/ui/field";
import {useAuth} from "@/hooks/useAuth";
import {Button} from "@/components/ui/button";
import {useUpdateUser} from "@/hooks/useUpdateUser";

const formSchema = z.object({
    id: z
        .number(),
    name: z
        .string()
        .min(5, "Name title must be at least 5 characters.")
        .max(32, "Name title must be at most 240 characters."),
    email: z
        .email("Invalid email address."),

})

export default function CurrentUserEditForm() {
    const { user, isLoading } = useAuth();
    const { mutate: updateUser, isPending } = useUpdateUser({ invalidateKeys: [['auth', 'me']] });

    const form = useForm<z.infer<typeof formSchema>>({
        resolver: zodResolver(formSchema),
        defaultValues: {
            id: 0,
            name: "",
            email: "",
        },
    })

    useEffect(() => {
        if (user) {
            form.reset({
                id: user.id || 0,
                name: user.name || '',
                email: user.email || '',
            });
        }
    }, [user]);

    function onSubmit(data: z.infer<typeof formSchema>) {
        updateUser(data);
    }

    if (isLoading) {
        return (
            <>
                <h1>Edycja użytkownika</h1>
                <div className="text-center py-10">Ładowanie danych użytkownika...</div>
            </>
        );
    }

    if (!user) {
        return (
            <>
                <h1>Edycja użytkownika</h1>
                <div className="text-center py-10">Nie można załadować danych użytkownika.</div>
            </>
        );
    }

    return (
        <>
            <h1>Edycja użytkownika</h1>
            <form id="current-user-edit-form" onSubmit={form.handleSubmit(onSubmit)}>
                <FieldGroup>
                    <Controller
                        name="name"
                        control={form.control}
                        render={({ field, fieldState }) => (
                            <Field data-invalid={fieldState.invalid} className={'md:w-1/3'}>
                                <FieldLabel htmlFor="current-user-edit-form-name">
                                    Name
                                </FieldLabel>
                                <Input
                                    {...field}
                                    id="current-user-edit-form-name"
                                    aria-invalid={fieldState.invalid}
                                    placeholder="John Doe"
                                    autoComplete="off"
                                />
                                {fieldState.invalid && (
                                    <FieldError errors={[fieldState.error]} />
                                )}
                            </Field>
                        )}
                    />
                    <Controller
                        name="email"
                        control={form.control}
                        render={({ field, fieldState }) => (
                            <Field data-invalid={fieldState.invalid} className={'md:w-1/3'}>
                                <FieldLabel htmlFor="current-user-edit-form-email">
                                    E-Mail
                                </FieldLabel>
                                <Input
                                    {...field}
                                    id="current-user-edit-form-email"
                                    aria-invalid={fieldState.invalid}
                                    placeholder="test@example.com"
                                    autoComplete="off"
                                    disabled={true}
                                />
                                {fieldState.invalid && (
                                    <FieldError errors={[fieldState.error]} />
                                )}
                            </Field>
                        )}
                    />
                    <Button
                        type="submit"
                        form="current-user-edit-form"
                        disabled={isPending}
                    >
                        {isPending ? 'Zapisywanie...' : 'Zapisz'}
                    </Button>
                </FieldGroup>
            </form>
        </>
    )
}