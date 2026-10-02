@php
    use App\Filament\Resources\Sikluses\SiklusResource;
@endphp

<x-filament-panels::page>
    @php
        $data = $this->cycleData;
        $cycles = $data['cycles'];
        $counts = $data['counts'];
    @endphp

    <div class="farm-content">
        <div class="farm-toolbar">
            <x-filament::tabs label="Filter status siklus">
                @foreach (['berjalan' => 'Berjalan', 'selesai' => 'Selesai'] as $value => $label)
                    <x-filament::tabs.item
                        :active="$cycleStatus === $value"
                        :badge="$counts[$value]"
                        wire:click="selectStatus('{{ $value }}')"
                        wire:loading.attr="disabled"
                        wire:target="selectStatus"
                    >
                        {{ $label }}
                    </x-filament::tabs.item>
                @endforeach
            </x-filament::tabs>

            @if (SiklusResource::canCreate())
                <x-filament::button
                    tag="a"
                    :href="SiklusResource::getUrl('create')"
                    icon="heroicon-o-plus-circle"
                >
                    Buat Siklus
                </x-filament::button>
            @endif
        </div>

        <h2 class="text-sm font-medium text-gray-500 dark:text-gray-400">
            {{ $cycleStatus === 'berjalan'
                ? 'Ringkasan siklus aktif'
                : 'Riwayat siklus selesai' }}
        </h2>

        <div
            class="grid items-start gap-5 xl:grid-cols-2"
            wire:loading.class="opacity-60"
            wire:target="selectStatus"
        >
            @forelse ($cycles as $cycle)
                <x-farmlog.cycle-card
                    :cycle="$cycle"
                    :url="SiklusResource::getUrl('edit', ['record' => $cycle['id']])"
                    :show-profit="false"
                    wire:key="cycle-card-{{ $cycle['id'] }}"
                >
                    <div class="space-y-3 [&_.farm-card]:bg-gray-50 [&_.farm-card]:shadow-none dark:[&_.farm-card]:bg-gray-800">
                        <p class="farm-muted">
                            {{ $cycle['status'] === 'berjalan'
                                ? 'Keuangan siklus · Hasil sementara'
                                : 'Keuangan siklus selesai' }}
                        </p>

                        <x-farmlog.financial-summary
                            :summary="$cycle['summary']"
                            investment-note="Investasi dicatat terpisah dari laba siklus."
                        />
                    </div>

                    <a
                        class="flex min-h-11 items-center justify-between gap-3 text-sm font-medium text-primary-600 dark:text-primary-400"
                        href="{{ SiklusResource::getUrl('edit', ['record' => $cycle['id']]) }}"
                    >
                        <span>Lihat detail dan jadwal siklus</span>
                        <x-filament::icon icon="heroicon-o-arrow-right" class="size-5 shrink-0" />
                    </a>
                </x-farmlog.cycle-card>
            @empty
                <x-farmlog.empty-state
                    class="col-span-full"
                    icon="heroicon-o-square-3-stack-3d"
                    :title="$cycleStatus === 'berjalan'
                        ? 'Belum ada siklus berjalan'
                        : 'Belum ada siklus selesai'"
                    :description="$cycleStatus === 'berjalan'
                        ? 'Buat siklus untuk mulai mencatat budidaya.'
                        : 'Siklus yang sudah ditutup akan muncul di sini.'"
                >
                    <x-slot name="actions">
                        @if ($cycleStatus === 'berjalan' && SiklusResource::canCreate())
                            <x-filament::button
                                tag="a"
                                :href="SiklusResource::getUrl('create')"
                                icon="heroicon-o-plus"
                            >
                                Buat siklus
                            </x-filament::button>
                        @endif
                    </x-slot>
                </x-farmlog.empty-state>
            @endforelse
        </div>

        @if ($cycles->hasPages())
            <div class="pt-1">
                {{ $cycles->links() }}
            </div>
        @endif

        @if ($cycles->count() > 0 && SiklusResource::canCreate())
            <div class="farm-card flex flex-col items-start gap-4 [&_h3]:text-base [&_h3]:font-semibold">
                <h3>Ingin menambah siklus baru?</h3>

                <x-filament::button
                    tag="a"
                    :href="SiklusResource::getUrl('create')"
                    icon="heroicon-o-plus"
                >
                    Buat Siklus Baru
                </x-filament::button>
            </div>
        @endif
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
