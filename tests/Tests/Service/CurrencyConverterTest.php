<?php

declare(strict_types=1);

/*
 * This file is part of Exchanger.
 *
 * (c) Florian Voutzinos <florian@voutzinos.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Exchanger\Tests\Service;

use Exchanger\CurrencyPair;
use Exchanger\Exception\Exception;
use Exchanger\ExchangeRateQuery;
use Exchanger\HistoricalExchangeRateQuery;
use Exchanger\Service\CurrencyConverter;
use Http\Client\HttpClient;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\ResponseInterface;

class CurrencyConverterTest extends TestCase
{
    /**
     * @test
     */
    public function it_throws_an_exception_if_access_key_option_missing_in_enterprise_mode()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "access_key" option must be provided.');
        new CurrencyConverter($this->createMock(HttpClient::class), null, ['enterprise' => true]);
    }

    /**
     * @test
     */
    public function it_throws_an_exception_with_error_response()
    {
        $this->expectException(Exception::class);
        $uri = 'https://free.currencyconverterapi.com/api/v6/convert?q=XXX_YYY&date=2000-01-01';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/CurrencyConverter/error.json');

        $service = new CurrencyConverter($this->getHttpAdapterMock($uri, $content, 200), null, ['access_key' => 'secret']);
        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('XXX/YYY')));
    }

    /** @test */
    public function it_fetches_a_rate_normal_mode()
    {
        $pair = CurrencyPair::createFromString('USD/EUR');
        $uri = 'https://free.currencyconverterapi.com/api/v6/convert?q=USD_EUR';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/CurrencyConverter/success.json');

        $service = new CurrencyConverter($this->getHttpAdapterMock($uri, $content, 200), null, ['access_key' => 'secret']);
        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertSame(0.726804, $rate->getValue());
        $this->assertEquals('currency_converter', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /** @test */
    public function it_fetches_a_rate_enterprise_mode()
    {
        $pair = CurrencyPair::createFromString('USD/EUR');
        $uri = 'https://api.currencyconverterapi.com/api/v6/convert?q=USD_EUR';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/CurrencyConverter/success.json');

        $service = new CurrencyConverter($this->getHttpAdapterMock($uri, $content, 200), null, ['access_key' => 'secret', 'enterprise' => true]);
        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertSame(0.726804, $rate->getValue());
        $this->assertEquals('currency_converter', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /** @test */
    public function it_fetches_a_historical_rate_normal_mode()
    {
        $pair = CurrencyPair::createFromString('USD/EUR');
        $uri = 'https://free.currencyconverterapi.com/api/v6/convert?q=USD_EUR&date=2017-01-01';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/CurrencyConverter/historical_success.json');
        $date = new \DateTime('2017-01-01 UTC');

        $service = new CurrencyConverter($this->getHttpAdapterMock($uri, $content, 200), null, ['access_key' => 'secret']);
        $rate = $service->getExchangeRate(new HistoricalExchangeRateQuery($pair, $date));

        $this->assertSame(0.726804, $rate->getValue());
        $this->assertEquals($date, $rate->getDate());
        $this->assertEquals('currency_converter', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /** @test */
    public function it_fetches_a_historical_rate_enterprise_mode()
    {
        $pair = CurrencyPair::createFromString('USD/EUR');
        $uri = 'https://api.currencyconverterapi.com/api/v6/convert?q=USD_EUR&date=2017-01-01';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/CurrencyConverter/historical_success.json');
        $date = new \DateTime('2017-01-01 UTC');

        $service = new CurrencyConverter($this->getHttpAdapterMock($uri, $content, 200), null, ['access_key' => 'secret', 'enterprise' => true]);
        $rate = $service->getExchangeRate(new HistoricalExchangeRateQuery($pair, $date));

        $this->assertSame(0.726804, $rate->getValue());
        $this->assertEquals($date, $rate->getDate());
        $this->assertEquals('currency_converter', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_has_a_name()
    {
        $service = new CurrencyConverter($this->createMock('Http\Client\HttpClient'), null, ['access_key' => 'secret']);

        $this->assertSame('currency_converter', $service->getName());
    }

    /**
     * Create a mocked Http adapter.
     *
     * @param string $url        The url
     * @param string $content    The body content
     * @param int    $statusCode HTTP status code
     *
     * @return HttpClient
     */
    protected function getHttpAdapterMock($url, $content, $statusCode = 200)
    {
        $response = $this->getResponse($content, $statusCode);

        $adapter = $this->createMock(HttpClient::class);

        $adapter
            ->expects($this->once())
            ->method('sendRequest')
            ->willReturn($response);

        return $adapter;
    }

    /**
     * Create a mocked Response.
     *
     * @param string $content    The body content
     * @param int    $statusCode HTTP status code
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    private function getResponse($content, $statusCode)
    {
        $body = $this->createMock(StreamInterface::class);
        $body
            ->expects($this->once())
            ->method('__toString')
            ->willReturn($content);

        $response = $this->createMock(ResponseInterface::class);
        $response
            ->expects($this->once())
            ->method('getBody')
            ->willReturn($body);

        $response
            ->expects($this->once())
            ->method('getStatusCode')
            ->willReturn($statusCode);

        return $response;
    }
}
