<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Support\Str;

class CategorySeeder extends BaseSeeder
{
    /**
     * The starting taxonomy for a digital-products marketplace.
     *
     * @var array<string, array<int, string>>
     */
    private const TREE = [
        'Ebook & Dokumen' => ['Ebook', 'Template Dokumen', 'Resume & CV'],
        'Desain Grafis' => ['Template Desain', 'Ilustrasi', 'Mockup', 'Preset & Filter'],
        'Software & Aplikasi' => ['Aplikasi Desktop', 'Plugin & Extension', 'Script & Source Code'],
        'Audio & Video' => ['Musik & Sound', 'Preset Video', 'Template Video'],
        'Kursus & Tutorial' => ['Kursus Online', 'Ebook Tutorial', 'Webinar Rekaman'],
        'Lisensi Digital' => ['Lisensi Software', 'Akun Premium'],
    ];

    public function run(): void
    {
        $order = 0;

        foreach (self::TREE as $parentName => $children) {
            $parent = Category::withTrashed()->firstOrCreate(
                ['slug' => Str::slug($parentName)],
                [
                    'name' => $parentName,
                    'sort_order' => $order++,
                    'is_active' => true,
                ],
            );

            // Materialised path, used to fetch a whole subtree in one query.
            $parent->path = (string) $parent->id;
            $parent->save();

            $childOrder = 0;

            foreach ($children as $childName) {
                $child = Category::withTrashed()->firstOrCreate(
                    ['slug' => Str::slug($parentName.'-'.$childName)],
                    [
                        'parent_id' => $parent->id,
                        'name' => $childName,
                        'sort_order' => $childOrder++,
                        'is_active' => true,
                    ],
                );

                $child->path = $parent->id.'.'.$child->id;
                $child->save();
            }
        }

        $this->info(sprintf(
            'Kategori siap: %d kategori.',
            Category::count(),
        ));
    }
}
