<?php

declare(strict_types=1);

namespace Framework\Filter;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Spiral\Filters\InputInterface;
use Spiral\Framework\Spiral;
use Spiral\Http\Config\HttpConfig;
use Spiral\Http\Request\InputBag;
use Spiral\Testing\Attribute\TestScope;
use Spiral\Tests\Framework\BaseTestCase;

#[TestScope(Spiral::HttpRequest)]
final class InputScopeTest extends BaseTestCase
{
    private ServerRequestInterface $request;

    protected function setUp(): void
    {
        parent::setUp();

        $request = new ServerRequest(
            method: 'POST',
            uri: 'https://site.com/users',
            headers: [
                'Authorization' => 'Bearer 123',
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ],
            body: 'name=John+Doe',
            version: '1.1',
            serverParams: [
                'REMOTE_ADDR' => '123.123.123',
            ]
        );

        $this->getContainer()->bind(
            ServerRequestInterface::class,
            $this->request = $request
                ->withQueryParams(['foo' => 'bar'])
                ->withCookieParams(['baz' => 'qux'])
                ->withParsedBody(['quux' => 'corge'])
                ->withAttribute('foz', 'baf'),
        );
    }

    public function testGetsMethod(): void
    {
        $this->assertSame('POST', $this->getContainer()->get(InputInterface::class)->getValue('method'));
    }

    public function testGetsPath(): void
    {
        $this->assertSame('/users', $this->getContainer()->get(InputInterface::class)->getValue('path'));
    }

    public function testGetsUri(): void
    {
        $uri = $this->getContainer()->get(InputInterface::class)->getValue('uri');
        $this->assertInstanceOf(UriInterface::class, $uri);

        $this->assertSame('https://site.com/users', (string)$uri);
    }

    public function testGetsRequest(): void
    {
        $this->assertSame($this->request, $this->getContainer()->get(InputInterface::class)->getValue('request'));
    }

    public function testGetsBearerToken(): void
    {
        $this->assertSame('123', $this->getContainer()->get(InputInterface::class)->getValue('bearerToken'));
    }

    public function testIsSecure(): void
    {
        $this->assertTrue($this->getContainer()->get(InputInterface::class)->getValue('isSecure'));
    }

    public function testIsAjax(): void
    {
        $this->assertTrue($this->getContainer()->get(InputInterface::class)->getValue('isAjax'));
    }

    public function testIsXmlHttpRequest(): void
    {
        $this->assertTrue($this->getContainer()->get(InputInterface::class)->getValue('isXmlHttpRequest'));
    }

    public function testIsJsonExpected(): void
    {
        $this->assertTrue($this->getContainer()->get(InputInterface::class)->getValue('isJsonExpected', true));
    }

    public function testGetsRemoteAddress(): void
    {
        $this->assertSame('123.123.123', $this->getContainer()->get(InputInterface::class)->getValue('remoteAddress'));
    }

    #[DataProvider('InputBagsDataProvider')]
    public function testGetsInputBag(string $source, string $name, mixed $expected): void
    {
        $this->assertSame($expected, $this->getContainer()->get(InputInterface::class)->getValue($source, $name));
    }

    public function testGetValueFromCustomInputBag(): void
    {
        $this->getContainer()
            ->bind(
                HttpConfig::class,
                new HttpConfig(['inputBags' => ['test' => ['class'  => InputBag::class, 'source' => 'getParsedBody']]])
            );

        $this->assertSame(
            'corge',
            $this->getContainer()->get(InputInterface::class)->getValue('test', 'quux')
        );
    }

    public static function InputBagsDataProvider(): \Traversable
    {
        yield 'headers' => ['headers', 'Authorization', 'Bearer 123'];
        yield 'data' => ['data', 'quux', 'corge'];
        yield 'query' => ['query', 'foo', 'bar'];
        yield 'cookies' => ['cookies', 'baz', 'qux'];
        yield 'server' => ['server', 'REMOTE_ADDR', '123.123.123'];
        yield 'attributes' => ['attributes', 'foz', 'baf'];
    }
}
