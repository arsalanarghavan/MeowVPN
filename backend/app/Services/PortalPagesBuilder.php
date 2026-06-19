<?php

namespace App\Services;

class PortalPagesBuilder
{
    public function __construct(protected SettingsStore $settings) {}

    /**
     * Laravel-native portal page choices (replaces WP page picker).
     *
     * @return list<array{id:int, title:string, path?:string}>
     */
    public function build(bool $isReseller): array
    {
        if ($isReseller) {
            return [];
        }

        $pages = $this->defaultPages();

        $raw = $this->settings->get('portal_pages', null);
        if (is_array($raw) && $raw !== []) {
            foreach ($raw as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $id = (int) ($row['id'] ?? 0);
                $title = trim((string) ($row['title'] ?? ''));
                $path = trim((string) ($row['path'] ?? ''));
                if ($id > 0 && $title !== '') {
                    $entry = ['id' => $id, 'title' => $title];
                    if ($path !== '') {
                        $entry['path'] = $path;
                    }
                    $pages[] = $entry;
                }
            }
        }

        $pageId = max(0, (int) $this->settings->get('portal_page_id', 0));
        if ($pageId > 0) {
            $title = trim((string) $this->settings->get('portal_page_title', 'Portal'));
            $legacy = ['id' => $pageId, 'title' => $title !== '' ? $title : 'Portal'];
            if (! $this->containsId($pages, $pageId)) {
                $pages[] = $legacy;
            }
        }

        return $pages;
    }

    /** @return list<array{id:int, title:string, path:string}> */
    private function defaultPages(): array
    {
        return [
            ['id' => 0, 'title' => 'Laravel Portal (/info)', 'path' => '/info'],
            ['id' => -1, 'title' => 'Subscription plain (/info?svp_p=1)', 'path' => '/info?svp_p=1'],
        ];
    }

    /** @param  list<array{id:int, title:string, path?:string}>  $pages */
    private function containsId(array $pages, int $id): bool
    {
        foreach ($pages as $p) {
            if ((int) ($p['id'] ?? -99) === $id) {
                return true;
            }
        }

        return false;
    }
}
