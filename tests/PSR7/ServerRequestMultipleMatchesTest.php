<?php

declare(strict_types=1);

namespace OpenAPIValidationTests\PSR7;

use GuzzleHttp\Psr7\ServerRequest;
use OpenAPIValidation\PSR7\Exception\Request\MultipleOperationsMismatchForRequest;
use OpenAPIValidation\PSR7\ServerRequestValidator;

class ServerRequestMultipleMatchesTest extends BaseValidatorTest
{
    public function test_it_matches_single_operation_red() : void
    {
        // This matches at least two paths
        $specFile = __DIR__ . '/../stubs/multipleMatches.yaml';
        $request  = new ServerRequest('get', '/users/goodstring');

        $validator = ServerRequestValidator::fromYamlFile($specFile);
        $validator->validate($request);
        $this->addToAssertionCount(1);
    }

    public function test_it_checks_against_multiple_matched_operations_red() : void
    {
        // This matches at least two paths
        $specFile = __DIR__ . '/../stubs/multipleMatches.yaml';
        $request  = new ServerRequest('get', '/users/12.33');

        try {
            $validator = ServerRequestValidator::fromYamlFile($specFile);
            $validator->validate($request);
            $this->fail('Exception expected');
        } catch (MultipleOperationsMismatchForRequest $e) {
            $this->assertCount(2, $e->matchedAddrs());
            $this->assertEquals('/users/{id}', $e->matchedAddrs()[0]->path());
            $this->assertEquals('/users/{group}', $e->matchedAddrs()[1]->path());
        }
    }
}
