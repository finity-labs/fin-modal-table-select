<?php

declare(strict_types=1);

use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get;
use FinityLabs\FinModalTableSelect\Components\ModalTableSelect;
use FinityLabs\FinModalTableSelect\Livewire\StandaloneRecordsTableSelectComponent;
use FinityLabs\FinModalTableSelect\Tests\Fixtures\Livewire\TestForm;
use FinityLabs\FinModalTableSelect\Tests\Fixtures\Tables\RoutesTable;
use Illuminate\Database\Eloquent\Model;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    app('view')->addNamespace('test-fixtures', __DIR__.'/../Fixtures/views');
});

/** @return array<int, array<string, mixed>> */
function routeRecords(): array
{
    return [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'route' => 'filament.admin.pages.dashboard', 'uri' => '/admin', 'panel' => 'admin'],
        ['key' => 'users.index', 'label' => 'Users', 'route' => 'filament.admin.resources.users.index', 'uri' => '/admin/users', 'panel' => 'admin'],
        ['key' => 'reports.index', 'label' => 'Reports', 'route' => 'filament.app.pages.reports', 'uri' => '/app/reports', 'panel' => 'app'],
    ];
}

/** @param  Model|array<string, mixed>|null  $record */
function mountRecordsForm(callable $makeComponents, Model|array|null $record = null): Testable
{
    TestForm::$makeComponents = Closure::fromCallable($makeComponents);
    TestForm::$record = $record;

    return Livewire::test(TestForm::class);
}

function recordsField(Testable $livewire, string $name = 'routes'): ModalTableSelect
{
    return $livewire->instance()->getSchema('form')->getFlatFields()[$name];
}

function mountModalTable(): Testable
{
    return Livewire::test(StandaloneRecordsTableSelectComponent::class, [
        'tableConfiguration' => base64_encode(RoutesTable::class),
        'tableArguments' => [
            StandaloneRecordsTableSelectComponent::RECORDS_ARGUMENT => routeRecords(),
            StandaloneRecordsTableSelectComponent::KEY_ATTRIBUTE_ARGUMENT => 'key',
        ],
        'state' => [],
    ]);
}

it('renders the array records as modal table rows', function () {
    mountModalTable()
        ->assertOk()
        ->assertSee('Dashboard')
        ->assertSee('Users')
        ->assertSee('Reports')
        ->assertSee('/admin/users');
});

it('narrows the modal table with search over the searchable columns', function () {
    mountModalTable()
        ->searchTable('users')
        ->assertSee('Users')
        ->assertDontSee('Dashboard')
        ->assertDontSee('Reports');
});

it('sorts the modal table by a sortable column in both directions', function () {
    mountModalTable()
        ->sortTable('label')
        ->assertSeeInOrder(['Dashboard', 'Reports', 'Users'])
        ->sortTable('label', 'desc')
        ->assertSeeInOrder(['Users', 'Reports', 'Dashboard']);
});

it('binds the selection to the field state as record keys', function () {
    mountModalTable()
        ->set('state', ['dashboard', 'users.index'])
        ->assertSet('state', ['dashboard', 'users.index']);
});

it('passes the records payload through the field table arguments', function () {
    $livewire = mountRecordsForm(fn (): array => [
        ModalTableSelect::make('routes')
            ->tableConfiguration(RoutesTable::class)
            ->standaloneRecords(fn (): array => routeRecords(), titleAttribute: 'label')
            ->multiple(),
    ]);

    $arguments = recordsField($livewire)->getTableArguments();

    expect($arguments[StandaloneRecordsTableSelectComponent::RECORDS_ARGUMENT])
        ->toHaveCount(3)
        ->and($arguments[StandaloneRecordsTableSelectComponent::KEY_ATTRIBUTE_ARGUMENT])->toBe('key');
});

it('opens the select modal inside a schema whose record is an array row', function () {
    // A record action on an array-backed table hands its modal schema the row
    // as a plain array, and the field's getRecord() passes it along. The
    // embedded Livewire component inherits a ?Model $record from Filament, so
    // forwarding the array into the mount is a TypeError on the select click.
    $livewire = mountRecordsForm(fn (): array => [
        ModalTableSelect::make('routes')
            ->tableConfiguration(RoutesTable::class)
            ->standaloneRecords(fn (): array => routeRecords(), titleAttribute: 'label'),
    ], record: ['__key' => 'users.index', 'key' => 'users.index', 'label' => 'Users']);

    // Mounting renders the modal schema, which embeds the child component;
    // before the guard this call died in the embedded Livewire mount.
    $livewire
        ->mountAction(TestAction::make('select')->schemaComponent('routes'))
        ->assertActionMounted(TestAction::make('select')->schemaComponent('routes'));
});

