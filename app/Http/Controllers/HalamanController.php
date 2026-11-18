<?php

namespace App\Http\Controllers;

use App\Services\HalamanPublikService;
use Illuminate\Http\Response;
use Illuminate\View\View;

class HalamanController extends Controller
{
    public function __construct(private readonly HalamanPublikService $publik) {}

    public function beranda(): View
    {
        return view('publik.beranda', $this->publik->beranda());
    }

    public function sekolah(string $kode): View
    {
        $data = $this->publik->sekolah($kode);

        abort_if($data === null, 404);

        return view('publik.sekolah', $data);
    }

    public function kebijakanPrivasi(): View
    {
        return view('publik.kebijakan-privasi');
    }

    public function robots(): Response
    {
        $baris = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /akun',
            'Disallow: /panitia',
            'Disallow: /admin',
            'Disallow: /super',
            'Disallow: /yayasan',
            'Disallow: /masuk',
            'Disallow: /daftar-akun',
            'Disallow: /lupa-password',
            'Disallow: /reset-password',
            'Sitemap: '.route('sitemap'),
            '',
        ];

        return response(implode("\n", $baris), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public function sitemap(): Response
    {
        $url = [route('beranda'), route('kebijakan-privasi')];

        foreach ($this->publik->kodeSekolahAktif() as $kode) {
            $url[] = route('sekolah', strtolower($kode));
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($url as $alamat) {
            $xml .= '  <url><loc>'.htmlspecialchars($alamat, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</loc></url>'."\n";
        }

        $xml .= '</urlset>'."\n";

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
