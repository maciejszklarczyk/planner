'use client'

import { zodResolver } from "@hookform/resolvers/zod"
import {Controller, useForm} from "react-hook-form"
import * as z from "zod"
import {useEffect, useRef, useState} from "react";
import {Input} from "@/components/ui/input";
import {FieldError} from "@/components/ui/field";
import {useAuth} from "@/hooks/useAuth";
import {Button} from "@/components/ui/button";
import {useUpdateUser} from "@/hooks/useUpdateUser";
import {Avatar, AvatarFallback, AvatarImage} from "@/components/ui/avatar";
import {useDeleteAvatar, useUploadAvatar} from "@/hooks/useUploadAvatar";
import {SettingCard, SettingRow} from "@/components/settings/SettingsPrimitives";

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
                <p className="text-[11px] font-semibold text-muted-foreground uppercase tracking-[0.06em] mb-3">
                    Profil
                </p>
                <form id="current-user-edit-form" onSubmit={form.handleSubmit(onSubmit)}>
                    <SettingCard>
                        <SettingRow label="Avatar" hint="Twoje zdjęcie profilowe">
                            <div className="flex items-center gap-3">
                                <Avatar className="size-9 text-sm">
                                    <AvatarImage src={avatarSrc}/>
                                    <AvatarFallback>{initials}</AvatarFallback>
                                </Avatar>
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
                                    {isUploading ? 'Przesyłanie...' : 'Zmień'}
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
                        </SettingRow>
                        <Controller
                            name="name"
                            control={form.control}
                            render={({field, fieldState}) => (
                                <SettingRow label="Imię" hint="Wyświetlana nazwa">
                                    <div className="flex flex-col items-end gap-1">
                                        <Input
                                            {...field}
                                            id="current-user-edit-form-name"
                                            aria-invalid={fieldState.invalid}
                                            placeholder="Jan Kowalski"
                                            autoComplete="off"
                                            className="w-52"
                                        />
                                        {fieldState.invalid && (
                                            <FieldError errors={[fieldState.error]}/>
                                        )}
                                    </div>
                                </SettingRow>
                            )}
                        />
                        <Controller
                            name="email"
                            control={form.control}
                            render={({field, fieldState}) => (
                                <SettingRow label="E-mail" hint="Adres email (niezmienialny)">
                                    <Input
                                        {...field}
                                        id="current-user-edit-form-email"
                                        aria-invalid={fieldState.invalid}
                                        placeholder="test@example.com"
                                        autoComplete="off"
                                        disabled
                                        className="w-52"
                                    />
                                </SettingRow>
                            )}
                        />
                    </SettingCard>
                </form>
                <div className="mt-4 flex justify-end">
                    <Button
                        type="submit"
                        form="current-user-edit-form"
                        disabled={isPending}
                        size="sm"
                    >
                        {isPending ? 'Zapisywanie...' : 'Zapisz profil'}
                    </Button>
                </div>
            </section>

            <section>
                <p className="text-[11px] font-semibold text-muted-foreground uppercase tracking-[0.06em] mb-3">
                    Bezpieczeństwo
                </p>
                <SettingCard>
                    <SettingRow label="Zmiana hasła" hint="Funkcja w przygotowaniu">
                        <Button variant="outline" size="sm" disabled>Zmień hasło</Button>
                    </SettingRow>
                </SettingCard>
            </section>
        </div>
    )
}