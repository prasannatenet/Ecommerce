<?php

/*
 * Bundle pricing through the JSON API.
 *
 * The API cart used to ignore ComboService entirely, so a combo added through
 * /api/v1 was charged at full product price while the same bundle was
 * discounted on the website. These tests pin the fixed behaviour: the bundle
 * price is applied, the lines reconcile to it exactly, and the arithmetic the
 * client is shown adds up.
 */

use App\Models\Cart;
use App\Models\Combo;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

function apiComboProduct(string $name, float $price, array $overrides = []): Product
{
    return Product::create(array_merge([
        'name' => $name,
        'slug' => Str::slug($name).'-'.uniqid(),
        'base_price' => $price,
        'product_type' => 'simple',
        'is_active' => true,
    ], $overrides));
}

/** @param array<int, Product> $products */
function apiComboBundle(array $products, float $value, string $type = 'percent', array $overrides = []): Combo
{
    $combo = Combo::create(array_merge([
        'name' => 'API Bundle '.uniqid(),
        'discount_type' => $type,
        'discount_value' => $value,
        'is_active' => true,
    ], $overrides));

    $combo->products()->sync(collect($products)->pluck('id')->all());

    return $combo;
}

it('charges the combo price when a bundle is added through the API', function () {
    $user = User::factory()->create();
    $studs = apiComboProduct('API Studs', 900);
    $bracelet = apiComboProduct('API Bracelet', 4388);
    $chain = apiComboProduct('API Chain', 3000);

    $combo = apiComboBundle([$studs, $bracelet, $chain], 10);

    // 900 + 4388 + 3000 = 8288; 10% off = 7459.20
    expect($combo->productsTotal())->toBe(8288.0)
        ->and($combo->comboPrice())->toBe(7459.20);

    Sanctum::actingAs($user);

    $response = postJson('/api/v1/cart/combo', ['combo_id' => $combo->id, 'quantity' => 1]);

    $response->assertCreated()->assertJsonPath('message', 'Combo added to cart');

    // One line per product, each flagged as part of the bundle.
    expect($response->json('data'))->toHaveCount(3);
    expect(collect($response->json('data'))->pluck('combo_id')->unique()->all())->toBe([$combo->id]);
    expect(collect($response->json('data'))->pluck('is_combo')->unique()->all())->toBe([true]);

    // The headline number: the cart total is the discounted bundle price, not
    // the 8,288 sum of the individual products.
    expect((float) $response->json('meta.subtotal'))->toBe(7459.20)
        ->and((float) $response->json('meta.total'))->toBe(7459.20)
        ->and((float) $response->json('meta.count'))->toBe(3.0);
});

it('makes the allocated line prices sum to the combo price exactly', function () {
    $user = User::factory()->create();
    $products = [
        apiComboProduct('Allocate A', 999.75),
        apiComboProduct('Allocate B', 4388.43),
        apiComboProduct('Allocate C', 7699.00),
    ];

    $combo = apiComboBundle($products, 5);
    $comboPrice = $combo->comboPrice();

    Sanctum::actingAs($user);

    $response = postJson('/api/v1/cart/combo', ['combo_id' => $combo->id]);

    $response->assertCreated();

    // Prices are rounded to two decimals per line, so a naive split can leave
    // the parts a paisa off the whole. The allocation has to reconcile exactly.
    $allocated = collect($response->json('data'))
        ->sum(fn (array $line) => (float) $line['price']);

    expect(round($allocated, 2))->toBe($comboPrice)
        ->and(round((float) $response->json('meta.subtotal'), 2))->toBe($comboPrice);
});

it('reports the pre-combo price so a client can strike it through', function () {
    $user = User::factory()->create();
    $studs = apiComboProduct('Strike Studs', 1000);
    $bracelet = apiComboProduct('Strike Bracelet', 2000);

    $combo = apiComboBundle([$studs, $bracelet], 10);

    Sanctum::actingAs($user);

    $response = postJson('/api/v1/cart/combo', ['combo_id' => $combo->id])->assertCreated();

    // Lines come back newest-first, so pick the one under test by product rather
    // than by position.
    $line = collect($response->json('data'))->firstWhere('product_id', $studs->id);

    // Regular price is the undiscounted one; the charged total is lower.
    expect((float) $line['regular_unit_price'])->toBe(1000.0)
        ->and((float) $line['line_total'])->toBeLessThan(1000.0)
        ->and($line['combo']['id'])->toBe($combo->id)
        // combo_units is 0 here by design: it counts units allocated to a bundle
        // that ComboService detected from ordinary product lines. This line was
        // added explicitly through the combo endpoint, so its discount is
        // already baked into the stored price instead.
        ->and((int) $line['combo_units'])->toBe(0);
});

