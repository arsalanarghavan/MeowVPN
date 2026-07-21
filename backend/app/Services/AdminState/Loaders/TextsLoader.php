<?php

namespace App\Services\AdminState\Loaders;

use App\Modules\Core\Bot\Services\BotTextDefaults;
use App\Services\AdminState\AdminStateContext;
use App\Services\AdminState\AdminStateResult;
use Illuminate\Support\Facades\DB;

class TextsLoader extends AbstractLoader
{
    protected function shouldLoad(AdminStateContext $ctx): bool
    {
        return $ctx->needsTexts();
    }

    protected function load(AdminStateContext $ctx, AdminStateResult $result): void
    {
        $grouped = $this->groupedForDashboard();
        $total = count($grouped);
        $result->setTotal('texts', $total);

        $p = $ctx->page('texts');
        $slice = array_slice($grouped, $p['offset'], $p['per_page']);

        $result->merge([
            'texts' => $slice,
            'textDefaults' => BotTextDefaults::valuesMap(),
        ]);
    }

    /** @return list<array<string, mixed>> */
    protected function groupedForDashboard(): array
    {
        $catalog = BotTextDefaults::valuesMap();
        $dbByKey = $this->tableExists('svp_texts') ? $this->groupedFromDb() : [];

        if ($catalog === []) {
            return array_values($dbByKey);
        }

        $out = [];
        foreach ($catalog as $kn => $vals) {
            $db = $dbByKey[$kn] ?? null;
            $dbId = $db ? (int) ($db['id'] ?? 0) : 0;
            $valueFa = $db && (string) ($db['value_fa'] ?? '') !== ''
                ? (string) $db['value_fa']
                : (string) ($vals['fa'] ?? '');
            $valueEn = $db && (string) ($db['value_en'] ?? '') !== ''
                ? (string) $db['value_en']
                : (string) ($vals['en'] ?? '');
            $out[] = [
                'id' => $dbId,
                'key_name' => $kn,
                'category' => $db
                    ? (string) ($db['category'] ?? BotTextDefaults::categoryForKey($kn))
                    : BotTextDefaults::categoryForKey($kn),
                'value_fa' => $valueFa,
                'value_en' => $valueEn,
                'updated_at' => $db ? (string) ($db['updated_at'] ?? '') : '',
                'catalog_only' => $dbId < 1,
            ];
            unset($dbByKey[$kn]);
        }

        foreach ($dbByKey as $extra) {
            $out[] = $extra + ['catalog_only' => false];
        }

        usort($out, static function (array $a, array $b): int {
            $c = strcmp((string) ($a['category'] ?? ''), (string) ($b['category'] ?? ''));
            if ($c !== 0) {
                return $c;
            }

            return strcmp((string) ($a['key_name'] ?? ''), (string) ($b['key_name'] ?? ''));
        });

        return $out;
    }

    /** @return array<string, array<string, mixed>> */
    protected function groupedFromDb(): array
    {
        $rows = DB::table('svp_texts')
            ->orderBy('category')
            ->orderBy('key_name')
            ->orderBy('locale')
            ->get();

        $by = [];
        foreach ($rows as $r) {
            $kn = (string) ($r->key_name ?? '');
            if ($kn === '') {
                continue;
            }
            if (! isset($by[$kn])) {
                $by[$kn] = [
                    'id' => (int) ($r->id ?? 0),
                    'key_name' => $kn,
                    'category' => (string) ($r->category ?? 'general'),
                    'value_fa' => '',
                    'value_en' => '',
                    'updated_at' => (string) ($r->updated_at ?? ''),
                ];
            }
            $loc = (string) ($r->locale ?? 'fa');
            if ($loc === 'en') {
                $by[$kn]['value_en'] = (string) ($r->value ?? '');
            } else {
                $by[$kn]['value_fa'] = (string) ($r->value ?? '');
            }
            $t = (string) ($r->updated_at ?? '');
            if ($t !== '' && $t > (string) ($by[$kn]['updated_at'] ?? '')) {
                $by[$kn]['updated_at'] = $t;
            }
        }

        return $by;
    }
}
