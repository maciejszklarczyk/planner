"use client";

import { ColumnDef } from "@tanstack/react-table";
import { Group } from "@/types/groups";
import { Pencil, Trash2, UserPlus } from "lucide-react";
import { Button } from "@/components/ui/button";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from "@/components/ui/alert-dialog";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { useRef, useState } from "react";
import { useGroupMembers } from "@/hooks/useGroupMembers";
import { useRemoveGroupMember } from "@/hooks/useRemoveGroupMember";
import { useAddGroupMember } from "@/hooks/useAddGroupMember";
import { useSearchUsers } from "@/hooks/useSearchUsers";
import { useDeleteGroup } from "@/hooks/useDeleteGroup";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";

function AddMemberDropdown({
  groupId,
  onAdd,
}: {
  groupId: number;
  onAdd: () => void;
}) {
  const [search, setSearch] = useState("");
  const [open, setOpen] = useState(false);
  const { data, isFetching } = useSearchUsers(search, groupId, true);
  const { mutate: addMember, isPending } = useAddGroupMember(groupId);
  const inputRef = useRef<HTMLInputElement>(null);

  const results = data?.data ?? [];

  function handleAdd(userId: number) {
    addMember(userId, {
      onSuccess: () => {
        setSearch("");
        setOpen(false);
        onAdd();
      },
    });
  }

  return (
    <div className="relative">
      <div className="flex gap-2">
        <Input
          ref={inputRef}
          placeholder="Szukaj użytkownika..."
          value={search}
          onChange={(e) => {
            setSearch(e.target.value);
            setOpen(e.target.value.length >= 2);
          }}
          onBlur={() => setTimeout(() => setOpen(false), 150)}
          onFocus={() => search.length >= 2 && setOpen(true)}
          className="h-8 text-sm"
        />
      </div>
      {open && (
        <ul className="absolute z-50 mt-1 w-full rounded-md border bg-popover shadow-md">
          {isFetching ? (
            <li className="flex justify-center py-3">
              <Skeleton className="size-4 rounded-full" />
            </li>
          ) : results.length === 0 ? (
            <li className="px-3 py-2 text-sm text-muted-foreground">
              Brak wyników
            </li>
          ) : (
            results.map((user) => (
              <li key={user.id}>
                <button
                  type="button"
                  disabled={isPending}
                  onMouseDown={() => handleAdd(user.id)}
                  className="flex w-full items-center gap-2 px-3 py-2 text-sm hover:bg-accent"
                >
                  <UserPlus className="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                  <span className="font-medium">{user.name || user.email}</span>
                  <span className="ml-1 text-muted-foreground">
                    {user.email}
                  </span>
                </button>
              </li>
            ))
          )}
        </ul>
      )}
    </div>
  );
}

function EditGroupModal({
  group,
  open,
  onOpenChange,
}: {
  group: Group;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}) {
  const { data, isLoading } = useGroupMembers(group.id, open);
  const { mutate: removeGroupMember, isPending } = useRemoveGroupMember(
    group.id,
  );

  const members = data?.data ?? [];
  const isOnlyMember = members.length === 1;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle>{group.name}</DialogTitle>
        </DialogHeader>
        <div className="mt-2 flex flex-col gap-4">
          <div>
            <p className="text-sm font-medium mb-2">Dodaj członka</p>
            <AddMemberDropdown groupId={group.id} onAdd={() => {}} />
          </div>
          <div>
            <p className="text-sm font-medium mb-3">
              Członkowie ({group.membersCount})
            </p>
            {isLoading ? (
              <div className="flex justify-center py-6">
                <Skeleton className="size-6 rounded-full" />
              </div>
            ) : members.length === 0 ? (
              <p className="text-sm text-muted-foreground text-center py-4">
                Brak członków w tej grupie.
              </p>
            ) : (
              <ul className="divide-y divide-border rounded-md border">
                {members.map((membership) => {
                  const canRemove =
                    membership.role !== "owner" && !isOnlyMember;
                  return (
                    <li
                      key={membership.id}
                      className="flex items-center justify-between px-3 py-2 text-sm"
                    >
                      <div>
                        <span className="font-medium">
                          {membership.user.name || membership.user.email}
                        </span>
                        <span className="ml-2 text-muted-foreground">
                          {membership.user.email}
                        </span>
                        <Badge
                          className={"capitalize ml-2"}
                          variant={
                            membership.role == "owner" ? "default" : "outline"
                          }
                        >
                          {membership.role}
                        </Badge>
                      </div>
                      <div className="flex items-center gap-2">
                        {canRemove && (
                          <Button
                            variant="ghost"
                            size="icon"
                            disabled={isPending}
                            onClick={() =>
                              removeGroupMember(membership.user.id)
                            }
                          >
                            <Trash2 data-icon className="text-destructive" />
                          </Button>
                        )}
                      </div>
                    </li>
                  );
                })}
              </ul>
            )}
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}

function EditGroupButton({ group }: { group: Group }) {
  const [open, setOpen] = useState(false);

  return (
    <>
      <Button variant="ghost" size="icon" onClick={() => setOpen(true)}>
        <Pencil data-icon />
      </Button>
      <EditGroupModal group={group} open={open} onOpenChange={setOpen} />
    </>
  );
}

function DeleteGroupButton({ group }: { group: Group }) {
  const { mutate: deleteGroup, isPending } = useDeleteGroup();

  return (
    <AlertDialog>
      <AlertDialogTrigger asChild>
        <Button size="icon" variant="ghost">
          <Trash2 data-icon className="text-destructive" />
        </Button>
      </AlertDialogTrigger>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Czy na pewno chcesz usunąć grupę?</AlertDialogTitle>
          <AlertDialogDescription>
            Ta akcja jest nieodwracalna. Grupa {group.name} zostanie trwale
            usunięta.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>Anuluj</AlertDialogCancel>
          <AlertDialogAction
            onClick={() => deleteGroup(group.id)}
            disabled={isPending}
          >
            {isPending ? "Usuwanie..." : "Usuń"}
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
    cell: ({ row }) => {
      const group = row.original;
      return <EditGroupButton group={group} />;
    },
  },
  {
    id: "delete",
    header: "Usuń",
    size: 40,
    cell: ({ row }) => {
      const group = row.original;
      return <DeleteGroupButton group={group} />;
    },
  },
];
