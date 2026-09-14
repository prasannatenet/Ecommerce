<?php

use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

function reviewTestProduct(): Product
{
    return Product::create([
        'name' => 'Review Test Product',
        'slug' => 'review-test-product-' . uniqid(),
        'base_price' => 100,
        'is_active' => true,
    ]);
}

it('lets a logged in user post a review and see it immediately on the product page', function () {
    /** @var User $user */
    $user = User::factory()->create();
    $product = reviewTestProduct();

    actingAs($user);

    // Non-AJAX post
    post(route('products.reviews.store', $product), [
        'rating'  => '5',
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

    actingAs($user);

    $response = post(route('products.reviews.store', $product), [
        'rating'  => '4',
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

    actingAs($user);

    post(route('products.reviews.store', $product), [
        'rating'  => '5',
        'comment' => 'First review.',
    ])->assertRedirect();

    post(route('products.reviews.store', $product), [
        'rating'  => '2',
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

    actingAs($user);

    post(route('products.reviews.store', $product), [
        'rating'  => '9',
        'comment' => 'Way too high a rating.',
    ], [
        'Accept' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->assertStatus(422)
      ->assertJsonValidationErrorFor('rating');

    post(route('products.reviews.store', $product), [
        'rating'  => '3',
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
        'rating'  => '5',
        'comment' => 'Nice product.',
    ])->assertRedirectToRoute('login');

    expect(Review::count())->toBe(0);
});

it('shows the review form only to logged in users and a login prompt to guests', function () {
    $product = reviewTestProduct();

    $guestPage = get(route('product.show', $product->slug));

    $guestPage->assertOk();
    $guestPage->assertSee('Log in');
    $guestPage->assertDontSeeHtml('id="reviewForm"');

    /** @var User $user */
    $user = User::factory()->create();

    actingAs($user);

    $userPage = get(route('product.show', $product->slug));

    $userPage->assertOk();
    $userPage->assertSeeHtml('id="reviewForm"');
});

it('lets the review author update their own review', function () {
    /** @var User $user */
    $user = User::factory()->create();
    $product = reviewTestProduct();

    actingAs($user);

    post(route('products.reviews.store', $product), [
        'rating'  => '4',
        'comment' => 'Good product.',
    ])->assertRedirect();

    /** @var Review $review */
    $review = Review::where('product_id', $product->id)->where('user_id', $user->id)->first();

    put(route('products.reviews.update', [$product, $review]), [
        'rating'  => '5',
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

    actingAs($author);

    post(route('products.reviews.store', $product), [
        'rating'  => '4',
        'comment' => 'Mine.',
    ])->assertRedirect();

    /** @var Review $review */
    $review = Review::where('product_id', $product->id)->where('user_id', $author->id)->first();

    actingAs($other);

    put(route('products.reviews.update', [$product, $review]), [
        'rating'  => '1',
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

    actingAs($user);

    post(route('products.reviews.store', $product), [
        'rating'  => '3',
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

    actingAs($author);

    post(route('products.reviews.store', $product), [
        'rating'  => '5',
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

    actingAs($author);

    post(route('products.reviews.store', $product), [
        'rating'  => '5',
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