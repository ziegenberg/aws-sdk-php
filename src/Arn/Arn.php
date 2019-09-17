<?php
namespace Aws\Arn;

use Aws\Arn\Exception\InvalidArnException;

class Arn
{
    private $data;
    private $string;

    /**
     * @todo Replace with cross-SDK standard when available
     *
     * @param $string
     * @return bool
     */
    public static function isArn($string)
    {
        return strpos($string, 'arn:') === 0;
    }

    public static function parse($string)
    {
        $input = explode(':', $string);
        $count = count($input);

        if ($count < 6) {
            throw new InvalidArnException("ARNs must contain at least 6 components delimited by ':'.");
        }

        $data = [
            'arn' => $input[0] ?: null,
            'partition' => $input[1] ?: null,
            'service' => $input[2] ?: null,
            'region' => $input[3] ?: null,
            'account_id' => $input[4] ?: null,
        ];

        if ($count === 6) {
            // Some ARNs may use '/' as delimiter between resource type and ID
            if ($pos = strpos($data[5], '/') > 0) {
                $data['resource_type'] = substr($input[5], 0, $pos);
                $data['resource_id'] = substr($input[5], $pos);
            } else {
                // ARNs which only have 6 sections omit resource type
                $data['resource_id'] = $input[5];
                $data['resource_type'] = null;
            }
        } else {
            $data['resource_type'] = $input[5];
        }

        if ($count === 7) {
            $data['resource_id'] = $input[6];
        }

        if ($count > 7) {

            // Consolidate sections after the 7th, as the delimiter character
            // was used in the resource ID
            $data['resource_id'] = $input[6];

            for ($i = 7; $i < $count - 1; $i++) {
                $data['resource_id'] .= ":{$input[$i]}";
            }
        }

        return $data;
    }

    public function __construct($data)
    {
        if (is_array($data)) {
            $this->data = $data;
        } elseif (is_string($data)) {
            $this->string = $data;
            $this->data = self::parse($data);
        } else {
            throw new InvalidArnException('Constructor accepts a string or an array as an argument.');
        }

        self::validate($this->data);
    }

    public function __toString()
    {
        if (!isset($this->string)) {
            $this->string = '';
        }
        return $this->string;
    }

    public function getPrefix()
    {
        return $this->data['arn'];
    }

    public function getPartition()
    {
        return $this->data['partition'];
    }

    public function getService()
    {
        return $this->data['service'];
    }

    public function getRegion()
    {
        return $this->data['region'];
    }

    public function getAccountId()
    {
        return $this->data['account_id'];
    }

    public function getResourceType()
    {
        return $this->data['resource_type'];
    }

    public function getResourceId()
    {
        return $this->data['resource_id'];
    }

    public function toArray()
    {
        return $this->data;
    }

    /**
     * Minimally restrictive generic ARN validation
     *
     * @param array $data
     */
    private static function validate(array $data)
    {
        if ($data['arn'] !== 'arn') {
            throw new InvalidArnException("The 1st component of an ARN must be 'arn'.");
        }

        if (empty($data['partition'])) {
            throw new InvalidArnException("The 2nd component of an ARN represents the partition and must not be empty.");
        }

        if (empty($data['service'])) {
            throw new InvalidArnException("The 3rd component of an ARN represents the service and must not be empty.");
        }

        if (empty($data['resource_id'])) {
            throw new InvalidArnException("The 6th or 7th component of an ARN represents the resource ID and must not be empty.");
        }
    }
}