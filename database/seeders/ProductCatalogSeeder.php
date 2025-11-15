<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ProductCatalogSeeder extends Seeder
{
	public function run(): void
	{
		$tenant = Tenant::query()->first();

		if (!$tenant) {
			$this->command?->warn('No tenant found. Skipping product catalog seeding.');
			return;
		}

		$tenantId = $tenant->id;

		Schema::disableForeignKeyConstraints();
		Product::query()->truncate();
		ProductCategory::query()->truncate();
		Schema::enableForeignKeyConstraints();

		$packPresets = $this->packPresets();
		$variantProfileSets = $this->variantProfileSets();
		$categoryArchetypes = $this->categoryArchetypes();

		$barcodeSeed = 9400000000000;
		$productSequence = 1;
		$categorySortOrder = 1;
		$categoryCount = 0;
		$productCount = 0;

		foreach ($categoryArchetypes as $archetype) {
			$baseMetadata = $archetype['baseMetadata'] ?? [];
			$archetypePackPreset = $archetype['packPreset'] ?? null;
			$archetypeVariantProfileKey = $archetype['variantProfileKey'] ?? 'default';
			$archetypeTaxClass = $archetype['taxClass'] ?? ['code' => 'SR-VAT0', 'rate' => 0.0];

			foreach ($archetype['nameVariants'] as $variantInfo) {
				$categoryName = $variantInfo['name'];
				$slug = method_exists(ProductCategory::class, 'generateSlug')
					? ProductCategory::generateSlug($categoryName)
					: Str::slug($categoryName);

				$metadata = array_filter([
					'icon' => $variantInfo['icon'] ?? ($baseMetadata['icon'] ?? null),
					'displayColor' => $variantInfo['displayColor'] ?? ($baseMetadata['displayColor'] ?? null),
					'region' => $variantInfo['region'] ?? null,
					'theme' => $baseMetadata['theme'] ?? null,
					'subtitle' => $variantInfo['description'] ?? null,
				]);

				$category = ProductCategory::create([
					'tenant_id' => $tenantId,
					'name' => $categoryName,
					'slug' => $slug,
					'description' => $variantInfo['description'] ?? 'Sri Lankan specialty selection.',
					'sort_order' => $categorySortOrder++,
					'is_active' => true,
					'metadata' => $metadata ?: null,
				]);

				$categoryCount++;

				foreach ($archetype['productFamilies'] as $family) {
					$familyVariantProfileKey = $family['variantProfileKey'] ?? $archetypeVariantProfileKey;
					$variantProfiles = $variantProfileSets[$familyVariantProfileKey] ?? $variantProfileSets['default'];

					$familyPackPreset = $family['packPreset'] ?? $archetypePackPreset;
					$packOptions = $familyPackPreset ? ($packPresets[$familyPackPreset] ?? []) : [];
					$baseAttributes = $family['attributes'] ?? [];

					foreach ($family['variations'] as $variation) {
						foreach ($variantProfiles as $variantProfile) {
							$nameParts = [
								$variation['prefix'] ?? null,
								$family['baseName'],
								$variation['suffix'] ?? null,
							];
							$productName = trim(preg_replace('/\s+/', ' ', implode(' ', array_filter($nameParts))));
							$sequence = str_pad((string) $productSequence, 4, '0', STR_PAD_LEFT);
							$sku = strtoupper($family['skuPrefix']).'-'.$variantProfile['skuSuffix'].'-'.$sequence;

							$price = round(
								$family['basePrice']
								* ($variation['priceMultiplier'] ?? 1.0)
								* ($variantProfile['priceMultiplier'] ?? 1.0),
								2
							);

							$costRatio = $variation['costRatio'] ?? $family['costRatio'] ?? 0.62;
							$costPrice = round($price * $costRatio, 2);
							$barcode = str_pad((string) ($barcodeSeed + $productSequence), 13, '0', STR_PAD_LEFT);

							$stockBase = $variation['stockBase']
								?? $family['stockBase']
								?? ($packOptions ? array_sum(array_column($packOptions, 'stock')) : 140);
							$stockQuantity = (int) max(12, round($stockBase * ($variantProfile['stockMultiplier'] ?? 1.0)));

							$weightBase = $variation['baseWeight'] ?? $family['baseWeight'] ?? ($packOptions[0]['weight'] ?? 0.5);
							$weight = round(
								$weightBase
								* ($variation['weightMultiplier'] ?? 1.0)
								* ($variantProfile['weightMultiplier'] ?? 1.0),
								3
							);

							$descriptionParts = [
								$family['description'] ?? null,
								$variation['descriptionSuffix'] ?? null,
								$variantProfile['descriptionAddon'] ?? null,
							];

							if (!empty($variantInfo['region'])) {
								$descriptionParts[] = 'Region focus: '.$variantInfo['region'];
							}

							$description = trim(implode(' ', array_filter($descriptionParts)));

							$originParts = array_filter([
								$family['baseOrigin'] ?? null,
								$variantInfo['region'] ?? null,
							]);
							$origin = $originParts ? implode(' | ', $originParts) : null;

							$attributeBundle = array_filter(array_merge(
								$baseAttributes,
								$variation['attributeOverrides'] ?? [],
								[
									'origin' => $origin,
									'variantLabel' => $variantProfile['label'] ?? null,
								]
							));

							$brandSlug = !empty($family['brand']) ? Str::slug($family['brand']) : null;

							$tags = array_values(array_filter(array_unique(array_merge(
								$family['tags'] ?? [],
								$variation['extraTags'] ?? [],
								$variantProfile['extraTags'] ?? [],
								[$category->slug, $brandSlug]
							))));

							$images = [
								'/storage/catalog/'.Str::slug($category->name).'/'.Str::slug($productName).'-1.jpg',
								'/storage/catalog/'.Str::slug($category->name).'/'.Str::slug($productName).'-2.jpg',
							];

							$taxClass = $variation['taxClass'] ?? $family['taxClass'] ?? $archetypeTaxClass;

							Product::create([
								'tenant_id' => $tenantId,
								'category_id' => $category->id,
								'sku' => $sku,
								'name' => $productName,
								'description' => $description,
								'brand' => $family['brand'] ?? null,
								'barcode' => $barcode,
								'price' => $price,
								'cost_price' => $costPrice,
								'tax_class' => $taxClass,
								'is_active' => true,
								'track_inventory' => !($variation['isMadeToOrder'] ?? $family['isMadeToOrder'] ?? false),
								'stock_quantity' => $stockQuantity,
								'reorder_level' => max(10, (int) round($stockQuantity * 0.25)),
								'max_stock_level' => max(40, $stockQuantity * 2),
								'weight' => $weight,
								'dimensions' => $variation['dimensions'] ?? $family['dimensions'] ?? null,
								'images' => $images,
								'variants' => $packOptions ? ['packs' => $packOptions] : null,
								'attributes' => $attributeBundle ?: null,
								'tags' => $tags ?: null,
							]);

							$productSequence++;
							$productCount++;
						}
					}
				}
			}
		}

		$this->command?->info(sprintf(
			'Product catalog seeding complete: %d categories and %d products for tenant %s.',
			$categoryCount,
			$productCount,
			$tenantId
		));
	}

	private function packPresets(): array
	{
		return [
			'bulk_rice' => [
				['label' => '1kg Pack', 'multiplier' => 1.0, 'weight' => 1.0, 'stock' => 120],
				['label' => '5kg Family Pack', 'multiplier' => 4.8, 'weight' => 5.0, 'stock' => 80],
				['label' => '10kg Bulk Pack', 'multiplier' => 9.4, 'weight' => 10.0, 'stock' => 40],
			],
			'standard_dry' => [
				['label' => '250g Pack', 'multiplier' => 0.26, 'weight' => 0.25, 'stock' => 140],
				['label' => '500g Pack', 'multiplier' => 0.5, 'weight' => 0.5, 'stock' => 100],
				['label' => '1kg Value Pack', 'multiplier' => 1.0, 'weight' => 1.0, 'stock' => 70],
			],
			'tea_pack' => [
				['label' => '50 Tea Bags Box', 'multiplier' => 0.5, 'weight' => 0.2, 'stock' => 130],
				['label' => '100 Tea Bags Box', 'multiplier' => 0.95, 'weight' => 0.4, 'stock' => 90],
				['label' => '200g Loose Leaf Tin', 'multiplier' => 1.1, 'weight' => 0.2, 'stock' => 80],
			],
			'herbal_pack' => [
				['label' => '50g Herbal Pack', 'multiplier' => 0.32, 'weight' => 0.05, 'stock' => 150],
				['label' => '100g Herbal Pack', 'multiplier' => 0.58, 'weight' => 0.1, 'stock' => 120],
				['label' => '200g Herbal Pack', 'multiplier' => 1.05, 'weight' => 0.2, 'stock' => 90],
			],
			'oil_bottle' => [
				['label' => '250ml Bottle', 'multiplier' => 0.3, 'weight' => 0.25, 'stock' => 120],
				['label' => '500ml Bottle', 'multiplier' => 0.55, 'weight' => 0.5, 'stock' => 90],
				['label' => '1L Bottle', 'multiplier' => 1.0, 'weight' => 1.0, 'stock' => 60],
			],
			'snack_pack' => [
				['label' => '150g Pack', 'multiplier' => 0.3, 'weight' => 0.15, 'stock' => 160],
				['label' => '300g Pack', 'multiplier' => 0.58, 'weight' => 0.3, 'stock' => 120],
				['label' => '600g Family Pack', 'multiplier' => 1.1, 'weight' => 0.6, 'stock' => 80],
			],
			'gift_box' => [
				['label' => 'Classic Gift Box', 'multiplier' => 1.0, 'weight' => 1.2, 'stock' => 45],
				['label' => 'Deluxe Gift Box', 'multiplier' => 1.45, 'weight' => 1.5, 'stock' => 35],
				['label' => 'Heritage Gift Box', 'multiplier' => 1.95, 'weight' => 1.8, 'stock' => 25],
			],
			'frozen_pack' => [
				['label' => '6 Piece Pack', 'multiplier' => 0.48, 'weight' => 0.35, 'stock' => 90],
				['label' => '12 Piece Pack', 'multiplier' => 0.9, 'weight' => 0.7, 'stock' => 70],
				['label' => '20 Piece Party Pack', 'multiplier' => 1.5, 'weight' => 1.1, 'stock' => 50],
			],
			'fresh_bundle' => [
				['label' => 'Small Bundle', 'multiplier' => 1.0, 'weight' => 0.5, 'stock' => 100],
				['label' => 'Medium Bundle', 'multiplier' => 1.6, 'weight' => 0.9, 'stock' => 70],
				['label' => 'Large Bundle', 'multiplier' => 2.2, 'weight' => 1.3, 'stock' => 55],
			],
			'craft_set' => [
				['label' => 'Single Piece', 'multiplier' => 1.0, 'weight' => 0.3, 'stock' => 80],
				['label' => 'Artisan Duo', 'multiplier' => 1.85, 'weight' => 0.6, 'stock' => 60],
				['label' => 'Gallery Collection', 'multiplier' => 2.6, 'weight' => 0.9, 'stock' => 40],
			],
		];
	}

	private function variantProfileSets(): array
	{
		return [
			'default' => [
				[
					'nameSuffix' => '',
					'skuSuffix' => 'CL',
					'label' => 'Classic Selection',
					'priceMultiplier' => 1.0,
					'stockMultiplier' => 1.0,
					'weightMultiplier' => 1.0,
					'extraTags' => ['classic'],
				],
			],
			'artisan' => [
				[
					'nameSuffix' => '',
					'skuSuffix' => 'AR',
					'label' => 'Artisan Batch',
					'priceMultiplier' => 1.08,
					'stockMultiplier' => 0.9,
					'weightMultiplier' => 1.0,
					'extraTags' => ['artisan'],
					'descriptionAddon' => 'Crafted in limited artisan batches.',
				],
			],
			'beverage' => [
				[
					'nameSuffix' => '',
					'skuSuffix' => 'BV',
					'label' => 'Estate Brew',
					'priceMultiplier' => 1.0,
					'stockMultiplier' => 1.1,
					'weightMultiplier' => 1.0,
					'extraTags' => ['beverage'],
					'descriptionAddon' => 'Estate curated brew profile.',
				],
			],
			'fresh' => [
				[
					'nameSuffix' => '',
					'skuSuffix' => 'FR',
					'label' => 'Market Fresh',
					'priceMultiplier' => 1.0,
					'stockMultiplier' => 1.2,
					'weightMultiplier' => 1.0,
					'extraTags' => ['fresh'],
					'descriptionAddon' => 'Packed at peak freshness.',
				],
			],
			'frozen' => [
				[
					'nameSuffix' => '',
					'skuSuffix' => 'FZ',
					'label' => 'Frozen Harvest',
					'priceMultiplier' => 1.0,
					'stockMultiplier' => 0.85,
					'weightMultiplier' => 1.0,
					'extraTags' => ['frozen'],
					'descriptionAddon' => 'Quick frozen to lock in flavour.',
				],
			],
			'craft' => [
				[
					'nameSuffix' => '',
					'skuSuffix' => 'CR',
					'label' => 'Craft Artisan',
					'priceMultiplier' => 1.0,
					'stockMultiplier' => 0.75,
					'weightMultiplier' => 1.0,
					'extraTags' => ['craft'],
					'descriptionAddon' => 'Hand crafted by master artisans.',
				],
			],
		];
	}

	private function categoryArchetypes(): array
	{
		return [
			[
				'packPreset' => 'standard_dry',
				'variantProfileKey' => 'default',
				'taxClass' => ['code' => 'SR-VAT0', 'rate' => 0.0],
				'baseMetadata' => [
					'icon' => 'local_fire_department',
					'displayColor' => '#b56a2d',
					'theme' => 'cinnamon',
				],
				'productFamilies' => [
					[
						'baseName' => 'Ceylon Cinnamon Quills',
						'skuPrefix' => 'CNQ',
						'brand' => 'CinnaHeritage',
						'basePrice' => 780.0,
						'baseWeight' => 0.25,
						'baseOrigin' => 'Matara',
						'description' => 'Grade Alba cinnamon quills rolled by coastal peelers.',
						'tags' => ['cinnamon', 'quills'],
						'attributes' => [
							'grade' => 'Alba',
							'harvestStyle' => 'hand peeled',
						],
						'costRatio' => 0.62,
						'variations' => [
							[
								'suffix' => 'Artisan Rolls',
								'priceMultiplier' => 1.0,
								'descriptionSuffix' => 'Hand rolled quills from village-curing barns.',
								'attributeOverrides' => ['batch' => 'artisan'],
								'extraTags' => ['artisan'],
							],
							[
								'suffix' => 'Golden Reserve',
								'priceMultiplier' => 1.16,
								'descriptionSuffix' => 'Limited reserve quills sun-kissed on cinnamon mats.',
								'attributeOverrides' => ['batch' => 'reserve'],
								'extraTags' => ['reserve', 'limited'],
							],
						],
					],
					[
						'baseName' => 'Cinnamon Powder Heritage',
						'skuPrefix' => 'CNP',
						'brand' => 'Mirissa Mills',
						'basePrice' => 620.0,
						'baseWeight' => 0.2,
						'baseOrigin' => 'Galle',
						'description' => 'Warm cinnamon powder stone-milled to capture full aroma.',
						'tags' => ['cinnamon', 'powder'],
						'attributes' => [
							'texture' => 'fine mill',
							'aroma' => 'warm spice',
						],
						'costRatio' => 0.58,
						'variations' => [
							[
								'suffix' => 'Breakfast Blend',
								'priceMultiplier' => 1.0,
								'descriptionSuffix' => 'Perfect for kiri bath, porridge, and dessert tables.',
								'attributeOverrides' => ['pairing' => 'breakfast'],
								'extraTags' => ['breakfast'],
							],
							[
								'suffix' => 'Firewood Roast',
								'priceMultiplier' => 1.12,
								'descriptionSuffix' => 'Slow-roasted over cinnamon wood embers for depth.',
								'attributeOverrides' => ['roastLevel' => 'smoked'],
								'extraTags' => ['smoked'],
							],
						],
					],
					[
						'baseName' => 'Cinnamon Honey Infusion',
						'skuPrefix' => 'CNH',
						'brand' => 'SpiceHive',
						'basePrice' => 780.0,
						'baseWeight' => 0.45,
						'baseOrigin' => 'Bentota',
						'description' => 'Wildflower honey infused with freshly shaved cinnamon.',
						'tags' => ['cinnamon', 'honey'],
						'attributes' => [
							'sweetener' => 'wildflower honey',
							'texture' => 'silky',
						],
						'costRatio' => 0.55,
						'variations' => [
							[
								'suffix' => 'Morning Pour',
								'priceMultiplier' => 1.0,
								'descriptionSuffix' => 'Brightened with lime zest for tropical breakfasts.',
								'attributeOverrides' => ['pairing' => 'hoppers'],
								'extraTags' => ['breakfast'],
							],
							[
								'suffix' => 'Twilight Drizzle',
								'priceMultiplier' => 1.14,
								'descriptionSuffix' => 'Laced with clove and kithul treacle for desserts.',
								'attributeOverrides' => ['pairing' => 'dessert'],
								'extraTags' => ['dessert'],
							],
						],
					],
				],
				'nameVariants' => [
					[
						'name' => 'Southern Cinnamon Guild',
						'region' => 'Matara & Galle coastal belt',
						'description' => 'Flagship cinnamon guild celebrating low-country master peelers.',
						'displayColor' => '#c8752f',
					],
					[
						'name' => 'Hill Country Cinnamon Collective',
						'region' => 'Kandy highlands',
						'description' => 'Highland cinnamon collective with brisk spice nuances.',
						'displayColor' => '#b96a2d',
					],
					[
						'name' => 'Kalutara Spice Estate',
						'region' => 'Kalutara river wetlands',
						'description' => 'River-nurtured cinnamon curated for gourmet kitchens.',
						'displayColor' => '#b86530',
					],
					[
						'name' => 'Cinnamon Heritage Bazaar',
						'region' => 'Colombo Old Bazaar',
						'description' => 'Bazaar-style cinnamon essentials for bustling city markets.',
						'displayColor' => '#b45f2a',
					],
					[
						'name' => 'Serendib Cinnamon Atelier',
						'region' => 'Southern artisan cooperatives',
						'description' => 'Boutique cinnamon atelier with heritage curing rituals.',
						'displayColor' => '#bb7034',
					],
				],
			],
			[
				'packPreset' => 'tea_pack',
				'variantProfileKey' => 'beverage',
				'taxClass' => ['code' => 'SR-VAT0', 'rate' => 0.0],
				'baseMetadata' => [
					'icon' => 'emoji_food_beverage',
					'displayColor' => '#4b7f7a',
					'theme' => 'tea',
				],
				'productFamilies' => [
					[
						'baseName' => 'Uva Sunrise Black Tea',
						'skuPrefix' => 'USB',
						'brand' => 'Uva Dawn',
						'basePrice' => 1180.0,
						'baseWeight' => 0.2,
						'baseOrigin' => 'Badulla',
						'description' => 'Brisk highland black tea delivering glowing copper liquor.',
						'tags' => ['tea', 'uva'],
						'attributes' => [
							'leafGrade' => 'BOPF',
							'harvestWindow' => 'July to August',
						],
						'costRatio' => 0.6,
						'variations' => [
							[
								'suffix' => 'Estate Brew',
								'priceMultiplier' => 1.0,
								'descriptionSuffix' => 'Estate-picked leaves for morning tea rituals.',
								'attributeOverrides' => ['brewNote' => 'bright and brisk'],
								'extraTags' => ['estate'],
							],
							[
								'suffix' => 'Monsoon Flush',
								'priceMultiplier' => 1.12,
								'descriptionSuffix' => 'Monsoon flush selection with honeyed finish.',
								'attributeOverrides' => ['brewNote' => 'honeyed'],
								'extraTags' => ['monsoon'],
							],
						],
					],
					[
						'baseName' => 'Nuwara Eliya Silver Tips',
						'skuPrefix' => 'NST',
						'brand' => 'MistVale',
						'basePrice' => 1680.0,
						'baseWeight' => 0.18,
						'baseOrigin' => 'Nuwara Eliya',
						'description' => 'Handpicked silver tips dried gently on cedar racks.',
						'tags' => ['tea', 'silver tips'],
						'attributes' => [
							'leafGrade' => 'Silver Tips',
							'harvestWindow' => 'Dawn picking',
						],
						'costRatio' => 0.64,
						'variations' => [
							[
								'suffix' => 'Silver Bloom',
								'priceMultiplier' => 1.0,
								'descriptionSuffix' => 'Fresh bloom buds with delicate floral sweetness.',
								'attributeOverrides' => ['brewNote' => 'floral'],
								'extraTags' => ['floral'],
							],
							[
								'suffix' => 'Frosted Dawn',
								'priceMultiplier' => 1.18,
								'descriptionSuffix' => 'Frost-kissed buds with vibrant citrus lift.',
								'attributeOverrides' => ['brewNote' => 'citrus'],
								'extraTags' => ['frost'],
							],
						],
					],
					[
						'baseName' => 'Kandy Heritage Brew',
						'skuPrefix' => 'KHB',
						'brand' => 'KandyLeaf',
						'basePrice' => 980.0,
						'baseWeight' => 0.22,
						'baseOrigin' => 'Kandy',
						'description' => 'Mid-country tea layered with cinnamon and clove nuance.',
						'tags' => ['tea', 'kandy'],
						'attributes' => [
							'leafGrade' => 'OP1',
							'harvestWindow' => 'Year round',
						],
						'costRatio' => 0.58,
						'variations' => [
							[
								'suffix' => 'Bo Tree Blend',
								'priceMultiplier' => 1.0,
								'descriptionSuffix' => 'Balanced brew inspired by temple offerings.',
								'attributeOverrides' => ['brewNote' => 'spiced'],
								'extraTags' => ['temple'],
							],
							[
								'suffix' => 'Temple Reserve',
								'priceMultiplier' => 1.14,
								'descriptionSuffix' => 'Aged in cedar chests for ceremony brews.',
								'attributeOverrides' => ['brewNote' => 'aged cedar'],
								'extraTags' => ['reserve'],
							],
						],
					],
				],
				'nameVariants' => [
					[
						'name' => 'Uva Highlands Tea House',
						'region' => 'Badulla & Ella ridges',
						'description' => 'Estate curated teas from the misty Uva slopes.',
						'displayColor' => '#43746f',
					],
					[
						'name' => 'Nuwara Mist Collective',
						'region' => 'Nuwara Eliya plateau',
						'description' => 'Fine cold-climate teas with alpine nuances.',
						'displayColor' => '#3f6f6a',
					],
					[
						'name' => 'Kandy Tea Pavilion',
						'region' => 'Kandy royal terraces',
						'description' => 'Heritage tea pavilion serving spiced mid-country brews.',
						'displayColor' => '#4a7b70',
					],
					[
						'name' => 'Sabaragamuwa Tea Atelier',
						'region' => 'Ratnapura river valleys',
						'description' => 'Full-bodied teas nurtured by gem country rains.',
						'displayColor' => '#4d7f76',
					],
					[
						'name' => 'Maskeliya Tea Verse',
						'region' => 'Maskeliya lakeside estates',
						'description' => 'Lake mist teas with crisp mineral finish.',
						'displayColor' => '#457672',
					],
				],
			],
			[
				'packPreset' => 'herbal_pack',
				'variantProfileKey' => 'artisan',
				'taxClass' => ['code' => 'SR-VAT0', 'rate' => 0.0],
				'baseMetadata' => [
					'icon' => 'spa',
					'displayColor' => '#5d8b4a',
					'theme' => 'herbal',
				],
				'productFamilies' => [
					[
						'baseName' => 'Katu Anoda Immune Tonic',
						'skuPrefix' => 'KAI',
						'brand' => 'AyurLeaf',
						'basePrice' => 880.0,
						'baseWeight' => 0.3,
						'baseOrigin' => 'Matale',
						'description' => 'Ayurvedic decoction brewed with katu anoda and island botanicals.',
						'tags' => ['herbal', 'tonic'],
						'attributes' => [
							'herb' => 'katu anoda',
							'preparation' => 'slow decoction',
						],
						'costRatio' => 0.54,
						'variations' => [
							[
								'suffix' => 'Daily Shield',
								'priceMultiplier' => 1.0,
								'descriptionSuffix' => 'Balanced tonic steeped with coriander and ginger.',
								'attributeOverrides' => ['dosha' => 'balanced'],
								'extraTags' => ['daily'],
							],
							[
								'suffix' => 'Night Restore',
								'priceMultiplier' => 1.12,
								'descriptionSuffix' => 'Soothing brew layered with ranawara and iramusu.',
								'attributeOverrides' => ['dosha' => 'kapha calming'],
								'extraTags' => ['night'],
							],
						],
					],
					[
						'baseName' => 'Heen Bovitiya Herbal Tea',
						'skuPrefix' => 'HBT',
						'brand' => 'AyurLeaf',
						'basePrice' => 640.0,
						'baseWeight' => 0.18,
						'baseOrigin' => 'Kurunegala',
						'description' => 'Sun-dried heen bovitiya leaves hand crushed for tea.',
						'tags' => ['herbal', 'tea'],
						'attributes' => [
							'herb' => 'heen bovitiya',
							'preparation' => 'sun-dried',
						],
						'costRatio' => 0.52,
						'variations' => [
							[
								'suffix' => 'Wildcrafted Cut',
								'priceMultiplier' => 1.0,
								'descriptionSuffix' => 'Wildcrafted leaves supporting liver wellness.',
								'attributeOverrides' => ['dosha' => 'pitta balancing'],
								'extraTags' => ['wildcrafted'],
							],
							[
								'suffix' => 'Sun-Dry Reserve',
								'priceMultiplier' => 1.15,
								'descriptionSuffix' => 'Reserve batches slow dried on woven mats.',
								'attributeOverrides' => ['dosha' => 'cooling'],
								'extraTags' => ['reserve'],
							],
						],
					],
					[
						'baseName' => 'Moringa Herbal Powder',
						'skuPrefix' => 'MHP',
						'brand' => 'GreenTemple',
						'basePrice' => 720.0,
						'baseWeight' => 0.25,
						'baseOrigin' => 'Anuradhapura',
						'description' => 'Stone ground moringa leaves rich in island minerals.',
						'tags' => ['herbal', 'moringa'],
						'attributes' => [
							'herb' => 'moringa',
							'preparation' => 'stone ground',
						],
						'costRatio' => 0.5,
						'variations' => [
							[
								'suffix' => 'Village Grind',
								'priceMultiplier' => 1.0,
								'descriptionSuffix' => 'Village grind ideal for herbal porridges.',
								'attributeOverrides' => ['dosha' => 'vata balancing'],
								'extraTags' => ['village'],
							],
							[
								'suffix' => 'Golden Leaf',
								'priceMultiplier' => 1.18,
								'descriptionSuffix' => 'Golden leaf selection blended with gotukola sprigs.',
								'attributeOverrides' => ['dosha' => 'tridosha harmony'],
								'extraTags' => ['premium'],
							],
						],
					],
				],
				'nameVariants' => [
					[
						'name' => 'Kandy Ayurvedic Dispensary',
						'region' => 'Kandy lakefront quarter',
						'description' => 'Herbal dispensary drawing on Kandyan healing recipes.',
						'displayColor' => '#5b8a47',
					],
					[
						'name' => 'Jaffna Siddha Collective',
						'region' => 'Jaffna peninsula',
						'description' => 'Siddha inspired tonics and herbs from northern practitioners.',
						'displayColor' => '#548443',
					],
					[
						'name' => 'Ruhunu Wellness Grove',
						'region' => 'Matara inland villages',
						'description' => 'Southern wellness grove blending Ayurvedic and village remedies.',
						'displayColor' => '#608f4c',
					],
					[
						'name' => 'Anuradhapura Healing Circle',
						'region' => 'Anuradhapura monastic zones',
						'description' => 'Monastic healing circle sharing ancient Ayurvedic tonics.',
						'displayColor' => '#578647',
					],
					[
						'name' => 'Colombo Heritage Ayurveda',
						'region' => 'Colombo heritage quarter',
						'description' => 'Urban heritage clinic curating trusted Ayurvedic staples.',
						'displayColor' => '#4f7d40',
					],
				],
			],
			[
				'packPreset' => 'oil_bottle',
				'variantProfileKey' => 'default',
				'taxClass' => ['code' => 'SR-VAT0', 'rate' => 0.0],
				'baseMetadata' => [
					'icon' => 'water_drop',
					'displayColor' => '#ad7b4a',
					'theme' => 'coconut',
				],
				'productFamilies' => [
					[
						'baseName' => 'Cold Pressed Coconut Oil',
						'skuPrefix' => 'CPO',
						'brand' => 'Kalpitiya Naturals',
						'basePrice' => 950.0,
						'baseWeight' => 1.0,
						'baseOrigin' => 'Puttalam',
						'description' => 'Virgin coconut oil extracted at low temperatures for purity.',
						'tags' => ['coconut', 'oil'],
						'attributes' => [
							'process' => 'cold pressed',
							'aroma' => 'fresh coconut',
						],
						'costRatio' => 0.56,
						'variations' => [
							[
								'suffix' => 'Sunrise Press',
								'priceMultiplier' => 1.0,
								'descriptionSuffix' => 'Pressed within four hours of harvest for a light finish.',
								'attributeOverrides' => ['pressing' => 'sunrise batch'],
								'extraTags' => ['light'],
							],
							[
								'suffix' => 'Moonlight Press',
								'priceMultiplier' => 1.12,
								'descriptionSuffix' => 'Night-cooled press retaining intense coconut aroma.',
								'attributeOverrides' => ['pressing' => 'night batch'],
								'extraTags' => ['aromatic'],
							],
						],
					],
					[
						'baseName' => 'Desiccated Coconut Flakes',
						'skuPrefix' => 'DCF',
						'brand' => 'CocoFlake',
						'basePrice' => 480.0,
						'baseWeight' => 0.35,
						'baseOrigin' => 'Chilaw',
						'description' => 'Fine cut coconut flakes dried for sweets and toppings.',
						'tags' => ['coconut', 'dessert'],
						'attributes' => [
							'cut' => 'fine',
							'drying' => 'low temperature',
						],
						'costRatio' => 0.52,
						'variations' => [
							[
								'suffix' => 'Baker Cut',
								'priceMultiplier' => 1.0,
								'descriptionSuffix' => 'Fine cut flakes ideal for bakery fillings.',
								'attributeOverrides' => ['bakeUse' => 'pastries'],
								'extraTags' => ['bakery'],
							],
							[
								'suffix' => 'Dessert Shred',
								'priceMultiplier' => 1.14,
								'descriptionSuffix' => 'Long shred flakes toasted for dessert toppings.',
								'attributeOverrides' => ['bakeUse' => 'desserts'],
								'extraTags' => ['dessert'],
							],
						],
					],
					[
						'baseName' => 'Coconut Flower Syrup',
						'skuPrefix' => 'CFS',
						'brand' => 'KithulPure',
						'basePrice' => 820.0,
						'baseWeight' => 0.6,
						'baseOrigin' => 'Kalutara',
						'description' => 'Golden coconut flower syrup tapped by traditional toddy tappers.',
						'tags' => ['coconut', 'syrup'],
						'attributes' => [
							'sweetener' => 'kithul sap',
							'texture' => 'pourable',
						],
						'costRatio' => 0.5,
						'variations' => [
							[
								'suffix' => 'Toddy Bloom',
								'priceMultiplier' => 1.0,
								'descriptionSuffix' => 'Fresh toddy bloom with caramel undertones.',
								'attributeOverrides' => ['flavor' => 'caramel'],
								'extraTags' => ['fresh'],
							],
							[
								'suffix' => 'Forest Amber',
								'priceMultiplier' => 1.16,
								'descriptionSuffix' => 'Forest amber syrup aged in clay vats for depth.',
								'attributeOverrides' => ['flavor' => 'deep amber'],
								'extraTags' => ['aged'],
							],
						],
					],
				],
				'nameVariants' => [
					[
						'name' => 'Kalpitiya Coconut Collective',
						'region' => 'Kalpitiya peninsula',
						'description' => 'Coastal collective harnessing abundant Kalpitiya coconuts.',
						'displayColor' => '#b8864e',
					],
					[
						'name' => 'Negombo Coconut Atelier',
						'region' => 'Negombo lagoon belt',
						'description' => 'Lagoon belt atelier crafting coconut pantry staples.',
						'displayColor' => '#b07a46',
					],
					[
						'name' => 'Chilaw Treacle Guild',
						'region' => 'Chilaw coastal towns',
						'description' => 'Treacle guild bottling rich coconut flower syrup traditions.',
						'displayColor' => '#ae7d4c',
					],
					[
						'name' => 'Batticaloa Coconut Craft',
						'region' => 'Batticaloa palm groves',
						'description' => 'Eastern coconut craft preserving palm tapping heritage.',
						'displayColor' => '#a97442',
					],
					[
						'name' => 'Southern Coconut Pantry',
						'region' => 'Hambantota coconut plains',
						'description' => 'Southern pantry featuring sun-dried coconut selections.',
						'displayColor' => '#b38252',
					],
				],
			],
			[
				'packPreset' => 'standard_dry',
				'variantProfileKey' => 'default',
				'taxClass' => ['code' => 'SR-VAT0', 'rate' => 0.0],
				'baseMetadata' => [
					'icon' => 'restaurant',
					'displayColor' => '#b8481a',
					'theme' => 'spice',
				],
				'productFamilies' => [
					[
						'baseName' => 'Roasted Curry Blend',
						'skuPrefix' => 'RCB',
						'brand' => 'SpiceRoute',
						'basePrice' => 480.0,
						'baseWeight' => 0.25,
						'baseOrigin' => 'Matale',
						'description' => 'Low country roasted curry blend with coriander and fennel.',
						'tags' => ['spice', 'curry'],
						'attributes' => [
							'heatLevel' => 'medium',
							'grind' => 'stone ground',
						],
						'costRatio' => 0.57,
						'variations' => [
							[
								'suffix' => 'Low Country Roast',
								'priceMultiplier' => 1.0,
								'descriptionSuffix' => 'Classic low country roast with toasted coconut.',
								'attributeOverrides' => ['style' => 'low country'],
								'extraTags' => ['low-country'],
							],
							[
								'suffix' => 'Kalu Pol Roast',
								'priceMultiplier' => 1.16,
								'descriptionSuffix' => 'Intense kalu pol profile with deep roasted spice.',
								'attributeOverrides' => ['style' => 'kalu pol'],
								'extraTags' => ['kalu-pol'],
							],
						],
					],
					[
						'baseName' => 'Colombo Masala Mix',
						'skuPrefix' => 'CMM',
						'brand' => 'SpiceRoute',
						'basePrice' => 520.0,
						'baseWeight' => 0.24,
						'baseOrigin' => 'Colombo',
						'description' => 'Urban masala blend balancing cinnamon, cloves, and pepper.',
						'tags' => ['spice', 'masala'],
						'attributes' => [
							'heatLevel' => 'medium',
							'aroma' => 'warm spice',
						],
						'costRatio' => 0.58,
						'variations' => [
							[
								'suffix' => 'Street Kitchen',
								'priceMultiplier' => 1.0,
								'descriptionSuffix' => 'Street food inspired masala with zesty lime leaf.',
								'attributeOverrides' => ['style' => 'street'],
								'extraTags' => ['street-food'],
							],
							[
								'suffix' => 'Crimson Feast',
								'priceMultiplier' => 1.14,
								'descriptionSuffix' => 'Deep crimson blend layered with roasted chilli.',
								'attributeOverrides' => ['style' => 'festival'],
								'extraTags' => ['festival'],
							],
						],
					],
					[
						'baseName' => 'Jaffna Devil Paste',
						'skuPrefix' => 'JDP',
						'brand' => 'FlameCraft',
						'basePrice' => 690.0,
						'baseWeight' => 0.28,
						'baseOrigin' => 'Jaffna',
						'description' => 'Fiery devil curry paste tempered with palmyra toddy.',
						'tags' => ['spice', 'jaffna'],
						'attributes' => [
							'heatLevel' => 'hot',
							'texture' => 'thick paste',
						],
						'costRatio' => 0.6,
						'variations' => [
							[
								'suffix' => 'Fire Lash',
								'priceMultiplier' => 1.0,
								'descriptionSuffix' => 'Classic devilled paste bright with red chilli.',
								'attributeOverrides' => ['style' => 'classic'],
								'extraTags' => ['fiery'],
							],
							[
								'suffix' => 'Palmyra Smoke',
								'priceMultiplier' => 1.18,
								'descriptionSuffix' => 'Smoky variant aged in palmyra husk barrels.',
								'attributeOverrides' => ['style' => 'smoked'],
								'extraTags' => ['smoked'],
							],
						],
					],
				],
				'nameVariants' => [
					[
						'name' => 'Colombo Spice Market',
						'region' => 'Pettah trade quarter',
						'description' => 'Spice market curation capturing Colombo street flavours.',
						'displayColor' => '#b94c1b',
					],
					[
						'name' => 'Jaffna Spice Vault',
						'region' => 'Jaffna bazaar lanes',
						'description' => 'Northern spice vault with bold chilli signatures.',
						'displayColor' => '#ad3f14',
					],
					[
						'name' => 'Matale Spice Pavilion',
						'region' => 'Matale spice gardens',
						'description' => 'Garden pavilion offering balanced roasting blends.',
						'displayColor' => '#b3471a',
					],
					[
						'name' => 'Negombo Curry Corner',
						'region' => 'Negombo fish market',
						'description' => 'Coastal curry corner where spice meets seafood.',
						'displayColor' => '#bb521f',
					],
					[
						'name' => 'Ruhunu Spice Atelier',
						'region' => 'Southern spice ateliers',
						'description' => 'Southern atelier bottling heirloom spice recipes.',
						'displayColor' => '#a83c12',
					],
				],
			],
			[
				'packPreset' => 'bulk_rice',
				'variantProfileKey' => 'default',
				'taxClass' => ['code' => 'SR-VAT0', 'rate' => 0.0],
				'baseMetadata' => [
					'icon' => 'rice_bowl',
					'displayColor' => '#d7a84c',
					'theme' => 'grain',
				],
				'productFamilies' => [
					[
						'baseName' => 'Kalu Heenati Rice',
						'skuPrefix' => 'KHR',
						'brand' => 'RathuFields',
						'basePrice' => 720.0,
						'baseWeight' => 1.0,
						'baseOrigin' => 'Hambantota',
						'description' => 'Ancient grain with earthy sweetness prized for festivals.',
						'tags' => ['rice', 'heritage'],
						'attributes' => [
							'grainType' => 'kalu heenati',
							'texture' => 'medium',
						],
						'costRatio' => 0.55,
						'variations' => [
							[
								'suffix' => 'Polished Grain',
								'priceMultiplier' => 1.0,
								'descriptionSuffix' => 'Lightly polished grains for daily island meals.',
								'attributeOverrides' => ['finish' => 'polished'],
								'extraTags' => ['daily'],
							],
							[
								'suffix' => 'Red Wholegrain',
								'priceMultiplier' => 1.14,
								'descriptionSuffix' => 'Wholegrain version retaining natural bran.',
								'attributeOverrides' => ['finish' => 'wholegrain'],
								'extraTags' => ['wholegrain'],
							],
						],
					],
					[
						'baseName' => 'Suwandel Fragrant Rice',
						'skuPrefix' => 'SFR',
						'brand' => 'LotusHarvest',
						'basePrice' => 840.0,
						'baseWeight' => 1.0,
						'baseOrigin' => 'Anuradhapura',
						'description' => 'Fragrant Suwandel rice used for temple offerings.',
						'tags' => ['rice', 'fragrant'],
						'attributes' => [
							'grainType' => 'suwandel',
							'aroma' => 'jasmine',
						],
						'costRatio' => 0.57,
						'variations' => [
							[
								'suffix' => 'Temple Harvest',
								'priceMultiplier' => 1.0,
								'descriptionSuffix' => 'Temple harvest ideal for milk rice traditions.',
								'attributeOverrides' => ['ritualUse' => 'kiribath'],
								'extraTags' => ['festival'],
							],
							[
								'suffix' => 'Festival Reserve',
								'priceMultiplier' => 1.18,
								'descriptionSuffix' => 'Reserve batch aged in clay bins for fragrance.',
								'attributeOverrides' => ['ritualUse' => 'festive'],
								'extraTags' => ['reserve'],
							],
						],
					],
					[
						'baseName' => 'Kurakkan Flour Blend',
						'skuPrefix' => 'KRF',
						'brand' => 'HillGrain',
						'basePrice' => 580.0,
						'baseWeight' => 0.9,
						'baseOrigin' => 'Monaragala',
						'description' => 'Nutty kurakkan flour stone ground for hearty meals.',
						'tags' => ['grain', 'kurakkan'],
						'attributes' => [
							'grainType' => 'kurakkan',
							'glutenFree' => true,
						],
						'costRatio' => 0.53,
						'variations' => [
							[
								'suffix' => 'Village Grind',
								'priceMultiplier' => 1.0,
								'descriptionSuffix' => 'Village grind for roti and porridge staples.',
								'attributeOverrides' => ['texture' => 'medium'],
								'extraTags' => ['village'],
							],
							[
								'suffix' => 'Stone Ground Reserve',
								'priceMultiplier' => 1.16,
								'descriptionSuffix' => 'Stone ground reserve with extra toasted notes.',
								'attributeOverrides' => ['texture' => 'coarse'],
								'extraTags' => ['toasted'],
							],
						],
					],
				],
				'nameVariants' => [
					[
						'name' => 'Anuradhapura Rice Guild',
						'region' => 'North central paddy fields',
						'description' => 'Rice guild sourcing heritage grains from ancient tanks.',
						'displayColor' => '#d9b055',
					],
					[
						'name' => 'Kurunegala Grain House',
						'region' => 'Kurunegala wewa belt',
						'description' => 'Grain house connecting wewa farmers to city markets.',
						'displayColor' => '#cca44e',
					],
					[
						'name' => 'Hambantota Grain Circle',
						'region' => 'Hambantota dry zone',
						'description' => 'Dry zone grain circle curating resilient rice varieties.',
						'displayColor' => '#d2aa52',
					],
					[
						'name' => 'Gampaha Paddy Collective',
						'region' => 'Gampaha low country fields',
						'description' => 'Collective championing low-country paddy revival.',
						'displayColor' => '#c89d49',
					],
					[
						'name' => 'Ampara Rice Atelier',
						'region' => 'Ampara eastern plains',
						'description' => 'Eastern atelier milling new harvest fragrant rice.',
						'displayColor' => '#dfb95b',
					],
				],
			],
			[
				'packPreset' => 'fresh_bundle',
				'variantProfileKey' => 'fresh',
				'taxClass' => ['code' => 'SR-VAT0', 'rate' => 0.0],
				'baseMetadata' => [
					'icon' => 'local_grocery_store',
					'displayColor' => '#d87f52',
					'theme' => 'produce',
				],
				'productFamilies' => [
					[
						'baseName' => 'Rambutan Orchard Selection',
						'skuPrefix' => 'ROS',
						'brand' => 'FruitCeylon',
						'basePrice' => 680.0,
						'baseWeight' => 0.9,
						'baseOrigin' => 'Gampaha',
						'description' => 'Handpicked rambutan clusters chilled for freshness.',
						'tags' => ['fruit', 'rambutan'],
						'attributes' => [
							'harvestStyle' => 'handpicked',
							'storage' => 'chilled',
						],
						'costRatio' => 0.48,
						'variations' => [
							[
								'suffix' => 'Sunburst Cluster',
								'priceMultiplier' => 1.0,
								'descriptionSuffix' => 'Sunburst clusters with honeyed sweetness.',
								'attributeOverrides' => ['flavor' => 'honey'],
								'extraTags' => ['sunburst'],
							],
							[
								'suffix' => 'Midnight Cluster',
								'priceMultiplier' => 1.12,
								'descriptionSuffix' => 'Night-harvested fruits with cool floral notes.',
								'attributeOverrides' => ['flavor' => 'floral'],
								'extraTags' => ['night-harvest'],
							],
						],
					],
					[
						'baseName' => 'Mango Sunrise Crate',
						'skuPrefix' => 'MSC',
						'brand' => 'FruitCeylon',
						'basePrice' => 840.0,
						'baseWeight' => 1.1,
						'baseOrigin' => 'Kurunegala',
						'description' => 'Curated Sri Lankan mango varieties packed at dawn.',
						'tags' => ['fruit', 'mango'],
						'attributes' => [
							'variety' => 'mixed premium',
							'ripeness' => 'tree ripened',
						],
						'costRatio' => 0.5,
						'variations' => [
							[
								'suffix' => 'Karuthakolomban Sweet',
								'priceMultiplier' => 1.0,
								'descriptionSuffix' => 'Signature Karuthakolomban sweetness with saffron glow.',
								'attributeOverrides' => ['dominantVariety' => 'Karuthakolomban'],
								'extraTags' => ['karuthakolomban'],
							],
							[
								'suffix' => 'Velleikolomban Zest',
								'priceMultiplier' => 1.1,
								'descriptionSuffix' => 'Zesty Velleikolomban mangoes with citrus finish.',
								'attributeOverrides' => ['dominantVariety' => 'Velleikolomban'],
								'extraTags' => ['velleikolomban'],
							],
						],
					],
					[
						'baseName' => 'Pineapple Golden Crown',
						'skuPrefix' => 'PGC',
						'brand' => 'IslandHarvest',
						'basePrice' => 760.0,
						'baseWeight' => 1.2,
						'baseOrigin' => 'Gampaha',
						'description' => 'Golden Mahaweli pineapples trimmed and ready to slice.',
						'tags' => ['fruit', 'pineapple'],
						'attributes' => [
							'variety' => 'Mauritius',
							'ripeness' => 'fully ripe',
						],
						'costRatio' => 0.49,
						'variations' => [
							[
								'suffix' => 'Cinnamon Splash',
								'priceMultiplier' => 1.0,
								'descriptionSuffix' => 'Cinnamon dust drizzle pack for tropical salads.',
								'attributeOverrides' => ['pairing' => 'cinnamon'],
								'extraTags' => ['cinnamon'],
							],
							[
								'suffix' => 'Lime Glaze',
								'priceMultiplier' => 1.14,
								'descriptionSuffix' => 'Lime glaze sachet for tangy island desserts.',
								'attributeOverrides' => ['pairing' => 'lime'],
								'extraTags' => ['lime'],
							],
						],
					],
				],
				'nameVariants' => [
					[
						'name' => 'Gampaha Fruit Boutique',
						'region' => 'Gampaha orchard belt',
						'description' => 'Boutique showcasing Gampaha backyard fruit harvests.',
						'displayColor' => '#e08957',
					],
					[
						'name' => 'Matara Fruit Gardens',
						'region' => 'Matara coastal orchards',
						'description' => 'Garden-fresh southern fruits rushed to store shelves.',
						'displayColor' => '#d87f4f',
					],
					[
						'name' => 'Jaffna Peninsula Fruits',
						'region' => 'Jaffna peninsula farms',
						'description' => 'Northern peninsula fruits with palmyra-sweet accents.',
						'displayColor' => '#e1925d',
					],
					[
						'name' => 'Kandy Orchard Pavilion',
						'region' => 'Kandy hill gardens',
						'description' => 'Hill country pavilion curating temperate fruit varietals.',
						'displayColor' => '#d37a4c',
					],
					[
						'name' => 'Polonnaruwa Fruit Hub',
						'region' => 'Polonnaruwa reservoirs',
						'description' => 'Dry-zone fruit hub pooling Mahaweli irrigation harvests.',
						'displayColor' => '#dd8653',
					],
				],
			],
			[
				'packPreset' => 'frozen_pack',
				'variantProfileKey' => 'frozen',
				'taxClass' => ['code' => 'SR-VAT8', 'rate' => 0.08],
				'baseMetadata' => [
					'icon' => 'set_meal',
					'displayColor' => '#3b7ba3',
					'theme' => 'seafood',
				],
				'productFamilies' => [
					[
						'baseName' => 'Negombo Lagoon Prawns',
						'skuPrefix' => 'NLP',
						'brand' => 'LagoonCatch',
						'basePrice' => 1680.0,
						'baseWeight' => 0.9,
						'baseOrigin' => 'Negombo',
						'description' => 'Lagoon prawns cleaned, deveined, and quick frozen.',
						'tags' => ['seafood', 'prawn'],
						'attributes' => [
							'catchMethod' => 'lagoon net',
							'processing' => 'quick frozen',
						],
						'costRatio' => 0.6,
						'variations' => [
							[
								'suffix' => 'Tiger Jumbo',
								'priceMultiplier' => 1.0,
								'descriptionSuffix' => 'Jumbo tiger prawns perfect for sizzling grills.',
								'attributeOverrides' => ['size' => 'tiger jumbo'],
								'extraTags' => ['jumbo'],
							],
							[
								'suffix' => 'Spiced Grill',
								'priceMultiplier' => 1.16,
								'descriptionSuffix' => 'Pre-marinated with chilli, lime, and garlic.',
								'attributeOverrides' => ['marinade' => 'chilli lime'],
								'extraTags' => ['marinated'],
							],
						],
					],
					[
						'baseName' => 'Southern Crab Curry Pack',
						'skuPrefix' => 'SCP',
						'brand' => 'SeaSpice',
						'basePrice' => 1820.0,
						'baseWeight' => 1.1,
						'baseOrigin' => 'Matara',
						'description' => 'Mud crabs cleaned with roasted curry paste sachets.',
						'tags' => ['seafood', 'crab'],
						'attributes' => [
							'catchMethod' => 'pot caught',
							'serving' => 'ready to cook',
						],
						'costRatio' => 0.62,
						'variations' => [
							[
								'suffix' => 'Clay Pot Ready',
								'priceMultiplier' => 1.0,
								'descriptionSuffix' => 'Includes spice pack tuned for clay pot curries.',
								'attributeOverrides' => ['serving' => 'clay pot'],
								'extraTags' => ['clay-pot'],
							],
							[
								'suffix' => 'Festival Feast',
								'priceMultiplier' => 1.18,
								'descriptionSuffix' => 'Festival feast pack with extra roasted curry and coconut milk.',
								'attributeOverrides' => ['serving' => 'festival'],
								'extraTags' => ['festival'],
							],
						],
					],
					[
						'baseName' => 'Jaffna Dry Fish Mix',
						'skuPrefix' => 'JDF',
						'brand' => 'JaffnaTide',
						'basePrice' => 940.0,
						'baseWeight' => 0.6,
						'baseOrigin' => 'Jaffna',
						'description' => 'Assorted dry fish slow cured in northern sea breeze.',
						'tags' => ['seafood', 'dry fish'],
						'attributes' => [
							'process' => 'sun dried',
							'saltLevel' => 'medium',
						],
						'costRatio' => 0.58,
						'variations' => [
							[
								'suffix' => 'Chilli Rub',
								'priceMultiplier' => 1.0,
								'descriptionSuffix' => 'Chilli-rubbed mix ready for stir-fries.',
								'attributeOverrides' => ['spice' => 'chilli'],
								'extraTags' => ['spicy'],
							],
							[
								'suffix' => 'Tamarind Cure',
								'priceMultiplier' => 1.14,
								'descriptionSuffix' => 'Tamarind cured selection mellowed for curries.',
								'attributeOverrides' => ['spice' => 'tamarind'],
								'extraTags' => ['tamarind'],
							],
						],
					],
				],
				'nameVariants' => [
					[
						'name' => 'Negombo Seafood Pavilion',
						'region' => 'Negombo lagoon',
						'description' => 'Seafood pavilion showcasing lagoon and coastal catches.',
						'displayColor' => '#3c7ea7',
					],
					[
						'name' => 'Trinco Ocean Guild',
						'region' => 'Trincomalee bay',
						'description' => 'Ocean guild curating blue waters bounty.',
						'displayColor' => '#34729b',
					],
					[
						'name' => 'Matara Coastline Catches',
						'region' => 'Matara shoreline',
						'description' => 'Coastline catches prepared for fiery southern curries.',
						'displayColor' => '#3779a3',
					],
					[
						'name' => 'Mannar Pearl Fisheries',
						'region' => 'Mannar gulf',
						'description' => 'Fisheries cooperative from pearl-rich gulf waters.',
						'displayColor' => '#2f6a91',
					],
					[
						'name' => 'Batticaloa Lagoon Harvest',
						'region' => 'Batticaloa lagoons',
						'description' => 'Lagoon harvest featuring eastern seafood craft.',
						'displayColor' => '#3a7aa6',
					],
				],
			],
			[
				'packPreset' => 'gift_box',
				'variantProfileKey' => 'default',
				'taxClass' => ['code' => 'SR-VAT0', 'rate' => 0.0],
				'baseMetadata' => [
					'icon' => 'cake',
					'displayColor' => '#d56a90',
					'theme' => 'sweets',
				],
				'productFamilies' => [
					[
						'baseName' => 'Kalu Dodol Squares',
						'skuPrefix' => 'KDS',
						'brand' => 'SweetRuhunu',
						'basePrice' => 680.0,
						'baseWeight' => 0.4,
						'baseOrigin' => 'Kalutara',
						'description' => 'Slow-cooked kalu dodol with coconut milk and kithul treacle.',
						'tags' => ['sweet', 'dodol'],
						'attributes' => [
							'texture' => 'chewy',
							'sweetener' => 'kithul',
						],
						'costRatio' => 0.52,
						'variations' => [
							[
								'suffix' => 'Jaggery Rich',
								'priceMultiplier' => 1.0,
								'descriptionSuffix' => 'Jaggery-rich version for classic festive platters.',
								'attributeOverrides' => ['garnish' => 'cashew shards'],
								'extraTags' => ['festive'],
							],
							[
								'suffix' => 'Cashew Crown',
								'priceMultiplier' => 1.16,
								'descriptionSuffix' => 'Topped with roasted cashew crowns and sesame.',
								'attributeOverrides' => ['garnish' => 'cashew crown'],
								'extraTags' => ['premium'],
							],
						],
					],
					[
						'baseName' => 'Coconut Milk Toffee',
						'skuPrefix' => 'CMT',
						'brand' => 'SweetRuhunu',
						'basePrice' => 540.0,
						'baseWeight' => 0.35,
						'baseOrigin' => 'Kandy',
						'description' => 'Soft coconut milk toffee whipped with condensed milk.',
						'tags' => ['sweet', 'toffee'],
						'attributes' => [
							'texture' => 'soft',
							'sweetener' => 'condensed milk',
						],
						'costRatio' => 0.5,
						'variations' => [
							[
								'suffix' => 'Cardamom Glow',
								'priceMultiplier' => 1.0,
								'descriptionSuffix' => 'Cardamom infused melt-in-mouth squares.',
								'attributeOverrides' => ['spice' => 'cardamom'],
								'extraTags' => ['cardamom'],
							],
							[
								'suffix' => 'Spicy Ginger',
								'priceMultiplier' => 1.12,
								'descriptionSuffix' => 'Ginger spiced toffee with sweet heat finish.',
								'attributeOverrides' => ['spice' => 'ginger'],
								'extraTags' => ['ginger'],
							],
						],
					],
					[
						'baseName' => 'Bibikkan Heritage Loaf',
						'skuPrefix' => 'BHL',
						'brand' => 'IslandBake',
						'basePrice' => 760.0,
						'baseWeight' => 0.5,
						'baseOrigin' => 'Colombo',
						'description' => 'Moist bibikkan with grated coconut, semolina, and molasses.',
						'tags' => ['sweet', 'bibikkan'],
						'attributes' => [
							'texture' => 'moist',
							'sweetener' => 'coconut treacle',
						],
						'costRatio' => 0.54,
						'variations' => [
							[
								'suffix' => 'Toddy Ferment',
								'priceMultiplier' => 1.0,
								'descriptionSuffix' => 'Fermented with palmyra toddy for gentle tang.',
								'attributeOverrides' => ['ferment' => 'palmyra toddy'],
								'extraTags' => ['toddy'],
							],
							[
								'suffix' => 'Festival Spice',
								'priceMultiplier' => 1.15,
								'descriptionSuffix' => 'Festival version with cloves, nutmeg, and raisin jewels.',
								'attributeOverrides' => ['ferment' => 'spiced'],
								'extraTags' => ['festival'],
							],
						],
					],
				],
				'nameVariants' => [
					[
						'name' => 'Colombo Sweet Bazaar',
						'region' => 'Pettah sweet street',
						'description' => 'Bazaar stall presenting beloved Colombo confections.',
						'displayColor' => '#d7658c',
					],
					[
						'name' => 'Kandy Dessert Parlour',
						'region' => 'Kandy city center',
						'description' => 'Dessert parlour blending Kandyan sweets and tea-time treats.',
						'displayColor' => '#ce5c84',
					],
					[
						'name' => 'Jaffna Sweets Corner',
						'region' => 'Jaffna market',
						'description' => 'Northern sweets corner featuring palmyra inspired delicacies.',
						'displayColor' => '#db7094',
					],
					[
						'name' => 'Down South Sweet Cart',
						'region' => 'Matara beachfront',
						'description' => 'Coastal sweet cart delivering toddy treacle delights.',
						'displayColor' => '#d45f86',
					],
					[
						'name' => 'Trincomalee Dessert Atelier',
						'region' => 'Trincomalee promenade',
						'description' => 'Dessert atelier crafting eastern harbour indulgences.',
						'displayColor' => '#cf6289',
					],
				],
			],
			[
				'packPreset' => 'craft_set',
				'variantProfileKey' => 'craft',
				'taxClass' => ['code' => 'SR-VAT8', 'rate' => 0.08],
				'baseMetadata' => [
					'icon' => 'handyman',
					'displayColor' => '#a66a3b',
					'theme' => 'craft',
				],
				'productFamilies' => [
					[
						'baseName' => 'Ambalangoda Mask Carving',
						'skuPrefix' => 'AMC',
						'brand' => 'MaskLore',
						'basePrice' => 6800.0,
						'baseWeight' => 0.7,
						'baseOrigin' => 'Ambalangoda',
						'description' => 'Hand-carved masks painted with natural pigments.',
						'tags' => ['craft', 'mask'],
						'attributes' => [
							'material' => 'kaduru wood',
							'craft' => 'hand carved',
						],
						'costRatio' => 0.64,
						'variations' => [
							[
								'suffix' => 'Kolam Spirit',
								'priceMultiplier' => 1.0,
								'descriptionSuffix' => 'Kolam dance spirit mask with playful hues.',
								'attributeOverrides' => ['motif' => 'kolam'],
								'extraTags' => ['kolam'],
							],
							[
								'suffix' => 'Raksha Guardian',
								'priceMultiplier' => 1.2,
								'descriptionSuffix' => 'Raksha guardian mask protecting households.',
								'attributeOverrides' => ['motif' => 'raksha'],
								'extraTags' => ['raksha'],
							],
						],
					],
					[
						'baseName' => 'Handloom Sarong Weave',
						'skuPrefix' => 'HSW',
						'brand' => 'LoomLine',
						'basePrice' => 5200.0,
						'baseWeight' => 0.5,
						'baseOrigin' => 'Kurunegala',
						'description' => 'Handloom sarongs dyed with plant-based pigments.',
						'tags' => ['craft', 'handloom'],
						'attributes' => [
							'material' => 'cotton',
							'loom' => 'pit loom',
						],
						'costRatio' => 0.6,
						'variations' => [
							[
								'suffix' => 'Sunset Weft',
								'priceMultiplier' => 1.0,
								'descriptionSuffix' => 'Sunset gradient woven with natural madder.',
								'attributeOverrides' => ['palette' => 'sunset'],
								'extraTags' => ['sunset'],
							],
							[
								'suffix' => 'Lagoon Weft',
								'priceMultiplier' => 1.18,
								'descriptionSuffix' => 'Lagoon inspired weave with indigo and teal bands.',
								'attributeOverrides' => ['palette' => 'lagoon'],
								'extraTags' => ['lagoon'],
							],
						],
					],
					[
						'baseName' => 'Brass Temple Lamp',
						'skuPrefix' => 'BTL',
						'brand' => 'HeritageForge',
						'basePrice' => 5800.0,
						'baseWeight' => 1.2,
						'baseOrigin' => 'Kandy',
						'description' => 'Polished brass oil lamps cast using lost-wax tradition.',
						'tags' => ['craft', 'brass'],
						'attributes' => [
							'material' => 'brass',
							'craft' => 'lost wax cast',
						],
						'costRatio' => 0.62,
						'variations' => [
							[
								'suffix' => 'Lotus Tier',
								'priceMultiplier' => 1.0,
								'descriptionSuffix' => 'Lotus tier lamp with serene contours.',
								'attributeOverrides' => ['motif' => 'lotus'],
								'extraTags' => ['lotus'],
							],
							[
								'suffix' => 'Peacock Crest',
								'priceMultiplier' => 1.22,
								'descriptionSuffix' => 'Peacock crest lamp celebrating temple art.',
								'attributeOverrides' => ['motif' => 'peacock'],
								'extraTags' => ['peacock'],
							],
						],
					],
				],
				'nameVariants' => [
					[
						'name' => 'Ambalangoda Craft House',
						'region' => 'Ambalangoda artisan lane',
						'description' => 'Craft house preserving southern mask artistry.',
						'displayColor' => '#a46638',
					],
					[
						'name' => 'Kandy Artisan Quarter',
						'region' => 'Kandy heritage streets',
						'description' => 'Artisan quarter blending Kandyan craft lineages.',
						'displayColor' => '#9c6033',
					],
					[
						'name' => 'Kurunegala Craft Boutique',
						'region' => 'Kurunegala weaving towns',
						'description' => 'Boutique celebrating handloom and brass craftsmanship.',
						'displayColor' => '#a4693a',
					],
					[
						'name' => 'Galle Heritage Workshop',
						'region' => 'Galle fort',
						'description' => 'Workshop within Galle fort curating colonial-era crafts.',
						'displayColor' => '#a06331',
					],
					[
						'name' => 'Jaffna Craft Collective',
						'region' => 'Jaffna cultural square',
						'description' => 'Collective uplifting northern artisans and woodcraft.',
						'displayColor' => '#975b2e',
					],
				],
			],
		];
	}
}
