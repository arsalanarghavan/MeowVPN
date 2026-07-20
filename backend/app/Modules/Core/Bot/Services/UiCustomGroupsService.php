<?php

namespace App\Modules\Core\Bot\Services;

use App\Services\SettingsStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bot UI dynamic custom submenu groups (WP SimpleVPBot_UI_Custom_Groups).
 */
class UiCustomGroupsService
{
    public function __construct(protected SettingsStore $settings) {}

    /** @return array<string, array<string, mixed>> */
    public function getAll(): array
    {
        $stored = $this->getStored();
        $raw = isset($stored['custom_groups']) && is_array($stored['custom_groups']) ? $stored['custom_groups'] : [];
        $out = [];
        foreach ($raw as $id => $g) {
            if (! is_array($g)) {
                continue;
            }
            $norm = $this->normalizeGroup((string) $id, $g);
            if ($norm) {
                $out[$norm['id']] = $norm;
            }
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function get(string $groupId): ?array
    {
        $all = $this->getAll();

        return $all[$groupId] ?? null;
    }

    public function isCustomSurface(string $surface): bool
    {
        return (bool) preg_match('/^(user|admin)_custom_/', $surface);
    }

    public function is_custom_surface(string $surface): bool
    {
        return $this->isCustomSurface($surface);
    }

    /** @return array<string, mixed>|null */
    public function getBySurface(string $surfaceId): ?array
    {
        foreach ($this->getAll() as $g) {
            if ((string) ($g['surface_id'] ?? '') === $surfaceId) {
                return $g;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    public function get_by_surface(string $surfaceId): ?array
    {
        return $this->getBySurface($surfaceId);
    }

    public function surface_section(string $surface): string
    {
        return $this->surfaceSection($surface);
    }

    public function surface_supports_groups(string $surface): bool
    {
        return $this->surfaceSupportsGroups($surface);
    }

    /** @return array<string, mixed> */
    public function export_for_dashboard(): array
    {
        return $this->exportForDashboard();
    }

    public function actionStudioCategory(string $actionId): string
    {
        if ($this->getByHubActionPublic($actionId)) {
            return 'custom_hub';
        }
        if (str_starts_with($actionId, 'user.main.')) {
            return 'user_main';
        }
        if (str_starts_with($actionId, 'admin.root.')) {
            return 'admin_root';
        }
        if (str_starts_with($actionId, 'admin.users.')) {
            return 'admin_users';
        }
        if (str_starts_with($actionId, 'admin.finance.')) {
            return 'admin_finance';
        }
        if (str_starts_with($actionId, 'admin.cat.') || str_starts_with($actionId, 'admin.adv.')) {
            return 'admin_settings';
        }
        if (str_starts_with($actionId, 'hub.') || str_starts_with($actionId, 'wiz.') || str_starts_with($actionId, 'op.')) {
            return 'admin_ops';
        }
        if (str_starts_with($actionId, 'svc_') || str_starts_with($actionId, 'purchase_')) {
            return 'inline';
        }

        return 'other';
    }

    public function action_studio_category(string $actionId): string
    {
        return $this->actionStudioCategory($actionId);
    }

    /**
     * @param  array<string, array<int, array<int, array<string, mixed>>>>  $mergedLayout
     * @return array<int, string>
     */
    public function validateCrossSurface(array $mergedLayout): array
    {
        $errors = [];
        $actionLoc = [];
        foreach ($mergedLayout as $surface => $rows) {
            if (! is_array($rows)) {
                continue;
            }
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                foreach ($row as $cell) {
                    if (! is_array($cell)) {
                        continue;
                    }
                    $aid = (string) ($cell['id'] ?? '');
                    if ($aid === '') {
                        continue;
                    }
                    if (isset($actionLoc[$aid])) {
                        $errors[] = 'duplicate_action_global:'.$aid;
                        continue;
                    }
                    $actionLoc[$aid] = (string) $surface;
                }
            }
        }
        foreach ($this->getAll() as $g) {
            $hub = (string) ($g['hub_action_id'] ?? '');
            $parent = (string) ($g['parent_surface'] ?? '');
            if ($hub === '' || $parent === '') {
                continue;
            }
            if (! isset($actionLoc[$hub]) || $actionLoc[$hub] !== $parent) {
                $errors[] = 'hub_not_on_parent:'.$hub;
            }
            foreach ((array) ($g['member_actions'] ?? []) as $mid) {
                $mid = (string) $mid;
                $surf = (string) ($g['surface_id'] ?? '');
                if (isset($actionLoc[$mid]) && $actionLoc[$mid] !== $surf) {
                    $errors[] = 'member_wrong_surface:'.$mid;
                }
            }
        }

        return $errors;
    }

    /** @return array<string, mixed>|null */
    protected function getByHubActionPublic(string $hubActionId): ?array
    {
        return $this->getByHubAction($hubActionId);
    }

    public function surfaceSection(string $surface): string
    {
        if ($this->isCustomSurface($surface)) {
            return str_starts_with($surface, 'user_custom_') ? 'user' : 'admin';
        }
        if ($surface === 'user_main' || str_starts_with($surface, 'svc_menu_') || str_starts_with($surface, 'purchase_delivery_')) {
            return 'user';
        }

        return 'admin';
    }

    public function surfaceSupportsGroups(string $surface): bool
    {
        $s = (string) $surface;
        if ($this->isCustomSurface($s)) {
            return false;
        }
        if (str_starts_with($s, 'svc_menu_') || str_starts_with($s, 'purchase_delivery_')) {
            return false;
        }

        return $s !== '';
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{ok:bool, message?:string, data?:array<string, mixed>}
     */
    public function create(array $params): array
    {
        $parent = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($params['parent_surface'] ?? ''))) ?? '';
        if ($parent === '' || ! $this->surfaceSupportsGroups($parent)) {
            return ['ok' => false, 'message' => 'bad_parent_surface'];
        }

        $section = $this->surfaceSection($parent);
        $labelFa = trim((string) ($params['label_fa'] ?? ''));
        $labelEn = trim((string) ($params['label_en'] ?? ''));
        if ($labelFa === '' && $labelEn === '') {
            return ['ok' => false, 'message' => 'missing_label'];
        }
        if ($labelFa === '') {
            $labelFa = $labelEn;
        }
        if ($labelEn === '') {
            $labelEn = $labelFa;
        }

        $membersIn = isset($params['member_actions']) && is_array($params['member_actions']) ? $params['member_actions'] : [];
        $members = [];
        foreach ($membersIn as $mid) {
            $mid = trim((string) $mid);
            if ($mid === '' || $this->getByHubAction($mid)) {
                continue;
            }
            $members[] = $mid;
        }
        $members = array_values(array_unique($members));
        if ($members === []) {
            return ['ok' => false, 'message' => 'no_member_actions'];
        }

        $gid = 'g_'.substr(md5(uniqid((string) random_int(1, PHP_INT_MAX), true)), 0, 10);
        $prefix = $section === 'user' ? 'user' : 'admin';
        $surfaceId = $prefix.'_custom_'.$gid;
        $hubId = $prefix.'.hub.'.$gid;
        $textKey = 'btn.custom.'.$gid;

        $group = [
            'id' => $gid,
            'section' => $section,
            'parent_surface' => $parent,
            'surface_id' => $surfaceId,
            'hub_action_id' => $hubId,
            'text_key' => $textKey,
            'label_fa' => $labelFa,
            'label_en' => $labelEn,
            'member_actions' => $members,
        ];

        $this->persistText($textKey, $labelFa, $labelEn);

        $stored = $this->getStored();
        if (! isset($stored['custom_groups']) || ! is_array($stored['custom_groups'])) {
            $stored['custom_groups'] = [];
        }
        $stored['custom_groups'][$gid] = $group;

        if (! isset($stored['surfaces']) || ! is_array($stored['surfaces'])) {
            $stored['surfaces'] = [];
        }

        $parentRows = isset($stored['surfaces'][$parent]) && is_array($stored['surfaces'][$parent])
            ? $stored['surfaces'][$parent]
            : [];
        $newParent = $this->removeActionsFromRows($parentRows, $members);
        $newParent = $this->appendHubToRows($newParent, $hubId);
        $stored['surfaces'][$parent] = $newParent;

        $customRows = [];
        foreach ($members as $mid) {
            $customRows[] = [
                'id' => $mid,
                'enabled' => true,
                'glass' => false,
            ];
        }
        $stored['surfaces'][$surfaceId] = [$customRows];

        $this->settings->set('bot_ui_layout', $stored);

        return [
            'ok' => true,
            'data' => [
                'group' => $group,
                'uiLayout' => $this->exportLayout(),
            ],
        ];
    }

    /**
     * @return array{ok:bool, message?:string, data?:array<string, mixed>}
     */
    public function delete(string $groupId, bool $restoreParent = true): array
    {
        $g = $this->get($groupId);
        if (! $g) {
            return ['ok' => false, 'message' => 'not_found'];
        }

        $stored = $this->getStored();
        if (isset($stored['custom_groups'][$groupId])) {
            unset($stored['custom_groups'][$groupId]);
        }

        $surfaceId = (string) ($g['surface_id'] ?? '');
        $parent = (string) ($g['parent_surface'] ?? '');
        $hub = (string) ($g['hub_action_id'] ?? '');
        $members = array_values((array) ($g['member_actions'] ?? []));

        if (isset($stored['surfaces'][$surfaceId])) {
            unset($stored['surfaces'][$surfaceId]);
        }
        if (isset($stored['surfaces'][$parent]) && is_array($stored['surfaces'][$parent])) {
            $stored['surfaces'][$parent] = $this->removeActionsFromRows(
                $stored['surfaces'][$parent],
                array_merge([$hub], $members)
            );
            if ($restoreParent && $members !== []) {
                $row = [];
                foreach ($members as $mid) {
                    $row[] = [
                        'id' => $mid,
                        'enabled' => true,
                        'glass' => false,
                    ];
                }
                $stored['surfaces'][$parent][] = $row;
            }
        }

        $textKey = (string) ($g['text_key'] ?? '');
        if ($textKey !== '' && Schema::hasTable('svp_texts')) {
            DB::table('svp_texts')->where('key_name', $textKey)->delete();
        }

        $this->settings->set('bot_ui_layout', $stored);

        return [
            'ok' => true,
            'data' => ['uiLayout' => $this->exportLayout()],
        ];
    }

    /** @return array<string, mixed> */
    public function exportForDashboard(): array
    {
        $groups = [];
        foreach ($this->getAll() as $g) {
            $groups[$g['id']] = [
                'id' => $g['id'],
                'section' => $g['section'],
                'parentSurface' => $g['parent_surface'],
                'surfaceId' => $g['surface_id'],
                'hubActionId' => $g['hub_action_id'],
                'textKey' => $g['text_key'],
                'labelFa' => $g['label_fa'],
                'labelEn' => $g['label_en'],
                'memberActions' => $g['member_actions'],
            ];
        }

        return $groups;
    }

    /** @return array<string, mixed> */
    public function exportLayout(): array
    {
        $stored = $this->getStored();

        return [
            'version' => (int) ($stored['version'] ?? 1),
            'surfaces' => is_array($stored['surfaces'] ?? null) ? $stored['surfaces'] : [],
            'custom_groups' => $this->getAll(),
        ];
    }

    /** @return array<string, mixed> */
    protected function getStored(): array
    {
        $layout = $this->settings->get('bot_ui_layout', []);

        return is_array($layout) ? $layout : [];
    }

    /** @return array<string, mixed>|null */
    protected function getByHubAction(string $hubActionId): ?array
    {
        foreach ($this->getAll() as $g) {
            if ((string) ($g['hub_action_id'] ?? '') === $hubActionId) {
                return $g;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>|null
     */
    protected function normalizeGroup(string $id, array $raw): ?array
    {
        $gid = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($raw['id'] ?? $id))) ?? '';
        if ($gid === '') {
            return null;
        }
        $section = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($raw['section'] ?? 'user'))) ?? 'user';
        if (! in_array($section, ['user', 'admin'], true)) {
            $section = 'user';
        }
        $prefix = $section === 'user' ? 'user' : 'admin';
        $members = [];
        foreach ((array) ($raw['member_actions'] ?? []) as $m) {
            $m = (string) $m;
            if ($m !== '') {
                $members[] = $m;
            }
        }

        return [
            'id' => $gid,
            'section' => $section,
            'parent_surface' => preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($raw['parent_surface'] ?? 'user_main'))) ?: 'user_main',
            'surface_id' => (string) ($raw['surface_id'] ?? ($prefix.'_custom_'.$gid)),
            'hub_action_id' => (string) ($raw['hub_action_id'] ?? ($prefix.'.hub.'.$gid)),
            'text_key' => (string) ($raw['text_key'] ?? ('btn.custom.'.$gid)),
            'label_fa' => (string) ($raw['label_fa'] ?? ''),
            'label_en' => (string) ($raw['label_en'] ?? ''),
            'member_actions' => array_values(array_unique($members)),
        ];
    }

