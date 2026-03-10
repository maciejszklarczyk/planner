"use client"

import {ColumnDef} from "@tanstack/react-table"
import {User} from "@/types/auth";
import {MailCheck, Pencil, Trash2} from "lucide-react"
import {Button} from "@/components/ui/button"
import {
    AlertDialog, AlertDialogAction, AlertDialogCancel,
    AlertDialogContent, AlertDialogDescription, AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger
} from "@/components/ui/alert-dialog";
import {useMutation, useQueryClient} from "@tanstack/react-query";
import {api} from "@/lib/api";
import {toast} from "sonner";
import {useResendInvite} from "@/hooks/useResendInvite";
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import {useState} from "react";
import {Controller, useForm} from "react-hook-form";
import {zodResolver} from "@hookform/resolvers/zod";
import * as z from "zod";
import {Field, FieldError, FieldGroup, FieldLabel} from "@/components/ui/field";
import {Input} from "@/components/ui/input";

const editUserSchema = z.object({
    id: z.number(),
    name: z.string().min(5, "Nazwa musi mieć co najmniej 5 znaków.").max(32, "Nazwa może mieć maksymalnie 32 znaki."),
    email: z.email("Nieprawidłowy adres email."),
});

type EditUserFormValues = z.infer<typeof editUserSchema>;

function EditUserModal({ user, open, onOpenChange }: { user: User; open: boolean; onOpenChange: (open: boolean) => void }) {
    const queryClient = useQueryClient();

    const form = useForm<EditUserFormValues>({
        resolver: zodResolver(editUserSchema),
        defaultValues: {
            id: user.id,
            name: user.name,
            email: user.email,
        },
    });

    const updateUserMutation = useMutation({
        mutationFn: (data: EditUserFormValues) => api.put('/user', data),
        onSuccess: () => {
            toast.success('Dane zaktualizowane', {
                description: 'Dane użytkownika zostały pomyślnie zaktualizowane',
            });
            queryClient.invalidateQueries({ queryKey: ['admin', 'users'] });
            onOpenChange(false);
        },
        onError: () => {
            toast.error('Błąd', {
                description: 'Nie udało się zaktualizować danych użytkownika',
            });
        },
    });

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edytuj użytkownika</DialogTitle>
                </DialogHeader>
                <form id="edit-user-form" onSubmit={form.handleSubmit((data) => updateUserMutation.mutate(data))}>
                    <FieldGroup>
                        <Controller
                            name="name"
                            control={form.control}
                            render={({ field, fieldState }) => (
                                <Field data-invalid={fieldState.invalid}>
                                    <FieldLabel htmlFor="edit-user-name">Nazwa</FieldLabel>
                                    <Input
                                        {...field}
                                        id="edit-user-name"
                                        aria-invalid={fieldState.invalid}
                                        autoComplete="off"
                                    />
                                    {fieldState.invalid && <FieldError errors={[fieldState.error]}/>}
                                </Field>
                            )}
                        />
                        <Controller
                            name="email"
                            control={form.control}
                            render={({ field, fieldState }) => (
                                <Field data-invalid={fieldState.invalid}>
                                    <FieldLabel htmlFor="edit-user-email">Email</FieldLabel>
                                    <Input
                                        {...field}
                                        id="edit-user-email"
                                        aria-invalid={fieldState.invalid}
                                        autoComplete="off"
                                        disabled
                                    />
                                    {fieldState.invalid && <FieldError errors={[fieldState.error]}/>}
                                </Field>
                            )}
                        />
                        <Button
                            type="submit"
                            form="edit-user-form"
                            disabled={updateUserMutation.isPending}
                        >
                            {updateUserMutation.isPending ? 'Zapisywanie...' : 'Zapisz'}
                        </Button>
                    </FieldGroup>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function EditUserButton({ user }: { user: User }) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <Button
                variant="ghost"
                size="icon"
                onClick={() => setOpen(true)}
            >
                <Pencil className="h-4 w-4"/>
            </Button>
            <EditUserModal user={user} open={open} onOpenChange={setOpen}/>
        </>
    );
}

function ResendInviteButton({ user }: { user: User }) {
    const { mutate: resend, isPending } = useResendInvite();

    return (
        <Button
            size="icon"
            variant="ghost"
            disabled={isPending}
            onClick={() => resend({ email: user.email })}
            title="Wyślij zaproszenie ponownie"
        >
            <MailCheck className="h-4 w-4"/>
        </Button>
    );
}

function DeleteUserButton({ user }: { user: User }) {
    const queryClient = useQueryClient();

    const deleteUserMutation = useMutation({
        mutationFn: (userId: number) =>
            api.delete(`/user/${userId}`),
        onSuccess: () => {
            toast.success('Użytkownik usunięty', {
                description: 'Użytkownik został pomyślnie usunięty',
            });
            queryClient.invalidateQueries({ queryKey: ['admin', 'users'] });
        },
        onError: (error) => {
            toast.error('Błąd', {
                description: 'Nie udało się usunąć użytkownika',
            });
            console.error('Delete error:', error);
        },
    });

    const handleDelete = () => {
        deleteUserMutation.mutate(user.id);
    };

    return (
        <AlertDialog>
            <AlertDialogTrigger asChild>
                <Button size="icon" variant="ghost">
                    <Trash2 className="h-4 w-4 text-destructive"/>
                </Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Czy na pewno chcesz usunąć użytkownika?</AlertDialogTitle>
                    <AlertDialogDescription>
                        Ta akcja jest nieodwracalna. Użytkownik {user.name} ({user.email}) zostanie trwale usunięty.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Anuluj</AlertDialogCancel>
                    <AlertDialogAction
                        onClick={handleDelete}
                        disabled={deleteUserMutation.isPending}
                    >
                        {deleteUserMutation.isPending ? 'Usuwanie...' : 'Usuń'}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}


export const columns: ColumnDef<User>[] = [
    {
        accessorKey: "id",
        header: "ID",
    },
    {
        accessorKey: "email",
        header: "Email",
    },
    {
        accessorKey: "name",
        header: "Name",
    },
    {
        accessorKey: "status",
        header: "Status",
    },
    {
        id: "resend-invite",
        header: "",
        size: 40,
        cell: ({row}) => {
            const user = row.original;
            if (user.status !== 'new') return null;
            return <ResendInviteButton user={user}/>;
        },
    },
    {
        id: "edit",
        header: "Edycja",
        size: 40,
        cell: ({row}) => {
            const user = row.original
            return <EditUserButton user={user}/>
        },
    },
    {
        id: "delete",
        header: "Usuń",
        size: 40,
        cell: ({row}) => {
            const user = row.original
            return <DeleteUserButton user={user} />
        },
    },
]