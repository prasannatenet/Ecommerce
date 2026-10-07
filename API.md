# Gehna Storefront API

**Complete reference for the frontend developer.**

Every JSON response in this document was captured from the running API, not
written by hand, so the shapes shown here are exactly what your code receives.

---

## Base URL

```
https://astroemerging.com/gehna/api/v1
```

Every path below is relative to that. For example the product list is:

```
https://astroemerging.com/gehna/api/v1/products
```

| | |
| --- | --- |
| React client | `public/js/gehna-api.js` - copy to `src/api/gehnaApi.js` |
| React checkout example | `docs/react/CheckoutPage.jsx` - full COD + Razorpay flow (see `docs/react/README.md`) |
| Live demo | `https://astroemerging.com/gehna/api-demo` |
| Tests | `php artisan test --filter="Api(Storefront|Checkout)Test"` |

The client is dependency-free (plain `fetch`) and already wraps every endpoint
below, including token storage and error handling. You can use it as-is or call
the API directly.

---

## Authentication

Token based (Laravel Sanctum). There is no session and no CSRF token to fetch
first.

**Step 1** - get a token:

```
POST /auth/register     (create an account)
POST /auth/login        (existing account)
```

**Step 2** - send it on every protected call:

```
Authorization: Bearer <token>
```

**Step 3** - clear it when signing out:

```
POST /auth/logout
```

Requests without a valid token get `401`. The client stores the token in
`localStorage` under `gehna_api_token` and clears it automatically on any 401,
so a revoked or expired session never leaves the UI stuck in a signed-in state.

---

## Response envelope

Every endpoint, success or failure, returns the same shape. One code path in
your app can therefore handle all of them.

**Success**

```json
{
    "success": true,
    "message": "OK",
    "data": { },
    "meta": { }
}
```

**List endpoints** add pagination to `meta`:

```json
"meta": {
    "current_page": 1,
    "last_page": 2,
    "per_page": 12,
    "total": 24
}
```

**Failure**

```json
{
    "success": false,
    "message": "The email field must be a valid email address.",
    "data": null,
    "errors": {
        "email": ["The email field must be a valid email address."]
    },
    "meta": { }
}
```

`message` is safe to show in a toast. `errors` is keyed by field name, ready to
map straight onto form inputs.

> `meta` and `errors` are always JSON **objects** (`{}`), never arrays. Use
> bracket access, not `.length` or `Object.keys` iteration order assumptions.

---

## Status codes

| Status | When | What to do |
| --- | --- | --- |
| `200` | Success | Render `data` |
| `201` | Created (register, add to cart, subscribe) | Render `data` |
| `401` | Missing, expired or revoked token | Clear the token, send the user to login |
| `403` | Authenticated but not allowed | Show the message |
| `404` | Not found, **or deliberately hidden** | Treat as "gone" - an inactive product also 404s, so a draft is not discoverable |
| `422` | Validation failed | Map `errors` onto the form |
| `429` | Rate limited | Back off; auth is limited to 10/min, public reads to 120/min |
| `5xx` | Server fault | Show a retry affordance |

## 1. Authentication

### `POST /auth/register`

**Register**  
Creates the account and signs in. `password_confirmation` is required.

**Request body**

```json
{
    "name": "Doc Preview",
    "email": "you@example.com",
    "password": "Password123!",
    "password_confirmation": "Password123!",
    "phone": "9812345678"
}
```

**Response `201`**

```json
{
    "success": true,
    "message": "Registration successful",
    "data": {
        "token": "<token>",
        "token_type": "Bearer",
        "user": {
            "id": 20,
            "name": "Doc Preview",
            "email": "you@example.com",
            "phone": "9812345678",
            "gehna_coins": 0,
            "roles": [],
            "is_admin": false,
            "email_verified_at": null,
            "created_at": "2026-10-07T12:32:30+00:00"
        }
    },
    "meta": {}
}
```

### `POST /auth/login`

**Login**  
Returns a bearer token. Rate limited to 10 requests per minute and sharing the storefront lockout policy.

**Request body**

```json
{
    "email": "you@example.com",
    "password": "Password123!"
}
```

**Response `200`**

```json
{
    "success": true,
    "message": "Logged in successfully",
    "data": {
        "token": "<token>",
        "token_type": "Bearer",
        "user": {
            "id": 20,
            "name": "Doc Preview",
            "email": "you@example.com",
            "phone": "9812345678",
            "gehna_coins": 0,
            "roles": [],
            "is_admin": false,
            "email_verified_at": null,
            "created_at": "2026-10-07T12:32:30+00:00"
        }
    },
    "meta": {}
}
```

### `GET /auth/me` &nbsp;`Bearer token required`

**Current user**  
Called on app boot to restore a session. The client turns a 401 here into "signed out" rather than an error.

**Response `200`**

```json
{
    "success": true,
    "message": "OK",
    "data": {
        "id": 20,
        "name": "Doc Preview",
        "email": "you@example.com",
        "phone": "9812345678",
        "gehna_coins": 0,
        "roles": [],
        "is_admin": false,
        "email_verified_at": null,
        "created_at": "2026-10-07T12:32:30+00:00"
    },
    "meta": {}
}
```

### `PUT /auth/profile` &nbsp;`Bearer token required`

**Update profile**  
Name and phone only.

**Request body**

```json
{
    "name": "Doc Preview",
    "phone": null
}
```

**Response `200`**

```json
{
    "success": true,
    "message": "Profile updated",
    "data": {
        "id": 20,
        "name": "Doc Preview",
        "email": "you@example.com",
        "phone": null,
        "gehna_coins": 0,
        "roles": [],
        "is_admin": false,
        "email_verified_at": null,
        "created_at": "2026-10-07T12:32:30+00:00"
    },
    "meta": {}
}
```

### `POST /auth/logout` &nbsp;`Bearer token required`

**Sign out this device**  
Revokes the token that made the call. Safe to call with an already-invalid token.

**Response `200`**

```json
{
    "success": true,
    "message": "Logged out successfully",
    "data": null,
    "meta": {}
}
```

### `POST /auth/logout-all` &nbsp;`Bearer token required`

**Sign out everywhere**  
Revokes every token for the account.

**Response `200`**

```json
{
    "success": true,
    "message": "Signed out of all devices",
    "data": null,
    "meta": {}
}
```

## 2. Products

### `GET /products`

**Product list**  
The main grid. Accepts every filter in section 9. Inactive products are never returned.

**Response `200`**

```json
{
    "success": true,
    "data": [
        {
            "id": 9,
            "name": "Silver Elegant Butterflies Bracelet",
            "slug": "silver-elegant-butterflies-bracelet",
            "sku": null,
            "url": "https://astroemerging.com/gehna/product/silver-elegant-butterflies-bracelet",
            "audience": "women",
            "material_type": "silver",
            "product_type": "simple",
            "is_simple": true,
            "is_variable": false,
            "short_description": "The Silver Elegant Butterflies Bracelet is inspired by the moon showing up unexpectedly on a rainy night with a group of three twinkling stars.",
            "description": "The Design: This silver bracelet has a moon motif studded with zircon at the centre with two stars to its left and one star to its right. 925 Silver Perfect for...",
            "price": 2999.5,
            "regular_price": 5999,
            "sale_price": 2999.5,
            "base_price": 5999,
            "discount_type": "percentage",
            "discount_value": 50,
            "discount_percentage": 50,
            "is_on_sale": true,
            "price_range": null,
            "primary_image": "https://astroemerging.com/gehna/storage/products/JZBDIxaCw2LrKudyhdATWUjMKIHiRtHPMNwoSypB.png",
            "images": [
                "https://astroemerging.com/gehna/storage/products/JZBDIxaCw2LrKudyhdATWUjMKIHiRtHPMNwoSypB.png",
                "https://astroemerging.com/gehna/storage/products/nFr8k7kOaGrKgqbLlsD7OYXDicRj40PnimAgOtQT.webp",
                "... 1 more, 3 total"
            ],
            "manage_stock": true,
            "stock": 494,
            "in_stock": true,
            "category": {
                "id": 7,
                "name": "Bracelets",
                "slug": "bracelets",
                "parent_id": null
            },
            "brand": null,
            "variations": [],
            "reviews_count": 1,
            "0": "... 2 more item(s) in the real response, 3 total"
        }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 3,
        "per_page": 3,
        "total": 9,
        "from": 1,
        "to": 3,
        "sort": "latest",
        "filters": []
    }
}
```

### `GET /products?on_sale=1&sort=price_asc&per_page=2`

**Filtered list**  
The same endpoint with `on_sale`, `sort` and `per_page` applied.

**Response `200`**

```json
{
    "success": true,
    "data": [
        {
            "id": 3,
            "name": "Silver Zircon Bubble Stud Earrings",
            "slug": "silver-zircon-bubble-stud-earrings",
            "sku": null,
            "url": "https://astroemerging.com/gehna/product/silver-zircon-bubble-stud-earrings",
            "audience": "women",
            "material_type": "silver",
            "product_type": "simple",
            "is_simple": true,
            "is_variable": false,
            "short_description": "Are you in search of the perfect everyday earrings suitable for office wear? Look no further, because we've got just the pair for you!",
            "description": "The Design: These silver earrings have a hexagonal shape with a zircon placed on them. 925 Silver Perfect for sensitive skin Earring Size: Height - 0.5 cm, Widt...",
            "price": 990,
            "regular_price": 3000,
            "sale_price": 990,
            "base_price": 3000,
            "discount_type": "percentage",
            "discount_value": 67,
            "discount_percentage": 67,
            "is_on_sale": true,
            "price_range": null,
            "primary_image": "https://astroemerging.com/gehna/storage/products/aDKzPcUL9Y3aBNrTlDVBwj33wfW4Y2EKoluEyZsA.png",
            "images": [
                "https://astroemerging.com/gehna/storage/products/aDKzPcUL9Y3aBNrTlDVBwj33wfW4Y2EKoluEyZsA.png",
                "https://astroemerging.com/gehna/storage/products/eabUVxJDrgefzhEGkW1j0pKgcqgtWJAaTgGL1D37.webp",
                "... 2 more, 4 total"
            ],
            "manage_stock": false,
            "stock": 0,
            "in_stock": false,
            "category": {
                "id": 4,
                "name": "Earrings",
                "slug": "earrings",
                "parent_id": null
            },
            "brand": null,
            "variations": [],
            "reviews_count": 0,
            "0": "... 1 more item(s) in the real response, 2 total"
        }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 5,
        "per_page": 2,
        "total": 9,
        "from": 1,
        "to": 2,
        "sort": "price_asc",
        "filters": {
            "on_sale": "1"
        }
    }
}
```

