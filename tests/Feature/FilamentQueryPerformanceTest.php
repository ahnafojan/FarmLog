<?php

use App\Actions\CreateSiklus;
use App\Actions\CreateUsaha;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Kategoris\Pages\ManageKategoris;
use App\Filament\Resources\Sikluses\Pages\EditSiklus;
use App\Filament\Resources\Sikluses\Pages\ListSikluses;
use App\Filament\Resources\Sikluses\RelationManagers\PenandasRelationManager;
use App\Filament\Resources\Transaksis\Pages\ManageTransaksis;
use App\Filament\Widgets\RevenueProfitChart;
use App\Models\Siklus;
use App\Models\Usaha;
use App\Models\User;
use Database\Factories\TransaksiFactory;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Livewire::withoutLazyLoading();
    $this->travelTo(now()->setDate(2026, 10, 12)->startOfDay());
});

/** @return array{0: Usaha, 1: Siklus} */
function filamentPerformanceData(int $count): array
{
    $user = User::factory()->create();
    $usaha = app(CreateUsaha::class)->handle($user, [
        'nama' => 'Usaha audit', 'jenis_usaha' => 'lele-pembesaran',
    ]);
    test()->actingAs($user);
    Filament::setTenant($usaha);
    Filament::bootCurrentPanel();

    for ($i = 1; $i <= $count; $i++) {
        $cycle = app(CreateSiklus::class)->handle($user, $usaha, [
            'nama' => "Siklus audit {$i}", 'tanggal_mulai' => '2026-10-01',
            'umur_masuk_hari' => 0, 'populasi_awal' => 1000, 'harga_bibit' => 500,
        ]);
    }

    $category = $usaha->kategoris()->where('nama', 'Listrik/air')->sole();
    TransaksiFactory::new()->untukKategori($category)->count($count)->create([
        'sikluses_id' => $cycle->id, 'tanggal' => '2026-10-12',
    ]);

    for ($i = 1; $i < $count; $i++) {
        $cycle->penandas()->make()->forceFill([
            'nama' => "Jadwal audit {$i}", 'jenis' => 'pengingat', 'tanggal' => '2026-10-20',
        ])->save();
    }

    return [$usaha, $cycle];
}

/** @return list<array{query: string, bindings: array, time: float}> */
function filamentMeasuredQueries(Closure $render, string $label): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $render();
        $queries = DB::getQueryLog();
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    if (getenv('PERF_QUERY_REPORT')) {
        fwrite(STDOUT, "\n{$label}: ".count($queries)." queries\n");
        if (getenv('PERF_QUERY_SQL')) {
            foreach ($queries as $query) {
                fwrite(STDOUT, $query['query']."\n");
            }
        }
    }

    return $queries;
}

test('resource pages keep a bounded query count', function (string $page, int $budget, int $count) {
    filamentPerformanceData($count);

    $queries = filamentMeasuredQueries(
        fn () => Livewire::test($page)->assertSuccessful(),
        class_basename($page)." ({$count} records)",
    );

    expect(count($queries))->toBeLessThanOrEqual($budget);
})->with([
    'transactions' => [ManageTransaksis::class, 5],
    'categories' => [ManageKategoris::class, 3],
    'cycle cards' => [ListSikluses::class, 4],
    'dashboard' => [Dashboard::class, 5],
])->with([1, 10]);

test('schedule authorization does not query relationships per row', function (int $count) {
    [, $cycle] = filamentPerformanceData($count);
    $owner = $cycle->fresh();
    $milestones = $cycle->penandas;

    $queries = filamentMeasuredQueries(
        fn () => Livewire::test(PenandasRelationManager::class, [
            'ownerRecord' => $owner, 'pageClass' => EditSiklus::class,
        ])->assertCanSeeTableRecords($milestones)
            ->assertDontSeeHtml('class="fi-pagination'),
        "PenandasRelationManager ({$count} records)",
    );

    expect(count($queries))->toBeLessThanOrEqual(4);
})->with([1, 10, 30]);

test('cycle detail keeps a bounded query count including its schedule', function (int $count) {
    [, $cycle] = filamentPerformanceData($count);

    $queries = filamentMeasuredQueries(
        fn () => Livewire::test(EditSiklus::class, ['record' => $cycle->id])->assertSee('Jadwal'),
        "EditSiklus ({$count} records)",
    );

    expect(count($queries))->toBeLessThanOrEqual(6);
})->with([1, 10]);

test('revenue widget renders supplied data without database queries', function () {
    filamentPerformanceData(1);

    $queries = filamentMeasuredQueries(
        fn () => Livewire::test(RevenueProfitChart::class, [
            'chartData' => ['labels' => ['12 Oct'], 'pemasukan' => [1000], 'laba_bersih' => [700]],
        ])->assertSuccessful(),
        'RevenueProfitChart',
    );

    expect($queries)->toBeEmpty();
});

