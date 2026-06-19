<?php

namespace App\Modules;

class ModuleManager
{
    /** @var array<string, bool> */
    protected array $enabled = [];

    public function __construct()
    {
        foreach (config('modules.modules', []) as $key => $module) {
            $this->enabled[$key] = (bool) ($module['enabled'] ?? false);
        }
        $this->enabled['core'] = true;
    }

    public function isEnabled(string $key): bool
    {
        if ($key === 'core') {
            return true;
        }

        if (! ($this->enabled[$key] ?? false)) {
            return false;
        }

        foreach (config("modules.modules.{$key}.depends", []) as $dep) {
            if (! $this->isEnabled($dep)) {
                return false;
            }
        }

        $dependsAny = config("modules.modules.{$key}.depends_any", []);
        if (is_array($dependsAny) && $dependsAny !== []) {
            $any = false;
            foreach ($dependsAny as $dep) {
                if ($this->rawEnabled((string) $dep) && $this->depsSatisfied((string) $dep)) {
                    $any = true;
                    break;
                }
            }
            if (! $any) {
                return false;
            }
        }

        return true;
    }

    protected function rawEnabled(string $key): bool
    {
        return $key === 'core' || (bool) ($this->enabled[$key] ?? false);
    }

    protected function depsSatisfied(string $key): bool
    {
        foreach (config("modules.modules.{$key}.depends", []) as $dep) {
            if (! $this->isEnabled((string) $dep)) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    public function enabledKeys(): array
    {
        return array_values(array_filter(
            array_keys($this->enabled),
            fn (string $key) => $this->isEnabled($key)
        ));
    }

    /**
     * Topological order of module keys (dependencies first).
     *
     * @return list<string>
     */
    public function bootOrder(): array
    {
        $modules = config('modules.modules', []);
        $visited = [];
        $visiting = [];
        $order = [];

        $visit = function (string $key) use (&$visit, &$visited, &$visiting, &$order, $modules): void {
            if (isset($visited[$key])) {
                return;
            }
            if (isset($visiting[$key])) {
                return;
            }
            $visiting[$key] = true;
            foreach ($modules[$key]['depends'] ?? [] as $dep) {
                if (isset($modules[$dep])) {
                    $visit((string) $dep);
                }
            }
            unset($visiting[$key]);
            $visited[$key] = true;
            $order[] = $key;
        };

        foreach (array_keys($modules) as $key) {
            $visit((string) $key);
        }

        return $order;
    }

    /** @return array<string, array{key: string, label: string, enabled: bool}> */
    public function manifest(): array
    {
        $out = [];
        foreach (config('modules.modules', []) as $key => $module) {
            $out[$key] = [
                'key' => $key,
                'label' => (string) ($module['label'] ?? $key),
                'enabled' => $this->isEnabled($key),
            ];
        }

        return $out;
    }
}
