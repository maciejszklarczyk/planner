'use client'

import { zodResolver } from "@hookform/resolvers/zod"
import {Controller, useForm} from "react-hook-form"
import * as z from "zod"
import {useEffect, useRef, useState} from "react";
import {Input} from "@/components/ui/input";
import {Field, FieldError, FieldGroup, FieldLabel} from "@/components/ui/field";
import {useAuth} from "@/hooks/useAuth";
import {Button} from "@/components/ui/button";
import {useUpdateUser} from "@/hooks/useUpdateUser";
import {Separator} from "@/components/ui/separator";
import {Avatar, AvatarFallback, AvatarImage} from "@/components/ui/avatar";
import {useDeleteAvatar, useUploadAvatar} from "@/hooks/useUploadAvatar";

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
    const [avatarBust, setAvatarBust] = useState(() => Date.now());
    const { mutate: uploadAvatar, isPending: isUploading } = useUploadAvatar({
        onSuccess: () => setAvatarBust(Date.now()),
    });
    const { mutate: deleteAvatar, isPending: isDeleting } = useDeleteAvatar({
        onSuccess: () => setAvatarBust(Date.now()),
    });
    const fileInputRef = useRef<HTMLInputElement>(null);

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

    const initials = user.name
        ?.split(' ')
        .map(w => w[0])
        .slice(0, 2)
        .join('')
        .toUpperCase() ?? '?';

    const avatarSrc = user.avatar ? `${user.avatar}?v=${avatarBust}` : undefined;

    return (
        <div className="flex flex-col gap-8">
            <section>
                <h2 className="text-lg font-semibold mb-4">Profil</h2>
                <div className="flex items-center gap-4 mb-6">
                    <Avatar className="size-16 text-lg">
                        <AvatarImage src={avatarSrc}/>
                        <AvatarFallback>{initials}</AvatarFallback>
                    </Avatar>
                    <div className="flex gap-2">
                        <input
                            ref={fileInputRef}
                            type="file"
                            accept="image/jpeg,image/png,image/webp,image/gif"
                            className="hidden"
                            onChange={e => {
                                const file = e.target.files?.[0];
                                if (file) uploadAvatar(file);
                                e.target.value = '';
                            }}
                        />
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={isUploading}
                            onClick={() => fileInputRef.current?.click()}
                        >
                            {isUploading ? 'Przesyłanie...' : 'Zmień avatar'}
                        </Button>
                        {user.avatar && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                disabled={isDeleting}
                                onClick={() => deleteAvatar()}
                            >
                                {isDeleting ? 'Usuwanie...' : 'Usuń'}
                            </Button>
                        )}
                    </div>
                </div>
                <form id="current-user-edit-form" onSubmit={form.handleSubmit(onSubmit)}>
                    <div className="flex flex-col gap-4 max-w-sm">
                        <Controller
                            name="name"
                            control={form.control}
                            render={({ field, fieldState }) => (
                                <Field data-invalid={fieldState.invalid}>
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
                                <Field data-invalid={fieldState.invalid}>
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
                            className="w-fit"
                        >
                            {isPending ? 'Zapisywanie...' : 'Zapisz'}
                        </Button>
                    </div>
                </form>
            </section>

            <Separator/>

            <section>
                <h2 className="text-lg font-semibold mb-1">Zmiana hasła</h2>
                <p className="text-sm text-muted-foreground mb-4">Funkcja w przygotowaniu.</p>
                <div className="flex flex-col gap-4 max-w-sm">
                    <Input placeholder="Aktualne hasło" type="password" disabled/>
                    <Input placeholder="Nowe hasło" type="password" disabled/>
                    <Input placeholder="Powtórz nowe hasło" type="password" disabled/>
                    <Button disabled className="w-fit">Zmień hasło</Button>
                </div>
            </section>
        </div>
    )
}