test('frequent table queries can filter and sort without a filesort', function () {
    [$usaha, $cycle] = filamentPerformanceData(1);
    $category = $usaha->kategoris()->where('nama', 'Listrik/air')->sole();
    $income = $usaha->kategoris()->where('arah', 'pemasukan')->firstOrFail();

    $categories = [];
    $milestones = [];
    $transactions = [];

    for ($i = 1; $i <= 1000; $i++) {
        $categories[] = [
            'usahas_id' => $usaha->id, 'nama' => "Kategori {$i}", 'arah' => 'pengeluaran',
            'klasifikasi' => 'operasional', 'urutan' => $i % 100,
        ];
        $milestones[] = [
            'sikluses_id' => $cycle->id, 'nama' => "Jadwal {$i}", 'jenis' => 'pengingat',
            'tanggal' => today()->addDays($i % 30)->toDateString(),
            'selesai_at' => $i % 2 ? now()->toDateTimeString() : null,
        ];
        $transactions[] = TransaksiFactory::new()->untukKategori($i % 100 === 0 ? $income : $category)->raw([
            'sikluses_id' => $cycle->id, 'tanggal' => today()->subDays($i % 30)->toDateString(),
            'total' => $i * 100,
        ]);
    }

    DB::table('kategoris')->insert($categories);
    DB::table('sikluses_penandas')->insert($milestones);
    DB::table('transaksis')->insert($transactions);

    $queries = [
        'categories' => DB::table('kategoris')->where('usahas_id', $usaha->id)
            ->orderBy('urutan')->orderBy('id')->limit(10),
        'schedule' => DB::table('sikluses_penandas')->where('sikluses_id', $cycle->id)
            ->orderBy('tanggal')->orderBy('id')->limit(10),
        'transactions filtered' => DB::table('transaksis')->where('usahas_id', $usaha->id)
            ->whereNull('deleted_at')->where('arah', 'pemasukan')
            ->orderByDesc('tanggal')->orderByDesc('id')->limit(10),
        'transactions total' => DB::table('transaksis')->where('usahas_id', $usaha->id)
            ->whereNull('deleted_at')->orderBy('total')->orderBy('id')->limit(10),
    ];

    $plans = collect($queries)->map(function (Builder $query, string $label): object {
        $plan = DB::select('EXPLAIN '.$query->toSql(), $query->getBindings())[0];
        if (getenv('PERF_QUERY_REPORT')) {
            fwrite(STDOUT, "\n{$label}: ".json_encode($plan)."\n");
        }

        return $plan;
    });

    foreach ($plans as $label => $plan) {
        expect((string) $plan->Extra, $label)->not->toContain('Using filesort');
        expect($plan->type, $label)->not->toBe('ALL');
    }
});

test('category edit reuses the classification check during rendering', function () {
    [$usaha] = filamentPerformanceData(1);
    $category = $usaha->kategoris()->where('nama', 'Listrik/air')->sole();
    $page = Livewire::test(ManageKategoris::class);

    $queries = filamentMeasuredQueries(
        fn () => $page->mountAction(TestAction::make('edit')->table($category))
            ->assertActionMounted(TestAction::make('edit')->table($category))
            ->assertFormFieldDisabled('arah')
            ->assertFormFieldDisabled('klasifikasi'),
        'ManageKategoris edit modal',
    );

    $classificationQueries = array_filter($queries, fn (array $query): bool => str_contains($query['query'], 'exists') && str_contains($query['query'], 'transaksis'));

    expect($classificationQueries)->toHaveCount(1);
});

test('category classification cache is isolated by record and refreshed on the next request', function () {
    [$usaha] = filamentPerformanceData(1);
    $unused = $usaha->kategoris()->create([
        'nama' => 'Kategori belum dipakai', 'arah' => 'pengeluaran', 'klasifikasi' => 'operasional',
    ]);
    $used = $usaha->kategoris()->where('nama', 'Listrik/air')->sole();
    $page = Livewire::test(ManageKategoris::class)
        ->mountAction(TestAction::make('edit')->table($used))
        ->assertFormFieldDisabled('arah')
        ->unmountAction()
        ->mountAction(TestAction::make('edit')->table($unused))
        ->assertFormFieldEnabled('arah');

    TransaksiFactory::new()->untukKategori($unused)->create()->delete();

    $page->call('$refresh')
        ->assertFormFieldDisabled('arah')
        ->assertFormFieldDisabled('klasifikasi');
});
