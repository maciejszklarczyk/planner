'use client'

import {UsersTable} from "@/components/users/UsersTable";
import {columns} from "@/components/users/UsersTableColumn";
import {useAuth} from "@/hooks/useAuth";
import {GroupsTable} from "@/components/users/GroupsTable";
import {groupColumns} from "@/components/users/GroupsTableColumn";
import {Tabs, TabsContent, TabsList, TabsTrigger} from "@/components/ui/tabs";
import {Button} from "@/components/ui/button";
import {IconPlus} from "@tabler/icons-react";
import {useState} from "react";
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

const invitationSchema = z.object({
    email: z.string().email('Nieprawidłowy email'),
});

type InvitationFormData = z.infer<typeof invitationSchema>;

export function AdminUserSettingsWrapper() {
    const {user, isLoading} = useAuth();
    const [activeTab, setActiveTab] = useState('users');
    const {mutate: invite} = useInvite();
    const {register, handleSubmit, formState: {errors}} = useForm<InvitationFormData>({
        resolver: zodResolver(invitationSchema),
    });


    if (isLoading) {
        return (
            <div className="flex items-center justify-center py-10">
                <div className="h-8 w-8 animate-spin rounded-full border-4 border-gray-200 border-t-orange-600"/>
            </div>
        );
    }

    const isAdmin = user?.roles?.includes('ROLE_ADMIN');

    if (!isAdmin) {
        return (
            <div className="flex flex-col items-center justify-center py-20">
                <h2 className="text-2xl font-bold mb-4">Brak dostępu</h2>
                <p className="text-gray-500">Nie masz uprawnień administratora, aby wyświetlić tę stronę.</p>
            </div>
        );
    }

    const onSubmit = (data: InvitationFormData) => {
        invite(data, {
            onSuccess: () => {
                toast.success('Wysłano', {
                    description: 'Użytkownik ma teraz 24h na zalogownaie sie.',
                });
            },
            onError: () => {
                toast.error('Błąd', {
                    description: 'jebło',
                });
            },
        });
    };

    return (

        <>
            <Tabs defaultValue="users" onValueChange={setActiveTab}
                  className="pt-10 w-full flex-col justify-start gap-6">
                <div className="flex justify-between">
                    <TabsList variant="line" className="">
                        <TabsTrigger value="users">Użytkownicy</TabsTrigger>
                        <TabsTrigger value="groups">Grupy</TabsTrigger>
                    </TabsList>
                    {activeTab === 'users' && (
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
                                    <FieldGroup className={"pt-5"}>
                                        <Field>
                                            <Label htmlFor="invitation-email">E-Mail</Label>
                                            <Input
                                                id="invitation-email"
                                                type="email"
                                                placeholder="name@example.com"
                                                {...register('email')}
                                            />
                                            {errors.email &&
                                                <p className="text-sm text-red-500">{errors.email.message}</p>}
                                        </Field>
                                    </FieldGroup>
                                    <DialogFooter className={"pt-5"}>
                                        <DialogClose asChild>
                                            <Button variant="outline" type="button">Anuluj</Button>
                                        </DialogClose>
                                        <Button type="submit">Wyślij zaproszenie</Button>
                                    </DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>
                    )}
                </div>
                <TabsContent value="users">
                    <div className="container mx-auto">
                        <div className="mb-6">
                            <h2 className="text-2xl font-bold">Zarządzanie użytkownikami</h2>
                            <p className="text-gray-500">Lista wszystkich użytkowników w systemie</p>
                        </div>
                        <UsersTable columns={columns}/>
                    </div>
                </TabsContent>
                <TabsContent value="groups">
                    <div className="container mx-auto">
                        <div className="mb-6">
                            <h2 className="text-2xl font-bold">Zarządzanie Grupami</h2>
                            <p className="text-gray-500">Lista wszystkich grup w systemie</p>
                        </div>
                        <GroupsTable columns={groupColumns}/>
                    </div>
                </TabsContent>
            </Tabs>
        </>
    );
}
