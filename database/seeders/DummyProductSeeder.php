<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DummyProductSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Create Tags ───
        $tagNames = [
            'New Arrival', 'Best Seller', 'On Sale', 'Featured',
            'Top Rated', 'Limited Edition', 'Heavy Duty', 'Eco Friendly',
            'Premium', 'Budget Pick',
        ];
        $tags = collect();
        foreach ($tagNames as $name) {
            $tags->push(Tag::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'slug' => Str::slug($name)]
            ));
        }

        // ─── IDs ───
        $brandIds    = Brand::pluck('id')->toArray();
        $categoryIds = Category::pluck('id')->toArray();
        $attributes  = Attribute::with('values')->where('is_active', true)->get();

        // ─── 10 Simple Products ───
        $simpleProducts = [
            [
                'name'              => 'Loctite 243 Medium Strength Threadlocker 10ml',
                'short_description' => 'Medium-strength threadlocker for fasteners up to M36.',
                'description'       => "Loctite 243 is a general purpose threadlocker that provides a medium strength bond. Ideal for locking and sealing threaded fasteners which require normal disassembly with standard hand tools.\n\nFeatures:\n• Oil tolerant — works on slightly oily surfaces\n• Thixotropic — won't drip or migrate\n• Blue color for identification\n• Temperature range: -55°C to +180°C",
                'base_price' => 320.00,
                'sale_price' => 289.00,
                'sku'        => 'LOC-243-10ML',
                'stock'      => 150,
                'weight'     => 0.05,
                'category_id' => 5, // Threadlocker
                'brand_id'    => 1, // Loctite
            ],
            [
                'name'              => 'Loctite 495 Super Bonder Instant Adhesive 20g',
                'short_description' => 'General purpose instant adhesive for plastics and rubber.',
                'description'       => "Loctite 495 is a low viscosity, fast curing instant adhesive designed for the bonding of a wide range of materials including metals, plastics, and elastomers.\n\nKey properties:\n• Fixture time: 5-20 seconds\n• Tensile strength: 18 N/mm²\n• Service temperature: -55°C to 80°C\n• Clear bond line",
                'base_price' => 450.00,
                'sale_price' => null,
                'sku'        => 'LOC-495-20G',
                'stock'      => 85,
                'weight'     => 0.04,
                'category_id' => 4, // Instant Adhesive
                'brand_id'    => 1,
            ],
            [
                'name'              => 'Actools Precision Digital Caliper 0-150mm',
                'short_description' => 'Stainless steel digital caliper with LCD display.',
                'description'       => "High-precision digital caliper with hardened stainless steel measuring surfaces.\n\nSpecifications:\n• Range: 0-150mm / 0-6 inch\n• Resolution: 0.01mm / 0.0005\"\n• Accuracy: ±0.02mm\n• Large LCD display with auto-off\n• Includes storage case",
                'base_price' => 1250.00,
                'sale_price' => 999.00,
                'sku'        => 'ACT-DCAL-150',
                'stock'      => 45,
                'weight'     => 0.32,
                'category_id' => 11, // Milling Tools
                'brand_id'    => 2, // Actools
            ],
            [
                'name'              => 'HSS Adjustable Hand Reamer Set 6-Piece',
                'short_description' => 'Complete set of adjustable reamers from 8mm to 25mm.',
                'description'       => "Professional-grade HSS adjustable hand reamers for precision hole finishing.\n\nSet includes:\n• 8/A (8.75-9.25mm)\n• 9/A (10.75-11.75mm)\n• 10/A (12.75-13.75mm)\n• 11/A (15.25-16.75mm)\n• 12/A (19.25-21.25mm)\n• 13/A (23.75-26.25mm)\n\nBlades are individually adjustable for precise sizing.",
                'base_price' => 3500.00,
                'sale_price' => null,
                'sku'        => 'ACT-REAMER-6PC',
                'stock'      => 20,
                'weight'     => 2.80,
                'category_id' => 9, // HSS Reamers
                'brand_id'    => 2,
            ],
            [
                'name'              => 'Loctite 518 Gasket Maker Flange Sealant 50ml',
                'short_description' => 'Anaerobic flange sealant for rigid metal flanges.',
                'description'       => "Loctite 518 is a semi-flexible, anaerobic gasket maker designed for use on rigid flanged connections in powertrain and drivetrain assemblies.\n\nBenefits:\n• Fills gaps up to 0.25mm\n• Resists engine fluids\n• Temperature range: -55°C to +150°C\n• Red color for easy identification",
                'base_price' => 890.00,
                'sale_price' => 799.00,
                'sku'        => 'LOC-518-50ML',
                'stock'      => 60,
                'weight'     => 0.10,
                'category_id' => 6, // Gasket
                'brand_id'    => 1,
            ],
            [
                'name'              => 'Rahish Heavy Duty Bench Vise 6-inch',
                'short_description' => 'Cast iron bench vise with swivel base and anvil.',
                'description'       => "Heavy-duty cast iron bench vise with 360° swivel base.\n\nSpecifications:\n• Jaw width: 6 inches (150mm)\n• Jaw opening: 6 inches max\n• Throat depth: 3.5 inches\n• Built-in pipe jaws and anvil\n• Powder coated for corrosion resistance",
                'base_price' => 2800.00,
                'sale_price' => null,
                'sku'        => 'RAH-VISE-6IN',
                'stock'      => 30,
                'weight'     => 12.50,
                'category_id' => 11,
                'brand_id'    => 3, // Rahish
            ],
            [
                'name'              => 'Loctite 638 Retaining Compound High Strength 50ml',
                'short_description' => 'High strength retaining compound for cylindrical assemblies.',
                'description'       => "Loctite 638 is a high strength retaining compound for bonding cylindrical fitting parts, particularly where maximum resistance to dynamic loads is required.\n\nProperties:\n• Gap fill: up to 0.15mm\n• Shear strength: 25 N/mm² on steel\n• Temperature range: -55°C to +175°C\n• Green color",
                'base_price' => 1100.00,
                'sale_price' => 950.00,
                'sku'        => 'LOC-638-50ML',
                'stock'      => 40,
                'weight'     => 0.10,
                'category_id' => 7, // Retaining
                'brand_id'    => 1,
            ],
            [
                'name'              => 'Actools Carbide End Mill 4-Flute 10mm',
                'short_description' => 'Solid carbide end mill for general-purpose milling.',
                'description'       => "High-performance solid carbide end mill with TiAlN coating.\n\nSpecifications:\n• Diameter: 10mm\n• Flute count: 4\n• Overall length: 75mm\n• Flute length: 25mm\n• Helix angle: 30°\n• Coating: TiAlN for extended tool life\n• Suitable for steel, stainless steel, cast iron",
                'base_price' => 780.00,
                'sale_price' => null,
                'sku'        => 'ACT-EM4F-10MM',
                'stock'      => 100,
                'weight'     => 0.08,
                'category_id' => 11,
                'brand_id'    => 2,
            ],
            [
                'name'              => 'Rahish Portable Honing Machine RH-200',
                'short_description' => 'Portable cylinder honing machine for automotive and industrial use.',
                'description'       => "Professional portable honing machine for precision bore finishing.\n\nSpecifications:\n• Honing range: 50mm to 200mm bore diameter\n• Motor: 1500W variable speed\n• RPM range: 200-800\n• Includes 3 sets of honing stones\n• Carrying case included\n• Ideal for engine cylinder reconditioning",
                'base_price' => 28000.00,
                'sale_price' => 24999.00,
                'sku'        => 'RAH-HONE-200',
                'stock'      => 8,
                'weight'     => 18.00,
                'category_id' => 10, // Honing Machine
                'brand_id'    => 3,
            ],
            [
                'name'              => 'Loctite 401 Instant Adhesive Universal 20g',
                'short_description' => 'Fast-curing, surface-insensitive instant adhesive.',
                'description'       => "Loctite 401 is a medium viscosity, fast curing instant adhesive with enhanced surface-insensitivity.\n\nAdvantages:\n• Bonds acidic and porous surfaces\n• Fixture time: 3-10 seconds\n• Tensile strength: 17 N/mm²\n• Wide material compatibility\n• Industry standard for production assembly",
                'base_price' => 510.00,
                'sale_price' => 475.00,
                'sku'        => 'LOC-401-20G',
                'stock'      => 200,
                'weight'     => 0.04,
                'category_id' => 4,
                'brand_id'    => 1,
            ],
        ];

        foreach ($simpleProducts as $data) {
            $product = Product::create(array_merge($data, [
                'slug'         => Str::slug($data['name']),
                'product_type' => 'simple',
                'is_active'    => true,
                'manage_stock' => true,
            ]));

            // Assign 2-3 random tags to each
            $product->tags()->sync($tags->random(rand(2, 3))->pluck('id')->toArray());
        }

        $this->command->info('✅ 10 Simple products created.');

        // ─── 10 Variable Products ───
        $variableProducts = [
            [
                'name'              => 'Actools Professional Mechanics Gloves',
                'short_description' => 'Durable synthetic leather work gloves with reinforced palm.',
                'description'       => "Professional-grade mechanics gloves with excellent grip and dexterity.\n\n• Synthetic leather palm with silicone grip pattern\n• Breathable spandex back\n• Reinforced fingertips and knuckle protection\n• Hook & loop cuff closure\n• Machine washable",
                'base_price' => 650.00,
                'category_id' => 11,
                'brand_id'    => 2,
                'variations'  => [
                    ['attributes' => ['Size' => '100', 'Color' => 'Black'], 'sku' => 'ACT-GLV-S-BLK', 'price' => 650. , 'stock' => 50],
                    ['attributes' => ['Size' => '200', 'Color' => 'Black'], 'sku' => 'ACT-GLV-M-BLK', 'price' => 650. , 'stock' => 75],
                    ['attributes' => ['Size' => '300', 'Color' => 'Black'], 'sku' => 'ACT-GLV-L-BLK', 'price' => 700. , 'stock' => 60],
                    ['attributes' => ['Size' => '200', 'Color' => 'Red'],   'sku' => 'ACT-GLV-M-RED', 'price' => 680. , 'stock' => 40],
                    ['attributes' => ['Size' => '300', 'Color' => 'Red'],   'sku' => 'ACT-GLV-L-RED', 'price' => 730. , 'stock' => 30],
                ],
            ],
            [
                'name'              => 'Loctite 242 Threadlocker Multi-Pack',
                'short_description' => 'Medium strength blue threadlocker available in multiple sizes.',
                'description'       => "Loctite 242 Threadlocker Blue in various sizes for different applications.\n\n• Medium strength — removable with hand tools\n• Oil tolerant for contaminated surfaces\n• Prevents loosening from vibration\n• Temperature range: -55°C to +150°C",
                'base_price' => 210.00,
                'category_id' => 5,
                'brand_id'    => 1,
                'variations'  => [
                    ['attributes' => ['Size' => '100'], 'sku' => 'LOC-242-10ML',  'price' => 210,  'stock' => 120],
                    ['attributes' => ['Size' => '200'], 'sku' => 'LOC-242-50ML',  'price' => 680,  'stock' => 80],
                    ['attributes' => ['Size' => '300'], 'sku' => 'LOC-242-250ML', 'price' => 2300, 'stock' => 25],
                ],
            ],
            [
                'name'              => 'Rahish Industrial Safety Helmet',
                'short_description' => 'Hard hat with ratchet suspension and ventilation.',
                'description'       => "Tough ABS shell safety helmet with 6-point ratchet suspension.\n\n• Conforms to IS 2925 standards\n• UV-stabilized ABS outer shell\n• Adjustable ratchet headband\n• Ventilated design for comfort\n• Rain gutter and accessory slots",
                'base_price' => 450.00,
                'category_id' => 11,
                'brand_id'    => 3,
                'variations'  => [
                    ['attributes' => ['Color' => 'Red'],   'sku' => 'RAH-HLM-RED',   'price' => 450, 'stock' => 100],
                    ['attributes' => ['Color' => 'Blue'],  'sku' => 'RAH-HLM-BLUE',  'price' => 450, 'stock' => 80],
                    ['attributes' => ['Color' => 'Black'], 'sku' => 'RAH-HLM-BLACK', 'price' => 480, 'stock' => 60],
                ],
            ],
            [
                'name'              => 'Actools Precision Screwdriver Set',
                'short_description' => 'Multi-bit screwdriver set with magnetic tips.',
                'description'       => "Premium precision screwdriver set for electronics and fine mechanical work.\n\n• Chrome vanadium steel bits\n• Magnetic tips for secure fastener hold\n• Anti-slip rotating cap\n• Carrying case with labeled slots\n• Lifetime warranty",
                'base_price' => 850.00,
                'category_id' => 11,
                'brand_id'    => 2,
                'variations'  => [
                    ['attributes' => ['Size' => '100', 'Color' => 'Blue'],  'sku' => 'ACT-SCRW-25-BLU', 'price' => 850,  'stock' => 45],
                    ['attributes' => ['Size' => '200', 'Color' => 'Blue'],  'sku' => 'ACT-SCRW-50-BLU', 'price' => 1350, 'stock' => 30],
                    ['attributes' => ['Size' => '300', 'Color' => 'Blue'],  'sku' => 'ACT-SCRW-75-BLU', 'price' => 1800, 'stock' => 20],
                    ['attributes' => ['Size' => '200', 'Color' => 'Red'],   'sku' => 'ACT-SCRW-50-RED', 'price' => 1400, 'stock' => 25],
                ],
            ],
            [
                'name'              => 'Loctite 577 Thread Sealant Medium Strength',
                'short_description' => 'General purpose pipe sealant for metal threads.',
                'description'       => "Loctite 577 is a medium strength, general purpose thread sealant designed to lock and seal metal pipes and fittings.\n\n• Replaces PTFE tape and pipe dope\n• Seals immediately at low pressure\n• Temperature range: -55°C to +150°C\n• Available in multiple sizes",
                'base_price' => 380.00,
                'category_id' => 6,
                'brand_id'    => 1,
                'variations'  => [
                    ['attributes' => ['Size' => '100'], 'sku' => 'LOC-577-50ML',  'price' => 380,  'stock' => 90],
                    ['attributes' => ['Size' => '200'], 'sku' => 'LOC-577-250ML', 'price' => 1500, 'stock' => 45],
                    ['attributes' => ['Size' => '300'], 'sku' => 'LOC-577-1L',    'price' => 4800, 'stock' => 15],
                ],
            ],
            [
                'name'              => 'Rahish Adjustable Torque Wrench',
                'short_description' => 'Click-type torque wrench with calibration certificate.',
                'description'       => "Professional-grade adjustable torque wrench for precision fastening.\n\n• Bi-directional click mechanism\n• ±4% accuracy (CW) per ISO 6789\n• Dual scale: Nm and ft-lbs\n• Quick release ratchet head\n• Includes calibration certificate\n• Protective storage case",
                'base_price' => 3200.00,
                'category_id' => 11,
                'brand_id'    => 3,
                'variations'  => [
                    ['attributes' => ['Size' => '100'], 'sku' => 'RAH-TRQ-1/4', 'price' => 3200,  'stock' => 25],
                    ['attributes' => ['Size' => '200'], 'sku' => 'RAH-TRQ-3/8', 'price' => 3800,  'stock' => 35],
                    ['attributes' => ['Size' => '300'], 'sku' => 'RAH-TRQ-1/2', 'price' => 4500,  'stock' => 20],
                ],
            ],
            [
                'name'              => 'Actools Carbide Drill Bit Set',
                'short_description' => 'Solid carbide drill bits with TiN coating.',
                'description'       => "Professional solid carbide drill bits with Titanium Nitride coating.\n\n• 135° split point for self-centering\n• High-temperature TiN coating\n• Ground from solid carbide blanks\n• Suitable for steel, aluminum, brass\n• Stored in indexed metal case",
                'base_price' => 1200.00,
                'category_id' => 8,
                'brand_id'    => 2,
                'variations'  => [
                    ['attributes' => ['Size' => '100', 'Color' => 'Black'], 'sku' => 'ACT-DRL-5PC-BLK',  'price' => 1200, 'stock' => 55],
                    ['attributes' => ['Size' => '200', 'Color' => 'Black'], 'sku' => 'ACT-DRL-10PC-BLK', 'price' => 2100, 'stock' => 40],
                    ['attributes' => ['Size' => '300', 'Color' => 'Black'], 'sku' => 'ACT-DRL-20PC-BLK', 'price' => 3600, 'stock' => 20],
                    ['attributes' => ['Size' => '100', 'Color' => 'Blue'],  'sku' => 'ACT-DRL-5PC-BLU',  'price' => 1300, 'stock' => 35],
                    ['attributes' => ['Size' => '200', 'Color' => 'Blue'],  'sku' => 'ACT-DRL-10PC-BLU', 'price' => 2250, 'stock' => 25],
                ],
            ],
            [
                'name'              => 'Loctite 648 Retaining Compound High Temp',
                'short_description' => 'High temperature retaining compound for heavy press fits.',
                'description'       => "Loctite 648 is designed for bonding cylindrical fitting parts in high-temperature environments.\n\n• Temperature range: -55°C to +200°C\n• Shear strength: 25 N/mm²\n• Gap fill: up to 0.15mm\n• Fast fixture time: 5 minutes\n• Green fluorescent for QC inspection",
                'base_price' => 950.00,
                'category_id' => 7,
                'brand_id'    => 1,
                'variations'  => [
                    ['attributes' => ['Size' => '100'], 'sku' => 'LOC-648-10ML', 'price' => 450,  'stock' => 100],
                    ['attributes' => ['Size' => '200'], 'sku' => 'LOC-648-50ML', 'price' => 950,  'stock' => 65],
                    ['attributes' => ['Size' => '300'], 'sku' => 'LOC-648-250ML','price' => 3800, 'stock' => 15],
                ],
            ],
            [
                'name'              => 'Rahish Workshop Apron Premium Leather',
                'short_description' => 'Full-grain leather workshop apron with tool pockets.',
                'description'       => "Hand-crafted premium leather workshop apron.\n\n• Full-grain cowhide leather\n• Reinforced cross-back straps\n• 6 tool pockets and pencil slot\n• Adjustable waist strap\n• Heat and spark resistant\n• Ideal for woodworking, welding, metalworking",
                'base_price' => 2200.00,
                'category_id' => 11,
                'brand_id'    => 3,
                'variations'  => [
                    ['attributes' => ['Color' => 'Black'], 'sku' => 'RAH-APRN-BLK',  'price' => 2200, 'stock' => 30],
                    ['attributes' => ['Color' => 'Red'],   'sku' => 'RAH-APRN-RED',  'price' => 2200, 'stock' => 20],
                    ['attributes' => ['Color' => 'Blue'],  'sku' => 'RAH-APRN-BLUE', 'price' => 2350, 'stock' => 15],
                ],
            ],
            [
                'name'              => 'Actools Multi-Purpose Lubricant Spray',
                'short_description' => 'Penetrating lubricant, rust dissolver, and protectant.',
                'description'       => "All-in-one industrial lubricant spray.\n\n• Penetrates and loosens rusted parts\n• Displaces moisture\n• Prevents corrosion\n• Lubricates moving parts\n• Cleans grease and grime\n• 360° valve works in any position",
                'base_price' => 280.00,
                'category_id' => 11,
                'brand_id'    => 2,
                'variations'  => [
                    ['attributes' => ['Size' => '100'], 'sku' => 'ACT-LUB-200ML', 'price' => 280,  'stock' => 200],
                    ['attributes' => ['Size' => '200'], 'sku' => 'ACT-LUB-400ML', 'price' => 480,  'stock' => 150],
                    ['attributes' => ['Size' => '300'], 'sku' => 'ACT-LUB-1L',    'price' => 950,  'stock' => 60],
                ],
            ],
        ];

        // Get attribute IDs for pivot
        $sizeAttr  = $attributes->firstWhere('name', 'Size');
        $colorAttr = $attributes->firstWhere('name', 'Color');

        foreach ($variableProducts as $data) {
            $variations = $data['variations'];
            unset($data['variations']);

            $product = Product::create(array_merge($data, [
                'slug'         => Str::slug($data['name']),
                'product_type' => 'variable',
                'is_active'    => true,
                'manage_stock' => false,
                'sale_price'   => null,
                'sku'          => null,
                'stock'        => 0,
            ]));

            // Determine which attributes this product uses
            $usedAttrs = [];
            foreach ($variations as $v) {
                foreach (array_keys($v['attributes']) as $attrName) {
                    $usedAttrs[$attrName] = true;
                }
            }

            // Attach attributes to product
            $position = 0;
            foreach ($usedAttrs as $attrName => $_) {
                $attr = $attributes->firstWhere('name', $attrName);
                if ($attr) {
                    $product->attributes()->attach($attr->id, ['position' => $position]);
                    $position++;
                }
            }

            // Create variations
            foreach ($variations as $v) {
                ProductVariation::create([
                    'product_id' => $product->id,
                    'sku'        => $v['sku'],
                    'price'      => $v['price'],
                    'stock'      => $v['stock'],
                    'attributes' => json_encode($v['attributes']),
                    'is_active'  => true,
                ]);
            }

            // Assign 2-3 random tags
            $product->tags()->sync($tags->random(rand(2, 3))->pluck('id')->toArray());
        }

        $this->command->info('✅ 10 Variable products created.');
        $this->command->info('✅ ' . $tags->count() . ' Tags created/verified.');
        $this->command->info('🎉 Dummy data seeding complete!');
    }
}
