# FinModalTableSelect for Filament

<img class="filament-hidden" alt="finity-labs-fin-modal-table-select" src="https://github.com/user-attachments/assets/8eabca4a-40bc-476a-95b4-f7c5f532ac66">

[![FILAMENT 4.x](https://img.shields.io/badge/FILAMENT-4.x-EBB304?style=flat-square)](https://filamentphp.com/docs/4.x/panels/installation)
[![FILAMENT 5.x](https://img.shields.io/badge/FILAMENT-5.x-EBB304?style=flat-square)](https://filamentphp.com/docs/5.x/panels/installation)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/finity-labs/fin-modal-table-select.svg?style=flat-square)](https://packagist.org/packages/finity-labs/fin-modal-table-select)
[![Tests](https://github.com/finity-labs/fin-modal-table-select/actions/workflows/tests.yml/badge.svg)](https://github.com/finity-labs/fin-modal-table-select/actions/workflows/tests.yml)
[![Code Style](https://github.com/finity-labs/fin-modal-table-select/actions/workflows/style.yml/badge.svg)](https://github.com/finity-labs/fin-modal-table-select/actions/workflows/style.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/finity-labs/fin-modal-table-select.svg?style=flat-square)](https://packagist.org/packages/finity-labs/fin-modal-table-select)
[![License](https://img.shields.io/packagist/l/finity-labs/fin-modal-table-select.svg?style=flat-square)](https://packagist.org/packages/finity-labs/fin-modal-table-select)

A drop-in replacement for Filament's native `ModalTableSelect` that shows what you've selected — and can push the selection into the rest of your form.

The stock component renders the selection as badges or comma-separated text. This one adds:

- **Nine display modes** — a table with columns inherited from your modal's `tableConfiguration()`, a stacked list with per-item remove, a card grid, a thumbnail strip, per-record badge colors/icons, text lists, an infolist card, your own Blade view per item, or nothing at all (selection-only). The mode resolves automatically from what you configure.
- **`fillsFields()`** — pick a company, get its name and tax number filled in, let the user overwrite the phone.
- **`fillsRepeater()` + `SelectedItemsRepeater`** — pick products, get one editable row each (quantity, price). Re-picking never wipes what the user edited, and deleting a row deselects its record.
- **`standalone()` + `standaloneRecords()`** — use the picker without an Eloquent relationship: records from a model query, or from a plain array (named routes, page classes, API data) with modal search and sort intact.

Everything user-facing stays stock Filament — the modal, the row entries, the repeater. This package only wires them together. All display modes support dark mode out of the box, and 58 locales ship with the package.

## Screenshots

**Pick records in a full Filament table** — search, sort, and filter inside the modal; the selection summary badge sits right on the field label:

<p align="center">
    <img src="https://github.com/user-attachments/assets/612cbda6-0d9a-481c-8dca-e1acbe16cf77" alt="The modal table select open over the invoice form, with rows checked" width="880">
</p>

**The invoice flow** — every picked product becomes an editable repeater row (`fillsRepeater()` + `SelectedItemsRepeater`). Re-picking keeps your edited quantities and prices; deleting a row deselects the product:

<p align="center">
    <img src="https://github.com/user-attachments/assets/848af614-d15b-416e-9a27-e1dca0150917" alt="Table-layout repeater filled from the selection, with editable quantity and price" width="880">
</p>

**Prefilled invoice header** — `fillsFields()` fills read-only entries and an editable phone from the picked company:

<p align="center">
    <img src="https://github.com/user-attachments/assets/48ae5f2f-bab3-44c4-9c2b-ed8c183c6121" alt="Company picker prefilling name, tax number, and an editable phone" width="880">
</p>

**Nine ways to show the selection** — badges with `displayLimit()`, stacked lists with per-item remove, inherited-column tables, text lists, per-record badge colors, card grids, thumbnails, and your own Blade view per item:

<p align="center">
    <img src="https://github.com/user-attachments/assets/08c6e393-9d1a-4071-a1be-77b137c3f39c" alt="Badges with display limit, stacked list, and inherited-column table displays" width="49%">
    <img src="https://github.com/user-attachments/assets/d90cb4a5-c896-4181-b10c-afca0fca8b52" alt="Text lists, per-record badges, card grid, thumbnails, and custom item views" width="49%">
</p>

## Quick example

```php
use FinityLabs\FinModalTableSelect\Components\ModalTableSelect;

// Selected records as a table, columns inherited from the modal:
ModalTableSelect::make('categories')
    ->relationship('categories', 'name')
    ->multiple()
    ->tableConfiguration(CategoriesTable::class)
    ->displayAsTable()

// Invoice lines: pick products, edit quantity/price per row:
ModalTableSelect::make('product_picker')
    ->standalone(Product::class, 'name')
    ->multiple()
    ->tableConfiguration(ProductsTable::class)
    ->selectionOnly()
    ->fillsRepeater('items', fn (Product $record): array => [
        'product_id' => $record->getKey(),
        'name'       => $record->name,
        'unit_price' => $record->default_price,
        'quantity'   => 1,
    ], keyAttribute: 'product_id')
```

## Array-backed records

The picker can run over plain arrays — no Eloquent at all. The modal table still searches the searchable columns and sorts the sortable ones, the selected keys land in the field state, and every display mode works unchanged. The records closure gets Filament's dependency injection, so it can scope the list by sibling form state:

```php
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use FinityLabs\FinModalTableSelect\Components\ModalTableSelect;
use Illuminate\Support\Facades\Route;

class RoutesTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([          // columns and filters only — no query;
            TextColumn::make('label')     // the component supplies the records itself
                ->searchable()
                ->sortable(),
            TextColumn::make('route')->searchable(),
            TextColumn::make('uri'),
            TextColumn::make('panel')->badge(),
        ]);
    }
}

ModalTableSelect::make('route_names')
    ->tableConfiguration(RoutesTable::class)
    ->standaloneRecords(fn (Get $get): array => collect(Route::getRoutes()->getRoutesByName())
        ->filter(fn ($route, string $name): bool => blank($get('panel')) || str_starts_with($name, "filament.{$get('panel')}."))
        ->map(fn ($route, string $name): array => [
            'key' => $name,
            'label' => (string) str($name)->afterLast('.')->headline(),
            'route' => $name,
            'uri' => '/'.$route->uri(),
            'panel' => (string) str($name)->after('filament.')->before('.'),
        ])
        ->values()
        ->all(), titleAttribute: 'label')
    ->multiple()
    ->displayAsTable()
```

The state stores the record keys (here: route names) as strings — cast the column to `array` for `multiple()`. A saved key the closure no longer returns still renders as its raw key instead of disappearing. `fillsFields()`, `fillsRepeater()`, and all display modes accept the array records; closures receive them as `fn (array $record) => ...`.

## Installation

```bash
composer require finity-labs/fin-modal-table-select
```

Custom Filament theme? Add the package views to your theme's `@source` list — see [Installation](docs/installation.md).

## Documentation

| Guide | Covers |
|-------|--------|
| [Installation](docs/installation.md) | Requirements, Tailwind setup, translations |
| [Getting started](docs/getting-started.md) | Concepts, display-mode resolution, 30-second tour |
| [Display modes](docs/display-modes.md) | Table, stacked list, cards, thumbnails, badges, lists, infolist, custom views |
| [Filling form fields](docs/filling-forms.md) | `fillsFields()` — prefill an invoice header from a company |
| [Filling a repeater](docs/repeater.md) | `fillsRepeater()` + `SelectedItemsRepeater` — the invoice-lines flow |
| [Standalone mode](docs/standalone.md) | Using the picker without a relationship |
| [Reference](docs/reference.md) | Full API table, public methods, translation keys |

## Compatibility

| Package | Filament | PHP |
|---------|----------|-----|
| 1.x | 4.12+ / 5.x | 8.2+ |

## License

MIT
