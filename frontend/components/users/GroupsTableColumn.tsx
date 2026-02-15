"use client"

import {ColumnDef} from "@tanstack/react-table"
import {Group} from "@/types/groups";
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

function DeleteGroupButton({ group }: { group: Group }) {
    const queryClient = useQueryClient();

    const deleteGroupMutation = useMutation({
        mutationFn: (groupId: number) =>
            api.delete(`/admin/groups/${groupId}`),
        onSuccess: () => {
            toast.success('Grupa usunięta', {
                description: 'Grupa została pomyślnie usunięta',
            });
            queryClient.invalidateQueries({ queryKey: ['admin', 'groups'] });
        },
        onError: (error) => {
            toast.error('Błąd', {
                description: 'Nie udało się usunąć grupy',
            });
            console.error('Delete error:', error);
        },
    });

    const handleDelete = () => {
        deleteGroupMutation.mutate(group.id);
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
                    <AlertDialogTitle>Czy na pewno chcesz usunąć grupę?</AlertDialogTitle>
                    <AlertDialogDescription>
                        Ta akcja jest nieodwracalna. Grupa {group.name} zostanie trwale usunięta.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Anuluj</AlertDialogCancel>
                    <AlertDialogAction
                        onClick={handleDelete}
                        disabled={deleteGroupMutation.isPending}
                    >
                        {deleteGroupMutation.isPending ? 'Usuwanie...' : 'Usuń'}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

export const groupColumns: ColumnDef<Group>[] = [
    {
        accessorKey: "id",
        header: "ID",
    },
    {
        accessorKey: "name",
        header: "Nazwa",
    },
    {
        accessorKey: "description",
        header: "Opis",
    },
    {
        accessorKey: "membersCount",
        header: "Liczba członków",
    },
    {
        id: "edit",
        header: "Edycja",
        size: 40,
        cell: ({row}) => {
            const group = row.original

            return (
                <Button
                    variant="ghost"
                    size="icon"
                    onClick={() => {
                        console.log("Edytuj grupę:", group.id)
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
            const group = row.original
            return <DeleteGroupButton group={group} />
        },
    },
]
