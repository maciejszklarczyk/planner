# 02 - Lista Funkcjonalności

## Legenda priorytetów

- **P0** - Must have (MVP)
- **P1** - Should have (v1.0)
- **P2** - Nice to have (przyszłość)

---

## Zarządzanie wycieczkami

| Feature | Opis                                                                                          | Priorytet | Notatki |
|---------|-----------------------------------------------------------------------------------------------|-----------|---------|
| Tworzenie wycieczki | Początkowy formularz ograniczony do minimum, możliwość dodania informacji w późniejszym kroku | P0        | |
| Edycja szczegółów wycieczki | Dostępna tylko dla głównego założyciela                                                       | P0        | |
| Zapraszanie uczestników | Dostępne tylko dla głównego założyciela                                                       | P0        | |
| Archiwizacja wycieczki | Na start archiwizacja tydzień po zakończeniu, nadal widoczna ale ze statusem archiwalna       | P2        | |

## Zarządzanie uczestnikami

| Feature | Opis                                                                                                                                                                                   | Priorytet | Notatki |
|---------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|-----------|---------|
| Dodawanie uczestników | Dodawanie do aplikacji na podstawie zaproszenia mailowego, a do wycieczki wybór z listy lub zaproszenie mailowe przez organizatora                                                     | P0        | |
| Role (admin/uczestnik) | Jedno główne konto admina, który będzie mógł edytować wszystko. Rola użytkownik dla reszty. Założyciel wycieczki powinien mieć dodatkową rolę dającą mu uprawnienia do danej wycieczki | P0        | |
| Profil użytkownika | Na start wystarczy edycja imienia i nazwiska oraz zmiana hasła.                                                                                                                        | P2        | |

## Wydatki i rozliczenia

| Feature | Opis                                                                                  | Priorytet | Notatki |
|---------|---------------------------------------------------------------------------------------|-----------|---------|
| Dodawanie wydatku | Tylko dla uczestników konkretnej wycieczki, dla wszystkich albo dla wybranych z listy | P0        | |
| Kategorie wydatków | Na razie nie potrzebne                                                                | P2        | |
| Podział równy | Pomiędzy wszystkich uczestników                                                       | P0        | |
| Podział niestandardowy | Pomiędzy uczestników wybranych z listy                                                | P0        | |
| Podsumowanie salda | Podstawowe informacje                                                                 | P0        | |
| Optymalizacja przelewów | Wymagana w celu uniknięcia kilku przelewów na np 2 zł                                 | P1        | |

## Powiadomienia

| Feature | Opis                                             | Priorytet | Notatki |
|---------|--------------------------------------------------|-----------|---------|
| Push notifications |                                                  |           | P2      |
| Przypomnienia o rozliczeniu | Wysyłane na drugi dzień po zakończeniu wycieczki | P2        |         |
| Email z podsumowaniem | Wysyłany po zakończeniu wycieczki                | P2        |         |

## Raporty i historia

| Feature | Opis                                                                               | Priorytet | Notatki |
|---------|------------------------------------------------------------------------------------|-----------|---------|
| Historia wydatków | Dla konkretnej wycieczki powinna być dostępna historia kto, ile i za kogo zapłacił | P0        | |
| Eksport do PDF/Excel | Nie jest teraz potrzebny                                                           | P2        | |
| Statystyki | Na razie niepotrzebne                                                              | P2        | |

---

## Podsumowanie MVP (P0)

1. Możliwość tworzenia wycieczki/eventu.
2. Możliwość dodawania uczestników poprzez mail i do wycieczki.
3. Możliwość dodawania wydatków do wycieczki.
4. Wyświetlanie historii wydatków dla konkretnej wycieczki.
5. Wyświetlanie podsumowania wydatków dla konkretnej wycieczki.

---

## Powiązane dokumenty

- [Wizja projektu](01-vision.md)
- [User Stories](03-user-stories.md)
- [Plan implementacji](04-implementation-plan.md)
