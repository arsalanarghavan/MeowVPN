<?php

namespace App\Http\Controllers;

use App\Services\DashboardBootBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class DashboardShellController extends Controller
{
    public function __invoke(Request $request, DashboardBootBuilder $bootBuilder, ?string $path = null): View
    {
        $dashPath = $path ?? '';
        if ($dashPath === 'login') {
            $dashPath = 'login';
        }

        $boot = $bootBuilder->forRequest($request, $dashPath === '' ? '' : explode('/', $dashPath)[0]);
        if ($path === 'login' || str_ends_with($request->path(), 'dashboard/login')) {
            $boot['dashPath'] = 'login';
        }

        $dist = env('FRONTEND_DIST', base_path('../frontend/dist'));
        $js = '/assets/index.js';
        $css = '/assets/index.css';
        if (File::isDirectory($dist)) {
            $jsFiles = File::glob($dist.'/assets/index*.js');
            $cssFiles = File::glob($dist.'/assets/index*.css');
            if (! empty($jsFiles)) {
                $js = '/assets/'.basename($jsFiles[0]);
            }
            if (! empty($cssFiles)) {
                $css = '/assets/'.basename($cssFiles[0]);
            }
        }

        $lang = (string) ($boot['lang'] ?? 'en');
        $rtl = ! empty($boot['isRtl']);

        return view('dashboard', [
            'boot' => $boot,
            'js' => $js,
            'css' => $css,
            'lang' => $lang,
            'rtl' => $rtl,
            'title' => ($boot['siteName'] ?? 'SimpleVPBot').' — Dashboard',
            'uiAccent' => (string) ($boot['uiAccent'] ?? 'default'),
        ]);
    }
}
