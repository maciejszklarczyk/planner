# Testy w projekcie

## Uruchamianie testów

### Wszystkie testy
```bash
vendor/bin/phpunit
```

### Konkretny plik testowy
```bash
vendor/bin/phpunit tests/Functional/Controller/AuthControllerTest.php
```

### Z ładnym formatowaniem
```bash
vendor/bin/phpunit --testdox
```

### Z coverage (wymaga xdebug)
```bash
vendor/bin/phpunit --coverage-html coverage/
```

## Struktura testów

- `tests/DatabaseTestCase.php` - klasa bazowa dla testów z bazą danych
- `tests/Functional/Controller/` - testy funkcjonalne kontrolerów
- `tests/bootstrap.php` - bootstrap dla testów

## Konfiguracja

- `phpunit.xml.dist` - konfiguracja PHPUnit
- `.env.test` - zmienne środowiskowe dla testów (używa SQLite)

## Istniejące testy

### AuthControllerTest (10 testów)
- ✅ Login z poprawnymi danymi
- ✅ Login z błędnym hasłem
- ✅ Login z błędnym emailem
- ✅ Login bez danych
- ✅ Login bez email
- ✅ Login bez hasła
- ✅ Endpoint /auth/me bez autentykacji
- ✅ Endpoint /auth/me z autentykacją
- ✅ Login zwykłego użytkownika
- ✅ Logout

