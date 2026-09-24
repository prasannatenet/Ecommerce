<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\ReviewImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

function reviewTestProduct(): Product
{
    return Product::create([
        'name' => 'Review Test Product',
        'slug' => 'review-test-product-'.uniqid(),
        'base_price' => 100,
        'is_active' => true,
    ]);
}

/**
 * Give a user a (delivered by default) order containing the product, so they
 * are allowed to review it.
 */
function reviewImageUpload(string $name): UploadedFile
{
    return new UploadedFile(
        public_path('frontend/assets/mc.png'),
        $name,
        'image/png',
        null,
        true
    );
}

function purchaseProductFor(User $user, Product $product, string $status = 'delivered'): Order
{
    $order = Order::create([
        'user_id' => $user->id,
        'status' => $status,
        'payment_method' => 'cod',
        'payment_status' => 'pending',
        'total' => 100,
        'shipping_address' => ['pincode' => '600001'],
        'billing_address' => ['pincode' => '600001'],
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'sku' => 'REVIEW-TEST-SKU',
        'unit_price' => 100,
        'quantity' => 1,
        'line_total' => 100,
    ]);

    return $order;
}

it('lets a logged in user post a review and see it immediately on the product page', function () {
    /** @var User $user */
    $user = User::factory()->create();
    $product = reviewTestProduct();
    purchaseProductFor($user, $product);

    actingAs($user);

    // Non-AJAX post
    post(route('products.reviews.store', $product), [
        'rating' => '5',
        'comment' => 'Absolutely love this product!',
    ])->assertRedirect()
        ->assertSessionHas('review_success', 'Thanks! Your review has been posted.');

    $review = Review::where('product_id', $product->id)->where('user_id', $user->id)->first();

    expect($review)->not->toBeNull();
    expect((int) $review->rating)->toBe(5);
    expect($review->comment)->toBe('Absolutely love this product!');

    // The review is auto-visible in the review section on the product detail page.
    $page = get(route('product.show', $product->slug));

    $page->assertOk();
    $page->assertSee('Absolutely love this product!');
    $page->assertSee($user->name);
    $page->assertSee('1 Customer Review');
});