it('hydrates a saved multiple selection into labels', function () {
    $livewire = mountRecordsForm(fn (): array => [
        ModalTableSelect::make('routes')
            ->tableConfiguration(RoutesTable::class)
            ->standaloneRecords(fn (): array => routeRecords(), titleAttribute: 'label')
            ->multiple(),
    ]);

    $livewire->set('data.routes', ['dashboard', 'reports.index']);

    $field = recordsField($livewire);

    expect($field->getOptionLabels())->toBe([
        'dashboard' => 'Dashboard',
        'reports.index' => 'Reports',
    ])
        ->and($livewire->html())->toContain('Dashboard')->toContain('Reports');
});

it('hydrates a saved single selection through the display record and label', function () {
    $livewire = mountRecordsForm(fn (): array => [
        ModalTableSelect::make('routes')
            ->tableConfiguration(RoutesTable::class)
            ->standaloneRecords(fn (): array => routeRecords(), titleAttribute: 'label'),
    ]);

    $livewire->set('data.routes', 'users.index');

    $field = recordsField($livewire);

    expect($field->getSelectedDisplayRecord())->toBe(routeRecords()[1])
        ->and($field->getOptionLabel())->toBe('Users');
});

it('still renders a key the records closure no longer returns', function () {
    $livewire = mountRecordsForm(fn (): array => [
        ModalTableSelect::make('routes')
            ->tableConfiguration(RoutesTable::class)
            ->standaloneRecords(fn (): array => routeRecords(), titleAttribute: 'label')
            ->multiple()
            ->stackedList(),
    ]);

    $livewire->set('data.routes', ['dashboard', 'ghost.route']);

    $field = recordsField($livewire);

    expect($field->getSelectedRecords()->all())->toBe([
        routeRecords()[0],
        ['key' => 'ghost.route'],
    ])
        ->and($field->getOptionLabels())->toBe([
            'dashboard' => 'Dashboard',
            'ghost.route' => 'ghost.route',
        ])
        ->and($livewire->html())->toContain('ghost.route');
});

it('evaluates the records closure with Get access to sibling state', function () {
    $livewire = mountRecordsForm(fn (): array => [
        TextInput::make('panel_filter'),
        ModalTableSelect::make('routes')
            ->tableConfiguration(RoutesTable::class)
            ->standaloneRecords(
                fn (Get $get): array => array_values(array_filter(
                    routeRecords(),
                    fn (array $record): bool => blank($get('panel_filter')) || $record['panel'] === $get('panel_filter'),
                )),
                titleAttribute: 'label',
            )
            ->multiple(),
    ]);

    $livewire->set('data.panel_filter', 'app');
    $livewire->set('data.routes', ['reports.index', 'dashboard']);

    $field = recordsField($livewire);

    // Reports is inside the 'app' scope; dashboard is filtered out by the
    // sibling state and degrades to its raw key.
    expect($field->getFreshSelectedRecords()->all())->toBe([
        routeRecords()[2],
        ['key' => 'dashboard'],
    ])
        ->and(array_keys($field->getStandaloneRecordsIndex()))->toBe(['reports.index']);
});

it('renders every display mode for array records', function (callable $configure, string $expectedHtml) {
    $livewire = mountRecordsForm(fn (): array => [
        $configure(
            ModalTableSelect::make('routes')
                ->tableConfiguration(RoutesTable::class)
                ->standaloneRecords(fn (): array => routeRecords(), titleAttribute: 'label'),
        ),
    ]);

    $livewire->set('data.routes', ['dashboard', 'users.index']);

    expect($livewire->html())->toContain($expectedHtml)->toContain('Dashboard');
})->with([
    'badges' => [fn (ModalTableSelect $field): ModalTableSelect => $field->multiple(), 'fi-badge'],
    'per-record badges' => [
        fn (ModalTableSelect $field): ModalTableSelect => $field->multiple()
            ->badgeColorFromRecord(fn (array $record): string => $record['panel'] === 'admin' ? 'success' : 'danger'),
        'fi-color-success',
    ],
    'stacked list' => [
        fn (ModalTableSelect $field): ModalTableSelect => $field->multiple()->stackedList()->stackedListSecondary('uri'),
        'fi-fo-modal-table-select-stacked',
    ],
    'card grid' => [
        fn (ModalTableSelect $field): ModalTableSelect => $field->multiple()->cardGrid()->cardTitle('label')->cardDescription('uri'),
        'fi-fo-modal-table-select-cards',
    ],
    'thumbnails' => [
        fn (ModalTableSelect $field): ModalTableSelect => $field->multiple()->thumbnails('image_url'),
        'fi-fo-modal-table-select-thumbs',
    ],
    'inherited table' => [
        fn (ModalTableSelect $field): ModalTableSelect => $field->multiple()->displayAsTable(),
        'fi-fo-modal-table-select-table',
    ],
    'explicit table columns' => [
        fn (ModalTableSelect $field): ModalTableSelect => $field->multiple()
            ->tableColumns([TableColumn::make('Screen'), TableColumn::make('URI')])
            ->tableSchema([TextEntry::make('label'), TextEntry::make('uri')]),
        '/admin/users',
    ],
    'item view' => [
        fn (ModalTableSelect $field): ModalTableSelect => $field->multiple()->itemView('test-fixtures::array-item'),
        'ROUTE: Dashboard',
    ],
]);

