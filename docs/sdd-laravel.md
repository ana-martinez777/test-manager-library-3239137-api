# Manager Library · API en Laravel guiada por el spec

Este documento explica **cómo se tradujo el spec** (`test-manager-library-3239137-docs/spec-.net`) a PHP con Laravel y
**con qué evidencia** se da por hecha cada tarea (artículo XII). El negocio —historias, criterios, reglas, contrato y
modelo de datos— se respeta tal cual; solo cambia la tecnología.

## 1. Traducción de decisiones técnicas (plan.md §1)

| # | Spec (.NET) | Laravel | Por qué |
|---|---|---|---|
| D-01/D-02 | .NET 8, Minimal API | PHP 8.4, Laravel 13 | Stack del reto |
| D-03 | EF Core + migraciones | Migraciones de Laravel con **SQL explícito** | Control exacto de `data-model.md`: esquema `library`, nombres de restricciones, índice parcial y **ningún `DEFAULT`** |
| D-04 | Testigo de versión + `CHECK` | `UPDATE … WHERE available_copies >= 1` (escritura condicionada) + `ck_book_available_range` | Concurrencia optimista sin columna extra (el modelo de datos no tiene columna de versión): si la fila cambió, el `UPDATE` afecta 0 filas, se reintenta una vez y luego 409 |
| D-05 | `Clock` | Puerto `Clock` (`SystemClock` en producción, `FixedClock` en pruebas) | Probar vencimientos sin esperar 14 días |
| D-06 | Puerto de lectura | `ReportReader` → `PgReportReader` (una consulta agregada) | ADR-004 |
| D-08 | Disco / S3 | `CoverStorage` → `LocalDiskCoverStorage` | Clave opaca, nunca ruta |
| D-09 | BCrypt 12 | `password_hash` BCrypt 12 detrás de `PasswordHasher` | Artículo IX |
| D-10 | Admin desde entorno | Comando `php artisan library:bootstrap-admin` al arrancar | La base no puede producir el hash |
| D-11 | JWT HS256 8 h | `JwtTokenService` propio (HS256, `sub` + `role`, 8 h) | Sin dependencias extra; verificado por la prueba de contrato |
| D-12 | xUnit + NetArchTest | PHPUnit (Unit, Architecture, Integration) + `scripts/contract-test.mjs` | Pirámide de pruebas de plan §3 |

## 2. Arquitectura hexagonal (architecture.md §2)

```text
src/Library/
├── Domain/                      # Book, Loan, LoanItem, Member, User, Category; Isbn, Quantity, DateRange, DocumentNumber
├── Application/
│   ├── Ports/                   # BookRepository, LoanRepository, MemberRepository, UserRepository, CategoryRepository,
│   │                            # ReportReader, Clock, IdGenerator, PasswordHasher, TokenIssuer, TokenVerifier, CoverStorage
│   ├── Services/                # un caso de uso por clase (LendBooksService, ReturnItemService, …)
│   └── ReadModels/              # DTO de lectura (Page, BookView, OverdueLine, MostBorrowedRow, …)
├── Adapters/
│   ├── Inbound/Http/            # controladores, middleware de token y rol, ErrorRenderer (cuerpo de error único)
│   └── Outbound/                # Persistence (PostgreSQL vía PDO), Security, Storage, System
└── Bootstrap/
    ├── Composition/PortBindings.php   # ÚNICO punto de composición (artículo III)
    └── Console/BootstrapAdminCommand.php
```

- El dominio no importa Laravel, PDO ni otras capas (artículo I). La aplicación solo conoce el dominio (artículo II).
- Los repositorios usan el PDO de la conexión de Laravel con SQL explícito: así las reglas de concurrencia y el reporte
  sobre valores congelados quedan visibles y verificables.
- Los errores de negocio se traducen a HTTP **solo** en `ErrorRenderer` (architecture.md §5).

## 3. Evidencia por tarea

| Tarea | Evidencia ejecutable |
|---|---|
| T-01 | `GET /api/v1/health` → `{"status":"ok"}`; `docker compose up` en `infra` |
| T-02 | `database/migrations/…create_library_schema.php`; consultas de `data-model.md` §10; `PostgresAdaptersTest::test_no_column_has_default` |
| T-03 | `tests/Unit/Domain/ValueObjectsTest.php` |
| T-04 | `tests/Unit/Domain/BookTest.php` |
| T-05 | `tests/Unit/Domain/LoanTest.php` (reloj controlado: open, overdue, closed) |
| T-06 | `tests/Unit/Domain/MemberAndUserTest.php` (`"Ana "` → `ana`; rol admin rechazado) |
| T-07 | `tests/Unit/Application/AuthServicesTest.php` |
| T-08 | `tests/Unit/Application/BookServicesTest.php` |
| T-09, T-10 | `tests/Unit/Application/LoanServicesTest.php` |
| T-11 | `ReportServiceTest.php` + `PostgresAdaptersTest::test_report_groups_by_frozen_values_after_rename` (ADR-004) |
| T-12 | `tests/Integration/PostgresAdaptersTest.php` (PostgreSQL real, cada prueba se deshace) |
| T-13 | `PostgresAdaptersTest::test_stale_copy_count_is_rejected_by_the_engine` + carrera real en `scripts/contract-test.mjs` |
| T-14 | `contract-test.mjs`: token de 8 h con `sub`/`role`; ninguna respuesta contiene `password` |
| T-15 | `contract-test.mjs`: PNG de 2 MB → 200; 3 MB → 422; GIF disfrazado → 422; sin portada → `null` |
| T-16 | `contract-test.mjs` recorre todas las rutas y los códigos de `api-contract.md` §2 |
| T-17 | Arranque con base vacía → migraciones → `library:bootstrap-admin` → login como admin en `contract-test.mjs` |
| T-18 | Migración `…add_domain_only_checks.php`; `PostgresAdaptersTest::test_engine_enforces_checks` |
| T-19 | `tests/Architecture/LayerDependenciesTest.php` (incluye una prueba de que el detector sí detecta) |

Límite conocido: `MEMBER_HAS_OVERDUE` (CA-06.5) se prueba con reloj falso en `LoanServicesTest`; por HTTP no se puede
sin esperar 14 días.

Códigos fuera de la tabla del contrato, usados solo para mantener el cuerpo único (RNF-4) en casos que el contrato no
nombra: `METHOD_NOT_ALLOWED` (405) e `INTERNAL_ERROR` (500, sin detalles internos).

## 4. Cómo ejecutar

```bash
# 1. Levantar (desde el repo infra)
cp .env.example .env        # cambia las claves
docker compose up --build

# 2. Pruebas (en otra terminal, desde el repo infra)
docker compose exec api php artisan test --testsuite=Unit,Architecture,Integration

# 3. Contrato y concurrencia (desde el repo api, con la API levantada)
ADMIN_USERNAME=admin ADMIN_PASSWORD='la-de-tu-.env' node scripts/contract-test.mjs
```
