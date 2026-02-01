"use client"

import {ColumnDef} from "@tanstack/react-table"
import {User} from "@/types/auth";
import {Pencil, Trash2} from "lucide-react"
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

// This type is used to define the shape of our data.
// You can use a Zod schema here if you want.

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
        id: "edit",
        header: "Edycja",
        size: 40,
        cell: ({row}) => {
            const user = row.original

            return (
                <Button
                    variant="ghost"
                    size="icon"
                    onClick={() => {
                        // Tutaj dodaj logikę edycji użytkownika
                        console.log("Edytuj użytkownika:", user.id)
                    }}
                >
                    <Pencil className="h-4 w-4"/>
                </Button>
            )
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