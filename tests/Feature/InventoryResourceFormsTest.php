<?php

namespace Tests\Feature;

use App\Filament\Resources\CategoryResource;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\WarehouseResource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryResourceFormsTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_resources_expose_their_create_form_schemas(): void
    {
        $warehouseForm = WarehouseResource::form(Schema::make());
        $productForm = ProductResource::form(Schema::make());
        $categoryForm = CategoryResource::form(Schema::make());

        $this->assertCount(4, $warehouseForm->getComponents());
        $this->assertCount(2, $productForm->getComponents());
        $this->assertContainsOnlyInstancesOf(Section::class, $productForm->getComponents());
        $this->assertCount(1, $categoryForm->getComponents());
    }
}
