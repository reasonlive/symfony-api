<?php

namespace App\Controller\Traits;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait ApiJsonResponseTrait
{
    public function ok(mixed $data): JsonResponse
    {
        return new JsonResponse([
            'data' => $data,
            'success' => true,
        ], Response::HTTP_OK);
    }

    public function fail(HttpException $exception): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'error' => $exception->getMessage(),
            'details' => method_exists($exception, 'getDetails')
                ? $exception->getDetails()
                : null,
        ], $exception->getStatusCode());
    }
}