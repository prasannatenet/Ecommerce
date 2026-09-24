<?php

use App\Models\Product;
use App\Models\Review;
use App\Models\ReviewImage;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

function adminReviewProduct(string $name): Product
{
    return Product::create([
        'name' => $name,
        'slug' => Str::slug($name).'-'.uniqid(),
        'base_price' => 100,
        'product_type' => 'simple',
        'is_active' => true,
    ]);
}

function adminReviewAdmin(): User
{
    Role::firstOrCreate(['name' => 'admin']);
    $admin = User::factory()->create(['name' => 'Review Administrator']);
    $admin->assignRole('admin');

    return $admin;
}

it('lets an admin view every product review and its photos', function () {
    $admin = adminReviewAdmin();
    $customer = User::factory()->create(['name' => 'Photo Customer', 'email' => 'photo.customer@example.com']);
    $product = adminReviewProduct('Admin Reviewed Product');
    $review = Review::create([
        'product_id' => $product->id,
        'user_id' => $customer->id,
        'rating' => 5,
        'comment' => 'Beautiful finish and excellent quality.',
    ]);
    $image = ReviewImage::create([
        'review_id' => $review->id,
        'path' => 'reviews/admin-visible.jpg',
    ]);

    actingAs($admin);

    get(route('admin.reviews.index'))
        ->assertOk()
        ->assertSee('Product Reviews')
        ->assertSee($product->name)
        ->assertSee($customer->name)
        ->assertSee($customer->email)
        ->assertSee($review->comment)
        ->assertSee($image->url)
        ->assertSee('1', false)
        ->assertDontSee('Approve Review')
        ->assertDontSee('Reject Review')
        ->assertDontSee('Delete Review');
});

it('filters admin reviews by text, customer, product, rating, and date', function () {
    $admin = adminReviewAdmin();
    $customer = User::factory()->create(['name' => 'Filter Customer', 'email' => 'filter.customer@example.com']);
    $product = adminReviewProduct('Filterable Product');
    $otherProduct = adminReviewProduct('Unrelated Product');

    $matching = Review::create([
        'product_id' => $product->id,
        'user_id' => $customer->id,
        'rating' => 4,
        'comment' => 'Matches the special search phrase.',
    ]);
    Review::create([
        'product_id' => $otherProduct->id,
        'user_id' => $customer->id,
        'rating' => 2,
        'comment' => 'Should not appear in filtered results.',
    ]);

    actingAs($admin);

    get(route('admin.reviews.index', ['search' => 'special search']))
        ->assertOk()
        ->assertSee($matching->comment)
        ->assertDontSee('Should not appear in filtered results.');

    get(route('admin.reviews.index', ['search' => 'filter.customer@example.com']))
        ->assertOk()
        ->assertSee($matching->comment);

    get(route('admin.reviews.index', ['product_id' => $product->id, 'rating' => 4]))
        ->assertOk()
        ->assertSee($matching->comment)
        ->assertDontSee('Should not appear in filtered results.');

    get(route('admin.reviews.index', [
        'date_from' => now()->toDateString(),
        'date_to' => now()->toDateString(),
    ]))->assertOk()->assertSee($matching->comment);
});

it('paginates product reviews twenty at a time', function () {
    $admin = adminReviewAdmin();
    $customer = User::factory()->create();
    $product = adminReviewProduct('Pagination Product');

    foreach (range(1, 21) as $number) {
        Review::create([
            'product_id' => $product->id,
            'user_id' => $customer->id,
            'rating' => 5,
            'comment' => 'Pagination review '.$number,
        ]);
    }

    actingAs($admin);

    get(route('admin.reviews.index'))
        ->assertOk()
        ->assertSee('page=2', false)
        ->assertViewHas('reviews', function ($reviews): bool {
            return $reviews->count() === 20 && $reviews->total() === 21;
        });
});

it('blocks non-admin users from product reviews', function () {
    $customer = User::factory()->create();
    actingAs($customer);

    get(route('admin.reviews.index'))->assertRedirect(route('account.index'));
});

it('rejects invalid admin review filters', function () {
    $admin = adminReviewAdmin();
    actingAs($admin);

    get(route('admin.reviews.index', ['rating' => 9]))
        ->assertSessionHasErrors('rating');
});
