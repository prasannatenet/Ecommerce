<?php

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVideo;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

function makeVideoAdminUser(): User
{
    Role::firstOrCreate(['name' => 'admin']);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

function videoTestProduct(): Product
{
    return Product::create([
        'name'         => 'Video Test Ring ' . uniqid(),
        'slug'         => 'video-test-ring-' . uniqid(),
        'base_price'   => 1500,
        'product_type' => 'simple',
        'is_active'    => true,
    ]);
}

test('admin can upload a short video along with the product images', function () {
    Storage::fake('public');

    $admin = makeVideoAdminUser();
    $fixtureImage = public_path('frontend/assets/mc.png');

    $this->actingAs($admin)
        ->post(route('admin.products.store'), [
            'name'          => 'Video Upload Bracelet',
            'audience'      => 'women',
            'material_type' => 'gold',
            'slug'          => 'video-upload-bracelet',
            'base_price'    => 2500,
            'product_type'  => 'simple',
            'is_active'     => '1',
            'primary_image' => new UploadedFile($fixtureImage, 'primary.png', 'image/png', null, true),
            'images'        => [new UploadedFile($fixtureImage, 'gallery.png', 'image/png', null, true)],
            'videos'        => [UploadedFile::fake()->create('clip.mp4', 512, 'video/mp4')],
        ])
        ->assertRedirect();

    $product = Product::where('slug', 'video-upload-bracelet')->firstOrFail();

    $video = $product->videos()->first();

    expect($video)->not->toBeNull()
        ->and($video->is_primary)->toBeTrue()
        ->and($video->path)->toStartWith('products/videos/');

    Storage::disk('public')->assertExists($video->path);

    // Images still upload alongside the video.
    expect($product->images()->count())->toBe(2);
});

test('non video files are rejected for the videos field', function () {
    Storage::fake('public');

    $admin = makeVideoAdminUser();

    $this->actingAs($admin)
        ->from(route('admin.products.create'))
        ->post(route('admin.products.store'), [
            'name'          => 'Bad Video Product',
            'audience'      => 'women',
            'material_type' => 'gold',
            'slug'          => 'bad-video-product',
            'base_price'    => 900,
            'product_type'  => 'simple',
            'is_active'     => '1',
            'videos'        => [UploadedFile::fake()->create('notes.pdf', 16, 'application/pdf')],
        ])
        ->assertSessionHasErrors('videos.0');

    expect(ProductVideo::count())->toBe(0);
});

test('product page renders the uploaded video inside the gallery', function () {
    Storage::fake('public');

    $product = videoTestProduct();

    $video = ProductVideo::create([
        'product_id' => $product->id,
        'path'       => 'products/videos/clip.mp4',
        'is_primary' => true,
    ]);

    $response = $this->get(route('product.show', $product->slug));

    $response->assertOk();
    $response->assertSee('id="mainProductVideo"', false);
    $response->assertSee('switchToVideo', false);
    // Playback is streamed through Laravel (206 partial content), not the static file URL.
    $response->assertSee(route('product.videos.stream', $video), false);

    // The thumbnail requests a real frame (not a black box) and carries a play badge.
    $response->assertSee(route('product.videos.stream', $video) . '#t=0.1', false);
    $response->assertSee('thumb-play-badge', false);
});

test('product videos are streamed with byte range support so playback starts immediately', function () {
    Storage::fake('public');

    $product = videoTestProduct();
    $video   = ProductVideo::create([
        'product_id' => $product->id,
        'path'       => 'products/videos/clip.mp4',
        'is_primary' => true,
    ]);

    Storage::disk('public')->put('products/videos/clip.mp4', str_repeat('a', 2048));

    $url = route('product.videos.stream', $video);

    $full = $this->get($url);
    $full->assertOk();
    expect($full->headers->get('accept-ranges'))->toBe('bytes');

    // A browser asks for the metadata in a range; Laravel must answer 206, not the
    // whole 24 MB file, otherwise playback stalls until the download finishes.
    $partial = $this->get($url, ['Range' => 'bytes=0-99']);

    $partial->assertStatus(206);
    expect($partial->headers->get('content-range'))->toBe('bytes 0-99/2048')
        ->and($partial->headers->get('content-length'))->toBe('100');

    // A range that starts past the end of the file is rejected (RFC 7233).
    $this->get($url, ['Range' => 'bytes=99999-999999'])->assertStatus(416);
});

test('video and images together show the image first and keep the player behind its thumbnail', function () {
    Storage::fake('public');

    $product = videoTestProduct();

    ProductImage::create([
        'product_id' => $product->id,
        'path'       => 'products/gallery.png',
        'is_primary' => false,
    ]);

    ProductVideo::create([
        'product_id' => $product->id,
        'path'       => 'products/videos/clip.mp4',
        'is_primary' => true,
    ]);

    $html = $this->get(route('product.show', $product->slug))->assertOk()->getContent();

    $playerWindow = substr($html, (int) strpos($html, 'id="mainProductVideo"'), 300);

    // The image is the default media; the video waits until its thumb is clicked.
    expect($playerWindow)->toContain('controls')
        ->and($playerWindow)->toContain('display:none')
        ->and($html)->toContain('gallery-thumb-video');
});

test('a product with only a video displays the player instead of the placeholder image', function () {
    Storage::fake('public');

    $product = videoTestProduct();

    ProductVideo::create([
        'product_id' => $product->id,
        'path'       => 'products/videos/sole-clip.mp4',
        'is_primary' => true,
    ]);

    $html = $this->get(route('product.show', $product->slug))->assertOk()->getContent();

    $placeholderWindow = substr($html, (int) strpos($html, 'id="mainProductImg"'), 200);
    $playerWindow = substr($html, (int) strpos($html, 'id="mainProductVideo"'), 300);

    expect($placeholderWindow)->toContain('main-product-img-placeholder')
        ->and($placeholderWindow)->toContain('display:none')
        ->and($playerWindow)->not->toContain('display:none')
        ->and($playerWindow)->toContain('controls');
});

test('admin can delete a product video and the primary flag moves to the next clip', function () {
    Storage::fake('public');

    $admin   = makeVideoAdminUser();
    $product = videoTestProduct();

    Storage::disk('public')->put('products/videos/first.mp4', 'first');
    Storage::disk('public')->put('products/videos/second.mp4', 'second');

    $first = ProductVideo::create([
        'product_id' => $product->id,
        'path'       => 'products/videos/first.mp4',
        'is_primary' => true,
    ]);

    $second = ProductVideo::create([
        'product_id' => $product->id,
        'path'       => 'products/videos/second.mp4',
        'is_primary' => false,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.products.videos.destroy', [$product, $first]))
        ->assertRedirect();

    expect(ProductVideo::find($first->id))->toBeNull()
        ->and($second->fresh()->is_primary)->toBeTrue();

    Storage::disk('public')->assertMissing('products/videos/first.mp4');
    Storage::disk('public')->assertExists('products/videos/second.mp4');
});
