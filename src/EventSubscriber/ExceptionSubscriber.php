<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // Определяем, нужно ли возвращать JSON
        $request = $event->getRequest();
        if (
            str_contains($request->getPathInfo(), '/api/')
            && $request->getContentTypeFormat() === 'json'
            && $request->isMethod('POST')
        ) {
            $response = $this->createJsonResponse($exception);
            $event->setResponse($response);
        }
    }

    private function createJsonResponse(\Throwable $exception): JsonResponse
    {
        $statusCode = method_exists($exception, 'getStatusCode')
            ? $exception->getStatusCode()
            : Response::HTTP_BAD_REQUEST;

        $data = [
            'error' => [
                'code' => $statusCode,
                'message' => $exception->getMessage(),
            ]
        ];

        // В development окружении добавляем детальную информацию
        if ($_ENV['APP_ENV'] === 'dev') {
            $data['error']['trace'] = $exception->getTrace();
            $data['error']['file'] = $exception->getFile();
            $data['error']['line'] = $exception->getLine();
        }

        return new JsonResponse($data, $statusCode);
    }
}