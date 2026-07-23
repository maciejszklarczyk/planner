# 01 - Wizja Projektu

## Problem do rozwiązania

Za każdym razem gdy wyjeżdżamy na wycieczkę, ktoś płaci za hotel, ktoś za paliwo,
ktoś za jedzenie - potem przez tygodnie rozliczamy się na Messengerze i zawsze ktoś
o czymś zapomina.
Podczas planowania wycieczki istotne dla nas jest, żeby mieć link, adres i kontakt do miejsca w które jedziemy.
Przed wyjazdem chcemy mieć możliwość zaplanowania wycieczki, aby nie zapomnieć o czymś. Skompletować menu, 
listę zakupów, listę atrakcji, czy biuro podróży w przypadku gdy jedziemy samochodami. 
W przypadku innego trasportu fajnie mieć możliwość zapisania godzin odjazdu/odlotu i peronu albo adresu lotniska.
Zdarza się, że potrzeba rozliczyć wypad na miasto, wtedy informacje o noclegach i transporcie nie są potrzebne, wystarczy tylko system rozliczania i data.

## Cel projektu

- Stworzyć prosty sposób na śledzenie wydatków podczas wycieczki i automatyczne wyliczanie kto komu ile jest winien."
- Umożliwić trzymanie danych o noclegu i danych o transporcie w jednym miejscu.
- Umożliwić łatwe zarządzanie planem wycieczki.
- Umożliwić stworzenie planu posiłków i listy zakupów z kategoriami.
- Wysyłanie przypominajek o wycieczce i po wycieczce w celu rozliczenia na maila.
- Umożliwić tworzenie grup znajomych tak, żeby kilka grup mogło korzystać z aplikacji.

## Grupa docelowa

- Grupy znajomych (5-15 osób)
- Wiek: 20-30 lat
- Częstotliwość wycieczek: 2-5 rocznie
- Technicznie zaawansowani ale i tak aplikacja powinna być prosta.

## Kluczowe założenia

- [ ] Aplikacja musi być prosta w użyciu
- [ ] Rozliczenia muszą być sprawiedliwe i przejrzyste
- [ ] Każdy uczestnik musi mieć dostęp do historii
- [ ] Użytkownicy innych grup nie powinni widzieć wycieczek innych grup
- [ ] Użytkownicy muszą mieć znajomych, których będą mogli dodać do wycieczki
- [ ] Aplikacja musi poprawnie zarządzać sesjami, być zabezpieczona i aktualizować listę zakupów na bieżąco
- [ ] Aplikacja musi mieć prosty sposób logowania i dobrą wersję mobilną
- [ ] Aplikacja musi mieć rejestrację na podstawie zaproszeń, bez głównego formularza - zalogowany użytkownik może wysłać zaproszenie do dołączenia do aplikacji.
- [ ] Udział w wydarzeniu musi być potwierdzony

## Czego NIE robimy (out of scope)

- Integracja z bankami (za skomplikowane na MVP)
- Planowanie tras (są do tego lepsze aplikacje)
- Rezerwacje hoteli (trzymanie tylko danych o noclegu)

## Inspiracje i konkurencja

| Aplikacja | Co podoba                         | Co nie podoba                                                                 |
|-----------|-----------------------------------|-------------------------------------------------------------------------------|
| Splitwise | Uproszczone rozliczenia, prostota | Koszt, osobna aplikacja                                                       |
| splitpro  | Prostota działania                | Konieczność utrzymania drugiej aplikacji, brak możliwości dopasowania pod nas |
| Tripsy    | Wizualne zaplanowanie wycieczki   | -                                                                             |

## Wizja sukcesu

- Nasza grupa używa aplikacji na każdej wycieczce i nikt nie musi już ręcznie liczyć kto komu ile jest winien.
- Nasza grupa używa aplikacji do zaplanowania wycieczek i wypadów na miasto


---

## Powiązane dokumenty

- [Lista funkcjonalności](02-features.md)
- [User Stories](03-user-stories.md)
- [Plan implementacji](04-implementation-plan.md)
