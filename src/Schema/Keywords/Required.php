<?php

declare(strict_types=1);

namespace OpenAPIValidation\Schema\Keywords;

use cebe\openapi\spec\Schema as CebeSchema;
use Exception;
use OpenAPIValidation\Schema\Exception\ValidationKeywordFailed;
use Respect\Validation\Validator;
use Throwable;
use function array_unique;
use function count;
use function sprintf;

class Required extends BaseKeyword
{
    /** @var int this can be Validator::VALIDATE_AS_REQUEST or Validator::VALIDATE_AS_RESPONSE */
    protected $validationDataType;

    public function __construct(CebeSchema $parentSchema, int $type)
    {
        parent::__construct($parentSchema);
        $this->validationDataType = $type;
    }

    /**
     * The value of this keyword MUST be an array.  This array MUST have at
     * least one element.  Elements of this array MUST be strings, and MUST
     * be unique.
     *
     * An object instance is valid against this keyword if its property set
     * contains all elements in this keyword's array value.
     *
     * @param mixed    $data
     * @param string[] $required
     */
    public function validate($data, array $required) : void
    {
        try {
            Validator::arrayType()->assert($data);
            Validator::arrayType()->assert($required);
            Validator::each(Validator::stringType())->assert($required);
            Validator::trueVal()->assert(count(array_unique($required)) === count($required));

            foreach ($required as $reqProperty) {
                $propertyFound = false;
                foreach ($data as $property => $value) {
                    $propertyFound = $propertyFound || ($reqProperty === $property);
                }

                if (! $propertyFound) {
                    // respect writeOnly/readOnly keywords
                    if ((
                            $this->parentSchema->properties[$reqProperty]->writeOnly &&
                            $this->validationDataType === \OpenAPIValidation\Schema\Validator::VALIDATE_AS_RESPONSE
                        )
                        ||
                        (
                            $this->parentSchema->properties[$reqProperty]->readOnly &&
                            $this->validationDataType === \OpenAPIValidation\Schema\Validator::VALIDATE_AS_REQUEST
                        )
                    ) {
                        continue;
                    }

                    throw new Exception(sprintf("Required property '%s' must be present in the object", $reqProperty));
                }
            }
        } catch (Throwable $e) {
            throw ValidationKeywordFailed::fromKeyword('required', $data, $e->getMessage(), $e);
        }
    }
}