### `GET /products/{slug}`

**Product detail**  
Returns `{ product, reviews }` in one request so the page never has to fan out into follow-up calls.

**Response `200`**

```json
{
    "success": true,
    "message": "OK",
    "data": {
        "product": {
            "id": 9,
            "name": "Silver Elegant Butterflies Bracelet",
            "slug": "silver-elegant-butterflies-bracelet",
            "sku": null,
            "url": "https://astroemerging.com/gehna/product/silver-elegant-butterflies-bracelet",
            "audience": "women",
            "material_type": "silver",
            "product_type": "simple",
            "is_simple": true,
            "is_variable": false,
            "short_description": "The Silver Elegant Butterflies Bracelet is inspired by the moon showing up unexpectedly on a rainy night with a group of three twinkling stars.",
            "description": "The Design: This silver bracelet has a moon motif studded with zircon at the centre with two stars to its left and one star to its right. 925 Silver Perfect for...",
            "price": 2999.5,
            "regular_price": 5999,
            "sale_price": 2999.5,
            "base_price": 5999,
            "discount_type": "percentage",
            "discount_value": 50,
            "discount_percentage": 50,
            "is_on_sale": true,
            "price_range": null,
            "primary_image": "https://astroemerging.com/gehna/storage/products/JZBDIxaCw2LrKudyhdATWUjMKIHiRtHPMNwoSypB.png",
            "images": [
                "https://astroemerging.com/gehna/storage/products/JZBDIxaCw2LrKudyhdATWUjMKIHiRtHPMNwoSypB.png",
                "https://astroemerging.com/gehna/storage/products/nFr8k7kOaGrKgqbLlsD7OYXDicRj40PnimAgOtQT.webp",
                "... 1 more, 3 total"
            ],
            "manage_stock": true,
            "stock": 494,
            "in_stock": true,
            "category": {
                "id": 7,
                "name": "Bracelets",
                "slug": "bracelets",
                "parent_id": null
            },
            "brand": null,
            "variations": [],
            "attributes": [],
            "videos": [
                {
                    "id": 2,
                    "is_primary": true,
                    "url": "https://astroemerging.com/gehna/product-videos/2",
                    "download_url": "https://astroemerging.com/gehna/storage/products/videos/h5nn69B114m3zdCyelKdrsmYZEd7DL09lBM3gqIU.mp4"
                }
            ],
            "rating": 5,
            "reviews_count": 1
        },
        "reviews": {
            "summary": {
                "average": 5,
                "count": 1
            },
            "data": [
                {
                    "id": 3,
                    "rating": 5,
                    "comment": "great Product",
                    "author": {
                        "name": "Admin",
                        "initials": "A"
                    },
                    "images": [],
                    "created_at": "2026-09-08T04:54:41+00:00"
                }
            ],
            "current_page": 1,
            "last_page": 1,
            "total": 1
        }
    },
    "meta": {}
}
```

### `GET /products/{slug}/related?per_page=2`

**Related products**  
The "you may also like" rail.

**Response `200`**

```json
{
    "success": true,
    "message": "OK",
    "data": [
        {
            "id": 8,
            "name": "Rose Gold My Love Bracelet",
            "slug": "rose-gold-my-love-bracelet",
            "sku": null,
            "url": "https://astroemerging.com/gehna/product/rose-gold-my-love-bracelet",
            "audience": "women",
            "material_type": "gold",
            "product_type": "simple",
            "is_simple": true,
            "is_variable": false,
            "short_description": "Don't hesitate any longer, it's the season of love -the perfect time to convey your feelings to that special someone.",
            "description": "The Design: This rose gold open-type bracelet features a heart motif that rotates and studded with zircons on one end and other end has a zircon studded heart i...",
            "price": 10895.39,
            "regular_price": 19799,
            "sale_price": 10895.39,
            "base_price": 19799,
            "discount_type": "percentage",
            "discount_value": 44.97,
            "discount_percentage": 44.97,
            "is_on_sale": true,
            "price_range": null,
            "primary_image": "https://astroemerging.com/gehna/storage/products/9IOxM3tY8wYLuJ0Nbz8cVHTumtdTZWffq7aw8TKL.png",
            "images": [
                "https://astroemerging.com/gehna/storage/products/9IOxM3tY8wYLuJ0Nbz8cVHTumtdTZWffq7aw8TKL.png",
                "https://astroemerging.com/gehna/storage/products/IqCtkrj5wdrGVCaZj4WlQFYvgboxyw7lVTBjeI4o.webp",
                "... 2 more, 4 total"
            ],
            "manage_stock": false,
            "stock": 0,
            "in_stock": false,
            "category": {
                "id": 7,
                "name": "Bracelets",
                "slug": "bracelets",
                "parent_id": null
            },
            "brand": null,
            "variations": [],
            "0": "... 1 more item(s) in the real response, 2 total"
        }
    ],
    "meta": {
        "total": 2
    }
}
```

### `GET /products/{slug}/reviews?per_page=2`

**Reviews**  
Pass `rating=5` to filter by star count.

**Response `200`**

```json
{
    "success": true,
    "data": [
        {
            "id": 3,
            "rating": 5,
            "comment": "great Product",
            "author": {
                "name": "Admin",
                "initials": "A"
            },
            "images": [],
            "created_at": "2026-09-08T04:54:41+00:00"
        }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 1,
        "per_page": 2,
        "total": 1,
        "average": 5
    }
}
```

## 3. Categories

### `GET /categories`

**Category list**  
Flat list. `?parent_id=root` for top level only. `products_count` counts active products only.

**Response `200`**

```json
{
    "success": true,
    "message": "OK",
    "data": [
        {
            "id": 5,
            "name": "Anklets",
            "slug": "anklets",
            "parent_id": null,
            "is_root": true,
            "position": 0,
            "description": "Anklets",
            "image": "https://astroemerging.com/gehna/storage/categories/2STL6Liwt0Nqua8Ye4KFn6PCjHRYqied8cCHgW0q.jpg",
            "breadcrumb": "Anklets",
            "products_count": 0,
            "url": "https://astroemerging.com/gehna/category/anklets",
            "0": "... 9 more item(s) in the real response, 10 total"
        }
    ],
    "meta": {
        "total": 10
    }
}
```

### `GET /categories?tree=1`

**Category tree**  
Nested, for a mega-menu.

**Response `200`**

```json
{
    "success": true,
    "message": "OK",
    "data": [
        {
            "id": 5,
            "name": "Anklets",
            "slug": "anklets",
            "parent_id": null,
            "is_root": true,
            "position": 0,
            "description": "Anklets",
            "image": "https://astroemerging.com/gehna/storage/categories/2STL6Liwt0Nqua8Ye4KFn6PCjHRYqied8cCHgW0q.jpg",
            "breadcrumb": "Anklets",
            "products_count": 0,
            "url": "https://astroemerging.com/gehna/category/anklets",
            "children": [],
            "0": "... 9 more item(s) in the real response, 10 total"
        }
    ],
    "meta": {
        "total": 10
    }
}
```

### `GET /categories/{slug}`

**One category**  
Includes the parent and the direct children.

**Response `200`**

```json
{
    "success": true,
    "message": "OK",
    "data": {
        "id": 7,
        "name": "Bracelets",
        "slug": "bracelets",
        "parent_id": null,
        "is_root": true,
        "position": 0,
        "description": "Bracelets",
        "image": "https://astroemerging.com/gehna/storage/categories/zI3pukSqDOszpmONt48DinTED9GwhKNRe2XE20jj.jpg",
        "breadcrumb": "Bracelets",
        "products_count": 3,
        "url": "https://astroemerging.com/gehna/category/bracelets",
        "children": []
    },
    "meta": {}
}
```

### `GET /categories/{slug}/products?per_page=2`

**Products in a category**  
Also includes products filed under the category's children. `meta.included` lists every category id covered.

**Response `200`**