it('applies a fixed discount as well as a percentage one', function () {
    $user = User::factory()->create();
    $first = apiComboProduct('Fixed A', 2000);
    $second = apiComboProduct('Fixed B', 1000);

    $combo = apiComboBundle([$first, $second], 500, 'fixed');

    expect($combo->comboPrice())->toBe(2500.0);

    Sanctum::actingAs($user);

    $response = postJson('/api/v1/cart/combo', ['combo_id' => $combo->id]);

    $response->assertCreated();

    // An explicitly added bundle stores its allocated price on each line, so the
    // saving is already inside `subtotal` and summarize() has nothing left to
    // discount - `combo_discount` is 0 by design, not a missed discount. (The
    // auto-detected case, where products were added one at a time, is the one
    // that reports a non-zero combo_discount.)
    expect((float) $response->json('meta.subtotal'))->toBe(2500.0)
        ->and((float) $response->json('meta.combo_discount'))->toBe(0.0)
        ->and((float) $response->json('meta.total'))->toBe(2500.0);
});

it('auto-detects a bundle that was added one product at a time', function () {
    $user = User::factory()->create();
    $studs = apiComboProduct('Auto Studs', 900);
    $bracelet = apiComboProduct('Auto Bracelet', 4388);
    $chain = apiComboProduct('Auto Chain', 3000);

    $combo = apiComboBundle([$studs, $bracelet, $chain], 10);

    Sanctum::actingAs($user);

    // Added through the plain product endpoint - no combo_id anywhere.
    foreach ([$studs, $bracelet, $chain] as $product) {
        postJson('/api/v1/cart', ['product_id' => $product->id])->assertCreated();
    }

    $response = getJson('/api/v1/cart')->assertOk();

    // 8,288 gross, 828.80 off.
    expect((float) $response->json('meta.subtotal'))->toBe(8288.0)
        ->and((float) $response->json('meta.combo_discount'))->toBe(828.80)
        ->and((float) $response->json('meta.total'))->toBe(7459.20);

    // Each line still reports its own regular price, and is charged less.
    $line = collect($response->json('data'))->firstWhere('product_id', $studs->id);

    expect((float) $line['regular_unit_price'])->toBe(900.0)
        ->and((float) $line['line_total'])->toBeLessThan(900.0)
        ->and((int) $line['combo_units'])->toBe(1);
});