it('renders the infolist card for a single array record', function () {
    $livewire = mountRecordsForm(fn (): array => [
        ModalTableSelect::make('routes')
            ->tableConfiguration(RoutesTable::class)
            ->standaloneRecords(fn (): array => routeRecords(), titleAttribute: 'label')
            ->infolistSchema([
                TextEntry::make('label'),
                TextEntry::make('uri'),
            ]),
    ]);

    $livewire->set('data.routes', 'reports.index');

    expect($livewire->html())
        ->toContain('Reports')
        ->toContain('/app/reports');
});

it('fills sibling fields from an array record', function () {
    $livewire = mountRecordsForm(fn (): array => [
        ModalTableSelect::make('routes')
            ->tableConfiguration(RoutesTable::class)
            ->standaloneRecords(fn (): array => routeRecords(), titleAttribute: 'label')
            ->fillsFields([
                'screen_label' => 'label',
                'screen_uri' => fn (array $record): string => strtoupper($record['uri']),
            ]),
        TextInput::make('screen_label'),
        TextInput::make('screen_uri'),
    ]);

    $field = recordsField($livewire);
    $field->state('users.index');
    $field->callAfterStateUpdated();

    expect($livewire->get('data.screen_label'))->toBe('Users')
        ->and($livewire->get('data.screen_uri'))->toBe('/ADMIN/USERS');
});

it('syncs array records into a repeater with merge semantics', function () {
    $livewire = mountRecordsForm(fn (): array => [
        ModalTableSelect::make('routes')
            ->tableConfiguration(RoutesTable::class)
            ->standaloneRecords(fn (): array => routeRecords(), titleAttribute: 'label')
            ->multiple()
            ->selectionOnly()
            ->fillsRepeater('items', fn (array $record): array => [
                'route' => $record['key'],
                'label' => $record['label'],
            ], keyAttribute: 'route'),
        TextInput::make('items')->hidden(),
    ]);

    $field = recordsField($livewire);
    $field->state(['dashboard', 'users.index']);
    $field->callAfterStateUpdated();

    $items = $livewire->get('data.items');

    expect(array_column($items, 'route'))->toBe(['dashboard', 'users.index'])
        ->and(array_column($items, 'label'))->toBe(['Dashboard', 'Users']);

    // Deselect one; the other row survives untouched.
    $field->state(['users.index']);
    $field->callAfterStateUpdated();

    expect(array_column($livewire->get('data.items'), 'route'))->toBe(['users.index']);
});

it('renders inside a Repeater row, where Filament clones the field, and scopes each row by its own state', function () {
    $livewire = mountRecordsForm(fn (): array => [
        Repeater::make('items')
            ->schema([
                TextInput::make('panel_filter'),
                ModalTableSelect::make('routes')
                    ->tableConfiguration(RoutesTable::class)
                    ->standaloneRecords(
                        fn (Get $get): array => array_values(array_filter(
                            routeRecords(),
                            fn (array $record): bool => blank($get('panel_filter')) || $record['panel'] === $get('panel_filter'),
                        )),
                        titleAttribute: 'label',
                    ),
            ]),
    ]);

    $livewire
        ->fillForm(['items' => [
            ['panel_filter' => 'app', 'routes' => 'reports.index'],
            ['panel_filter' => 'admin', 'routes' => 'users.index'],
        ]])
        ->assertOk()
        ->assertSee('Reports')
        ->assertSee('Users');

    /** @var Repeater $repeater */
    $repeater = $livewire->instance()->getSchema('form')->getFlatFields()['items'];
    $items = array_keys($livewire->get('data.items'));

    $pickerOf = fn (int|string $item): ModalTableSelect => $repeater->getChildSchema($item)->getFlatFields()['routes'];

    expect(array_keys($pickerOf($items[0])->getStandaloneRecordsIndex()))->toBe(['reports.index'])
        ->and(array_keys($pickerOf($items[1])->getStandaloneRecordsIndex()))->toBe(['dashboard', 'users.index'])
        ->and($pickerOf($items[1])->getAction('select'))->not->toBeNull();
});
