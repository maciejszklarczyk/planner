'use client'

import {Button} from "@/components/ui/button";
import {
    Dialog, DialogClose,
    DialogContent,
    DialogDescription, DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger
} from "@/components/ui/dialog";
import {Field, FieldGroup} from "@/components/ui/field";
import {Label} from "@/components/ui/label";
import {Input} from "@/components/ui/input";
import {toast} from "sonner";
import {z} from "zod";
import {useInvite} from "@/hooks/useInvite";
import {useForm} from "react-hook-form";
import {zodResolver} from "@hookform/resolvers/zod";
import {IconPlus} from "@tabler/icons-react";

const invitationSchema = z.object({
    email: z.string().email('Nieprawidłowy email'),
});

type InvitationFormData = z.infer<typeof invitationSchema>;

export function InviteUserDialog() {
    const {mutate: invite} = useInvite();
    const {register, handleSubmit, formState: {errors}} = useForm<InvitationFormData>({
        resolver: zodResolver(invitationSchema),
    });

    const onSubmit = (data: InvitationFormData) => {
        invite(data, {
            onSuccess: () => {
                toast.success('Wysłano', {
                    description: 'Użytkownik ma teraz 24h na zalogowanie się.',
                });
            },
            onError: () => {
                toast.error('Błąd', {
                    description: 'Nie udało się wysłać zaproszenia.',
                });
            },
        });
    };

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm" type="button">
                    <IconPlus/>
                    <span className="hidden lg:inline">Dodaj użytkownika</span>
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={handleSubmit(onSubmit)}>
                    <DialogHeader>
                        <DialogTitle>Wyślij zaproszenie.</DialogTitle>
                        <DialogDescription>
                            Na podany email zostanie wysłane zaproszenie do dołączenia. Zaproszenie
                            będzie ważne 24h.
                        </DialogDescription>
                    </DialogHeader>
                    <FieldGroup className="pt-5">
                        <Field>
                            <Label htmlFor="invitation-email">E-Mail</Label>
                            <Input
                                id="invitation-email"
                                type="email"
                                placeholder="name@example.com"
                                {...register('email')}
                            />
                            {errors.email && <p className="text-sm text-red-500">{errors.email.message}</p>}
                        </Field>
                    </FieldGroup>
                    <DialogFooter className="pt-5">
                        <DialogClose asChild>
                            <Button variant="outline" type="button">Anuluj</Button>
                        </DialogClose>
                        <Button type="submit">Wyślij zaproszenie</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
