<?php

namespace App\Http\Controllers;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Geometry\Factories\CircleFactory;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class AvatarController extends Controller
{
    // Path to base images and total count.
    protected string $imagesPath = 'images/avatars/v1/';
    protected int $totalImages   = 58;

    // Pastel colour palette.
    protected array $colors = [
        '#F794E9','#FF93B6','#F5A67E','#F9C584','#A5ED9A','#6CF2A2','#5FECEE',
        '#87C6ED','#AAC7DE','#B3E0E0','#C8B7FA','#C7B8D2','#C7C0B7','#969CA8',
        '#C1D2BD','#DCACA8','#DFC39E','#5B5B5B',
    ];

    /**
     * Groups of image numbers keyed by emotion.  Adjust these lists once you’ve
     * examined your PNGs and decided which faces correspond to each emotion.
     */
    protected array $emotionGroups = [
        'happy'     => [1,2,3,4,5,6,7,8],
        'sad'       => [9,10,11,12,13],
        'angry'     => [14,15,16,17,18],
        'surprised' => [19,20,21,22,23],
        'neutral'   => [24,25,26,27,28,29,30],
        // Add more emotions here as needed.
    ];

    // Existing avatar generator (name only).
    public function generate(string $name = null)
    {
        $colorIndex = request()->get('color') ?? null;
        if ($colorIndex < 0 || $colorIndex > count($this->colors) - 1) {
            $colorIndex = null;
        }
        if (!$name) {
            $name = Str::random(6);
            return redirect()->route('avatar.generate', ['name' => $name, 'colorIndex' => $colorIndex]);
        }

        $hash = md5($name);
        $imageIndex    = hexdec(substr($hash, 0, 8)) % $this->totalImages;
        $selectedImage = $this->imagesPath.($imageIndex + 1).'.png';

        if (!$colorIndex) {
            $colorIndex = hexdec(substr($hash, 8, 8)) % count($this->colors);
        }
        $selectedColor = $this->colors[$colorIndex];

        return $this->renderAvatar($selectedImage, $selectedColor, $hash, (string)$colorIndex);
    }

    // New method: generate an agent avatar using a name and emotion.
    public function generateAgent(string $name = null, string $emotion = null)
    {
        $colorIndex = request()->get('color') ?? null;
        if ($colorIndex < 0 || $colorIndex > count($this->colors) - 1) {
            $colorIndex = null;
        }
        if (!$name) {
            $name = Str::random(6);
            return redirect()->route('agent.generate', ['name' => $name, 'emotion' => $emotion, 'colorIndex' => $colorIndex]);
        }

        // Fall back to neutral if the emotion is unknown or missing.
        $emotion = strtolower($emotion ?? 'neutral');
        $group   = $this->emotionGroups[$emotion] ?? $this->emotionGroups['neutral'];

        $hash = md5($name);
        // Pick a face within the emotion group based on the name.
        $imageIndex  = hexdec(substr($hash, 0, 8)) % count($group);
        $imageNumber = $group[$imageIndex];
        $selectedImage = $this->imagesPath.$imageNumber.'.png';

        if (!$colorIndex) {
            $colorIndex = hexdec(substr($hash, 8, 8)) % count($this->colors);
        }
        $selectedColor = $this->colors[$colorIndex];

        // Include the emotion in the cache key so different emotions for the same name
        // produce different files.
        $cacheKey = "{$hash}_{$emotion}_{$colorIndex}";

        return $this->renderAvatar($selectedImage, $selectedColor, $cacheKey, (string)$colorIndex);
    }

    /**
     * Compose and cache the WebP avatar.  This method draws a coloured circle,
     * overlays the selected PNG, optionally flips it, scales it down to 128×128
     * and saves the result to `storage/app/public/avatars/generated`.
     */
    protected function renderAvatar(string $imagePath, string $backgroundColour, string $hash, string $colorIndex)
    {
        $fileName = "{$hash}_{$colorIndex}.webp";
        $filePath = storage_path("app/public/avatars/generated/{$fileName}");

        if (file_exists($filePath)) {
            return response()->file($filePath)
                ->header('Content-Type', 'image/webp')
                ->header('Cache-Control', 'public, max-age=31536000, immutable');
        }

        $manager = new ImageManager(new Driver());
        $canvas  = $manager->create(1024, 1024);
        $canvas->drawCircle(512, 512, function (CircleFactory $circle) use ($backgroundColour) {
            $circle->radius(511);
            $circle->background($backgroundColour);
        });

        try {
            $selectedImage = $manager->read($imagePath);
            $selectedImage->resize(920, 920);
        } catch (\Exception $e) {
            abort(404, "Base image not found: {$imagePath}");
        }

        $canvas->place($selectedImage, 'center', 0, 10);
        if (hexdec(substr($hash, 16, 8)) % 2 === 0) {
            $canvas->flop();
        }

        $webp = $canvas->scale(128, 128)->sharpen(2)->toWebp(100);
        Storage::disk('public')->put("avatars/generated/{$fileName}", $webp);

        return response($webp)
            ->header('Content-Type', 'image/webp')
            ->header('Content-Disposition', 'inline; filename=\"avatar.webp\"')
            ->header('Cache-Control', 'public, max-age=31536000, immutable');
    }
}
