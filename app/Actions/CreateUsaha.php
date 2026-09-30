<?php

namespace App\Actions;

use App\Models\TemplateKategori;
use App\Models\TemplateUsaha;
use App\Models\Usaha;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateUsaha
{
    /**
     * @param  array{nama: string, template_usahas_id: int|string}  $data
     */
    public function handle(User $user, array $data): Usaha
    {
        $data = Validator::make($data, [
            'nama' => ['required', 'string', 'max:150', Rule::unique('usahas', 'nama')->where('user_id', $user->id)->whereNull('deleted_at')],
            'template_usahas_id' => ['required', Rule::exists('template_usahas', 'id')->where('aktif', true)],
        ])->validate();

        return DB::transaction(function () use ($user, $data): Usaha {
            $template = TemplateUsaha::query()->findOrFail($data['template_usahas_id']);
            $usaha = new Usaha(['nama' => $data['nama'], 'rekap_dasar' => $template->rekap_default]);
            $usaha->user()->associate($user);
            $usaha->template()->associate($template);
            $usaha->save();

            foreach (TemplateKategori::query()->where('template_usahas_id', $template->id)->orderBy('urutan')->get() as $category) {
                $copy = $usaha->kategoris()->make($category->only([
                    'nama', 'arah', 'klasifikasi', 'satuan_default', 'pakai_kuantitas', 'urutan',
                ]));
                $copy->kode_sistem = $category->kode_sistem;
                $copy->save();
            }

            return $usaha;
        });
    }
}
