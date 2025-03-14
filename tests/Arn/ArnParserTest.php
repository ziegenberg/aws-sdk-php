<?php
namespace Aws\Test\Arn;

use Aws\Arn\AccessPointArn;
use Aws\Arn\Arn;
use Aws\Arn\ArnParser;
use Aws\Arn\S3\AccessPointArn as S3AccessPointArn;
use Aws\Arn\S3\OutpostsAccessPointArn;
use Aws\Arn\S3\OutpostsBucketArn;
use Aws\Arn\S3\RegionalBucketArn;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\Aws\Arn\ArnParser::class)]
class ArnParserTest extends TestCase
{

    /**
     *
     * @param $string
     * @param $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('isArnCases')]
    public function testDeterminesShouldAttemptToParseAsArn($string, $expected)
    {
        $this->assertEquals($expected, ArnParser::isArn($string));
    }

    public static function isArnCases()
    {
        return [
            [
                'arn:aws:foo:us-west-2:123456789012:bar_type:baz_id',
                true
            ],
            [
                'arn:',
                true
            ],
            [
                'arn',
                false
            ],
            [
                'barn:aws:foo:us-west-2:123456789012:bar_type:baz_id',
                false
            ],
            [
                '',
                false
            ],
            [
                null,
                false
            ]
        ];
    }

    /**
     *
     * @param $string
     * @param $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('parsedArnCases')]
    public function testCorrectlyChoosesArnClass($string, $expected)
    {
        $this->assertTrue(ArnParser::parse($string) instanceof $expected);
    }

    public static function parsedArnCases()
    {
        return [
            [
                'arn:aws:foo:us-west-2:123456789012:bar_type:baz-id',
                Arn::class
            ],
            [
                'arn:aws:foo:us-west-2:123456789012:accesspoint:baz-id',
                AccessPointArn::class
            ],
            [
                'arn:aws:s3:us-west-2:123456789012:accesspoint:baz-id',
                S3AccessPointArn::class
            ],
            [
                'arn:aws:s3-outposts:us-west-2:123456789012:outpost:op-01234567890123456:accesspoint:myaccesspoint',
                OutpostsAccessPointArn::class
            ],
            [
                'arn:aws:s3-outposts:us-west-2:123456789012:outpost:op-01234567890123456:bucket:mybucket',
                OutpostsBucketArn::class,
            ],
        ];
    }
}
