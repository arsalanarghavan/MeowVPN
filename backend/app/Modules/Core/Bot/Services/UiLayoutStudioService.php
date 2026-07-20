<?php

namespace App\Modules\Core\Bot\Services;

use App\Services\SettingsStore;

/**
 * Bot UI layout persistence/validation for dashboard studio (WP SimpleVPBot_UI_Layout parity).
 */
class UiLayoutStudioService
{
    /** @var array<int, string> */
    public const BUTTON_STYLES = ['primary', 'success', 'danger'];

    public function __construct(
        protected SettingsStore $settings,
        protected UiCustomGroupsService $groups,
    ) {}

    /** @return array<string, mixed> */
    public function getStored(): array
    {
        $raw = $this->settings->get('bot_ui_layout', []);

        return is_array($raw) ? $raw : [];
    }

    /** @return array<string, array<int, array<int, array<string, mixed>>>> */
    public function getMergedSurfaces(): array
    {
        $stored = $this->getStored();
        $surfaces = isset($stored['surfaces']) && is_array($stored['surfaces']) ? $stored['surfaces'] : [];
        $defaults = UiActionRegistryService::default_surface_rows();
        $out = [];
        foreach ($defaults as $surface => $defaultRows) {
            $out[$surface] = $this->mergeSurfaceRows(
                $surface,
                $surfaces[$surface] ?? null,
                $defaultRows,
            );
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok:bool, surfaces?:array<string,array<int,array<int,array<string,mixed>>>>, errors?:array<int,string>}
     */
    public function validateSurfacesPayload(array $payload): array
    {
        $errors = [];
        $out = [];
        $defaults = UiActionRegistryService::default_surface_rows();
        foreach ($defaults as $surface => $_d) {
            if (! isset($payload[$surface])) {
                continue;
            }
            $incoming = $payload[$surface];
            if (! is_array($incoming)) {
                $errors[] = 'bad_surface:'.$surface;
                continue;
            }
            if ($incoming === []) {
                $out[$surface] = [];
                continue;
            }
            $validIds = array_flip(UiActionRegistryService::surface_action_ids($surface));
            $surfOut = [];
            $seenIds = [];
            foreach ($incoming as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $surfRow = [];
                foreach ($row as $cell) {
                    if (! is_array($cell)) {
                        continue;
                    }
                    $id = isset($cell['id']) ? (string) $cell['id'] : '';
                    if ($id === '' || ! isset($validIds[$id])) {
                        $errors[] = 'unknown_action:'.$surface.':'.$id;
                        continue;
                    }
                    if (isset($seenIds[$id])) {
                        $errors[] = 'duplicate_action:'.$surface.':'.$id;
                        continue;
                    }
                    $seenIds[$id] = true;
                    $rawStyle = isset($cell['style']) ? (string) $cell['style'] : '';
                    if ($rawStyle !== '' && $this->normalizeCellStyle($rawStyle) === '') {
                        $errors[] = 'bad_style:'.$surface.':'.$id;
                        continue;
                    }
                    $surfRow[] = $this->mergeCellBase($id, $cell);
                }
                if ($surfRow !== []) {
                    $surfOut[] = $surfRow;
                }
            }
            if ($surfOut !== []) {
                $out[$surface] = $surfOut;
            }
        }

        $mergedCheck = $this->getMergedSurfaces();
        foreach ($out as $sid => $rows) {
            $mergedCheck[$sid] = $rows;
        }
        foreach ($this->groups->validateCrossSurface($mergedCheck) as $err) {
            $errors[] = $err;
        }

        return [
            'ok' => $errors === [],
            'surfaces' => $out,
            'errors' => $errors,
        ];
    }

    /** @param  array<string, array<int, array<int, array<string, mixed>>>>  $surfaces */
    public function saveSurfaces(array $surfaces): void
    {
        $stored = $this->getStored();
        if (! isset($stored['surfaces']) || ! is_array($stored['surfaces'])) {
            $stored['surfaces'] = [];
        }
        foreach ($surfaces as $k => $rows) {
            $stored['surfaces'][$k] = $rows;
        }
        $stored['version'] = UiActionRegistryService::LAYOUT_VERSION;
        $this->settings->set('bot_ui_layout', $stored);
    }

    public function resetAll(): void
    {
        $this->settings->set('bot_ui_layout', []);
    }

    /** @return array<string, mixed> */
    public function exportMergedForDashboard(): array
    {
        $stored = $this->getStored();
        $surfacesOut = [];
        foreach ($this->getMergedSurfaces() as $sid => $rows) {
            $surfacesOut[$sid] = $rows;
        }

        return [
            'version' => isset($stored['version']) ? (int) $stored['version'] : UiActionRegistryService::LAYOUT_VERSION,
            'surfaces' => $surfacesOut,
            'customGroups' => $this->groups->exportForDashboard(),
        ];
    }

    /**
     * @param  mixed  $storedRows
     * @param  array<int, array<int, string>>  $defaultRows
     * @return array<int, array<int, array<string, mixed>>>
     */
    protected function mergeSurfaceRows(string $surface, mixed $storedRows, array $defaultRows): array
    {
        if (! is_array($storedRows) || $storedRows === []) {
            return $this->rowsFromIds($defaultRows);
        }
        $validIds = array_flip(UiActionRegistryService::surface_action_ids($surface));
        $out = [];
        foreach ($storedRows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $built = [];
            foreach ($row as $cell) {
                if (is_string($cell)) {
                    $cell = ['id' => $cell, 'enabled' => true];
                }
                if (! is_array($cell)) {
                    continue;
                }
                $id = (string) ($cell['id'] ?? '');
                if ($id === '' || ! isset($validIds[$id])) {
                    continue;
                }
                $built[] = $this->mergeCellBase($id, $cell);
            }
            if ($built !== []) {
                $out[] = $built;
            }
        }
        if ($out === []) {
            return $this->rowsFromIds($defaultRows);
        }

        return $out;
    }

    /**
     * @param  array<int, array<int, string>>  $idRows
     * @return array<int, array<int, array<string, mixed>>>
     */
    protected function rowsFromIds(array $idRows): array
    {
        $out = [];
        foreach ($idRows as $row) {
            $br = [];
            foreach ($row as $id) {
                $br[] = $this->mergeCellBase((string) $id, ['enabled' => true, 'glass' => false]);
            }
            if ($br !== []) {
                $out[] = $br;
            }
        }

        return $out;
    }

    /** @param  array<string, mixed>  $cell */
    protected function mergeCellBase(string $id, array $cell): array
    {
        return array_merge(
            [
                'id' => $id,
                'enabled' => ! isset($cell['enabled']) || ! empty($cell['enabled']),
                'glass' => ! empty($cell['glass']),
            ],
            $this->cellDecorFields($cell),
        );
    }

    /** @param  array<string, mixed>  $cell */
    protected function cellDecorFields(array $cell): array
    {
        $out = [];
        $style = $this->normalizeCellStyle($cell['style'] ?? '');
        if ($style !== '') {
            $out['style'] = $style;
        }
        $emoji = $this->normalizeCellIconCustomEmojiId($cell['icon_custom_emoji_id'] ?? '');
        if ($emoji !== '') {
            $out['icon_custom_emoji_id'] = $emoji;
        }

        return $out;
    }

    public function normalizeCellStyle(mixed $style): string
    {
        $s = is_string($style) ? strtolower(trim($style)) : '';

        return in_array($s, self::BUTTON_STYLES, true) ? $s : '';
    }

    public function normalizeCellIconCustomEmojiId(mixed $raw): string
    {
        $id = trim((string) $raw);
        if ($id === '' || ! preg_match('/^\d+$/', $id)) {
            return '';
        }

        return $id;
    }
}
