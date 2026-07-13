"use client";

import { ColumnDef } from "@tanstack/react-table";
import { User } from "@/types/auth";
import { MailCheck, Pencil, Trash2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
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
import { useState } from "react";
import { Controller, useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import * as z from "zod";
import {
  Field,
  FieldError,
  FieldGroup,
  FieldLabel,
} from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { useResendInvite } from "@/hooks/useResendInvite";
import { useUpdateUser } from "@/hooks/useUpdateUser";
import { useDeleteUser } from "@/hooks/useDeleteUser";

const editUserSchema = z.object({
  id: z.number(),
  name: z
    .string()
    .min(5, "Nazwa musi mieć co najmniej 5 znaków.")
    .max(32, "Nazwa może mieć maksymalnie 32 znaki."),
  email: z.email("Nieprawidłowy adres email."),
});

type EditUserFormValues = z.infer<typeof editUserSchema>;

function EditUserModal({
  user,
  open,
  onOpenChange,
}: {
  user: User;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}) {
  const { mutate: updateUser, isPending } = useUpdateUser({
    onSuccess: () => onOpenChange(false),
  });

  const form = useForm<EditUserFormValues>({
    resolver: zodResolver(editUserSchema),
    defaultValues: {
      id: user.id,
      name: user.name,
      email: user.email,
    },
  });

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Edytuj użytkownika</DialogTitle>
        </DialogHeader>
        <form
          id="edit-user-form"
          onSubmit={form.handleSubmit((data) => updateUser(data))}
        >
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
                  <FieldLabel htmlFor="edit-user-email">Email</FieldLabel>
                  <Input
                    {...field}
                    id="edit-user-email"
                    aria-invalid={fieldState.invalid}
                    autoComplete="off"
                    disabled
                  />
                  {fieldState.invalid && (
                    <FieldError errors={[fieldState.error]} />
                  )}
                </Field>
              )}
            />
            <Button type="submit" form="edit-user-form" disabled={isPending}>
              {isPending ? "Zapisywanie..." : "Zapisz"}
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
      <Button variant="ghost" size="icon" onClick={() => setOpen(true)}>
        <Pencil data-icon />
      </Button>
      <EditUserModal user={user} open={open} onOpenChange={setOpen} />
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
      <MailCheck data-icon />
    </Button>
  );
}

function DeleteUserButton({ user }: { user: User }) {
  const { mutate: deleteUser, isPending } = useDeleteUser();

  return (
    <AlertDialog>
      <AlertDialogTrigger asChild>
        <Button size="icon" variant="ghost">
          <Trash2 data-icon className="text-destructive" />
        </Button>
      </AlertDialogTrigger>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>
            Czy na pewno chcesz usunąć użytkownika?
          </AlertDialogTitle>
          <AlertDialogDescription>
            Ta akcja jest nieodwracalna. Użytkownik {user.name} ({user.email})
            zostanie trwale usunięty.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>Anuluj</AlertDialogCancel>
          <AlertDialogAction
            onClick={() => deleteUser(user.id)}
            disabled={isPending}
          >
            {isPending ? "Usuwanie..." : "Usuń"}
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
    cell: ({ row }) => {
      const status = row.original.status;
      return (
        <Badge
          variant={status === "active" ? "default" : "secondary"}
          className="capitalize"
        >
          {status}
        </Badge>
      );
    },
  },
  {
    id: "resend-invite",
    header: "",
    size: 40,
    cell: ({ row }) => {
      const user = row.original;
      if (user.status !== "new") return null;
      return <ResendInviteButton user={user} />;
    },
  },
  {
    id: "edit",
    header: "Edycja",
    size: 40,
    cell: ({ row }) => {
      const user = row.original;
      return <EditUserButton user={user} />;
    },
  },
  {
    id: "delete",
    header: "Usuń",
    size: 40,
    cell: ({ row }) => {
      const user = row.original;
      return <DeleteUserButton user={user} />;
    },
  },
];