```json
{
    "success": true,
    "data": [
        {
            "id": 9,
            "name": "Silver Elegant Butterflies Bracelet",
            "slug": "silver-elegant-butterflies-bracelet",
            "sku": null,
            "url": "https://astroemerging.com/gehna/product/silver-elegant-butterflies-bracelet",
            "audience": "women",
            "material_type": "silver",
            "product_type": "simple",
            "is_simple": true,
            "is_variable": false,
            "short_description": "The Silver Elegant Butterflies Bracelet is inspired by the moon showing up unexpectedly on a rainy night with a group of three twinkling stars.",
            "description": "The Design: This silver bracelet has a moon motif studded with zircon at the centre with two stars to its left and one star to its right. 925 Silver Perfect for...",
            "price": 2999.5,
            "regular_price": 5999,
            "sale_price": 2999.5,
            "base_price": 5999,
            "discount_type": "percentage",
            "discount_value": 50,
            "discount_percentage": 50,
            "is_on_sale": true,
            "price_range": null,
            "primary_image": "https://astroemerging.com/gehna/storage/products/JZBDIxaCw2LrKudyhdATWUjMKIHiRtHPMNwoSypB.png",
            "images": [
                "https://astroemerging.com/gehna/storage/products/JZBDIxaCw2LrKudyhdATWUjMKIHiRtHPMNwoSypB.png",
                "https://astroemerging.com/gehna/storage/products/nFr8k7kOaGrKgqbLlsD7OYXDicRj40PnimAgOtQT.webp",
                "... 1 more, 3 total"
            ],
            "manage_stock": true,
            "stock": 494,
            "in_stock": true,
            "category": {
                "id": 7,
                "name": "Bracelets",
                "slug": "bracelets",
                "parent_id": null
            },
            "brand": null,
            "variations": [],
            "reviews_count": 1,
            "0": "... 1 more item(s) in the real response, 2 total"
        }
    ],
    "category": {
        "id": 7,
        "name": "Bracelets",
        "slug": "bracelets",
        "parent_id": null,
        "is_root": true,
        "position": 0,
        "description": "Bracelets",
        "image": "https://astroemerging.com/gehna/storage/categories/zI3pukSqDOszpmONt48DinTED9GwhKNRe2XE20jj.jpg",
        "breadcrumb": "Bracelets",
        "url": "https://astroemerging.com/gehna/category/bracelets"
    },
    "meta": {
        "current_page": 1,
        "last_page": 2,
        "per_page": 2,
        "total": 3,
        "from": 1,
        "to": 2,
        "included": [
            7
        ]
    }
}
```

## 4. Brands and combos

### `GET /brands`

**Brand list**

**Response `200`**

```json
{
    "success": true,
    "message": "OK",
    "data": [],
    "meta": {
        "total": 0
    }
}
```

### `GET /brands/{slug}`

**One brand**

**Response `404`**

```json
{
    "success": false,
    "message": "Brand not found.",
    "data": null,
    "errors": {},
    "meta": {}
}
```

### `GET /brands/{slug}/products?per_page=2`

**Products for a brand**  
Accepts the same filters as the product list.

**Response `404`**

```json
{
    "success": false,
    "message": "Brand not found.",
    "data": null,
    "errors": {},
    "meta": {}
}
```

### `GET /combos`

**Combo list**  
Only combos that are switched on and inside their date window.

**Response `200`**

```json
{
    "success": true,
    "message": "OK",
    "data": [
        {
            "id": 1,
            "name": "try",
            "slug": "try",
            "description": null,
            "discount_type": "percent",
            "discount_value": 5,
            "products_total": 12787.13,
            "discount_amount": 639.36,
            "combo_price": 12147.77,
            "savings_percent": 5,
            "is_live": true,
            "starts_at": null,
            "expires_at": null,
            "products": [
                {
                    "id": 4,
                    "name": "Golden Star Constellation Tiny Studs",
                    "slug": "golden-star-constellation-tiny-studs",
                    "sku": null,
                    "url": "https://astroemerging.com/gehna/product/golden-star-constellation-tiny-studs",
                    "audience": "women",
                    "material_type": "gold",
                    "product_type": "simple",
                    "is_simple": true,
                    "is_variable": false,
                    "short_description": "The night sky devoid of any stars is unimaginable and incomplete. Likewise, your accessory collection, without these earrings, would be incomplete.",
                    "description": "The Design:\r\nThese golden earrings feature a star motif set with zircons.\r\n\r\n925 Silver with Gold Plating\r\nPerfect for sensitive skin\r\nEarring Size: Height - 0.5 cm, Width - 0.5 cm\r\nComes with the GIVA Jewellery kit and authenticity certificate\r\nContent: Earrings\r\nNet Qty- 1 pair\r\nStyling Tip:\r\nTeam these with a purple ruffle dress.",
                    "price": 999.75,
                    "regular_price": 3999,
                    "sale_price": 999.75,
                    "base_price": 3999,
                    "discount_type": "percentage",
                    "discount_value": 75,
                    "discount_percentage": 75,
                    "is_on_sale": true,
                    "price_range": null,
                    "primary_image": "https://astroemerging.com/gehna/storage/products/0sp8aUfrXeY9JNRAZYxe89EkgjeFpu70sQg6IHob.png",
                    "images": [
                        "https://astroemerging.com/gehna/storage/products/0sp8aUfrXeY9JNRAZYxe89EkgjeFpu70sQg6IHob.png",
                        "https://astroemerging.com/gehna/storage/products/vTMnfpRpo8Mn0rLkWsOzmMoRQfy3PWw9NxmkjByF.webp",
                        "https://astroemerging.com/gehna/storage/products/l53QZdhYsrJn8s4G13N1qYMyZv84np6YkprH2D0L.webp",
                        "https://astroemerging.com/gehna/storage/products/tV62bwAPIlIq1GyEDmCGGQa1mzQWcwvufSQzS0D1.webp"
                    ],
                    "manage_stock": false,
                    "stock": 0,
                    "in_stock": false,
                    "variations": []
                },
                {
                    "id": 7,
                    "name": "Rose Gold For My Dear Bracelet",
                    "slug": "rose-gold-for-my-dear-bracelet",
                    "sku": null,
                    "url": "https://astroemerging.com/gehna/product/rose-gold-for-my-dear-bracelet",
                    "audience": "men",
                    "material_type": "gold",
                    "product_type": "simple",
                    "is_simple": true,
                    "is_variable": false,
                    "short_description": "The Rose Gold For My Dear Bracelet is inspired by the special moments captured in the camera of our hearts that we keep encapsulated in a treasure box of memories.",
                    "description": "The Design:\r\n\r\nThe rose gold bracelet has a design of the antlers of a reindeer with a zircon on the head.\r\n\r\n925 silver with Rose Gold plating\r\nPerfect for sensitive skin\r\nAdjustable size\r\nLength of the bracelet - 16.5 cm+ 4 cm adjustable\r\nComes with the GIVA Jewellery kit and authenticity certificate\r\nNet Qty- 1 piece\r\nStyling Tip:\r\n\r\nStyle this with a beige ribbed top and blue jeans.",
                    "price": 4388.43,
                    "regular_price": 7699,
                    "sale_price": 4388.43,
                    "base_price": 7699,
                    "discount_type": "percentage",
                    "discount_value": 43,
                    "discount_percentage": 43,
                    "is_on_sale": true,
                    "price_range": null,
                    "primary_image": "https://astroemerging.com/gehna/storage/products/lmjp2W1sXyGGN8e54MUXgPmMGcEi7V7DfT4MVwmn.png",
                    "images": [
                        "https://astroemerging.com/gehna/storage/products/lmjp2W1sXyGGN8e54MUXgPmMGcEi7V7DfT4MVwmn.png",
                        "https://astroemerging.com/gehna/storage/products/2d8xGFiDZgDbCm5BvIEAgodEsmtfkqtX5DPUnpe6.webp",
                        "https://astroemerging.com/gehna/storage/products/mU9q8NWxS3eiDTigzzbFGphU18Dtb8MtALvHAo4b.webp"
                    ],
                    "manage_stock": false,
                    "stock": 0,
                    "in_stock": false,
                    "variations": []
                },
                "... 2 more, 4 total"
            ],
            "products_count": 4
        }
    ],
    "meta": {
        "total": 1
    }
}
```

### `GET /combos/{slug}`

**One combo**  
Pricing is computed server-side, so a bundle can never be quoted at a different figure.

**Response `200`**

```json
{
    "success": true,
    "message": "OK",
    "data": {
        "id": 1,
        "name": "try",
        "slug": "try",
        "description": null,
        "discount_type": "percent",
        "discount_value": 5,
        "products_total": 12787.13,
        "discount_amount": 639.36,
        "combo_price": 12147.77,
        "savings_percent": 5,
        "is_live": true,
        "starts_at": null,
        "expires_at": null,
        "products": [
            {
                "id": 4,
                "name": "Golden Star Constellation Tiny Studs",
                "slug": "golden-star-constellation-tiny-studs",
                "sku": null,
                "url": "https://astroemerging.com/gehna/product/golden-star-constellation-tiny-studs",
                "audience": "women",
                "material_type": "gold",
                "product_type": "simple",
                "is_simple": true,
                "is_variable": false,
                "short_description": "The night sky devoid of any stars is unimaginable and incomplete. Likewise, your accessory collection, without these earrings, would be incomplete.",
                "description": "The Design:\r\nThese golden earrings feature a star motif set with zircons.\r\n\r\n925 Silver with Gold Plating\r\nPerfect for sensitive skin\r\nEarring Size: Height - 0.5 cm, Width - 0.5 cm\r\nComes with the GIVA Jewellery kit and authenticity certificate\r\nContent: Earrings\r\nNet Qty- 1 pair\r\nStyling Tip:\r\nTeam these with a purple ruffle dress.",
                "price": 999.75,
                "regular_price": 3999,
                "sale_price": 999.75,
                "base_price": 3999,
                "discount_type": "percentage",
                "discount_value": 75,
                "discount_percentage": 75,
                "is_on_sale": true,
                "price_range": null,
                "primary_image": "https://astroemerging.com/gehna/storage/products/0sp8aUfrXeY9JNRAZYxe89EkgjeFpu70sQg6IHob.png",
                "images": [
                    "https://astroemerging.com/gehna/storage/products/0sp8aUfrXeY9JNRAZYxe89EkgjeFpu70sQg6IHob.png",
                    "https://astroemerging.com/gehna/storage/products/vTMnfpRpo8Mn0rLkWsOzmMoRQfy3PWw9NxmkjByF.webp",
                    "https://astroemerging.com/gehna/storage/products/l53QZdhYsrJn8s4G13N1qYMyZv84np6YkprH2D0L.webp",
                    "https://astroemerging.com/gehna/storage/products/tV62bwAPIlIq1GyEDmCGGQa1mzQWcwvufSQzS0D1.webp"
                ],
                "manage_stock": false,
                "stock": 0,
                "in_stock": false,
                "variations": []
            },
            {
                "id": 7,
                "name": "Rose Gold For My Dear Bracelet",
                "slug": "rose-gold-for-my-dear-bracelet",
                "sku": null,
                "url": "https://astroemerging.com/gehna/product/rose-gold-for-my-dear-bracelet",
                "audience": "men",
                "material_type": "gold",
                "product_type": "simple",
                "is_simple": true,
                "is_variable": false,
                "short_description": "The Rose Gold For My Dear Bracelet is inspired by the special moments captured in the camera of our hearts that we keep encapsulated in a treasure box of memories.",
                "description": "The Design:\r\n\r\nThe rose gold bracelet has a design of the antlers of a reindeer with a zircon on the head.\r\n\r\n925 silver with Rose Gold plating\r\nPerfect for sensitive skin\r\nAdjustable size\r\nLength of the bracelet - 16.5 cm+ 4 cm adjustable\r\nComes with the GIVA Jewellery kit and authenticity certificate\r\nNet Qty- 1 piece\r\nStyling Tip:\r\n\r\nStyle this with a beige ribbed top and blue jeans.",
                "price": 4388.43,
                "regular_price": 7699,
                "sale_price": 4388.43,
                "base_price": 7699,
                "discount_type": "percentage",
                "discount_value": 43,
                "discount_percentage": 43,
                "is_on_sale": true,
                "price_range": null,
                "primary_image": "https://astroemerging.com/gehna/storage/products/lmjp2W1sXyGGN8e54MUXgPmMGcEi7V7DfT4MVwmn.png",
                "images": [
                    "https://astroemerging.com/gehna/storage/products/lmjp2W1sXyGGN8e54MUXgPmMGcEi7V7DfT4MVwmn.png",
                    "https://astroemerging.com/gehna/storage/products/2d8xGFiDZgDbCm5BvIEAgodEsmtfkqtX5DPUnpe6.webp",
                    "https://astroemerging.com/gehna/storage/products/mU9q8NWxS3eiDTigzzbFGphU18Dtb8MtALvHAo4b.webp"
                ],
                "manage_stock": false,
                "stock": 0,
                "in_stock": false,
                "variations": []
            },
            "... 2 more, 4 total"
        ],
        "products_count": 4
    },
    "meta": {}
}
```

