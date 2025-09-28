<?php

namespace App\Controller\Exceptions;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class ValidationException extends BadRequestHttpException
{
    private array $details;
    public function __construct(mixed $details = [])
    {
        parent::__construct("Validation failed");

        if ($details instanceof ConstraintViolationListInterface) {
            foreach ($details as $detail) {
                $this->details[] = [
                    'field' => $detail->getPropertyPath(),
                    'message' => $detail->getMessage()
                ];
            }
        }
        else {
            $this->details = $details;
        }
    }

    public function getDetails(): array
    {
        return $this->details;
    }
}