    /**
     * @param  array<int, array<int, array<string, mixed>>>  $rows
     * @param  array<int, string>  $ids
     * @return array<int, array<int, array<string, mixed>>>
     */
    protected function removeActionsFromRows(array $rows, array $ids): array
    {
        $flip = array_flip($ids);
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $built = [];
            foreach ($row as $cell) {
                if (! is_array($cell)) {
                    continue;
                }
                $aid = (string) ($cell['id'] ?? '');
                if ($aid === '' || isset($flip[$aid])) {
                    continue;
                }
                $built[] = $cell;
            }
            if ($built !== []) {
                $out[] = $built;
            }
        }

        return $out;
    }

    /**
     * @param  array<int, array<int, array<string, mixed>>>  $rows
     * @return array<int, array<int, array<string, mixed>>>
     */
    protected function appendHubToRows(array $rows, string $hubId): array
    {
        $rows[] = [[
            'id' => $hubId,
            'enabled' => true,
            'glass' => false,
        ]];

        return $rows;
    }

    protected function persistText(string $key, string $fa, string $en): void
    {
        if (! Schema::hasTable('svp_texts')) {
            return;
        }
        $payload = json_encode(['fa' => $fa, 'en' => $en], JSON_UNESCAPED_UNICODE);
        DB::table('svp_texts')->updateOrInsert(
            ['key_name' => $key],
            ['value' => $payload ?: $fa, 'updated_at' => now()]
        );
    }

    /** @return array<string, array<string, mixed>> */
    public static function get_all(): array
    {
        return app(self::class)->getAll();
    }

    /**
     * @param  array<string, mixed>  $group
     * @return array<int, array<int, string>>
     */
    public static function default_rows_for_group(array $group): array
    {
        $members = array_values((array) ($group['member_actions'] ?? []));
        if ($members === []) {
            return [];
        }

        return [$members];
    }

    /** @return array<int, string> */
    public static function hub_ids_for_parent(string $parentSurface): array
    {
        $out = [];
        foreach (self::get_all() as $g) {
            if ((string) ($g['parent_surface'] ?? '') === $parentSurface) {
                $out[] = (string) $g['hub_action_id'];
            }
        }

        return $out;
    }

    /** @return array<int, string> */
    public static function surface_action_ids(string $surface): array
    {
        $svc = app(self::class);
        if ($svc->isCustomSurface($surface)) {
            $g = $svc->getBySurface($surface);

            return $g ? array_values((array) ($g['member_actions'] ?? [])) : [];
        }
        $ids = UiActionRegistryService::surface_action_ids_static($surface);

        return array_values(array_unique(array_merge($ids, self::hub_ids_for_parent($surface))));
    }

    /** @return array<string, mixed>|null */
    public static function hub_action_def_by_id(string $actionId): ?array
    {
        $g = app(self::class)->getByHubAction($actionId);

        return $g ? self::hub_action_def($g) : null;
    }

    /** @param  array<string, mixed>  $group */
    public static function hub_action_def(array $group): array
    {
        $section = (string) ($group['section'] ?? 'user');
        $route = $section === 'user'
            ? ['user_submenu' => (string) $group['surface_id']]
            : ['admin_submenu' => (string) $group['surface_id']];

        return [
            'surface' => (string) $group['parent_surface'],
            'kind' => 'reply',
            'text_key' => (string) $group['text_key'],
            'glass_default' => false,
            'max_len' => 256,
            'route' => $route,
            'is_custom_hub' => true,
        ];
    }
}
