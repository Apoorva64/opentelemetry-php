# BillingApi\DefaultApi

All URIs are relative to http://localhost:8003, except if the operation defines another base path.

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**capturePayment()**](DefaultApi.md#capturePayment) | **POST** /v1/billing/payments/{paymentId}/capture | Capture payment |
| [**createPaymentIntent()**](DefaultApi.md#createPaymentIntent) | **POST** /v1/billing/payment-intents | Create payment intent |
| [**createRefund()**](DefaultApi.md#createRefund) | **POST** /v1/billing/refunds | Create refund |
| [**getBillingHealth()**](DefaultApi.md#getBillingHealth) | **GET** /v1/billing/health | Health check |
| [**getPaymentIntent()**](DefaultApi.md#getPaymentIntent) | **GET** /v1/billing/payment-intents/{id} | Get payment intent |


## `capturePayment()`

```php
capturePayment($payment_id)
```

Capture payment

Captures a payment and notifies the order service

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');



$apiInstance = new BillingApi\Api\DefaultApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client()
);
$payment_id = 'payment_id_example'; // string | Payment intent ID

try {
    $apiInstance->capturePayment($payment_id);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->capturePayment: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **payment_id** | **string**| Payment intent ID | |

### Return type

void (empty response body)

### Authorization

No authorization required

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: Not defined

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `createPaymentIntent()`

```php
createPaymentIntent($create_payment_intent_request): \BillingApi\Model\CreatePaymentIntent201Response
```

Create payment intent

Creates a new payment intent for an order

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');



$apiInstance = new BillingApi\Api\DefaultApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client()
);
$create_payment_intent_request = new \BillingApi\Model\CreatePaymentIntentRequest(); // \BillingApi\Model\CreatePaymentIntentRequest

try {
    $result = $apiInstance->createPaymentIntent($create_payment_intent_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->createPaymentIntent: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **create_payment_intent_request** | [**\BillingApi\Model\CreatePaymentIntentRequest**](../Model/CreatePaymentIntentRequest.md)|  | |

### Return type

[**\BillingApi\Model\CreatePaymentIntent201Response**](../Model/CreatePaymentIntent201Response.md)

### Authorization

No authorization required

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `createRefund()`

```php
createRefund($create_refund_request)
```

Create refund

Creates a refund for a captured payment

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');



$apiInstance = new BillingApi\Api\DefaultApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client()
);
$create_refund_request = new \BillingApi\Model\CreateRefundRequest(); // \BillingApi\Model\CreateRefundRequest

try {
    $apiInstance->createRefund($create_refund_request);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->createRefund: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **create_refund_request** | [**\BillingApi\Model\CreateRefundRequest**](../Model/CreateRefundRequest.md)|  | |

### Return type

void (empty response body)

### Authorization

No authorization required

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: Not defined

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `getBillingHealth()`

```php
getBillingHealth(): \BillingApi\Model\GetBillingHealth200Response
```

Health check

Returns the health status of the billing service

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');



$apiInstance = new BillingApi\Api\DefaultApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client()
);

try {
    $result = $apiInstance->getBillingHealth();
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->getBillingHealth: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

This endpoint does not need any parameter.

### Return type

[**\BillingApi\Model\GetBillingHealth200Response**](../Model/GetBillingHealth200Response.md)

### Authorization

No authorization required

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `getPaymentIntent()`

```php
getPaymentIntent($id): \BillingApi\Model\PaymentIntent
```

Get payment intent

Retrieves payment intent details

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');



$apiInstance = new BillingApi\Api\DefaultApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client()
);
$id = 'id_example'; // string | Payment intent ID

try {
    $result = $apiInstance->getPaymentIntent($id);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->getPaymentIntent: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **string**| Payment intent ID | |

### Return type

[**\BillingApi\Model\PaymentIntent**](../Model/PaymentIntent.md)

### Authorization

No authorization required

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)