## 5. Cart, wishlist and orders

### `GET /cart` &nbsp;`Bearer token required`

**Cart**  
`meta.count` and `meta.subtotal` drive the header badge.

**Response `200`**

```json
{
    "success": true,
    "message": "OK",
    "data": [],
    "meta": {
        "subtotal": 0,
        "combo_discount": 0,
        "total": 0,
        "count": 0
    }
}
```

### `POST /cart` &nbsp;`Bearer token required`

**Add to cart**  
Sending the same product twice tops up the existing line. `product_variation_id` is optional.

**The price is always resolved from the database, never from the request body.**

**Request body**

```json
{
    "product_id": 9,
    "quantity": 2
}
```

**Response `201`**

```json
{
    "success": true,
    "message": "Added to cart",
    "data": [
        {
            "id": 121,
            "product_id": 9,
            "product_variation_id": null,
            "combo_id": null,
            "name": "Silver Elegant Butterflies Bracelet",
            "slug": "silver-elegant-butterflies-bracelet",
            "sku": null,
            "image": "https://astroemerging.com/gehna/storage/products/JZBDIxaCw2LrKudyhdATWUjMKIHiRtHPMNwoSypB.png",
            "options": [],
            "price": 2999.5,
            "quantity": 2,
            "is_combo": false,
            "combo": null,
            "combo_units": 0,
            "combo_names": [],
            "regular_unit_price": 2999.5,
            "line_total": 5999,
            "available_stock": 494,
            "url": "https://astroemerging.com/gehna/product/silver-elegant-butterflies-bracelet"
        }
    ],
    "meta": {
        "subtotal": 5999,
        "combo_discount": 0,
        "total": 5999,
        "count": 2
    }
}
```

### `PATCH /cart/{id}` &nbsp;`Bearer token required`

**Set quantity**  
Quantity `0` removes the line, so the drawer can drop a row without a second call.

**Request body**

```json
{
    "quantity": 3
}
```

**Response `200`**

```json
{
    "success": true,
    "message": "Cart updated",
    "data": [
        {
            "id": 121,
            "product_id": 9,
            "product_variation_id": null,
            "combo_id": null,
            "name": "Silver Elegant Butterflies Bracelet",
            "slug": "silver-elegant-butterflies-bracelet",
            "sku": null,
            "image": "https://astroemerging.com/gehna/storage/products/JZBDIxaCw2LrKudyhdATWUjMKIHiRtHPMNwoSypB.png",
            "options": [],
            "price": 2999.5,
            "quantity": 3,
            "is_combo": false,
            "combo": null,
            "combo_units": 0,
            "combo_names": [],
            "regular_unit_price": 2999.5,
            "line_total": 8998.5,
            "available_stock": 494,
            "url": "https://astroemerging.com/gehna/product/silver-elegant-butterflies-bracelet"
        }
    ],
    "meta": {
        "subtotal": 8998.5,
        "combo_discount": 0,
        "total": 8998.5,
        "count": 3
    }
}
```

### `GET /cart` &nbsp;`Bearer token required`

**Cart with items**

**Response `200`**

```json
{
    "success": true,
    "message": "OK",
    "data": [
        {
            "id": 121,
            "product_id": 9,
            "product_variation_id": null,
            "combo_id": null,
            "name": "Silver Elegant Butterflies Bracelet",
            "slug": "silver-elegant-butterflies-bracelet",
            "sku": null,
            "image": "https://astroemerging.com/gehna/storage/products/JZBDIxaCw2LrKudyhdATWUjMKIHiRtHPMNwoSypB.png",
            "options": [],
            "price": 2999.5,
            "quantity": 3,
            "is_combo": false,
            "combo": null,
            "combo_units": 0,
            "combo_names": [],
            "regular_unit_price": 2999.5,
            "line_total": 8998.5,
            "available_stock": 494,
            "url": "https://astroemerging.com/gehna/product/silver-elegant-butterflies-bracelet"
        }
    ],
    "meta": {
        "subtotal": 8998.5,
        "combo_discount": 0,
        "total": 8998.5,
        "count": 3
    }
}
```

### `DELETE /cart/{id}` &nbsp;`Bearer token required`

**Remove one line**

**Response `200`**

```json
{
    "success": true,
    "message": "Item removed",
    "data": [],
    "meta": {
        "subtotal": 0,
        "combo_discount": 0,
        "total": 0,
        "count": 0
    }
}
```

### `DELETE /cart` &nbsp;`Bearer token required`

**Empty the cart**

**Response `200`**

```json
{
    "success": true,
    "message": "Cart cleared",
    "data": [],
    "meta": {
        "subtotal": 0,
        "combo_discount": 0,
        "total": 0,
        "count": 0
    }
}
```

### `POST /wishlist/toggle` &nbsp;`Bearer token required`

**Add or remove**  
Flip the heart from `data.in_wishlist` rather than guessing, or the icon drifts out of sync with the server.

**Request body**

```json
{
    "product_id": 9
}
```

**Response `201`**

```json
{
    "success": true,
    "message": "Added to wishlist",
    "data": {
        "product_id": 9,
        "in_wishlist": true,
        "count": 1
    },
    "meta": {}
}
```

### `GET /wishlist` &nbsp;`Bearer token required`

**Wishlist**  
Full product objects, ready to render as cards.

**Response `200`**

```json
{
    "success": true,
    "data": [
        {
            "id": 9,
            "name": "Silver Elegant Butterflies Bracelet",
            "slug": "silver-elegant-butterflies-bracelet",
            "sku": null,
            "url": "https://astroemerging.com/gehna/product/silver-elegant-butterflies-bracelet",
            "audience": "women",
            "material_type": "silver",
            "product_type": "simple",
            "is_simple": true,
            "is_variable": false,
            "short_description": "The Silver Elegant Butterflies Bracelet is inspired by the moon showing up unexpectedly on a rainy night with a group of three twinkling stars.",
            "description": "The Design: This silver bracelet has a moon motif studded with zircon at the centre with two stars to its left and one star to its right. 925 Silver Perfect for...",
            "price": 2999.5,
            "regular_price": 5999,
            "sale_price": 2999.5,
            "base_price": 5999,
            "discount_type": "percentage",
            "discount_value": 50,
            "discount_percentage": 50,
            "is_on_sale": true,
            "price_range": null,
            "primary_image": "https://astroemerging.com/gehna/storage/products/JZBDIxaCw2LrKudyhdATWUjMKIHiRtHPMNwoSypB.png",
            "images": [
                "https://astroemerging.com/gehna/storage/products/JZBDIxaCw2LrKudyhdATWUjMKIHiRtHPMNwoSypB.png",
                "https://astroemerging.com/gehna/storage/products/nFr8k7kOaGrKgqbLlsD7OYXDicRj40PnimAgOtQT.webp",
                "... 1 more, 3 total"
            ],
            "manage_stock": true,
            "stock": 494,
            "in_stock": true,
            "category": {
                "id": 7,
                "name": "Bracelets",
                "slug": "bracelets",
                "parent_id": null
            },
            "brand": null,
            "variations": [],
            "reviews_count": 1
        }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 1,
        "total": 1,
        "count": 1
    }
}
```

### `DELETE /wishlist/{productId}` &nbsp;`Bearer token required`

**Remove from wishlist**

**Response `200`**

```json
{
    "success": true,
    "message": "Removed from wishlist",
    "data": {
        "product_id": 9,
        "in_wishlist": false,
        "count": 0
    },
    "meta": {}
}
```

### `GET /orders` &nbsp;`Bearer token required`

