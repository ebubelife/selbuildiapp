<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\SupplierProfile;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addHour(), function () {
            $urls = [
                ['loc' => route('home'), 'priority' => '1.0', 'changefreq' => 'daily'],
                ['loc' => route('shop.index'), 'priority' => '0.9', 'changefreq' => 'daily'],
            ];

            foreach (Category::whereNull('parent_id')->get() as $category) {
                $urls[] = [
                    'loc' => route('shop.index', ['category' => $category->id]),
                    'priority' => '0.7',
                    'changefreq' => 'weekly',
                ];
            }

            Product::where('is_active', true)->select('slug', 'updated_at')->each(function (Product $product) use (&$urls) {
                $urls[] = [
                    'loc' => route('shop.show', $product),
                    'lastmod' => $product->updated_at->toAtomString(),
                    'priority' => '0.8',
                    'changefreq' => 'weekly',
                ];
            });

            SupplierProfile::whereNotNull('verified_at')->select('slug', 'updated_at')->each(function (SupplierProfile $supplier) use (&$urls) {
                $urls[] = [
                    'loc' => route('suppliers.show', $supplier),
                    'lastmod' => $supplier->updated_at->toAtomString(),
                    'priority' => '0.6',
                    'changefreq' => 'weekly',
                ];
            });

            // The XML declaration is built from concatenated pieces and
            // prepended here rather than written literally in either this
            // comment or sitemap.blade.php - the two-character PHP close
            // tag sequence ends PHP mode wherever it appears in a source
            // file, including inside a single-line comment, which is
            // exactly what broke this the first two times.
            $xmlDeclaration = '<'.'?xml version="1.0" encoding="UTF-8"?'.'>';

            return $xmlDeclaration."\n".view('sitemap', ['urls' => $urls])->render();
        });

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }
}
