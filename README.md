# Symfony REST-приложение для расчета цены продукта и проведения оплаты

# Public API Controller

Контроллер предоставляет API endpoints для расчета стоимости товаров и обработки покупок.

## Base URL
http://127.0.0.1:8337

## Endpoints

### 1. Расчет стоимости товара

**Endpoint:** `POST /api/calculate-price`

Рассчитывает итоговую стоимость товара с учетом налога и купона.

#### Request Body
```json
{
    "product": 1,
    "taxNumber": "DE123456789",
    "couponCode": "D15"
}
```
product (integer, required) - ID товара

taxNumber (string, required) - Налоговый номер страны (формат должен соответствовать шаблону страны)

couponCode (string, optional) - Код купона на скидку

#### Response
##### Успешный ответ (200 OK):
```json
{
  "success": true,
  "data": {
    "product": "Product Name",
    "price": 119.0,
    "coupon": "D15",
    "tax": "19%"
  }
}
```

##### 400 Bad Request - Ошибки валидации:
```json
{
        "success": false,
        "error": "Validation failed",
        "details": [
                {
                "field": "product",
                "message": "Product ID must be a positive number"
                }
        ]
}
```


##### 404 Not Found - Товар не найден или неверный налоговый номер:
```json
{
        "success": false,
        "error": "Product not found"
}
```

### 2. Обработка покупки

**Endpoint:** `POST /api/purchase`

Выполняет процесс покупки товара, включая расчет стоимости и обработку платежа.

#### Request Body:
```json
{
    "product": 1,
    "taxNumber": "DE123456789",
    "couponCode": "D15",
    "paymentProcessor": "paypal",
    "amount": 119.0
}
```
product (integer, required) - ID товара

taxNumber (string, required) - Налоговый номер страны

couponCode (string, optional) - Код купона на скидку

paymentProcessor (string, required) - Платежная система (paypal или stripe)

amount (float, required) - Сумма платежа

#### Response
##### Успешный ответ (200 OK):
```json
{
    "success": true,
    "data": {
        "message": "Payment processed successfully!"
    }
}
```

##### 400 Bad Request - Ошибки валидации:
```json
{
    "success": false,
    "error": "Validation failed",
    "details": [
        {
            "field": "paymentProcessor",
            "message": "Need appropriate payment processor"
        }
    ]
}
```

##### 404 Not Found - Товар не найден или неверный налоговый номер

##### 422 Unprocessable Entity - Недостаточно средств или ошибка платежа:
```json
{
    "success": false,
    "error": "Insufficient funds"
}
```

## Особенности реализации
#### Валидация купонов
Купон считается валидным если:

Купон существует в системе

Купон активен (не истек срок действия)

Купон привязан к конкретному товару

#### Расчет стоимости
Итоговая цена рассчитывается с учетом:

Базовой цены товара

Налога страны (определяется по формату налогового номера)

Скидки по купону (если применим)

#### Обработка ошибок
Все ошибки возвращаются в стандартизированном формате с соответствующими HTTP статусами.

## Структура DTO

#### CalculatePriceRequestDto
product - ID товара (обязательный, положительное число)

taxNumber - Налоговый номер (обязательный)

couponCode - Код купона (опциональный)

#### PurchaseRequestDto
product - ID товара (обязательный, положительное число)

taxNumber - Налоговый номер (обязательный)

couponCode - Код купона (опциональный)

paymentProcessor - Платежная система (обязательный, paypal/stripe)

amount - Сумма платежа (обязательный, положительное число)

#### Исключения
ValidationException - Ошибки валидации входных данных (400)

PaymentFailedException - Ошибки обработки платежа (422)

NotFoundHttpException - Ресурс не найден (404)




