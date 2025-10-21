<?php

namespace YourVendor\ActivityFeed\Tests\Unit;

use YourVendor\ActivityFeed\Renderers\DescriptionRenderer;
use YourVendor\ActivityFeed\Tests\TestCase;

class DescriptionRendererTest extends TestCase
{
    /** @test */
    public function it_can_format_boolean_values()
    {
        $renderer = new DescriptionRenderer();

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($renderer);
        $method = $reflection->getMethod('formatValue');
        $method->setAccessible(true);

        $this->assertEquals('true', $method->invoke($renderer, true));
        $this->assertEquals('false', $method->invoke($renderer, false));
    }

    /** @test */
    public function it_can_format_null_values()
    {
        $renderer = new DescriptionRenderer();

        $reflection = new \ReflectionClass($renderer);
        $method = $reflection->getMethod('formatValue');
        $method->setAccessible(true);

        $this->assertEquals('null', $method->invoke($renderer, null));
    }

    /** @test */
    public function it_can_format_array_values()
    {
        $renderer = new DescriptionRenderer();

        $reflection = new \ReflectionClass($renderer);
        $method = $reflection->getMethod('formatValue');
        $method->setAccessible(true);

        $array = ['key' => 'value'];
        $this->assertEquals(json_encode($array), $method->invoke($renderer, $array));
    }
}
