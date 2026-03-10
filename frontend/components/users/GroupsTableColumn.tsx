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
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import {useState} from "react";
import {useMutation, useQueryClient} from "@tanstack/react-query";
import {api} from "@/lib/api";
import {toast} from "sonner";
import {useGroupMembers} from "@/hooks/useGroupMembers";

function EditGroupModal({ group, open, onOpenChange }: { group: Group; open: boolean; onOpenChange: (open: boolean) => void }) {
    const { data, isLoading } = useGroupMembers(group.id, open);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle>{group.name}</DialogTitle>
                </DialogHeader>
                <div className="mt-2">
                    <p className="text-sm font-medium mb-3">
                        Członkowie ({group.membersCount})
                    </p>
                    {isLoading ? (
                        <div className="flex justify-center py-6">
                            <div className="h-6 w-6 animate-spin rounded-full border-4 border-gray-200 border-t-orange-600"/>
                        </div>
                    ) : data?.data.length === 0 ? (
                        <p className="text-sm text-muted-foreground text-center py-4">
                            Brak członków w tej grupie.
                        </p>
                    ) : (
                        <ul className="divide-y divide-border rounded-md border">
                            {data?.data.map((membership) => (
                                <li key={membership.id} className="flex items-center justify-between px-3 py-2 text-sm">
                                    <div>
                                        <span className="font-medium">
                                            {membership.user.name || membership.user.email}
                                        </span>
                                        <span className="ml-2 text-muted-foreground">
                                            {membership.user.email}
                                        </span>
                                    </div>
                                    <span className="text-xs text-muted-foreground capitalize">
                                        {membership.role}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}

function EditGroupButton({ group }: { group: Group }) {
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
            <EditGroupModal group={group} open={open} onOpenChange={setOpen}/>
        </>
    );
}

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
            return <EditGroupButton group={group}/>
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
