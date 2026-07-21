<?php

namespace App\Modules\Backup\Services;

use App\Services\SettingsStore;

class BackupScopeService
{
    public function __construct(protected SettingsStore $settings) {}

    public function includeDatabase(): bool
    {
        $raw = $this->settings->get('backup_include_database');
        if ($raw === null) {
            return true;
        }

        return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
    }

    /** @return list<int> */
    public function disabledPanelIds(): array
    {
        $raw = $this->settings->get('backup_panel_disabled', []);
        if (! is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $id) {
            $n = (int) $id;
            if ($n >= 0) {
                $ids[] = $n;
            }
        }

        return array_values(array_unique($ids));
    }

    public function panelEnabled(int $panelId): bool
    {
        return ! in_array($panelId, $this->disabledPanelIds(), true);
    }

    /** @param  list<array{id: int, label: string}>  $panels */
    public function hasAnyEnabled(array $panels): bool
    {
        if ($this->includeDatabase()) {
            return true;
        }

        foreach ($panels as $panel) {
            $id = (int) ($panel['id'] ?? 0);
            if ($id >= 0 && $this->panelEnabled($id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{id: int, label: string}>  $panels
     * @return array{include_database: bool, panels: list<array{id: int, label: string, enabled: bool}>}
     */
    public function forApi(array $panels): array
    {
        $disabled = $this->disabledPanelIds();
        $scopePanels = [];
        foreach ($panels as $panel) {
            $id = (int) ($panel['id'] ?? 0);
            $label = trim((string) ($panel['label'] ?? ''));
            $scopePanels[] = [
                'id' => $id,
                'label' => $label !== '' ? $label : ($id === 0 ? 'legacy' : "#{$id}"),
                'enabled' => ! in_array($id, $disabled, true),
            ];
        }

        return [
            'include_database' => $this->includeDatabase(),
            'panels' => $scopePanels,
        ];
    }
}