**Order history**  
Read-only. `?status=` and `?payment_status=` filter the list.

**Response `200`**

```json
{
    "success": true,
    "data": [],
    "meta": {
        "current_page": 1,
        "last_page": 1,
        "per_page": 10,
        "total": 0
    }
}
```

### `GET /orders/{id}` &nbsp;`Bearer token required`

**Order detail**  
The one call that returns an order with its `items` and `items_count` filled in - use it for the order confirmation and history detail views.

**Response `200`**

```json
{
    "success": true,
    "message": "OK",
    "data": {
        "id": 47,
        "order_number": 47,
        "status": "pending",
        "payment_status": "pending",
        "payment_method": "cod",
        "paid_at": null,
        "cancelled_at": null,
        "cancel_reason": null,
        "subtotal": 5999,
        "total": 5999,
        "refunded_total": 0,
        "refund_status": "none",
        "shipping_address": {
            "name": "Doc Preview",
            "email": "you@example.com",
            "phone": "9812345678",
            "line1": "12 Museum Road",
            "city": "Bengaluru",
            "state": "Karnataka",
            "zip": "560001",
            "country": "India"
        },
        "billing_address": {
            "name": "Doc Preview",
            "email": "you@example.com",
            "phone": "9812345678",
            "line1": "12 Museum Road",
            "city": "Bengaluru",
            "state": "Karnataka",
            "zip": "560001",
            "country": "India"
        },
        "items": [
            {
                "id": 83,
                "product_id": 9,
                "product_variation_id": null,
                "product_name": "Silver Elegant Butterflies Bracelet",
                "sku": null,
                "image": "https://astroemerging.com/gehna/storage/products/JZBDIxaCw2LrKudyhdATWUjMKIHiRtHPMNwoSypB.png",
                "unit_price": 2999.5,
                "quantity": 2,
                "line_total": 5999,
                "meta": {
                    "from_cart_id": 122,
                    "variation_attributes": null
                },
                "url": "https://astroemerging.com/gehna/product/silver-elegant-butterflies-bracelet"
            }
        ],
        "items_count": 1,
        "created_at": "2026-10-07T12:32:40+00:00",
        "updated_at": "2026-10-07T12:32:40+00:00"
    },
    "meta": {}
}
```

## 6. Checkout and payments

### `GET /checkout/summary` &nbsp;`Bearer token required`

**Checkout summary**  
Prices the cart on the server: combo and coupon discounts, Gehna Coins, shipping (free when the subtotal is ₹5,000 or more, otherwise ₹199) and the grand total. Also returns which payment providers are active, every eligible coupon, and a `checkout_token`.

**Send that `checkout_token` back with `place-order`.** It makes retries idempotent: a double-tapped button or a network retry returns the order that already exists instead of creating a second one.

**Response `200`**

```json
{
    "success": true,
    "message": "OK",
    "data": {
        "pricing": {
            "subtotal": 5999,
            "total_quantity": 2,
            "combo_discount": 0,
            "coupon_discount": 0,
            "coins_discount": 0,
            "coins_used": 0,
            "shipping_charge": 0,
            "grand_total": 5999
        },
        "combo_summary": {
            "has_applied_combo": false,
            "discount": 0
        },
        "applied_coupon": null,
        "applied_coins": {
            "requested": 0,
            "coins": 0,
            "discount": 0,
            "balance": 0,
            "balance_after": 0
        },
        "user_coins": {
            "balance": 0,
            "balance_after": 0
        },
        "available_coupons": [
            {
                "id": 5,
                "code": "Diwali",
                "type": "percent",
                "amount": 10,
                "buy_quantity": 0,
                "get_quantity": 0,
                "reward_coins": 0,
                "min_order_amount": 5000,
                "validity_text": "From 22 Sep 2026, 04:32 PM",
                "is_applicable": true,
                "ineligible_reason": null
            }
        ],
        "payment_providers": {
            "cod": {
                "enabled": true,
                "name": "Cash on Delivery"
            },
            "razorpay": {
                "enabled": true,
                "name": "Razorpay",
                "key": "rzp_test_TkujmCnZM9yg11"
            }
        },
        "checkout_token": "b33e3972-6e77-438a-9ae4-e88314514350"
    },
    "meta": {}
}
```

### `POST /checkout/apply-coupon` &nbsp;`Bearer token required`

**Apply a coupon**  
The discount is recomputed server-side from the coupon rules - never trust a discount sent by the client. Coupons cannot be combined with an active combo offer.

**Request body**

```json
{
    "coupon_code": "Diwali"
}
```

**Response `200`**

```json
{
    "success": true,
    "message": "Coupon applied successfully.",
    "data": {
        "coupon": {
            "id": 5,
            "code": "Diwali",
            "type": "percent",
            "amount": 10,
            "discount": 599.9,
            "free_items": []
        }
    },
    "meta": {}
}
```

### `POST /checkout/remove-coupon` &nbsp;`Bearer token required`

**Remove the coupon**

**Response `200`**

```json
{
    "success": true,
    "message": "Coupon removed.",
    "data": null,
    "meta": {}
}
```

### `POST /checkout/apply-coins` &nbsp;`Bearer token required`

**Redeem Gehna Coins**  
Success returns `{ coins_used, discount, balance, balance_after }`, clamped to the balance and the order total. The capture shows the zero-balance rejection a freshly registered account gets; the applied figure always also appears in `summary.data.applied_coins`.

**Request body**

```json
{
    "coins": 5
}
```

**Response `422`**

```json
{
    "success": false,
    "message": "You do not have any Gehna Coins to redeem yet.",
    "data": null,
    "errors": {},
    "meta": {}
}
```

### `POST /checkout/remove-coins` &nbsp;`Bearer token required`

**Remove redeemed coins**

**Response `200`**

```json
{
    "success": true,
    "message": "Gehna Coins removed.",
    "data": null,
    "meta": {}
}
```

### `POST /checkout/place-order` &nbsp;`Bearer token required`

**Place order - Cash on Delivery**  
Creates the order, moves stock and clears the cart in one transaction. `is_paid` is `false`: payment is collected on delivery.

All `billing_*` fields are required. The `shipping_*` fields fall back to their `billing_*` counterparts, so `shipping_same_as_billing: true` alone is enough.

**Request body**

```json
{
    "payment_method": "cod",
    "checkout_token": "9eb39c4c-28a2-49d0-9cec-2217fc185221",
    "billing_name": "Doc Preview",
    "billing_email": "you@example.com",
    "billing_phone": "9812345678",
    "billing_line1": "12 Museum Road",
    "billing_city": "Bengaluru",
    "billing_state": "Karnataka",
    "billing_zip": "560001",
    "billing_country": "India",
    "shipping_same_as_billing": true
}
```

**Response `201`**

```json
{
    "success": true,
    "message": "Order placed successfully with Cash on Delivery.",
    "data": {
        "order": {
            "id": 47,
            "order_number": 47,
            "status": "pending",
            "payment_status": "pending",
            "payment_method": "cod",
            "paid_at": null,
            "cancelled_at": null,
            "cancel_reason": null,
            "subtotal": 5999,
            "total": 5999,
            "refunded_total": 0,
            "refund_status": "none",
            "shipping_address": {
                "name": "Doc Preview",
                "email": "you@example.com",
                "phone": "9812345678",
                "line1": "12 Museum Road",
                "city": "Bengaluru",
                "state": "Karnataka",
                "zip": "560001",
                "country": "India"
            },
            "billing_address": {
                "name": "Doc Preview",
                "email": "you@example.com",
                "phone": "9812345678",
                "line1": "12 Museum Road",
                "city": "Bengaluru",
                "state": "Karnataka",
                "zip": "560001",
                "country": "India"
            },
            "created_at": "2026-10-07T12:32:40+00:00",
            "updated_at": "2026-10-07T12:32:40+00:00"
        },
        "payment_method": "cod",
        "is_paid": false
    },
    "meta": {}
}
```

### `GET /checkout/payment-status/{order}` &nbsp;`Bearer token required`

**Payment status - COD confirmed**  
For an order paid on delivery `state` is `cod_confirmed`. The other values you will see are `paid`, `failed` and `processing`.

**Response `200`**

```json
{
    "success": true,
    "message": "Order confirmed. Payment is due on delivery.",
    "data": {
        "order_id": 47,
        "state": "cod_confirmed",
        "payment_status": "pending",
        "transaction_status": "pending"
    },
    "meta": {}
}
```

### `POST /checkout/place-order` &nbsp;`Bearer token required`

**Place order - Razorpay (online payment)**  
The same request with `payment_method: "razorpay"`. The response carries a `razorpay` object - hand it straight to the Razorpay popup:

```js
const rzp = new Razorpay({
    ...data.razorpay,
    handler: (res) => api.verifyRazorpay({ order_id: data.order.id, ...res }),
});
rzp.open();
```

`amount` is in paise (₹ × 100). The order is created locally first, so if the customer abandons the popup the order simply stays `processing`.

**Request body**

```json
{
    "payment_method": "razorpay",
    "checkout_token": "5584a74c-3b3f-4350-b8f7-a8f5f7cebf42",
    "billing_name": "Doc Preview",
    "billing_email": "you@example.com",
    "billing_phone": "9812345678",
    "billing_line1": "12 Museum Road",
    "billing_city": "Bengaluru",
    "billing_state": "Karnataka",
    "billing_zip": "560001",
    "billing_country": "India",
    "shipping_same_as_billing": true
}
```

**Response `201`**

