<?php

declare(strict_types=1);

namespace OpenAPIValidation\PSR7;

use cebe\openapi\Reader;
use cebe\openapi\ReferenceContext;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\PathItem;
use cebe\openapi\spec\Response as ResponseSpec;
use GuzzleHttp\Psr7\ServerRequest;
use OpenAPIValidation\PSR7\Exception\NoOperation;
use OpenAPIValidation\PSR7\Exception\NoPath;
use OpenAPIValidation\PSR7\Exception\NoResponseCode;
use Psr\Http\Message\ServerRequestInterface;
use function realpath;

abstract class Validator
{
    /** @var OpenApi */
    protected $openApi;

    protected function __construct(OpenApi $schema)
    {
        $this->openApi = $schema;
    }

    public static function fromYaml(string $yaml) : self
    {
        $oas = Reader::readFromYaml($yaml);
        $oas->resolveReferences(new ReferenceContext($oas, '/'));

        return new static($oas);
    }

    public static function fromJson(string $json) : self
    {
        $oas = Reader::readFromJson($json);
        $oas->resolveReferences(new ReferenceContext($oas, '/'));

        return new static($oas);
    }

    public static function fromYamlFile(string $yamlFile) : self
    {
        \Respect\Validation\Validator::file()->assert($yamlFile);

        $oas = Reader::readFromYamlFile($yamlFile);
        $oas->resolveReferences(new ReferenceContext($oas, realpath($yamlFile)));

        return new static($oas);
    }

    public static function fromJsonFile(string $jsonFile) : self
    {
        \Respect\Validation\Validator::file()->assert($jsonFile);

        $oas = Reader::readFromJsonFile($jsonFile);
        $oas->resolveReferences(new ReferenceContext($oas, realpath($jsonFile)));

        return new static($oas);
    }

    /**
     * Find the schema which describes a given response
     */
    protected function findResponseSpec(ResponseAddress $addr) : ResponseSpec
    {
        $operation = $this->findOperationSpec($addr->getOperationAddress());

        $response = $operation->responses->getResponse($addr->responseCode());
        if (! $response) {
            throw NoResponseCode::fromPathAndMethodAndResponseCode($addr->path(), $addr->method(), $addr->responseCode());
        }

        return $response;
    }

    /**
     * Find a particualr operation (path + method) in the spec
     */
    protected function findOperationSpec(OperationAddress $addr) : Operation
    {
        $pathSpec = $this->findPathSpec($addr);

        if (! isset($pathSpec->getOperations()[$addr->method()])) {
            throw NoOperation::fromPathAndMethod($addr->path(), $addr->method());
        }

        return $pathSpec->getOperations()[$addr->method()];
    }

    /**
     * Find a particualr path in the spec
     */
    protected function findPathSpec(PathAddress $addr) : PathItem
    {
        $pathSpec = $this->openApi->paths->getPath($addr->path());

        if (! $pathSpec) {
            throw NoPath::fromPath($addr->path());
        }

        return $pathSpec;
    }

    /**
     * Check the openapi spec and find matching operations(path+method)
     * This should consider path parameters as well
     * "/users/12" should match both ["/users/{id}", "/users/{group}"]
     *
     * @param ServerRequest $request
     *
     * @return OperationAddress[]
     */
    protected function findMatchingOperations(ServerRequestInterface $request) : array
    {
        $pathFinder = new PathFinder($this->openApi, $request->getUri(), $request->getMethod());

        return $pathFinder->search();
    }
}
