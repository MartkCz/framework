<?php

declare(strict_types=1);

namespace Spiral\Tests\Filters\Dto\Attribute\Input;

use Spiral\Filters\Attribute\Input\RemoteAddress;

final class RemoteAddressTest extends \Spiral\Tests\Filters\Dto\AttributeTest
{
    public function testGetsValue(): void
    {
        $attribute = new RemoteAddress();

        $this->input
            ->shouldReceive('getValue')
            ->once()
            ->with('remoteAddress')
            ->andReturn('bar');

        $this->assertSame(
            'bar',
            $attribute->getValue($this->input, $this->makeProperty())
        );
    }

    public function testGetsSchema(): void
    {
        $attribute = new RemoteAddress();

        $this->assertSame(
            'remoteAddress',
            $attribute->getSchema($this->makeProperty())
        );
    }
}