```json
{
    "success": true,
    "message": "Order created. Please complete Razorpay payment.",
    "data": {
        "order": {
            "id": 48,
            "order_number": 48,
            "status": "pending",
            "payment_status": "initiated",
            "payment_method": "razorpay",
            "paid_at": null,
            "cancelled_at": null,
            "cancel_reason": null,
            "subtotal": 3199,
            "total": 3199,
            "refunded_total": 0,
            "refund_status": "none",
            "shipping_address": {
                "name": "Doc Preview",
                "email": "you@example.com",
                "phone": "9812345678",
                "line1": "12 Museum Road",
                "city": "Bengaluru",
                "state": "Karnataka",
                "zip": "560001",
                "country": "India"
            },
            "billing_address": {
                "name": "Doc Preview",
                "email": "you@example.com",
                "phone": "9812345678",
                "line1": "12 Museum Road",
                "city": "Bengaluru",
                "state": "Karnataka",
                "zip": "560001",
                "country": "India"
            },
            "created_at": "2026-10-07T12:32:42+00:00",
            "updated_at": "2026-10-07T12:32:42+00:00"
        },
        "payment_method": "razorpay",
        "is_paid": false,
        "razorpay": {
            "key": "rzp_test_TkujmCnZM9yg11",
            "order_id": "order_Tl1GfwQwFH2nAN",
            "amount": 319900,
            "currency": "INR",
            "name": "Gehna",
            "description": "Order #48",
            "prefill": {
                "name": "Doc Preview",
                "email": "you@example.com",
                "contact": "9812345678"
            }
        }
    },
    "meta": {}
}
```

### `GET /checkout/payment-status/{order}` &nbsp;`Bearer token required`

**Payment status - awaiting payment**  
Poll this while the popup outcome is unknown. `state: "processing"` means neither a success nor a failure has been recorded yet.

**Response `200`**

```json
{
    "success": true,
    "message": "Payment is being confirmed. Please wait.",
    "data": {
        "order_id": 48,
        "state": "processing",
        "payment_status": "initiated",
        "transaction_status": "initiated"
    },
    "meta": {}
}
```

### `POST /checkout/verify-razorpay` &nbsp;`Bearer token required`

**Verify the Razorpay signature (forged)**  
The popup's `handler` gives you `razorpay_order_id`, `razorpay_payment_id` and `razorpay_signature`; send them with the local `order_id`. The server recomputes `HMAC_SHA256(order_id|payment_id, secret)` - here the forged signature is rejected and the order flips to `failed`.

On success the response is `200 { order, payment_status: "paid" }`, the cart is cleared and the invoice mail is queued. Verifying an already-paid order is safe: it returns success again instead of an error.

**Request body**

```json
{
    "order_id": 48,
    "razorpay_order_id": "order_Tl1GfwQwFH2nAN",
    "razorpay_payment_id": "pay_documentation",
    "razorpay_signature": "forged-signature"
}
```

**Response `422`**

```json
{
    "success": false,
    "message": "Payment verification failed. Signature mismatch.",
    "data": null,
    "errors": {},
    "meta": {}
}
```

### `GET /checkout/payment-status/{order}` &nbsp;`Bearer token required`

**Payment status - failed**  
The customer can safely retry `place-order` **with the same `checkout_token`**: an unpaid order is reused rather than duplicated.

**Response `200`**

```json
{
    "success": true,
    "message": "Payment was not completed. You can safely retry this order.",
    "data": {
        "order_id": 48,
        "state": "failed",
        "payment_status": "failed",
        "transaction_status": "failed"
    },
    "meta": {}
}
```

## 7. Search and site content

### `GET /search?q=bracelet`

**Search**  
Returns products plus the categories that matched and the customer recent searches.

**Response `200`**

```json
{
    "success": true,
    "data": [
        {
            "id": 9,
            "name": "Silver Elegant Butterflies Bracelet",
            "slug": "silver-elegant-butterflies-bracelet",
            "sku": null,
            "url": "https://astroemerging.com/gehna/product/silver-elegant-butterflies-bracelet",
            "audience": "women",
            "material_type": "silver",
            "product_type": "simple",
            "is_simple": true,
            "is_variable": false,
            "short_description": "The Silver Elegant Butterflies Bracelet is inspired by the moon showing up unexpectedly on a rainy night with a group of three twinkling stars.",
            "description": "The Design: This silver bracelet has a moon motif studded with zircon at the centre with two stars to its left and one star to its right. 925 Silver Perfect for...",
            "price": 2999.5,
            "regular_price": 5999,
            "sale_price": 2999.5,
            "base_price": 5999,
            "discount_type": "percentage",
            "discount_value": 50,
            "discount_percentage": 50,
            "is_on_sale": true,
            "price_range": null,
            "primary_image": "https://astroemerging.com/gehna/storage/products/JZBDIxaCw2LrKudyhdATWUjMKIHiRtHPMNwoSypB.png",
            "images": [
                "https://astroemerging.com/gehna/storage/products/JZBDIxaCw2LrKudyhdATWUjMKIHiRtHPMNwoSypB.png",
                "https://astroemerging.com/gehna/storage/products/nFr8k7kOaGrKgqbLlsD7OYXDicRj40PnimAgOtQT.webp",
                "... 1 more, 3 total"
            ],
            "manage_stock": true,
            "stock": 494,
            "in_stock": true,
            "category": {
                "id": 7,
                "name": "Bracelets",
                "slug": "bracelets",
                "parent_id": null
            },
            "brand": null,
            "variations": [],
            "reviews_count": 1,
            "0": "... 2 more item(s) in the real response, 3 total"
        }
    ],
    "meta": {
        "query": "bracelet",
        "current_page": 1,
        "last_page": 1,
        "per_page": 12,
        "total": 3,
        "categories": [
            {
                "id": 7,
                "name": "Bracelets",
                "slug": "bracelets",
                "parent_id": null,
                "is_root": true,
                "position": 0,
                "description": "Bracelets",
                "image": "https://astroemerging.com/gehna/storage/categories/zI3pukSqDOszpmONt48DinTED9GwhKNRe2XE20jj.jpg",
                "breadcrumb": "Bracelets",
                "products_count": 3,
                "url": "https://astroemerging.com/gehna/category/bracelets"
            }
        ],
        "recent": []
    }
}
```

### `GET /home`

**Home page**  
Sliders, categories, new arrivals, featured, on sale, brands, FAQs and testimonials in one request.

**Response `200`**

```json
{
    "success": true,
    "message": "OK",
    "data": {
        "sliders": [
            {
                "id": 1,
                "title": "Slider1",
                "subheading": null,
                "button_text": null,
                "button_link": null,
                "image": "https://astroemerging.com/gehna/storage/sliders/Jx2UPzXcbaoSE95QSjP07deexgjAH7a0CcBihv5M.png",
                "sort_order": 1,
                "0": "... 2 more item(s) in the real response, 3 total"
            }
        ],
        "categories": [
            {
                "id": 5,
                "name": "Anklets",
                "slug": "anklets",
                "parent_id": null,
                "is_root": true,
                "position": 0,
                "description": "Anklets",
                "image": "https://astroemerging.com/gehna/storage/categories/2STL6Liwt0Nqua8Ye4KFn6PCjHRYqied8cCHgW0q.jpg",
                "breadcrumb": "Anklets",
                "products_count": 0,
                "url": "https://astroemerging.com/gehna/category/anklets",
                "0": "... 9 more item(s) in the real response, 10 total"
            }
        ],
        "new_arrivals": [
            {
                "id": 9,
                "name": "Silver Elegant Butterflies Bracelet",
                "slug": "silver-elegant-butterflies-bracelet",
                "sku": null,
                "url": "https://astroemerging.com/gehna/product/silver-elegant-butterflies-bracelet",
                "audience": "women",
                "material_type": "silver",
                "product_type": "simple",
                "is_simple": true,
                "is_variable": false,
                "short_description": "The Silver Elegant Butterflies Bracelet is inspired by the moon showing up unexpectedly on a rainy night with a group of three twinkling stars.",
                "description": "The Design: This silver bracelet has a moon motif studded with zircon at the centre with two stars to its left and one star to its right. 925 Silver Perfect for...",
                "price": 2999.5,
                "regular_price": 5999,
                "sale_price": 2999.5,
                "base_price": 5999,
                "discount_type": "percentage",
                "discount_value": 50,
                "discount_percentage": 50,
                "is_on_sale": true,
                "price_range": null,
                "primary_image": "https://astroemerging.com/gehna/storage/products/JZBDIxaCw2LrKudyhdATWUjMKIHiRtHPMNwoSypB.png",
                "images": [
                    "https://astroemerging.com/gehna/storage/products/JZBDIxaCw2LrKudyhdATWUjMKIHiRtHPMNwoSypB.png",
                    "https://astroemerging.com/gehna/storage/products/nFr8k7kOaGrKgqbLlsD7OYXDicRj40PnimAgOtQT.webp",
                    "... 1 more, 3 total"
                ],
                "manage_stock": true,
                "stock": 494,
                "in_stock": true,
                "category": {
                    "id": 7,
                    "name": "Bracelets",
                    "slug": "bracelets",
                    "parent_id": null
                },
                "brand": null,
                "variations": [],
                "reviews_count": 1,
                "0": "... 7 more item(s) in the real response, 8 total"
            }
        ],
        "featured": [
            {
                "id": 9,
                "name": "Silver Elegant Butterflies Bracelet",
                "slug": "silver-elegant-butterflies-bracelet",
                "sku": null,
                "url": "https://astroemerging.com/gehna/product/silver-elegant-butterflies-bracelet",
                "audience": "women",
                "material_type": "silver",
                "product_type": "simple",
                "is_simple": true,
                "is_variable": false,
                "short_description": "The Silver Elegant Butterflies Bracelet is inspired by the moon showing up unexpectedly on a rainy night with a group of three twinkling stars.",
                "description": "The Design: This silver bracelet has a moon motif studded with zircon at the centre with two stars to its left and one star to its right. 925 Silver Perfect for...",
                "price": 2999.5,
                "regular_price": 5999,
                "sale_price": 2999.5,
                "base_price": 5999,
                "discount_type": "percentage",
                "discount_value": 50,
                "discount_percentage": 50,
                "is_on_sale": true,
                "price_range": null,
                "primary_image": "https://astroemerging.com/gehna/storage/products/JZBDIxaCw2LrKudyhdATWUjMKIHiRtHPMNwoSypB.png",
                "images": [
                    "https://astroemerging.com/gehna/storage/products/JZBDIxaCw2LrKudyhdATWUjMKIHiRtHPMNwoSypB.png",
                    "https://astroemerging.com/gehna/storage/products/nFr8k7kOaGrKgqbLlsD7OYXDicRj40PnimAgOtQT.webp",
                    "... 1 more, 3 total"
                ],
                "manage_stock": true,
                "stock": 494,
                "in_stock": true,
                "category": {
                    "id": 7,
                    "name": "Bracelets",
                    "slug": "bracelets",
                    "parent_id": null
                },
                "brand": null,
                "variations": [],
                "reviews_count": 1,
                "0": "... 7 more item(s) in the real response, 8 total"
            }
        ],
        "on_sale": [
            {
                "id": 9,
                "name": "Silver Elegant Butterflies Bracelet",
                "slug": "silver-elegant-butterflies-bracelet",
                "sku": null,
                "url": "https://astroemerging.com/gehna/product/silver-elegant-butterflies-bracelet",
                "audience": "women",
                "material_type": "silver",
                "product_type": "simple",
                "is_simple": true,
                "is_variable": false,
                "short_description": "The Silver Elegant Butterflies Bracelet is inspired by the moon showing up unexpectedly on a rainy night with a group of three twinkling stars.",
                "description": "The Design: This silver bracelet has a moon motif studded with zircon at the centre with two stars to its left and one star to its right. 925 Silver Perfect for...",
                "price": 2999.5,
                "regular_price": 5999,
                "sale_price": 2999.5,
                "base_price": 5999,
                "discount_type": "percentage",
                "discount_value": 50,
                "discount_percentage": 50,
                "is_on_sale": true,
                "price_range": null,
                "primary_image": "https://astroemerging.com/gehna/storage/products/JZBDIxaCw2LrKudyhdATWUjMKIHiRtHPMNwoSypB.png",
                "images": [
                    "https://astroemerging.com/gehna/storage/products/JZBDIxaCw2LrKudyhdATWUjMKIHiRtHPMNwoSypB.png",
                    "https://astroemerging.com/gehna/storage/products/nFr8k7kOaGrKgqbLlsD7OYXDicRj40PnimAgOtQT.webp",
                    "... 1 more, 3 total"
                ],
                "manage_stock": true,
                "stock": 494,
                "in_stock": true,
                "category": {
                    "id": 7,
                    "name": "Bracelets",
                    "slug": "bracelets",
                    "parent_id": null
                },
                "brand": null,
                "variations": [],
                "reviews_count": 1,
                "0": "... 7 more item(s) in the real response, 8 total"
            }
        ],
        "brands": [],
        "faqs": [],
        "testimonials": []
    },
    "meta": {}
}
```

