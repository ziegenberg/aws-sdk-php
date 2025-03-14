<?php
namespace Aws\Test\Api\Serializer;

use Aws\Api\Service;
use Aws\AwsClient;
use Aws\Signature\SignatureInterface;
use Aws\Test\UsesServiceTrait;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\Aws\Api\Serializer\QuerySerializer::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\Aws\Api\Serializer\JsonRpcSerializer::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\Aws\Api\Serializer\RestSerializer::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\Aws\Api\Serializer\RestJsonSerializer::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\Aws\Api\Serializer\RestXmlSerializer::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\Aws\Api\Serializer\JsonBody::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\Aws\Api\Serializer\XmlBody::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\Aws\Api\Serializer\Ec2ParamBuilder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\Aws\Api\Serializer\QueryParamBuilder::class)]
class ComplianceTest extends TestCase
{
    use UsesServiceTrait;

    #[\PHPUnit\Framework\Attributes\DoesNotPerformAssertions]
    public static function testCaseProvider()
    {
        $cases = [];

        $files = glob(__DIR__ . '/../test_cases/protocols/input/*.json');
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            foreach ($data as $suite) {
                $suite['metadata']['type'] = $suite['metadata']['protocol'];
                foreach ($suite['cases'] as $case) {
                    $serviceData = [
                        'metadata' => $suite['metadata'],
                        'shapes' => $suite['shapes'],
                        'operations' => [
                            $case['given']['name'] => $case['given']
                        ]
                    ];
                    $description = new Service(
                        $serviceData,
                        function () { return []; }
                    );
                    $cases[] = [
                        $file . ': ' . $suite['description'],
                        $description,
                        $case['given']['name'],
                        isset($case['params']) ? $case['params'] : [],
                        $case['serialized'],
                        isset($suite['clientEndpoint'])
                            ? $suite['clientEndpoint']
                            : null
                    ];
                }
            }
        }

        return $cases;
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('testCaseProvider')]
    public function testPassesComplianceTest(
        $about,
        Service $service,
        $name,
        array $args,
        $serialized,
        $clientEndpoint
    ) {
        $ep = !empty($clientEndpoint)
            ? $clientEndpoint
            : 'http://us-east-1.foo.amazonaws.com';
        $client = new AwsClient([
            'service'      => 'foo',
            'api_provider' => function () use ($service) {
                return $service->toArray();
            },
            'credentials'  => false,
            'signature'    => $this->getMockBuilder(SignatureInterface::class)->getMock(),
            'region'       => 'us-west-2',
            'endpoint'     => $ep,
            'error_parser' => Service::createErrorParser($service->getProtocol()),
            'serializer'   => Service::createSerializer($service, $ep),
            'version'      => 'latest',
            'validate'     => false,
            'idempotency_auto_fill' => function ($length) {
                return str_repeat(chr(0x00), $length);
            }
        ]);

        $command = $client->getCommand($name, $args);
        $request = \Aws\serialize($command);
        $this->assertEquals($serialized['uri'], $request->getRequestTarget());

        $body = (string) $request->getBody();
        switch ($service->getMetadata('type')) {
            case 'json':
            case 'rest-json':
                // Normalize the JSON data.
                $body = str_replace(['":', ','], ['": ', ', '], $body);
                $body = str_replace(',  ', ', ', $body);
                break;
            case 'rest-xml':
                // Normalize XML data.
                if ($serialized['body'] && preg_match('/(\<\/|\/\>)/', $serialized['body'])) {
                    $serialized['body'] = str_replace(
                        ' />',
                        '/>',
                        '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
                            . $serialized['body']
                    );
                    $body = trim($body);
                }
                break;
        }

        $this->assertEquals($serialized['body'], $body);

        if (isset($serialized['host'])) {
            $this->assertEquals($serialized['host'], $request->getUri()->getHost());
        }

        if (isset($serialized['headers'])) {
            foreach ($serialized['headers'] as $key => $value) {
                $this->assertSame($value, $request->getHeaderLine($key));
            }
        }
        if (isset($serialized['forbidHeaders'])) {
            foreach ($serialized['forbidHeaders'] as $key => $value) {
                $this->assertTrue(!isset($request->getHeaders()[$key]));
            }
        }
    }
}
