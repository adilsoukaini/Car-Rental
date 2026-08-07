<?php

namespace Tests\Feature\VehicleAttributes;

use Plugins\VehicleAttributes\Models\VehicleAttributeDefinition;
use Plugins\VehicleAttributes\Services\AttributeValueCaster;
use Tests\TestCase;

class AttributeValueCasterTest extends TestCase
{
    private function definition(string $type, array $options = []): VehicleAttributeDefinition
    {
        return new VehicleAttributeDefinition([
            'name' => 'Test',
            'key' => 'test',
            'type' => $type,
            'options' => $options,
        ]);
    }

    public function test_number_casts_to_float_and_drops_non_numeric(): void
    {
        $this->assertSame(250, AttributeValueCaster::cast($this->definition('number'), '250'));
        $this->assertSame(12.5, AttributeValueCaster::cast($this->definition('number'), '12.5'));
        $this->assertNull(AttributeValueCaster::cast($this->definition('number'), 'abc'));
    }

    public function test_boolean_parses_truthy_and_falsy_stored_values(): void
    {
        $def = $this->definition('boolean');

        $this->assertTrue(AttributeValueCaster::cast($def, '1'));
        $this->assertFalse(AttributeValueCaster::cast($def, '0'));
        $this->assertTrue(AttributeValueCaster::cast($def, 'true'));
    }

    public function test_text_and_textarea_return_the_raw_string(): void
    {
        $this->assertSame('full coverage', AttributeValueCaster::cast($this->definition('text'), 'full coverage'));
        $this->assertSame("line 1\nline 2", AttributeValueCaster::cast($this->definition('textarea'), "line 1\nline 2"));
    }

    public function test_select_resolves_the_display_label_from_an_associative_map(): void
    {
        $def = $this->definition('select', ['full' => 'Full Coverage', 'basic' => 'Basic']);

        $this->assertSame('Full Coverage', AttributeValueCaster::cast($def, 'full'));
        $this->assertSame('Basic', AttributeValueCaster::cast($def, 'basic'));
    }

    public function test_select_returns_the_raw_value_when_not_in_the_map_or_a_plain_list(): void
    {
        $assoc = $this->definition('select', ['full' => 'Full Coverage']);
        $this->assertSame('unknown-key', AttributeValueCaster::cast($assoc, 'unknown-key'));

        $list = $this->definition('select', ['full', 'basic']);
        $this->assertSame('basic', AttributeValueCaster::cast($list, 'basic'));
    }

    public function test_blank_values_cast_to_null(): void
    {
        $this->assertNull(AttributeValueCaster::cast($this->definition('text'), null));
        $this->assertNull(AttributeValueCaster::cast($this->definition('text'), ''));
    }
}