### `GET /sliders`

**Hero sliders**

**Response `200`**

```json
{
    "success": true,
    "message": "OK",
    "data": [
        {
            "id": 1,
            "title": "Slider1",
            "subheading": null,
            "button_text": null,
            "button_link": null,
            "image": "https://astroemerging.com/gehna/storage/sliders/Jx2UPzXcbaoSE95QSjP07deexgjAH7a0CcBihv5M.png",
            "sort_order": 1,
            "0": "... 2 more item(s) in the real response, 3 total"
        }
    ],
    "meta": {}
}
```

### `GET /faqs`

**FAQs**

**Response `200`**

```json
{
    "success": true,
    "message": "OK",
    "data": [],
    "meta": {}
}
```

### `GET /testimonials`

**Testimonials**

**Response `200`**

```json
{
    "success": true,
    "message": "OK",
    "data": [],
    "meta": {}
}
```

### `GET /pages`

**CMS page index**  
Titles and slugs only; the body is on the detail call.

**Response `200`**

```json
{
    "success": true,
    "message": "OK",
    "data": [],
    "meta": {}
}
```

### `GET /pages/{slug}`

**One CMS page**  
Full HTML content plus meta fields.

**Response `404`**

```json
{
    "success": false,
    "message": "Page not found.",
    "data": null,
    "errors": {},
    "meta": {}
}
```

### `GET /settings`

**Store settings**  
Name, contact details, address and social links. Mail credentials are never exposed.

**Response `200`**

```json
{
    "success": true,
    "message": "OK",
    "data": {
        "site_name": "Gehna",
        "description": null,
        "email": null,
        "phone": null,
        "address": null,
        "city": null,
        "state": null,
        "country": null,
        "zip": null,
        "logo": "https://astroemerging.com/gehna/storage/settings/xOw95lBN92dOUWuwAM4G5Fx0ku1HenNB81K9JKvc.png",
        "favicon": "https://astroemerging.com/gehna/storage/settings/xRbIAtrZ7Y5TTXbAvj4S68DQl4dGQFihQqEDJv2Z.png",
        "social": []
    },
    "meta": {}
}
```

### `POST /newsletter`

**Newsletter sign-up**  
An address that is already subscribed returns 200 rather than an error.

**Request body**

```json
{
    "email": "you@example.com"
}
```

**Response `201`**

```json
{
    "success": true,
    "message": "Thanks for subscribing to our newsletter.",
    "data": {
        "subscribed": true,
        "already_subscribed": false
    },
    "meta": {}
}
```

### `GET /metal-prices`

**Metal spot rates**  
Live gold and silver prices, cached server-side.

**Response `200`**

```json
{
    "success": true,
    "data": {
        "available": true,
        "stale": false,
        "message": null,
        "currency": "INR",
        "currency_symbol": "₹",
        "unit_grams": 10,
        "unit_label": "per 10 gram (1 tola)",
        "updated_at": "2026-10-07T12:32:26+00:00",
        "updated_at_display": "07 Oct 2026, 12:32 PM",
        "metals": [
            {
                "key": "gold",
                "name": "Gold",
                "symbol": "XAU",
                "price": 127540.95,
                "price_display": "₹127,541",
                "change": -1502.85,
                "change_display": "-₹1,503",
                "change_percent": -1.16,
                "change_percent_display": "-1.16%",
                "high": 129216.23,
                "low": 127540.95,
                "open": 129170.16,
                "direction": "down",
                "is_up": false,
                "purity": [
                    {
                        "key": "24k",
                        "label": "24K",
                        "price": 127540.95,
                        "price_display": "₹127,541",
                        "0": "... 2 more item(s) in the real response, 3 total"
                    }
                ],
                "0": "... 1 more item(s) in the real response, 2 total"
            }
        ]
    },
    "meta": {
        "source": "goldapi.io",
        "cache_ttl": 900,
        "generated_at": "2026-10-07T12:32:26+00:00"
    }
}
```

## 8. Errors you must handle

### `GET /cart (no token)`

**401 Unauthenticated**  
The token is missing, expired or revoked. The client clears the stored token so the UI re-renders as signed out.

**Response `401`**

```json
{
    "success": false,
    "message": "Unauthenticated.",
    "data": null,
    "errors": {},
    "meta": {}
}
```

### `GET /products/{unknown slug}`

**404 Not found**  
Also what an **inactive** product returns, so a draft is not discoverable. There is no 403 for those.

**Response `404`**

```json
{
    "success": false,
    "message": "Product not found.",
    "data": null,
    "errors": {},
    "meta": {}
}
```

### `POST /auth/login (bad input)`

**422 Validation failed**  
`errors` is keyed by field name, ready to map straight onto form inputs.

**Request body**

```json
{
    "email": "nope",
    "password": ""
}
```

**Response `422`**

```json
{
    "success": false,
    "message": "The email field must be a valid email address. (and 1 more error)",
    "data": null,
    "errors": {
        "email": [
            "The email field must be a valid email address."
        ],
        "password": [
            "The password field is required."
        ]
    },
    "meta": {}
}
```


---

# 9. Product filters

Accepted by `GET /products`, `GET /categories/{slug}/products`,
`GET /brands/{slug}/products` and `GET /search`.

| Parameter | Example | Notes |
| --- | --- | --- |
| `q` | `q=ring` | Free text over name, SKU, short and full description |
| `category_id` | `3` or `3,7` | One id or a comma separated list |
| `brand_id` | `2` or `2,5` | One id or a comma separated list |
| `audience` | `women` | `women`, `men` or `unisex` |
| `material` | `gold` | `gold`, `silver`, `diamond`, `platinum`, `other` |
| `min_price` | `2500` | |
| `max_price` | `10000` | |
| `in_stock` | `1` | Only items that can actually be bought |
| `on_sale` | `1` | Only discounted items |
| `sort` | `price_asc` | `latest` (default), `oldest`, `price_asc`, `price_desc`, `name` |
| `per_page` | `24` | Clamped server-side to 60 |
| `page` | `2` | |

An unknown `sort` falls back to `latest` rather than erroring, so a stale
bookmarked URL with an old value still renders.

---

# 10. Object reference

Field-by-field meaning of the objects the endpoints return. Types are as they
actually arrive, so you can write the TypeScript interfaces straight from this.

## Product

```ts
interface Product {
    id: number;
    name: string;
    slug: string;
    sku: string | null;
    url: string;                    // absolute, on the storefront domain
    audience: 'women' | 'men' | 'unisex';
    material_type: 'gold' | 'silver' | 'diamond' | 'platinum' | 'other';
    product_type: 'simple' | 'variable';
    is_simple: boolean;
    is_variable: boolean;
    short_description: string | null;
    description: string | null;

    // `price` is what the customer pays (variation aware, sale price applied).
    // `regular_price` is the number to strike through.
    price: number;
    regular_price: number;
    sale_price: number | null;
    base_price: number | null;
    discount_type: 'percentage' | 'fixed' | null;
    discount_value: number | null;
    discount_percentage: number | null;
    is_on_sale: boolean;
    price_range: { min: number; max: number } | null;   // variable products

    primary_image: string | null;    // absolute URL
    images: string[];                 // absolute URLs

    manage_stock: boolean;
    stock: number;
    in_stock: boolean;

    category: CategorySummary | null;
    brand: Brand | null;
    variations: ProductVariation[];
    reviews_count: number;
}
```