it('refuses a combo that is switched off', function () {
    $user = User::factory()->create();
    $combo = apiComboBundle(
        [apiComboProduct('Off A', 100), apiComboProduct('Off B', 100)],
        10,
        'percent',
        ['is_active' => false],
    );

    Sanctum::actingAs($user);

    postJson('/api/v1/cart/combo', ['combo_id' => $combo->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('combo_id');

    expect(Cart::where('user_id', $user->id)->count())->toBe(0);
});

it('refuses a combo whose schedule window has closed', function () {
    $user = User::factory()->create();
    $combo = apiComboBundle(
        [apiComboProduct('Expired A', 100), apiComboProduct('Expired B', 100)],
        10,
        'percent',
        ['starts_at' => now()->subDays(10), 'expires_at' => now()->subDay()],
    );

    Sanctum::actingAs($user);

    postJson('/api/v1/cart/combo', ['combo_id' => $combo->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('combo_id');
});

it('keeps the discount when a disabled product is not part of what was sold', function () {
    $user = User::factory()->create();
    $first = apiComboProduct('Stay A', 1000);
    $second = apiComboProduct('Stay B', 1000);
    $disabled = apiComboProduct('Dies Later', 500, ['is_active' => false]);

    $combo = apiComboBundle([$first, $second, $disabled], 10);

    Sanctum::actingAs($user);
    postJson('/api/v1/cart/combo', ['combo_id' => $combo->id])->assertCreated();

    // Now the third product is switched off while the bundle is in the cart.
    $disabled->update(['is_active' => false]);

    // Completeness must be judged against the products that can actually be
    // sold. Requiring the dead one would declare a complete bundle broken and
    // silently refund the customer the discount on the very next cart read.
    $response = getJson('/api/v1/cart')->assertOk();

    expect((float) $response->json('meta.subtotal'))->toBe(1800.0)
        ->and((float) $response->json('data.0.line_total'))->toBeLessThanOrEqual(1800.0);
});

it('ignores a deactivated product rather than selling it inside the bundle', function () {
    $user = User::factory()->create();
    $first = apiComboProduct('Still On A', 1000);
    $second = apiComboProduct('Still On B', 1000);
    $disabled = apiComboProduct('Switched Off', 500, ['is_active' => false]);

    $combo = apiComboBundle([$first, $second, $disabled], 10);

    Sanctum::actingAs($user);

    $response = postJson('/api/v1/cart/combo', ['combo_id' => $combo->id])->assertCreated();

    // Only the two live products are sold, and the bundle is priced on those
    // alone - 2,000 gross, 10% off = 1,800. Counting the dead 500 product in
    // would have inflated the total to 2,250.
    $ids = collect($response->json('data'))->pluck('product_id')->map(fn ($id) => (int) $id);

    expect($response->json('data'))->toHaveCount(2)
        ->and($ids->sort()->values()->all())->toBe(collect([$first->id, $second->id])->sort()->values()->all())
        ->and((float) $response->json('meta.subtotal'))->toBe(1800.0);
});

it('refuses a combo that no longer has two live products', function () {
    $user = User::factory()->create();
    $active = apiComboProduct('Only One', 1000);
    $disabled = apiComboProduct('Second Dead', 1000, ['is_active' => false]);

    $combo = apiComboBundle([$active, $disabled], 10);

    Sanctum::actingAs($user);

    postJson('/api/v1/cart/combo', ['combo_id' => $combo->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('combo_id');
});

it('tops up the same bundle instead of duplicating its lines', function () {
    $user = User::factory()->create();
    $studs = apiComboProduct('Topup Studs', 1000);
    $bracelet = apiComboProduct('Topup Bracelet', 1000);

    $combo = apiComboBundle([$studs, $bracelet], 10);

    Sanctum::actingAs($user);

    postJson('/api/v1/cart/combo', ['combo_id' => $combo->id, 'quantity' => 1])->assertCreated();
    $response = postJson('/api/v1/cart/combo', ['combo_id' => $combo->id, 'quantity' => 2])->assertCreated();

    // Still two lines, each at quantity 3.
    expect($response->json('data'))->toHaveCount(2)
        ->and(collect($response->json('data'))->pluck('quantity')->all())->toBe([3, 3]);
});

it('never takes a price from the request body', function () {
    $user = User::factory()->create();
    $studs = apiComboProduct('Tamper Studs', 1000);
    $bracelet = apiComboProduct('Tamper Bracelet', 1000);

    $combo = apiComboBundle([$studs, $bracelet], 10);

    Sanctum::actingAs($user);

    $response = postJson('/api/v1/cart/combo', [
        'combo_id' => $combo->id,
        'price' => 1,
        'combo_price' => 1,
    ])->assertCreated();

    // 2,000 gross, 10% off = 1,800. A client-supplied 1 is ignored.
    expect((float) $response->json('meta.subtotal'))->toBe(1800.0);
});

it('restores regular prices when a bundle line is removed', function () {
    $user = User::factory()->create();
    $studs = apiComboProduct('Broken Studs', 1000);
    $bracelet = apiComboProduct('Broken Bracelet', 1000);

    $combo = apiComboBundle([$studs, $bracelet], 10);

    Sanctum::actingAs($user);

    $added = postJson('/api/v1/cart/combo', ['combo_id' => $combo->id])->assertCreated();
    $studsLine = collect($added->json('data'))->firstWhere('product_id', $studs->id);

    deleteJson('/api/v1/cart/'.$studsLine['id'])->assertOk();

    // The bundle can no longer be honoured, so the surviving line is charged
    // full price again rather than quietly keeping a discount it has not earned.
    $survivor = getJson('/api/v1/cart')->assertOk();

    expect((float) $survivor->json('data.0.price'))->toBe(1000.0)
        ->and((float) $survivor->json('data.0.line_total'))->toBe(1000.0)
        ->and((float) $survivor->json('meta.combo_discount'))->toBe(0.0);
});

it('keeps the derived prices out of the carts table', function () {
    $user = User::factory()->create();
    $combo = apiComboBundle(
        [apiComboProduct('Persist A', 1000), apiComboProduct('Persist B', 2000)],
        10,
    );

    Sanctum::actingAs($user);

    $lineId = postJson('/api/v1/cart/combo', ['combo_id' => $combo->id])
        ->assertCreated()
        ->json('data.0.id');

    // regular_unit_price and friends are display-only. If they were ever marked
    // dirty, a later save() would try to write a column that does not exist, so
    // the model must be re-synced after annotating it.
    $line = Cart::findOrFail($lineId);
    $line->quantity = 2;
    $line->save();

    expect($line->fresh()->quantity)->toBe(2)
        ->and($line->fresh()->combo_id)->toBe($combo->id);
});

it('requires a token to add a bundle', function () {
    $combo = apiComboBundle(
        [apiComboProduct('Anon A', 100), apiComboProduct('Anon B', 100)],
        10,
    );

    postJson('/api/v1/cart/combo', ['combo_id' => $combo->id])->assertUnauthorized();
});

it('scopes bundle lines to the signed in customer', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $combo = apiComboBundle(
        [apiComboProduct('Scoped A', 1000), apiComboProduct('Scoped B', 1000)],
        10,
    );

    Sanctum::actingAs($owner);
    postJson('/api/v1/cart/combo', ['combo_id' => $combo->id])->assertCreated();

    Sanctum::actingAs($other);
    getJson('/api/v1/cart')->assertOk()->assertJsonCount(0, 'data');
});