it('returns the freshly rendered review list over ajax so it appears without a page reload', function () {
    /** @var User $user */
    $user = User::factory()->create();
    $product = reviewTestProduct();
    purchaseProductFor($user, $product);

    actingAs($user);

    $response = post(route('products.reviews.store', $product), [
        'rating' => '4',
        'comment' => 'Great value for money.',
    ], [
        'Accept' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('reviews_count', 1)
        ->assertJsonPath('average_rating', 4);

    $payload = $response->json();

    expect($payload['reviews_html'])->toContain('Great value for money.');
    expect($payload['reviews_html'])->toContain($user->name);
});

it('updates the same review when the same user reviews the same product again', function () {
    /** @var User $user */
    $user = User::factory()->create();
    $product = reviewTestProduct();
    purchaseProductFor($user, $product);

    actingAs($user);

    post(route('products.reviews.store', $product), [
        'rating' => '5',
        'comment' => 'First review.',
    ])->assertRedirect();

    post(route('products.reviews.store', $product), [
        'rating' => '2',
        'comment' => 'Updated review.',
    ])->assertRedirect();

    expect(Review::where('product_id', $product->id)->count())->toBe(1);

    $review = Review::where('product_id', $product->id)->where('user_id', $user->id)->first();

    expect((int) $review->rating)->toBe(2);
    expect($review->comment)->toBe('Updated review.');
});

it('validates rating and comment when posting a review', function () {
    /** @var User $user */
    $user = User::factory()->create();
    $product = reviewTestProduct();
    purchaseProductFor($user, $product);

    actingAs($user);

    post(route('products.reviews.store', $product), [
        'rating' => '9',
        'comment' => 'Way too high a rating.',
    ], [
        'Accept' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->assertStatus(422)
        ->assertJsonValidationErrorFor('rating');

    post(route('products.reviews.store', $product), [
        'rating' => '3',
        'comment' => '',
    ], [
        'Accept' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->assertStatus(422)
        ->assertJsonValidationErrorFor('comment');

    expect(Review::count())->toBe(0);
});

it('requires login before a user can submit a review', function () {
    $product = reviewTestProduct();

    post(route('products.reviews.store', $product), [
        'rating' => '5',
        'comment' => 'Nice product.',
    ])->assertRedirectToRoute('login');

    expect(Review::count())->toBe(0);
});

it('shows the review form only to logged in buyers and a login prompt to guests', function () {
    $product = reviewTestProduct();

    $guestPage = get(route('product.show', $product->slug));

    $guestPage->assertOk();
    $guestPage->assertSee('Log in');
    $guestPage->assertDontSeeHtml('id="reviewForm"');

    /** @var User $user */
    $user = User::factory()->create();

    actingAs($user);

    // Logged in but has not purchased this product yet — no review form.
    $nonBuyerPage = get(route('product.show', $product->slug));

    $nonBuyerPage->assertOk();
    $nonBuyerPage->assertDontSeeHtml('id="reviewForm"');
    $nonBuyerPage->assertSee('after purchasing');

    // Once the user buys the product, the review form appears.
    purchaseProductFor($user, $product);

    $buyerPage = get(route('product.show', $product->slug));

    $buyerPage->assertOk();
    $buyerPage->assertSeeHtml('id="reviewForm"');
});

it('lets the review author update their own review', function () {
    /** @var User $user */
    $user = User::factory()->create();
    $product = reviewTestProduct();
    purchaseProductFor($user, $product);

    actingAs($user);

    post(route('products.reviews.store', $product), [
        'rating' => '4',
        'comment' => 'Good product.',
    ])->assertRedirect();

    /** @var Review $review */
    $review = Review::where('product_id', $product->id)->where('user_id', $user->id)->first();

    put(route('products.reviews.update', [$product, $review]), [
        'rating' => '5',
        'comment' => 'Actually, this is excellent!',
    ], [
        'Accept' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('reviews_count', 1)
        ->assertJsonPath('average_rating', 5);

    $review->refresh();

    expect((int) $review->rating)->toBe(5);
    expect($review->comment)->toBe('Actually, this is excellent!');
});

it('does not let another user update someone elses review', function () {
    /** @var User $author */
    $author = User::factory()->create();
    /** @var User $other */
    $other = User::factory()->create();
    $product = reviewTestProduct();
    purchaseProductFor($author, $product);

    actingAs($author);

    post(route('products.reviews.store', $product), [
        'rating' => '4',
        'comment' => 'Mine.',
    ])->assertRedirect();

    /** @var Review $review */
    $review = Review::where('product_id', $product->id)->where('user_id', $author->id)->first();

    actingAs($other);

    put(route('products.reviews.update', [$product, $review]), [
        'rating' => '1',
        'comment' => 'Hacked!',
    ], [
        'Accept' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->assertStatus(403);

    $review->refresh();

    expect((int) $review->rating)->toBe(4);
    expect($review->comment)->toBe('Mine.');
});

it('lets the review author delete their own review', function () {
    /** @var User $user */
    $user = User::factory()->create();
    $product = reviewTestProduct();
    purchaseProductFor($user, $product);

    actingAs($user);

    post(route('products.reviews.store', $product), [
        'rating' => '3',
        'comment' => 'Okay product.',
    ])->assertRedirect();

    /** @var Review $review */
    $review = Review::where('product_id', $product->id)->where('user_id', $user->id)->first();

    delete(route('products.reviews.destroy', [$product, $review]), [], [
        'Accept' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('reviews_count', 0);

    expect(Review::count())->toBe(0);
});

it('does not let another user delete someone elses review', function () {
    /** @var User $author */
    $author = User::factory()->create();
    /** @var User $other */
    $other = User::factory()->create();
    $product = reviewTestProduct();
    purchaseProductFor($author, $product);

    actingAs($author);

    post(route('products.reviews.store', $product), [
        'rating' => '5',
        'comment' => 'Mine.',
    ])->assertRedirect();

    /** @var Review $review */
    $review = Review::where('product_id', $product->id)->where('user_id', $author->id)->first();

    actingAs($other);

    delete(route('products.reviews.destroy', [$product, $review]), [], [
        'Accept' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->assertStatus(403);

    expect(Review::count())->toBe(1);
});

it('shows edit and delete actions only to the review author', function () {
    /** @var User $author */
    $author = User::factory()->create();
    $product = reviewTestProduct();
    purchaseProductFor($author, $product);

    actingAs($author);

    post(route('products.reviews.store', $product), [
        'rating' => '5',
        'comment' => 'My own review.',
    ])->assertRedirect();

    // The author sees the edit/delete actions (data-rating only appears on the edit button).
    get(route('product.show', $product->slug))
        ->assertOk()
        ->assertSeeHtml('data-rating');

    // Another logged-in user does not see them on that review.
    /** @var User $other */
    $other = User::factory()->create();

    actingAs($other);

    get(route('product.show', $product->slug))
        ->assertOk()
        ->assertDontSeeHtml('data-rating');
});

it('uploads and displays up to three review images', function () {
    Storage::fake('public');

    /** @var User $user */
    $user = User::factory()->create();
    $product = reviewTestProduct();
    purchaseProductFor($user, $product);

    actingAs($user);

    $response = post(route('products.reviews.store', $product), [
        'rating' => '5',
        'comment' => 'The photos show the finish beautifully.',
        'images' => [
            reviewImageUpload('one.png'),
            reviewImageUpload('two.png'),
            reviewImageUpload('three.png'),
        ],
    ], [
        'Accept' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ]);

    $response->assertOk()->assertJsonPath('success', true);

    $review = Review::where('product_id', $product->id)->where('user_id', $user->id)->firstOrFail();
    expect($review->images)->toHaveCount(3);

    foreach ($review->images as $image) {
        expect($image->path)->toStartWith('reviews/'.$review->id.'/');
        Storage::disk('public')->assertExists($image->path);
    }

    expect($response->json('reviews_html'))
        ->toContain('review-image-trigger')
        ->toContain($review->images->first()->url);
});

it('rejects a fourth review image and unsupported file types', function () {
    Storage::fake('public');

    /** @var User $user */
    $user = User::factory()->create();
    $product = reviewTestProduct();
    purchaseProductFor($user, $product);

    actingAs($user);

    post(route('products.reviews.store', $product), [
        'rating' => '4',
        'comment' => 'Too many photos.',
        'images' => [
            reviewImageUpload('one.png'),
            reviewImageUpload('two.png'),
            reviewImageUpload('three.png'),
            reviewImageUpload('four.png'),
        ],
    ], [
        'Accept' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->assertStatus(422)->assertJsonValidationErrorFor('images');

    post(route('products.reviews.store', $product), [
        'rating' => '4',
        'comment' => 'Unsupported attachment.',
        'images' => [UploadedFile::fake()->create('notes.pdf', 20, 'application/pdf')],
    ], [
        'Accept' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->assertStatus(422)->assertJsonValidationErrorFor('images.0');

    expect(Review::count())->toBe(0)
        ->and(ReviewImage::count())->toBe(0);
});

it('lets the author add and remove images while editing without exceeding three', function () {
    Storage::fake('public');

    /** @var User $user */
    $user = User::factory()->create();
    $product = reviewTestProduct();
    purchaseProductFor($user, $product);

    actingAs($user);

    post(route('products.reviews.store', $product), [
        'rating' => '4',
        'comment' => 'Initial review.',
        'images' => [
            reviewImageUpload('initial-one.png'),
            reviewImageUpload('initial-two.png'),
        ],
    ])->assertRedirect();

    $review = Review::where('product_id', $product->id)->where('user_id', $user->id)->firstOrFail();
    $removeImage = $review->images()->firstOrFail();
    $removedPath = $removeImage->path;

    put(route('products.reviews.update', [$product, $review]), [
        'rating' => '5',
        'comment' => 'Updated with replacements.',
        'remove_image_ids' => [$removeImage->id],
        'images' => [
            reviewImageUpload('replacement-one.png'),
            reviewImageUpload('replacement-two.png'),
        ],
    ], [
        'Accept' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->assertOk()->assertJsonPath('success', true);

    expect($review->fresh()->images)->toHaveCount(3)
        ->and(ReviewImage::find($removeImage->id))->toBeNull();
    Storage::disk('public')->assertMissing($removedPath);
    foreach ($review->fresh()->images as $image) {
        Storage::disk('public')->assertExists($image->path);
    }

    put(route('products.reviews.update', [$product, $review]), [
        'rating' => '5',
        'comment' => 'This would exceed the three-image limit.',
        'images' => [reviewImageUpload('one-too-many.png')],
    ], [
        'Accept' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->assertStatus(422)->assertJsonValidationErrorFor('images');

    expect($review->fresh()->images)->toHaveCount(3);
});

it('does not let an author remove an image belonging to another review', function () {
    Storage::fake('public');

    /** @var User $author */
    $author = User::factory()->create();
    /** @var User $otherAuthor */
    $otherAuthor = User::factory()->create();
    $product = reviewTestProduct();
    $otherProduct = reviewTestProduct();
    purchaseProductFor($author, $product);
    purchaseProductFor($otherAuthor, $otherProduct);

    actingAs($author);

    post(route('products.reviews.store', $product), [
        'rating' => '5',
        'comment' => 'My review.',
    ])->assertRedirect();
    $review = Review::where('product_id', $product->id)->where('user_id', $author->id)->firstOrFail();
    $otherReview = Review::create([
        'product_id' => $otherProduct->id,
        'user_id' => $otherAuthor->id,
        'rating' => 4,
        'comment' => 'Another customer review.',
    ]);
    $otherImage = ReviewImage::create([
        'review_id' => $otherReview->id,
        'path' => 'reviews/'.$otherReview->id.'/protected.png',
    ]);
    Storage::disk('public')->put($otherImage->path, 'protected');

    put(route('products.reviews.update', [$product, $review]), [
        'rating' => '5',
        'comment' => 'Attempted image removal.',
        'remove_image_ids' => [$otherImage->id],
    ], [
        'Accept' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->assertStatus(422)->assertJsonValidationErrorFor('remove_image_ids.0');

    expect(ReviewImage::find($otherImage->id))->not->toBeNull();
    Storage::disk('public')->assertExists($otherImage->path);
});

it('deletes stored images when the author deletes the review', function () {
    Storage::fake('public');

    /** @var User $user */
    $user = User::factory()->create();
    $product = reviewTestProduct();
    purchaseProductFor($user, $product);

    actingAs($user);

    post(route('products.reviews.store', $product), [
        'rating' => '3',
        'comment' => 'Review with a photo.',
        'images' => [reviewImageUpload('delete-me.png')],
    ])->assertRedirect();

    $review = Review::where('product_id', $product->id)->where('user_id', $user->id)->firstOrFail();
    $path = $review->images()->firstOrFail()->path;
    Storage::disk('public')->assertExists($path);

    delete(route('products.reviews.destroy', [$product, $review]), [], [
        'Accept' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->assertOk();

    expect(Review::count())->toBe(0)
        ->and(ReviewImage::count())->toBe(0);
    Storage::disk('public')->assertMissing($path);
});

it('does not let a user review a product they have not purchased', function () {
    /** @var User $user */
    $user = User::factory()->create();
    $product = reviewTestProduct();

    actingAs($user);

    post(route('products.reviews.store', $product), [
        'rating' => '5',
        'comment' => 'Never bought this.',
    ], [
        'Accept' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->assertStatus(403);

    expect(Review::count())->toBe(0);
});

it('does not count cancelled orders as a purchase for reviewing', function () {
    /** @var User $user */
    $user = User::factory()->create();
    $product = reviewTestProduct();

    purchaseProductFor($user, $product, 'cancelled');

    actingAs($user);

    post(route('products.reviews.store', $product), [
        'rating' => '4',
        'comment' => 'Order was cancelled.',
    ], [
        'Accept' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->assertStatus(403);

    expect(Review::count())->toBe(0);
});
