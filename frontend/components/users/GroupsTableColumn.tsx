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
import {useGroupMembers} from "@/hooks/useGroupMembers";
import {useRemoveGroupMember} from "@/hooks/useRemoveGroupMember";
import {useDeleteGroup} from "@/hooks/useDeleteGroup";

function EditGroupModal({ group, open, onOpenChange }: { group: Group; open: boolean; onOpenChange: (open: boolean) => void }) {
    const { data, isLoading } = useGroupMembers(group.id, open);
    const { mutate: removeGroupMember, isPending } = useRemoveGroupMember(group.id);

    const members = data?.data ?? [];
    const isOnlyMember = members.length === 1;

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
                    ) : members.length === 0 ? (
                        <p className="text-sm text-muted-foreground text-center py-4">
                            Brak członków w tej grupie.
                        </p>
                    ) : (
                        <ul className="divide-y divide-border rounded-md border">
                            {members.map((membership) => {
                                const canRemove = membership.role !== 'owner' && !isOnlyMember;
                                return (
                                    <li key={membership.id} className="flex items-center justify-between px-3 py-2 text-sm">
                                        <div>
                                            <span className="font-medium">
                                                {membership.user.name || membership.user.email}
                                            </span>
                                            <span className="ml-2 text-muted-foreground">
                                                {membership.user.email}
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <span className="text-xs text-muted-foreground capitalize">
                                                {membership.role}
                                            </span>
                                            {canRemove && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="h-7 w-7"
                                                    disabled={isPending}
                                                    onClick={() => removeGroupMember(membership.user.id)}
                                                >
                                                    <Trash2 className="h-3.5 w-3.5 text-destructive"/>
                                                </Button>
                                            )}
                                        </div>
                                    </li>
                                );
                            })}
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
    const { mutate: deleteGroup, isPending } = useDeleteGroup();

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
                        onClick={() => deleteGroup(group.id)}
                        disabled={isPending}
                    >
                        {isPending ? 'Usuwanie...' : 'Usuń'}
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
