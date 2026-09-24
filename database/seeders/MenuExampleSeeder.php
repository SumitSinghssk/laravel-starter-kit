<?php

namespace Database\Seeders;

use App\Services\Menus\MenuBuilder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MenuExampleSeeder extends Seeder
{
    public function run(MenuBuilder $menus): void
    {
        $goku = $this->hoodie('menus/examples/goku-shadow-hoodie.png', [238, 236, 232], [120, 124, 130], 'SHADOW');
        $ace = $this->hoodie('menus/examples/ace-wanted-hoodie.png', [28, 42, 78], [146, 104, 70], 'WANTED');

        $row = fn (string $label, ?string $url, int $depth, array $extra = []) => [
            'label' => $label, 'url' => $url ?? '', 'depth' => $depth, 'style' => 'dropdown',
            'image' => '', 'description' => '', 'new_tab' => false, 'is_active' => true, ...$extra,
        ];

        $items = [
            $row('Categories', null, 0, ['style' => 'mega']),
            $row('Hoodies', null, 1),
            $row('Anime Hoodies', '/category/anime-hoodies', 2),
            $row('Religious Hoodie', '/category/religious-hoodie', 2),
            $row('Featured Products', null, 1),
            $row('Goku Shadow Hoodie – Dragon Ball Z', '/product/goku-shadow-hoodie', 2, ['image' => $goku, 'description' => 'Rs 1299']),
            $row('Portgas D. Ace Wanted Poster Hoodie', '/product/portgas-d-ace-wanted-hoodie', 2, ['image' => $ace, 'description' => 'Rs 1299']),
            $row('Information', null, 0),
            $row('About Us', '/about-us', 1),
            $row('Blog', '/blog', 1),
            $row('FAQ', '/faq', 1),
            $row('Contact', '/contact', 0),
        ];

        $menu = $menus->menu('header');
        [$clean, $errors] = $menus->validate($menu, ['items' => $items]);

        if ($errors) {
            throw new RuntimeException('Example menu is invalid: '.json_encode($errors));
        }

        $menus->save($menu, $clean);
    }

    private function hoodie(string $path, array $cloth, array $wall, string $print): string
    {
        $w = 400;
        $h = 500;
        $img = imagecreatetruecolor($w, $h);
        imageantialias($img, true);

        for ($y = 0; $y < $h; $y++) {
            $shade = 1 - $y / $h * 0.25;
            $color = imagecolorallocate($img, (int) ($wall[0] * $shade), (int) ($wall[1] * $shade), (int) ($wall[2] * $shade));
            imageline($img, 0, $y, $w, $y, $color);
        }
        for ($i = 0; $i < 900; $i++) {
            $n = random_int(-18, 18);
            $speck = imagecolorallocatealpha($img, max(0, min(255, $wall[0] + $n)), max(0, min(255, $wall[1] + $n)), max(0, min(255, $wall[2] + $n)), 60);
            imagefilledellipse($img, random_int(0, $w), random_int(0, $h), 3, 3, $speck);
        }

        $body = imagecolorallocate($img, ...$cloth);
        $dark = imagecolorallocate($img, (int) ($cloth[0] * 0.8), (int) ($cloth[1] * 0.8), (int) ($cloth[2] * 0.8));

        imagefilledpolygon($img, [70, 150, 130, 118, 150, 300, 95, 430, 40, 420], $dark);
        imagefilledpolygon($img, [330, 150, 270, 118, 250, 300, 305, 430, 360, 420], $dark);
        imagefilledellipse($img, 200, 118, 150, 130, $dark);
        imagefilledpolygon($img, [120, 120, 280, 120, 305, 470, 95, 470], $body);
        imagefilledellipse($img, 200, 112, 118, 100, $body);
        imagefilledellipse($img, 200, 128, 66, 58, $dark);
        imagefilledrectangle($img, 118, 420, 282, 470, $dark);

        $panel = imagecolorallocate($img, 22, 22, 26);
        $light = imagecolorallocate($img, 236, 226, 200);
        imagefilledrectangle($img, 150, 205, 250, 345, $panel);
        imagefilledellipse($img, 200, 262, 52, 60, $light);
        imagefilledpolygon($img, [168, 345, 232, 345, 222, 292, 178, 292], $light);
        $textWidth = imagefontwidth(5) * strlen($print);
        imagestring($img, 5, (int) (200 - $textWidth / 2), 212, $print, $light);

        ob_start();
        imagepng($img);
        imagedestroy($img);
        Storage::disk('public')->put($path, ob_get_clean());

        return '/storage/'.$path;
    }
}