## ProductVariation

Populated on the detail endpoint. An empty array on a list endpoint means the
product has no variations yet, not that they are hidden.

```ts
interface ProductVariation {
    id: number;
    product_id: number;
    sku: string | null;
    price: number;                 // effective selling price
    regular_price: number;
    sale_price: number | null;
    discount_percentage: number | null;
    is_on_sale: boolean;
    stock: number;
    in_stock: boolean;
    is_active: boolean;
    description: string | null;
    attributes: Record<string, string>;   // e.g. { "Size": "18", "Weight": "5.2g" }
    images: string[];
}
```

## Cart line

```ts
interface CartLine {
    id: number;                     // this is the {id} for PATCH and DELETE
    product_id: number;
    product_variation_id: number | null;
    combo_id: number | null;
    name: string | null;
    slug: string | null;
    sku: string | null;
    image: string | null;
    options: Record<string, string>;   // variation attributes, e.g. { "Size": "18" }
    price: number;                      // unit price, resolved server-side
    quantity: number;
    is_combo: boolean;                  // true when this line is part of a bundle
    combo: {
        id: number;
        name: string;
        slug: string;
        combo_price: number;            // the whole bundle, not this line
    } | null;
    combo_units: number;                // units of this line covered by a detected bundle
    combo_names: string[];              // names of the bundles applying to this line
    regular_unit_price: number;         // pre-combo price, for the struck-through figure
    line_total: number;                 // what the customer is charged (price * quantity)
    available_stock: number;
    url: string | null;
}
```

`line_total` is not always `price * quantity`. When a bundle applies, it is the
discounted amount, so render `regular_unit_price` struck through next to it to
show the saving. `combo_units` is `0` for a line added through
`POST /cart/combo` (its discount is already inside `price`) and greater than `0`
when `ComboService` detected a bundle from ordinary product lines.

```ts
interface CartMeta {
    subtotal: number;      // sum of line prices
    combo_discount: number;// bundle saving taken off by an auto-detected bundle
    total: number;         // subtotal - combo_discount (shipping/tax are added at checkout)
    count: number;         // total units, drives the header badge
}
```

## User

```ts
interface User {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    gehna_coins: number;               // loyalty balance
    roles: string[];                   // e.g. ["customer"] or ["admin"]
    is_admin: boolean;
    email_verified_at: string | null;
    created_at: string;                // ISO 8601
}
```

`roles` lets the React app show or hide admin-only chrome without a second
round trip. The password hash and remember token are never serialised.

## Order

```ts
interface Order {
    id: number;
    order_number: number;              // same as id, for display
    status: string;                    // pending, processing, shipped, delivered, cancelled
    payment_status: string;            // pending, paid, failed, refunded
    payment_method: string | null;
    paid_at: string | null;
    cancelled_at: string | null;
    cancel_reason: string | null;
    subtotal: number;
    total: number;
    refunded_total: number;
    refund_status: string | null;
    shipping_address: Address;
    billing_address: Address;
    items: OrderItem[];
    items_count: number;
    created_at: string;
    updated_at: string;
}
```

## Envelope

```ts
interface ApiResponse<T> {
    success: boolean;
    message: string;
    data: T;
    meta: Record<string, unknown>;
}

interface ApiError {
    success: false;
    message: string;
    data: null;
    errors: Record<string, string[]>;
    meta: Record<string, unknown>;
}

interface PaginatedMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}
```


## Category

```ts
interface Category {
    id: number;
    name: string;
    slug: string;
    parent_id: number | null;
    is_root: boolean;
    position: number;
    description: string | null;
    image: string | null;
    breadcrumb: string;            // e.g. "Jewellery > Necklaces"
    products_count: number;         // active products only
    url: string;
    children: Category[];           // only on ?tree=1 and the detail endpoint
}

interface CategorySummary {
    id: number;
    name: string;
    slug: string;
    parent_id: number | null;
}
```

`products_count` counts **active** products only, so the badge on a category
card always matches what the category page will actually show.


---

# 11. Integration notes

Things that will save you debugging time.

**Prices come from the server, always.** The cart ignores any price in the
request body and resolves the unit price from the product and variation rows. Do
not try to send a price; read the one that comes back.

**Image and page URLs are absolute.** Every media field is a full URL built from
the storefront domain. A path-only `/storage/...` would resolve against your dev
server and every image would 404. Use the URLs as given.

**Inactive products return 404, not 403.** A draft is not discoverable, so do not
treat a 404 on a product as "the server is broken" - it is "not for sale".

**Cart, wishlist and orders are always scoped to the token's owner.** A line id
belonging to another account returns 404 rather than 403, for the same reason.

**`/home` exists to save you ten round trips.** A landing page that fires one
`/home` call renders in one paint. A page that fetches ten things renders ten
times.

**Wishlist toggling is idempotent.** `POST /wishlist/toggle` adds or removes and
tells you which in `data.in_wishlist`. Drive the heart icon from that value
rather than assuming the click always adds.

**Checkout runs over this API with the same guardrails as the Blade checkout.**
`GET /checkout/summary` prices the cart and hands you a `checkout_token`;
`POST /checkout/place-order` then moves stock, calls Razorpay and records the
order. Send that token back on every retry: a double-tapped button or a network
repeat returns the order that already exists instead of creating a second one.
The gateway webhook (`POST /webhooks/razorpay`) is server-to-server - your
client never calls it. `/orders` stays read-only history.

**Reviews expose the author's name and initials only.** A review is public, so a
customer's email or phone never rides along inside a product payload.

**CORS.** Your React origin must be in `API_CORS_ORIGINS` on the server or the
browser will block the responses before they reach your code. Local dev origins
(`http://localhost:5173`, `http://localhost:3000`) are already listed.

---

# 12. Using the client

```js
import api, { authApi, productApi, ApiError } from './api/gehnaApi';

// Sign in. The token is stored for you.
await api.login({ email: 'you@example.com', password: 'your-password' });

// Product grid with filters and pagination.
const { data: products, meta } = await api.getProducts({
    on_sale: 1,
    sort: 'price_asc',
    per_page: 24,
    page: 1,
});

console.log(meta.total, meta.last_page);

// Product detail.
const { data: { product, reviews } } = await api.getProduct(product.slug);
console.log(product.price, product.variations, reviews.summary.average);

// Cart and wishlist.
await api.addToCart({ product_id: 7, quantity: 2 });
const { meta: cartMeta } = await api.getCart();
console.log(cartMeta.count, cartMeta.subtotal);
await api.toggleWishlist({ product_id: 7 });

// Checkout: price the cart, then place the order.
const { data: summary } = await api.getCheckoutSummary();
console.log(summary.pricing.grand_total, summary.payment_providers);
await api.applyCoupon('SAVE10');                       // optional
const placed = await api.placeOrder({
    payment_method: 'razorpay',                        // or 'cod'
    checkout_token: summary.checkout_token,            // idempotent retry
    billing_name: 'Ravi Kumar',
    billing_email: 'you@example.com',
    billing_phone: '9812345678',
    billing_line1: '12 MG Road',
    billing_city: 'Bengaluru',
    billing_state: 'Karnataka',
    billing_zip: '560001',
    billing_country: 'India',
    shipping_same_as_billing: true,
});
// placed.data.razorpay feeds the Razorpay popup; the popup handler then calls
// api.verifyRazorpay({ order_id: placed.data.order.id, ...paymentResponse }).

// Errors: one type, one check.
try {
    await api.getCart();
} catch (error) {
    if (error instanceof ApiError) {
        if (error.isUnauthenticated) redirectToLogin();
        if (error.isValidationError)  showFieldErrors(error.errors);
        if (error.isNetworkError)     showOfflineBanner();
    }
}

// Sign out.
await api.logout();
```

---

# 13. Server configuration

```dotenv
# Public base URL including the install folder. Leave empty to auto-detect.
STOREFRONT_URL=
API_VERSION=v1
API_PER_PAGE=12
API_PER_PAGE_MAX=60
API_CORS_ORIGINS=https://astroemerging.com,https://www.astroemerging.com,http://localhost:5173,http://localhost:3000
SANCTUM_STATEFUL_DOMAINS=astroemerging.com,www.astroemerging.com,localhost,localhost:5173
SANCTUM_TOKEN_PREFIX=
```

`STOREFRONT_URL` is intentionally empty: the app detects the `/gehna` sub-folder
from the incoming request, so it works unchanged in local development and in
production. Set it only if a reverse proxy makes the request root wrong.

After changing anything on the server:

```bash
php artisan config:clear && php artisan route:clear && php artisan view:clear
```

---

## How this document is kept honest

The JSON above was produced by calling the running API and capturing the
responses, with the local dev origin rewritten to the production domain and long
text shortened. Re-running that capture after a backend change regenerates this
file, so the examples cannot quietly drift from reality.

The API itself is covered by `tests/Feature/ApiStorefrontTest.php`:

```bash
php artisan test --filter=ApiStorefrontTest
```

23 tests cover the envelope, catalogue filters, product detail pricing, token
auth, cart/wishlist/orders isolation, and the guarantee that mail credentials
never leave the server.

Checkout, coupons, Gehna Coins, order placement and the Razorpay signature
verification are covered separately:

```bash
php artisan test --filter=ApiCheckoutTest
```

A complete React checkout page built on the same client is included at
`docs/react/CheckoutPage.jsx`.