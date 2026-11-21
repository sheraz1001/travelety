<?php

namespace App\Console\Commands;

use App\Models\EsimPlan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use XMLReader;

/** Imports all Airalo eSIM plans from the public product feed (streamed, ~3,000 plans). */
class ImportEsims extends Command
{
    protected $signature = 'esim:import {--file= : Use a local XML file instead of downloading}';

    protected $description = 'Import Airalo eSIM plans from the data feed';

    public const FEED = 'https://www.airalo.com/products.xml';

    public function handle(): int
    {
        @set_time_limit(900);
        $file = $this->option('file');
        if (! $file) {
            $file = storage_path('app/airalo-feed.xml');
            $res = Http::timeout(180)->withHeaders(['User-Agent' => 'Travelety/1.0'])->sink($file)->get(self::FEED);
            if (! $res->successful() || filesize($file) < 10000) {
                $this->error('Feed download failed: HTTP '.$res->status());

                return self::FAILURE;
            }
        }

        $reader = new XMLReader;
        $reader->open($file);
        $g = 'http://base.google.com/ns/1.0';
        $seen = [];
        $count = 0;
        $now = now();

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'item') {
                continue;
            }
            $node = $reader->expand(new \DOMDocument);
            $item = $node ? simplexml_import_dom($node) : null;
            if ($item === null || $item === false) {
                continue;
            }
            $x = $item->children($g);
            $row = $this->map($x);
            if (! $row) {
                continue;
            }
            $row['updated_at'] = $now;
            EsimPlan::updateOrCreate(['external_id' => $row['external_id']], $row + ['created_at' => $now]);
            $seen[] = $row['external_id'];
            $count++;
        }
        $reader->close();

        if ($count > 500) { // only deactivate when the feed looked complete
            EsimPlan::whereNotIn('external_id', $seen)->update(['is_active' => false]);
        }
        Cache::forget('sitemap.v2.esim');
        Cache::forget('esim.index.v2');
        $this->info("Imported {$count} eSIM plans.");

        return self::SUCCESS;
    }

    protected function map($x): ?array
    {
        $title = trim((string) $x->title);
        $link = trim((string) $x->link);
        $group = trim((string) $x->item_group_id);
        $price = (float) explode(' ', trim((string) $x->price))[0];
        if (! $title || ! $link || ! $group || $price <= 0) {
            return null;
        }
        if (trim((string) $x->availability) && trim((string) $x->availability) !== 'in_stock') {
            return null;
        }

        [$type, $slugPart] = array_pad(explode('-', $group, 2), 2, '');
        $parts = array_map('trim', explode('>', (string) $x->product_type));

        // "5 GB Turkey travel eSIM valid for 30 days" → "Turkey"
        $name = preg_match('/^(?:Unlimited(?:\s*GB)?|[\d.]+\s*GB)\s+(.+?)\s+travel eSIM/i', $title, $m) ? $m[1] : Str::headline($slugPart);
        $name = ['global (international)' => 'Global', 'World' => 'Global'][$name] ?? $name;

        $sizeLabel = trim((string) $x->custom_label_2);
        $dataGb = preg_match('/([\d.]+)\s*GB/i', $sizeLabel, $m) && ! str_starts_with(strtolower($sizeLabel), 'unlimited') ? (float) $m[1] : null;
        $days = (int) preg_replace('/\D/', '', (string) $x->custom_label_3);

        // operator from URL: /turkey-esim/merhaba-30days-5gb
        $path = trim((string) parse_url($link, PHP_URL_PATH), '/');
        $op = preg_replace('/-(in-)?\d+days.*$/', '', basename($path));
        $operator = Str::headline(str_replace('+', ' plus', $op));

        $details = [];
        foreach ($x->product_detail as $d) {
            $dc = $d->children('http://base.google.com/ns/1.0');
            $details[trim((string) $dc->attribute_name)] = trim((string) $dc->attribute_value);
        }

        return [
            'external_id' => trim((string) $x->id),
            'group_id' => $group,
            'group_type' => $type === 'region' ? (in_array($slugPart, ['world', 'global', 'discover-global']) ? 'global' : 'region') : 'country',
            'slug' => Str::slug($slugPart),
            'name' => $name,
            'region' => isset($parts[1]) ? (['europe & cis' => 'Europe & CIS', 'arabia & africa' => 'Middle East & Africa', 'asia pacific' => 'Asia Pacific', 'latin america' => 'Latin America', 'north america' => 'North America', 'global' => 'Global', 'other' => 'Other'][strtolower($parts[1])] ?? Str::title($parts[1])) : null,
            'operator' => $operator,
            'title' => Str::limit($title, 250, ''),
            'data_gb' => $dataGb,
            'data_label' => $dataGb ? rtrim(rtrim(number_format($dataGb, 1), '0'), '.').' GB' : 'Unlimited',
            'days' => max(1, $days),
            'price_usd' => $price,
            'plan_type' => $parts[3] ?? 'data',
            'coverage' => $details['Coverage'] ?? null,
            'networks' => isset($details['Supported networks']) ? Str::limit($details['Supported networks'], 490, '') : null,
            'other_info' => $details['Other info'] ?? null,
            'top_ups' => isset($details['Available top-ups']) ? strtolower($details['Available top-ups']) === 'yes' : null,
            'link' => $link,
            'image' => trim((string) $x->image_link) ?: null,
            'is_active' => true,
        ];
    }
}
