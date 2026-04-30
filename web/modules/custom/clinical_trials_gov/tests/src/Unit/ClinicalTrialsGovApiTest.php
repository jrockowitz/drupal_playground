<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Unit;

use Drupal\clinical_trials_gov\ClinicalTrialsGovApi;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for ClinicalTrialsGovApi.
 *
 * @coversDefaultClass \Drupal\clinical_trials_gov\ClinicalTrialsGovApi
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovApiTest extends UnitTestCase {

  /**
   * The API service under test.
   */
  protected ClinicalTrialsGovApi $api;

  /**
   * The mocked HTTP client.
   */
  protected ClientInterface&MockObject $httpClient;

  /**
   * The mocked logger.
   */
  protected LoggerInterface&MockObject $logger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->httpClient = $this->createMock(ClientInterface::class);
    $this->logger = $this->createMock(LoggerInterface::class);
    $this->api = new ClinicalTrialsGovApi($this->httpClient, $this->logger);
  }

  /**
   * Tests that get() returns decoded JSON from a successful response.
   *
   * @covers ::get
   */
  public function testGetReturnsDecodedJson(): void {
    $data = ['studies' => [['nctId' => 'NCT001']], 'totalCount' => 1];
    $this->httpClient
      ->expects($this->once())
      ->method('request')
      ->with('GET', 'https://clinicaltrials.gov/api/v2/studies', [
        'query' => ['query.cond' => 'cancer'],
        'headers' => ['Accept' => 'application/json'],
      ])
      ->willReturn(new Response(200, [], json_encode($data)));

    $result = $this->api->get('/studies', ['query.cond' => 'cancer']);

    // Check that the decoded JSON is returned as-is.
    $this->assertSame($data, $result);
  }

  /**
   * Tests that get() returns an empty array when the API returns JSON null.
   *
   * @covers ::get
   */
  public function testGetReturnsEmptyArrayForNullResponse(): void {
    $this->httpClient
      ->method('request')
      ->willReturn(new Response(200, [], 'null'));

    $result = $this->api->get('/version');

    // Check that null JSON is normalized to an empty array.
    $this->assertSame([], $result);
  }

  /**
   * Tests that get() with no parameters omits the query string.
   *
   * @covers ::get
   */
  public function testGetWithNoParameters(): void {
    $this->httpClient
      ->expects($this->once())
      ->method('request')
      ->with('GET', 'https://clinicaltrials.gov/api/v2/version', [
        'query' => [],
        'headers' => ['Accept' => 'application/json'],
      ])
      ->willReturn(new Response(200, [], '{"dataTimestamp":"2024-01-01"}'));

    $result = $this->api->get('/version');

    // Check that the decoded response is returned.
    $this->assertSame(['dataTimestamp' => '2024-01-01'], $result);
  }

  /**
   * Tests that get() returns an empty array and logs on HTTP error.
   *
   * @covers ::get
   */
  public function testGetReturnsEmptyArrayOnHttpError(): void {
    $this->httpClient
      ->method('request')
      ->willThrowException(new RequestException('Service unavailable', new Request('GET', '/studies')));

    $this->logger
      ->expects($this->once())
      ->method('error');

    $result = $this->api->get('/studies', ['query.cond' => 'cancer']);

    // Check that an HTTP error degrades gracefully to an empty array.
    $this->assertSame([], $result);
  }

}
